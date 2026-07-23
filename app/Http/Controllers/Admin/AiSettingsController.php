<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\OpenAIIntegration;
use App\Services\AiSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    public function __construct(protected AiSettingsService $aiSettings) {}

    /**
     * Display the Main AI module page.
     */
    public function index(): View
    {
        $settings = [
            'auto_connect' => $this->aiSettings->isAutoConnectEnabled(),
            'has_api_key' => $this->aiSettings->hasMainApiKey(),
            'default_token_limit' => $this->aiSettings->getDefaultTokenLimit(),
            'main_model' => $this->aiSettings->getMainModel(),
        ];

        $connectedCount = OpenAIIntegration::where('uses_main_ai', true)->count();
        $totalTokensUsed = (int) OpenAIIntegration::where('uses_main_ai', true)->sum('tokens_used');

        $companyUsage = Company::query()
            ->where('status', '!=', 'suspended')
            ->with('openaiIntegration')
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) {
                $integration = $company->openaiIntegration;
                $limit = $integration?->token_limit;
                $used = (int) ($integration?->tokens_used ?? 0);
                $hasOwnKey = (bool) $integration?->api_key;

                if (! $integration) {
                    $source = 'none';
                } elseif ($hasOwnKey) {
                    $source = 'own';
                } else {
                    $source = 'main';
                }

                return [
                    'id' => $company->id,
                    'company' => $company->name,
                    'has_integration' => (bool) $integration,
                    'source' => $source,
                    'tokens_used' => $used,
                    'token_limit' => $limit,
                    'percent' => $limit ? min(100, (int) round($used / $limit * 100)) : null,
                ];
            })
            ->sortByDesc('tokens_used')
            ->values();

        return view('admin.ai-settings', compact('settings', 'connectedCount', 'totalTokensUsed', 'companyUsage'));
    }

    /**
     * Update the platform main AI settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'auto_connect' => ['nullable', 'boolean'],
            'main_api_key' => ['nullable', 'string', 'max:255'],
            'default_token_limit' => ['nullable', 'integer', 'min:0'],
            'main_model' => ['nullable', 'string', 'max:100'],
            'apply_to_all' => ['nullable', 'boolean'],
        ]);

        $this->aiSettings->updateSettings([
            'auto_connect' => (bool) ($validated['auto_connect'] ?? false),
            'main_api_key' => $validated['main_api_key'] ?? null,
            'default_token_limit' => $validated['default_token_limit'] ?? 0,
            'main_model' => $validated['main_model'] ?? AiSettingsService::DEFAULT_MODEL,
        ]);

        $message = 'Main AI settings saved successfully.';

        if (! empty($validated['apply_to_all'])) {
            $count = $this->aiSettings->connectAllCompanies();
            $message .= " Connected {$count} existing companies to the main AI.";
        }

        return redirect()->route('admin.ai-settings')->with('success', $message);
    }

    /**
     * Set the token limit for a single company.
     */
    public function updateCompanyLimit(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'token_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $limit = (int) ($validated['token_limit'] ?? 0);

        OpenAIIntegration::updateOrCreate(
            ['company_id' => $company->id],
            ['token_limit' => $limit > 0 ? $limit : null]
        );

        $label = $limit > 0 ? number_format($limit).' tokens' : 'unlimited';

        return redirect()->route('admin.ai-settings')
            ->with('success', "Token limit for {$company->name} set to {$label}.");
    }
}
