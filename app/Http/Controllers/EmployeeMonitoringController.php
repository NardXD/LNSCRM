<?php

namespace App\Http\Controllers;

use App\Models\ScreenRecording;
use App\Models\TimeTracking;
use App\Models\User;
use App\Services\LiveViewService;
use App\Services\TimezoneService;
use App\Support\ScreenRecordingPlayback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmployeeMonitoringController extends Controller
{
    public function __construct(
        protected LiveViewService $liveView
    ) {}
    /**
     * Display the employee monitoring page.
     */
    public function index()
    {
        return view('dashboard.employee-monitoring');
    }

    /**
     * Get all employees with their monitoring data for the company.
     */
    public function getEmployees(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        // Support date range or single date
        [$dateStart, $dateEnd] = $this->resolveMonitoringDateRange($request);
        $statusOnly = $request->boolean('status_only');

        if (! $companyId) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to a company.',
            ], 400);
        }

        if ($statusOnly) {
            $employeeIds = User::where('company_id', $companyId)
                ->orderBy('name')
                ->pluck('id')
                ->all();

            $liveMaps = $this->liveView->employeeLiveStatusMaps($employeeIds);

            $employees = collect($employeeIds)->map(function ($employeeId) use ($liveMaps) {
                $isClockedIn = isset($liveMaps['clocked_in'][$employeeId]);
                $isRecordingSession = isset($liveMaps['recording'][$employeeId]);
                $liveAvailable = isset($liveMaps['live'][$employeeId]);

                return [
                    'id' => $employeeId,
                    'status' => $isClockedIn ? 'active' : 'inactive',
                    'is_clocked_in' => $isClockedIn,
                    'is_recording_session' => $isRecordingSession,
                    'live_available' => $liveAvailable,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'employees' => $employees,
            ]);
        }

        $employees = User::where('company_id', $companyId)
            ->with('department')
            ->orderBy('name')
            ->get();

        $employeeIds = $employees->pluck('id')->all();
        $liveMaps = $this->liveView->employeeLiveStatusMaps($employeeIds);

        $videoCounts = ScreenRecording::query()
            ->whereIn('user_id', $employeeIds)
            ->whereBetween('date', [$dateStart, $dateEnd])
            ->where('status', 'completed')
            ->whereNotNull('screen_recording_path')
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as video_count')
            ->pluck('video_count', 'user_id');

        $employees = $employees->map(function ($employee) use ($liveMaps, $videoCounts) {
            $isClockedIn = isset($liveMaps['clocked_in'][$employee->id]);
            $isRecordingSession = isset($liveMaps['recording'][$employee->id]);
            $liveAvailable = isset($liveMaps['live'][$employee->id]);
            $videoCount = (int) ($videoCounts[$employee->id] ?? 0);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'department' => $employee->department ? $employee->department->name : 'General',
                'status' => $isClockedIn ? 'active' : 'inactive',
                'is_clocked_in' => $isClockedIn,
                'is_recording_session' => $isRecordingSession,
                'live_available' => $liveAvailable,
                'screenshots_today' => 0,
                'videos_today' => $videoCount,
                'total_screenshots' => 0,
                'total_videos' => $videoCount,
            ];
        });

        return response()->json([
            'success' => true,
            'employees' => $employees,
        ]);
    }

    /**
     * Get recordings (screenshots/videos) for a specific employee.
     */
    public function getEmployeeRecordings(Request $request, $employeeId)
    {
        try {
            $user = Auth::user();
            $companyId = $user->company_id;

            [$dateStart, $dateEnd] = $this->resolveMonitoringDateRange($request);

            // Verify employee belongs to same company
            $employee = User::where('id', $employeeId)
                ->where('company_id', $companyId)
                ->first();

            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.',
                ], 404);
            }

            $recordings = ScreenRecording::query()
                ->where('user_id', $employeeId)
                ->whereNotNull('screen_recording_path')
                ->where('status', 'completed')
                ->whereBetween('date', [$dateStart, $dateEnd])
                ->orderByDesc('created_at')
                ->get([
                    'id',
                    'created_at',
                    'date',
                    'screen_recording_path',
                    'screen_recording_duration',
                ]);

            if ($recordings->isEmpty()) {
                $recordings = ScreenRecording::query()
                    ->where('user_id', $employeeId)
                    ->whereNotNull('screen_recording_path')
                    ->where('status', 'completed')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get([
                        'id',
                        'created_at',
                        'date',
                        'screen_recording_path',
                        'screen_recording_duration',
                    ]);
            }

            $recordings = $recordings->map(function ($recording) {
                return [
                    'id' => $recording->id,
                    'type' => 'video',
                    'time' => TimezoneService::toCompanyTimezone($recording->created_at)->format('H:i:s'),
                    'date' => TimezoneService::toCompanyTimezone($recording->date)->format('M d, Y'),
                    'date_full' => TimezoneService::toCompanyTimezone($recording->created_at)->format('M d, Y \a\t g:i A'),
                    'duration' => (int) ($recording->screen_recording_duration ?? 0),
                    'duration_formatted' => $this->formatDuration($recording->screen_recording_duration ?? 0),
                    'url' => route('api.employee-monitoring.view-recording', $recording->id),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'recordings' => $recordings,
                'screenshots' => [],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error loading employee recordings', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load recordings. Please try again.',
            ], 500);
        }
    }

    /**
     * View/download recording file.
     */
    public function viewRecording($id)
    {
        try {
            $user = Auth::user();
            $companyId = $user->company_id;

            $recording = ScreenRecording::find($id);

            if (! $recording || ! $recording->screen_recording_path) {
                abort(404, 'Recording not found');
            }

            // Verify the recording belongs to a user in the same company
            $employee = User::find($recording->user_id);
            if (! $employee || $employee->company_id !== $companyId) {
                abort(403, 'Access denied');
            }

            // Check if file exists
            if (! Storage::disk('private')->exists($recording->screen_recording_path)) {
                abort(404, 'Recording file not found');
            }

            // Return the file as a response with appropriate headers
            $filePath = Storage::disk('private')->path($recording->screen_recording_path);

            return ScreenRecordingPlayback::fileResponse($filePath, $recording->screen_recording_path);
        } catch (\Exception $e) {
            Log::error('Error viewing employee monitoring recording', [
                'recording_id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Error viewing recording');
        }
    }

    /**
     * Delete a single recording.
     */
    public function deleteRecording($id)
    {
        try {
            $user = Auth::user();
            $companyId = $user->company_id;

            $recording = ScreenRecording::find($id);

            if (! $recording) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recording not found.',
                ], 404);
            }

            // Verify the recording belongs to a user in the same company
            $employee = User::find($recording->user_id);
            if (! $employee || $employee->company_id !== $companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.',
                ], 403);
            }

            // Delete the file from storage if it exists
            if ($recording->screen_recording_path && Storage::disk('private')->exists($recording->screen_recording_path)) {
                Storage::disk('private')->delete($recording->screen_recording_path);
            }

            // Delete the database record
            $recording->delete();

            return response()->json([
                'success' => true,
                'message' => 'Recording deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting recording: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the recording.',
            ], 500);
        }
    }

    /**
     * Delete multiple recordings (bulk delete).
     */
    public function deleteRecordings(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|integer|exists:screen_recordings,id',
            ]);

            $user = Auth::user();
            $companyId = $user->company_id;

            $ids = $request->input('ids');
            $deletedCount = 0;
            $errors = [];

            foreach ($ids as $id) {
                $recording = ScreenRecording::find($id);

                if (! $recording) {
                    $errors[] = "Recording {$id} not found.";

                    continue;
                }

                // Verify the recording belongs to a user in the same company
                $employee = User::find($recording->user_id);
                if (! $employee || $employee->company_id !== $companyId) {
                    $errors[] = "Access denied for recording {$id}.";

                    continue;
                }

                // Delete the file from storage if it exists
                if ($recording->screen_recording_path && Storage::disk('private')->exists($recording->screen_recording_path)) {
                    Storage::disk('private')->delete($recording->screen_recording_path);
                }

                // Delete the database record
                $recording->delete();
                $deletedCount++;
            }

            if ($deletedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No recordings were deleted.',
                    'errors' => $errors,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} recording(s) deleted successfully.",
                'deleted_count' => $deletedCount,
                'errors' => $errors,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error bulk deleting recordings: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the recordings.',
            ], 500);
        }
    }

    /**
     * Get recorder sync health metrics for the current company.
     */
    public function getSyncHealth(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        if (! $companyId) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to a company.',
            ], 400);
        }

        $dateStart = $request->get('date_start', $request->get('date', TimezoneService::today()->format('Y-m-d')));
        $dateEnd = $request->get('date_end', $dateStart);

        $query = ScreenRecording::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$dateStart, $dateEnd]);

        $queued = (clone $query)->where('sync_status', 'queued')->count();
        $uploading = (clone $query)->where('sync_status', 'uploading')->count();
        $uploaded = (clone $query)->where('sync_status', 'uploaded')->count();
        $failed = (clone $query)->where('sync_status', 'failed')->count();

        $latestFailures = (clone $query)
            ->where('sync_status', 'failed')
            ->whereNotNull('last_error')
            ->with('user:id,name,email')
            ->orderByDesc('last_retry_at')
            ->limit(20)
            ->get()
            ->map(function (ScreenRecording $recording): array {
                return [
                    'id' => $recording->id,
                    'upload_id' => $recording->upload_id,
                    'user' => $recording->user ? [
                        'id' => $recording->user->id,
                        'name' => $recording->user->name,
                        'email' => $recording->user->email,
                    ] : null,
                    'device_id' => $recording->device_id,
                    'device_platform' => $recording->device_platform,
                    'retry_count' => $recording->retry_count,
                    'last_error' => $recording->last_error,
                    'last_retry_at' => $recording->last_retry_at,
                ];
            });

        return response()->json([
            'success' => true,
            'summary' => [
                'queued' => $queued,
                'uploading' => $uploading,
                'uploaded' => $uploaded,
                'failed' => $failed,
            ],
            'latest_failures' => $latestFailures,
        ]);
    }

    /**
     * Format duration in seconds to readable format.
     */
    private function formatDuration($seconds)
    {
        $seconds = (int) $seconds;

        if ($seconds <= 0) {
            return '0s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        if ($minutes > 0) {
            return sprintf('%02d:%02d', $minutes, $secs);
        }

        return sprintf('%02ds', $secs);
    }

    /**
     * Resolve the monitoring date range from the request as calendar dates (Y-m-d).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveMonitoringDateRange(Request $request): array
    {
        $dateStart = $request->get('date_start', $request->get('date', TimezoneService::today()->format('Y-m-d')));
        $dateEnd = $request->get('date_end', $dateStart);

        return [
            $this->normalizeMonitoringDate($dateStart),
            $this->normalizeMonitoringDate($dateEnd),
        ];
    }

    /**
     * Treat browser date-picker values as calendar dates without shifting the day.
     */
    private function normalizeMonitoringDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        return TimezoneService::toCompanyTimezone($date)->format('Y-m-d');
    }
}
