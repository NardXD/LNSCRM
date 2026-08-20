<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\FacebookConversation;
use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadIdentity;
use App\Models\LeadLabel;
use App\Models\LeadNote;
use App\Models\LeadRule;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\ContactConversationHistoryService;
use App\Services\FlexCrmLookupService;
use App\Services\LeadActivityService;
use App\Services\LeadInboxAttachService;
use App\Services\LeadRuleEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeadsController extends Controller
{
    public function __construct(
        protected LeadActivityService $leadActivity,
        protected FlexCrmLookupService $crmLookup,
        protected LeadInboxAttachService $inboxAttach
    ) {}

    public function index(): View
    {
        return view('dashboard.leads', [
            'canManageLeadRules' => Auth::user()?->hasPermission('create_lead_rules') ?? false,
            'leadFormOptions' => Lead::formOptions(),
        ]);
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
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('alt_first_name', 'like', "%{$search}%")
                    ->orWhere('alt_last_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
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
        } else {
            $query->where('status', '!=', Lead::STATUS_ARCHIVED);
        }

        $source = trim((string) $request->get('source', ''));
        if ($source === '__none__') {
            $query->where(function ($q) {
                $q->whereNull('source')->orWhere('source', '');
            });
        } elseif ($source !== '') {
            $query->where('source', $source);
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

        $assignedTo = trim((string) $request->get('assigned_to', ''));
        if ($assignedTo === '__none__') {
            $query->whereNull('assigned_to');
        } elseif ($assignedTo !== '' && ctype_digit($assignedTo)) {
            $query->where('assigned_to', (int) $assignedTo);
        }

        $perPage = min(100, max(10, (int) $request->get('per_page', 20)));
        $leads = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($leads->items())->map(fn (Lead $lead) => $this->serialize($lead))->all(),
            'sources' => Lead::query()
                ->where('company_id', $companyId)
                ->whereNotNull('source')
                ->where('source', '!=', '')
                ->distinct()
                ->orderBy('source')
                ->pluck('source')
                ->values()
                ->all(),
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

        $lead = Lead::create(array_merge($this->profilePayload($request), [
            'company_id' => $companyId,
            'assigned_to' => $this->assignedToForCompany($companyId, $request->input('assigned_to')),
            'status' => $request->input('status') ?: 'new',
        ]));

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
        $this->inboxAttach->attachMany($lead, $request->input('inbox_conversation_ids', []), $user);
        if ($lead->assigned_to) {
            $this->leadActivity->recordAssignment($lead, null, $lead->assigned_to);
        }
        if ($legacyNote !== '') {
            $this->leadActivity->recordNote($lead, true, note: $legacyNote);
        }
        $lead->load(['identities', 'assignedUser:id,name', 'labels', 'leadNotes.user:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Lead created.',
            'data' => $this->serializeWithInbox($lead),
        ], 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $lead->load(['identities', 'assignedUser:id,name', 'labels', 'leadNotes.user:id,name']);

        return response()->json([
            'success' => true,
            'data' => $this->serializeWithInbox($lead),
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

        $status = $request->input('status') ?: $lead->status;
        $payload = array_merge($this->profilePayload($request), [
            'assigned_to' => $this->assignedToForCompany((int) $lead->company_id, $request->input('assigned_to')),
            'status' => $status,
        ]);
        if ($status !== Lead::STATUS_SNOOZED) {
            $payload['reopen_at'] = null;
            $payload['reopen_status'] = null;
        }
        $lead->update($payload);

        $lead->syncIdentities($identities);
        $this->leadActivity->recordDiff($lead, $before);
        $lead->load(['identities', 'assignedUser:id,name', 'labels', 'leadNotes.user:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Lead updated.',
            'data' => $this->serializeWithInbox($lead),
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

    public function listRules(): JsonResponse
    {
        $companyId = (int) Auth::user()->company_id;
        $rules = LeadRule::query()
            ->where('company_id', $companyId)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (LeadRule $rule) => $this->serializeRule($rule));

        return response()->json([
            'success' => true,
            'data' => $rules,
            'meta' => [
                'can_manage' => Auth::user()->hasPermission('create_lead_rules'),
                'triggers' => LeadRuleEngine::triggerLabels(),
                'channels' => LeadRuleEngine::CHANNELS,
                'statuses' => Lead::STATUSES,
                'inboxes' => SharedInbox::query()
                    ->where('company_id', $companyId)
                    ->where('type', SharedInbox::TYPE_SHARED)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'type'])
                    ->map(fn (SharedInbox $inbox) => [
                        'id' => $inbox->id,
                        'name' => $inbox->name,
                        'email' => $inbox->email,
                        'type' => $inbox->type,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        if ($denied = $this->denyUnlessLeadRulePermission()) {
            return $denied;
        }

        $validated = $this->validateRule($request);
        $rule = LeadRule::create([
            'company_id' => Auth::user()->company_id,
            'name' => $validated['name'],
            'priority' => $validated['priority'] ?? 100,
            'is_active' => $validated['is_active'] ?? true,
            'stop_processing' => $validated['stop_processing'] ?? false,
            'triggers' => array_values(array_unique($validated['triggers'])),
            'conditions' => $validated['conditions'],
            'actions' => $validated['actions'],
            'created_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'data' => $this->serializeRule($rule)], 201);
    }

    public function updateRule(Request $request, LeadRule $leadRule): JsonResponse
    {
        if ($denied = $this->denyUnlessLeadRulePermission()) {
            return $denied;
        }
        $this->leadRuleForUser($leadRule);

        if ($request->exists('is_active') && count($request->except(['_token', '_method'])) <= 1) {
            $leadRule->update(['is_active' => $request->boolean('is_active')]);

            return response()->json(['success' => true, 'data' => $this->serializeRule($leadRule->fresh())]);
        }

        $validated = $this->validateRule($request);
        $leadRule->update($validated);

        return response()->json(['success' => true, 'data' => $this->serializeRule($leadRule->fresh())]);
    }

    public function destroyRule(LeadRule $leadRule): JsonResponse
    {
        if ($denied = $this->denyUnlessLeadRulePermission()) {
            return $denied;
        }
        $this->leadRuleForUser($leadRule);
        $leadRule->delete();

        return response()->json(['success' => true]);
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

    public function searchInboxConversations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'except_lead_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->inboxAttach->search(
                $request->user(),
                (string) ($validated['q'] ?? ''),
                isset($validated['except_lead_id']) ? (int) $validated['except_lead_id'] : null
            ),
        ]);
    }

    public function listInboxConversations(Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);

        return response()->json([
            'success' => true,
            'data' => $this->inboxAttach->attached($lead),
        ]);
    }

    public function attachInboxConversation(Request $request, Lead $lead): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer'],
        ]);

        $conversation = $this->inboxAttach->conversationForLead($lead, (int) $validated['conversation_id']);
        $result = $this->inboxAttach->attach($lead, $conversation, $request->user());
        $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);

        return response()->json([
            'success' => true,
            'message' => 'Shared email attached.',
            'conversation' => $result['conversation'],
            'data' => $this->serializeWithInbox($lead),
        ]);
    }

    public function detachInboxConversation(Lead $lead, InboxConversation $conversation): JsonResponse
    {
        $lead = $this->leadForUser($lead);
        $this->inboxAttach->detach($lead, $conversation, Auth::user());
        $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);

        return response()->json([
            'success' => true,
            'message' => 'Shared email detached.',
            'data' => $this->inboxAttach->attached($lead),
        ]);
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

    public function storeLabel(Request $request): JsonResponse
    {
        $companyId = (int) Auth::user()->company_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $name = trim($validated['name']);
        if ($name === '') {
            return response()->json(['message' => 'Enter a label name.'], 422);
        }

        $existing = LeadLabel::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'That label already exists.',
                'data' => $this->serializeLabel($existing),
            ]);
        }

        $label = LeadLabel::create([
            'company_id' => $companyId,
            'name' => $name,
            'color' => $validated['color'] ?? $this->nextLabelColor($companyId),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Label created.',
            'data' => $this->serializeLabel($label),
        ], 201);
    }

    public function updateLabel(Request $request, LeadLabel $leadLabel): JsonResponse
    {
        $this->labelForUser($leadLabel);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if (array_key_exists('name', $validated)) {
            $name = trim($validated['name']);
            if ($name === '') {
                return response()->json(['message' => 'Enter a label name.'], 422);
            }
            $duplicate = LeadLabel::query()
                ->where('company_id', $leadLabel->company_id)
                ->whereKeyNot($leadLabel->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();
            if ($duplicate) {
                return response()->json(['message' => 'Another label already uses that name.'], 422);
            }
            $validated['name'] = $name;
        }

        $leadLabel->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->serializeLabel($leadLabel->fresh()),
        ]);
    }

    public function destroyLabel(LeadLabel $leadLabel): JsonResponse
    {
        $this->labelForUser($leadLabel);
        $leadLabel->delete();

        return response()->json(['success' => true]);
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
        $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);
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
        $this->leadActivity->recordNote($lead, true, note: $validated['note']);
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
            'label_id' => ['nullable', 'integer', 'exists:lead_labels,id'],
            'name' => ['nullable', 'required_without:label_id', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $companyId = (int) $lead->company_id;
        $label = null;
        if (! empty($validated['label_id'])) {
            $label = LeadLabel::query()
                ->where('company_id', $companyId)
                ->whereKey($validated['label_id'])
                ->first();
        }

        $name = trim((string) ($validated['name'] ?? ''));
        if (! $label && $name !== '') {
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
        }

        if (! $label) {
            return response()->json(['message' => 'Choose or type a label.'], 422);
        }

        $alreadyAttached = $lead->labels()->where('lead_labels.id', $label->id)->exists();
        $lead->labels()->syncWithoutDetaching([$label->id]);
        if (! $alreadyAttached) {
            $this->leadActivity->recordLabel($lead, $label->name, true, labelId: $label->id);
        }
        $lead->load('labels');
        $lead->touch();
        $this->crmLookup->forgetLeadIndexes($companyId);

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
        $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);

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
        [$primaryPhones, $altPhones] = $this->groupContactIdentities($phones);
        [$primaryEmails, $altEmails] = $this->groupContactIdentities($emails);
        $mapContact = fn (LeadIdentity $i) => [
            'id' => $i->id,
            'value' => $i->value,
            'label' => $i->label,
            'is_primary' => $i->is_primary,
        ];

        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'title' => $lead->title,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'address' => $lead->address,
            'city' => $lead->city,
            'postal_code' => $lead->postal_code,
            'date_of_birth' => $lead->date_of_birth?->format('Y-m-d'),
            'initials' => $lead->initials,
            'company_name' => $lead->company_name,
            'alt_title' => $lead->alt_title,
            'alt_first_name' => $lead->alt_first_name,
            'alt_last_name' => $lead->alt_last_name,
            'alt_address' => $lead->alt_address,
            'alt_city' => $lead->alt_city,
            'alt_postal_code' => $lead->alt_postal_code,
            'status' => $lead->status,
            'reopen_at' => $lead->reopen_at?->toIso8601String(),
            'reopen_status' => $lead->reopen_status,
            'source' => $lead->source,
            'customer_type' => $lead->customer_type,
            'residential_type' => $lead->residential_type,
            'business_industry' => $lead->business_industry,
            'business_industry_other' => $lead->business_industry_other,
            'storage_reason' => $lead->storage_reason,
            'storage_reason_other' => $lead->storage_reason_other,
            'assigned_to' => $lead->assigned_to,
            'assigned_user' => $lead->assignedUser ? [
                'id' => $lead->assignedUser->id,
                'name' => $lead->assignedUser->name,
            ] : null,
            'phone' => $primaryPhones->first()?->value,
            'email' => $primaryEmails->first()?->value,
            'alt_phone' => $altPhones->first()?->value,
            'alt_email' => $altEmails->first()?->value,
            'primary_phones' => $primaryPhones->map($mapContact)->values()->all(),
            'primary_emails' => $primaryEmails->map($mapContact)->values()->all(),
            'alt_phones' => $altPhones->map($mapContact)->values()->all(),
            'alt_emails' => $altEmails->map($mapContact)->values()->all(),
            'phones' => $phones->map($mapContact)->values()->all(),
            'emails' => $emails->map($mapContact)->values()->all(),
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
     * @return array<string, mixed>
     */
    protected function serializeWithInbox(Lead $lead): array
    {
        $data = $this->serializeWithActivity($lead);
        $data['attached_inbox_conversations'] = $this->inboxAttach->attached($lead);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function profilePayload(StoreLeadRequest $request): array
    {
        $customerType = $this->nullableString($request->input('customer_type'));
        $industry = $this->nullableString($request->input('business_industry'));
        $reason = $this->nullableString($request->input('storage_reason'));
        $first = $this->nullableString($request->input('first_name'));
        $last = $this->nullableString($request->input('last_name'));
        $name = trim(($first ?? '').' '.($last ?? ''));
        if ($name === '') {
            $name = $request->string('name')->toString();
        }

        return [
            'name' => $name,
            'title' => $this->nullableString($request->input('title'), 10),
            'first_name' => $first,
            'last_name' => $last,
            'address' => $this->nullableString($request->input('address'), 500),
            'city' => $this->nullableString($request->input('city')),
            'postal_code' => $this->nullableString($request->input('postal_code'), 20),
            'date_of_birth' => $this->nullableString($request->input('date_of_birth'), 10),
            'company_name' => $this->nullableString($request->input('company_name')),
            'alt_title' => $this->nullableString($request->input('alt_title'), 10),
            'alt_first_name' => $this->nullableString($request->input('alt_first_name')),
            'alt_last_name' => $this->nullableString($request->input('alt_last_name')),
            'alt_address' => $this->nullableString($request->input('alt_address'), 500),
            'alt_city' => $this->nullableString($request->input('alt_city')),
            'alt_postal_code' => $this->nullableString($request->input('alt_postal_code'), 20),
            'source' => $this->nullableString($request->input('source')),
            'customer_type' => $customerType,
            'residential_type' => $customerType === Lead::CUSTOMER_TYPE_RESIDENTIAL
                ? $this->nullableString($request->input('residential_type'), 30)
                : null,
            'business_industry' => $customerType === Lead::CUSTOMER_TYPE_BUSINESS ? $industry : null,
            'business_industry_other' => $customerType === Lead::CUSTOMER_TYPE_BUSINESS && $industry === 'Other'
                ? $this->nullableString($request->input('business_industry_other'))
                : null,
            'storage_reason' => $reason,
            'storage_reason_other' => $reason === 'Other'
                ? $this->nullableString($request->input('storage_reason_other'))
                : null,
        ];
    }

    protected function nullableString(mixed $value, int $max = 255): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LeadIdentity>  $identities
     * @return array{0: \Illuminate\Support\Collection<int, LeadIdentity>, 1: \Illuminate\Support\Collection<int, LeadIdentity>}
     */
    protected function groupContactIdentities($identities): array
    {
        $primary = $identities->filter(fn (LeadIdentity $identity) => strcasecmp((string) $identity->label, 'Primary') === 0)->values();
        $alt = $identities->filter(fn (LeadIdentity $identity) => strcasecmp((string) $identity->label, 'Alternate') === 0)->values();
        $unlabeled = $identities->filter(function (LeadIdentity $identity) {
            $label = strtolower(trim((string) $identity->label));

            return $label !== 'primary' && $label !== 'alternate';
        })->values();

        $primary = $primary->concat($unlabeled)->values();

        return [$primary, $alt];
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

    protected function labelForUser(LeadLabel $label): LeadLabel
    {
        if ((int) $label->company_id !== (int) Auth::user()->company_id) {
            abort(404);
        }

        return $label;
    }

    protected function leadRuleForUser(LeadRule $rule): LeadRule
    {
        if ((int) $rule->company_id !== (int) Auth::user()->company_id) {
            abort(404);
        }

        return $rule;
    }

    protected function denyUnlessLeadRulePermission(): ?JsonResponse
    {
        if (Auth::user()?->hasPermission('create_lead_rules')) {
            return null;
        }

        return response()->json([
            'message' => 'You do not have permission to manage lead rules.',
        ], 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateRule(Request $request, bool $partial = false): array
    {
        $triggerKeys = implode(',', array_keys(LeadRuleEngine::triggerLabels()));
        $required = $partial ? 'sometimes' : 'required';

        $validated = $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['boolean'],
            'stop_processing' => ['boolean'],
            'triggers' => [$required, 'array', 'min:1'],
            'triggers.*' => ['required', 'string', 'in:'.$triggerKeys],
            'conditions' => [$required, 'array', 'min:1'],
            'conditions.*.field' => ['required', 'in:channel,shared_inbox,inbox,contact_name,phone,email,subject,message,lead_status,lead_label,label_added,status_changed'],
            'conditions.*.operator' => ['required', 'in:contains,equals,starts_with,in'],
            'conditions.*.value' => ['nullable'],
            'actions' => [$required, 'array', 'min:1'],
            'actions.*.type' => ['required', 'in:create_lead,assign,add_label,set_status,notify_assignee,reopen_after_days'],
            'actions.*.value' => ['nullable'],
        ]);

        foreach ($validated['conditions'] ?? [] as $condition) {
            $field = $condition['field'] ?? '';
            if ($field === 'channel') {
                $ids = collect(is_array($condition['value'] ?? null) ? $condition['value'] : [])
                    ->map(fn ($id) => LeadRuleEngine::normalizeChannel((string) $id))
                    ->filter(fn ($id) => array_key_exists($id, LeadRuleEngine::CHANNELS));
                if ($ids->count() !== count((array) ($condition['value'] ?? []))) {
                    abort(response()->json(['message' => 'Choose valid channels.'], 422));
                }
                continue;
            }
            if ($field === 'shared_inbox' || $field === 'inbox') {
                $ids = collect(is_array($condition['value'] ?? null) ? $condition['value'] : [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();
                if ($ids->isEmpty()) {
                    continue;
                }
                $valid = SharedInbox::query()
                    ->where('company_id', Auth::user()->company_id)
                    ->where('type', SharedInbox::TYPE_SHARED)
                    ->whereIn('id', $ids)
                    ->count();
                if ($valid !== $ids->count()) {
                    abort(response()->json(['message' => 'Choose valid shared inboxes.'], 422));
                }
                continue;
            }
            if (trim((string) ($condition['value'] ?? '')) === '') {
                abort(response()->json(['message' => 'Each condition needs a value.'], 422));
            }
            if ($field === 'lead_status' && ! in_array((string) $condition['value'], Lead::STATUSES, true)) {
                abort(response()->json(['message' => 'Choose a valid lead status.'], 422));
            }
            if ($field === 'status_changed' && ! in_array((string) $condition['value'], Lead::STATUSES, true)) {
                abort(response()->json(['message' => 'Choose a valid lead status.'], 422));
            }
        }

        foreach ($validated['actions'] ?? [] as $action) {
            $type = $action['type'] ?? '';
            if ($type === 'assign') {
                $assignee = $action['value'] ?? '';
                if (is_array($assignee)) {
                    $mode = trim((string) ($assignee['mode'] ?? ''));
                    $userIds = collect(is_array($assignee['user_ids'] ?? null) ? $assignee['user_ids'] : [])
                        ->map(fn ($id) => (int) $id)
                        ->filter(fn ($id) => $id > 0)
                        ->unique()
                        ->values();
                    if ($mode !== LeadRuleEngine::ASSIGN_ROUND_ROBIN_SELECTED || $userIds->isEmpty()) {
                        abort(response()->json(['message' => 'Select teammates for round robin.'], 422));
                    }
                    $valid = User::query()
                        ->where('company_id', Auth::user()->company_id)
                        ->whereIn('id', $userIds)
                        ->count();
                    if ($valid !== $userIds->count()) {
                        abort(response()->json(['message' => 'Choose valid teammates for round robin.'], 422));
                    }
                    continue;
                }
                $assignee = (string) $assignee;
                $special = [
                    LeadRuleEngine::ASSIGN_AVAILABLE,
                    LeadRuleEngine::ASSIGN_AVAILABLE_ROUND_ROBIN,
                    LeadRuleEngine::ASSIGN_ROUND_ROBIN,
                ];
                if (! in_array($assignee, $special, true) && ! $this->assignedToForCompany((int) Auth::user()->company_id, $assignee)) {
                    abort(response()->json(['message' => 'Choose a teammate, round robin, or available inbound agents.'], 422));
                }
            }
            if (in_array($type, ['add_label', 'set_status'], true) && ($action['value'] === null || $action['value'] === '')) {
                abort(response()->json(['message' => 'That action needs a value.'], 422));
            }
            if ($type === 'set_status') {
                $status = (string) $action['value'];
                if ($status === Lead::STATUS_SNOOZED || ! in_array($status, Lead::STATUSES, true)) {
                    abort(response()->json(['message' => 'Choose a valid lead status.'], 422));
                }
            }
            if ($type === 'reopen_after_days') {
                $days = (int) $action['value'];
                if ($days < 1 || $days > 365) {
                    abort(response()->json(['message' => 'Choose how many days before reopen (1–365).'], 422));
                }
            }
            if ($type === 'create_lead' && is_array($action['value'] ?? null)) {
                foreach (['name', 'phone', 'email', 'name_keyword', 'phone_keyword', 'email_keyword'] as $key) {
                    if (mb_strlen(trim((string) ($action['value'][$key] ?? ''))) > 80) {
                        abort(response()->json(['message' => 'Keep create-lead keywords under 80 characters.'], 422));
                    }
                }
            }
        }

        if (! empty($validated['triggers'])) {
            $validated['triggers'] = array_values(array_unique($validated['triggers']));
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRule(LeadRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'priority' => $rule->priority,
            'is_active' => $rule->is_active,
            'stop_processing' => $rule->stop_processing,
            'triggers' => $rule->triggers ?? [],
            'conditions' => $rule->conditions ?? [],
            'actions' => $rule->actions ?? [],
            'last_applied_at' => $rule->last_applied_at?->toIso8601String(),
            'created_at' => $rule->created_at?->toIso8601String(),
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }
}
