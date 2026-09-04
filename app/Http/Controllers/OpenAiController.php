<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OpenAIIntegration;
use App\Services\AiSettingsService;
use App\Services\OpenAiContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OpenAiController extends Controller
{
    public function __construct(protected AiSettingsService $aiSettings) {}

    /**
     * Send a chat message to OpenAI and return the response.
     */
    public function chat(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = OpenAIIntegration::where('company_id', $company->id)
            ->where('is_active', true)
            ->first();

        // A company's own key (from Integrations) always takes priority over the platform Main AI.
        $hasOwnKey = $integration && $integration->api_key;
        $usesMainAi = ! $hasOwnKey && $this->aiSettings->hasMainApiKey();

        if (! $hasOwnKey && ! $usesMainAi) {
            return response()->json([
                'error' => 'OpenAI integration is not configured. Please add your API key in Integrations.',
            ], 400);
        }

        if ($usesMainAi && $integration && ! $integration->hasTokensRemaining()) {
            return response()->json([
                'error' => 'AI token limit reached for your account. Please contact your administrator to increase the limit.',
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'messages' => ['required', 'array'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant,system'],
            'messages.*.content' => ['required', 'string'],
            'model' => ['nullable', 'string'],
            'context_type' => ['nullable', 'string', 'in:leads,shared-inbox,viber,whatsapp,facebook,sms,broadcast,knowledge-base'],
            'data_source' => ['nullable', 'string', 'in:database,openai'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($usesMainAi) {
            $apiKey = $this->aiSettings->getMainApiKey();

            if (empty($apiKey)) {
                return response()->json([
                    'error' => 'The platform main AI is not configured yet. Please contact your administrator.',
                ], 400);
            }

            // Ensure a tracking record exists so the Main AI token limit applies.
            if (! $integration || ! $integration->uses_main_ai) {
                $integration = $this->aiSettings->connectCompany($company->id);
            }
        } else {
            try {
                $apiKey = Crypt::decryptString($integration->api_key);
            } catch (\Exception $e) {
                Log::error('OpenAI API key decryption failed', ['company_id' => $company->id]);

                return response()->json([
                    'error' => 'OpenAI integration is misconfigured. Please update your API key in Integrations.',
                ], 400);
            }
        }

        $messages = $request->input('messages');
        $model = $request->input('model', $usesMainAi ? $this->aiSettings->getMainModel() : 'gpt-4o');
        $contextType = $request->input('context_type');
        $dataSource = $request->input('data_source', 'database');

        $lastUserMessage = collect($messages)->where('role', 'user')->last();
        $userContent = (string) ($lastUserMessage['content'] ?? '');

        // Infer context from keywords if not explicitly selected (Database mode only)
        if ($dataSource === 'database' && ! $contextType) {
            $contextType = OpenAiContextService::inferContextTypeFromMessage($userContent);
        }

        // Build system message with optional CRM data context
        $systemContent = 'You are an AI assistant for a CRM system focused on leads and omnichannel messaging. Help users manage leads, shared inboxes, Viber, WhatsApp, Facebook/Instagram, SMS conversations, broadcast messaging campaigns, and the knowledge base. Be concise and professional.';
        if ($dataSource === 'database' && $contextType) {
            $contextService = new OpenAiContextService;
            $crmData = $contextService->getContextForCompany($company->id, $contextType, $request->user(), $company, $userContent);
            $systemContent .= "\n\n--- ACTUAL CRM DATA FROM USER'S DATABASE (use this to generate accurate summaries) ---\n\n".$crmData;
        }
        $systemMessage = [
            'role' => 'system',
            'content' => $systemContent,
        ];
        $messages = array_merge([$systemMessage], $messages);

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            $body = $response->json();
            $errorMessage = $body['error']['message'] ?? $response->body();
            Log::warning('OpenAI API error', [
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return response()->json([
                'error' => 'OpenAI API error: '.$errorMessage,
            ], 502);
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        $tokensUsed = (int) ($data['usage']['total_tokens'] ?? 0);
        if ($tokensUsed > 0 && $integration) {
            $integration->increment('tokens_used', $tokensUsed);
        }

        return response()->json(['content' => $content]);
    }

    private function getCompany(Request $request): ?Company
    {
        if (app()->bound('company')) {
            try {
                return app('company');
            } catch (\Exception $e) {
                //
            }
        }

        $companyId = $request->user()?->company_id;

        return $companyId ? Company::find($companyId) : null;
    }
}
