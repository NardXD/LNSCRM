<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Config;

class SmtpSettingsService
{
    private const GROUP = 'email';

    private const KEYS = ['mailer', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'];

    public function getSettings(): array
    {
        $settings = [];
        foreach (self::KEYS as $key) {
            $settings[$key] = SystemSetting::getValue($key, null, self::GROUP);
        }

        return $settings;
    }

    public function saveSettings(array $data): void
    {
        foreach (self::KEYS as $key) {
            if ($key === 'password' && empty($data[$key])) {
                continue; // Keep existing password if blank
            }
            if (array_key_exists($key, $data)) {
                SystemSetting::setValue($key, $data[$key], 'string', self::GROUP);
            }
        }
    }

    public function isConfigured(): bool
    {
        $mailer = SystemSetting::getValue('mailer', null, self::GROUP);
        if (empty($mailer)) {
            return false;
        }
        // Log/sendmail don't need a host
        if (in_array($mailer, ['log', 'sendmail'])) {
            return true;
        }
        // SMTP requires a host
        return !empty(SystemSetting::getValue('host', null, self::GROUP));
    }

    /**
     * Apply DB SMTP settings to Laravel's mail config at runtime.
     */
    public function applyToConfig(): void
    {
        $settings = $this->getSettings();

        if (empty($settings['host'])) {
            return;
        }

        $mailer = $settings['mailer'] ?: 'smtp';

        Config::set('mail.default', $mailer);
        Config::set("mail.mailers.{$mailer}.transport", $mailer);
        Config::set("mail.mailers.{$mailer}.host", $settings['host']);
        Config::set("mail.mailers.{$mailer}.port", (int) ($settings['port'] ?: 587));
        Config::set("mail.mailers.{$mailer}.encryption", $settings['encryption'] ?: 'tls');
        Config::set("mail.mailers.{$mailer}.username", $settings['username']);
        Config::set("mail.mailers.{$mailer}.password", $settings['password']);

        if (!empty($settings['from_address'])) {
            Config::set('mail.from.address', $settings['from_address']);
        }
        if (!empty($settings['from_name'])) {
            Config::set('mail.from.name', $settings['from_name']);
        }
    }
}
