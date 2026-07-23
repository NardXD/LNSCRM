<?php

namespace App\Http\Controllers;

use App\Models\HiringCandidate;
use App\Models\HiringQueueItem;
use App\Models\HiringQueueItemComment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HiringQueueController extends Controller
{
    private const QUEUE_STATUSES = ['open', 'cancel', 'pending', 'close'];

    private const CANDIDATE_STATUSES = ['rejected', 'pending', 'accepted'];

    public function index(): View
    {
        return view('dashboard.hiring-queue');
    }

    public function getItems(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = HiringQueueItem::where('company_id', $user->company_id)
            ->withCount(['candidates', 'comments'])
            ->orderByDesc('created_at');

        $perPage = (int) $request->get('per_page', 10);
        $items = $query->paginate($perPage);

        return response()->json([
            'items' => collect($items->items())->map(fn ($item) => [
                'id' => $item->id,
                'job_title' => $this->normalizeJobTitle($item->job_title),
                'full_description' => $item->full_description,
                'source' => $item->source,
                'client_email' => $item->client_email,
                'status' => $this->normalizeQueueStatus($item->status),
                'created_by' => $item->created_by ?: 'Unknown',
                'created_at' => $item->created_at->format('M j, Y'),
                'candidates_count' => $item->candidates_count,
                'comments_count' => $item->comments_count,
            ])->all(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'job_title' => 'required|string|max:255',
            'full_description' => 'required|string',
            'source' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
        ]);

        $item = HiringQueueItem::create([
            'company_id' => $user->company_id,
            'job_title' => $this->normalizeJobTitle($validated['job_title']),
            'full_description' => $validated['full_description'],
            'source' => $this->resolveQueueSource($validated['source'] ?? null, $user?->company?->name, $validated['client_email'] ?? null),
            'client_email' => $this->normalizeClientEmail($validated['client_email'] ?? null),
            'status' => 'open',
            'created_by' => $this->resolveCreatorName(),
        ]);

        return response()->json(['item' => $item, 'message' => 'Added to hiring queue.']);
    }

    public function update(Request $request, HiringQueueItem $item): JsonResponse
    {
        $this->authorizeCompany($item);
        $validated = $request->validate([
            'job_title' => 'required|string|max:255',
            'full_description' => 'required|string',
            'client_email' => 'nullable|email|max:255',
        ]);

        $item->update([
            'job_title' => $this->normalizeJobTitle($validated['job_title']),
            'full_description' => $validated['full_description'],
            'client_email' => $this->normalizeClientEmail($validated['client_email'] ?? null),
        ]);

        return response()->json([
            'item' => ['id' => $item->id, 'job_title' => $item->job_title],
            'message' => 'Queue item updated.',
        ]);
    }

    public function getComments(HiringQueueItem $item): JsonResponse
    {
        $this->authorizeCompany($item);
        $comments = $item->comments()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'comments' => $comments->map(fn ($comment) => $this->formatComment($comment))->all(),
        ]);
    }

    public function storeComment(Request $request, HiringQueueItem $item): JsonResponse
    {
        $this->authorizeCompany($item);
        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $user = Auth::user();
        $comment = $item->comments()->create([
            'user_id' => $user->id,
            'content' => trim($validated['content']),
        ]);

        $comment->load('user');

        return response()->json([
            'comment' => $this->formatComment($comment),
            'message' => 'Comment added.',
        ], 201);
    }

    public function destroyComment(HiringQueueItem $item, HiringQueueItemComment $comment): JsonResponse
    {
        $this->authorizeCompany($item);
        $this->authorizeComment($item, $comment);
        $this->authorizeCommentDeletion($comment);

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted.',
        ]);
    }

    public function storeFromAssistant(Request $request): JsonResponse
    {
        $company = $request->get('company') ?? (app()->bound('company') ? app('company') : null);
        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $validated = $request->validate([
            'job_title' => 'required|string|max:255',
            'full_description' => 'required|string',
            'source' => 'nullable|string|max:255',
            'client_email' => 'required|email|max:255',
            'creator_name' => 'nullable|string|max:255',
        ]);

        $cleanDescription = $this->sanitizeAssistantDescription($validated['full_description']);
        $parsedJobTitle = $this->extractJobTitleFromDescription($cleanDescription);

        $item = HiringQueueItem::create([
            'company_id' => $company->id,
            'job_title' => $this->normalizeJobTitle($parsedJobTitle ?: $validated['job_title']),
            'full_description' => $cleanDescription,
            'source' => $this->resolveQueueSource($validated['source'] ?? null, $company->name, $validated['client_email'] ?? null),
            'client_email' => $this->normalizeClientEmail($validated['client_email'] ?? null),
            'status' => 'open',
            'created_by' => $this->resolveCreatorName($validated['creator_name'] ?? 'Prompted via Hiring Assistant'),
        ]);

        return response()->json(['item' => $item, 'message' => 'Job description added to hiring queue.']);
    }

    public function getCandidates(HiringQueueItem $item): JsonResponse
    {
        $this->authorizeCompany($item);
        $candidates = $item->candidates()->orderByDesc('created_at')->get();

        return response()->json([
            'candidates' => $candidates->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'interview_date' => $c->interview_date?->format('Y-m-d'),
                'notes' => $c->notes,
                'status' => $this->normalizeCandidateStatus($c->status),
            ]),
        ]);
    }

    public function storeCandidate(Request $request, HiringQueueItem $item): JsonResponse
    {
        $this->authorizeCompany($item);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'interview_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => ['nullable', 'string', Rule::in(self::CANDIDATE_STATUSES)],
        ]);

        $validated['status'] = $validated['status'] ?? 'pending';
        $candidate = $item->candidates()->create($validated);

        return response()->json(['candidate' => $candidate, 'message' => 'Candidate added.']);
    }

    public function updateCandidate(Request $request, HiringQueueItem $item, HiringCandidate $candidate): JsonResponse
    {
        $this->authorizeCompany($item);
        $this->authorizeCandidate($item, $candidate);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'interview_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => ['nullable', 'string', Rule::in(self::CANDIDATE_STATUSES)],
        ]);

        $validated['status'] = $validated['status'] ?? 'pending';
        $candidate->update($validated);

        return response()->json([
            'candidate' => $candidate,
            'message' => 'Candidate updated.',
        ]);
    }

    public function updateStatus(Request $request, HiringQueueItem $item): JsonResponse
    {
        $this->authorizeCompany($item);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::QUEUE_STATUSES)],
        ]);

        $item->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'item' => [
                'id' => $item->id,
                'status' => $item->status,
            ],
            'message' => 'Queue status updated.',
        ]);
    }

    public function updateCandidateStatus(Request $request, HiringQueueItem $item, HiringCandidate $candidate): JsonResponse
    {
        $this->authorizeCompany($item);
        $this->authorizeCandidate($item, $candidate);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::CANDIDATE_STATUSES)],
        ]);

        $candidate->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'candidate' => [
                'id' => $candidate->id,
                'status' => $candidate->status,
            ],
            'message' => 'Candidate status updated.',
        ]);
    }

    public function show(HiringQueueItem $item): JsonResponse
    {
        $this->authorizeCompany($item);

        return response()->json([
            'item' => [
                'id' => $item->id,
                'job_title' => $this->normalizeJobTitle($item->job_title),
                'full_description' => $item->full_description,
                'source' => $item->source,
                'client_email' => $item->client_email,
                'status' => $this->normalizeQueueStatus($item->status),
                'created_by' => $item->created_by ?: 'Unknown',
                'created_at' => $item->created_at->format('M j, Y'),
            ],
        ]);
    }

    public function pdf(HiringQueueItem $item)
    {
        $this->authorizeCompany($item);
        $company = Auth::user()?->company;

        $pdf = Pdf::loadView('hiring-queue.pdf', [
            'item' => $item,
            'company' => $company,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);

        $filename = 'job-description-'.preg_replace('/[^a-z0-9\-]/i', '-', $item->job_title).'.pdf';

        return $pdf->download($filename);
    }

    private function formatComment(HiringQueueItemComment $comment): array
    {
        $author = $comment->user?->name ?: 'Unknown';

        return [
            'id' => $comment->id,
            'author' => $author,
            'initials' => $this->getInitials($author),
            'content' => $comment->content,
            'created_at' => $comment->created_at->diffForHumans(),
            'can_delete' => $this->canDeleteComment($comment),
        ];
    }

    private function canDeleteComment(HiringQueueItemComment $comment): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $comment->user_id === $user->id || $user->isAdmin();
    }

    private function getInitials(string $name): string
    {
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr($words[count($words) - 1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    private function authorizeCompany(HiringQueueItem $item): void
    {
        $user = Auth::user();
        if ($user && $item->company_id !== $user->company_id) {
            abort(403);
        }
    }

    private function authorizeCandidate(HiringQueueItem $item, HiringCandidate $candidate): void
    {
        if ($candidate->hiring_queue_item_id !== $item->id) {
            abort(404);
        }
    }

    private function authorizeComment(HiringQueueItem $item, HiringQueueItemComment $comment): void
    {
        if ($comment->hiring_queue_item_id !== $item->id) {
            abort(404);
        }
    }

    private function authorizeCommentDeletion(HiringQueueItemComment $comment): void
    {
        if (! $this->canDeleteComment($comment)) {
            abort(403, 'You can only delete your own comments.');
        }
    }

    private function normalizeQueueStatus(?string $status): string
    {
        if (in_array($status, self::QUEUE_STATUSES, true)) {
            return $status;
        }

        return match ($status) {
            'confirmed' => 'open',
            'closed' => 'close',
            default => 'pending',
        };
    }

    private function normalizeCandidateStatus(?string $status): string
    {
        if (in_array($status, self::CANDIDATE_STATUSES, true)) {
            return $status;
        }

        return 'pending';
    }

    private function sanitizeAssistantDescription(string $description): string
    {
        $clean = trim($description);

        if (preg_match('/Job Description\s*:/i', $clean, $matches, PREG_OFFSET_CAPTURE)) {
            $clean = substr($clean, (int) $matches[0][1]);
        }

        $clean = preg_replace('/^\s*---+\s*$/m', '', $clean) ?? $clean;
        $clean = preg_replace('/\n?\s*Let me know if.*$/is', '', $clean) ?? $clean;
        $clean = preg_replace("/\n{3,}/", "\n\n", $clean) ?? $clean;

        return trim($clean);
    }

    private function extractJobTitleFromDescription(string $description): ?string
    {
        if (preg_match('/Job Description\s*:\s*(.+?)(?:\r?\n|$)/i', $description, $matches)) {
            $title = trim($matches[1]);

            return $title !== '' ? $title : null;
        }

        return null;
    }

    private function resolveCreatorName(?string $fallback = null): string
    {
        $user = Auth::user();

        if ($user) {
            return trim($user->name ?: ($user->email ?: ('User #'.$user->id)));
        }

        if ($fallback && trim($fallback) !== '') {
            return trim($fallback);
        }

        return 'Unknown';
    }

    private function resolveQueueSource(?string $requestedSource = null, ?string $companyName = null, ?string $clientEmail = null): string
    {
        $user = Auth::user();
        $normalizedRequested = trim((string) $requestedSource);

        // For "client" source, prefer a real client identifier.
        if ($normalizedRequested !== '' && ! in_array(strtolower($normalizedRequested), ['client', 'sales_rep'], true)) {
            return $this->truncateSource($normalizedRequested);
        }

        if (! empty($clientEmail)) {
            return $this->truncateSource($clientEmail);
        }

        if ($user && ! empty($user->email)) {
            return $this->truncateSource($user->email);
        }

        if (! empty($companyName)) {
            return $this->truncateSource($companyName);
        }

        return 'Client';
    }

    private function truncateSource(string $value): string
    {
        return mb_substr(trim($value), 0, 50);
    }

    private function normalizeClientEmail(?string $email): ?string
    {
        $value = trim((string) $email);

        return $value !== '' ? mb_substr($value, 0, 255) : null;
    }

    private function normalizeJobTitle(?string $title): string
    {
        $value = trim((string) $title);
        $value = preg_replace('/^\s*job\s+description\s*:\s*/i', '', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : 'Job Position';
    }
}
