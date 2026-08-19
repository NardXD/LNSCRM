<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\FacebookConversation;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadIdentity;
use App\Models\LeadLabel;
use App\Models\LeadNote;
use App\Models\User;
use App\Services\ContactConversationHistoryService;
use App\Services\LeadActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeadsController extends Controller
{
    public function __construct(
        protected LeadActivityService $leadActivity
    ) {}

    public function index(): View
    {
        return view('dashboard.leads');
    }

    public function list(Request $request): JsonResponse
    {
        $companyId = (int) Auth::user()->company_id;
        $query = Lead::query()
            ->where('company_id', $companyId)
            ->with(['identities', 'assignedUser:id,name', 'labels'])
            ->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhereHas('identities', function ($identity) use ($search) {
                        $identity->where('value', 'like', "%{$search}%")
                            ->orWhere('normalized_value', 'like', "%{$search}%");
                    })
                    ->orWhereHas('labels', function ($label) use ($search) {
                        $label->where('lead_labels.name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && $request->get('status') !== 'all') {
            $query->where('status', $request->get('status'));
        }

        $labelIds = $request->input('label_ids', $request->input('label_id'));
        if (! is_array($labelIds)) {
            $labelIds = $labelIds !== null && $labelIds !== ''
                ? preg_split('/\s*,\s*/', (string) $labelIds)
                : [];
        }
        $labelIds = array_values(array_unique(array_filter(array_map('intval', $labelIds))));
        foreach ($labelIds as $labelId) {
            $query->whereHas('labels', fn ($label) => $label->where('lead_labels.id', $labelId));
        }

        $perPage = min(100, max(10, (int) $request->get('per_page', 20)));
        $leads = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($leads->items())->map(fn (Lead $lead) => $this->serialize($lead))->all(),
            'pagination' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
            ],
        ]);
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = (int) $user->company_id;
        $identities = $this->identityPayload($request);

        if ($conflict = $this->findIdentityConflict($companyId, $identities)) {
            return $this->conflictResponse($conflict);
        }

        $lead = Lead::create([
            'company_id' => $companyId,
            'assigned_to' => $this->assignedToForCompany($companyId, $request->input('assigned_to')),
            'name' => $request->string('name')->toString(),
            'company_name' => $request->input('company_name'),
            'status' => $request->input('status') ?: 'new',
            'source' => $request->input('source'),
        ]);

        $legacyNote = trim((string) $request->input('notes', ''));
        if ($legacyNote !== '') {
            LeadNote::create([
                'lead_id' => $lead->id,
                'user_id' => Auth::id(),
                'note' => $legacyNote,
            ]);
        }

        $lead->syncIdentities($identities);
        $this->applyFacebookConversationName($lead, $request);
        $this->leadActivity->recordCreated($lead, $request->input('source') ?: 'manual');
        if ($lead->assigned_to) {
            $this->leadActivity->recordAssignment($lead, null, $lead->assigned_to);
        }
        if ($legacyNote !== '') {
            $this->leadActivity->recordNote($lead, true);
        }
        $lead->load(['identities', 'assignedUser:id,name', 'labels', 'leadNotes.user:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Lead created.',
            'data' => $this->serializeWithActivity($lead),
        ], 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $lead->load(['identities', 'assignedUser:id,name', 'labels', 'leadNotes.user:id,name']);

        return response()->json([
            'success' => true,
            'data' => $this->serializeWithActivity($lead),
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $identities = $this->identityPayload($request);
        $before = $this->leadActivity->snapshot($lead);

        if ($conflict = $this->findIdentityConflict((int) $lead->company_id, $identities, $lead->id)) {
            return $this->conflictResponse($conflict);
        }

        $lead->update([
            'assigned_to' => $this->assignedToForCompany((int) $lead->company_id, $request->input('assigned_to')),
            'name' => $request->string('name')->toString(),
            'company_name' => $request->input('company_name'),
            'status' => $request->input('status') ?: $lead->status,
            'source' => $request->input('source'),
        ]);

        $lead->syncIdentities($identities);
        $this->leadActivity->recordDiff($lead, $before);
        $lead->load(['identities', 'assignedUser:id,name', 'labels', 'leadNotes.user:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Lead updated.',
            'data' => $this->serializeWithActivity($lead),
        ]);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted.',
        ]);
    }

    public function history(Lead $lead, ContactConversationHistoryService $history): JsonResponse
    {
        $lead = $this->leadForUser($lead);

        return response()->json($history->history(
            (int) $lead->company_id,
            null,
            null,
            100,
            null,
            $lead->id
        ));
    }

    public function listActivities(Request $request, Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $perPage = min(50, max(10, (int) $request->get('per_page', 20)));
        $page = $lead->activities()
            ->with('user:id,name')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn (LeadActivity $activity) => $this->serializeActivity($activity))->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function labels(): JsonResponse
    {
        $companyId = (int) Auth::user()->company_id;
        $labels = LeadLabel::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (LeadLabel $label) => $this->serializeLabel($label));

        return response()->json(['success' => true, 'data' => $labels]);
    }

    public function assignees(): JsonResponse
    {
        $companyId = (int) Auth::user()->company_id;
        $users = User::query()
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function assign(Request $request, Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $fromId = $lead->assigned_to;
        $toId = $this->assignedToForCompany((int) $lead->company_id, $validated['assigned_to'] ?? null);
        $lead->update(['assigned_to' => $toId]);
        $this->leadActivity->recordAssignment($lead, $fromId, $toId);
        $lead->load(['identities', 'assignedUser:id,name', 'labels']);

        return response()->json([
            'success' => true,
            'message' => 'Lead assigned.',
            'data' => $this->serializeWithActivity($lead),
        ]);
    }

    public function storeNote(Request $request, Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $note = LeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'note' => $validated['note'],
        ]);
        $note->load('user:id,name');
        $this->leadActivity->recordNote($lead, true);
        $lead->touch();

        return response()->json([
            'success' => true,
            'message' => 'Note added.',
            'data' => $this->serializeNote($note),
        ], 201);
    }

    public function destroyNote(Lead $lead, LeadNote $note): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        if ((int) $note->lead_id !== (int) $lead->id) {
            abort(404);
        }

        $note->delete();
        $this->leadActivity->recordNote($lead, false);
        $lead->touch();

        return response()->json(['success' => true, 'message' => 'Note deleted.']);
    }

    public function attachLabel(Request $request, Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $name = trim($validated['name']);
        $companyId = (int) $lead->company_id;
        $label = LeadLabel::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if (! $label) {
            $label = LeadLabel::create([
                'company_id' => $companyId,
                'name' => $name,
                'color' => $validated['color'] ?? $this->nextLabelColor($companyId),
            ]);
        }

        $alreadyAttached = $lead->labels()->where('lead_labels.id', $label->id)->exists();
        $lead->labels()->syncWithoutDetaching([$label->id]);
        if (! $alreadyAttached) {
            $this->leadActivity->recordLabel($lead, $label->name, true);
        }
        $lead->load('labels');
        $lead->touch();

        return response()->json([
            'success' => true,
            'message' => 'Label added.',
            'data' => $this->serializeLabel($label),
            'labels' => $lead->labels->map(fn (LeadLabel $item) => $this->serializeLabel($item))->values()->all(),
        ]);
    }

    public function detachLabel(Lead $lead, LeadLabel $leadLabel): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        if ((int) $leadLabel->company_id !== (int) $lead->company_id) {
            abort(404);
        }

        $lead->labels()->detach($leadLabel->id);
        $this->leadActivity->recordLabel($lead, $leadLabel->name, false);
        $lead->load('labels');
        $lead->touch();

        return response()->json([
            'success' => true,
            'message' => 'Label removed.',
            'labels' => $lead->labels->map(fn (LeadLabel $item) => $this->serializeLabel($item))->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Lead $lead): array
    {
        $phones = $lead->identities->where('type', LeadIdentity::TYPE_PHONE)->values();
        $emails = $lead->identities->where('type', LeadIdentity::TYPE_EMAIL)->values();

        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'initials' => $lead->initials,
            'company_name' => $lead->company_name,
            'status' => $lead->status,
            'source' => $lead->source,
            'assigned_to' => $lead->assigned_to,
            'assigned_user' => $lead->assignedUser ? [
                'id' => $lead->assignedUser->id,
                'name' => $lead->assignedUser->name,
            ] : null,
            'phones' => $phones->map(fn (LeadIdentity $i) => [
                'id' => $i->id,
                'value' => $i->value,
                'label' => $i->label,
                'is_primary' => $i->is_primary,
            ])->values()->all(),
            'emails' => $emails->map(fn (LeadIdentity $i) => [
                'id' => $i->id,
                'value' => $i->value,
                'label' => $i->label,
                'is_primary' => $i->is_primary,
            ])->values()->all(),
            'facebook_name' => $lead->identities->firstWhere('type', LeadIdentity::TYPE_FACEBOOK)?->value,
            'instagram_username' => $lead->identities->firstWhere('type', LeadIdentity::TYPE_INSTAGRAM)?->value,
            'labels' => $lead->relationLoaded('labels')
                ? $lead->labels->map(fn (LeadLabel $label) => $this->serializeLabel($label))->values()->all()
                : [],
            'notes' => $lead->relationLoaded('leadNotes')
                ? $lead->leadNotes->map(fn (LeadNote $note) => $this->serializeNote($note))->values()->all()
                : [],
            'crm_url' => url('/leads?lead='.$lead->id),
            'updated_at' => $lead->updated_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeWithActivity(Lead $lead): array
    {
        $latest = $lead->activities()->with('user:id,name')->orderByDesc('id')->first();
        $data = $this->serialize($lead);
        $data['latest_activity'] = $latest ? $this->serializeActivity($latest) : null;
        $data['activity_count'] = $lead->activities()->count();
        $data['activities'] = $latest ? [$data['latest_activity']] : [];

        return $data;
    }

    /**
     * @return list<array{type: string, value: string, label: ?string, is_primary: bool}>
     */
    protected function identityPayload(StoreLeadRequest $request): array
    {
        $items = [];
        $firstPhone = true;
        foreach ($request->input('phones', []) as $phone) {
            $items[] = [
                'type' => LeadIdentity::TYPE_PHONE,
                'value' => $phone['value'],
                'label' => $phone['label'] ?? null,
                'is_primary' => $firstPhone,
            ];
            $firstPhone = false;
        }

        $firstEmail = true;
        foreach ($request->input('emails', []) as $email) {
            $items[] = [
                'type' => LeadIdentity::TYPE_EMAIL,
                'value' => $email['value'],
                'label' => $email['label'] ?? null,
                'is_primary' => $firstEmail,
            ];
            $firstEmail = false;
        }

        $facebook = trim((string) $request->input('facebook_name', ''));
        if ($facebook !== '' && ! FacebookConversation::isPlaceholderName($facebook)) {
            $items[] = [
                'type' => LeadIdentity::TYPE_FACEBOOK,
                'value' => $facebook,
                'label' => null,
                'is_primary' => true,
            ];
        }

        $instagram = trim((string) $request->input('instagram_username', ''));
        if ($instagram !== '' && ! FacebookConversation::isPlaceholderName($instagram)) {
            $items[] = [
                'type' => LeadIdentity::TYPE_INSTAGRAM,
                'value' => $instagram,
                'label' => null,
                'is_primary' => true,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{type: string, value: string, label: ?string, is_primary: bool}>  $identities
     */
    protected function findIdentityConflict(int $companyId, array $identities, ?int $exceptLeadId = null): ?LeadIdentity
    {
        foreach ($identities as $item) {
            if (! in_array($item['type'], [LeadIdentity::TYPE_PHONE, LeadIdentity::TYPE_EMAIL], true)) {
                continue;
            }

            $normalized = LeadIdentity::normalize($item['type'], $item['value']);
            if ($normalized === '') {
                continue;
            }

            $match = LeadIdentity::query()
                ->where('type', $item['type'])
                ->where('normalized_value', $normalized)
                ->whereHas('lead', fn ($q) => $q->where('company_id', $companyId))
                ->when($exceptLeadId, fn ($q) => $q->where('lead_id', '!=', $exceptLeadId))
                ->with('lead:id,name')
                ->first();

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    protected function conflictResponse(LeadIdentity $conflict): JsonResponse
    {
        $kind = $conflict->type === LeadIdentity::TYPE_EMAIL ? 'email' : 'phone number';

        return response()->json([
            'success' => false,
            'message' => "That {$kind} is already on lead \"{$conflict->lead?->name}\".",
            'existing_lead_id' => $conflict->lead_id,
        ], 422);
    }

    /**
     * @return array{id: int, name: string, color: string}
     */
    protected function serializeLabel(LeadLabel $label): array
    {
        return [
            'id' => $label->id,
            'name' => $label->name,
            'color' => $label->color,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeNote(LeadNote $note): array
    {
        return [
            'id' => $note->id,
            'note' => $note->note,
            'user_id' => $note->user_id,
            'author' => $note->user?->name ?: 'Unknown',
            'created_at' => $note->created_at?->toIso8601String(),
            'time_ago' => $note->created_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeActivity(LeadActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'action' => $activity->action,
            'summary' => $activity->summary,
            'meta' => $activity->meta ?? [],
            'actor' => $activity->user?->name ?: 'System',
            'user_id' => $activity->user_id,
            'created_at' => $activity->created_at?->toIso8601String(),
            'time_ago' => $activity->created_at?->diffForHumans(),
        ];
    }

    protected function nextLabelColor(int $companyId): string
    {
        $palette = ['#4338ca', '#0f766e', '#b45309', '#be123c', '#1d4ed8', '#7c3aed', '#0f7b4c', '#c2410c'];
        $count = LeadLabel::query()->where('company_id', $companyId)->count();

        return $palette[$count % count($palette)];
    }

    protected function applyFacebookConversationName(Lead $lead, StoreLeadRequest $request): void
    {
        $conversationId = (int) $request->input('facebook_conversation_id', 0);
        if ($conversationId < 1) {
            return;
        }

        $conversation = FacebookConversation::query()
            ->where('company_id', $lead->company_id)
            ->find($conversationId);

        if (! $conversation || ! FacebookConversation::isPlaceholderName($conversation->name)) {
            return;
        }

        $conversation->name = $lead->name;
        $conversation->save();
    }

    protected function assignedToForCompany(int $companyId, mixed $assignedTo): ?int
    {
        if (! $assignedTo) {
            return null;
        }

        $exists = User::query()
            ->where('company_id', $companyId)
            ->where('id', (int) $assignedTo)
            ->exists();

        return $exists ? (int) $assignedTo : null;
    }

    protected function leadForUser(Lead $lead): Lead
    {
        if ((int) $lead->company_id !== (int) Auth::user()->company_id) {
            abort(404);
        }

        return $lead;
    }
}
