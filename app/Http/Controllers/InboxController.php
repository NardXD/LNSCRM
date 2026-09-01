<?php

namespace App\Http\Controllers;

use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use App\Models\InboxConversationComment;
use App\Models\InboxMessage;
use App\Models\InboxTag;
use App\Models\InboxTemplate;
use App\Models\InboxUserSetting;
use App\Models\InboxConversationUserRead;
use App\Models\Lead;
use App\Models\LeadLabel;
use App\Models\OutlookMailAccount;
use App\Models\ScheduledInboxReply;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use App\Notifications\InboxMessageNotification;
use App\Notifications\InboxThreadUpdateNotification;
use App\Services\CalendarOauthSettingsService;
use App\Services\ChannelUnreadNotifier;
use App\Services\FlexCrmLookupService;
use App\Services\InboxReopenService;
use App\Services\InboxReplyService;
use App\Services\InboxThreadMergeService;
use App\Services\LeadActivityService;
use App\Services\LeadAutoCreateService;
use App\Services\LeadInboxAttachService;
use App\Services\LeadRuleEngine;
use App\Services\OutlookMailService;
use App\Support\EmailQuotedHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxController extends Controller
{
    public function __construct(
        protected OutlookMailService $mailService,
        protected CalendarOauthSettingsService $oauthSettings,
        protected FlexCrmLookupService $crmLookup,
        protected LeadActivityService $leadActivity,
        protected ChannelUnreadNotifier $unreadNotifier,
        protected LeadAutoCreateService $leadAutoCreate,
        protected InboxThreadMergeService $threadMerge,
        protected LeadInboxAttachService $inboxAttach,
        protected InboxReplyService $replyService,
        protected InboxReopenService $reopenService
    ) {}

    public function index(): View
    {
        return view('dashboard.inbox');
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;

        // Opportunistic send-later + snooze reopen when the inbox is opened (throttled).
        if (Cache::add('inbox:flush-scheduled-sends', 1, now()->addMinute())) {
            try {
                $this->replyService->processDue(20);
            } catch (\Throwable $e) {
                Log::warning('Inbox bootstrap scheduled send flush failed', [
                    'message' => $e->getMessage(),
                ]);
            }
            try {
                $this->reopenService->processDue(100);
            } catch (\Throwable $e) {
                Log::warning('Inbox bootstrap snooze reopen flush failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $inboxesQuery = $this->accessibleInboxes($user)->with(['members:id,name,email', 'account']);

        // Unlink accounts that do not match each inbox's configured email (clears polluted mail).
        foreach ($inboxesQuery->get() as $inbox) {
            $this->mailService->repairInboxBinding($inbox);
        }

        $inboxes = $this->accessibleInboxes($user)
            ->withCount([
                'conversations as open_count' => fn ($q) => $q->notMerged()->where('folder', 'inbox')->where('status', 'open'),
                'conversations as unread_count' => function ($q) use ($user) {
                    $q->notMerged()
                        ->where('folder', 'inbox')
                        ->where('status', 'open')
                        ->join('shared_inboxes as si', 'si.id', '=', 'inbox_conversations.shared_inbox_id')
                        ->leftJoin('inbox_conversation_user_reads as ir', function ($join) use ($user) {
                            $join->on('ir.inbox_conversation_id', '=', 'inbox_conversations.id')
                                ->where('ir.user_id', '=', $user->id);
                        })
                        ->where(function ($w) {
                            $w->where('si.type', SharedInbox::TYPE_SHARED)
                                ->whereRaw('COALESCE(ir.is_read, 0) = 0')
                                ->orWhere(function ($w2) {
                                    $w2->where('si.type', '!=', SharedInbox::TYPE_SHARED)
                                        ->where('inbox_conversations.is_read', false);
                                });
                        });
                },
                'conversations as archived_count' => fn ($q) => $q->notMerged()->where('folder', 'inbox')->where('status', 'archived')
                    ->where(fn ($q) => $q->whereNull('reopen_at')->orWhere('reopen_at', '<=', now())),
                'conversations as snoozed_count' => fn ($q) => $q->notMerged()->where('folder', 'inbox')->where('status', 'archived')
                    ->whereNotNull('reopen_at')->where('reopen_at', '>', now()),
                'conversations as drafts_count' => fn ($q) => $q->notMerged()->where('folder', 'drafts'),
                'conversations as sent_count' => fn ($q) => $q->notMerged()->where('folder', 'sent'),
                'conversations as trash_count' => fn ($q) => $q->notMerged()->where('folder', 'trash'),
                'conversations as spam_count' => fn ($q) => $q->notMerged()->where('folder', 'spam'),
            ])
            ->with(['members:id,name,email', 'account'])
            ->orderByRaw("CASE WHEN type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(fn (SharedInbox $inbox) => $this->formatInbox($inbox));

        $inboxIds = $this->accessibleInboxes($user)->pluck('id');

        $tags = InboxTag::where('company_id', $companyId)
            ->withCount([
                'conversations as unread_count' => function ($q) use ($inboxIds, $user) {
                    if ($inboxIds->isEmpty()) {
                        $q->whereRaw('0 = 1');

                        return;
                    }
                    $q->whereIn('shared_inbox_id', $inboxIds)
                        ->whereNull('merged_into_id')
                        ->where('folder', 'inbox')
                        ->where('status', 'open')
                        ->join('shared_inboxes as si', 'si.id', '=', 'inbox_conversations.shared_inbox_id')
                        ->leftJoin('inbox_conversation_user_reads as ir', function ($join) use ($user) {
                            $join->on('ir.inbox_conversation_id', '=', 'inbox_conversations.id')
                                ->where('ir.user_id', '=', $user->id);
                        })
                        ->where(function ($w) {
                            $w->where('si.type', SharedInbox::TYPE_SHARED)
                                ->whereRaw('COALESCE(ir.is_read, 0) = 0')
                                ->orWhere(function ($w2) {
                                    $w2->where('si.type', '!=', SharedInbox::TYPE_SHARED)
                                        ->where('inbox_conversations.is_read', false);
                                });
                        });
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (InboxTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'unread_count' => (int) ($tag->unread_count ?? 0),
            ]);
        $templates = InboxTemplate::where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (InboxTemplate $template) => $this->formatTemplate($template));

        $members = User::where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $account = OutlookMailAccount::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        $settings = InboxUserSetting::firstOrCreate(
            ['user_id' => $user->id],
            ['pinned_tag_ids' => []]
        );

        $pinnedTagIds = collect($settings->pinned_tag_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        // Keep only pins for tags that still exist in this company.
        $validTagIds = InboxTag::where('company_id', $companyId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $pinnedTagIds = array_values(array_intersect($pinnedTagIds, $validTagIds));
        if ($pinnedTagIds !== ($settings->pinned_tag_ids ?? [])) {
            $settings->pinned_tag_ids = $pinnedTagIds;
            $settings->save();
        }

        return response()->json([
            'outlook_configured' => $this->oauthSettings->isConfigured('outlook', $companyId),
            'mail_connected' => (bool) $account,
            'mail_email' => $account?->email,
            'connect_url' => route('inbox.connect.outlook'),
            'user_id' => $user->id,
            'inboxes' => $inboxes,
            'tags' => $tags,
            'lead_labels' => LeadLabel::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'templates' => $templates,
            'members' => $members,
            'pinned_tag_ids' => $pinnedTagIds,
            'permissions' => [
                'create_tags' => $user->hasPermission('create_inbox_tags'),
                'create_templates' => $user->hasPermission('create_inbox_templates'),
            ],
            'redirect_url_outlook_mail' => rtrim(config('app.url'), '/').'/inbox/connect/outlook/callback',
        ]);
    }

    public function redirectOutlook(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $creds = $this->mailService->getMailCredentials($companyId);
        if (empty($creds['client_id']) || empty($creds['client_secret'])) {
            $home = match ($request->query('intent')) {
                'broadcast' => 'broadcast-messaging',
                'quotation' => 'quotation-builder.microsoft-365-mail',
                default => 'inbox',
            };

            return redirect()->route($home)->with('error', 'Outlook is not configured. Add Microsoft OAuth credentials in Integrations.');
        }

        $tenant = $this->oauthSettings->getMicrosoftTenant($companyId);
        $intent = $request->query('intent', 'personal');
        $sharedInboxId = $request->query('shared_inbox_id');
        $state = encrypt([
            'user_id' => $request->user()->id,
            'intent' => $intent,
            'shared_inbox_id' => $sharedInboxId,
        ]);

        $params = [
            'client_id' => $creds['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $creds['redirect'],
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read Mail.ReadWrite Mail.Send Mail.ReadWrite.Shared offline_access',
            'state' => $state,
            // Always show account picker so shared vs personal can use different MS365 accounts.
            'prompt' => 'select_account',
        ];

        if ($intent === 'shared' && $sharedInboxId) {
            $sharedInbox = SharedInbox::where('company_id', $companyId)
                ->where('id', $sharedInboxId)
                ->first();
            $hint = $sharedInbox?->external_mailbox ?: $sharedInbox?->email;
            if ($hint) {
                $params['login_hint'] = $hint;
            }
        }

        $url = 'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/authorize?'.http_build_query($params);

        return redirect($url);
    }

    public function callbackOutlook(Request $request): RedirectResponse
    {
        @set_time_limit(120);

        $companyId = $request->user()->company_id;
        $creds = $this->mailService->getMailCredentials($companyId);

        $state = $this->outlookOauthState($request);

        if ($request->filled('error')) {
            $msError = $request->input('error_description', $request->input('error'));
            Log::warning('Outlook mail OAuth error', ['error' => $msError]);

            return $this->outlookConnectRedirect($state, 'error', $this->friendlyOauthError($msError));
        }

        $code = $request->input('code');
        if (! $code) {
            return $this->outlookConnectRedirect($state, 'error', 'Missing authorization code.');
        }

        $tenant = $this->oauthSettings->getMicrosoftTenant($companyId);
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            [
                'client_id' => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'code' => $code,
                'redirect_uri' => $creds['redirect'],
                'grant_type' => 'authorization_code',
            ]
        );

        if (! $response->successful()) {
            $body = $response->json();
            $msError = $body['error_description'] ?? $body['error'] ?? $response->body();
            Log::warning('Outlook mail token exchange failed', ['body' => $response->body()]);

            return $this->outlookConnectRedirect($state, 'error', $this->friendlyOauthError($msError));
        }

        try {
            $data = $response->json();
            $accessToken = $data['access_token'];
            $userInfo = Http::withToken($accessToken)->get('https://graph.microsoft.com/v1.0/me')->json();
            $email = $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? null;
            if (! $email) {
                return $this->outlookConnectRedirect($state, 'error', 'Connected to Microsoft, but no email was returned for this account.');
            }
            $user = $request->user();

            $account = OutlookMailAccount::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'email' => $email,
                ],
                [
                    'company_id' => $user->company_id,
                    'access_token' => $accessToken,
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                    'is_active' => true,
                ]
            );

            if (($state['intent'] ?? 'personal') === 'personal') {
                $inbox = SharedInbox::updateOrCreate(
                    [
                        'company_id' => $user->company_id,
                        'type' => SharedInbox::TYPE_PERSONAL,
                        'created_by' => $user->id,
                    ],
                    [
                        'outlook_mail_account_id' => $account->id,
                        'name' => 'Personal',
                        'email' => $email,
                        'external_mailbox' => null,
                        'is_active' => true,
                        'color' => '#0ea5e9',
                    ]
                );
                SharedInboxMember::updateOrCreate(
                    ['shared_inbox_id' => $inbox->id, 'user_id' => $user->id],
                    ['role' => 'admin']
                );
                // Sync is started by the inbox UI after redirect (paged) — never block OAuth callback.
            }

            if (($state['intent'] ?? '') === 'broadcast') {
                $displayName = trim((string) ($userInfo['displayName'] ?? ''));
                $inbox = SharedInbox::updateOrCreate(
                    [
                        'company_id' => $user->company_id,
                        'type' => SharedInbox::TYPE_BROADCAST,
                        'created_by' => $user->id,
                        'email' => $email,
                    ],
                    [
                        'outlook_mail_account_id' => $account->id,
                        'name' => $displayName !== '' ? $displayName : $email,
                        'external_mailbox' => null,
                        'is_active' => true,
                        'color' => '#6366f1',
                    ]
                );
                SharedInboxMember::updateOrCreate(
                    ['shared_inbox_id' => $inbox->id, 'user_id' => $user->id],
                    ['role' => 'admin']
                );
            }

            if (($state['intent'] ?? '') === 'quotation') {
                $displayName = trim((string) ($userInfo['displayName'] ?? ''));
                SharedInbox::updateOrCreate(
                    [
                        'company_id' => $user->company_id,
                        'type' => SharedInbox::TYPE_QUOTATION,
                    ],
                    [
                        'outlook_mail_account_id' => $account->id,
                        'created_by' => $user->id,
                        'name' => $displayName !== '' ? $displayName : 'Quotation mail',
                        'email' => $email,
                        'external_mailbox' => null,
                        'is_active' => true,
                        'color' => '#0ea5e9',
                    ]
                );
            }

            if (! empty($state['shared_inbox_id'])) {
                $inbox = SharedInbox::where('company_id', $user->company_id)
                    ->where('id', $state['shared_inbox_id'])
                    ->first();
                if ($inbox && $inbox->userIsAdmin($user)) {
                    $updates = [
                        'outlook_mail_account_id' => $account->id,
                        'is_active' => true,
                    ];

                    // Signed in as the shared mailbox itself → bind to /me.
                    if (! empty($inbox->external_mailbox) && strcasecmp((string) $inbox->external_mailbox, (string) $email) === 0) {
                        $updates['email'] = $email;
                        $updates['external_mailbox'] = null;
                    } elseif (empty($inbox->external_mailbox)) {
                        // mailbox_login: require the MS365 account to match the inbox email.
                        if ($inbox->email && strcasecmp((string) $inbox->email, (string) $email) !== 0) {
                            return $this->outlookConnectRedirect(
                                $state,
                                'error',
                                'Shared inbox is set to '.$inbox->email.'. Sign in with that Microsoft 365 account, not '.$email.'.'
                            );
                        }
                        $updates['email'] = $email;
                        $updates['external_mailbox'] = null;
                    } else {
                        // shared_mailbox: keep external address; account may differ.
                        $updates['email'] = $inbox->email ?: $inbox->external_mailbox;
                    }

                    // Drop mail previously synced under a different MS365 account.
                    if (
                        $inbox->outlook_mail_account_id
                        && (int) $inbox->outlook_mail_account_id !== (int) $account->id
                    ) {
                        $this->mailService->clearInboxConversations($inbox);
                    }

                    $inbox->update($updates);
                    // Sync is started by the inbox UI after redirect (paged) — never block OAuth callback.
                }
            }
        } catch (\Throwable $e) {
            Log::error('Outlook mail connect failed after token', [
                'message' => $e->getMessage(),
            ]);

            return $this->outlookConnectRedirect($state, 'error', 'Connected to Microsoft, but saving the mailbox failed: '.$e->getMessage());
        }

        return $this->outlookConnectRedirect($state, 'status', 'outlook-mail-connected');
    }

    /**
     * @return array{user_id?: int, intent?: string, shared_inbox_id?: mixed}
     */
    private function outlookOauthState(Request $request): array
    {
        try {
            $state = decrypt((string) $request->input('state', ''));

            return is_array($state) ? $state : ['intent' => 'personal'];
        } catch (\Throwable) {
            return ['intent' => 'personal'];
        }
    }

    private function outlookConnectRedirect(array $state, string $flashKey, mixed $flashValue): RedirectResponse
    {
        $route = match ($state['intent'] ?? '') {
            'broadcast' => 'broadcast-messaging',
            'quotation' => 'quotation-builder.microsoft-365-mail',
            default => 'inbox',
        };

        return redirect()->route($route)->with($flashKey, $flashValue);
    }

    private function friendlyOauthError(mixed $msError): string
    {
        $text = is_string($msError) ? $msError : json_encode($msError);
        if (str_contains($text, 'AADSTS50194') || str_contains($text, 'not configured as a multi-tenant')) {
            return 'Your Azure app is single-tenant. In Integrations, set Microsoft Tenant ID to your Directory (tenant) ID, or change the app to multi-tenant in Azure.';
        }
        if (str_contains($text, 'redirect_uri') || str_contains($text, 'AADSTS50011')) {
            return 'Redirect URI mismatch. Add http://localhost:8000/inbox/connect/outlook/callback exactly in Azure App Registration → Authentication.';
        }
        if (str_contains($text, 'AADSTS700016') || str_contains($text, 'Application not found')) {
            return 'Microsoft could not find this app in the tenant. Check Client ID and Tenant ID in Integrations.';
        }

        // Keep flash short
        $short = preg_replace('/\s+/', ' ', $text ?? '');
        $short = mb_substr($short, 0, 220);

        return 'Could not connect Outlook mail. '.$short;
    }

    public function disconnectMail(Request $request): JsonResponse
    {
        OutlookMailAccount::where('user_id', $request->user()->id)->delete();

        SharedInbox::where('company_id', $request->user()->company_id)
            ->where('type', SharedInbox::TYPE_PERSONAL)
            ->where('created_by', $request->user()->id)
            ->update(['outlook_mail_account_id' => null, 'is_active' => false]);

        return response()->json(['disconnected' => true]);
    }

    public function listConversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'inbox_id' => ['nullable', 'integer'],
            'view' => ['nullable', 'string', 'in:open,assigned_to_me,unassigned,archived,snoozed,drafts,sent,trash,spam,all'],
            'tag_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:500'],
            'folder' => ['nullable', 'string', 'in:any,inbox,drafts,sent,trash,spam'],
            'assigned_to' => ['nullable', 'integer'],
            'is_read' => ['nullable', 'in:0,1,true,false'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $view = $validated['view'] ?? 'open';
        $folderFilter = $validated['folder'] ?? null;
        $inboxIds = $this->accessibleInboxes($user)->pluck('id');

        if (! empty($validated['inbox_id'])) {
            if (! $inboxIds->contains((int) $validated['inbox_id'])) {
                return response()->json(['message' => 'Inbox not found.'], 404);
            }
            $inboxIds = collect([(int) $validated['inbox_id']]);
        }

        $query = InboxConversation::with(['assignee:id,name,email', 'tags', 'leadLabels', 'inbox:id,name,type,color', 'lead.identities', 'lead.assignedUser:id,name', 'lead.labels'])
            ->withCount('mergedConversations')
            ->notMerged()
            ->whereIn('shared_inbox_id', $inboxIds);

        // Compute per-user read state for shared inboxes.
        // - shared inbox: use inbox_conversation_user_reads
        // - personal inbox: fall back to inbox_conversations.is_read
        $query->join('shared_inboxes as si', 'si.id', '=', 'inbox_conversations.shared_inbox_id')
            ->leftJoin('inbox_conversation_user_reads as ir', function ($join) use ($user) {
                $join->on('ir.inbox_conversation_id', '=', 'inbox_conversations.id')
                    ->where('ir.user_id', '=', $user->id);
            })
            ->select('inbox_conversations.*')
            ->selectRaw(
                'CASE WHEN si.type = ? THEN COALESCE(ir.is_read, 0) ELSE inbox_conversations.is_read END as user_is_read',
                [SharedInbox::TYPE_SHARED]
            );

        // Advanced folder=any searches across all folders; otherwise apply sidebar view
        // or an explicit advanced folder filter.
        if ($folderFilter === 'any') {
            // no folder/status lock
        } elseif ($folderFilter) {
            $query->where('folder', $folderFilter);
        } elseif ($view === 'open') {
            $query->where('folder', 'inbox')->where('status', 'open');
        } elseif ($view === 'archived') {
            $query->where('folder', 'inbox')->where('status', 'archived')
                ->where(function ($q) {
                    $q->whereNull('reopen_at')->orWhere('reopen_at', '<=', now());
                });
        } elseif ($view === 'snoozed') {
            $query->where('folder', 'inbox')->where('status', 'archived')
                ->whereNotNull('reopen_at')
                ->where('reopen_at', '>', now());
        } elseif ($view === 'assigned_to_me') {
            $query->where('folder', 'inbox')->where('status', 'open')->where('assigned_to', $user->id);
        } elseif ($view === 'unassigned') {
            $query->where('folder', 'inbox')->where('status', 'open')->whereNull('assigned_to');
        } elseif ($view === 'drafts') {
            $query->where('folder', 'drafts');
        } elseif ($view === 'sent') {
            $query->where('folder', 'sent');
        } elseif ($view === 'trash') {
            $query->where('folder', 'trash');
        } elseif ($view === 'spam') {
            $query->where('folder', 'spam');
        }

        if (! empty($validated['tag_id'])) {
            $query->whereHas('tags', fn ($q) => $q->where('inbox_tags.id', $validated['tag_id']));
        }

        if (isset($validated['assigned_to'])) {
            if ((int) $validated['assigned_to'] === 0) {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', (int) $validated['assigned_to']);
            }
        }

        if (isset($validated['is_read']) && $validated['is_read'] !== '') {
            $read = filter_var($validated['is_read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($read !== null) {
                $query->whereRaw(
                    'CASE WHEN si.type = ? THEN COALESCE(ir.is_read, 0) ELSE inbox_conversations.is_read END = ?',
                    [SharedInbox::TYPE_SHARED, $read ? 1 : 0]
                );
            }
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('last_message_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('last_message_at', '<=', $validated['date_to']);
        }

        if (! empty($validated['from'])) {
            $from = $validated['from'];
            $query->where(function ($q) use ($from) {
                $q->where('from_email', 'like', "%{$from}%")
                    ->orWhere('from_name', 'like', "%{$from}%")
                    ->orWhereHas('messages', function ($mq) use ($from) {
                        $mq->where('from_email', 'like', "%{$from}%")
                            ->orWhere('from_name', 'like', "%{$from}%");
                    });
            });
        }

        if (! empty($validated['to'])) {
            $to = $validated['to'];
            $query->whereHas('messages', fn ($mq) => $mq->where('to_emails', 'like', "%{$to}%"));
        }

        if (! empty($validated['subject'])) {
            $subject = $validated['subject'];
            $query->where(function ($q) use ($subject) {
                $q->where('subject', 'like', "%{$subject}%")
                    ->orWhereHas('messages', fn ($mq) => $mq->where('subject', 'like', "%{$subject}%"));
            });
        }

        if (! empty($validated['body'])) {
            $body = $validated['body'];
            $query->where(function ($q) use ($body) {
                $q->where('snippet', 'like', "%{$body}%")
                    ->orWhereHas('messages', function ($mq) use ($body) {
                        $mq->where('body_text', 'like', "%{$body}%")
                            ->orWhere('body_html', 'like', "%{$body}%");
                    });
            });
        }

        if (! empty($validated['search'])) {
            $s = $validated['search'];
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                    ->orWhere('from_email', 'like', "%{$s}%")
                    ->orWhere('from_name', 'like', "%{$s}%")
                    ->orWhere('snippet', 'like', "%{$s}%")
                    ->orWhereHas('messages', function ($mq) use ($s) {
                        $mq->where('subject', 'like', "%{$s}%")
                            ->orWhere('from_email', 'like', "%{$s}%")
                            ->orWhere('to_emails', 'like', "%{$s}%")
                            ->orWhere('body_text', 'like', "%{$s}%");
                    });
            });
        }

        $paginator = $query->orderByDesc('last_message_at')->paginate(40);

        return response()->json([
            'conversations' => collect($paginator->items())->map(function ($c) {
                $c->is_read = (bool) ($c->user_is_read ?? $c->is_read);
                return $this->formatConversation($c);
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function suggestEmails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'field' => ['nullable', 'string', 'in:from,to,any'],
        ]);

        $q = trim($validated['q']);
        $field = $validated['field'] ?? 'any';
        $user = $request->user();
        $inboxIds = $this->accessibleInboxes($user)->pluck('id');
        $suggestions = [];

        $add = function (?string $email, ?string $name = null) use (&$suggestions, $q): void {
            $email = strtolower(trim((string) $email));
            if ($email === '' || ! str_contains($email, '@')) {
                return;
            }
            $name = trim((string) $name);
            $haystack = strtolower($email.' '.$name);
            if (! str_contains($haystack, strtolower($q))) {
                return;
            }
            if (! isset($suggestions[$email])) {
                $suggestions[$email] = [
                    'email' => $email,
                    'name' => $name !== '' ? $name : null,
                ];

                return;
            }
            if ($name !== '' && empty($suggestions[$email]['name'])) {
                $suggestions[$email]['name'] = $name;
            }
        };

        if ($field !== 'to') {
            InboxConversation::query()
                ->whereIn('shared_inbox_id', $inboxIds)
                ->whereNotNull('from_email')
                ->where('from_email', '!=', '')
                ->where(function ($query) use ($q) {
                    $query->where('from_email', 'like', "%{$q}%")
                        ->orWhere('from_name', 'like', "%{$q}%");
                })
                ->orderByDesc('last_message_at')
                ->limit(40)
                ->get(['from_email', 'from_name'])
                ->each(fn ($row) => $add($row->from_email, $row->from_name));

            InboxMessage::query()
                ->whereHas('conversation', fn ($cq) => $cq->whereIn('shared_inbox_id', $inboxIds))
                ->whereNotNull('from_email')
                ->where('from_email', '!=', '')
                ->where(function ($query) use ($q) {
                    $query->where('from_email', 'like', "%{$q}%")
                        ->orWhere('from_name', 'like', "%{$q}%");
                })
                ->orderByDesc('sent_at')
                ->limit(40)
                ->get(['from_email', 'from_name'])
                ->each(fn ($row) => $add($row->from_email, $row->from_name));
        }

        if ($field !== 'from') {
            InboxMessage::query()
                ->whereHas('conversation', fn ($cq) => $cq->whereIn('shared_inbox_id', $inboxIds))
                ->whereNotNull('to_emails')
                ->where('to_emails', 'like', "%{$q}%")
                ->orderByDesc('sent_at')
                ->limit(50)
                ->pluck('to_emails')
                ->each(function ($raw) use ($add) {
                    foreach (preg_split('/\s*,\s*/', (string) $raw) ?: [] as $email) {
                        $add($email);
                    }
                });
        }

        User::query()
            ->where('company_id', $user->company_id)
            ->whereNotNull('email')
            ->where(function ($query) use ($q) {
                $query->where('email', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['email', 'name'])
            ->each(fn ($row) => $add($row->email, $row->name));

        $results = collect($suggestions)
            ->sortBy(fn ($item) => strtolower($item['name'] ?: $item['email']))
            ->values()
            ->take(12)
            ->all();

        return response()->json(['suggestions' => $results]);
    }

    public function showConversation(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        if ($conversation->merged_into_id) {
            $conversation = $conversation->mergeRoot();
            $this->authorizeConversation($request->user(), $conversation);
        }

        $conversation->load([
            'messages',
            'comments.user:id,name,email',
            'activities.user:id,name,email',
            'scheduledReplies' => fn ($q) => $q->where('status', ScheduledInboxReply::STATUS_PENDING)->with('user:id,name,email'),
            'assignee:id,name,email',
            'tags',
            'leadLabels',
            'mergedConversations',
            // Need full inbox (incl. outlook_mail_account_id / external_mailbox)
            // so hydrate can load the Graph account and fetch the full HTML body.
            // A constrained select like inbox:id,name,... omits the FK and silently
            // skips hydration — leaving only Graph bodyPreview (~255 chars).
            'inbox.account',
            'inbox.members:id,name,email',
            'lead.identities',
            'lead.assignedUser:id,name',
            'lead.labels',
            'userReads',
        ]);

        $relatedConversations = $conversation->relatedFolderConversations();
        if ($conversation->inbox) {
            foreach ($relatedConversations as $related) {
                $this->mailService->hydrateConversationBodies(
                    $conversation->inbox,
                    $related
                );
            }
        }
        $relatedConversations->load('messages');
        $conversation->setRelation(
            'messages',
            $this->dedupeThreadMessages($relatedConversations->flatMap->messages)
        );

        // Per-user read tracking for shared inboxes.
        // Only bump last_read_at when the member first reads (or re-reads after unread),
        // so the Participants list can show ages like "Read 22 mins ago".
        if ($conversation->inbox && $conversation->inbox->type === SharedInbox::TYPE_SHARED) {
            $readRow = InboxConversationUserRead::firstOrNew([
                'inbox_conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
            ]);
            $wasUnread = ! $readRow->exists || ! $readRow->is_read || ! $readRow->last_read_at;
            $readRow->is_read = true;
            if ($wasUnread) {
                $readRow->last_read_at = now();
            }
            $readRow->save();
            $conversation->setAttribute('is_read', true);
            $conversation->load('userReads');
        } else {
            if (! $conversation->is_read) {
                $conversation->update(['is_read' => true]);
            }
        }
        $this->unreadNotifier->markConversationRead(
            $request->user(),
            InboxMessageNotification::class,
            (int) $conversation->id
        );

        return response()->json([
            'conversation' => $this->formatConversation($conversation, true),
        ]);
    }

    public function downloadMessageAttachment(
        Request $request,
        InboxConversation $conversation,
        InboxMessage $message,
        int $index
    ): Response|JsonResponse {
        $this->authorizeConversation($request->user(), $conversation);
        if ((int) $message->inbox_conversation_id !== (int) $conversation->id) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        $meta = collect($message->attachments ?? [])->values()->get($index);
        if (! is_array($meta) || empty($meta['id'])) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $inbox = $conversation->inbox()->with('account')->first();
        if (! $inbox?->account) {
            return response()->json(['message' => 'This inbox is not connected to Outlook.'], 422);
        }

        $file = $this->mailService->downloadMessageAttachment($inbox, $message, (string) $meta['id']);
        if (! $file) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $name = $file['name'];
        $contentType = $file['content_type'] ?: 'application/octet-stream';

        return response($file['content'], 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $name,
                preg_replace('/[^\x20-\x7E]/', '_', $name) ?: 'attachment'
            ),
            'Content-Length' => (string) strlen($file['content']),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function assign(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $conversation->loadMissing('assignee:id,name,email');
        $previousAssignee = $conversation->assignee;
        $assignee = null;

        if ($validated['assigned_to'] ?? null) {
            $assignee = User::findOrFail($validated['assigned_to']);
            if ($assignee->company_id !== $request->user()->company_id) {
                return response()->json(['message' => 'Invalid assignee.'], 422);
            }
        }

        $newAssigneeId = $validated['assigned_to'] ?? null;
        if ((int) ($conversation->assigned_to ?? 0) === (int) ($newAssigneeId ?? 0)) {
            $this->syncLeadAssignment($conversation, $newAssigneeId);
            $conversation->load('assignee:id,name,email');

            return response()->json(['conversation' => $this->formatConversation($conversation)]);
        }

        $conversation->assigned_to = $newAssigneeId;
        $conversation->save();
        $conversation->load('assignee:id,name,email');
        $this->syncLeadAssignment($conversation, $newAssigneeId);

        if ($assignee) {
            $this->recordActivity(
                $conversation,
                $request->user(),
                'assigned',
                $request->user()->name.' assigned this to '.$assignee->name,
                [
                    'assignee_id' => $assignee->id,
                    'assignee_name' => $assignee->name,
                    'previous_assignee_id' => $previousAssignee?->id,
                    'previous_assignee_name' => $previousAssignee?->name,
                ]
            );
        } else {
            $this->recordActivity(
                $conversation,
                $request->user(),
                'unassigned',
                $request->user()->name.' removed the assignee'.($previousAssignee ? ' (was '.$previousAssignee->name.')' : ''),
                [
                    'previous_assignee_id' => $previousAssignee?->id,
                    'previous_assignee_name' => $previousAssignee?->name,
                ]
            );
        }

        return response()->json(['conversation' => $this->formatConversation($conversation)]);
    }

    public function updateStatus(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'status' => ['required', 'in:open,archived,spam,trashed,drafts,sent'],
        ]);

        $status = $validated['status'];
        $folderMap = [
            'open' => 'inbox',
            'archived' => 'inbox',
            'drafts' => 'drafts',
            'sent' => 'sent',
            'spam' => 'spam',
            'trashed' => 'trash',
        ];
        $newFolder = $folderMap[$status] ?? 'inbox';
        $oldFolder = $conversation->folder ?: 'inbox';
        $oldStatus = $conversation->status;
        $inbox = $conversation->inbox;
        $actor = $request->user();

        if ($newFolder !== $oldFolder) {
            $existing = InboxConversation::where('shared_inbox_id', $conversation->shared_inbox_id)
                ->where('folder', $newFolder)
                ->where('external_conversation_id', $conversation->external_conversation_id)
                ->where('id', '!=', $conversation->id)
                ->first();

            if ($existing) {
                InboxConversation::where('merged_into_id', $conversation->id)
                    ->update(['merged_into_id' => $existing->id]);
                $conversation->messages()->update(['inbox_conversation_id' => $existing->id]);
                $conversation->comments()->update(['inbox_conversation_id' => $existing->id]);
                $conversation->activities()->update(['inbox_conversation_id' => $existing->id]);
                if ($conversation->last_message_at && (! $existing->last_message_at || $conversation->last_message_at->gt($existing->last_message_at))) {
                    $existing->last_message_at = $conversation->last_message_at;
                    $existing->snippet = $conversation->snippet ?: $existing->snippet;
                }
                $existing->status = $status;
                $existing->message_count = $existing->messages()->count();
                if ($status === 'open' || $status === 'archived') {
                    $existing->reopen_at = null;
                }
                $existing->save();

                if ($inbox && in_array($newFolder, ['trash', 'spam', 'inbox'], true)) {
                    $this->mailService->moveConversationToFolder($inbox, $existing->fresh(['messages']), $newFolder);
                }

                $conversation->delete();
                $this->recordStatusActivity($existing, $actor, $oldStatus, $oldFolder, $status, $newFolder);
                $this->fireConversationStatusRules($existing->fresh(['tags', 'leadLabels', 'inbox']), $status);

                return response()->json([
                    'conversation' => $this->formatConversation($existing->fresh(['assignee', 'tags', 'leadLabels', 'inbox'])),
                ]);
            }

            if ($inbox && in_array($newFolder, ['trash', 'spam', 'inbox'], true)) {
                $this->mailService->moveConversationToFolder($inbox, $conversation, $newFolder);
            }

            $conversation->folder = $newFolder;
        }

        $conversation->status = $status;
        if ($status === 'open' || $status === 'archived') {
            $conversation->reopen_at = null;
        }
        $conversation->save();
        $this->recordStatusActivity($conversation, $actor, $oldStatus, $oldFolder, $status, $newFolder);
        $this->fireConversationStatusRules($conversation->fresh(['tags', 'leadLabels', 'inbox']), $status);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh(['assignee', 'tags', 'leadLabels', 'inbox'])),
        ]);
    }

    public function snooze(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'until' => ['required', 'date', 'after:now'],
        ]);

        $until = $this->parseScheduledSendAt($validated['until']);
        if (! $until || $until->lte(now())) {
            return response()->json(['message' => 'Choose a future date and time.'], 422);
        }

        $conversation->status = 'archived';
        $conversation->folder = 'inbox';
        $conversation->reopen_at = $until;
        $conversation->save();

        $this->recordActivity(
            $conversation,
            $request->user(),
            'snoozed',
            $request->user()->name.' snoozed this until '.$until->timezone(config('app.timezone'))->format('M j, g:ia'),
            ['reopen_at' => $until->toIso8601String()]
        );

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh(['assignee', 'tags', 'leadLabels', 'inbox'])),
        ]);
    }

    public function mergeCandidates(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $candidates = $this->threadMerge->candidates($conversation, $validated['q'] ?? null);

        return response()->json([
            'conversations' => $candidates->map(fn (InboxConversation $c) => $this->formatMergeCandidate($c)),
        ]);
    }

    public function mergeConversations(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'required_without:conversation_ids'],
            'conversation_ids' => ['nullable', 'array', 'min:1', 'required_without:conversation_id'],
            'conversation_ids.*' => ['integer'],
        ]);

        $sourceIds = collect($validated['conversation_ids'] ?? [])
            ->when(
                ! empty($validated['conversation_id']),
                fn ($ids) => $ids->push((int) $validated['conversation_id'])
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) $conversation->id)
            ->values();

        if ($sourceIds->isEmpty()) {
            return response()->json(['message' => 'Choose at least one other conversation to merge.'], 422);
        }

        $merged = $conversation;
        foreach ($sourceIds as $sourceId) {
            $source = InboxConversation::query()->findOrFail($sourceId);
            $this->authorizeConversation($request->user(), $source);

            $sourceLabel = $source->subject ?: 'Untitled';
            $sourceEmail = $source->from_email;

            $merged = $this->threadMerge->merge($merged, $source);
            $this->recordActivity(
                $merged,
                $request->user(),
                'merged',
                $request->user()->name.' merged "'.$sourceLabel.'" into this conversation',
                [
                    'source_conversation_id' => $sourceId,
                    'source_subject' => $sourceLabel,
                    'source_from' => $sourceEmail,
                ]
            );
        }

        return response()->json([
            'conversation' => $this->formatConversation(
                $merged->fresh(['assignee', 'tags', 'leadLabels', 'inbox', 'mergedConversations'])
            ),
        ]);
    }

    public function unmergeConversations(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
        ]);

        if (! empty($validated['conversation_id'])) {
            $source = InboxConversation::query()->findOrFail((int) $validated['conversation_id']);
            $this->authorizeConversation($request->user(), $source);
            $sourceLabel = $source->subject ?: 'Untitled';
            $sourceId = $source->id;
            $merged = $this->threadMerge->unmerge($conversation, $source);
            $this->recordActivity(
                $merged,
                $request->user(),
                'unmerged',
                $request->user()->name.' unmerged "'.$sourceLabel.'" from this conversation',
                [
                    'source_conversation_id' => $sourceId,
                    'source_subject' => $sourceLabel,
                ]
            );
        } else {
            $children = InboxConversation::query()
                ->where('merged_into_id', $conversation->mergeRoot()->id)
                ->orderBy('id')
                ->get();
            $merged = $conversation;
            foreach ($children as $source) {
                $sourceLabel = $source->subject ?: 'Untitled';
                $sourceId = $source->id;
                $merged = $this->threadMerge->unmerge($merged, $source);
                $this->recordActivity(
                    $merged,
                    $request->user(),
                    'unmerged',
                    $request->user()->name.' unmerged "'.$sourceLabel.'" from this conversation',
                    [
                        'source_conversation_id' => $sourceId,
                        'source_subject' => $sourceLabel,
                    ]
                );
            }
        }

        return response()->json([
            'conversation' => $this->formatConversation(
                $merged->fresh(['assignee', 'tags', 'leadLabels', 'inbox', 'mergedConversations'])
            ),
        ]);
    }

    public function updateRead(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'is_read' => ['required', 'boolean'],
        ]);

        $conversation->loadMissing('inbox');
        $isSharedInbox = $conversation->inbox && $conversation->inbox->type === SharedInbox::TYPE_SHARED;

        if ($isSharedInbox) {
            InboxConversationUserRead::updateOrCreate(
                [
                    'inbox_conversation_id' => $conversation->id,
                    'user_id' => $request->user()->id,
                ],
                [
                    'is_read' => (bool) $validated['is_read'],
                    'last_read_at' => $validated['is_read'] ? now() : null,
                ]
            );
        } else {
            $conversation->is_read = (bool) $validated['is_read'];
            $conversation->save();
        }

        $fresh = $conversation->fresh(['assignee', 'tags', 'leadLabels', 'inbox']);
        if ($isSharedInbox) {
            $fresh->setAttribute('is_read', (bool) $validated['is_read']);
        }

        return response()->json([
            'conversation' => $this->formatConversation($fresh),
        ]);
    }

    public function syncTags(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer'],
        ]);

        $previousIds = $conversation->tags()->pluck('inbox_tags.id')->map(fn ($id) => (int) $id)->all();
        $tagIds = InboxTag::where('company_id', $request->user()->company_id)
            ->whereIn('id', $validated['tag_ids'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $conversation->tags()->sync($tagIds);
        $conversation->load('tags', 'leadLabels');

        $addedIds = array_values(array_diff($tagIds, $previousIds));
        $removedIds = array_values(array_diff($previousIds, $tagIds));
        $tagsById = InboxTag::whereIn('id', array_merge($addedIds, $removedIds))
            ->get(['id', 'name', 'color'])
            ->keyBy('id');

        if ($addedIds) {
            $names = collect($addedIds)->map(fn ($id) => $tagsById[$id]->name ?? ('#'.$id))->implode(', ');
            $this->recordActivity(
                $conversation,
                $request->user(),
                'tags_added',
                $request->user()->name.' added tag'.(count($addedIds) > 1 ? 's' : '').': '.$names,
                [
                    'tag_ids' => $addedIds,
                    'tag_names' => collect($addedIds)->map(fn ($id) => $tagsById[$id]->name ?? null)->filter()->values()->all(),
                ]
            );
        }

        if ($removedIds) {
            $names = collect($removedIds)->map(fn ($id) => $tagsById[$id]->name ?? ('#'.$id))->implode(', ');
            $this->recordActivity(
                $conversation,
                $request->user(),
                'tags_removed',
                $request->user()->name.' removed tag'.(count($removedIds) > 1 ? 's' : '').': '.$names,
                [
                    'tag_ids' => $removedIds,
                    'tag_names' => collect($removedIds)->map(fn ($id) => $tagsById[$id]->name ?? null)->filter()->values()->all(),
                ]
            );
        }

        return response()->json(['conversation' => $this->formatConversation($conversation->fresh(['tags', 'leadLabels', 'inbox', 'assignee']))]);
    }

    public function attachLeadLabel(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'lead_id' => ['nullable', 'integer'],
            'label_id' => ['nullable', 'integer', 'exists:lead_labels,id'],
            'name' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $lead = $this->matchingLead($conversation, isset($validated['lead_id']) ? (int) $validated['lead_id'] : null);
        if (! $lead) {
            return response()->json(['message' => 'Save this contact as a lead before adding labels.'], 422);
        }

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
                    'color' => $validated['color'] ?? '#4338ca',
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
        $this->crmLookup->forgetLeadIndexes($companyId);

        return response()->json(['conversation' => $this->formatConversation($conversation->fresh(['tags', 'leadLabels', 'inbox', 'assignee']))]);
    }

    public function detachLeadLabel(Request $request, InboxConversation $conversation, LeadLabel $leadLabel): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $leadId = $request->integer('lead_id') ?: null;
        $lead = $this->matchingLead($conversation, $leadId ?: null);
        if (! $lead) {
            return response()->json(['message' => 'No matching lead.'], 422);
        }
        if ((int) $leadLabel->company_id !== (int) $lead->company_id) {
            abort(404);
        }

        $lead->labels()->detach($leadLabel->id);
        $this->leadActivity->recordLabel($lead, $leadLabel->name, false);
        $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);

        return response()->json(['conversation' => $this->formatConversation($conversation->fresh(['tags', 'leadLabels', 'inbox', 'assignee', 'lead.identities', 'lead.assignedUser:id,name', 'lead.labels']))]);
    }

    /**
     * Remove a lead label attached directly to a conversation (no matching lead),
     * e.g. one applied by Front tag import.
     */
    public function detachConversationLabel(Request $request, InboxConversation $conversation, LeadLabel $leadLabel): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        if ((int) $leadLabel->company_id !== (int) $conversation->company_id) {
            abort(404);
        }

        $conversation->leadLabels()->detach($leadLabel->id);

        return response()->json(['conversation' => $this->formatConversation($conversation->fresh(['tags', 'leadLabels', 'inbox', 'assignee']))]);
    }

    public function attachLead(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'lead_id' => ['required', 'integer'],
        ]);

        $lead = Lead::query()
            ->where('company_id', $request->user()->company_id)
            ->find((int) $validated['lead_id']);
        if (! $lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        $this->inboxAttach->attach($lead, $conversation, $request->user());

        // Labels applied while this conversation had no matching lead (e.g. Front
        // import) now graduate onto the lead itself, and no longer need to live
        // directly on the conversation.
        $conversationLabelIds = $conversation->leadLabels()->pluck('lead_labels.id')->map(fn ($id) => (int) $id)->all();
        if ($conversationLabelIds !== []) {
            $lead->labels()->syncWithoutDetaching($conversationLabelIds);
            $conversation->leadLabels()->detach($conversationLabelIds);
        }

        $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh([
                'tags',
                'leadLabels',
                'inbox',
                'assignee',
                'lead.identities',
                'lead.assignedUser:id,name',
                'lead.labels',
            ])),
        ]);
    }

    public function detachLead(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $conversation = $conversation->mergeRoot();
        $lead = $conversation->lead_id
            ? Lead::query()->where('company_id', $conversation->company_id)->find($conversation->lead_id)
            : null;
        if (! $lead) {
            return response()->json(['message' => 'This email is not attached to a lead.'], 422);
        }

        $this->inboxAttach->detach($lead, $conversation, $request->user());
        $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh(['tags', 'leadLabels', 'inbox', 'assignee'])),
        ]);
    }

    public function reply(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000000'],
            'to' => ['nullable', 'string', 'max:2000'],
            'cc' => ['nullable', 'string', 'max:2000'],
            'inbox_id' => ['nullable', 'integer'],
            'reply_all' => ['nullable', 'boolean'],
            'archive' => ['nullable', 'boolean'],
            'send_at' => ['nullable', 'date', 'after:now'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.contentType' => ['nullable', 'string', 'max:120'],
            'attachments.*.contentBytes' => ['required_with:attachments', 'string', 'max:5000000'],
            'attachments.*.isInline' => ['nullable', 'boolean'],
            'attachments.*.contentId' => ['nullable', 'string', 'max:120'],
        ]);

        $inbox = $conversation->inbox;
        if (! empty($validated['inbox_id']) && (int) $validated['inbox_id'] !== (int) $inbox?->id) {
            $requestedInbox = $this->accessibleInboxes($request->user())
                ->where('id', $validated['inbox_id'])
                ->with('account')
                ->first();
            if (! $requestedInbox) {
                return response()->json(['message' => 'From inbox not found.'], 404);
            }
            $inbox = $requestedInbox;
        }
        if (! $inbox?->account) {
            return response()->json(['message' => 'This inbox is not connected to Outlook.'], 422);
        }

        $lastInbound = $conversation->messages()->where('direction', 'inbound')->orderByDesc('sent_at')->first();
        $source = $lastInbound ?: $conversation->messages()->orderByDesc('sent_at')->first();
        $mailboxEmail = strtolower(trim((string) ($inbox->email ?: $inbox->account->email ?: '')));
        $requestedTo = $this->normalizeRecipientEmails($validated['to'] ?? null);
        $requestedCc = $this->normalizeRecipientEmails($validated['cc'] ?? null);
        $honorRecipients = $request->exists('to') || $request->exists('cc');
        $sourceReplyTo = $this->normalizeRecipientEmails($source?->reply_to_emails);
        $sourceFrom = $this->normalizeRecipientEmails($source?->from_email);
        $defaultTo = $sourceReplyTo->isNotEmpty()
            ? $sourceReplyTo
            : ($sourceFrom->isNotEmpty()
                ? $sourceFrom
                : $this->normalizeRecipientEmails($conversation->from_email));

        if ($honorRecipients) {
            $toEmails = $requestedTo;
            $ccEmails = $requestedCc->reject(fn ($email) => $toEmails->contains($email))->values();
        } else {
            $toEmails = $requestedTo->isNotEmpty()
                ? $requestedTo
                : $defaultTo;
            $ccEmails = $requestedCc;

            if ($request->boolean('reply_all') && $source) {
                $others = collect()
                    ->merge($sourceReplyTo)
                    ->merge($sourceFrom)
                    ->merge($this->normalizeRecipientEmails($source->to_emails))
                    ->merge($this->normalizeRecipientEmails($source->cc_emails))
                    ->filter()
                    ->unique()
                    ->reject(fn ($email) => $email === $mailboxEmail)
                    ->values();
                if ($others->isNotEmpty()) {
                    $toEmails = collect([$others->shift()]);
                    $ccEmails = $others->merge($ccEmails)->unique()->values();
                }
            }
        }

        $toEmails = $toEmails
            ->reject(fn ($email) => $email === '')
            ->unique()
            ->values();
        $ccEmails = $ccEmails
            ->reject(fn ($email) => $email === '' || $toEmails->contains($email))
            ->unique()
            ->values();

        if ($toEmails->isEmpty()) {
            return response()->json(['message' => 'No recipient found.'], 422);
        }

        foreach ($toEmails->merge($ccEmails) as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['message' => "Invalid recipient: {$email}"], 422);
            }
        }

        $to = $toEmails->implode(', ');
        $cc = $ccEmails->isNotEmpty() ? $ccEmails->implode(', ') : null;

        $attachments = $this->normalizeAttachments($validated['attachments'] ?? []);
        if ($attachments === false) {
            return response()->json(['message' => 'Attachments are too large. Keep each file under 3 MB.'], 422);
        }

        $prepared = $this->prepareTemplateContent((string) $validated['body'], $attachments);
        if (count($prepared['attachments']) > 10) {
            return response()->json(['message' => 'Too many attachments. Use up to 5 files plus a few inline images.'], 422);
        }
        $validated['body'] = $prepared['body'];
        $attachments = $prepared['attachments'];

        $archive = $request->boolean('archive');

        if (! empty($validated['send_at'])) {
            $sendAt = $this->parseScheduledSendAt($validated['send_at']);
            if (! $sendAt || $sendAt->lte(now())) {
                return response()->json(['message' => 'Choose a future date and time.'], 422);
            }
            $scheduled = ScheduledInboxReply::create([
                'inbox_conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
                'shared_inbox_id' => $inbox->id,
                'type' => ScheduledInboxReply::TYPE_REPLY,
                'to_emails' => $to,
                'cc_emails' => $cc,
                'body_html' => $validated['body'],
                'body_text' => strip_tags($validated['body']),
                'attachments' => [],
                'send_at' => $sendAt,
                'archive_after' => $archive,
                'status' => ScheduledInboxReply::STATUS_PENDING,
            ]);

            if ($attachments !== []) {
                $scheduled->update([
                    'attachments' => $this->replyService->storeScheduledAttachments($scheduled, $attachments),
                ]);
            }

            $this->recordActivity(
                $conversation,
                $request->user(),
                'reply_scheduled',
                $request->user()->name.' scheduled a reply for '.$sendAt->timezone(config('app.timezone'))->format('M j, g:ia'),
                [
                    'scheduled_reply_id' => $scheduled->id,
                    'send_at' => $sendAt->toIso8601String(),
                    'archive_after' => $archive,
                ]
            );

            $conversation->load([
                'assignee',
                'tags',
                'leadLabels',
                'inbox',
                'scheduledReplies' => fn ($q) => $q->where('status', ScheduledInboxReply::STATUS_PENDING)->with('user:id,name,email'),
            ]);

            return response()->json([
                'scheduled' => true,
                'scheduled_reply' => $this->formatScheduledReply($scheduled->fresh(['user:id,name,email'])),
                'conversation' => $this->formatConversation($conversation),
            ]);
        }

        try {
            $result = $this->replyService->send($conversation, $inbox, $request->user(), [
                'body' => $validated['body'],
                'to' => $to,
                'cc' => $cc,
                'attachments' => $attachments,
                'archive' => $archive,
                'reply_to_message_id' => $lastInbound?->external_message_id,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $this->recordActivity(
            $conversation,
            $request->user(),
            'replied',
            $request->user()->name.' sent a reply',
            ['message_id' => $result['message']->id]
        );

        if ($archive) {
            $this->recordActivity(
                $conversation,
                $request->user(),
                'archived',
                $request->user()->name.' archived this conversation',
                ['source' => 'send_and_archive', 'message_id' => $result['message']->id]
            );
        }

        $this->applyLeadRules($conversation, LeadRuleEngine::TRIGGER_OUTBOUND_REPLY);

        return response()->json([
            'message' => $this->formatMessage($result['message']),
            'conversation' => $this->formatConversation($result['conversation']),
            'archived' => $archive,
        ]);
    }

    public function cancelScheduledReply(
        Request $request,
        InboxConversation $conversation,
        ScheduledInboxReply $scheduledReply
    ): JsonResponse {
        $this->authorizeConversation($request->user(), $conversation);
        if ((int) $scheduledReply->inbox_conversation_id !== (int) $conversation->id) {
            return response()->json(['message' => 'Scheduled reply not found.'], 404);
        }
        if ($scheduledReply->status !== ScheduledInboxReply::STATUS_PENDING) {
            return response()->json(['message' => 'Only pending scheduled replies can be cancelled.'], 422);
        }

        $wasCompose = $scheduledReply->isCompose();
        $scheduledReply->status = ScheduledInboxReply::STATUS_CANCELLED;
        $scheduledReply->save();
        $this->replyService->deleteScheduledAttachmentFiles($scheduledReply);

        $this->recordActivity(
            $conversation,
            $request->user(),
            $wasCompose ? 'compose_schedule_cancelled' : 'reply_schedule_cancelled',
            $request->user()->name.' cancelled a scheduled '.($wasCompose ? 'message' : 'reply'),
            ['scheduled_reply_id' => $scheduledReply->id]
        );

        // Scheduled new messages live as empty draft threads — remove them on cancel.
        if ($wasCompose
            && ($conversation->folder === 'drafts' || $conversation->status === 'drafts')
            && $conversation->messages()->count() === 0
        ) {
            $conversationId = $conversation->id;
            $conversation->delete();

            return response()->json([
                'deleted' => true,
                'conversation_id' => $conversationId,
            ]);
        }

        $conversation->load([
            'assignee',
            'tags',
            'leadLabels',
            'inbox',
            'scheduledReplies' => fn ($q) => $q->where('status', ScheduledInboxReply::STATUS_PENDING)->with('user:id,name,email'),
        ]);

        return response()->json([
            'conversation' => $this->formatConversation($conversation),
        ]);
    }

    public function storeComment(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:50000'],
            'mentioned_user_ids' => ['nullable', 'array', 'max:20'],
            'mentioned_user_ids.*' => ['integer'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.contentType' => ['nullable', 'string', 'max:120'],
            'attachments.*.contentBytes' => ['required_with:attachments', 'string', 'max:5000000'],
        ]);

        $html = $validated['body'];
        if (! str_contains($html, '<')) {
            $html = nl2br(e($html));
        }

        $plain = trim(strip_tags($html));
        if ($plain === '') {
            return response()->json(['message' => 'Comment cannot be empty.'], 422);
        }

        $companyId = $request->user()->company_id;
        $mentionIds = collect($validated['mentioned_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($mentionIds->isNotEmpty()) {
            $validMentionIds = User::where('company_id', $companyId)
                ->whereIn('id', $mentionIds)
                ->pluck('id');
            $mentionIds = $validMentionIds;
        }

        $normalized = $this->normalizeAttachments($validated['attachments'] ?? []);
        if ($normalized === false) {
            return response()->json(['message' => 'Attachments are too large. Keep each file under 3 MB.'], 422);
        }

        $comment = InboxConversationComment::create([
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'body_html' => $html,
            'body_text' => $plain,
            'mentioned_user_ids' => $mentionIds->all(),
            'attachments' => [],
        ]);

        $storedAttachments = [];
        foreach ($normalized as $index => $attachment) {
            $binary = base64_decode($attachment['contentBytes'], true);
            if ($binary === false) {
                continue;
            }
            $safeName = Str::slug(pathinfo($attachment['name'], PATHINFO_FILENAME)) ?: 'file';
            $ext = pathinfo($attachment['name'], PATHINFO_EXTENSION);
            $filename = $safeName.($ext ? '.'.$ext : '');
            $path = "inbox-comments/{$comment->id}/{$index}_{$filename}";
            Storage::disk('local')->put($path, $binary);
            $storedAttachments[] = [
                'name' => $attachment['name'],
                'content_type' => $attachment['contentType'],
                'size' => strlen($binary),
                'path' => $path,
                'index' => $index,
            ];
        }

        if ($storedAttachments) {
            $comment->update(['attachments' => $storedAttachments]);
        }

        $conversation->update(['last_message_at' => now()]);

        $comment->load('user:id,name,email');

        $mentionLabel = $mentionIds->isEmpty()
            ? ''
            : ' (mentioned '.User::whereIn('id', $mentionIds)->pluck('name')->implode(', ').')';

        $this->recordActivity(
            $conversation,
            $request->user(),
            'commented',
            $request->user()->name.' added an internal comment'.$mentionLabel,
            [
                'comment_id' => $comment->id,
                'mentioned_user_ids' => $mentionIds->all(),
                'attachment_count' => count($storedAttachments),
                'snippet' => $plain,
            ]
        );

        return response()->json([
            'comment' => $this->formatComment($comment),
            'conversation' => $this->formatConversation($conversation->fresh(['assignee', 'tags', 'leadLabels', 'inbox'])),
        ], 201);
    }

    public function downloadCommentAttachment(
        Request $request,
        InboxConversation $conversation,
        InboxConversationComment $comment,
        int $index
    ): StreamedResponse|JsonResponse {
        $this->authorizeConversation($request->user(), $conversation);
        if ((int) $comment->inbox_conversation_id !== (int) $conversation->id) {
            return response()->json(['message' => 'Comment not found.'], 404);
        }

        $attachments = $comment->attachments ?? [];
        $file = collect($attachments)->firstWhere('index', $index)
            ?? ($attachments[$index] ?? null);

        if (! $file || empty($file['path']) || ! Storage::disk('local')->exists($file['path'])) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return Storage::disk('local')->download(
            $file['path'],
            $file['name'] ?? 'attachment',
            ['Content-Type' => $file['content_type'] ?? 'application/octet-stream']
        );
    }

    public function compose(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'inbox_id' => ['required', 'integer'],
            'to' => ['required', 'string', 'max:2000'],
            'cc' => ['nullable', 'string', 'max:2000'],
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:5000000'],
            'send_at' => ['nullable', 'date', 'after:now'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.contentType' => ['nullable', 'string', 'max:120'],
            'attachments.*.contentBytes' => ['required_with:attachments', 'string', 'max:5000000'],
            'attachments.*.isInline' => ['nullable', 'boolean'],
            'attachments.*.contentId' => ['nullable', 'string', 'max:120'],
        ]);

        $inbox = $this->accessibleInboxes($user)
            ->where('id', $validated['inbox_id'])
            ->with('account')
            ->first();

        if (! $inbox) {
            return response()->json(['message' => 'Inbox not found.'], 404);
        }

        if (! $inbox->account) {
            return response()->json(['message' => 'This inbox is not connected to Outlook.'], 422);
        }

        $toEmails = collect(explode(',', $validated['to']))
            ->map(fn ($e) => trim($e))
            ->filter()
            ->values();

        if ($toEmails->isEmpty()) {
            return response()->json(['message' => 'Add at least one recipient.'], 422);
        }

        foreach ($toEmails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['message' => "Invalid recipient: {$email}"], 422);
            }
        }

        $ccEmails = $this->normalizeRecipientEmails($validated['cc'] ?? null)
            ->reject(fn ($email) => $toEmails->contains($email))
            ->values();
        foreach ($ccEmails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['message' => "Invalid recipient: {$email}"], 422);
            }
        }

        $htmlBody = $validated['body'];
        if (! str_contains($htmlBody, '<')) {
            $htmlBody = nl2br(e($htmlBody));
        }

        $attachments = $this->normalizeAttachments($validated['attachments'] ?? []);
        if ($attachments === false) {
            return response()->json(['message' => 'Attachments are too large. Keep each file under 3 MB.'], 422);
        }

        $prepared = $this->prepareTemplateContent($htmlBody, $attachments);
        if (count($prepared['attachments']) > 10) {
            return response()->json(['message' => 'Too many attachments. Use up to 5 files plus a few inline images.'], 422);
        }
        $htmlBody = $prepared['body'];
        $attachments = $prepared['attachments'];

        $to = $toEmails->implode(', ');
        $cc = $ccEmails->isNotEmpty() ? $ccEmails->implode(', ') : null;
        $fromEmail = $inbox->email ?? $inbox->account->email;

        if (! empty($validated['send_at'])) {
            $sendAt = $this->parseScheduledSendAt($validated['send_at']);
            if (! $sendAt || $sendAt->lte(now())) {
                return response()->json(['message' => 'Choose a future date and time.'], 422);
            }
            $localId = 'local-scheduled-compose-'.uniqid();

            $conversation = InboxConversation::create([
                'company_id' => $user->company_id,
                'shared_inbox_id' => $inbox->id,
                'folder' => 'drafts',
                'external_conversation_id' => $localId,
                'subject' => $validated['subject'],
                'snippet' => EmailQuotedHistory::snippet($htmlBody),
                'from_name' => $user->name,
                'from_email' => $fromEmail,
                'status' => 'drafts',
                'assigned_to' => $user->id,
                'is_read' => true,
                'message_count' => 0,
                'last_message_at' => $sendAt,
            ]);

            $scheduled = ScheduledInboxReply::create([
                'inbox_conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'shared_inbox_id' => $inbox->id,
                'type' => ScheduledInboxReply::TYPE_COMPOSE,
                'to_emails' => $to,
                'cc_emails' => $cc,
                'subject' => $validated['subject'],
                'body_html' => $htmlBody,
                'body_text' => strip_tags($htmlBody),
                'attachments' => [],
                'send_at' => $sendAt,
                'archive_after' => false,
                'status' => ScheduledInboxReply::STATUS_PENDING,
            ]);

            if ($attachments !== []) {
                $scheduled->update([
                    'attachments' => $this->replyService->storeScheduledAttachments($scheduled, $attachments),
                ]);
            }

            $this->recordActivity(
                $conversation,
                $user,
                'compose_scheduled',
                $user->name.' scheduled a message for '.$sendAt->timezone(config('app.timezone'))->format('M j, g:ia'),
                [
                    'scheduled_reply_id' => $scheduled->id,
                    'send_at' => $sendAt->toIso8601String(),
                ]
            );

            $conversation->load([
                'assignee',
                'tags',
                'leadLabels',
                'inbox',
                'scheduledReplies' => fn ($q) => $q->where('status', ScheduledInboxReply::STATUS_PENDING)->with('user:id,name,email'),
            ]);

            return response()->json([
                'scheduled' => true,
                'scheduled_reply' => $this->formatScheduledReply($scheduled->fresh(['user:id,name,email'])),
                'conversation' => $this->formatConversation($conversation),
            ], 201);
        }

        try {
            $result = $this->replyService->sendCompose($inbox, $user, [
                'to' => $to,
                'cc' => $cc,
                'subject' => $validated['subject'],
                'body' => $htmlBody,
                'attachments' => $attachments,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $this->applyLeadRules($result['conversation'], LeadRuleEngine::TRIGGER_OUTBOUND_MESSAGE_NEW);

        return response()->json([
            'conversation' => $this->formatConversation(
                $result['conversation']->fresh(['assignee', 'tags', 'leadLabels', 'inbox', 'messages']) ?? $result['conversation'],
                true
            ),
            'message' => $this->formatMessage($result['message']),
        ], 201);
    }

    public function syncTotals(Request $request): JsonResponse
    {
        @set_time_limit(120);

        $user = $request->user();
        $validated = $request->validate([
            'inbox_id' => ['nullable', 'integer'],
        ]);

        $query = $this->accessibleInboxes($user)->with('account');
        if (! empty($validated['inbox_id'])) {
            $query->where('id', (int) $validated['inbox_id']);
        }

        $inboxes = $query->get();

        $total = 0;
        $alreadySynced = 0;
        $graphTotal = 0;
        $details = [];

        foreach ($inboxes as $inbox) {
            if (! $inbox->account) {
                continue;
            }

            $counts = $this->mailService->getMailboxMessageTotals($inbox->fresh(['account']));
            $total += $counts['remaining'];
            $alreadySynced += $counts['already_synced'];
            $graphTotal += $counts['graph_total'];
            $details[] = [
                'id' => $inbox->id,
                'name' => $inbox->name,
                'total' => $counts['remaining'],
                'remaining' => $counts['remaining'],
                'already_synced' => $counts['already_synced'],
                'graph_total' => $counts['graph_total'],
                'folders' => $counts['folders'],
                'folders_remaining' => $counts['folders_remaining'],
                'folders_synced' => $counts['folders_synced'],
            ];
        }

        return response()->json([
            'total' => $total,
            'remaining' => $total,
            'already_synced' => $alreadySynced,
            'graph_total' => $graphTotal,
            'inboxes' => $details,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        @set_time_limit(600);

        $user = $request->user();
        $validated = $request->validate([
            'inbox_id' => ['nullable', 'integer'],
            'folder' => ['nullable', 'string', 'in:inbox,drafts,sent,trash,spam'],
            // When true (default), sync every accessible connected inbox.
            'all' => ['nullable', 'boolean'],
            // Page-by-page mode for progress UI.
            'paged' => ['nullable', 'boolean'],
            'next_link' => ['nullable', 'string', 'max:4000'],
            'fetched_so_far' => ['nullable', 'integer', 'min:0'],
        ]);

        $folder = $validated['folder'] ?? null;
        $paged = (bool) ($validated['paged'] ?? false);
        $syncAll = ($validated['all'] ?? true) && empty($validated['inbox_id']);

        // Folder-scoped sync requires a specific inbox (used by progress UI).
        if (($folder || $paged) && empty($validated['inbox_id'])) {
            return response()->json(['message' => 'inbox_id is required when syncing a folder.'], 422);
        }

        if ($paged) {
            if (! $folder) {
                return response()->json(['message' => 'folder is required for paged sync.'], 422);
            }

            $inbox = $this->accessibleInboxes($user)
                ->with('account')
                ->where('id', $validated['inbox_id'])
                ->first();

            if (! $inbox || ! $inbox->account) {
                return response()->json(['message' => 'Inbox not found or not connected.'], 404);
            }

            if (! isset(OutlookMailService::FOLDERS[$folder])) {
                return response()->json(['message' => 'Invalid folder.'], 422);
            }

            $page = $this->mailService->syncFolderPage(
                $inbox->fresh(['account']),
                $inbox->account,
                $folder,
                OutlookMailService::FOLDERS[$folder],
                $validated['next_link'] ?? null,
                (int) ($validated['fetched_so_far'] ?? 0)
            );

            if ($page['done']) {
                $inbox->last_synced_at = now();
                $inbox->save();
            }

            return response()->json([
                'synced' => $page['imported'],
                'fetched' => $page['fetched'],
                'skipped' => $page['skipped'] ?? max(0, $page['fetched'] - $page['imported']),
                'next_link' => $page['next_link'],
                'done' => $page['done'],
                'caught_up' => (bool) ($page['caught_up'] ?? false),
                'inbox_name' => $inbox->name,
                'folder' => $folder,
            ]);
        }

        $query = $this->accessibleInboxes($user)->with('account');
        if (! $syncAll && ! empty($validated['inbox_id'])) {
            $query->where('id', $validated['inbox_id']);
        }

        $total = 0;
        $syncedInboxes = 0;
        $skipped = [];
        $inboxName = null;

        foreach ($query->get() as $inbox) {
            if (! $inbox->account) {
                $skipped[] = ['id' => $inbox->id, 'name' => $inbox->name, 'reason' => 'not_connected'];

                continue;
            }

            $inboxName = $inbox->name;
            $total += $this->mailService->syncInbox($inbox->fresh(['account']), $folder);
            $syncedInboxes++;
        }

        return response()->json([
            'synced' => $total,
            'inboxes_synced' => $syncedInboxes,
            'inbox_name' => $inboxName,
            'folder' => $folder,
            'skipped' => $skipped,
        ]);
    }

    public function storeInbox(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'external_mailbox' => ['nullable', 'email', 'max:255'],
            'connect_mode' => ['nullable', 'in:mailbox_login,shared_mailbox'],
            'color' => ['nullable', 'string', 'max:20'],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer'],
        ]);

        $mode = $validated['connect_mode'] ?? 'mailbox_login';
        $hintEmail = $validated['email'];

        // mailbox_login: user will sign in as the MS365 mailbox itself (/me).
        // shared_mailbox: user signs in with an account that has access to external_mailbox.
        $externalMailbox = $mode === 'shared_mailbox'
            ? ($validated['external_mailbox'] ?? $validated['email'])
            : null;

        if ($mode === 'shared_mailbox' && ! $externalMailbox) {
            return response()->json([
                'message' => 'Shared mailbox email is required when using shared mailbox mode.',
            ], 422);
        }

        $inbox = SharedInbox::create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name' => $validated['name'],
            'email' => $hintEmail,
            'external_mailbox' => $externalMailbox,
            'type' => SharedInbox::TYPE_SHARED,
            'color' => $validated['color'] ?? '#5f61e6',
            'is_active' => true,
        ]);

        SharedInboxMember::create([
            'shared_inbox_id' => $inbox->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $memberIds = collect($validated['member_ids'] ?? [])
            ->filter(fn ($id) => (int) $id !== (int) $user->id)
            ->unique();

        $validIds = User::where('company_id', $user->company_id)
            ->whereIn('id', $memberIds)
            ->pluck('id');

        foreach ($validIds as $memberId) {
            SharedInboxMember::create([
                'shared_inbox_id' => $inbox->id,
                'user_id' => $memberId,
                'role' => 'member',
            ]);
        }

        $inbox->load(['members:id,name,email']);

        return response()->json([
            'inbox' => $this->formatInbox($inbox),
            'connect_url' => route('inbox.connect.outlook', [
                'intent' => 'shared',
                'shared_inbox_id' => $inbox->id,
            ]),
        ], 201);
    }

    public function updateInboxMembers(Request $request, SharedInbox $sharedInbox): JsonResponse
    {
        $user = $request->user();
        if (! $sharedInbox->userIsAdmin($user) || $sharedInbox->company_id !== $user->company_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($sharedInbox->isPersonal()) {
            return response()->json(['message' => 'Personal inbox members cannot be changed.'], 422);
        }

        $validated = $request->validate([
            'members' => ['required', 'array'],
            'members.*.user_id' => ['required', 'integer'],
            'members.*.role' => ['required', 'in:admin,member'],
        ]);

        $validUsers = User::where('company_id', $user->company_id)
            ->whereIn('id', collect($validated['members'])->pluck('user_id'))
            ->pluck('id')
            ->all();

        SharedInboxMember::where('shared_inbox_id', $sharedInbox->id)->delete();

        foreach ($validated['members'] as $row) {
            if (! in_array((int) $row['user_id'], $validUsers, true)) {
                continue;
            }
            SharedInboxMember::create([
                'shared_inbox_id' => $sharedInbox->id,
                'user_id' => $row['user_id'],
                'role' => $row['role'],
            ]);
        }

        // Ensure at least one admin (current user)
        if (! SharedInboxMember::where('shared_inbox_id', $sharedInbox->id)->where('role', 'admin')->exists()) {
            SharedInboxMember::updateOrCreate(
                ['shared_inbox_id' => $sharedInbox->id, 'user_id' => $user->id],
                ['role' => 'admin']
            );
        }

        $sharedInbox->load(['members:id,name,email']);

        return response()->json(['inbox' => $this->formatInbox($sharedInbox)]);
    }

    public function storeTag(Request $request): JsonResponse
    {
        if ($denied = $this->denyUnlessPermission($request, 'create_inbox_tags')) {
            return $denied;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $tag = InboxTag::firstOrCreate(
            [
                'company_id' => $request->user()->company_id,
                'name' => $validated['name'],
            ],
            ['color' => $validated['color'] ?? '#64748b']
        );

        return response()->json(['tag' => $tag], 201);
    }

    public function destroyTag(Request $request, InboxTag $tag): JsonResponse
    {
        if ($denied = $this->denyUnlessPermission($request, 'create_inbox_tags')) {
            return $denied;
        }

        if ($tag->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tagId = (int) $tag->id;
        $tag->delete();

        InboxUserSetting::query()
            ->whereNotNull('pinned_tag_ids')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($tagId) {
                foreach ($rows as $settings) {
                    $ids = collect($settings->pinned_tag_ids ?? [])->map(fn ($id) => (int) $id)->values();
                    if (! $ids->contains($tagId)) {
                        continue;
                    }
                    $settings->pinned_tag_ids = $ids->reject(fn ($id) => $id === $tagId)->values()->all();
                    $settings->save();
                }
            });

        return response()->json(['deleted' => true]);
    }

    public function syncPinnedTags(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $tagIds = collect($validated['tag_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $validIds = InboxTag::where('company_id', $user->company_id)
            ->whereIn('id', $tagIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Preserve caller order, drop invalid ids.
        $ordered = $tagIds->filter(fn ($id) => in_array($id, $validIds, true))->values()->all();

        $settings = InboxUserSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['pinned_tag_ids' => $ordered]
        );

        return response()->json([
            'pinned_tag_ids' => $settings->pinned_tag_ids ?? [],
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        if ($denied = $this->denyUnlessPermission($request, 'create_inbox_templates')) {
            return $denied;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:500'],
            'body_html' => ['nullable', 'string', 'max:5000000'],
            'body' => ['nullable', 'string', 'max:100000'],
            'body_text' => ['nullable', 'string', 'max:100000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.contentType' => ['nullable', 'string', 'max:120'],
            'attachments.*.contentBytes' => ['required_with:attachments', 'string', 'max:5000000'],
            'attachments.*.isInline' => ['nullable', 'boolean'],
            'attachments.*.contentId' => ['nullable', 'string', 'max:120'],
        ]);

        $bodyHtml = $validated['body_html'] ?? null;
        $bodyText = $validated['body_text'] ?? $validated['body'] ?? null;
        if (! $bodyHtml && $bodyText) {
            $bodyHtml = nl2br(e($bodyText));
        }
        $bodyText = $this->templatePlainText($bodyText, $bodyHtml);
        if (! $bodyText) {
            return response()->json(['message' => 'Template body is required.'], 422);
        }

        $attachments = $this->normalizeAttachments($validated['attachments'] ?? []);
        if ($attachments === false) {
            return response()->json(['message' => 'One or more attachments exceed the 3 MB limit.'], 422);
        }

        $prepared = $this->prepareTemplateContent((string) $bodyHtml, $attachments);
        if (count($prepared['attachments']) > 10) {
            return response()->json(['message' => 'A template can include at most 5 file attachments plus inline images.'], 422);
        }

        $template = InboxTemplate::create([
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'subject' => $validated['subject'] ?? null,
            'body_html' => $prepared['body'],
            'body_text' => $bodyText,
            'attachments' => $prepared['attachments'] !== [] ? $prepared['attachments'] : null,
        ]);

        return response()->json(['template' => $this->formatTemplate($template)], 201);
    }

    public function updateTemplate(Request $request, InboxTemplate $template): JsonResponse
    {
        if ($denied = $this->denyUnlessPermission($request, 'create_inbox_templates')) {
            return $denied;
        }

        if ($template->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:500'],
            'body_html' => ['nullable', 'string', 'max:5000000'],
            'body' => ['nullable', 'string', 'max:100000'],
            'body_text' => ['nullable', 'string', 'max:100000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.contentType' => ['nullable', 'string', 'max:120'],
            'attachments.*.contentBytes' => ['required_with:attachments', 'string', 'max:5000000'],
            'attachments.*.isInline' => ['nullable', 'boolean'],
            'attachments.*.contentId' => ['nullable', 'string', 'max:120'],
        ]);

        $bodyHtml = $validated['body_html'] ?? null;
        $bodyText = $validated['body_text'] ?? $validated['body'] ?? null;
        if (! $bodyHtml && $bodyText) {
            $bodyHtml = nl2br(e($bodyText));
        }
        $bodyText = $this->templatePlainText($bodyText, $bodyHtml);
        if (! $bodyText) {
            return response()->json(['message' => 'Template body is required.'], 422);
        }

        $attachments = $this->normalizeAttachments($validated['attachments'] ?? []);
        if ($attachments === false) {
            return response()->json(['message' => 'One or more attachments exceed the 3 MB limit.'], 422);
        }

        $prepared = $this->prepareTemplateContent((string) $bodyHtml, $attachments);
        if (count($prepared['attachments']) > 10) {
            return response()->json(['message' => 'A template can include at most 5 file attachments plus inline images.'], 422);
        }

        $template->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'] ?? null,
            'body_html' => $prepared['body'],
            'body_text' => $bodyText,
            'attachments' => $prepared['attachments'] !== [] ? $prepared['attachments'] : null,
        ]);

        return response()->json(['template' => $this->formatTemplate($template->fresh())]);
    }

    public function destroyTemplate(Request $request, InboxTemplate $template): JsonResponse
    {
        if ($denied = $this->denyUnlessPermission($request, 'create_inbox_templates')) {
            return $denied;
        }

        if ($template->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $template->delete();

        return response()->json(['deleted' => true]);
    }

    public function importTemplates(Request $request): JsonResponse
    {
        if ($denied = $this->denyUnlessPermission($request, 'create_inbox_templates')) {
            return $denied;
        }

        $validated = $request->validate([
            'templates' => ['required', 'array', 'max:100'],
            'templates.*.name' => ['required', 'string', 'max:160'],
            'templates.*.subject' => ['nullable', 'string', 'max:500'],
            'templates.*.body_html' => ['nullable', 'string', 'max:100000'],
            'templates.*.body' => ['nullable', 'string', 'max:100000'],
            'templates.*.body_text' => ['nullable', 'string', 'max:100000'],
        ]);

        $companyId = $request->user()->company_id;
        $existingNames = InboxTemplate::where('company_id', $companyId)
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim($name)))
            ->all();
        $existingLookup = array_fill_keys($existingNames, true);

        $imported = [];
        foreach ($validated['templates'] as $row) {
            $name = trim($row['name']);
            $key = mb_strtolower($name);
            if (isset($existingLookup[$key])) {
                continue;
            }

            $bodyHtml = $row['body_html'] ?? null;
            $bodyText = $row['body_text'] ?? $row['body'] ?? null;
            if (! $bodyHtml && $bodyText) {
                $bodyHtml = nl2br(e($bodyText));
            }
            $bodyText = $this->templatePlainText($bodyText, $bodyHtml);
            if (! $bodyText) {
                continue;
            }

            $template = InboxTemplate::create([
                'company_id' => $companyId,
                'created_by' => $request->user()->id,
                'name' => $name,
                'subject' => $row['subject'] ?? null,
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
            ]);
            $existingLookup[$key] = true;
            $imported[] = $this->formatTemplate($template);
        }

        $templates = InboxTemplate::where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (InboxTemplate $template) => $this->formatTemplate($template));

        return response()->json([
            'imported' => count($imported),
            'templates' => $templates,
        ]);
    }

    private function denyUnlessPermission(Request $request, string $slug): ?JsonResponse
    {
        if ($request->user()?->hasPermission($slug)) {
            return null;
        }

        return response()->json([
            'message' => 'You do not have permission to perform this action.',
        ], 403);
    }

    private function accessibleInboxes(User $user)
    {
        return SharedInbox::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('type', '!=', SharedInbox::TYPE_BROADCAST)
            ->where(function ($q) use ($user) {
                $q->where(function ($personal) use ($user) {
                    $personal->where('type', SharedInbox::TYPE_PERSONAL)
                        ->where('created_by', $user->id);
                })->orWhere(function ($shared) use ($user) {
                    $shared->where('type', SharedInbox::TYPE_SHARED)
                        ->whereHas('members', fn ($m) => $m->where('users.id', $user->id));
                });
            });
    }

    /**
     * @param  array<int, array{name?: string, contentType?: string, contentBytes?: string, isInline?: bool, contentId?: string}>  $attachments
     * @return array<int, array{name: string, contentType: string, contentBytes: string, isInline?: bool, contentId?: string}>|false
     */
    private function normalizeAttachments(array $attachments): array|false
    {
        $normalized = [];
        foreach ($attachments as $attachment) {
            $name = trim((string) ($attachment['name'] ?? ''));
            $bytes = (string) ($attachment['contentBytes'] ?? '');
            if ($name === '' || $bytes === '') {
                continue;
            }

            // Reject obviously oversized payloads (~3MB decoded).
            $approxBytes = (int) (strlen($bytes) * 0.75);
            if ($approxBytes > 3 * 1024 * 1024) {
                return false;
            }

            $item = [
                'name' => $name,
                'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
                'contentBytes' => $bytes,
            ];

            if (! empty($attachment['isInline']) && ! empty($attachment['contentId'])) {
                $item['isInline'] = true;
                $item['contentId'] = (string) $attachment['contentId'];
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private function templatePlainText(?string $bodyText, ?string $bodyHtml): ?string
    {
        $text = trim((string) $bodyText);
        if ($text !== '') {
            return $text;
        }
        if ($bodyHtml) {
            $text = trim(strip_tags($bodyHtml));
            if ($text !== '') {
                return $text;
            }
            if (preg_match('/<img\b/i', $bodyHtml)) {
                return '(image)';
            }
        }

        return null;
    }

    /**
     * Convert data-URI images in HTML into cid: inline attachments for storage/send.
     *
     * @param  array<int, array{name: string, contentType: string, contentBytes: string, isInline?: bool, contentId?: string}>  $attachments
     * @return array{body: string, attachments: array<int, array{name: string, contentType: string, contentBytes: string, isInline?: bool, contentId?: string}>}
     */
    private function prepareTemplateContent(string $body, array $attachments): array
    {
        $inlineCount = 0;

        $body = preg_replace_callback(
            '/<img\b([^>]*)\ssrc=(["\'])data:image\/([^;]+);base64,([^"\']+)\2([^>]*)>/i',
            function (array $matches) use (&$attachments, &$inlineCount) {
                if (count($attachments) >= 10) {
                    return $matches[0];
                }

                $ext = strtolower($matches[3]);
                $bytes = $matches[4];
                $approxBytes = (int) (strlen($bytes) * 0.75);
                if ($approxBytes > 3 * 1024 * 1024) {
                    return $matches[0];
                }

                $inlineCount++;
                $contentId = 'tpl-img-'.$inlineCount.'-'.bin2hex(random_bytes(4));
                $attachments[] = [
                    'name' => 'image-'.$inlineCount.'.'.$ext,
                    'contentType' => 'image/'.$ext,
                    'contentBytes' => $bytes,
                    'isInline' => true,
                    'contentId' => $contentId,
                ];

                $before = trim($matches[1]);
                $after = trim($matches[5]);

                return '<img'.($before ? ' '.$before : '').' src="cid:'.$contentId.'"'.($after ? ' '.$after : '').'>';
            },
            $body
        ) ?? $body;

        return [
            'body' => $body,
            'attachments' => $attachments,
        ];
    }

    /**
     * Embed cid: inline attachments as data URIs for the template editor / insert preview.
     *
     * @param  array<int, array<string, mixed>>|null  $attachments
     */
    private function embedTemplateInlineImages(string $html, ?array $attachments): string
    {
        if (! is_array($attachments) || $attachments === []) {
            return $html;
        }

        foreach ($attachments as $file) {
            if (empty($file['isInline']) || empty($file['contentId']) || empty($file['contentBytes'])) {
                continue;
            }
            $dataUri = 'data:'.($file['contentType'] ?? 'image/png').';base64,'.$file['contentBytes'];
            $html = str_replace('cid:'.$file['contentId'], $dataUri, $html);
        }

        return $html;
    }

    /**
     * Parse a scheduled send time in the app timezone.
     * Accepts "YYYY-MM-DD HH:MM:SS", "YYYY-MM-DDTHH:MM", or ISO-8601.
     */
    private function parseScheduledSendAt(mixed $value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $tz = config('app.timezone') ?: 'UTC';

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $raw)) {
                return Carbon::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $raw), $tz);
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $raw)) {
                return Carbon::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $raw), $tz);
            }

            return Carbon::parse($raw, $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    private function authorizeConversation(User $user, InboxConversation $conversation): void
    {
        $inbox = $conversation->inbox;
        if (! $inbox || ! $inbox->userCanAccess($user)) {
            abort(403, 'Forbidden');
        }
    }

    private function formatInbox(SharedInbox $inbox): array
    {
        return [
            'id' => $inbox->id,
            'name' => $inbox->name,
            'email' => $inbox->email,
            'type' => $inbox->type,
            'color' => $inbox->color,
            'external_mailbox' => $inbox->external_mailbox,
            'account_email' => $inbox->account?->email,
            'connected' => (bool) $inbox->outlook_mail_account_id,
            'connect_url' => $inbox->type === SharedInbox::TYPE_SHARED
                ? route('inbox.connect.outlook', [
                    'intent' => 'shared',
                    'shared_inbox_id' => $inbox->id,
                ])
                : route('inbox.connect.outlook'),
            'last_synced_at' => $inbox->last_synced_at?->toIso8601String(),
            'open_count' => $inbox->open_count ?? null,
            'unread_count' => $inbox->unread_count ?? null,
            'archived_count' => $inbox->archived_count ?? null,
            'snoozed_count' => $inbox->snoozed_count ?? null,
            'drafts_count' => $inbox->drafts_count ?? null,
            'sent_count' => $inbox->sent_count ?? null,
            'trash_count' => $inbox->trash_count ?? null,
            'spam_count' => $inbox->spam_count ?? null,
            'members' => $inbox->relationLoaded('members')
                ? $inbox->members->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'email' => $m->email,
                    'role' => $m->pivot->role ?? 'member',
                ])
                : [],
        ];
    }

    private function formatConversation(InboxConversation $c, bool $withMessages = false): array
    {
        $data = [
            'id' => $c->id,
            'inbox_id' => $c->shared_inbox_id,
            'folder' => $c->folder ?: 'inbox',
            'inbox' => $c->relationLoaded('inbox') && $c->inbox ? [
                'id' => $c->inbox->id,
                'name' => $c->inbox->name,
                'email' => $c->inbox->email,
                'type' => $c->inbox->type,
                'color' => $c->inbox->color,
            ] : null,
            'subject' => $c->subject,
            'snippet' => $this->conversationSnippet($c, $withMessages),
            'from_name' => $c->from_name,
            'from_email' => $c->from_email,
            'status' => $c->status,
            'is_read' => $c->is_read,
            'message_count' => $c->message_count,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'reopen_at' => $c->reopen_at?->toIso8601String(),
            'assigned_to' => $c->assigned_to,
            'lead_id' => $c->lead_id ? (int) $c->lead_id : null,
            'assignee' => $c->relationLoaded('assignee') && $c->assignee ? [
                'id' => $c->assignee->id,
                'name' => $c->assignee->name,
                'email' => $c->assignee->email,
            ] : null,
            'tags' => $c->relationLoaded('tags')
                ? $c->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])
                : [],
            'lead_labels' => $c->relationLoaded('leadLabels')
                ? $c->leadLabels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])
                : [],
            'lead' => $this->conversationLeadPayload($c),
            'merged_count' => (int) ($c->merged_conversations_count
                ?? ($c->relationLoaded('mergedConversations') ? $c->mergedConversations->count() : 0)),
            'merged_threads' => $c->relationLoaded('mergedConversations')
                ? $c->mergedConversations->map(fn (InboxConversation $m) => $this->formatMergeCandidate($m))->values()->all()
                : [],
        ];

        if ($withMessages && $c->relationLoaded('messages')) {
            $data['messages'] = $c->messages->map(fn ($m) => $this->formatMessage($m));
        }

        if ($withMessages && $c->relationLoaded('comments')) {
            $data['comments'] = $c->comments->map(fn ($comment) => $this->formatComment($comment));
        }

        if ($withMessages && $c->relationLoaded('activities')) {
            $data['activities'] = $c->activities->map(fn ($activity) => $this->formatActivity($activity));
        }

        if ($c->relationLoaded('scheduledReplies')) {
            $data['scheduled_replies'] = $c->scheduledReplies
                ->filter(fn (ScheduledInboxReply $r) => $r->status === ScheduledInboxReply::STATUS_PENDING)
                ->map(fn (ScheduledInboxReply $r) => $this->formatScheduledReply($r))
                ->values();
        }

        if ($withMessages) {
            $data['member_reads'] = $this->formatMemberReads($c);
        }

        return $data;
    }

    private function conversationSnippet(InboxConversation $c, bool $withMessages = false): string
    {
        $html = null;
        $text = (string) ($c->snippet ?? '');
        if ($withMessages && $c->relationLoaded('messages') && $c->messages->isNotEmpty()) {
            $latest = $c->messages->sortBy([
                ['sent_at', 'asc'],
                ['id', 'asc'],
            ])->last();
            if ($latest) {
                $html = $latest->body_html;
                $text = (string) ($latest->body_text ?: $c->snippet);
            }
        }

        return EmailQuotedHistory::snippet($html, $text);
    }

    /**
     * @param  Collection<int, InboxMessage>  $messages
     * @return Collection<int, InboxMessage>
     */
    private function dedupeThreadMessages(Collection $messages): Collection
    {
        $sorted = $messages->sortBy([
            ['sent_at', 'asc'],
            ['id', 'asc'],
        ])->values();

        $seenExternal = [];
        $unique = $sorted->filter(function (InboxMessage $message) use (&$seenExternal) {
            $externalId = (string) ($message->external_message_id ?? '');
            if ($externalId === '' || str_starts_with($externalId, 'local-')) {
                return true;
            }
            if (isset($seenExternal[$externalId])) {
                return false;
            }
            $seenExternal[$externalId] = true;

            return true;
        })->values();

        return $unique->filter(function (InboxMessage $message) use ($unique) {
            $externalId = (string) ($message->external_message_id ?? '');
            if ($message->direction !== 'outbound' || ! str_starts_with($externalId, 'local-')) {
                return true;
            }

            return ! $unique->contains(function (InboxMessage $other) use ($message) {
                if ((int) $other->id === (int) $message->id || $other->direction !== 'outbound') {
                    return false;
                }
                $otherId = (string) ($other->external_message_id ?? '');
                if ($otherId === '' || str_starts_with($otherId, 'local-')) {
                    return false;
                }
                if (! $message->sent_at || ! $other->sent_at) {
                    return false;
                }

                return abs($message->sent_at->diffInSeconds($other->sent_at)) <= 900;
            });
        })->values();
    }

    /**
     * Shared-inbox teammates and when each of them last read this conversation.
     *
     * @return list<array{id: int, name: string, email: ?string, is_read: bool, last_read_at: ?string}>
     */
    private function formatMemberReads(InboxConversation $c): array
    {
        $inbox = $c->relationLoaded('inbox') ? $c->inbox : null;
        if (! $inbox || $inbox->type !== SharedInbox::TYPE_SHARED) {
            return [];
        }

        $members = $inbox->relationLoaded('members')
            ? $inbox->members
            : $inbox->members()->orderBy('name')->get(['users.id', 'users.name', 'users.email']);

        if ($members->isEmpty()) {
            return [];
        }

        $reads = $c->relationLoaded('userReads')
            ? $c->userReads->keyBy('user_id')
            : InboxConversationUserRead::query()
                ->where('inbox_conversation_id', $c->id)
                ->whereIn('user_id', $members->pluck('id'))
                ->get()
                ->keyBy('user_id');

        return $members
            ->map(function ($member) use ($reads) {
                $row = $reads->get($member->id);
                $isRead = (bool) ($row?->is_read);
                $lastReadAt = $row?->last_read_at;

                return [
                    'id' => (int) $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'is_read' => $isRead,
                    'last_read_at' => $isRead && $lastReadAt ? $lastReadAt->toIso8601String() : null,
                ];
            })
            ->sort(function (array $a, array $b) {
                if ($a['is_read'] !== $b['is_read']) {
                    return $a['is_read'] ? -1 : 1;
                }
                if ($a['is_read'] && $b['is_read']) {
                    return strcmp((string) ($b['last_read_at'] ?? ''), (string) ($a['last_read_at'] ?? ''));
                }

                return strcasecmp((string) $a['name'], (string) $b['name']);
            })
            ->values()
            ->all();
    }

    private function formatScheduledReply(ScheduledInboxReply $reply): array
    {
        return [
            'id' => $reply->id,
            'type' => $reply->type ?: ScheduledInboxReply::TYPE_REPLY,
            'to' => $reply->to_emails,
            'cc' => $reply->cc_emails,
            'subject' => $reply->subject,
            'body_html' => $reply->body_html,
            'body_text' => $reply->body_text,
            'send_at' => $reply->send_at?->toIso8601String(),
            'archive_after' => (bool) $reply->archive_after,
            'status' => $reply->status,
            'attachment_count' => count($reply->attachments ?? []),
            'user' => $reply->relationLoaded('user') && $reply->user ? [
                'id' => $reply->user->id,
                'name' => $reply->user->name,
                'email' => $reply->user->email,
            ] : null,
            'created_at' => $reply->created_at?->toIso8601String(),
        ];
    }

    private function formatMergeCandidate(InboxConversation $c): array
    {
        return [
            'id' => $c->id,
            'subject' => $c->subject,
            'snippet' => EmailQuotedHistory::snippet(null, $c->snippet),
            'from_name' => $c->from_name,
            'from_email' => $c->from_email,
            'folder' => $c->folder ?: 'inbox',
            'message_count' => $c->message_count,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
        ];
    }

    private function formatTemplate(InboxTemplate $template): array
    {
        $attachments = is_array($template->attachments) ? array_values($template->attachments) : [];
        $bodyHtml = $this->embedTemplateInlineImages((string) ($template->body_html ?? ''), $attachments);

        return [
            'id' => $template->id,
            'name' => $template->name,
            'subject' => $template->subject,
            'body' => $template->body_text,
            'body_text' => $template->body_text,
            'body_html' => $bodyHtml,
            'attachments' => $attachments,
            'format' => 'html',
            'created_by' => $template->created_by,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    private function formatMessage(InboxMessage $m): array
    {
        $allAttachments = collect($m->attachments ?? [])->values();

        $attachments = $allAttachments
            ->map(function ($file, $index) use ($m) {
                if (! is_array($file) || empty($file['id']) || empty($file['name'])) {
                    return null;
                }
                // Inline/cid images are shown inside the HTML body, not as chips.
                if (! empty($file['is_inline'])) {
                    return null;
                }

                return [
                    'id' => $file['id'],
                    'name' => $file['name'] ?? 'file',
                    'content_type' => $file['content_type'] ?? 'application/octet-stream',
                    'size' => $file['size'] ?? null,
                    'index' => $index,
                    'download_url' => url('/api/inbox/conversations/'.$m->inbox_conversation_id.'/messages/'.$m->id.'/attachments/'.$index),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'from_name' => $m->from_name,
            'from_email' => $m->from_email,
            'to_emails' => $m->to_emails,
            'cc_emails' => $m->cc_emails,
            'reply_to_emails' => $m->reply_to_emails,
            'to' => $this->parseEmailList($m->to_emails),
            'cc' => $this->parseEmailList($m->cc_emails),
            'reply_to' => $this->parseEmailList($m->reply_to_emails),
            'subject' => $m->subject,
            'body_html' => $this->rewriteCidImagesForClient((string) ($m->body_html ?? ''), $m, $allAttachments),
            'body_text' => $m->body_text,
            'is_read' => (bool) $m->is_read,
            'attachments' => $attachments,
            'sent_at' => $m->sent_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseEmailList(?string $value): array
    {
        return $this->normalizeRecipientEmails($value)->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function normalizeRecipientEmails(?string $value): Collection
    {
        return collect(preg_split('/[,;]+/', (string) $value) ?: [])
            ->map(function ($part) {
                $part = trim((string) $part);
                if ($part === '') {
                    return '';
                }
                if (preg_match('/<([^>]+)>/', $part, $matches)) {
                    $part = trim($matches[1]);
                }

                return strtolower($part);
            })
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Fallback: map any remaining cid: refs to authenticated attachment URLs for display.
     *
     * @param  Collection<int, mixed>  $allAttachments
     */
    private function rewriteCidImagesForClient(string $html, InboxMessage $m, Collection $allAttachments): string
    {
        if ($html === '' || ! preg_match('/cid:/i', $html)) {
            return $html;
        }

        foreach ($allAttachments as $index => $file) {
            if (! is_array($file)) {
                continue;
            }
            $cid = trim((string) ($file['content_id'] ?? ''), "<> \t\r\n");
            if ($cid === '') {
                continue;
            }
            $url = url('/api/inbox/conversations/'.$m->inbox_conversation_id.'/messages/'.$m->id.'/attachments/'.$index);
            $quoted = preg_quote($cid, '/');
            $html = preg_replace(
                '/(src\s*=\s*["\'])cid:'.$quoted.'(?:@[^"\']*)?(["\'])/i',
                '$1'.$url.'$2',
                $html
            ) ?? $html;
            $html = preg_replace(
                '/(url\(\s*[\'"]?)cid:'.$quoted.'(?:@[^\'"\)]*)?([\'"]?\s*\))/i',
                '$1'.$url.'$2',
                $html
            ) ?? $html;
        }

        return $html;
    }

    private function formatComment(InboxConversationComment $comment): array
    {
        $attachments = collect($comment->attachments ?? [])->map(function ($file) use ($comment) {
            $index = $file['index'] ?? 0;

            return [
                'name' => $file['name'] ?? 'file',
                'content_type' => $file['content_type'] ?? 'application/octet-stream',
                'size' => $file['size'] ?? null,
                'index' => $index,
                'download_url' => url('/api/inbox/conversations/'.$comment->inbox_conversation_id.'/comments/'.$comment->id.'/attachments/'.$index),
            ];
        })->values()->all();

        return [
            'id' => $comment->id,
            'body_html' => $comment->body_html,
            'body_text' => $comment->body_text,
            'mentioned_user_ids' => $comment->mentioned_user_ids ?? [],
            'attachments' => $attachments,
            'user' => $comment->relationLoaded('user') && $comment->user ? [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'email' => $comment->user->email,
            ] : null,
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }

    private function formatActivity(InboxConversationActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'action' => $activity->action,
            'summary' => $activity->summary,
            'meta' => $activity->meta ?? [],
            'user' => $activity->relationLoaded('user') && $activity->user ? [
                'id' => $activity->user->id,
                'name' => $activity->user->name,
                'email' => $activity->user->email,
            ] : null,
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private function recordActivity(
        InboxConversation $conversation,
        ?User $actor,
        string $action,
        string $summary,
        ?array $meta = null
    ): InboxConversationActivity {
        $activity = InboxConversationActivity::create([
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'summary' => mb_substr($summary, 0, 500),
            'meta' => $meta,
        ]);

        $this->notifyConversationWatchers($conversation, $actor, $action, $summary, $meta ?? []);

        return $activity;
    }

    /**
     * Notify inbox members, assignee, prior commenters, and any extra mentioned users.
     *
     * @param  array<string, mixed>  $meta
     */
    private function notifyConversationWatchers(
        InboxConversation $conversation,
        ?User $actor,
        string $action,
        string $summary,
        array $meta = []
    ): void {
        $mentionedIds = collect($meta['mentioned_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $recipients = $this->conversationNotifyRecipients(
            $conversation,
            $actor,
            $mentionedIds->all()
        );

        if ($recipients->isEmpty()) {
            return;
        }

        $snippet = isset($meta['snippet']) ? (string) $meta['snippet'] : null;
        $subjectLabel = $conversation->subject ?: 'a conversation';

        foreach ($recipients as $recipient) {
            $isMention = $action === 'commented' && $mentionedIds->contains((int) $recipient->id);
            $notifySummary = $isMention
                ? (($actor?->name ?: 'Someone').' mentioned you in "'.$subjectLabel.'"')
                : $summary;

            try {
                $recipient->notify(new InboxThreadUpdateNotification(
                    conversation: $conversation,
                    action: $isMention ? 'mention' : $action,
                    summary: $notifySummary,
                    actor: $actor,
                    snippet: $snippet,
                    isMention: $isMention,
                ));
            } catch (\Throwable $e) {
                Log::warning('Failed to notify inbox thread watcher', [
                    'conversation_id' => $conversation->id,
                    'action' => $action,
                    'user_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<int, int|string>  $extraUserIds
     * @return Collection<int, User>
     */
    private function conversationNotifyRecipients(
        InboxConversation $conversation,
        ?User $except = null,
        array $extraUserIds = []
    ): Collection {
        $conversation->loadMissing('inbox');

        $ids = collect($extraUserIds);

        $inbox = $conversation->inbox;
        if ($inbox) {
            if ($inbox->isPersonal()) {
                if ($inbox->created_by) {
                    $ids->push((int) $inbox->created_by);
                }
            } else {
                $ids = $ids->merge($inbox->members()->pluck('users.id'));
            }
        }

        if ($conversation->assigned_to) {
            $ids->push((int) $conversation->assigned_to);
        }

        $ids = $ids->merge(
            InboxConversationComment::query()
                ->where('inbox_conversation_id', $conversation->id)
                ->pluck('user_id')
        );

        $ids = $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($except) {
            $ids = $ids->reject(fn ($id) => $id === (int) $except->id)->values();
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('company_id', $conversation->company_id)
            ->whereIn('id', $ids)
            ->get();
    }

    private function fireConversationStatusRules(InboxConversation $conversation, string $status): void
    {
        // Inbox archive/move rules now live on Leads as channel conditions + lead status.
    }

    /**
     * @param  string|array<int, string>  $triggers
     */
    private function applyLeadRules(InboxConversation $conversation, string|array $triggers): void
    {
        $fresh = $conversation->fresh(['inbox']);
        if (! $fresh) {
            return;
        }

        $latest = $fresh->messages()->orderByDesc('sent_at')->orderByDesc('id')->first();

        $this->leadAutoCreate->applyRules(
            $this->leadAutoCreate->fromInboxConversation($fresh),
            'inbox',
            $triggers,
            [
                'company_id' => (int) $fresh->company_id,
                'contact_name' => $fresh->from_name,
                'email' => $fresh->from_email,
                'subject' => $fresh->subject,
                'message' => (string) ($latest?->body_text ?: $fresh->snippet),
                'inbox_id' => $fresh->shared_inbox_id,
                'shared_inbox_id' => $fresh->shared_inbox_id,
                'inbox_conversation_id' => $fresh->id,
            ]
        );
    }

    private function recordStatusActivity(
        InboxConversation $conversation,
        User $actor,
        ?string $oldStatus,
        string $oldFolder,
        string $newStatus,
        string $newFolder
    ): void {
        if ($oldStatus === $newStatus && $oldFolder === $newFolder) {
            return;
        }

        $map = [
            'archived' => ['archived', $actor->name.' archived this conversation'],
            'spam' => ['moved_spam', $actor->name.' marked this as spam'],
            'trashed' => ['moved_trash', $actor->name.' moved this to trash'],
            'open' => ['restored', $actor->name.($oldStatus === 'archived' ? ' reopened' : ' restored').' this conversation'],
        ];

        [$action, $summary] = $map[$newStatus] ?? [
            'status_changed',
            $actor->name.' changed status to '.$newStatus,
        ];

        $this->recordActivity($conversation, $actor, $action, $summary, [
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'from_folder' => $oldFolder,
            'to_folder' => $newFolder,
        ]);
    }

    private function matchingLead(InboxConversation $conversation, ?int $leadId = null): ?Lead
    {
        $companyId = (int) $conversation->company_id;
        if ($leadId) {
            $lead = Lead::query()->where('company_id', $companyId)->find($leadId);
            if ($lead) {
                return $lead;
            }
        }

        if ($conversation->lead_id) {
            $attached = Lead::query()->where('company_id', $companyId)->find($conversation->lead_id);
            if ($attached) {
                return $attached;
            }
        }

        $email = $this->contactEmail($conversation->from_email);
        if ($email) {
            $lead = $this->crmLookup->findLeadByEmail($companyId, $email);
            if ($lead) {
                return $lead;
            }
        }
        if ($conversation->from_name) {
            return $this->crmLookup->findLeadByName($companyId, $conversation->from_name);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function conversationLeadPayload(InboxConversation $c): ?array
    {
        if ($c->lead_id) {
            $lead = $c->relationLoaded('lead') ? $c->lead : Lead::query()->where('company_id', $c->company_id)->find($c->lead_id);
            if ($lead) {
                return $this->crmLookup->serializeLead($lead);
            }
        }

        return $this->crmLookup->matchAssignedLead(
            $this->crmLookup->leadIndex((int) $c->company_id),
            null,
            $c->from_email,
            $c->from_name
        );
    }

    private function contactEmail(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/<([^>]+@[^>]+)>/', $raw, $matches)) {
            return strtolower(trim($matches[1]));
        }
        if (str_contains($raw, '@')) {
            return strtolower($raw);
        }

        return null;
    }

    private function syncLeadAssignment(InboxConversation $conversation, mixed $userId): void
    {
        $lead = $this->matchingLead($conversation);
        if (! $lead) {
            return;
        }

        $toId = $userId ? (int) $userId : null;
        $fromId = $lead->assigned_to ? (int) $lead->assigned_to : null;
        if ($fromId === $toId) {
            return;
        }

        $lead->assigned_to = $toId;
        $lead->save();
        $this->leadActivity->recordAssignment($lead, $fromId, $toId, reason: 'inbox');
        $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);
    }
}
