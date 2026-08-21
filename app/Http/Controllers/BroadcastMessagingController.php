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
                'sms_senders' => $this->broadcasts->smsSenders($user),
                'email_senders' => $this->broadcasts->emailSenders($user),
                'integrations_url' => route('integrations'),
                'inbox_url' => route('inbox'),
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

    public function show(BroadcastCampaign $campaign): JsonResponse
    {
        $campaign->load(['creator:id,name', 'recipients']);

        return response()->json([
            'success' => true,
            'data' => $this->serializeCampaign($campaign, true),
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
        @set_time_limit(120);

        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:sms,email'],
            'from_number' => ['nullable', 'string', 'max:32'],
            'shared_inbox_id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'recipients' => ['required', 'array', 'min:1', 'max:'.BroadcastMessagingService::MAX_RECIPIENTS],
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
            'data' => $this->serializeCampaign($campaign, true),
        ], 201);
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
    protected function serializeCampaign(BroadcastCampaign $campaign, bool $withRecipients = false): array
    {
        $data = [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'type' => $campaign->type,
            'status' => $campaign->status,
            'sender' => $campaign->sender_label,
            'from_number' => $campaign->from_number,
            'shared_inbox_id' => $campaign->shared_inbox_id,
            'subject' => $campaign->subject,
            'body' => $campaign->body,
            'recipient_count' => (int) $campaign->recipient_count,
            'sent_count' => (int) $campaign->sent_count,
            'delivered_count' => (int) $campaign->delivered_count,
            'failed_count' => (int) $campaign->failed_count,
            'created_by' => $campaign->creator?->name,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'sent_at' => $campaign->sent_at?->toIso8601String(),
        ];

        if ($withRecipients) {
            $recipients = $campaign->relationLoaded('recipients')
                ? $campaign->recipients
                : $campaign->recipients()->orderBy('id')->get();

            $data['recipients'] = $recipients->map(fn (BroadcastCampaignRecipient $recipient) => [
                'id' => $recipient->id,
                'name' => $recipient->name,
                'address' => $recipient->address,
                'source' => $recipient->source,
                'status' => $recipient->status,
                'error_message' => $recipient->error_message,
                'sent_at' => $recipient->sent_at?->toIso8601String(),
                'delivered_at' => $recipient->delivered_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }
}
