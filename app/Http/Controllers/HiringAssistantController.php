<?php

namespace App\Http\Controllers;

use App\Models\OpenAIIntegration;
use App\Services\AiSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HiringAssistantController extends Controller
{
    public function __construct(protected AiSettingsService $aiSettings) {}

    private const SYSTEM_PROMPT_TEMPLATE = <<<'PROMPT'
You are a hiring assistant that helps users create detailed job descriptions. Your goal is to gather the necessary information through a friendly conversation, then output a formatted job description.

QUESTION SET - ADAPT TO THE POSITION:
First, identify the job/position the user needs (e.g., PHP Developer, Customer Support Rep, Data Analyst, Project Manager, Virtual Assistant). Then tailor your questions to that specific role.

For a DEVELOPER/TECH role: ask about tech stack, frameworks (Laravel, React, etc.), databases, APIs, version control, remote setup.
For a CUSTOMER SUPPORT role: ask about channels (email, chat, phone), ticketing systems, languages, communication expectations.
For a DATA/ANALYTICS role: ask about tools (SQL, Excel, BI tools), reporting needs, data sources.
For a VA/ADMIN role: ask about tasks, calendar management, communication tools, time zones.
For a PROJECT MANAGER role: ask about methodologies, team size, tools (Jira, Asana), stakeholder management.
Always ask (for any role): job title, client company name, client email address (REQUIRED), location, experience level, schedule, timezone, start date, primary responsibilities, required qualifications, preferred qualifications, compensation.

RULES:
1. Ask ONE question at a time. Keep responses concise (2-4 sentences).
2. The SET of questions must be based on the specific position—different roles get different tailored questions.
3. If no job mentioned yet, ask what position they're hiring for first.
4. Be warm and conversational.
5. Do NOT make up information. Only use what the user provides.
6. When you have gathered enough information, output ONLY the job description in EXACTLY this format. Do NOT add any intro or transitional text before it (e.g. no "Thanks for providing...", "Here's the job description we've crafted:", etc.). Start directly with "Job Description: [Job Title]". Do NOT finalize the job description unless the client email is provided.

SUMMARY - WRITE IT YOURSELF:
The Summary must be written by you (AI). Synthesize the collected information into a compelling 2-4 sentence professional paragraph that sells the role. Do not quote the user. Write an engaging, polished summary that would attract qualified candidates. Highlight the opportunity, key expectations, and what makes the role appealing.

Job Description: [Job Title]
Client Company: [Client Company Name]
Client Email: [Client Email Address - REQUIRED]
Company: {{COMPANY_NAME}} Location: [Location] Experience Level: [Level] Schedule: [e.g. Full-Time (8 Hours Daily, PH Time)]

Summary
[Write a compelling 2-4 sentence professional summary—AI-crafted, not user quotes]

Key Responsibilities
- [Duty 1]
- [Duty 2]
- [Add more as needed]

Required Qualifications
- [Requirement 1]
- [Requirement 2]
- [Add more as needed]

Preferred Qualifications
- [Preferred 1]
- [Preferred 2]
- [Or "None" if not specified]

Schedule & Timezone
Hours: [X hours per week (Y hours per day)].
Timezone: [e.g. Philippines Standard Time (PHT)].

Compensation
Rate: $[X.XX] USD per hour.

7. Output {{COMPANY_NAME}} as-is—it is replaced with the actual company name.
8. Use bullet points with "- " for lists. Use **bold** for emphasis where helpful.
9. Do NOT add a closing line like "Let me know if you need changes" or "Would you like me to revise anything?"—end with the job description content only.
PROMPT;

    /**
     * Handle chat messages for the hiring assistant.
     * Public endpoint - requires company from subdomain.
     */
    public function chat(Request $request): JsonResponse
    {
        $company = $request->get('company') ?? (app()->bound('company') ? app('company') : null);

        if (! $company) {
            return response()->json(['error' => 'Please access this page through your company link.'], 404);
        }

        if ($company->status === 'suspended') {
            return response()->json(['error' => 'This company account has been suspended.'], 403);
        }

        $integration = OpenAIIntegration::where('company_id', $company->id)
            ->where('is_active', true)
            ->first();

        $validator = Validator::make($request->all(), [
            'messages' => ['required', 'array'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant,system'],
            'messages.*.content' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Prefer the company's own OpenAI key; fall back to the platform Main AI when not configured.
        $apiKey = null;
        $model = 'gpt-4o';
        $usesMainAi = false;

        if ($integration && $integration->api_key) {
            try {
                $apiKey = Crypt::decryptString($integration->api_key);
            } catch (\Exception $e) {
                Log::error('OpenAI API key decryption failed', ['company_id' => $company->id]);
                $apiKey = null;
            }
        }

        if (! $apiKey) {
            $apiKey = $this->aiSettings->getMainApiKey();

            if (! $apiKey) {
                return response()->json([
                    'error' => 'AI is not configured for your organization. Please ask your admin to set up OpenAI in Integrations.',
                ], 400);
            }

            $usesMainAi = true;
            $model = $this->aiSettings->getMainModel();

            // Ensure a tracking record exists so the Main AI token limit applies.
            if (! $integration || ! $integration->uses_main_ai) {
                $integration = $this->aiSettings->connectCompany($company->id);
            }
        }

        if ($usesMainAi && $integration && ! $integration->hasTokensRemaining()) {
            return response()->json([
                'error' => 'AI token limit reached for your organization. Please contact your admin to increase the limit.',
            ], 429);
        }

        $messages = $request->input('messages');
        $systemContent = str_replace('{{COMPANY_NAME}}', $company->name, self::SYSTEM_PROMPT_TEMPLATE);
        $systemMessage = ['role' => 'system', 'content' => $systemContent];
        $messages = array_merge([$systemMessage], $messages);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                ]);
        } catch (\Exception $e) {
            Log::error('Hiring Assistant HTTP error', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Could not reach AI service. Please try again.'], 502);
        }

        if (! $response->successful()) {
            $body = $response->json();
            $errorMessage = $body['error']['message'] ?? $response->body();
            Log::warning('Hiring Assistant OpenAI error', [
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return response()->json([
                'error' => 'Sorry, I encountered an error. Please try again. ('.substr($errorMessage, 0, 80).')',
            ], 502);
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        if ($usesMainAi && $integration) {
            $tokensUsed = (int) ($data['usage']['total_tokens'] ?? 0);
            if ($tokensUsed > 0) {
                $integration->increment('tokens_used', $tokensUsed);
            }
        }

        return response()->json(['content' => $content]);
    }
}
