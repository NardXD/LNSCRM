<?php

namespace App\Services;

use App\Models\TwilioPhoneNumber;
use App\Models\User;
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
     *     sms_assigned_user_name: ?string
     * }>
     */
    public function optionsForCompany(int $companyId, ?int $forEmployeeId = null, ?string $purpose = null): array
    {
        $purpose = $this->normalizePurpose($purpose, allowNull: true);

        return TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->with(['assignedUser:id,name', 'smsAssignedUser:id,name'])
            ->orderBy('phone_number')
            ->get()
            ->filter(function (TwilioPhoneNumber $number) use ($forEmployeeId, $purpose) {
                if ($purpose === null) {
                    return true;
                }

                $assignedUserId = $this->inventoryAssigneeId($number, $purpose);

                return $assignedUserId === null || $assignedUserId === $forEmployeeId;
            })
            ->map(fn (TwilioPhoneNumber $number) => $this->formatOption($number))
            ->values()
            ->all();
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

        $record = TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('phone_number', $normalized)
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                $field => 'Select a Twilio number from your company inventory (Phone System → Numbers).',
            ]);
        }

        $assignedUserId = $this->inventoryAssigneeId($record, $purpose);
        if ($assignedUserId && $assignedUserId !== $forUserId) {
            throw ValidationException::withMessages([
                $field => $purpose === self::PURPOSE_SMS
                    ? 'This Twilio number is already assigned to another employee for SMS.'
                    : 'This Twilio number is already assigned to another employee for the phone system.',
            ]);
        }

        return $normalized;
    }

    public function assignToUser(User $user, ?string $rawNumber, string $purpose = self::PURPOSE_VOICE): void
    {
        $purpose = $this->normalizePurpose($purpose);
        $companyId = (int) $user->company_id;
        $normalized = $this->validateAssignable($rawNumber, $companyId, $user->id, $purpose);
        $inventoryColumn = $this->inventoryColumn($purpose);
        $userColumn = $this->userColumn($purpose);

        TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where($inventoryColumn, $user->id)
            ->update([$inventoryColumn => null]);

        if (! $normalized) {
            $user->update([$userColumn => null]);

            return;
        }

        $record = TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('phone_number', $normalized)
            ->first();

        if ($record && $this->inventoryAssigneeId($record, $purpose) && $this->inventoryAssigneeId($record, $purpose) !== $user->id) {
            User::query()
                ->where('id', $this->inventoryAssigneeId($record, $purpose))
                ->where($userColumn, $normalized)
                ->update([$userColumn => null]);
        }

        $record?->update([$inventoryColumn => $user->id]);
        $user->update([$userColumn => $normalized]);
    }

    public function assignInventoryRecord(TwilioPhoneNumber $twilioPhoneNumber, User $employee, string $purpose = self::PURPOSE_VOICE): void
    {
        $purpose = $this->normalizePurpose($purpose);

        if ((int) $twilioPhoneNumber->company_id !== (int) $employee->company_id) {
            throw ValidationException::withMessages([
                'user_id' => 'Employee not found.',
            ]);
        }

        $this->clearUserAssignment($employee, $purpose, exceptInventoryId: $twilioPhoneNumber->id);
        $this->stealInventoryAssignment($twilioPhoneNumber, $purpose);

        $twilioPhoneNumber->update([
            $this->inventoryColumn($purpose) => $employee->id,
        ]);
        $employee->update([
            $this->userColumn($purpose) => $twilioPhoneNumber->phone_number,
        ]);
    }

    public function unassignInventoryRecord(TwilioPhoneNumber $twilioPhoneNumber, ?string $purpose = null): void
    {
        $purpose = $this->normalizePurpose($purpose, allowNull: true);
        $purposes = $purpose ? [$purpose] : [self::PURPOSE_VOICE, self::PURPOSE_SMS];

        foreach ($purposes as $itemPurpose) {
            $assigneeId = $this->inventoryAssigneeId($twilioPhoneNumber, $itemPurpose);
            if ($assigneeId) {
                User::query()
                    ->where('id', $assigneeId)
                    ->where($this->userColumn($itemPurpose), $twilioPhoneNumber->phone_number)
                    ->update([$this->userColumn($itemPurpose) => null]);
            }

            $twilioPhoneNumber->{$this->inventoryColumn($itemPurpose)} = null;
        }

        $twilioPhoneNumber->save();
    }

    /**
     * @return array{
     *     phone_number: string,
     *     friendly_name: ?string,
     *     assigned_user_id: ?int,
     *     assigned_user_name: ?string,
     *     sms_assigned_user_id: ?int,
     *     sms_assigned_user_name: ?string
     * }
     */
    public function formatOption(TwilioPhoneNumber $number): array
    {
        return [
            'phone_number' => $number->phone_number,
            'friendly_name' => $number->friendly_name,
            'assigned_user_id' => $number->assigned_user_id,
            'assigned_user_name' => $number->assignedUser?->name,
            'sms_assigned_user_id' => $number->sms_assigned_user_id,
            'sms_assigned_user_name' => $number->smsAssignedUser?->name,
        ];
    }

    protected function clearUserAssignment(User $employee, string $purpose, ?int $exceptInventoryId = null): void
    {
        TwilioPhoneNumber::query()
            ->where('company_id', $employee->company_id)
            ->where($this->inventoryColumn($purpose), $employee->id)
            ->when($exceptInventoryId, fn ($query) => $query->where('id', '!=', $exceptInventoryId))
            ->update([$this->inventoryColumn($purpose) => null]);
    }

    protected function stealInventoryAssignment(TwilioPhoneNumber $twilioPhoneNumber, string $purpose): void
    {
        $assigneeId = $this->inventoryAssigneeId($twilioPhoneNumber, $purpose);
        if (! $assigneeId) {
            return;
        }

        User::query()
            ->where('id', $assigneeId)
            ->where($this->userColumn($purpose), $twilioPhoneNumber->phone_number)
            ->update([$this->userColumn($purpose) => null]);
    }

    protected function inventoryAssigneeId(TwilioPhoneNumber $number, string $purpose): ?int
    {
        $id = $number->{$this->inventoryColumn($purpose)};

        return $id !== null ? (int) $id : null;
    }

    protected function userColumn(string $purpose): string
    {
        return $purpose === self::PURPOSE_SMS ? 'twilio_sms_number' : 'twilio_number';
    }

    protected function inventoryColumn(string $purpose): string
    {
        return $purpose === self::PURPOSE_SMS ? 'sms_assigned_user_id' : 'assigned_user_id';
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
