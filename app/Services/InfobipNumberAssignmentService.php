<?php

namespace App\Services;

use App\Models\InfobipPhoneNumber;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class InfobipNumberAssignmentService
{
    public function __construct(
        protected InfobipCompanyService $infobipCompany
    ) {}

    /**
     * @return array<int, array{phone_number: string, friendly_name: ?string, assigned_user_id: ?int, assigned_user_name: ?string}>
     */
    public function optionsForCompany(int $companyId, ?int $forEmployeeId = null): array
    {
        return InfobipPhoneNumber::query()
            ->where('company_id', $companyId)
            ->with('assignedUser:id,name')
            ->orderBy('phone_number')
            ->get()
            ->filter(function (InfobipPhoneNumber $number) use ($forEmployeeId) {
                return $number->assigned_user_id === null || $number->assigned_user_id === $forEmployeeId;
            })
            ->map(fn (InfobipPhoneNumber $number) => [
                'phone_number' => $number->phone_number,
                'friendly_name' => $number->friendly_name,
                'assigned_user_id' => $number->assigned_user_id,
                'assigned_user_name' => $number->assignedUser?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @throws ValidationException
     */
    public function validateAssignable(?string $rawNumber, int $companyId, ?int $forUserId = null): ?string
    {
        if ($rawNumber === null || trim($rawNumber) === '') {
            return null;
        }

        $normalized = $this->infobipCompany->normalizePhone($rawNumber);

        $record = InfobipPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('phone_number', $normalized)
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'phone_system_number' => 'Select a phone number from your company inventory (Phone System → Numbers).',
            ]);
        }

        if ($record->assigned_user_id && $record->assigned_user_id !== $forUserId) {
            throw ValidationException::withMessages([
                'phone_system_number' => 'This phone number is already assigned to another employee.',
            ]);
        }

        return $normalized;
    }

    public function assignToUser(User $user, ?string $rawNumber): void
    {
        $companyId = (int) $user->company_id;
        $normalized = $this->validateAssignable($rawNumber, $companyId, $user->id);

        InfobipPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('assigned_user_id', $user->id)
            ->update(['assigned_user_id' => null]);

        if (! $normalized) {
            $user->update(['phone_system_number' => null]);

            return;
        }

        $record = InfobipPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('phone_number', $normalized)
            ->first();

        if ($record && $record->assigned_user_id && $record->assigned_user_id !== $user->id) {
            User::query()
                ->where('id', $record->assigned_user_id)
                ->where('phone_system_number', $normalized)
                ->update(['phone_system_number' => null]);
        }

        $record?->update(['assigned_user_id' => $user->id]);
        $user->update(['phone_system_number' => $normalized]);
    }

    public function assignInventoryRecord(InfobipPhoneNumber $phoneNumber, User $employee): void
    {
        if ((int) $phoneNumber->company_id !== (int) $employee->company_id) {
            throw ValidationException::withMessages([
                'user_id' => 'Employee not found.',
            ]);
        }

        $alreadyAssigned = InfobipPhoneNumber::query()
            ->where('company_id', $employee->company_id)
            ->where('assigned_user_id', $employee->id)
            ->where('id', '!=', $phoneNumber->id)
            ->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'user_id' => 'Employee already has a phone number assigned. Unassign it first.',
            ]);
        }

        if ($phoneNumber->assigned_user_id && $phoneNumber->assigned_user_id !== $employee->id) {
            User::query()
                ->where('id', $phoneNumber->assigned_user_id)
                ->where('phone_system_number', $phoneNumber->phone_number)
                ->update(['phone_system_number' => null]);
        }

        $phoneNumber->update(['assigned_user_id' => $employee->id]);
        $employee->update(['phone_system_number' => $phoneNumber->phone_number]);
    }

    public function unassignInventoryRecord(InfobipPhoneNumber $phoneNumber): void
    {
        if ($phoneNumber->assigned_user_id) {
            User::query()
                ->where('id', $phoneNumber->assigned_user_id)
                ->where('phone_system_number', $phoneNumber->phone_number)
                ->update(['phone_system_number' => null]);
        }

        $phoneNumber->update(['assigned_user_id' => null]);
    }
}
