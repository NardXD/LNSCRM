<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    /** @var array<string, int> */
    private const BODY_LIMITS = [
        MessageTemplate::CHANNEL_SMS => 1600,
        MessageTemplate::CHANNEL_FACEBOOK => 2000,
        MessageTemplate::CHANNEL_VIBER => 7000,
        MessageTemplate::CHANNEL_WHATSAPP => 4096,
    ];

    /** @var array<string, string> */
    private const VIEW_PERMISSIONS = [
        MessageTemplate::CHANNEL_SMS => 'view_sms',
        MessageTemplate::CHANNEL_FACEBOOK => 'view_facebook',
        MessageTemplate::CHANNEL_VIBER => 'view_viber',
        MessageTemplate::CHANNEL_WHATSAPP => 'view_whatsapp',
    ];

    public function index(Request $request, string $channel): JsonResponse
    {
        $this->assertChannel($channel);
        $this->assertViewPermission($request, $channel);

        $templates = MessageTemplate::query()
            ->where('company_id', $request->user()->company_id)
            ->where('channel', $channel)
            ->orderBy('name')
            ->get()
            ->map(fn (MessageTemplate $template) => $this->formatTemplate($template));

        return response()->json([
            'templates' => $templates,
            'permissions' => [
                'create_templates' => $request->user()->hasPermission('create_message_templates'),
            ],
        ]);
    }

    public function store(Request $request, string $channel): JsonResponse
    {
        $this->assertChannel($channel);
        if ($denied = $this->denyUnlessCreatePermission($request)) {
            return $denied;
        }

        $maxBody = self::BODY_LIMITS[$channel];
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:'.$maxBody],
            'body_text' => ['nullable', 'string', 'max:'.$maxBody],
        ]);

        $bodyText = trim((string) ($validated['body_text'] ?? $validated['body'] ?? ''));
        if ($bodyText === '') {
            return response()->json(['message' => 'Template message is required.'], 422);
        }

        $template = MessageTemplate::create([
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'channel' => $channel,
            'name' => $validated['name'],
            'body_text' => $bodyText,
        ]);

        return response()->json(['template' => $this->formatTemplate($template)], 201);
    }

    public function update(Request $request, string $channel, MessageTemplate $template): JsonResponse
    {
        $this->assertChannel($channel);
        if ($denied = $this->denyUnlessCreatePermission($request)) {
            return $denied;
        }
        if ($denied = $this->denyUnlessOwned($request, $channel, $template)) {
            return $denied;
        }

        $maxBody = self::BODY_LIMITS[$channel];
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:'.$maxBody],
            'body_text' => ['nullable', 'string', 'max:'.$maxBody],
        ]);

        $bodyText = trim((string) ($validated['body_text'] ?? $validated['body'] ?? ''));
        if ($bodyText === '') {
            return response()->json(['message' => 'Template message is required.'], 422);
        }

        $template->update([
            'name' => $validated['name'],
            'body_text' => $bodyText,
        ]);

        return response()->json(['template' => $this->formatTemplate($template->fresh())]);
    }

    public function destroy(Request $request, string $channel, MessageTemplate $template): JsonResponse
    {
        $this->assertChannel($channel);
        if ($denied = $this->denyUnlessCreatePermission($request)) {
            return $denied;
        }
        if ($denied = $this->denyUnlessOwned($request, $channel, $template)) {
            return $denied;
        }

        $template->delete();

        return response()->json(['deleted' => true]);
    }

    private function assertChannel(string $channel): void
    {
        if (! isset(self::BODY_LIMITS[$channel])) {
            abort(404);
        }
    }

    private function assertViewPermission(Request $request, string $channel): void
    {
        $slug = self::VIEW_PERMISSIONS[$channel] ?? null;
        if (! $slug || ! $request->user()?->hasPermission($slug)) {
            abort(403);
        }
    }

    private function denyUnlessCreatePermission(Request $request): ?JsonResponse
    {
        if ($request->user()?->hasPermission('create_message_templates')) {
            return null;
        }

        return response()->json(['message' => 'You do not have permission to manage templates.'], 403);
    }

    private function denyUnlessOwned(Request $request, string $channel, MessageTemplate $template): ?JsonResponse
    {
        if ($template->company_id !== $request->user()->company_id || $template->channel !== $channel) {
            return response()->json(['message' => 'Template not found.'], 404);
        }

        return null;
    }

    private function formatTemplate(MessageTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'body' => $template->body_text,
            'body_text' => $template->body_text,
            'channel' => $template->channel,
            'created_by' => $template->created_by,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
