<?php

namespace App\Services;

use App\Models\Lead;

class LeadStoreganiseService
{
    public function __construct(
        protected int $companyId,
        protected ?StoreganiseService $storeganise = null,
        protected ?LeadStoreganiseMapper $mapper = null,
    ) {
        $this->storeganise ??= new StoreganiseService($companyId);
        $this->mapper ??= new LeadStoreganiseMapper;
    }

    public function isConfigured(): bool
    {
        return $this->storeganise->isConfigured();
    }

    /**
     * @return list<array{id: string, email: ?string, phone: ?string, name: string, match_types: list<string>, match_values: list<string>}>
     */
    public function findDuplicates(Lead $lead): array
    {
        $lead->loadMissing('identities');

        return $this->storeganise->findDuplicateUsers(
            $lead->emailValues(),
            $lead->phoneValues(),
            $lead->storeganise_user_id,
        );
    }

    /**
     * @return array{action: 'push'|'update', exists: bool, user_id: ?string}
     */
    public function resolveAction(Lead $lead, string $siteId): array
    {
        $siteId = trim($siteId);
        $lead->loadMissing('identities');

        $linkedUserId = trim((string) ($lead->storeganise_user_id ?? ''));
        $linkedSiteId = trim((string) ($lead->storeganise_site_id ?? ''));

        if ($linkedUserId !== '' && ($siteId === '' || $linkedSiteId === '' || $linkedSiteId === $siteId)) {
            return [
                'action' => 'update',
                'exists' => true,
                'user_id' => $linkedUserId,
            ];
        }

        $duplicates = $this->findDuplicates($lead);
        if ($duplicates !== []) {
            $match = collect($duplicates)->first(
                fn (array $dup) => $siteId !== '' && (string) ($dup['site_id'] ?? '') === $siteId
            ) ?? $duplicates[0];

            return [
                'action' => 'update',
                'exists' => true,
                'user_id' => (string) ($match['id'] ?? ''),
            ];
        }

        return [
            'action' => 'push',
            'exists' => false,
            'user_id' => null,
        ];
    }

    /**
     * @return array{success: bool, error?: string, duplicates?: list<array<string, mixed>>, user_id?: string, site_id?: string, linked_existing?: bool}
     */
    public function pushLead(Lead $lead, string $siteId, ?string $linkUserId = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Storeganise is not connected. Set it up under Integrations.'];
        }

        $lead->refresh();
        $lead->loadMissing('identities');

        $siteId = trim($siteId);
        if ($siteId === '') {
            return ['success' => false, 'error' => 'Please select a facility.'];
        }

        $action = $this->resolveAction($lead, $siteId);
        if (($action['action'] ?? 'push') === 'update' && $linkUserId === null) {
            return [
                'success' => false,
                'error' => 'This lead already exists in Storeganise for the selected facility. Use Update instead.',
            ];
        }

        $email = $lead->emailValues()[0] ?? null;
        if (! $email) {
            return ['success' => false, 'error' => 'This lead needs a primary email before it can be pushed to Storeganise.'];
        }

        $site = $this->storeganise->getSite($siteId);
        if ($site === null) {
            return ['success' => false, 'error' => 'The selected facility could not be found in Storeganise.'];
        }

        $resolvedSiteId = (string) ($site['id'] ?? $siteId);

        if ($linkUserId !== null) {
            return $this->syncLinkedUser($lead, $site, $email, $resolvedSiteId, $linkUserId, linkedExisting: true);
        }

        $payload = $this->mapper->toUserPayload($lead, $site, $email, includePassword: true);
        $created = $this->storeganise->createUser($payload);
        if (! ($created['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $created['error'] ?? 'Failed to create Storeganise user.',
            ];
        }

        $userId = (string) ($created['user']['id'] ?? $email);
        $this->markLeadPushed($lead, $resolvedSiteId, $userId);

        return [
            'success' => true,
            'user_id' => $userId,
            'site_id' => $resolvedSiteId,
            'linked_existing' => false,
        ];
    }

    /**
     * @return array{success: bool, error?: string, user_id?: string, site_id?: string, linked_existing?: bool}
     */
    public function updateLead(Lead $lead, string $siteId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Storeganise is not connected. Set it up under Integrations.'];
        }

        $lead->refresh();
        $lead->loadMissing('identities');

        $siteId = trim($siteId);
        if ($siteId === '') {
            return ['success' => false, 'error' => 'Please select a facility.'];
        }

        $email = $lead->emailValues()[0] ?? null;
        if (! $email) {
            return ['success' => false, 'error' => 'This lead needs a primary email before it can be updated in Storeganise.'];
        }

        $site = $this->storeganise->getSite($siteId);
        if ($site === null) {
            return ['success' => false, 'error' => 'The selected facility could not be found in Storeganise.'];
        }

        $resolvedSiteId = (string) ($site['id'] ?? $siteId);
        $action = $this->resolveAction($lead, $resolvedSiteId);
        $userId = trim((string) ($lead->storeganise_user_id ?? ''));
        if ($userId === '' && ! empty($action['user_id'])) {
            $userId = (string) $action['user_id'];
        }
        if ($userId === '') {
            $existing = $this->storeganise->getUser($email);
            if ($existing === null) {
                return ['success' => false, 'error' => 'No Storeganise user found for this lead at the selected facility. Use Push instead.'];
            }
            $userId = (string) ($existing['id'] ?? $email);
        }

        if (($action['action'] ?? 'update') === 'push') {
            return ['success' => false, 'error' => 'This lead is not in Storeganise yet for the selected facility. Use Push instead.'];
        }

        return $this->syncLinkedUser($lead, $site, $email, $resolvedSiteId, $userId, linkedExisting: false);
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array{success: bool, error?: string, user_id?: string, site_id?: string, linked_existing?: bool}
     */
    protected function syncLinkedUser(
        Lead $lead,
        array $site,
        string $email,
        string $siteId,
        string $userId,
        bool $linkedExisting,
    ): array {
        $payload = $this->mapper->toUserPayload($lead, $site, $email, includePassword: false);
        $updated = $this->storeganise->updateUser($userId, $payload);
        if (! ($updated['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $updated['error'] ?? 'Failed to update Storeganise user.',
            ];
        }

        $this->markLeadPushed($lead, $siteId, $userId);

        return [
            'success' => true,
            'user_id' => $userId,
            'site_id' => $siteId,
            'linked_existing' => $linkedExisting,
        ];
    }

    protected function markLeadPushed(Lead $lead, string $siteId, string $userId): void
    {
        $lead->forceFill([
            'storeganise_site_id' => $siteId,
            'storeganise_user_id' => $userId,
            'storeganise_pushed_at' => now(),
        ])->save();
    }
}
