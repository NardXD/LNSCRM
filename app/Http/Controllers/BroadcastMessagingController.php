<?php

namespace App\Http\Controllers;

use App\Models\BroadcastCampaign;
use App\Models\BroadcastCampaignRecipient;
use App\Services\BroadcastMessagingService;
use App\Services\OutlookMailService;
use App\Services\TwilioCompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BroadcastMessagingController extends Controller
{
    public function __construct(
        protected BroadcastMessagingService $broadcasts,
        protected TwilioCompanyService $twilioCompany,
        protected OutlookMailService $mailService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $company = $user?->company;
        $outlookCreds = $this->mailService->getMailCredentials($user?->company_id);

        return view('dashboard.broadcast-messaging', [
            'twilioConnected' => (bool) ($company && $this->twilioCompany->getActiveIntegration($company)),
            'outlookConfigured' => ! empty($outlookCreds['client_id']) && ! empty($outlookCreds['client_secret']),
            'canSendSms' => (bool) $user?->hasPermission('send_broadcast_sms'),
            'canSendEmail' => (bool) $user?->hasPermission('send_broadcast_email'),
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;
        $outlookCreds = $this->mailService->getMailCredentials($user->company_id);

        return response()->json([
            'success' => true,
            'data' => [
                'twilio_connected' => (bool) ($company && $this->twilioCompany->getActiveIntegration($company)),
                'outlook_configured' => ! empty($outlookCreds['client_id']) && ! empty($outlookCreds['client_secret']),
                'can_send_sms' => $user->hasPermission('send_broadcast_sms'),
                'can_send_email' => $user->hasPermission('send_broadcast_email'),
                'max_recipients' => BroadcastMessagingService::maxRecipients(),
                'sms_senders' => $this->broadcasts->smsSenders($user),
                'email_senders' => $this->broadcasts->emailSenders($user),
                'integrations_url' => route('integrations'),
                'inbox_url' => route('inbox'),
                'outlook_connect_url' => route('inbox.connect.outlook', ['intent' => 'broadcast']),
            ],
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = BroadcastCampaign::query()
            ->where('company_id', $user->company_id)
            ->with('creator:id,name')
            ->orderByDesc('created_at');

        $type = trim((string) $request->query('type', ''));
        if (in_array($type, [BroadcastCampaign::TYPE_SMS, BroadcastCampaign::TYPE_EMAIL], true)) {
            $query->where('type', $type);
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sender_label', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%');
            });
        }

        $perPage = min(50, max(10, (int) $request->query('per_page', 20)));
        $campaigns = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($campaigns->items())->map(fn (BroadcastCampaign $campaign) => $this->serializeCampaign($campaign))->all(),
            'pagination' => [
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total(),
            ],
        ]);
    }

    public function show(Request $request, BroadcastCampaign $campaign): JsonResponse
    {
        $campaign->load(['creator:id,name']);

        $perPage = min(50, max(10, (int) $request->query('per_page', 20)));
        $recipients = $campaign->recipients()->orderBy('id')->paginate($perPage);

        $data = $this->serializeCampaign($campaign, true);
        $data['recipient_addresses'] = $campaign->recipients()
            ->pluck('address')
            ->map(fn ($address) => strtolower((string) $address))
            ->values()
            ->all();
        $data['recipients'] = collect($recipients->items())
            ->map(fn (BroadcastCampaignRecipient $recipient) => $this->serializeRecipient($recipient))
            ->values()
            ->all();
        $data['recipients_pagination'] = [
            'current_page' => $recipients->currentPage(),
            'last_page' => $recipients->lastPage(),
            'per_page' => $recipients->perPage(),
            'total' => $recipients->total(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function recipients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'in:sms,email'],
            'q' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'in:all,leads,clients,contacts'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->broadcasts->searchRecipients(
            $request->user(),
            $validated['channel'],
            (string) ($validated['q'] ?? ''),
            (string) ($validated['source'] ?? 'all'),
            (int) ($validated['page'] ?? 1)
        );

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        @set_time_limit(300);

        $user = $request->user();
        $maxRecipients = BroadcastMessagingService::maxRecipients();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:sms,email'],
            'from_number' => ['nullable', 'string', 'max:32'],
            'shared_inbox_id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:500000'],
            'attachments' => ['nullable', 'array', 'max:'.BroadcastMessagingService::MAX_ATTACHMENTS],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.contentType' => ['nullable', 'string', 'max:120'],
            'attachments.*.contentBytes' => ['required_with:attachments', 'string', 'max:5000000'],
            'attachments.*.isInline' => ['nullable', 'boolean'],
            'attachments.*.contentId' => ['nullable', 'string', 'max:120'],
            'recipients' => ['required', 'array', 'min:1', 'max:'.$maxRecipients],
            'recipients.*.source' => ['nullable', 'string', 'max:32'],
            'recipients.*.source_id' => ['nullable', 'integer'],
            'recipients.*.name' => ['nullable', 'string', 'max:160'],
            'recipients.*.address' => ['required', 'string', 'max:190'],
        ]);

        if ($validated['type'] === BroadcastCampaign::TYPE_SMS) {
            if (! $user->hasPermission('send_broadcast_sms')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to send SMS broadcasts.'], 403);
            }
            if (mb_strlen($validated['body']) > 1600) {
                return response()->json(['success' => false, 'message' => 'SMS messages can be at most 1600 characters.'], 422);
            }
        }

        if ($validated['type'] === BroadcastCampaign::TYPE_EMAIL) {
            if (! $user->hasPermission('send_broadcast_email')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to send email broadcasts.'], 403);
            }
            if (trim((string) ($validated['subject'] ?? '')) === '') {
                return response()->json(['success' => false, 'message' => 'Enter an email subject.'], 422);
            }
        }

        try {
            $campaign = $this->broadcasts->createAndSend($user, $validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Broadcast queued.',
            'data' => $this->serializeCampaign($campaign),
        ], 201);
    }

    public function addRecipients(Request $request, BroadcastCampaign $campaign): JsonResponse
    {
        @set_time_limit(300);

        $user = $request->user();
        $maxRecipients = BroadcastMessagingService::maxRecipients();
        $validated = $request->validate([
            'recipients' => ['required', 'array', 'min:1', 'max:'.$maxRecipients],
            'recipients.*.source' => ['nullable', 'string', 'max:32'],
            'recipients.*.source_id' => ['nullable', 'integer'],
            'recipients.*.name' => ['nullable', 'string', 'max:160'],
            'recipients.*.address' => ['required', 'string', 'max:190'],
        ]);

        try {
            $updated = $this->broadcasts->addRecipients($user, $campaign, $validated['recipients']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Recipients added and sending started.',
            'data' => $this->serializeCampaign($updated),
        ]);
    }

    public function retryFailed(Request $request, BroadcastCampaign $campaign): JsonResponse
    {
        @set_time_limit(120);

        $user = $request->user();
        $validated = $request->validate([
            'recipient_ids' => ['nullable', 'array', 'max:'.BroadcastMessagingService::maxRecipients()],
            'recipient_ids.*' => ['integer'],
        ]);

        try {
            $updated = $this->broadcasts->retryFailed(
                $user,
                $campaign,
                $validated['recipient_ids'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Retry started.',
            'data' => $this->serializeCampaign($updated),
        ]);
    }

    public function smsStatus(Request $request)
    {
        $messageSid = (string) $request->input('MessageSid', '');
        $status = (string) $request->input('MessageStatus', '');

        if ($messageSid !== '' && $status !== '') {
            $this->broadcasts->applyTwilioStatus($messageSid, $status);
        }

        return response('OK', 200);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCampaign(BroadcastCampaign $campaign, bool $includeAttachmentBytes = false): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'type' => $campaign->type,
            'status' => $campaign->status,
            'sender' => $campaign->sender_label,
            'from_number' => $campaign->from_number,
            'shared_inbox_id' => $campaign->shared_inbox_id,
            'subject' => $campaign->subject,
            'body' => $campaign->body,
            'attachments' => $this->serializeAttachments($campaign->attachments ?? [], $includeAttachmentBytes),
            'recipient_count' => (int) $campaign->recipient_count,
            'sent_count' => (int) $campaign->sent_count,
            'delivered_count' => (int) $campaign->delivered_count,
            'failed_count' => (int) $campaign->failed_count,
            'created_by' => $campaign->creator?->name,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'sent_at' => $campaign->sent_at?->toIso8601String(),
            'can_send' => $this->userCanSendCampaign($campaign),
            'retryable_count' => (int) $campaign->recipients()
                ->whereIn('status', [
                    BroadcastCampaignRecipient::STATUS_FAILED,
                    BroadcastCampaignRecipient::STATUS_UNDELIVERED,
                ])
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRecipient(BroadcastCampaignRecipient $recipient): array
    {
        return [
            'id' => $recipient->id,
            'name' => $recipient->name,
            'address' => $recipient->address,
            'source' => $recipient->source,
            'status' => $recipient->status,
            'error_message' => $recipient->error_message,
            'sent_at' => $recipient->sent_at?->toIso8601String(),
            'delivered_at' => $recipient->delivered_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $attachments
     * @return list<array<string, mixed>>
     */
    protected function serializeAttachments(?array $attachments, bool $includeBytes = false): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        return collect($attachments)->map(function (array $attachment) use ($includeBytes) {
            $bytes = (string) ($attachment['contentBytes'] ?? '');
            $item = [
                'name' => (string) ($attachment['name'] ?? 'attachment'),
                'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
                'isInline' => ! empty($attachment['isInline']),
                'contentId' => $attachment['contentId'] ?? null,
                'size' => $bytes !== '' ? (int) (strlen($bytes) * 0.75) : null,
            ];

            if ($includeBytes && $bytes !== '') {
                $item['contentBytes'] = $bytes;
            }

            return $item;
        })->values()->all();
    }

    protected function userCanSendCampaign(BroadcastCampaign $campaign): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $permission = $campaign->isSms() ? 'send_broadcast_sms' : 'send_broadcast_email';

        return $user->hasPermission($permission);
    }
}
