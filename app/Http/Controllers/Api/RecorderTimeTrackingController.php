<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecorderTimeClockRequest;
use App\Models\Company;
use App\Models\TimeTracking;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecorderTimeTrackingController extends Controller
{
    /**
     * @return array{date: string, time: string, display: string}|null
     */
    private function clockStamp(?TimeTracking $record, string $clockPart): ?array
    {
        if ($record === null) {
            return null;
        }

        $dateRaw = $record->date;
        $date = $dateRaw instanceof Carbon ? $dateRaw->format('Y-m-d') : (string) $dateRaw;

        $timeRaw = $clockPart === 'in' ? $record->time_in : $record->time_out;
        if ($timeRaw === null) {
            return null;
        }

        $time = is_string($timeRaw)
            ? (strlen($timeRaw) >= 8 ? substr($timeRaw, 0, 8) : $timeRaw)
            : $timeRaw->format('H:i:s');

        return [
            'date' => $date,
            'time' => $time,
            'display' => "{$date} {$time}",
        ];
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->attributes->get('recorder_user');

        $activeRecord = TimeTracking::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->first();

        $lastTimeInRecord = TimeTracking::query()
            ->where('user_id', $user->id)
            ->whereNotNull('time_in')
            ->orderByDesc('created_at')
            ->first();

        $lastTimeOutRecord = TimeTracking::query()
            ->where('user_id', $user->id)
            ->whereNotNull('time_out')
            ->orderByDesc('updated_at')
            ->first();

        return response()->json([
            'success' => true,
            'clocked_in' => (bool) $activeRecord,
            'record' => $activeRecord,
            'last_time_in' => $this->clockStamp($lastTimeInRecord, 'in'),
            'last_time_out' => $this->clockStamp($lastTimeOutRecord, 'out'),
        ]);
    }

    private function recorderCompanyTimezone(Request $request): string
    {
        $companyId = $request->attributes->get('recorder_company_id');
        $company = Company::query()->find($companyId);

        return $company?->timezone ?: (string) config('app.timezone', 'UTC');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizedDateTime(RecorderTimeClockRequest $request, string $tz): array
    {
        $dateStr = $request->string('date')->toString();
        $timeStr = $request->string('time')->toString();
        $point = Carbon::createFromFormat('Y-m-d H:i:s', "{$dateStr} {$timeStr}", $tz);

        return [$point->format('Y-m-d'), $point->format('H:i:s')];
    }

    public function timeIn(RecorderTimeClockRequest $request): JsonResponse
    {
        $user = $request->attributes->get('recorder_user');
        $tz = $this->recorderCompanyTimezone($request);
        [$date, $time] = $this->normalizedDateTime($request, $tz);

        $activeRecord = TimeTracking::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($activeRecord) {
            $timeInStr = is_string($activeRecord->time_in) ? $activeRecord->time_in : $activeRecord->time_in->format('H:i:s');
            $dateStr = $activeRecord->date instanceof Carbon ? $activeRecord->date->format('Y-m-d') : $activeRecord->date;
            $timeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeInStr, $tz);
            $newTimeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$time, $tz);
            $hoursSinceLastClockIn = $timeInDateTime->diffInHours($newTimeInDateTime, false);

            if ($hoursSinceLastClockIn < 24) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have an active time tracking session. Please clock out first before starting a new one.',
                    'record' => $activeRecord,
                ], 400);
            }

            $timeOutDateTime = $timeInDateTime->copy()->addHours(12);
            $hoursWorked = 12 * 3600;
            $activeRecord->update([
                'time_out' => $timeOutDateTime->format('H:i:s'),
                'hours_worked' => $hoursWorked,
                'status' => 'completed',
            ]);
        }

        $record = TimeTracking::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'date' => $date,
            'time_in' => $time,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Time in recorded successfully.',
            'record' => $record,
        ]);
    }

    public function timeOut(RecorderTimeClockRequest $request): JsonResponse
    {
        $user = $request->attributes->get('recorder_user');
        $tz = $this->recorderCompanyTimezone($request);
        [$date, $time] = $this->normalizedDateTime($request, $tz);

        $record = TimeTracking::where('user_id', $user->id)
            ->where('date', $date)
            ->where('status', 'active')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->first();

        if (! $record) {
            $record = TimeTracking::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('time_in')
                ->whereNull('time_out')
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Please clock in first.',
            ], 400);
        }

        if ($record->time_out) {
            return response()->json([
                'success' => false,
                'message' => 'You have already clocked out today.',
                'record' => $record,
            ], 400);
        }

        $timeInStr = is_string($record->time_in) ? $record->time_in : $record->time_in->format('H:i:s');
        $dateStr = $record->date instanceof Carbon ? $record->date->format('Y-m-d') : $record->date;
        $timeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeInStr, $tz);
        $timeOutDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$time, $tz);

        if ($timeOutDateTime->lessThan($timeInDateTime)) {
            $timeOutDateTime->addDay();
        }

        $hoursWorked = max(0, $timeOutDateTime->timestamp - $timeInDateTime->timestamp);

        $record->update([
            'time_out' => $time,
            'hours_worked' => $hoursWorked,
            'status' => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Time out recorded successfully.',
            'record' => $record,
            'hours_worked' => $record->hours_worked_formatted,
        ]);
    }
}
