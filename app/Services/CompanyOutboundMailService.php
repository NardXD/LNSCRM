<?php

namespace App\Services;

use App\Models\GmailIntegration;
use App\Models\SharedInbox;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;

class CompanyOutboundMailService
{
    public function __construct(
        protected OutlookMailService $outlookMailService,
    ) {}

    public function quotationMailbox(int $companyId): ?SharedInbox
    {
        return SharedInbox::query()
            ->where('company_id', $companyId)
            ->where('type', SharedInbox::TYPE_QUOTATION)
            ->where('is_active', true)
            ->whereNotNull('outlook_mail_account_id')
            ->with('account')
            ->first();
    }

    public function hasOutboundSender(int $companyId): bool
    {
        $mailbox = $this->quotationMailbox($companyId);
        if ($mailbox?->account) {
            return true;
        }

        return GmailIntegration::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @param  array<int, array{name: string, content: string, contentType?: string}>  $attachments
     */
    public function sendViaOutlook(SharedInbox $inbox, string $to, string $subject, string $htmlBody, array $attachments = []): bool
    {
        $graphAttachments = [];
        foreach ($attachments as $attachment) {
            $name = trim((string) ($attachment['name'] ?? ''));
            $content = (string) ($attachment['content'] ?? '');
            if ($name === '' || $content === '') {
                continue;
            }

            $graphAttachments[] = [
                'name' => $name,
                'contentBytes' => base64_encode($content),
                'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
            ];
        }

        $result = $this->outlookMailService->sendMail($inbox, [
            'to' => $to,
            'subject' => $subject,
            'body' => $htmlBody,
            'attachments' => $graphAttachments,
        ]);

        return $result !== null;
    }

    /**
     * Configure Laravel mail for Gmail fallback.
     *
     * @return array{provider: string, email: string}|null
     */
    public function configureMailer(int $companyId, string $fromName): ?array
    {
        $gmail = GmailIntegration::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (! $gmail) {
            return null;
        }

        $password = Crypt::decryptString($gmail->app_password);

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.encryption', 'tls');
        Config::set('mail.mailers.smtp.username', $gmail->email);
        Config::set('mail.mailers.smtp.password', $password);
        Config::set('mail.from.address', $gmail->email);
        Config::set('mail.from.name', $fromName);

        return [
            'provider' => 'gmail',
            'email' => $gmail->email,
        ];
    }

    public static function configurationHelpMessage(): string
    {
        return 'Outbound email is not configured. Sign in with Microsoft 365 under Quotation Builder → Microsoft 365 Mail, or connect Gmail in Integrations.';
    }
}
