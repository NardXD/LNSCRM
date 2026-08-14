<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTimeTracking;
use App\Models\ScreenRecording;
use App\Models\Task;
use App\Models\Team;
use App\Models\TimeTracking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TeamManagementController extends Controller
{
    /**
     * Display the team management page.
     */
    public function index()
    {
        return view('dashboard.team-management');
    }

    /**
     * Get all teams for the authenticated user's company.
     */
    public function getTeams(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        $query = Team::where('company_id', $user->company_id)
            ->with(['leader', 'members'])
            ->withCount('members');

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->active === 'true');
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $teams = $query->orderBy('name')->get()->map(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'color' => $team->color,
                'is_active' => $team->is_active,
                'leader' => $team->leader ? [
                    'id' => $team->leader->id,
                    'name' => $team->leader->name,
                    'email' => $team->leader->email,
                    'photo' => $team->leader->photo ? asset('storage/' . $team->leader->photo) : null,
                    'initials' => $this->getInitials($team->leader->name),
                ] : null,
                'members_count' => $team->members_count,
                'members' => $team->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'photo' => $member->photo ? asset('storage/' . $member->photo) : null,
                        'initials' => $this->getInitials($member->name),
                        'role' => $member->pivot->role,
                        'joined_at' => $member->pivot->joined_at,
                    ];
                }),
                'projects_count' => 0,
                'created_at' => $team->created_at->format('M d, Y'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $teams,
        ]);
    }

    /**
     * Get a single team with full details.
     */
    public function getTeam(Team $team)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        $team->load(['leader', 'members']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'color' => $team->color,
                'is_active' => $team->is_active,
                'leader_id' => $team->leader_id,
                'leader' => $team->leader ? [
                    'id' => $team->leader->id,
                    'name' => $team->leader->name,
                    'email' => $team->leader->email,
                    'photo' => $team->leader->photo ? asset('storage/' . $team->leader->photo) : null,
                    'initials' => $this->getInitials($team->leader->name),
                ] : null,
                'members' => $team->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'photo' => $member->photo ? asset('storage/' . $member->photo) : null,
                        'initials' => $this->getInitials($member->name),
                        'role' => $member->pivot->role,
                        'joined_at' => $member->pivot->joined_at,
                    ];
                }),
                'projects' => [],
            ],
        ]);
    }

    /**
     * Store a new team.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'leader_id' => 'nullable|exists:users,id',
            'color' => 'nullable|string|max:7',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
        ]);

        // Verify leader belongs to same company
        if ($validated['leader_id'] ?? null) {
            $leader = User::where('id', $validated['leader_id'])
                ->where('company_id', $user->company_id)
                ->first();
            if (!$leader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected leader does not belong to your company.',
                ], 422);
            }
        }

        $team = Team::create([
            'company_id' => $user->company_id,
            'leader_id' => $validated['leader_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#5f61e6',
            'is_active' => true,
        ]);

        // Add members
        if (!empty($validated['member_ids'])) {
            $members = User::whereIn('id', $validated['member_ids'])
                ->where('company_id', $user->company_id)
                ->pluck('id');
            
            foreach ($members as $memberId) {
                $team->members()->attach($memberId, [
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }
        }

        $team->load(['leader', 'members']);

        return response()->json([
            'success' => true,
            'message' => 'Team created successfully.',
            'data' => $team,
        ], 201);
    }

    /**
     * Update a team.
     */
    public function update(Request $request, Team $team)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'leader_id' => 'nullable|exists:users,id',
            'color' => 'nullable|string|max:7',
            'is_active' => 'sometimes|boolean',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
        ]);

        // Verify leader belongs to same company
        if (isset($validated['leader_id']) && $validated['leader_id']) {
            $leader = User::where('id', $validated['leader_id'])
                ->where('company_id', $user->company_id)
                ->first();
            if (!$leader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected leader does not belong to your company.',
                ], 422);
            }
        }

        // Update team attributes (exclude member_ids from direct update)
        $teamAttributes = collect($validated)->except('member_ids')->toArray();
        if (array_key_exists('leader_id', $teamAttributes) && ! $teamAttributes['leader_id']) {
            $teamAttributes['leader_id'] = null;
        }
        $team->update($teamAttributes);

        // Sync team members when member_ids is provided
        if (array_key_exists('member_ids', $validated)) {
            $newMemberIds = collect($validated['member_ids'])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->filter()
                ->values()
                ->all();

            // Exclude leader from members (leader is stored separately)
            $leaderId = $team->leader_id ?? $validated['leader_id'] ?? null;
            if ($leaderId) {
                $newMemberIds = array_values(array_filter($newMemberIds, fn ($id) => (int) $id !== (int) $leaderId));
            }

            $currentMemberIds = $team->members()->pluck('users.id')->all();
            $toAdd = array_diff($newMemberIds, $currentMemberIds);
            $toRemove = array_diff($currentMemberIds, $newMemberIds);

            foreach ($toRemove as $userId) {
                $team->members()->detach($userId);
            }

            foreach ($toAdd as $userId) {
                $memberUser = User::where('id', $userId)
                    ->where('company_id', $user->company_id)
                    ->first();
                if ($memberUser) {
                    $team->members()->attach($userId, [
                        'role' => 'member',
                        'joined_at' => now(),
                    ]);
                }
            }
        }

        $team->load(['leader', 'members']);

        return response()->json([
            'success' => true,
            'message' => 'Team updated successfully.',
            'data' => $team,
        ]);
    }

    /**
     * Delete a team.
     */
    public function destroy(Team $team)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        $team->delete();

        return response()->json([
            'success' => true,
            'message' => 'Team deleted successfully.',
        ]);
    }

    /**
     * Get paginated team members.
     */
    public function getTeamMembers(Request $request, Team $team)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        // Load relationships
        $team->load(['leader', 'members']);

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        // Build the list of all members including leader
        $allMembers = collect();

        // Add leader first if exists
        if ($team->leader) {
            $allMembers->push([
                'id' => $team->leader->id,
                'name' => $team->leader->name,
                'email' => $team->leader->email,
                'photo' => $team->leader->photo ? asset('storage/' . $team->leader->photo) : null,
                'initials' => $this->getInitials($team->leader->name),
                'role' => 'leader',
                'joined_at' => $team->created_at,
            ]);
        }

        // Add team members
        foreach ($team->members as $member) {
            // Skip if member is also the leader (avoid duplicates)
            if ($team->leader && $member->id === $team->leader->id) {
                continue;
            }

            $allMembers->push([
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'photo' => $member->photo ? asset('storage/' . $member->photo) : null,
                'initials' => $this->getInitials($member->name),
                'role' => $member->pivot->role,
                'joined_at' => $member->pivot->joined_at,
            ]);
        }

        // Apply search filter
        if ($search) {
            $allMembers = $allMembers->filter(function ($member) use ($search) {
                return str_contains(strtolower($member['name']), strtolower($search)) ||
                       str_contains(strtolower($member['email']), strtolower($search));
            });
        }

        $total = $allMembers->count();

        // Paginate the collection
        $members = $allMembers->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $members,
            'pagination' => [
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ]);
    }

    /**
     * Add members to a team.
     */
    public function addMembers(Request $request, Team $team)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        $validated = $request->validate([
            'member_ids' => 'required|array',
            'member_ids.*' => 'exists:users,id',
            'role' => 'nullable|in:member,co-leader',
        ]);

        $role = $validated['role'] ?? 'member';
        $addedCount = 0;

        foreach ($validated['member_ids'] as $memberId) {
            // Verify user belongs to same company
            $memberUser = User::where('id', $memberId)
                ->where('company_id', $user->company_id)
                ->first();

            if ($memberUser && !$team->members()->where('users.id', $memberId)->exists()) {
                $team->members()->attach($memberId, [
                    'role' => $role,
                    'joined_at' => now(),
                ]);
                $addedCount++;
            }
        }

        $team->load('members');

        return response()->json([
            'success' => true,
            'message' => "{$addedCount} member(s) added to the team.",
            'data' => $team,
        ]);
    }

    /**
     * Remove a member from a team.
     */
    public function removeMember(Team $team, User $member)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        $team->members()->detach($member->id);

        return response()->json([
            'success' => true,
            'message' => 'Member removed from the team.',
        ]);
    }

    /**
     * Update a member's role in the team.
     */
    public function updateMemberRole(Request $request, Team $team, User $member)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        $validated = $request->validate([
            'role' => 'required|in:member,co-leader',
        ]);

        $team->members()->updateExistingPivot($member->id, [
            'role' => $validated['role'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member role updated successfully.',
        ]);
    }

    /**
     * Get time tracking records for team members.
     */
    public function getTeamTimeTracking(Request $request, Team $team)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        // Check if user has permission to view team time tracking
        // Team leaders and co-leaders can view their team's data
        if (!$user->isAdmin() && !$user->hasPermission('view_team_management') && !$team->isLeaderOrCoLeader($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this team\'s time tracking.',
            ], 403);
        }

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $memberIds = $team->members->pluck('id')->toArray();
        if ($team->leader_id) {
            $memberIds[] = $team->leader_id;
        }
        $memberIds = array_unique($memberIds);

        $query = TimeTracking::whereIn('user_id', $memberIds)
            ->where('company_id', $team->company_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('user')
            ->orderBy('date', 'desc');

        $total = $query->count();
        
        $records = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'user_id' => $record->user_id,
                    'user_name' => $record->user->name,
                    'user_initials' => $this->getInitials($record->user->name),
                    'date' => $record->date->format('M d, Y'),
                    'time_in' => $record->time_in,
                    'time_out' => $record->time_out,
                    'hours_worked' => $record->hours_worked_formatted,
                    'status' => $record->status,
                ];
            });

        // Group by user for summary (from all records, not paginated)
        $allRecords = TimeTracking::whereIn('user_id', $memberIds)
            ->where('company_id', $team->company_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('user')
            ->get();

        $summary = $allRecords->groupBy('user_id')->map(function ($userRecords) {
            return [
                'user_id' => $userRecords->first()->user_id,
                'user_name' => $userRecords->first()->user->name,
                'total_records' => $userRecords->count(),
                'days_present' => $userRecords->where('status', 'completed')->count(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'records' => $records,
                'summary' => $summary,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ]);
    }

    /**
     * Get screen recordings for team members.
     */
    public function getTeamRecordings(Request $request, Team $team)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        // Check if user has permission to view team recordings
        if (!$user->isAdmin() && !$user->hasPermission('view_team_management') && !$team->isLeaderOrCoLeader($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this team\'s recordings.',
            ], 403);
        }

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $memberId = $request->input('member_id');

        $memberIds = $team->members->pluck('id')->toArray();
        if ($team->leader_id) {
            $memberIds[] = $team->leader_id;
        }
        $memberIds = array_unique($memberIds);

        // Filter by specific member if requested
        if ($memberId && in_array($memberId, $memberIds)) {
            $memberIds = [$memberId];
        }

        // Get all recordings for the date range
        $recordings = ScreenRecording::whereIn('user_id', $memberIds)
            ->where('company_id', $team->company_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->whereNotNull('screen_recording_path')
            ->with('user')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Group recordings by user
        $groupedRecordings = $recordings->groupBy('user_id')->map(function ($userRecordings, $userId) use ($team) {
            $firstRecording = $userRecordings->first();
            $user = $firstRecording->user;
            
            return [
                'user_id' => $userId,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_initials' => $this->getInitials($user->name),
                'user_photo' => $user->photo ? asset('storage/' . $user->photo) : null,
                'total_videos' => $userRecordings->count(),
                'recordings' => $userRecordings->map(function ($recording) use ($team) {
                    return [
                        'id' => $recording->id,
                        'date' => $recording->date->format('M d, Y'),
                        'date_full' => $recording->date->format('M d, Y') . ' at ' . $recording->created_at->format('h:i A'),
                        'time' => $recording->created_at->format('H:i:s'),
                        'duration' => $recording->duration_formatted,
                        'duration_formatted' => $recording->duration_formatted,
                        'url' => route('api.team-management.teams.recordings.view', [
                            'team' => $team->id,
                            'recording' => $recording->id
                        ]),
                        'status' => $recording->status,
                        'has_recording' => !empty($recording->screen_recording_path),
                    ];
                })->values()->toArray(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $groupedRecordings,
            'total' => $recordings->count(),
        ]);
    }

    /**
     * View a team member's recording.
     */
    public function viewRecording(Team $team, $recordingId)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            abort(404, 'Team not found');
        }

        // Check if user has permission
        if (!$user->isAdmin() && !$user->hasPermission('view_team_management') && !$team->isLeaderOrCoLeader($user)) {
            abort(403, 'Access denied');
        }

        $memberIds = $team->members->pluck('id')->toArray();
        if ($team->leader_id) {
            $memberIds[] = $team->leader_id;
        }

        $recording = ScreenRecording::where('id', $recordingId)
            ->whereIn('user_id', array_unique($memberIds))
            ->first();

        if (!$recording || !$recording->screen_recording_path) {
            abort(404, 'Recording not found');
        }

        if (!Storage::disk('private')->exists($recording->screen_recording_path)) {
            abort(404, 'Recording file not found');
        }

        $filePath = Storage::disk('private')->path($recording->screen_recording_path);
        $mimeType = mime_content_type($filePath) ?: 'video/webm';

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
        ]);
    }

    /**
     * Get tasks for team members, grouped by employee.
     */
    public function getTeamTasks(Request $request, Team $team)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        // Get all team member IDs (including leader)
        $memberIds = $team->members->pluck('id')->toArray();
        if ($team->leader_id) {
            $memberIds[] = $team->leader_id;
        }
        $memberIds = array_unique($memberIds);

        // Get all tasks assigned to team members (from any project in the company)
        $tasks = Task::whereIn('assigned_to', $memberIds)
            ->whereNotNull('assigned_to')
            ->whereHas('project', function ($query) use ($team) {
                $query->where('company_id', $team->company_id);
            })
            ->with(['assignedUser', 'project'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Group tasks by assigned user
        $groupedTasks = $tasks->groupBy('assigned_to')->map(function ($userTasks, $userId) {
            $firstTask = $userTasks->first();
            $assignedUser = $firstTask->assignedUser;

            return [
                'user_id' => $userId,
                'user_name' => $assignedUser->name,
                'user_email' => $assignedUser->email,
                'user_initials' => $this->getInitials($assignedUser->name),
                'user_photo' => $assignedUser->photo ? asset('storage/' . $assignedUser->photo) : null,
                'total_tasks' => $userTasks->count(),
                'tasks' => $userTasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => $task->priority,
                        'status' => $task->status,
                        'progress' => $task->progress,
                        'deadline' => $task->deadline ? $task->deadline->format('M d, Y') : null,
                        'project_title' => $task->project->title ?? 'N/A',
                        'created_at' => $task->created_at->format('M d, Y'),
                    ];
                })->values()->toArray(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $groupedTasks,
        ]);
    }

    /**
     * Get project time tracking records for a specific task.
     */
    public function getTaskTimeTracking(Request $request, Team $team, $taskId)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        // Verify task exists and belongs to a project in the company
        $task = Task::whereHas('project', function ($query) use ($team) {
            $query->where('company_id', $team->company_id);
        })->find($taskId);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found or access denied.',
            ], 404);
        }

        // Get all project time tracking records for this task
        $timeTrackingRecords = ProjectTimeTracking::where('task_id', $taskId)
            ->where('company_id', $team->company_id)
            ->with(['user'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'user_id' => $record->user_id,
                    'user_name' => $record->user->name,
                    'user_initials' => $this->getInitials($record->user->name),
                    'date' => $record->date->format('M d, Y'),
                    'start_time' => $record->start_time,
                    'end_time' => $record->end_time,
                    'hours_worked' => $record->hours_worked_formatted,
                    'notes' => $record->notes,
                    'status' => $record->status,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'project_title' => $task->project->title ?? 'N/A',
                    'progress' => $task->progress,
                ],
                'time_tracking' => $timeTrackingRecords,
                'total_records' => $timeTrackingRecords->count(),
            ],
        ]);
    }

    /**
     * Get available users for team assignment.
     */
    public function getAvailableUsers(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or no company assigned.',
            ], 401);
        }

        $teamId = $request->input('exclude_team_id');

        $query = User::where('company_id', $user->company_id)
            ->where('status', 'active');

        // Exclude users already in a specific team
        if ($teamId) {
            $team = Team::find($teamId);
            if ($team) {
                $existingMemberIds = $team->members->pluck('id')->toArray();
                if ($team->leader_id) {
                    $existingMemberIds[] = $team->leader_id;
                }
                $query->whereNotIn('id', $existingMemberIds);
            }
        }

        $users = $query->orderBy('name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'photo' => $user->photo ? asset('storage/' . $user->photo) : null,
                'initials' => $this->getInitials($user->name),
                'department' => $user->department ? $user->department->name : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get team stats for dashboard.
     */
    public function getTeamStats(Team $team)
    {
        $user = Auth::user();

        if (!$user || $team->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found or access denied.',
            ], 404);
        }

        $memberIds = $team->members->pluck('id')->toArray();
        if ($team->leader_id) {
            $memberIds[] = $team->leader_id;
        }
        $memberIds = array_unique($memberIds);

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Today's attendance
        $todayAttendance = TimeTracking::whereIn('user_id', $memberIds)
            ->where('date', $today)
            ->whereNotNull('time_in')
            ->count();

        // Active projects - not used anymore, set to 0
        $activeProjects = 0;

        // Completed tasks this week - calculate from tasks assigned to team members
        $completedTasksThisWeek = Task::whereIn('assigned_to', $memberIds)
            ->where('status', 'done')
            ->where('updated_at', '>=', $startOfWeek)
            ->count();

        // Total hours this month
        $totalHoursThisMonth = TimeTracking::whereIn('user_id', $memberIds)
            ->where('date', '>=', $startOfMonth)
            ->sum('hours_worked');

        return response()->json([
            'success' => true,
            'data' => [
                'total_members' => count($memberIds),
                'today_attendance' => $todayAttendance,
                'active_projects' => $activeProjects,
                'completed_tasks_this_week' => $completedTasksThisWeek,
                'total_hours_this_month' => round($totalHoursThisMonth / 3600, 1), // Convert seconds to hours
            ],
        ]);
    }

    /**
     * Get initials from name.
     */
    private function getInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $initials = '';

        if (count($parts) >= 2) {
            $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        } else {
            $initials = strtoupper(substr($name, 0, 2));
        }

        return $initials;
    }
}
