<?php

namespace App\Services;

use App\Models\TwilioPhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TwilioNumberAssignmentService
{
    public const PURPOSE_VOICE = 'voice';

    public const PURPOSE_SMS = 'sms';

    public function __construct(
        protected TwilioCompanyService $twilioCompany
    ) {}

    /**
     * @return array<int, array{
     *     phone_number: string,
     *     friendly_name: ?string,
     *     assigned_user_id: ?int,
     *     assigned_user_name: ?string,
     *     sms_assigned_user_id: ?int,
     *     sms_assigned_user_name: ?string,
     *     voice_users: array<int, array{id: int, name: string}>,
     *     sms_users: array<int, array{id: int, name: string}>
     * }>
     */
    public function optionsForCompany(int $companyId, ?int $forEmployeeId = null, ?string $purpose = null): array
    {
        $this->normalizePurpose($purpose, allowNull: true);
        $assignees = $this->assigneesForCompany($companyId);

        $options = TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->orderBy('phone_number')
            ->get()
            ->map(fn (TwilioPhoneNumber $number) => $this->formatOption($number, $assignees))
            ->values();

        if ($forEmployeeId) {
            $employee = User::query()->find($forEmployeeId);
            foreach ([self::PURPOSE_VOICE, self::PURPOSE_SMS] as $employeePurpose) {
                $current = $employee?->{$this->userColumn($employeePurpose)};
                if (! $current) {
                    continue;
                }

                $normalized = $this->twilioCompany->normalizePhone($current);
                if ($options->contains(fn (array $option) => $option['phone_number'] === $normalized)) {
                    continue;
                }

                $options->push($this->formatLooseOption($normalized, $assignees));
            }
        }

        return $options->values()->all();
    }

    /**
     * @return array{
     *     voice: array<string, array<int, array{id: int, name: string}>>,
     *     sms: array<string, array<int, array{id: int, name: string}>>
     * }
     */
    public function assigneesForCompany(int $companyId): array
    {
        $voice = [];
        $sms = [];
        $columns = ['id', 'name', 'twilio_number'];
        $hasSmsNumber = Schema::hasColumn('users', 'twilio_sms_number');
        if ($hasSmsNumber) {
            $columns[] = 'twilio_sms_number';
        }

        User::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get($columns)
            ->each(function (User $user) use (&$voice, &$sms, $hasSmsNumber) {
                if ($user->twilio_number) {
                    $number = $this->twilioCompany->normalizePhone($user->twilio_number);
                    $voice[$number][] = ['id' => (int) $user->id, 'name' => $user->name];
                }
                $smsNumber = $hasSmsNumber ? $user->twilio_sms_number : $user->twilio_number;
                if ($smsNumber) {
                    $number = $this->twilioCompany->normalizePhone($smsNumber);
                    $sms[$number][] = ['id' => (int) $user->id, 'name' => $user->name];
                }
            });

        return ['voice' => $voice, 'sms' => $sms];
    }

    /**
     * @throws ValidationException
     */
    public function validateAssignable(?string $rawNumber, int $companyId, ?int $forUserId = null, string $purpose = self::PURPOSE_VOICE): ?string
    {
        $purpose = $this->normalizePurpose($purpose);
        $field = $this->userColumn($purpose);

        if ($rawNumber === null || trim($rawNumber) === '') {
            return null;
        }

        $normalized = $this->twilioCompany->normalizePhone($rawNumber);
        $currentUser = $forUserId ? User::query()->find($forUserId) : null;
        $alreadyTheirs = $currentUser && $currentUser->{$field} === $normalized;

        $record = TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('phone_number', $normalized)
            ->first();

        if (! $record && ! $alreadyTheirs) {
            throw ValidationException::withMessages([
                $field => 'Select a Twilio number from your company inventory (Phone System → Numbers).',
            ]);
        }

        return $normalized;
    }

    public function assignToUser(User $user, ?string $rawNumber, string $purpose = self::PURPOSE_VOICE): void
    {
        $purpose = $this->normalizePurpose($purpose);
        $companyId = (int) $user->company_id;
        $normalized = $this->validateAssignable($rawNumber, $companyId, $user->id, $purpose);
        $userColumn = $this->userColumn($purpose);
        $previousNumber = $user->{$userColumn};

        $user->update([$userColumn => $normalized]);

        if ($previousNumber) {
            $this->refreshInventoryAssignees($companyId, $previousNumber);
        }
        if ($normalized) {
            $this->refreshInventoryAssignees($companyId, $normalized);
        }
    }

    public function assignInventoryRecord(TwilioPhoneNumber $twilioPhoneNumber, User $employee, string $purpose = self::PURPOSE_VOICE): void
    {
        $purpose = $this->normalizePurpose($purpose);

        if ((int) $twilioPhoneNumber->company_id !== (int) $employee->company_id) {
            throw ValidationException::withMessages([
                'user_id' => 'Employee not found.',
            ]);
        }

        $this->assignToUser($employee, $twilioPhoneNumber->phone_number, $purpose);
    }

    public function unassignInventoryRecord(TwilioPhoneNumber $twilioPhoneNumber, ?string $purpose = null, ?int $userId = null): void
    {
        $purpose = $this->normalizePurpose($purpose, allowNull: true);
        $purposes = $purpose ? [$purpose] : [self::PURPOSE_VOICE, self::PURPOSE_SMS];
        $companyId = (int) $twilioPhoneNumber->company_id;

        foreach ($purposes as $itemPurpose) {
            $query = User::query()
                ->where('company_id', $companyId)
                ->where($this->userColumn($itemPurpose), $twilioPhoneNumber->phone_number);

            if ($userId) {
                $query->where('id', $userId);
            }

            $query->update([$this->userColumn($itemPurpose) => null]);
        }

        $this->refreshInventoryAssignees($companyId, $twilioPhoneNumber->phone_number);
    }

    /**
     * @param  array{
     *     voice?: array<string, array<int, array{id: int, name: string}>>,
     *     sms?: array<string, array<int, array{id: int, name: string}>>
     * }  $assignees
     * @return array{
     *     phone_number: string,
     *     friendly_name: ?string,
     *     assigned_user_id: ?int,
     *     assigned_user_name: ?string,
     *     sms_assigned_user_id: ?int,
     *     sms_assigned_user_name: ?string,
     *     voice_users: array<int, array{id: int, name: string}>,
     *     sms_users: array<int, array{id: int, name: string}>
     * }
     */
    public function formatOption(TwilioPhoneNumber $number, array $assignees = []): array
    {
        return $this->formatLooseOption($number->phone_number, $assignees, $number->friendly_name);
    }

    /**
     * @param  array{
     *     voice?: array<string, array<int, array{id: int, name: string}>>,
     *     sms?: array<string, array<int, array{id: int, name: string}>>
     * }  $assignees
     * @return array{
     *     phone_number: string,
     *     friendly_name: ?string,
     *     assigned_user_id: ?int,
     *     assigned_user_name: ?string,
     *     sms_assigned_user_id: ?int,
     *     sms_assigned_user_name: ?string,
     *     voice_users: array<int, array{id: int, name: string}>,
     *     sms_users: array<int, array{id: int, name: string}>
     * }
     */
    protected function formatLooseOption(string $phoneNumber, array $assignees = [], ?string $friendlyName = null): array
    {
        $voiceUsers = $assignees['voice'][$phoneNumber] ?? [];
        $smsUsers = $assignees['sms'][$phoneNumber] ?? [];

        return [
            'phone_number' => $phoneNumber,
            'friendly_name' => $friendlyName,
            'assigned_user_id' => $voiceUsers[0]['id'] ?? null,
            'assigned_user_name' => $voiceUsers[0]['name'] ?? null,
            'sms_assigned_user_id' => $smsUsers[0]['id'] ?? null,
            'sms_assigned_user_name' => $smsUsers[0]['name'] ?? null,
            'voice_users' => $voiceUsers,
            'sms_users' => $smsUsers,
        ];
    }

    protected function refreshInventoryAssignees(int $companyId, string $rawNumber): void
    {
        $normalized = $this->twilioCompany->normalizePhone($rawNumber);
        $record = TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('phone_number', $normalized)
            ->first();

        if (! $record) {
            return;
        }

        $voiceUser = User::query()
            ->where('company_id', $companyId)
            ->where('twilio_number', $normalized)
            ->orderBy('id')
            ->first();

        $updates = [
            'assigned_user_id' => $voiceUser?->id,
        ];

        if (Schema::hasColumn('users', 'twilio_sms_number') && Schema::hasColumn('twilio_phone_numbers', 'sms_assigned_user_id')) {
            $smsUser = User::query()
                ->where('company_id', $companyId)
                ->where('twilio_sms_number', $normalized)
                ->orderBy('id')
                ->first();
            $updates['sms_assigned_user_id'] = $smsUser?->id;
        }

        $record->update($updates);
    }

    protected function userColumn(string $purpose): string
    {
        return $purpose === self::PURPOSE_SMS ? 'twilio_sms_number' : 'twilio_number';
    }

    protected function normalizePurpose(?string $purpose, bool $allowNull = false): ?string
    {
        if ($purpose === null || $purpose === '') {
            return $allowNull ? null : self::PURPOSE_VOICE;
        }

        $normalized = strtolower($purpose);
        if (! in_array($normalized, [self::PURPOSE_VOICE, self::PURPOSE_SMS], true)) {
            throw ValidationException::withMessages([
                'purpose' => 'Choose whether this number is for the phone system or SMS.',
            ]);
        }

        return $normalized;
    }
}
