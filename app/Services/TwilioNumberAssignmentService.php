<?php

namespace App\Services;

use App\Models\TwilioPhoneNumber;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TwilioNumberAssignmentService
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany
    ) {}

    /**
     * @return array<int, array{phone_number: string, friendly_name: ?string, assigned_user_id: ?int, assigned_user_name: ?string}>
     */
    public function optionsForCompany(int $companyId, ?int $forEmployeeId = null): array
    {
        return TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->with('assignedUser:id,name')
            ->orderBy('phone_number')
            ->get()
            ->filter(function (TwilioPhoneNumber $number) use ($forEmployeeId) {
                return $number->assigned_user_id === null || $number->assigned_user_id === $forEmployeeId;
            })
            ->map(fn (TwilioPhoneNumber $number) => [
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

        $normalized = $this->twilioCompany->normalizePhone($rawNumber);

        $record = TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('phone_number', $normalized)
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'twilio_number' => 'Select a Twilio number from your company inventory (Phone System → Numbers).',
            ]);
        }

        if ($record->assigned_user_id && $record->assigned_user_id !== $forUserId) {
            throw ValidationException::withMessages([
                'twilio_number' => 'This Twilio number is already assigned to another employee.',
            ]);
        }

        return $normalized;
    }

    public function assignToUser(User $user, ?string $rawNumber): void
    {
        $companyId = (int) $user->company_id;
        $normalized = $this->validateAssignable($rawNumber, $companyId, $user->id);

        TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('assigned_user_id', $user->id)
            ->update(['assigned_user_id' => null]);

        if (! $normalized) {
            $user->update(['twilio_number' => null]);

            return;
        }

        $record = TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->where('phone_number', $normalized)
            ->first();

        if ($record && $record->assigned_user_id && $record->assigned_user_id !== $user->id) {
            User::query()
                ->where('id', $record->assigned_user_id)
                ->where('twilio_number', $normalized)
                ->update(['twilio_number' => null]);
        }

        $record?->update(['assigned_user_id' => $user->id]);
        $user->update(['twilio_number' => $normalized]);
    }

    public function assignInventoryRecord(TwilioPhoneNumber $twilioPhoneNumber, User $employee): void
    {
        if ((int) $twilioPhoneNumber->company_id !== (int) $employee->company_id) {
            throw ValidationException::withMessages([
                'user_id' => 'Employee not found.',
            ]);
        }

        $alreadyAssigned = TwilioPhoneNumber::query()
            ->where('company_id', $employee->company_id)
            ->where('assigned_user_id', $employee->id)
            ->where('id', '!=', $twilioPhoneNumber->id)
            ->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'user_id' => 'Employee already has a Twilio number assigned. Unassign it first.',
            ]);
        }

        if ($twilioPhoneNumber->assigned_user_id && $twilioPhoneNumber->assigned_user_id !== $employee->id) {
            User::query()
                ->where('id', $twilioPhoneNumber->assigned_user_id)
                ->where('twilio_number', $twilioPhoneNumber->phone_number)
                ->update(['twilio_number' => null]);
        }

        $twilioPhoneNumber->update(['assigned_user_id' => $employee->id]);
        $employee->update(['twilio_number' => $twilioPhoneNumber->phone_number]);
    }

    public function unassignInventoryRecord(TwilioPhoneNumber $twilioPhoneNumber): void
    {
        if ($twilioPhoneNumber->assigned_user_id) {
            User::query()
                ->where('id', $twilioPhoneNumber->assigned_user_id)
                ->where('twilio_number', $twilioPhoneNumber->phone_number)
                ->update(['twilio_number' => null]);
        }

        $twilioPhoneNumber->update(['assigned_user_id' => null]);
    }
}
