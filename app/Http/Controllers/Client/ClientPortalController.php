<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Project;
use App\Models\ProjectTimeTracking;
use App\Models\ScreenRecording;
use App\Models\TimeTracking;
use App\Models\User;
use App\Services\LiveViewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ClientPortalController extends Controller
{
    public function __construct(protected LiveViewService $liveView) {}

    /**
     * Display the client portal dashboard.
     */
    public function dashboard(): View
    {
        return view('client-portal.dashboard');
    }

    /**
     * Get assigned employees for the client.
     */
    public function getAssignedEmployees(Request $request): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        // Support date range or single date
        $dateStart = $request->get('date_start', $request->get('date', Carbon::today()->format('Y-m-d')));
        $dateEnd = $request->get('date_end', $dateStart);

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        // Get employees assigned to this client
        $employeeRecords = $client->employees()
            ->with('department')
            ->orderBy('name')
            ->get();

        $liveStatusMaps = $this->liveView->employeeLiveStatusMaps($employeeRecords->pluck('id')->all());

        $employees = $employeeRecords
            ->map(function ($employee) use ($dateStart, $dateEnd, $liveStatusMaps) {
                $today = Carbon::today()->format('Y-m-d');

                // Get video count for TODAY specifically (for "Videos Today" stat)
                $videosToday = ScreenRecording::where('user_id', $employee->id)
                    ->whereDate('date', $today)
                    ->where('status', 'completed')
                    ->whereNotNull('screen_recording_path')
                    ->count();

                // Get total videos for the selected date range
                $totalVideos = ScreenRecording::where('user_id', $employee->id)
                    ->whereBetween('date', [$dateStart, $dateEnd])
                    ->where('status', 'completed')
                    ->whereNotNull('screen_recording_path')
                    ->count();

                // Check if user is currently active (in date range)
                $isActive = TimeTracking::where('user_id', $employee->id)
                    ->whereBetween('date', [$dateStart, $dateEnd])
                    ->where('status', 'active')
                    ->exists();

                // Get time tracking for today (or last day of range for time in/out display)
                $todayTracking = TimeTracking::where('user_id', $employee->id)
                    ->whereDate('date', $dateEnd)
                    ->first();

                // Get time tracking records for the date range
                $trackingRecords = TimeTracking::where('user_id', $employee->id)
                    ->whereBetween('date', [$dateStart, $dateEnd])
                    ->orderBy('date', 'desc')
                    ->orderBy('time_in', 'desc')
                    ->get();

                // Calculate total worked hours from database (hours_worked is stored in seconds)
                $totalWorkedSeconds = $trackingRecords->sum('hours_worked');
                $workedHours = $totalWorkedSeconds / 3600; // Convert seconds to hours

                $timeRecords = [];
                foreach ($trackingRecords as $tracking) {
                    // Get hours from database field (stored in seconds, convert to hours)
                    $dailyHours = ($tracking->hours_worked ?? 0) / 3600;

                    $timeRecords[] = [
                        'date' => Carbon::parse($tracking->date)->format('M d, Y'),
                        'day' => Carbon::parse($tracking->date)->format('l'),
                        'time_in' => $tracking->time_in ? Carbon::parse($tracking->time_in)->format('h:i A') : null,
                        'time_out' => $tracking->time_out ? Carbon::parse($tracking->time_out)->format('h:i A') : null,
                        'hours' => round($dailyHours, 2),
                        'status' => $tracking->status,
                    ];
                }

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'department' => $employee->department?->name ?? 'General',
                    'photo' => $employee->photo ? public_media_url($employee->photo) : null,
                    'status' => $isActive ? 'active' : 'inactive',
                    'live_available' => isset($liveStatusMaps['live'][$employee->id]),
                    'videos_today' => $videosToday,
                    'total_videos' => $totalVideos,
                    'worked_hours' => round($workedHours, 2),
                    'time_in' => $todayTracking?->time_in ? Carbon::parse($todayTracking->time_in)->format('h:i A') : null,
                    'time_out' => $todayTracking?->time_out ? Carbon::parse($todayTracking->time_out)->format('h:i A') : null,
                    'time_records' => $timeRecords,
                ];
            });

        return response()->json([
            'success' => true,
            'employees' => $employees,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
            ],
        ]);
    }

    /**
     * Get recordings for a specific assigned employee.
     */
    public function getEmployeeRecordings(Request $request, int $employeeId): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        // Verify employee is assigned to this client
        $employee = $client->employees()->where('users.id', $employeeId)->first();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found or not assigned to your account.',
            ], 404);
        }

        // Support date range or single date
        $dateStart = $request->get('date_start', $request->get('date', Carbon::today()->format('Y-m-d')));
        $dateEnd = $request->get('date_end', $dateStart);
        $dateStart = Carbon::parse($dateStart)->format('Y-m-d');
        $dateEnd = Carbon::parse($dateEnd)->format('Y-m-d');

        // Get recordings for the date range
        $recordings = ScreenRecording::where('user_id', $employeeId)
            ->whereNotNull('screen_recording_path')
            ->where('status', 'completed')
            ->whereBetween('date', [$dateStart, $dateEnd])
            ->orderBy('created_at', 'desc')
            ->get();

        // If no recordings for this date range, get recent ones
        if ($recordings->isEmpty()) {
            $recordings = ScreenRecording::where('user_id', $employeeId)
                ->whereNotNull('screen_recording_path')
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }

        $recordings = $recordings->map(function ($recording) {
            return [
                'id' => $recording->id,
                'type' => 'video',
                'time' => Carbon::parse($recording->created_at)->format('H:i:s'),
                'date' => $recording->date instanceof Carbon
                    ? $recording->date->format('M d, Y')
                    : Carbon::parse($recording->date)->format('M d, Y'),
                'date_full' => Carbon::parse($recording->created_at)->format('M d, Y \a\t g:i A'),
                'duration' => $recording->screen_recording_duration ?? 0,
                'duration_formatted' => $this->formatDuration($recording->screen_recording_duration ?? 0),
                'url' => route('client.portal.view-recording', $recording->id),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'recordings' => $recordings,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
            ],
        ]);
    }

    /**
     * View/stream recording file for assigned employee.
     */
    public function viewRecording(int $id)
    {
        try {
            $clientUser = Auth::guard('client')->user();
            $client = $clientUser->client;

            if (! $client) {
                abort(403, 'Client not found');
            }

            $recording = ScreenRecording::find($id);

            if (! $recording || ! $recording->screen_recording_path) {
                abort(404, 'Recording not found');
            }

            // Verify the recording belongs to an employee assigned to this client
            $employee = $client->employees()->where('users.id', $recording->user_id)->first();

            if (! $employee) {
                abort(403, 'Access denied - Employee not assigned to your account');
            }

            // Check if file exists
            if (! Storage::disk('private')->exists($recording->screen_recording_path)) {
                abort(404, 'Recording file not found');
            }

            // Return the file as a response
            $filePath = Storage::disk('private')->path($recording->screen_recording_path);
            $mimeType = mime_content_type($filePath) ?: 'video/webm';

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
            ]);
        } catch (\Exception $e) {
            Log::error('Error viewing recording in client portal', [
                'error' => $e->getMessage(),
                'recording_id' => $id,
            ]);
            abort(500, 'Error viewing recording');
        }
    }

    /**
     * Get company users for team member selection.
     */
    public function getCompanyUsers(): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $users = $client->employees()
            ->where('users.status', 'active')
            ->orderBy('users.name')
            ->get()
            ->map(function ($u) {
                $nameParts = explode(' ', $u->name);
                $initials = strtoupper(
                    substr($nameParts[0], 0, 1).
                    (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
                );

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'initials' => $initials,
                ];
            });

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    /**
     * Create a new project for the client.
     */
    public function storeProject(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|in:active,on-hold,completed',
            'deadline' => 'required|date',
            'description' => 'nullable|string',
            'team' => 'nullable|array',
            'team.*' => 'integer',
        ]);

        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
        }

        DB::beginTransaction();
        try {
            $project = Project::create([
                'company_id' => $client->company_id,
                'client_id' => $client->id,
                'title' => $request->title,
                'client' => $client->name,
                'status' => $request->status,
                'deadline' => $request->deadline,
                'description' => $request->description,
                'progress' => 0,
            ]);

            if ($request->has('team') && is_array($request->team) && count($request->team) > 0) {
                $teamMemberIds = User::where('company_id', $client->company_id)
                    ->whereIn('id', $request->team)
                    ->pluck('id')
                    ->toArray();
                $project->teamMembers()->attach($teamMemberIds);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully.',
                'project' => ['id' => $project->id, 'title' => $project->title],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating project from client portal', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error creating project. Please try again.'], 500);
        }
    }

    /**
     * Format duration in seconds to readable format.
     */
    private function formatDuration(int $seconds): string
    {
        if (! $seconds) {
            return '0s';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%02d:%02d', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }

    /**
     * Display the projects page.
     */
    public function projects(): View
    {
        return view('client-portal.projects');
    }

    /**
     * Display the billing page.
     */
    public function billing(): View
    {
        return view('client-portal.billing');
    }

    /**
     * Display signed documents for the client.
     */
    public function documents(): View
    {
        return view('client-portal.documents');
    }

    /**
     * Get signed contracts for the client portal.
     */
    public function getContracts(): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $contracts = Contract::where('client_id', $client->id)
            ->where('status', 'signed')
            ->orderByDesc('signed_at')
            ->get()
            ->map(fn (Contract $contract) => [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'title' => $contract->title,
                'signed_at' => $contract->signed_at?->format('M d, Y'),
                'effective_date' => $contract->effective_date?->format('M d, Y'),
                'pdf_url' => route('client.portal.contracts.pdf', $contract->id),
            ]);

        return response()->json([
            'success' => true,
            'contracts' => $contracts,
        ]);
    }

    /**
     * Download a signed contract PDF for the client portal.
     */
    public function downloadContractPdf(int $contractId): Response
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            abort(403, 'Client not found');
        }

        $contract = Contract::where('id', $contractId)
            ->where('client_id', $client->id)
            ->where('status', 'signed')
            ->firstOrFail();

        $contract->load(['client', 'company', 'signers']);

        $pdf = Pdf::loadView('contract.pdf', ['contract' => $contract])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);

        return $pdf->download('contract-'.$contract->contract_number.'.pdf');
    }

    /**
     * Get invoices for the client.
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $query = \App\Models\Invoice::where('client_id', $client->id)
            ->whereIn('status', ['sent', 'paid', 'overdue'])
            ->orderBy('invoice_date', 'desc');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $invoices = $query->with('items')->get();

        $formatted = $invoices->map(function ($inv) {
            return [
                'id'                 => $inv->id,
                'invoice_number'     => $inv->invoice_number,
                'date'               => $inv->invoice_date->format('M d, Y'),
                'due_date'           => $inv->due_date->format('M d, Y'),
                'due_date_raw'       => $inv->due_date->format('Y-m-d'),
                'amount'             => (float) $inv->total,
                'subtotal'           => (float) $inv->subtotal,
                'tax_rate'           => (float) $inv->tax_rate,
                'tax_amount'         => (float) $inv->tax_amount,
                'status'             => $inv->status,
                'notes'              => $inv->notes,
                'stripe_payment_url' => $inv->stripe_payment_url,
                'items'              => $inv->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity'    => (float) $i->quantity,
                    'unit_price'  => (float) $i->unit_price,
                    'total'       => (float) $i->total,
                ])->values(),
            ];
        });

        return response()->json(['success' => true, 'invoices' => $formatted]);
    }

    /**
     * Mark a sent invoice as paid (client-side update).
     */
    public function markInvoicePaid(Request $request, int $invoiceId): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $invoice = \App\Models\Invoice::where('id', $invoiceId)
            ->where('client_id', $client->id)
            ->where('status', 'sent')
            ->first();

        if (! $invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found or cannot be updated.'], 404);
        }

        $invoice->status = 'paid';
        if ($request->filled('notes')) {
            $invoice->notes = $request->notes;
        }
        $invoice->save();

        return response()->json(['success' => true, 'message' => 'Invoice marked as paid.']);
    }

    /**
     * Get billing stats for the client.
     */
    public function getBillingStats(): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $base = \App\Models\Invoice::where('client_id', $client->id)
            ->whereIn('status', ['sent', 'paid', 'overdue']);

        $stats = [
            'total'         => (clone $base)->count(),
            'sent_count'    => (clone $base)->where('status', 'sent')->count(),
            'sent_amount'   => (float) (clone $base)->where('status', 'sent')->sum('total'),
            'paid_count'    => (clone $base)->where('status', 'paid')->count(),
            'paid_amount'   => (float) (clone $base)->where('status', 'paid')->sum('total'),
            'overdue_count' => (clone $base)->where('status', 'overdue')->count(),
            'overdue_amount'=> (float) (clone $base)->where('status', 'overdue')->sum('total'),
        ];

        return response()->json(['success' => true, 'stats' => $stats]);
    }

    /**
     * Get all projects for the client.
     */
    public function getProjects(Request $request): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $status = $request->get('status', 'all');

        $query = Project::where('client_id', $client->id)
            ->with(['tasks', 'teamMembers']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $projects = $query->orderBy('created_at', 'desc')->get();

        $formattedProjects = $projects->map(function ($project) {
            $progress = $project->calculateProgress();
            $totalTasks = $project->tasks->count();
            $completedTasks = $project->tasks->where('status', 'done')->count();

            // Calculate total hours worked on project
            $totalHours = ProjectTimeTracking::where('project_id', $project->id)
                ->sum('hours_worked');
            $totalHoursDecimal = round($totalHours / 3600, 2);

            return [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'status' => $project->status,
                'progress' => $progress,
                'deadline' => $project->deadline ? $project->deadline->format('M d, Y') : null,
                'deadline_raw' => $project->deadline ? $project->deadline->format('Y-m-d') : null,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'total_hours' => $totalHoursDecimal,
                'team_members' => $project->teamMembers->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'photo' => $member->photo ? public_media_url($member->photo) : null,
                    ];
                }),
                'created_at' => $project->created_at->format('M d, Y'),
            ];
        });

        // Calculate stats
        $stats = [
            'total' => $projects->count(),
            'active' => $projects->where('status', 'active')->count(),
            'completed' => $projects->where('status', 'completed')->count(),
            'on_hold' => $projects->where('status', 'on-hold')->count(),
        ];

        return response()->json([
            'success' => true,
            'projects' => $formattedProjects,
            'stats' => $stats,
        ]);
    }

    /**
     * Get a single project with its tasks.
     */
    public function getProject(int $projectId): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $project = Project::where('id', $projectId)
            ->where('client_id', $client->id)
            ->with(['tasks.assignedUser', 'teamMembers'])
            ->first();

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.',
            ], 404);
        }

        $progress = $project->calculateProgress();

        // Format tasks
        $tasks = $project->tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'progress' => $task->progress,
                'deadline' => $task->deadline ? $task->deadline->format('M d, Y') : null,
                'deadline_raw' => $task->deadline ? $task->deadline->format('Y-m-d') : null,
                'assigned_to' => $task->assignedUser ? [
                    'id' => $task->assignedUser->id,
                    'name' => $task->assignedUser->name,
                    'photo' => $task->assignedUser->photo ? public_media_url($task->assignedUser->photo) : null,
                ] : null,
            ];
        });

        // Group tasks by status
        $tasksByStatus = [
            'todo' => $tasks->where('status', 'todo')->values(),
            'in-progress' => $tasks->where('status', 'in-progress')->values(),
            'review' => $tasks->where('status', 'review')->values(),
            'done' => $tasks->where('status', 'done')->values(),
        ];

        return response()->json([
            'success' => true,
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'status' => $project->status,
                'progress' => $progress,
                'deadline' => $project->deadline ? $project->deadline->format('M d, Y') : null,
                'deadline_raw' => $project->deadline ? $project->deadline->format('Y-m-d') : null,
                'total_tasks' => $project->tasks->count(),
                'completed_tasks' => $project->tasks->where('status', 'done')->count(),
                'team_members' => $project->teamMembers->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'photo' => $member->photo ? public_media_url($member->photo) : null,
                    ];
                }),
                'created_at' => $project->created_at->format('M d, Y'),
            ],
            'tasks' => $tasks,
            'tasks_by_status' => $tasksByStatus,
        ]);
    }

    /**
     * Get time tracking records for a project.
     */
    public function getProjectTimeTracking(Request $request, int $projectId): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        // Verify project belongs to client
        $project = Project::where('id', $projectId)
            ->where('client_id', $client->id)
            ->first();

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.',
            ], 404);
        }

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = ProjectTimeTracking::where('project_id', $projectId)
            ->with(['user', 'task'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc');

        if ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        }

        $records = $query->get();

        $formattedRecords = $records->map(function ($record) {
            return [
                'id' => $record->id,
                'date' => $record->date->format('M d, Y'),
                'date_raw' => $record->date->format('Y-m-d'),
                'start_time' => $record->start_time,
                'end_time' => $record->end_time,
                'hours_worked' => $record->hours_worked_decimal,
                'hours_worked_formatted' => $record->hours_worked_formatted,
                'notes' => $record->notes,
                'status' => $record->status,
                'user' => $record->user ? [
                    'id' => $record->user->id,
                    'name' => $record->user->name,
                    'photo' => $record->user->photo ? public_media_url($record->user->photo) : null,
                ] : null,
                'task' => $record->task ? [
                    'id' => $record->task->id,
                    'title' => $record->task->title,
                ] : null,
            ];
        });

        // Calculate totals
        $totalSeconds = $records->sum('hours_worked');
        $totalHours = round($totalSeconds / 3600, 2);

        // Group by user for summary
        $byUser = $records->groupBy('user_id')->map(function ($userRecords, $userId) {
            $user = $userRecords->first()->user;
            $userTotalSeconds = $userRecords->sum('hours_worked');

            return [
                'user_id' => $userId,
                'user_name' => $user?->name ?? 'Unknown',
                'total_hours' => round($userTotalSeconds / 3600, 2),
                'records_count' => $userRecords->count(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'records' => $formattedRecords,
            'summary' => [
                'total_hours' => $totalHours,
                'total_records' => $records->count(),
                'by_user' => $byUser,
            ],
        ]);
    }

    /**
     * Get time tracking summary across all client projects.
     */
    public function getTimeTrackingSummary(Request $request): JsonResponse
    {
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        // Get all project IDs for this client
        $projectIds = Project::where('client_id', $client->id)->pluck('id');

        // Get time tracking records
        $records = ProjectTimeTracking::whereIn('project_id', $projectIds)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->with(['user', 'project', 'task'])
            ->orderBy('date', 'desc')
            ->get();

        // Total hours
        $totalSeconds = $records->sum('hours_worked');
        $totalHours = round($totalSeconds / 3600, 2);

        // Group by project
        $byProject = $records->groupBy('project_id')->map(function ($projectRecords, $projectId) {
            $project = $projectRecords->first()->project;
            $projectTotalSeconds = $projectRecords->sum('hours_worked');

            return [
                'project_id' => $projectId,
                'project_title' => $project?->title ?? 'Unknown',
                'total_hours' => round($projectTotalSeconds / 3600, 2),
                'records_count' => $projectRecords->count(),
            ];
        })->values();

        // Group by user
        $byUser = $records->groupBy('user_id')->map(function ($userRecords, $userId) {
            $user = $userRecords->first()->user;
            $userTotalSeconds = $userRecords->sum('hours_worked');

            return [
                'user_id' => $userId,
                'user_name' => $user?->name ?? 'Unknown',
                'user_photo' => $user?->photo ? public_media_url($user->photo) : null,
                'total_hours' => round($userTotalSeconds / 3600, 2),
                'records_count' => $userRecords->count(),
            ];
        })->values();

        // Daily breakdown
        $byDate = $records->groupBy(function ($record) {
            return $record->date->format('Y-m-d');
        })->map(function ($dateRecords, $date) {
            $dateTotalSeconds = $dateRecords->sum('hours_worked');

            return [
                'date' => Carbon::parse($date)->format('M d, Y'),
                'date_raw' => $date,
                'total_hours' => round($dateTotalSeconds / 3600, 2),
                'records_count' => $dateRecords->count(),
            ];
        })->sortByDesc('date_raw')->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_hours' => $totalHours,
                'total_records' => $records->count(),
                'date_range' => [
                    'start' => Carbon::parse($startDate)->format('M d, Y'),
                    'end' => Carbon::parse($endDate)->format('M d, Y'),
                ],
            ],
            'by_project' => $byProject,
            'by_user' => $byUser,
            'by_date' => $byDate,
        ]);
    }
}
