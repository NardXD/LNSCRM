<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Models\ContractStatusHistory;
use App\Models\GmailIntegration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ContractController extends Controller
{
    public function index(): View
    {
        return view('dashboard.contracts');
    }

    public function getContracts(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Contract::where('company_id', $user->company_id)
            ->with(['client', 'user', 'signers'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $contracts = $query->paginate($request->integer('per_page', 10));

        $data = $contracts->map(fn (Contract $contract) => $this->formatContractListItem($contract));

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $contracts->currentPage(),
                'last_page' => $contracts->lastPage(),
                'per_page' => $contracts->perPage(),
                'total' => $contracts->total(),
            ],
        ]);
    }

    public function getStats(): JsonResponse
    {
        $companyId = Auth::user()->company_id;

        $total = Contract::where('company_id', $companyId)->count();
        $pending = Contract::where('company_id', $companyId)
            ->whereIn('status', ['pending_signatures', 'partially_signed'])
            ->count();
        $signed = Contract::where('company_id', $companyId)->where('status', 'signed')->count();
        $draft = Contract::where('company_id', $companyId)->where('status', 'draft')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'pending' => $pending,
                'signed' => $signed,
                'draft' => $draft,
            ],
        ]);
    }

    public function getClients(): JsonResponse
    {
        $companyId = Auth::user()->company_id;

        $clients = Client::where('company_id', $companyId)
            ->with(['contacts:id,client_id,name,email,role'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'contact_person']);

        return response()->json(['success' => true, 'data' => $clients]);
    }

    public function getNextContractNumber(): JsonResponse
    {
        $companyId = Auth::user()->company_id;

        return response()->json([
            'success' => true,
            'data' => ['contract_number' => Contract::generateContractNumber($companyId)],
        ]);
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $user = Auth::user();

        $client = Client::where('company_id', $user->company_id)
            ->where('id', $request->client_id)
            ->first();

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $contract = DB::transaction(function () use ($request, $user) {
            $contract = Contract::create([
                'company_id' => $user->company_id,
                'client_id' => $request->client_id,
                'user_id' => $user->id,
                'contract_number' => Contract::generateContractNumber($user->company_id),
                'title' => $request->title,
                'content' => $request->content,
                'status' => 'draft',
                'effective_date' => $request->effective_date,
                'expiry_date' => $request->expiry_date,
            ]);

            $this->syncSigners($contract, $request->signers);
            $this->recordStatusChange($contract, 'draft', null, $user->id, 'Contract created');

            return $contract->load(['client', 'signers']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Contract created successfully.',
            'data' => $this->formatContractDetail($contract),
        ], 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        if ($response = $this->authorizeContract($contract)) {
            return $response;
        }

        $contract->load(['client', 'user', 'signers', 'statusHistory.user']);

        return response()->json([
            'success' => true,
            'data' => $this->formatContractDetail($contract),
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        if ($response = $this->authorizeContract($contract)) {
            return $response;
        }

        if (! in_array($contract->status, ['draft', 'pending_signatures'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft or pending contracts can be edited.',
            ], 422);
        }

        $user = Auth::user();

        if ($request->filled('client_id')) {
            $client = Client::where('company_id', $user->company_id)
                ->where('id', $request->client_id)
                ->first();

            if (! $client) {
                return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
            }
        }

        DB::transaction(function () use ($request, $contract, $user) {
            $previousStatus = $contract->status;

            $contract->update($request->only([
                'client_id', 'title', 'content', 'effective_date', 'expiry_date',
            ]));

            if ($request->has('signers') && $contract->status === 'draft') {
                $contract->signers()->delete();
                $this->syncSigners($contract, $request->signers);
            }

            $this->recordStatusChange($contract, $contract->status, $previousStatus, $user->id, 'Contract updated');
        });

        return response()->json([
            'success' => true,
            'message' => 'Contract updated successfully.',
            'data' => $this->formatContractDetail($contract->fresh()->load(['client', 'signers'])),
        ]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        if ($response = $this->authorizeContract($contract)) {
            return $response;
        }

        if (! in_array($contract->status, ['draft', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft or cancelled contracts can be deleted.',
            ], 422);
        }

        $contract->delete();

        return response()->json(['success' => true, 'message' => 'Contract deleted successfully.']);
    }

    public function sendForSignature(Contract $contract): JsonResponse
    {
        if ($response = $this->authorizeContract($contract)) {
            return $response;
        }

        $user = Auth::user();

        if (! $user->hasPermission('send_contracts')) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to send contracts.'], 403);
        }

        if (! in_array($contract->status, ['draft', 'pending_signatures', 'partially_signed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This contract cannot be sent for signature.',
            ], 422);
        }

        $gmailIntegration = GmailIntegration::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->first();

        if (! $gmailIntegration) {
            return response()->json([
                'success' => false,
                'message' => 'Gmail integration is not configured. Please configure it in Integrations.',
            ], 400);
        }

        $contract->load(['client', 'company', 'signers']);

        if ($contract->signers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Add at least one signer before sending.',
            ], 422);
        }

        try {
            $appPassword = Crypt::decryptString($gmailIntegration->app_password);
            $this->configureMail($gmailIntegration, $contract->company->name ?? 'Company');

            $previousStatus = $contract->status;
            $sentCount = 0;

            foreach ($contract->signers as $signer) {
                if ($signer->status === 'signed') {
                    continue;
                }

                $signer->generateSigningToken();
                $signingUrl = route('contracts.sign', ['token' => $signer->token]);

                $emailHtml = view('emails.contract-signing', [
                    'contract' => $contract,
                    'signer' => $signer,
                    'signingUrl' => $signingUrl,
                    'company' => $contract->company,
                ])->render();

                Mail::html($emailHtml, function ($message) use ($signer, $contract, $gmailIntegration) {
                    $message->from($gmailIntegration->email, $contract->company->name ?? 'Company')
                        ->to($signer->email, $signer->name)
                        ->subject('Please sign: '.$contract->title);
                });

                $sentCount++;
            }

            $newStatus = $contract->status === 'draft' ? 'pending_signatures' : $contract->status;

            $contract->update([
                'status' => $newStatus,
                'sent_at' => now(),
            ]);

            $this->recordStatusChange(
                $contract,
                $newStatus,
                $previousStatus,
                $user->id,
                "Contract sent for signature to {$sentCount} signer(s)"
            );

            return response()->json([
                'success' => true,
                'message' => "Contract sent to {$sentCount} signer(s).",
                'data' => $this->formatContractDetail($contract->fresh()->load(['client', 'signers'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send contract: '.$e->getMessage(),
            ], 500);
        }
    }

    public function pdf(Contract $contract): Response
    {
        if ($contract->company_id !== Auth::user()->company_id) {
            abort(404);
        }

        $contract->load(['client', 'company', 'signers']);

        $pdf = Pdf::loadView('contract.pdf', ['contract' => $contract])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);

        return $pdf->download('contract-'.$contract->contract_number.'.pdf');
    }

    public function showSigningPage(string $token): View
    {
        $signer = ContractSigner::where('token', $token)
            ->with(['contract.client', 'contract.company', 'contract.signers'])
            ->firstOrFail();

        $contract = $signer->contract;
        $expired = $signer->token_expires_at && $signer->token_expires_at->isPast();
        $alreadySigned = $signer->status === 'signed';
        $contractComplete = $contract->status === 'signed';
        $invalid = ! $signer->isTokenValid() && ! $alreadySigned;

        return view('contract.sign', [
            'signer' => $signer,
            'contract' => $contract,
            'token' => $token,
            'expired' => $expired,
            'alreadySigned' => $alreadySigned,
            'contractComplete' => $contractComplete,
            'invalid' => $invalid,
        ]);
    }

    public function submitSignature(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'string'],
            'method' => ['required', 'in:draw,type,upload'],
        ]);

        $signer = ContractSigner::where('token', $token)
            ->with('contract.signers')
            ->first();

        if (! $signer || ! $signer->isTokenValid()) {
            return response()->json([
                'success' => false,
                'message' => 'This signing link is invalid or has expired.',
            ], 403);
        }

        $contract = $signer->contract;

        if (in_array($contract->status, ['signed', 'cancelled', 'expired'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This contract is no longer available for signing.',
            ], 422);
        }

        $signatureData = $request->signature;
        if (! str_starts_with($signatureData, 'data:image/')) {
            return response()->json(['success' => false, 'message' => 'Invalid signature format.'], 422);
        }

        $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $signatureData);
        $decoded = base64_decode($imageData, true);

        if ($decoded === false) {
            return response()->json(['success' => false, 'message' => 'Invalid signature data.'], 422);
        }

        $path = "contracts/{$contract->id}/signatures/{$signer->id}.png";
        Storage::disk('local')->put($path, $decoded);

        $previousStatus = $contract->status;

        DB::transaction(function () use ($signer, $contract, $path, $request, $previousStatus) {
            $signer->update([
                'status' => 'signed',
                'signed_at' => now(),
                'signature_path' => $path,
                'signature_ip' => $request->ip(),
                'signature_method' => $request->method,
            ]);

            if ($contract->allSignersSigned()) {
                $contract->update([
                    'status' => 'signed',
                    'signed_at' => now(),
                ]);
                $this->recordStatusChange($contract, 'signed', $previousStatus, null, 'All parties have signed');
            } else {
                $contract->update(['status' => 'partially_signed']);
                $this->recordStatusChange($contract, 'partially_signed', $previousStatus, null, $signer->name.' signed the contract');
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your signature has been recorded.',
            'data' => ['contract_status' => $contract->fresh()->status],
        ]);
    }

    public function cancel(Contract $contract): JsonResponse
    {
        if ($response = $this->authorizeContract($contract)) {
            return $response;
        }

        if ($contract->status === 'signed') {
            return response()->json([
                'success' => false,
                'message' => 'Signed contracts cannot be cancelled.',
            ], 422);
        }

        $previousStatus = $contract->status;
        $contract->update(['status' => 'cancelled']);
        $this->recordStatusChange($contract, 'cancelled', $previousStatus, Auth::id(), 'Contract cancelled');

        return response()->json([
            'success' => true,
            'message' => 'Contract cancelled.',
            'data' => $this->formatContractDetail($contract->fresh()->load(['client', 'signers'])),
        ]);
    }

    public function getStatusHistory(Contract $contract): JsonResponse
    {
        if ($response = $this->authorizeContract($contract)) {
            return $response;
        }

        $history = ContractStatusHistory::where('contract_id', $contract->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'status' => $item->status,
                'previous_status' => $item->previous_status,
                'notes' => $item->notes,
                'changed_by' => $item->user ? $item->user->name : 'System',
                'changed_at' => $item->created_at->format('Y-m-d H:i:s'),
                'changed_at_formatted' => $item->created_at->format('M d, Y h:i A'),
            ]);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    protected function syncSigners(Contract $contract, array $signers): void
    {
        foreach ($signers as $index => $signerData) {
            ContractSigner::create([
                'contract_id' => $contract->id,
                'name' => $signerData['name'],
                'email' => $signerData['email'],
                'role' => $signerData['role'] ?? 'client',
                'signing_order' => $signerData['signing_order'] ?? ($index + 1),
                'status' => 'pending',
            ]);
        }
    }

    protected function recordStatusChange(
        Contract $contract,
        string $status,
        ?string $previousStatus,
        ?int $userId,
        ?string $notes = null
    ): void {
        ContractStatusHistory::create([
            'contract_id' => $contract->id,
            'user_id' => $userId,
            'status' => $status,
            'previous_status' => $previousStatus,
            'notes' => $notes,
        ]);
    }

    protected function authorizeContract(Contract $contract): ?JsonResponse
    {
        if ($contract->company_id !== Auth::user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Contract not found.'], 404);
        }

        return null;
    }

    protected function configureMail(GmailIntegration $gmailIntegration, string $fromName): void
    {
        $appPassword = Crypt::decryptString($gmailIntegration->app_password);

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.encryption', 'tls');
        Config::set('mail.mailers.smtp.username', $gmailIntegration->email);
        Config::set('mail.mailers.smtp.password', $appPassword);
        Config::set('mail.from.address', $gmailIntegration->email);
        Config::set('mail.from.name', $fromName);
    }

    protected function formatContractListItem(Contract $contract): array
    {
        $signedCount = $contract->signers->where('status', 'signed')->count();
        $totalSigners = $contract->signers->count();

        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'title' => $contract->title,
            'client' => $contract->client->name,
            'client_id' => $contract->client_id,
            'status' => $contract->status,
            'effective_date' => $contract->effective_date?->format('M d, Y'),
            'created_by' => $contract->user->name,
            'created_at' => $contract->created_at->format('M d, Y'),
            'signers_progress' => "{$signedCount}/{$totalSigners}",
        ];
    }

    protected function formatContractDetail(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'title' => $contract->title,
            'content' => $contract->content,
            'status' => $contract->status,
            'client_id' => $contract->client_id,
            'client' => $contract->client?->only(['id', 'name', 'email']),
            'effective_date' => $contract->effective_date?->format('Y-m-d'),
            'expiry_date' => $contract->expiry_date?->format('Y-m-d'),
            'sent_at' => $contract->sent_at?->toIso8601String(),
            'signed_at' => $contract->signed_at?->toIso8601String(),
            'created_by' => $contract->user?->name,
            'signers' => $contract->signers->map(fn (ContractSigner $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'role' => $s->role,
                'signing_order' => $s->signing_order,
                'status' => $s->status,
                'signed_at' => $s->signed_at?->toIso8601String(),
            ]),
            'status_history' => $contract->statusHistory?->map(fn ($h) => [
                'status' => $h->status,
                'previous_status' => $h->previous_status,
                'notes' => $h->notes,
                'changed_by' => $h->user?->name ?? 'System',
                'changed_at_formatted' => $h->created_at->format('M d, Y h:i A'),
            ]) ?? [],
        ];
    }
}
