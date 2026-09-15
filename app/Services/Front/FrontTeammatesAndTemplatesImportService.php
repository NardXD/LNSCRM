<?php

namespace App\Services\Front;

use App\Mail\WelcomeEmail;
use App\Models\Company;
use App\Models\FrontIntegration;
use App\Models\InboxTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class FrontTeammatesAndTemplatesImportService
{
    /**
     * @param  array{
     *     dry_run?: bool,
     *     import_teammates?: bool,
     *     import_templates?: bool,
     *     role?: string|int|null,
     *     send_welcome?: bool,
     *     created_by?: int|null,
     * }  $options
     * @return array<string, mixed>
     */
    public function importFromApi(Company $company, FrontApiClient $client, array $options = []): array
    {
        $stats = $this->emptyStats();
        $importTeammates = $options['import_teammates'] ?? true;
        $importTemplates = $options['import_templates'] ?? true;

        $teammates = [];
        if ($importTeammates || $importTemplates) {
            try {
                $teammates = $client->listTeammates();
            } catch (\Throwable $e) {
                if ($importTeammates) {
                    throw new RuntimeException('Could not list Front teammates: '.$e->getMessage(), 0, $e);
                }
                $stats['template_warnings'][] = 'Could not list Front teammates for personal templates: '.$e->getMessage();
            }
        }

        if ($importTeammates) {
            $this->importTeammates($company, $teammates, $options, $stats);
        }

        if ($importTemplates) {
            $templates = $this->collectMessageTemplates($client, $teammates, $stats);
            $this->importTemplates($company, $templates, $options, $stats);
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function importFromFile(Company $company, string $path, array $options = []): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Import file not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload)) {
            throw new RuntimeException('Import file must contain valid JSON.');
        }

        $stats = $this->emptyStats();

        if ($options['import_teammates'] ?? true) {
            $teammates = collect($payload['teammates'] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->all();
            $this->importTeammates($company, $teammates, $options, $stats);
        }

        if ($options['import_templates'] ?? true) {
            $templates = collect($payload['message_templates'] ?? $payload['templates'] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->all();
            $this->importTemplates($company, $templates, $options, $stats);
        }

        return $stats;
    }

    public static function clientForCompany(Company $company, ?string $tokenOverride = null): FrontApiClient
    {
        $token = FrontApiClient::normalizeToken((string) ($tokenOverride ?: ''));
        if ($token === '') {
            $token = FrontApiClient::normalizeToken((string) config('services.front.api_token', ''));
        }
        if ($token === '') {
            $integration = FrontIntegration::query()->where('company_id', $company->id)->first();
            $token = (string) ($integration?->getDecryptedApiToken() ?: '');
        }
        if ($token === '') {
            throw new RuntimeException('Front API token is required. Save one under Integrations → Front.com, set FRONT_API_TOKEN, or pass --token.');
        }

        return new FrontApiClient($token);
    }

    /**
     * @param  list<array<string, mixed>>  $teammates
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $stats
     */
    private function importTeammates(Company $company, array $teammates, array $options, array &$stats): void
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $role = $this->resolveRole($company, $options['role'] ?? null);
        $stats['teammates_scanned'] = count($teammates);

        foreach ($teammates as $teammate) {
            if ((bool) ($teammate['is_blocked'] ?? false)) {
                $stats['teammates_skipped_blocked'] = ((int) ($stats['teammates_skipped_blocked'] ?? 0)) + 1;

                continue;
            }

            $email = strtolower(trim((string) ($teammate['email'] ?? '')));
            if ($email === '' || ! str_contains($email, '@')) {
                $stats['teammates_skipped_no_email'] = ((int) ($stats['teammates_skipped_no_email'] ?? 0)) + 1;

                continue;
            }

            $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existing) {
                if ((int) $existing->company_id === (int) $company->id) {
                    $stats['teammates_existing'] = ((int) ($stats['teammates_existing'] ?? 0)) + 1;
                } else {
                    $stats['teammates_conflict'] = ((int) ($stats['teammates_conflict'] ?? 0)) + 1;
                    $stats['teammate_conflict_samples'] = $this->appendSample(
                        $stats['teammate_conflict_samples'] ?? [],
                        $email.' already belongs to another company'
                    );
                }

                continue;
            }

            $name = $this->teammateName($teammate, $email);
            $password = Str::password(16);

            if ($dryRun) {
                $stats['teammates_created'] = ((int) ($stats['teammates_created'] ?? 0)) + 1;
                $stats['created_teammates'][] = [
                    'name' => $name,
                    'email' => $email,
                    'password' => '(dry run)',
                ];

                continue;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'company_id' => $company->id,
                'role_id' => $role?->id,
                'status' => 'active',
                'is_admin' => false,
            ]);

            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            $stats['teammates_created'] = ((int) ($stats['teammates_created'] ?? 0)) + 1;
            $stats['created_teammates'][] = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ];

            if ($options['send_welcome'] ?? false) {
                try {
                    Mail::to($email)->send(new WelcomeEmail(
                        userName: $name,
                        userEmail: $email,
                        loginUrl: url('/login'),
                        companyName: $company->name,
                        temporaryPassword: $password,
                    ));
                    $stats['welcome_emails_sent'] = ((int) ($stats['welcome_emails_sent'] ?? 0)) + 1;
                } catch (\Throwable $e) {
                    $stats['welcome_email_errors'] = $stats['welcome_email_errors'] ?? [];
                    $stats['welcome_email_errors'][] = $email.': '.$e->getMessage();
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $teammates
     * @param  array<string, mixed>  $stats
     * @return list<array<string, mixed>>
     */
    private function collectMessageTemplates(FrontApiClient $client, array $teammates, array &$stats): array
    {
        $byId = [];

        try {
            foreach ($client->listMessageTemplates() as $template) {
                $id = (string) ($template['id'] ?? '');
                $byId[$id !== '' ? $id : 'shared-'.count($byId)] = $template;
            }
        } catch (\Throwable $e) {
            $stats['template_warnings'][] = 'Could not list shared Front message templates: '.$e->getMessage();
        }

        foreach ($teammates as $teammate) {
            $teammateId = (string) ($teammate['id'] ?? '');
            if ($teammateId === '') {
                continue;
            }

            try {
                foreach ($client->listTeammateMessageTemplates($teammateId) as $template) {
                    $id = (string) ($template['id'] ?? '');
                    $key = $id !== '' ? $id : $teammateId.'-'.count($byId);
                    if (! isset($byId[$key])) {
                        $byId[$key] = $template;
                    }
                }
            } catch (\Throwable $e) {
                $stats['template_warnings'][] = 'Could not list templates for teammate '.$teammateId.': '.$e->getMessage();
            }
        }

        return array_values($byId);
    }

    /**
     * @param  list<array<string, mixed>>  $templates
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $stats
     */
    private function importTemplates(Company $company, array $templates, array $options, array &$stats): void
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $createdBy = $this->resolveCreatedBy($company, $options['created_by'] ?? null);
        $stats['templates_scanned'] = count($templates);

        foreach ($templates as $template) {
            $name = trim((string) ($template['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $frontId = trim((string) ($template['id'] ?? '')) ?: null;
            $subject = trim((string) ($template['subject'] ?? '')) ?: null;
            $body = (string) ($template['body'] ?? $template['body_html'] ?? $template['body_text'] ?? '');
            [$html, $plain] = $this->templateBodies($body);
            if ($plain === '') {
                $stats['templates_skipped_empty'] = ((int) ($stats['templates_skipped_empty'] ?? 0)) + 1;

                continue;
            }

            $existing = $this->existingTemplate($company, $frontId, $name);
            if ($existing) {
                $stats['templates_existing'] = ((int) ($stats['templates_existing'] ?? 0)) + 1;

                continue;
            }

            if ($dryRun) {
                $stats['templates_created'] = ((int) ($stats['templates_created'] ?? 0)) + 1;

                continue;
            }

            InboxTemplate::query()->create([
                'company_id' => $company->id,
                'created_by' => $createdBy,
                'name' => Str::limit($name, 160, ''),
                'subject' => $subject !== null ? Str::limit($subject, 500, '') : null,
                'body_html' => $html,
                'body_text' => Str::limit($plain, 100000, ''),
                'attachments' => null,
                'front_template_id' => $frontId,
            ]);

            $stats['templates_created'] = ((int) ($stats['templates_created'] ?? 0)) + 1;
        }
    }

    private function existingTemplate(Company $company, ?string $frontId, string $name): ?InboxTemplate
    {
        if ($frontId) {
            $byFrontId = InboxTemplate::query()
                ->where('company_id', $company->id)
                ->where('front_template_id', $frontId)
                ->first();
            if ($byFrontId) {
                return $byFrontId;
            }
        }

        return InboxTemplate::query()
            ->where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function templateBodies(string $body): array
    {
        $body = trim($body);
        $plain = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($body !== strip_tags($body)) {
            $html = strip_tags($body, '<p><br><br/><a><strong><em><b><i><ul><ol><li><code><pre><blockquote><span><div><h1><h2><h3><h4><img>');

            return [$html, $plain];
        }

        return [nl2br(e($body), false), $plain !== '' ? $plain : $body];
    }

    /**
     * @param  array<string, mixed>  $teammate
     */
    private function teammateName(array $teammate, string $email): string
    {
        $full = trim(trim((string) ($teammate['first_name'] ?? '')).' '.trim((string) ($teammate['last_name'] ?? '')));
        if ($full !== '') {
            return $full;
        }

        $username = trim((string) ($teammate['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        return Str::before($email, '@');
    }

    private function resolveRole(Company $company, mixed $roleOption): ?Role
    {
        $query = Role::query()->where('company_id', $company->id)->where('is_active', true);

        if (is_numeric($roleOption) && (int) $roleOption > 0) {
            return (clone $query)->whereKey((int) $roleOption)->first();
        }

        $slug = is_string($roleOption) ? trim($roleOption) : '';
        if ($slug !== '') {
            return (clone $query)
                ->where(function ($builder) use ($slug) {
                    $builder->where('slug', $slug)->orWhereRaw('LOWER(name) = ?', [mb_strtolower($slug)]);
                })
                ->first();
        }

        $nonAdmin = (clone $query)
            ->where('slug', '!=', 'admin')
            ->orderBy('id')
            ->first();

        return $nonAdmin ?: $query->orderBy('id')->first();
    }

    private function resolveCreatedBy(Company $company, mixed $userId): ?int
    {
        if (is_numeric($userId) && (int) $userId > 0) {
            $user = User::query()->where('company_id', $company->id)->whereKey((int) $userId)->first();
            if ($user) {
                return (int) $user->id;
            }
        }

        return User::query()->where('company_id', $company->id)->orderBy('id')->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'teammates_scanned' => 0,
            'teammates_created' => 0,
            'teammates_existing' => 0,
            'teammates_conflict' => 0,
            'teammates_skipped_blocked' => 0,
            'teammates_skipped_no_email' => 0,
            'created_teammates' => [],
            'teammate_conflict_samples' => [],
            'welcome_emails_sent' => 0,
            'templates_scanned' => 0,
            'templates_created' => 0,
            'templates_existing' => 0,
            'templates_skipped_empty' => 0,
            'template_warnings' => [],
        ];
    }

    /**
     * @param  list<string>  $samples
     * @return list<string>
     */
    private function appendSample(array $samples, string $label): array
    {
        if (in_array($label, $samples, true) || count($samples) >= 10) {
            return $samples;
        }

        $samples[] = $label;

        return $samples;
    }
}
