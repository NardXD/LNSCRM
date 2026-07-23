<?php

namespace App\Services;

use App\Models\OpenAIIntegration;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class AiSettingsService
{
    public const GROUP = 'ai';

    public const KEY_AUTO_CONNECT = 'ai_auto_connect_new_companies';

    public const KEY_MAIN_API_KEY = 'ai_main_api_key';

    public const KEY_DEFAULT_TOKEN_LIMIT = 'ai_default_token_limit';

    public const KEY_MAIN_MODEL = 'ai_main_model';

    public const DEFAULT_MODEL = 'gpt-4o';

    /**
     * Whether new companies should be auto-connected to the main AI.
     */
    public function isAutoConnectEnabled(): bool
    {
        return (bool) SystemSetting::getValue(self::KEY_AUTO_CONNECT, false, self::GROUP);
    }

    /**
     * Default token limit applied to newly connected companies (0 = unlimited).
     */
    public function getDefaultTokenLimit(): int
    {
        return (int) SystemSetting::getValue(self::KEY_DEFAULT_TOKEN_LIMIT, 0, self::GROUP);
    }

    /**
     * The model used for the platform's main AI.
     */
    public function getMainModel(): string
    {
        $model = SystemSetting::getValue(self::KEY_MAIN_MODEL, self::DEFAULT_MODEL, self::GROUP);

        return $model ?: self::DEFAULT_MODEL;
    }

    /**
     * Whether the platform main AI API key has been configured.
     */
    public function hasMainApiKey(): bool
    {
        return ! empty(SystemSetting::getValue(self::KEY_MAIN_API_KEY, null, self::GROUP));
    }

    /**
     * Get the decrypted main AI API key, or null when unavailable.
     */
    public function getMainApiKey(): ?string
    {
        $encrypted = SystemSetting::getValue(self::KEY_MAIN_API_KEY, null, self::GROUP);

        if (empty($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            Log::error('Main AI API key decryption failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Persist the global main AI settings.
     *
     * @param  array{auto_connect?: bool, default_token_limit?: int, main_model?: string, main_api_key?: ?string}  $data
     */
    public function updateSettings(array $data): void
    {
        if (array_key_exists('auto_connect', $data)) {
            SystemSetting::setValue(
                self::KEY_AUTO_CONNECT,
                $data['auto_connect'] ? '1' : '0',
                'boolean',
                self::GROUP,
                'Automatically connect new companies to the platform main AI.'
            );
        }

        if (array_key_exists('default_token_limit', $data)) {
            SystemSetting::setValue(
                self::KEY_DEFAULT_TOKEN_LIMIT,
                (string) max(0, (int) $data['default_token_limit']),
                'integer',
                self::GROUP,
                'Default token limit for companies connected to the main AI (0 = unlimited).'
            );
        }

        if (array_key_exists('main_model', $data) && ! empty($data['main_model'])) {
            SystemSetting::setValue(
                self::KEY_MAIN_MODEL,
                $data['main_model'],
                'string',
                self::GROUP,
                'Model used for the platform main AI.'
            );
        }

        if (array_key_exists('main_api_key', $data) && ! empty($data['main_api_key'])) {
            SystemSetting::setValue(
                self::KEY_MAIN_API_KEY,
                Crypt::encryptString($data['main_api_key']),
                'string',
                self::GROUP,
                'Encrypted platform main AI API key.'
            );
        }
    }

    /**
     * Connect new companies to the main AI when the global setting is enabled.
     */
    public function autoConnectIfEnabled(int $companyId): void
    {
        if (! $this->isAutoConnectEnabled()) {
            return;
        }

        $this->connectCompany($companyId);
    }

    /**
     * Connect a single company to the platform main AI with the default token limit.
     */
    public function connectCompany(int $companyId): OpenAIIntegration
    {
        $limit = $this->getDefaultTokenLimit();

        return OpenAIIntegration::updateOrCreate(
            ['company_id' => $companyId],
            [
                'uses_main_ai' => true,
                'is_active' => true,
                'token_limit' => $limit > 0 ? $limit : null,
            ]
        );
    }

    /**
     * Connect every existing company to the platform main AI.
     */
    public function connectAllCompanies(): int
    {
        $limit = $this->getDefaultTokenLimit();
        $companyIds = \App\Models\Company::query()->pluck('id');

        foreach ($companyIds as $companyId) {
            OpenAIIntegration::updateOrCreate(
                ['company_id' => $companyId],
                [
                    'uses_main_ai' => true,
                    'is_active' => true,
                    'token_limit' => $limit > 0 ? $limit : null,
                ]
            );
        }

        return $companyIds->count();
    }
}
