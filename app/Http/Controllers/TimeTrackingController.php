<?php

namespace App\Http\Controllers;

use App\Models\ScreenRecording;
use App\Models\TimeTracking;
use App\Models\SystemSetting;
use App\Services\LiveViewService;
use App\Services\TimezoneService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TimeTrackingController extends Controller
{
    protected function broadcastEmployeeMonitoringStatus(): void
    {
        $userId = Auth::id();

        if ($userId) {
            app(LiveViewService::class)->broadcastEmployeeMonitoringStatus((int) $userId);
        }
    }

    /**
     * Display the time tracking page.
     */
    public function index()
    {
        return view('dashboard.time-tracking');
    }

    /**
     * Get company timezone for the authenticated user.
     */
    private function getCompanyTimezone()
    {
        return TimezoneService::getCompanyTimezone();
    }

    /**
     * Start screen recording.
     */
    public function startRecording(Request $request)
    {
        try {
            $request->validate([
                // We no longer rely on the frontend date for recordings.
                // Keep it optional for backward compatibility.
                'date' => 'nullable|date',
            ]);

            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // IMPORTANT:
            // For screen recordings the `date` column should always reflect
            // the recording's created_at day in the COMPANY timezone.
            // Derive the date from \"today\" in company timezone, not from the request.
            $date = TimezoneService::today()->format('Y-m-d');

            // Check if there's an active recording session for today
            $sessionRecord = ScreenRecording::where('user_id', $user->id)
                ->where('date', $date)
                ->where('status', 'recording')
                ->whereJsonContains('metadata->recording_session_active', true)
                ->first();

            if ($sessionRecord) {
                // Session already active, return it but create a new recording record for this clip
                $record = ScreenRecording::create([
                    'user_id' => $user->id,
                    'company_id' => $user->company_id ?? 1,
                    'date' => $date,
                    'status' => 'recording',
                    'metadata' => [
                        'session_id' => $sessionRecord->id,
                    ],
                ]);

                $this->broadcastEmployeeMonitoringStatus();

                return response()->json([
                    'success' => true,
                    'message' => 'Recording started successfully.',
                    'record' => $record,
                    'session_active' => true,
                ]);
            }

            // Create new recording session record (first one of the day)
            $sessionRecord = ScreenRecording::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id ?? 1,
                'date' => $date,
                'status' => 'recording',
                'metadata' => [
                    'recording_session_active' => true,
                    'session_started_at' => TimezoneService::now()->toIso8601String(),
                    'last_recording_at' => null,
                    'next_recording_at' => null,
                ],
            ]);

            // Create individual recording record for this clip
            $record = ScreenRecording::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id ?? 1,
                'date' => $date,
                'status' => 'recording',
                'metadata' => [
                    'session_id' => $sessionRecord->id,
                ],
            ]);

            $this->broadcastEmployeeMonitoringStatus();

            return response()->json([
                'success' => true,
                'message' => 'Recording started successfully.',
                'record' => $record,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stop screen recording.
     */
    public function stopRecording(Request $request)
    {
        $request->validate([
            'record_id' => 'required|exists:screen_recordings,id',
            'duration' => 'nullable|integer|min:0',
            'next_recording_at' => 'nullable|date',
        ]);

        $user = Auth::user();
        $record = ScreenRecording::where('id', $request->record_id)
            ->where('user_id', $user->id)
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.',
            ], 404);
        }

        // Update metadata with next recording time if provided
        $metadata = $record->metadata ?? [];
        if ($request->input('next_recording_at')) {
            $metadata['last_recording_at'] = TimezoneService::now()->toIso8601String();
            // next_recording_at comes as ISO string from frontend, parse and convert to company timezone
            $metadata['next_recording_at'] = TimezoneService::toCompanyTimezone($request->input('next_recording_at'))->toIso8601String();

            // Also update the session record if it exists
            if (isset($metadata['session_id'])) {
                $sessionRecord = ScreenRecording::find($metadata['session_id']);
                if ($sessionRecord && $sessionRecord->status === 'recording') {
                    $sessionMetadata = $sessionRecord->metadata ?? [];
                    $sessionMetadata['last_recording_at'] = TimezoneService::now()->toIso8601String();
                    $sessionMetadata['next_recording_at'] = TimezoneService::toCompanyTimezone($request->input('next_recording_at'))->toIso8601String();
                    $sessionRecord->update(['metadata' => $sessionMetadata]);
                }
            }
        }

        $record->update([
            'screen_recording_duration' => $request->duration ?? 0,
            'status' => 'completed',
            'metadata' => $metadata,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recording stopped successfully.',
            'record' => $record,
        ]);
    }

    /**
     * Upload screen recording file.
     */
    public function uploadRecording(Request $request)
    {
        try {
            $request->validate([
                'record_id' => 'required|exists:screen_recordings,id',
                'recording' => 'required|file|mimes:webm,mp4,ogg|max:102400', // Max 100MB
            ]);

            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $record = ScreenRecording::where('id', $request->record_id)
                ->where('user_id', $user->id)
                ->first();

            if (! $record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found.',
                ], 404);
            }

            // Ensure the directory exists
            $companyId = $user->company_id ?? 1;
            $directory = "recordings/{$companyId}/{$user->id}";

            // Store the recording file
            $path = $request->file('recording')->store(
                $directory,
                'private'
            );

            $fileSize = $request->file('recording')->getSize();

            $record->update([
                'screen_recording_path' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recording uploaded successfully.',
                'record' => $record,
                'file_size' => $fileSize,
                'file_path' => $path,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading recording: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
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

            if (! $user) {
                abort(401, 'Unauthorized');
            }

            $record = ScreenRecording::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (! $record || ! $record->screen_recording_path) {
                abort(404, 'Recording not found');
            }

            // Check if file exists
            if (! Storage::disk('private')->exists($record->screen_recording_path)) {
                abort(404, 'Recording file not found');
            }

            // Return the file as a download/stream
            $filePath = Storage::disk('private')->path($record->screen_recording_path);
            $mimeType = mime_content_type($filePath) ?: 'video/webm';

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
            ]);
        } catch (\Exception $e) {
            Log::error('Error viewing recording: '.$e->getMessage());
            abort(500, 'Error viewing recording');
        }
    }

    /**
     * Time In action.
     */
    public function timeIn(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'time' => 'required|date_format:H:i:s',
            ]);

            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Parse date and time using company timezone
            $date = TimezoneService::toCompanyTimezone($request->date)->format('Y-m-d');
            $time = TimezoneService::toCompanyTimezone($request->time)->format('H:i:s');

            // Check if there's already an active (incomplete) record
            // This check looks for any active record across all dates to handle cross-day scenarios
            $activeRecord = TimeTracking::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('time_in')
                ->whereNull('time_out')
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($activeRecord) {
                // Check if this active record is recent enough to be considered current
                // Allow clocking in if the previous shift ended (even if status wasn't updated)
                $timeInStr = is_string($activeRecord->time_in) ? $activeRecord->time_in : $activeRecord->time_in->format('H:i:s');
                $dateStr = $activeRecord->date instanceof \Carbon\Carbon ? $activeRecord->date->format('Y-m-d') : $activeRecord->date;
                // Use company timezone for datetime calculations
                $companyTimezone = TimezoneService::getCompanyTimezone();
                $timeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeInStr, $companyTimezone);
                $newTimeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$time, $companyTimezone);

                // If the new time_in is more than 24 hours after the previous time_in, allow it
                // This handles cases where user forgot to clock out or system had issues
                $hoursSinceLastClockIn = $timeInDateTime->diffInHours($newTimeInDateTime, false);
                
                if ($hoursSinceLastClockIn < 24) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You have an active time tracking session. Please clock out first before starting a new one.',
                        'record' => $activeRecord,
                    ], 400);
                }
                // If more than 24 hours have passed, auto-complete the previous record
                else {
                    // Auto-complete the previous record (assuming 12 hour max shift)
                    $timeOutDateTime = $timeInDateTime->copy()->addHours(12);
                    $hoursWorked = 12 * 3600; // 12 hours in seconds
                    
                    $activeRecord->update([
                        'time_out' => $timeOutDateTime->format('H:i:s'),
                        'hours_worked' => $hoursWorked,
                        'status' => 'completed',
                    ]);
                }
            }

            // Allow multiple shifts per day - removed the check for completed records on same date

            // Create new record for time in
            $record = TimeTracking::create([
                'user_id' => $user->id,
                'company_id' => $user->company_id ?? 1,
                'date' => $date,
                'time_in' => $time,
                'status' => 'active',
            ]);

            $this->broadcastEmployeeMonitoringStatus();

            return response()->json([
                'success' => true,
                'message' => 'Time in recorded successfully.',
                'record' => $record,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Time Out action.
     */
    public function timeOut(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'time' => 'required|date_format:H:i:s',
            ]);

            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Parse date and time using company timezone
            $date = TimezoneService::toCompanyTimezone($request->date)->format('Y-m-d');
            $time = TimezoneService::toCompanyTimezone($request->time)->format('H:i:s');

            // Find the active record with time_in but no time_out
            // First try to find record on current date, if not found, find the most recent active record
            // (this handles cross-day scenarios where user clocks in one day and out the next)
            $record = TimeTracking::where('user_id', $user->id)
                ->where('date', $date)
                ->where('status', 'active')
                ->whereNotNull('time_in')
                ->whereNull('time_out')
                ->first();

            // If no record found on current date, find most recent active record (handles cross-day clock out)
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

            // Calculate hours worked - TIME OUT - TIME IN = hours:minutes:seconds
            // time_in is stored as TIME format (H:i:s), so we need to combine it with the date
            $timeInStr = is_string($record->time_in) ? $record->time_in : $record->time_in->format('H:i:s');
            $dateStr = $record->date instanceof \Carbon\Carbon ? $record->date->format('Y-m-d') : $record->date;

            // Create datetime objects for both time_in and time_out (using company timezone)
            $companyTimezone = TimezoneService::getCompanyTimezone();
            $timeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeInStr, $companyTimezone);
            $timeOutDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$time, $companyTimezone);

            // Handle edge case where time_out might be on next day (e.g., clock in 23:00, clock out 01:00)
            if ($timeOutDateTime->lessThan($timeInDateTime)) {
                // If time_out is before time_in, assume it's the next day
                $timeOutDateTime->addDay();
            }

            // Calculate difference in seconds: TIME OUT - TIME IN
            // Use timestamps to ensure correct calculation order
            $hoursWorked = max(0, $timeOutDateTime->timestamp - $timeInDateTime->timestamp);

            $record->update([
                'time_out' => $time,
                'hours_worked' => $hoursWorked,
                'status' => 'completed',
            ]);

            app(LiveViewService::class)->clearHeartbeat((int) $user->id);
            $this->broadcastEmployeeMonitoringStatus();

            return response()->json([
                'success' => true,
                'message' => 'Time out recorded successfully.',
                'record' => $record,
                'hours_worked' => $record->hours_worked_formatted,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get time tracking records for the user.
     */
    public function getRecords(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $query = TimeTracking::where('user_id', $user->id)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $records = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($record) use ($user) {
                // Check if this date has recordings and get the first one for viewing
                $recording = ScreenRecording::where('user_id', $user->id)
                    ->where('date', $record->date->format('Y-m-d'))
                    ->where('status', 'completed')
                    ->whereNotNull('screen_recording_path')
                    ->orderBy('created_at', 'desc')
                    ->first();

                // Calculate hours worked: TIME OUT - TIME IN = hours:minutes:seconds
                $hours = '--';
                if ($record->time_in && $record->time_out) {
                    // Use the formatted attribute if hours_worked exists and is valid (positive)
                    if ($record->hours_worked && $record->hours_worked > 0) {
                        $hours = $record->hours_worked_formatted;
                    } else {
                        // Recalculate if hours_worked is missing
                        $timeInStr = is_string($record->time_in) ? $record->time_in : Carbon::parse($record->time_in)->format('H:i:s');
                        $timeOutStr = is_string($record->time_out) ? $record->time_out : Carbon::parse($record->time_out)->format('H:i:s');
                        $dateStr = $record->date instanceof \Carbon\Carbon ? $record->date->format('Y-m-d') : $record->date;

                        // Use company timezone for datetime calculations
                        $companyTimezone = TimezoneService::getCompanyTimezone();
                        $timeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeInStr, $companyTimezone);
                        $timeOutDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeOutStr, $companyTimezone);

                        // Handle edge case where time_out might be on next day
                        if ($timeOutDateTime->lessThan($timeInDateTime)) {
                            $timeOutDateTime->addDay();
                        }

                        // Calculate: TIME OUT - TIME IN (in seconds) using timestamps
                        $hoursWorkedSeconds = max(0, $timeOutDateTime->timestamp - $timeInDateTime->timestamp);

                        // Format as HH:MM:SS
                        $hours = sprintf('%02d:%02d:%02d',
                            floor($hoursWorkedSeconds / 3600),
                            floor(($hoursWorkedSeconds % 3600) / 60),
                            $hoursWorkedSeconds % 60
                        );
                    }
                }

                return [
                    'id' => $record->id,
                    'date' => $record->date->format('m-d-Y'),
                    'time_in' => $record->time_in ? (is_string($record->time_in) ? $record->time_in : Carbon::parse($record->time_in)->format('H:i:s')) : '--',
                    'time_out' => $record->time_out ? (is_string($record->time_out) ? $record->time_out : Carbon::parse($record->time_out)->format('H:i:s')) : '--',
                    'hours' => $hours,
                    'has_recording' => ! empty($recording),
                    'recording_id' => $recording ? $recording->id : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $records,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Get current active record for today.
     */
    public function getActiveRecord(Request $request)
    {
        $user = Auth::user();
        $date = $request->get('date', TimezoneService::today()->format('Y-m-d'));

        // Check for active recording session (in ScreenRecording table)
        $sessionRecord = ScreenRecording::where('user_id', $user->id)
            ->where('date', $date)
            ->where('status', 'recording')
            ->whereJsonContains('metadata->recording_session_active', true)
            ->first();

        // Check for time in/out record (in TimeTracking table)
        // First try current date, then check for most recent active record across all dates
        // This handles cross-day scenarios (e.g., clock in at 23:49 on day 1, check at 00:08 on day 2)
        $timeRecord = TimeTracking::where('user_id', $user->id)
            ->where('date', $date)
            ->where('status', 'active')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->first();

        // If no record found on current date, find most recent active record (handles cross-day clock in)
        if (! $timeRecord) {
            $timeRecord = TimeTracking::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('time_in')
                ->whereNull('time_out')
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // Check if we need to auto-timeout (12 hours limit)
        $autoTimeout = false;
        $shouldAutoTimeout = false;
        if ($timeRecord) {
            $timeInStr = is_string($timeRecord->time_in) ? $timeRecord->time_in : $timeRecord->time_in->format('H:i:s');
            $dateStr = $timeRecord->date instanceof \Carbon\Carbon ? $timeRecord->date->format('Y-m-d') : $timeRecord->date;

            // Create datetime object for time_in (using company timezone)
            $companyTimezone = TimezoneService::getCompanyTimezone();
            $timeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeInStr, $companyTimezone);
            $now = TimezoneService::now();

            // Calculate hours elapsed
            $hoursElapsed = $timeInDateTime->diffInHours($now, false);

            // If more than 12 hours have passed, auto-timeout
            if ($hoursElapsed >= 12) {
                $shouldAutoTimeout = true;
                // Auto-timeout the record
                $timeOutDateTime = $timeInDateTime->copy()->addHours(12);
                $timeOut = $timeOutDateTime->format('H:i:s');

                // Calculate hours worked (12 hours = 43200 seconds)
                $hoursWorked = 12 * 3600;

                $timeRecord->update([
                    'time_out' => $timeOut,
                    'hours_worked' => $hoursWorked,
                    'status' => 'completed',
                ]);

                $autoTimeout = true;
                $timeRecord = null; // Set to null since it's now completed
            }
        }

        $sessionActive = false;
        $nextRecordingAt = null;

        if ($sessionRecord) {
            $metadata = $sessionRecord->metadata ?? [];
            $sessionActive = $metadata['recording_session_active'] ?? false;
            $nextRecordingAt = $metadata['next_recording_at'] ?? null;
        }

        return response()->json([
            'success' => true,
            'record' => $timeRecord ? [
                'id' => $timeRecord->id,
                'date' => $timeRecord->date->format('m-d-Y'),
                'time_in' => $timeRecord->time_in ? (is_string($timeRecord->time_in) ? $timeRecord->time_in : Carbon::parse($timeRecord->time_in)->format('H:i:s')) : null,
                'time_out' => $timeRecord->time_out ? (is_string($timeRecord->time_out) ? $timeRecord->time_out : Carbon::parse($timeRecord->time_out)->format('H:i:s')) : null,
            ] : null,
            'session_active' => $sessionActive,
            'next_recording_at' => $nextRecordingAt,
            'auto_timeout' => $autoTimeout,
            'auto_timeout_message' => $autoTimeout ? 'Your session was automatically timed out after 12 hours.' : null,
        ]);
    }

    /**
     * Get all recordings for today.
     */
    public function getTodayRecordings(Request $request)
    {
        $user = Auth::user();
        $date = $request->get('date', TimezoneService::today()->format('Y-m-d'));

        $recordings = ScreenRecording::where('user_id', $user->id)
            ->where('date', $date)
            ->whereNotNull('screen_recording_path')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                $createdAt = TimezoneService::toCompanyTimezone($record->created_at);
                return [
                    'id' => $record->id,
                    'date' => $createdAt->format('m-d-Y'),
                    'time' => $createdAt->format('H:i:s'),
                    'duration' => $record->screen_recording_duration ?? 0,
                    'duration_formatted' => $this->formatDuration($record->screen_recording_duration ?? 0),
                    'has_recording' => ! empty($record->screen_recording_path),
                ];
            });

        return response()->json([
            'success' => true,
            'recordings' => $recordings,
        ]);
    }

    /**
     * Format duration in seconds to readable format.
     */
    private function formatDuration($seconds)
    {
        if (! $seconds) {
            return '0s';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }

    /**
     * Stop recording session.
     */
    public function stopRecordingSession(Request $request)
    {
        try {
            $user = Auth::user();
            $date = $request->get('date', TimezoneService::today()->format('Y-m-d'));

            // Find active session in ScreenRecording table
            $sessionRecord = ScreenRecording::where('user_id', $user->id)
                ->where('date', $date)
                ->where('status', 'recording')
                ->whereJsonContains('metadata->recording_session_active', true)
                ->first();

            if (! $sessionRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active recording session found.',
                ], 404);
            }

            $metadata = $sessionRecord->metadata ?? [];
            $metadata['recording_session_active'] = false;
            $metadata['session_stopped_at'] = TimezoneService::now()->toIso8601String();

            $sessionRecord->update([
                'status' => 'completed',
                'metadata' => $metadata,
            ]);

            app(LiveViewService::class)->clearHeartbeat((int) $user->id);
            $this->broadcastEmployeeMonitoringStatus();

            return response()->json([
                'success' => true,
                'message' => 'Recording session stopped successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }
}
