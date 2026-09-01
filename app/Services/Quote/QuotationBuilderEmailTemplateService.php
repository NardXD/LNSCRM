<?php

namespace App\Services\Quote;

use App\Models\SystemSetting;

class QuotationBuilderEmailTemplateService
{
    public const GROUP_PREFIX = 'quotation_builder_email_';

    public const KEY_SUBJECT = 'storage_quote_subject';

    public const KEY_BODY = 'storage_quote_body';

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public static function placeholders(): array
    {
        return [
            ['key' => 'customer_name', 'label' => 'Customer name', 'description' => 'Lead first and last name'],
            ['key' => 'customer_email', 'label' => 'Customer email', 'description' => 'Recipient email address'],
            ['key' => 'facility', 'label' => 'Facility', 'description' => 'Facility name on the quote'],
            ['key' => 'storage_fee', 'label' => 'Storage fee', 'description' => 'Monthly storage fee (formatted)'],
            ['key' => 'total_due', 'label' => 'Total due', 'description' => 'Total amount payable (formatted)'],
            ['key' => 'start_date', 'label' => 'Start date', 'description' => 'Lease start date'],
            ['key' => 'end_date', 'label' => 'End date', 'description' => 'Lease end date'],
            ['key' => 'company_name', 'label' => 'Company name', 'description' => 'Your company name'],
        ];
    }

    public static function defaultSubject(): string
    {
        return 'Your storage quote from {{facility}}';
    }

    public static function defaultBody(): string
    {
        return <<<'HTML'
<p>Hi {{customer_name}},</p>

<p>Thank you for your interest in {{facility}}. Please find your storage quote attached as a PDF.</p>

<table style="width:60%">
    <tr>
        <td>Total Storage Fee (Monthly)</td>
        <td><strong>PHP {{storage_fee}}</strong></td>
    </tr>
    <tr>
        <td>Total Amount Payable (VAT inclusive)</td>
        <td><strong>PHP {{total_due}}</strong></td>
    </tr>
</table>

<p>This is an estimate only and does not constitute a binding contract. Our sales staff will follow up to confirm the final terms.</p>

<p>Thank you,<br>{{facility}}</p>
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
     * @param  array<string, mixed>  $data  QuoteDocumentData array
     * @return array{subject: string, body: string}
     */
    public function renderForQuote(int $companyId, array $data, ?string $companyName = null): array
    {
        $template = $this->getTemplate($companyId);
        $context = $this->contextFromQuoteData($data, $companyName);

        return [
            'subject' => $this->render($template['subject'], $context),
            'body' => $this->render($template['body'], $context),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function contextFromQuoteData(array $data, ?string $companyName = null): array
    {
        $tenant = $data['tenant'] ?? [];
        $totals = $data['totals'] ?? [];
        $terms = $data['terms'] ?? [];

        $first = trim((string) ($tenant['first_name'] ?? ''));
        $last = trim((string) ($tenant['last_name'] ?? ''));
        $customerName = trim($first.' '.$last);

        return [
            'customer_name' => $customerName !== '' ? $customerName : 'there',
            'customer_email' => (string) ($tenant['email'] ?? ''),
            'facility' => (string) ($data['facility_label'] ?? ''),
            'storage_fee' => number_format((float) ($totals['storage_fee'] ?? 0), 2),
            'total_due' => number_format((float) ($totals['total_due'] ?? 0), 2),
            'start_date' => (string) ($terms['start_date_display'] ?? ''),
            'end_date' => (string) ($terms['end_date_display'] ?? ''),
            'company_name' => (string) ($companyName ?? ''),
        ];
    }

    /**
     * Sample values for the email template preview UI.
     *
     * @return array<string, string>
     */
    public function samplePreviewContext(?string $companyName = null): array
    {
        return $this->contextFromQuoteData([
            'tenant' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane.doe@example.com',
            ],
            'facility_label' => 'Loc&Stor Makati',
            'totals' => [
                'storage_fee' => 5000,
                'total_due' => 6500,
            ],
            'terms' => [
                'start_date_display' => 'Sep 1, 2026',
                'end_date_display' => 'Aug 31, 2027',
            ],
        ], $companyName);
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
