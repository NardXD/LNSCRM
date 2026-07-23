<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveCreditRequest;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\UpdateLeaveRequestRequest;
use App\Models\LeaveCredit;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LeaveManagementController extends Controller
{
    /**
     * Display the leave management page.
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        $user = Auth::user();

        $permissions = [
            'view_stats' => $user->isAdmin() || $user->isTeamLeaderOrCoLeader() || $user->hasPermission('view_leave_stats'),
            'create_request' => $user->hasPermission('create_leave_request'),
            'view_credits' => $user->isAdmin() || $user->isTeamLeaderOrCoLeader() || $user->hasPermission('view_leave_credits'),
            'manage_credits' => $user->isAdmin() || $user->isTeamLeaderOrCoLeader() || $user->hasPermission('manage_leave_credits'),
            'view_calendar' => $user->isAdmin() || $user->isTeamLeaderOrCoLeader() || $user->hasPermission('view_leave_calendar'),
        ];

        return view('dashboard.leave-management', compact('permissions'));
    }

    /**
     * Get all leave requests for the authenticated user's company.
     */
    public function getLeaveRequests(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        $query = LeaveRequest::where('company_id', $user->company_id)
            ->with(['user', 'approver']);

        // Filter by status (default to pending)
        $status = $request->input('status', 'pending');
        $query->where('status', $status);

        // Filter by user (for team leaders viewing their team's requests)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        // If user is a team leader, show requests from their team members
        if (! $user->isAdmin() && $user->isTeamLeaderOrCoLeader()) {
            $teamMemberIds = $user->managedTeams()
                ->with('members')
                ->get()
                ->flatMap(function ($team) {
                    $memberIds = $team->members->pluck('id')->toArray();
                    if ($team->leader_id) {
                        $memberIds[] = $team->leader_id;
                    }

                    return $memberIds;
                })
                ->unique()
                ->toArray();

            // Also include the user's own requests
            $teamMemberIds[] = $user->id;

            $query->whereIn('user_id', $teamMemberIds);
        } elseif (! $user->isAdmin()) {
            // Regular users can only see their own requests
            $query->where('user_id', $user->id);
        }

        $perPage = min((int) $request->input('per_page', 10), 50);
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $leaveRequests = $paginated->map(function ($request) use ($user) {
            return [
                'id' => $request->id,
                'user_id' => $request->user_id,
                'user_name' => $request->user->name,
                'user_email' => $request->user->email,
                'leave_type' => $request->leave_type,
                'leave_type_label' => ucfirst($request->leave_type),
                'start_date' => $request->start_date->format('Y-m-d'),
                'start_date_formatted' => $request->start_date->format('M d, Y'),
                'end_date' => $request->end_date->format('Y-m-d'),
                'end_date_formatted' => $request->end_date->format('M d, Y'),
                'days_requested' => $request->days_requested,
                'reason' => $request->reason,
                'attachment_path' => $request->attachment_path,
                'attachment_filename' => $request->attachment_path ? basename($request->attachment_path) : null,
                'status' => $request->status,
                'status_label' => ucfirst($request->status),
                'rejection_reason' => $request->rejection_reason,
                'approved_by' => $request->approved_by,
                'approver_name' => $request->approver ? $request->approver->name : null,
                'approved_at' => $request->approved_at ? $request->approved_at->format('M d, Y h:i A') : null,
                'created_at' => $request->created_at->format('M d, Y'),
                'can_approve' => $this->canApprove($request),
                'can_cancel' => $request->user_id === $user->id && $request->status === 'pending',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $leaveRequests,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
            ],
        ]);
    }

    /**
     * Store a new leave request.
     */
    public function storeLeaveRequest(StoreLeaveRequestRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        // Check permission to create leave request
        if (! $user->hasPermission('create_leave_request')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create leave requests.',
            ], 403);
        }

        $validated = $request->validated();

        // Calculate days requested
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $daysRequested = $startDate->diffInDays($endDate) + 1;

        // Check if user has enough leave credits
        $availableCredits = $this->getAvailableCredits($user->id, $validated['leave_type']);
        if ($availableCredits < $daysRequested) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient leave credits. Available: {$availableCredits} days, Requested: {$daysRequested} days.",
            ], 422);
        }

        // Handle sick leave attachment upload
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                "leave-attachments/{$user->company_id}",
                'private'
            );
        }

        $leaveRequest = LeaveRequest::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days_requested' => $daysRequested,
            'reason' => $validated['reason'] ?? null,
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
        ]);

        $leaveRequest->load(['user', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully.',
            'data' => $leaveRequest,
        ], 201);
    }

    /**
     * Update leave request status (approve/reject).
     */
    public function updateLeaveRequest(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || $leaveRequest->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found or access denied.',
            ], 404);
        }

        // Check if user can approve this request
        if (! $this->canApprove($leaveRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to approve/reject this leave request.',
            ], 403);
        }

        $validated = $request->validated();

        // If approving, check if user has enough credits
        if ($validated['status'] === 'approved') {
            $availableCredits = $this->getAvailableCredits($leaveRequest->user_id, $leaveRequest->leave_type);
            if ($availableCredits < $leaveRequest->days_requested) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot approve: User has insufficient leave credits. Available: {$availableCredits} days, Requested: {$leaveRequest->days_requested} days.",
                ], 422);
            }
        }

        $leaveRequest->update([
            'status' => $validated['status'],
            'approved_by' => $validated['status'] !== 'pending' ? $user->id : null,
            'approved_at' => $validated['status'] !== 'pending' ? now() : null,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        $leaveRequest->load(['user', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Leave request updated successfully.',
            'data' => $leaveRequest,
        ]);
    }

    /**
     * Cancel a leave request.
     */
    public function cancelLeaveRequest(LeaveRequest $leaveRequest): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || $leaveRequest->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found or access denied.',
            ], 404);
        }

        // Only the requester can cancel their own request
        if ($leaveRequest->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only cancel your own leave requests.',
            ], 403);
        }

        // Cannot cancel if already approved or rejected
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a leave request that has already been approved or rejected.',
            ], 422);
        }

        $leaveRequest->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request cancelled successfully.',
        ]);
    }

    /**
     * View or download leave request attachment.
     * Serves inline for display (images, PDF) or as download.
     */
    public function viewAttachment(Request $request, LeaveRequest $leaveRequest)
    {
        $user = Auth::user();

        if (! $user || $leaveRequest->company_id !== $user->company_id) {
            abort(404, 'Leave request not found or access denied.');
        }

        if (! $leaveRequest->attachment_path) {
            abort(404, 'No attachment found for this leave request.');
        }

        if (! Storage::disk('private')->exists($leaveRequest->attachment_path)) {
            abort(404, 'Attachment file not found.');
        }

        $filePath = Storage::disk('private')->path($leaveRequest->attachment_path);
        $filename = basename($leaveRequest->attachment_path);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $inlineExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $inline = in_array($extension, $inlineExtensions);

        return response()->file($filePath, [
            'Content-Type' => match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'pdf' => 'application/pdf',
                default => 'application/octet-stream',
            },
            'Content-Disposition' => $inline ? 'inline' : 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Get all leave credits for the authenticated user's company.
     */
    public function getLeaveCredits(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        // Check permission to view leave credits
        if (! $user->isAdmin() && ! $user->isTeamLeaderOrCoLeader() && ! $user->hasPermission('view_leave_credits')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view leave credits.',
            ], 403);
        }

        $query = LeaveCredit::where('company_id', $user->company_id)
            ->with('user');

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by year
        if ($request->has('year')) {
            $query->where('year', $request->year);
        } else {
            $query->where('year', date('Y'));
        }

        // Filter by leave type
        if ($request->has('leave_type') && $request->leave_type !== 'all') {
            $query->where('leave_type', $request->leave_type);
        }

        $leaveCredits = $query->orderBy('user_id')->orderBy('leave_type')->get()->map(function ($credit) {
            return [
                'id' => $credit->id,
                'user_id' => $credit->user_id,
                'user_name' => $credit->user->name,
                'user_email' => $credit->user->email,
                'leave_type' => $credit->leave_type,
                'leave_type_label' => ucfirst($credit->leave_type),
                'credits' => (float) $credit->credits,
                'year' => $credit->year,
                'notes' => $credit->notes,
                'available_credits' => $this->getAvailableCredits($credit->user_id, $credit->leave_type, $credit->year),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $leaveCredits,
        ]);
    }

    /**
     * Store or update leave credits for a user.
     */
    public function storeLeaveCredit(StoreLeaveCreditRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        // Check if user has permission to manage leave credits (admin or team leader or has permission)
        if (! $user->isAdmin() && ! $user->isTeamLeaderOrCoLeader() && ! $user->hasPermission('manage_leave_credits')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage leave credits.',
            ], 403);
        }

        $validated = $request->validated();

        // Verify the target user belongs to the same company
        $targetUser = User::where('id', $validated['user_id'])
            ->where('company_id', $user->company_id)
            ->first();

        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Selected user does not belong to your company.',
            ], 422);
        }

        // Use updateOrCreate to handle both create and update
        $leaveCredit = LeaveCredit::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'leave_type' => $validated['leave_type'],
                'year' => $validated['year'],
                'company_id' => $user->company_id,
            ],
            [
                'credits' => $validated['credits'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $leaveCredit->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Leave credits updated successfully.',
            'data' => $leaveCredit,
        ]);
    }

    /**
     * Get leave credits information for the authenticated user.
     */
    public function getMyLeaveCredits(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        $year = $request->input('year', date('Y'));
        $leaveTypes = ['vacation', 'sick', 'personal', 'emergency', 'other'];

        $creditsInfo = [];
        foreach ($leaveTypes as $leaveType) {
            // Get total credits
            $totalCredits = LeaveCredit::where('user_id', $user->id)
                ->where('leave_type', $leaveType)
                ->where('year', $year)
                ->sum('credits');

            // Get used credits (approved + pending requests)
            $usedCredits = LeaveRequest::where('user_id', $user->id)
                ->where('leave_type', $leaveType)
                ->whereIn('status', ['approved', 'pending'])
                ->whereYear('start_date', $year)
                ->sum('days_requested');

            $availableCredits = max(0, (float) $totalCredits - (float) $usedCredits);

            $creditsInfo[$leaveType] = [
                'total' => (float) $totalCredits,
                'used' => (float) $usedCredits,
                'available' => $availableCredits,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $creditsInfo,
        ]);
    }

    /**
     * Get employees on leave for a specific date.
     */
    public function getEmployeesOnLeave(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        // Check permission to view calendar
        if (! $user->isAdmin() && ! $user->isTeamLeaderOrCoLeader() && ! $user->hasPermission('view_leave_calendar')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view employees on leave.',
            ], 403);
        }

        $date = $request->input('date');
        if (! $date) {
            return response()->json([
                'success' => false,
                'message' => 'Date parameter is required.',
            ], 400);
        }

        $query = LeaveRequest::where('company_id', $user->company_id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->with('user');

        // If user is a team leader, show requests from their team members
        if (! $user->isAdmin() && $user->isTeamLeaderOrCoLeader()) {
            $teamMemberIds = $user->managedTeams()
                ->with('members')
                ->get()
                ->flatMap(function ($team) {
                    $memberIds = $team->members->pluck('id')->toArray();
                    if ($team->leader_id) {
                        $memberIds[] = $team->leader_id;
                    }

                    return $memberIds;
                })
                ->unique()
                ->toArray();

            // Also include the user's own requests
            $teamMemberIds[] = $user->id;

            $query->whereIn('user_id', $teamMemberIds);
        } elseif (! $user->isAdmin()) {
            // Regular users can only see their own requests
            $query->where('user_id', $user->id);
        }

        $employees = $query->get()->map(function ($request) {
            return [
                'id' => $request->user_id,
                'name' => $request->user->name,
                'email' => $request->user->email,
                'leave_type' => $request->leave_type,
                'leave_type_label' => ucfirst($request->leave_type),
                'start_date' => $request->start_date->format('Y-m-d'),
                'end_date' => $request->end_date->format('Y-m-d'),
                'days_requested' => $request->days_requested,
                'reason' => $request->reason,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $employees,
            'count' => $employees->count(),
        ]);
    }

    /**
     * Get leave calendar data for a specific month.
     */
    public function getLeaveCalendar(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        // Check permission to view calendar
        if (! $user->isAdmin() && ! $user->isTeamLeaderOrCoLeader() && ! $user->hasPermission('view_leave_calendar')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view the leave calendar.',
            ], 403);
        }

        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get all approved leave requests that overlap with the month
        // A request overlaps if: start_date <= end_of_month AND end_date >= start_of_month
        $query = LeaveRequest::where('company_id', $user->company_id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endDate->format('Y-m-d'))
            ->where('end_date', '>=', $startDate->format('Y-m-d'))
            ->with('user');

        // If user is a team leader, show requests from their team members
        if (! $user->isAdmin() && $user->isTeamLeaderOrCoLeader()) {
            $teamMemberIds = $user->managedTeams()
                ->with('members')
                ->get()
                ->flatMap(function ($team) {
                    $memberIds = $team->members->pluck('id')->toArray();
                    if ($team->leader_id) {
                        $memberIds[] = $team->leader_id;
                    }

                    return $memberIds;
                })
                ->unique()
                ->toArray();

            $teamMemberIds[] = $user->id;
            $query->whereIn('user_id', $teamMemberIds);
        } elseif (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $leaveRequests = $query->get();

        // Build calendar data: count employees on leave per day
        $calendarData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $count = 0;

            foreach ($leaveRequests as $request) {
                if ($currentDate->gte($request->start_date) && $currentDate->lte($request->end_date)) {
                    $count++;
                }
            }

            $calendarData[$dateStr] = $count;
            $currentDate->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $calendarData,
            'year' => $year,
            'month' => $month,
        ]);
    }

    /**
     * Get available users for leave credit management.
     */
    public function getAvailableUsers(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        $query = User::where('company_id', $user->company_id)
            ->where('status', 'active');

        // If user is a team leader, only show their team members
        if (! $user->isAdmin() && $user->isTeamLeaderOrCoLeader()) {
            $teamMemberIds = $user->managedTeams()
                ->with('members')
                ->get()
                ->flatMap(function ($team) {
                    $memberIds = $team->members->pluck('id')->toArray();
                    if ($team->leader_id) {
                        $memberIds[] = $team->leader_id;
                    }

                    return $memberIds;
                })
                ->unique()
                ->toArray();

            $query->whereIn('id', $teamMemberIds);
        }

        $users = $query->orderBy('name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get leave statistics for dashboard.
     */
    public function getLeaveStats(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        // Check permission to view stats
        if (! $user->isAdmin() && ! $user->isTeamLeaderOrCoLeader() && ! $user->hasPermission('view_leave_stats')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view leave statistics.',
            ], 403);
        }

        $year = $request->input('year', date('Y'));

        // Get user IDs to filter (own requests or team members if team leader)
        $userIds = [$user->id];
        if ($user->isTeamLeaderOrCoLeader() || $user->isAdmin()) {
            if ($user->isAdmin()) {
                $userIds = User::where('company_id', $user->company_id)->pluck('id')->toArray();
            } else {
                $teamMemberIds = $user->managedTeams()
                    ->with('members')
                    ->get()
                    ->flatMap(function ($team) {
                        $memberIds = $team->members->pluck('id')->toArray();
                        if ($team->leader_id) {
                            $memberIds[] = $team->leader_id;
                        }

                        return $memberIds;
                    })
                    ->unique()
                    ->toArray();
                $userIds = array_unique(array_merge($userIds, $teamMemberIds));
            }
        }

        $stats = [
            'pending_requests' => LeaveRequest::where('company_id', $user->company_id)
                ->whereIn('user_id', $userIds)
                ->where('status', 'pending')
                ->count(),
            'approved_requests' => LeaveRequest::where('company_id', $user->company_id)
                ->whereIn('user_id', $userIds)
                ->where('status', 'approved')
                ->whereYear('start_date', $year)
                ->count(),
            'total_credits' => LeaveCredit::where('company_id', $user->company_id)
                ->whereIn('user_id', $userIds)
                ->where('year', $year)
                ->sum('credits'),
            'used_credits' => LeaveRequest::where('company_id', $user->company_id)
                ->whereIn('user_id', $userIds)
                ->where('status', 'approved')
                ->whereYear('start_date', $year)
                ->sum('days_requested'),
        ];

        $stats['available_credits'] = $stats['total_credits'] - $stats['used_credits'];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Check if user can approve a leave request.
     */
    private function canApprove(LeaveRequest $leaveRequest): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Admin can approve any request
        if ($user->isAdmin()) {
            return true;
        }

        // Team leaders can approve requests from their team members
        if ($user->isTeamLeaderOrCoLeader()) {
            $teamMemberIds = $user->managedTeams()
                ->with('members')
                ->get()
                ->flatMap(function ($team) {
                    $memberIds = $team->members->pluck('id')->toArray();
                    if ($team->leader_id) {
                        $memberIds[] = $team->leader_id;
                    }

                    return $memberIds;
                })
                ->unique()
                ->toArray();

            return in_array($leaveRequest->user_id, $teamMemberIds);
        }

        return false;
    }

    /**
     * Get available leave credits for a user.
     */
    private function getAvailableCredits(int $userId, string $leaveType, ?int $year = null): float
    {
        if (! $year) {
            $year = date('Y');
        }

        // Get total credits
        $totalCredits = LeaveCredit::where('user_id', $userId)
            ->where('leave_type', $leaveType)
            ->where('year', $year)
            ->sum('credits');

        // Get used credits (approved requests)
        $usedCredits = LeaveRequest::where('user_id', $userId)
            ->where('leave_type', $leaveType)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days_requested');

        return max(0, (float) $totalCredits - (float) $usedCredits);
    }
}
