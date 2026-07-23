<?php

namespace App\Http\Requests;

use App\Models\LeaveCredit;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeaveRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leave_type' => 'required|in:vacation,sick,personal,emergency,other',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'required_if:leave_type,sick|nullable|file|mimes:pdf,jpeg,jpg,png|max:5120',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leave_type.required' => 'Please select a leave type.',
            'leave_type.in' => 'Invalid leave type selected.',
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
            'attachment.required_if' => 'A file attachment is required for sick leave.',
            'attachment.file' => 'The attachment must be a valid file.',
            'attachment.mimes' => 'The attachment must be a file of type: PDF, JPEG, JPG, or PNG.',
            'attachment.max' => 'The attachment must not exceed 5 MB.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();

            if (! $user || ! $user->company_id) {
                return;
            }

            $leaveType = $this->input('leave_type');
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if (! $leaveType || ! $startDate || ! $endDate) {
                return;
            }

            // Calculate days requested
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $daysRequested = $start->diffInDays($end) + 1;

            // Get available credits
            $availableCredits = $this->getAvailableCredits($user->id, $leaveType, $start->year);

            // Check if user has enough credits
            if ($availableCredits < $daysRequested) {
                $validator->errors()->add(
                    'leave_type',
                    "Insufficient leave credits. You have {$availableCredits} day(s) available, but you are requesting {$daysRequested} day(s)."
                );
            }

            // Check if user has any credits at all
            if ($availableCredits <= 0) {
                $validator->errors()->add(
                    'leave_type',
                    'You do not have any available leave credits for this leave type. Please contact your administrator to add leave credits.'
                );
            }
        });
    }

    /**
     * Get available leave credits for a user.
     * This includes pending requests to prevent over-submission.
     */
    private function getAvailableCredits(int $userId, string $leaveType, int $year): float
    {
        // Get total credits
        $totalCredits = LeaveCredit::where('user_id', $userId)
            ->where('leave_type', $leaveType)
            ->where('year', $year)
            ->sum('credits');

        // Get used credits (approved requests + pending requests to prevent over-submission)
        $usedCredits = LeaveRequest::where('user_id', $userId)
            ->where('leave_type', $leaveType)
            ->whereIn('status', ['approved', 'pending'])
            ->whereYear('start_date', $year)
            ->sum('days_requested');

        return max(0, (float) $totalCredits - (float) $usedCredits);
    }
}
