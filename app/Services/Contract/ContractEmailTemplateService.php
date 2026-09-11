<?php

namespace App\Services\Contract;

use App\Models\ContractSigner;
use App\Models\SystemSetting;

class ContractEmailTemplateService
{
    public const GROUP_PREFIX = 'contract_signing_email_';

    public const KEY_SUBJECT = 'contract_signing_subject';

    public const KEY_BODY = 'contract_signing_body';

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public static function placeholders(): array
    {
        return [
            ['key' => 'signer_name', 'label' => 'Signer name', 'description' => 'Name of the person signing'],
            ['key' => 'signer_email', 'label' => 'Signer email', 'description' => 'Recipient email address'],
            ['key' => 'contract_title', 'label' => 'Contract title', 'description' => 'Title of the contract'],
            ['key' => 'contract_number', 'label' => 'Contract number', 'description' => 'Contract reference number'],
            ['key' => 'company_name', 'label' => 'Company name', 'description' => 'Your company name'],
            ['key' => 'signing_url', 'label' => 'Signing link', 'description' => 'Link to review and sign the contract'],
            ['key' => 'expiry_date', 'label' => 'Link expires', 'description' => 'Date the signing link expires'],
        ];
    }

    public static function defaultSubject(): string
    {
        return 'Please sign: {{contract_title}}';
    }

    public static function defaultBody(): string
    {
        return <<<'HTML'
<p>Hi {{signer_name}},</p>

<p>{{company_name}} has sent you <strong>{{contract_title}}</strong> ({{contract_number}}) to review and sign.</p>

<p><a href="{{signing_url}}" style="display:inline-block;padding:10px 20px;background:#5f61e6;color:#ffffff;text-decoration:none;border-radius:6px;">Review &amp; Sign</a></p>

<p>This link expires on {{expiry_date}}. If you have any questions, please contact us.</p>

<p>Thank you,<br>{{company_name}}</p>
HTML;
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function getTemplate(int $companyId): array
    {
        $group = self::GROUP_PREFIX.$companyId;

        return [
            'subject' => (string) (SystemSetting::getValue(self::KEY_SUBJECT, self::defaultSubject(), $group) ?? self::defaultSubject()),
            'body' => (string) (SystemSetting::getValue(self::KEY_BODY, self::defaultBody(), $group) ?? self::defaultBody()),
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function saveTemplate(int $companyId, string $subject, string $body): array
    {
        $group = self::GROUP_PREFIX.$companyId;
        $subject = trim($subject);
        $body = trim($body);

        SystemSetting::setValue(self::KEY_SUBJECT, $subject !== '' ? $subject : self::defaultSubject(), 'string', $group);
        SystemSetting::setValue(self::KEY_BODY, $body !== '' ? $body : self::defaultBody(), 'string', $group);

        return $this->getTemplate($companyId);
    }

    public function resetTemplate(int $companyId): array
    {
        $group = self::GROUP_PREFIX.$companyId;

        SystemSetting::setValue(self::KEY_SUBJECT, self::defaultSubject(), 'string', $group);
        SystemSetting::setValue(self::KEY_BODY, self::defaultBody(), 'string', $group);

        return $this->getTemplate($companyId);
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function renderForSigner(int $companyId, ContractSigner $signer, string $signingUrl, ?string $companyName = null): array
    {
        $template = $this->getTemplate($companyId);
        $context = $this->contextFromSigner($signer, $signingUrl, $companyName);

        return [
            'subject' => $this->render($template['subject'], $context),
            'body' => $this->render($template['body'], $context),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function contextFromSigner(ContractSigner $signer, string $signingUrl, ?string $companyName = null): array
    {
        $contract = $signer->contract;

        return [
            'signer_name' => $signer->name,
            'signer_email' => $signer->email,
            'contract_title' => $contract->title,
            'contract_number' => $contract->contract_number,
            'company_name' => (string) ($companyName ?? ''),
            'signing_url' => $signingUrl,
            'expiry_date' => $signer->token_expires_at?->format('M d, Y') ?? '',
        ];
    }

    /**
     * Sample values for the email template preview UI.
     *
     * @return array<string, string>
     */
    public function samplePreviewContext(?string $companyName = null): array
    {
        return [
            'signer_name' => 'Jane Doe',
            'signer_email' => 'jane.doe@example.com',
            'contract_title' => 'Self-Storage Agreement — LNS-2026-001',
            'contract_number' => 'CT-2026-001',
            'company_name' => (string) ($companyName ?? ''),
            'signing_url' => url('/contracts/sign/sample-token'),
            'expiry_date' => now()->addDays(30)->format('M d, Y'),
        ];
    }

    /**
     * @param  array<string, string>  $context
     */
    public function render(string $template, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($template, $replacements);
    }
}
