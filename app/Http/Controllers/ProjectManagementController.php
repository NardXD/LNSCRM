<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTimeTracking;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectManagementController extends Controller
{
    /**
     * Get all projects for the authenticated user's company.
     */
    public function getProjects(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $status = $request->get('status');
            $query = Project::with(['teamMembers', 'tasks', 'client'])
                ->where('company_id', $user->company_id);

            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            $projects = $query->orderBy('created_at', 'desc')->get()->map(function ($project) {
                // Calculate progress based on tasks
                $progress = $project->calculateProgress();
                $project->progress = $progress;

                // Handle client - try to get name from relationship first, then fall back to string field
                $clientName = null;
                try {
                    if ($project->relationLoaded('client') && $project->client) {
                        $clientName = $project->client->name;
                    } elseif ($project->client_id) {
                        // If client_id exists but relationship not loaded, try to load it
                        $project->loadMissing('client');
                        if ($project->client) {
                            $clientName = $project->client->name;
                        }
                    }
                } catch (\Exception $e) {
                    // If relationship loading fails, continue to fallback
                }

                // Fall back to client field if it's a string
                if (! $clientName && ! empty($project->client) && is_string($project->client)) {
                    $clientName = $project->client;
                }

                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'client' => $clientName ?: ($project->client ?? null),
                    'client_id' => $project->client_id,
                    'client_name' => $clientName,
                    'status' => $project->status,
                    'progress' => $progress,
                    'tasks' => $project->total_tasks_count,
                    'completed' => $project->completed_tasks_count,
                    'deadline' => $project->deadline ? $project->deadline->format('M d, Y') : null,
                    'deadline_raw' => $project->deadline ? $project->deadline->format('Y-m-d') : null,
                    'description' => $project->description,
                    'team' => $project->teamMembers->map(function ($member) {
                        return strtoupper(substr($member->name, 0, 1).substr(strstr($member->name, ' ') ?: $member->name, 1, 1));
                    })->toArray(),
                    'team_members' => $project->teamMembers->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'name' => $member->name,
                            'initials' => strtoupper(substr($member->name, 0, 1).substr(strstr($member->name, ' ') ?: $member->name, 1, 1)),
                        ];
                    })->values(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $projects,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching projects: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single project.
     */
    public function getProject(Project $project)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id || $project->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied.',
                ], 404);
            }

            $project->load(['teamMembers', 'tasks.assignedUser']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'client' => $project->client,
                    'status' => $project->status,
                    'progress' => $project->calculateProgress(),
                    'deadline' => $project->deadline->format('Y-m-d'),
                    'description' => $project->description,
                    'team' => $project->teamMembers->pluck('id')->toArray(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching project: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new project.
     */
    public function storeProject(StoreProjectRequest $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            // Check if user is active
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active users can create projects.',
                ], 403);
            }

            // Check permission to create projects
            if (! $user->hasPermission('create_project_management')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to create projects.',
                ], 403);
            }

            // Check if user's role is active
            if ($user->role_id) {
                $user->load('role');
                if ($user->role && ! $user->role->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your role must be active to create projects.',
                    ], 403);
                }
            }

            DB::beginTransaction();

            // If client_id is provided, use it; otherwise use client name
            $clientId = null;
            $clientName = $request->client ?? '';

            if ($request->has('client_id') && $request->client_id) {
                // Verify client belongs to company
                $client = Client::where('id', $request->client_id)
                    ->where('company_id', $user->company_id)
                    ->first();
                if ($client) {
                    $clientId = $client->id;
                    $clientName = $client->name; // Use client name from database
                }
            }

            $project = Project::create([
                'company_id' => $user->company_id,
                'client_id' => $clientId,
                'title' => $request->title,
                'client' => $clientName,
                'status' => $request->status,
                'deadline' => $request->deadline,
                'description' => $request->description,
                'progress' => 0,
            ]);

            // Attach team members
            if ($request->has('team') && is_array($request->team)) {
                // Verify team members belong to the same company
                $teamMemberIds = User::where('company_id', $user->company_id)
                    ->whereIn('id', $request->team)
                    ->pluck('id')
                    ->toArray();
                $project->teamMembers()->attach($teamMemberIds);
            }

            DB::commit();

            $project->load('teamMembers');

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully.',
                'data' => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'client' => $project->client,
                    'status' => $project->status,
                    'progress' => 0,
                    'tasks' => 0,
                    'completed' => 0,
                    'deadline' => $project->deadline->format('M d, Y'),
                    'team' => $project->teamMembers->pluck('id')->map(function ($id) use ($project) {
                        $member = $project->teamMembers->firstWhere('id', $id);

                        return strtoupper(substr($member->name, 0, 1).substr(strstr($member->name, ' ') ?: $member->name, 1, 1));
                    })->toArray(),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating project: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a project.
     */
    public function updateProject(UpdateProjectRequest $request, Project $project)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id || $project->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied.',
                ], 404);
            }

            // Check permission to edit projects
            if (! $user->hasPermission('edit_project_management')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to edit projects.',
                ], 403);
            }

            DB::beginTransaction();

            $updateData = $request->only([
                'title',
                'status',
                'deadline',
                'description',
            ]);

            // Update client_id if provided
            if ($request->has('client_id')) {
                if ($request->client_id) {
                    // Verify client belongs to company
                    $client = Client::where('id', $request->client_id)
                        ->where('company_id', $user->company_id)
                        ->first();
                    if ($client) {
                        $updateData['client_id'] = $client->id;
                        $updateData['client'] = $client->name; // Update client name as well
                    }
                } else {
                    $updateData['client_id'] = null;
                    $updateData['client'] = $request->client ?? ''; // Use provided client name or empty string
                }
            } elseif ($request->has('client')) {
                // If only client name is provided (without client_id), update it
                $updateData['client'] = $request->client;
            }

            $project->update($updateData);

            // Update team members
            if ($request->has('team')) {
                $teamMemberIds = User::where('company_id', $user->company_id)
                    ->whereIn('id', $request->team ?? [])
                    ->pluck('id')
                    ->toArray();
                $project->teamMembers()->sync($teamMemberIds);
            }

            // Update progress if status changed to completed
            if ($request->status === 'completed') {
                $project->progress = 100;
                $project->save();
            } else {
                $project->progress = $project->calculateProgress();
                $project->save();
            }

            DB::commit();

            $project->load('teamMembers');

            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully.',
                'data' => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'client' => $project->client,
                    'status' => $project->status,
                    'progress' => $project->progress,
                    'tasks' => $project->total_tasks_count,
                    'completed' => $project->completed_tasks_count,
                    'deadline' => $project->deadline->format('M d, Y'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating project: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a project.
     */
    public function deleteProject(Project $project)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id || $project->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or access denied.',
                ], 404);
            }

            // Check permission to delete projects
            if (! $user->hasPermission('delete_project_management')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete projects.',
                ], 403);
            }

            $project->delete();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting project: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get project statistics.
     */
    public function getProjectStats()
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $stats = [
                'total' => Project::where('company_id', $user->company_id)->count(),
                'active' => Project::where('company_id', $user->company_id)->where('status', 'active')->count(),
                'completed' => Project::where('company_id', $user->company_id)->where('status', 'completed')->count(),
                'on_hold' => Project::where('company_id', $user->company_id)->where('status', 'on-hold')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stats: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all tasks.
     */
    public function getTasks(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $projectId = $request->get('project_id', 'all');
            $status = $request->get('status', 'all');

            $query = Task::with(['project', 'assignedUser'])
                ->whereHas('project', function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                });

            if ($projectId !== 'all') {
                $query->where('project_id', $projectId);
            }

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $paginatedTasks = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $tasks = $paginatedTasks->map(function ($task) {
                $assignedTo = $task->assignedUser;
                $initials = '--';
                $name = 'Unassigned';

                if ($assignedTo) {
                    $nameParts = explode(' ', $assignedTo->name);
                    $initials = strtoupper(
                        substr($nameParts[0], 0, 1).
                        (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
                    );
                    $name = $assignedTo->name;
                }

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'project' => $task->project->title,
                    'project_id' => $task->project_id,
                    'assignedTo' => $assignedTo ? [
                        'id' => $assignedTo->id,
                        'name' => $name,
                        'initials' => $initials,
                    ] : null,
                    'priority' => $task->priority,
                    'deadline' => $task->deadline ? $task->deadline->format('M d, Y') : null,
                    'deadline_raw' => $task->deadline ? $task->deadline->format('Y-m-d') : null,
                    'status' => $task->status,
                    'progress' => $task->progress,
                    'description' => $task->description,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'pagination' => [
                    'current_page' => $paginatedTasks->currentPage(),
                    'last_page' => $paginatedTasks->lastPage(),
                    'per_page' => $paginatedTasks->perPage(),
                    'total' => $paginatedTasks->total(),
                    'from' => $paginatedTasks->firstItem() ?? 0,
                    'to' => $paginatedTasks->lastItem() ?? 0,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tasks: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new task.
     */
    public function storeTask(StoreTaskRequest $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            // Check if user is active
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active users can create tasks.',
                ], 403);
            }

            // Check permission to create tasks
            if (! $user->hasPermission('create_task_management')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to create tasks.',
                ], 403);
            }

            // Check if user's role is active
            if ($user->role_id) {
                $user->load('role');
                if ($user->role && ! $user->role->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your role must be active to create tasks.',
                    ], 403);
                }
            }

            // Verify project belongs to user's company
            $project = Project::where('id', $request->project_id)
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            $task = Task::create([
                'project_id' => $request->project_id,
                'assigned_to' => $request->assigned_to,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority,
                'deadline' => $request->deadline,
                'status' => $request->status ?? 'todo',
                'progress' => $request->progress ?? 0,
            ]);

            // Update project progress
            $project->progress = $project->calculateProgress();
            $project->save();

            $task->load(['project', 'assignedUser']);

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully.',
                'data' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'project' => $task->project->title,
                    'assignedTo' => $task->assignedUser ? [
                        'name' => $task->assignedUser->name,
                        'initials' => strtoupper(substr($task->assignedUser->name, 0, 1).substr(strstr($task->assignedUser->name, ' ') ?: $task->assignedUser->name, 1, 1)),
                    ] : null,
                    'priority' => $task->priority,
                    'deadline' => $task->deadline ? $task->deadline->format('M d, Y') : null,
                    'status' => $task->status,
                    'progress' => $task->progress,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating task: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a task.
     */
    public function updateTask(UpdateTaskRequest $request, Task $task)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            // Verify task belongs to user's company
            if ($task->project->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found or access denied.',
                ], 404);
            }

            // Check permission to edit tasks (allow if user is assigned to the task or has edit permission)
            // Users assigned to a task can always edit their own task
            if (! $user->hasPermission('edit_project_management') && $task->assigned_to !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to edit this task. You can only edit tasks assigned to you.',
                ], 403);
            }

            $updateData = $request->only([
                'project_id',
                'assigned_to',
                'title',
                'description',
                'priority',
                'deadline',
                'status',
                'progress',
            ]);

            // Automatically set status to "done" if progress reaches 100%
            if (isset($updateData['progress']) && $updateData['progress'] >= 100) {
                $updateData['status'] = 'done';
            }

            $task->update($updateData);

            // Update project progress
            $project = $task->project;
            $project->progress = $project->calculateProgress();
            $project->save();

            $task->load(['project', 'assignedUser']);

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully.',
                'data' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'project' => $task->project->title,
                    'assignedTo' => $task->assignedUser ? [
                        'name' => $task->assignedUser->name,
                        'initials' => strtoupper(substr($task->assignedUser->name, 0, 1).substr(strstr($task->assignedUser->name, ' ') ?: $task->assignedUser->name, 1, 1)),
                    ] : null,
                    'priority' => $task->priority,
                    'deadline' => $task->deadline ? $task->deadline->format('M d, Y') : null,
                    'status' => $task->status,
                    'progress' => $task->progress,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating task: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a task.
     */
    public function deleteTask(Task $task)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            // Verify task belongs to user's company
            if ($task->project->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found or access denied.',
                ], 404);
            }

            // Check permission to delete tasks
            if (! $user->hasPermission('delete_project_management')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete tasks.',
                ], 403);
            }

            $project = $task->project;
            $task->delete();

            // Update project progress
            $project->progress = $project->calculateProgress();
            $project->save();

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting task: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get time tracking entries.
     */
    public function getTimeTracking(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $projectId = $request->get('project_id', 'all');
            $date = $request->get('date');

            $query = ProjectTimeTracking::with(['user', 'project', 'task'])
                ->where('company_id', $user->company_id);

            if ($projectId !== 'all') {
                $query->where('project_id', $projectId);
            }

            if ($date) {
                $query->where('date', $date);
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $paginatedEntries = $query->orderBy('date', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

            $entries = $paginatedEntries->map(function ($entry) {
                $user = $entry->user;
                $nameParts = explode(' ', $user->name);
                $initials = strtoupper(
                    substr($nameParts[0], 0, 1).
                    (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1))
                );

                // Format hours as HH:MM:SS
                $hoursFormatted = $entry->hours_worked_formatted ?? '00:00:00';

                return [
                    'id' => $entry->id,
                    'date' => $entry->date->format('M d, Y'),
                    'project' => $entry->project ? $entry->project->title : '--',
                    'task' => $entry->task ? $entry->task->title : '--',
                    'employee' => [
                        'name' => $user->name,
                        'initials' => $initials,
                    ],
                    'hours' => $hoursFormatted,
                    'description' => $entry->notes,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $entries,
                'pagination' => [
                    'current_page' => $paginatedEntries->currentPage(),
                    'last_page' => $paginatedEntries->lastPage(),
                    'per_page' => $paginatedEntries->perPage(),
                    'total' => $paginatedEntries->total(),
                    'from' => $paginatedEntries->firstItem() ?? 0,
                    'to' => $paginatedEntries->lastItem() ?? 0,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching time tracking: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get time tracking summary.
     */
    public function getTimeTrackingSummary(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $today = ProjectTimeTracking::where('company_id', $user->company_id)
                ->whereDate('date', today())
                ->where('status', 'completed')
                ->sum('hours_worked');

            $thisWeek = ProjectTimeTracking::where('company_id', $user->company_id)
                ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
                ->where('status', 'completed')
                ->sum('hours_worked');

            $thisMonth = ProjectTimeTracking::where('company_id', $user->company_id)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->where('status', 'completed')
                ->sum('hours_worked');

            // Helper function to format seconds as HH:MM:SS
            $formatTime = function ($seconds) {
                if (! $seconds || $seconds < 0) {
                    return '00:00:00';
                }

                $totalSeconds = (int) $seconds;
                $hours = floor($totalSeconds / 3600);
                $minutes = floor(($totalSeconds % 3600) / 60);
                $secs = $totalSeconds % 60;

                return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
            };

            return response()->json([
                'success' => true,
                'data' => [
                    'today' => $formatTime($today),
                    'this_week' => $formatTime($thisWeek),
                    'this_month' => $formatTime($thisMonth),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching time tracking summary: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard progress data.
     */
    public function getDashboardProgress(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $projectId = $request->get('project_id', 'all');

            $query = Project::with('tasks')
                ->where('company_id', $user->company_id)
                ->where('status', 'active');

            if ($projectId !== 'all') {
                $query->where('id', $projectId);
            }

            $projects = $query->get();

            // Calculate overall stats
            $totalTasks = 0;
            $completedTasks = 0;
            $totalProgress = 0;

            foreach ($projects as $project) {
                $totalTasks += $project->total_tasks_count;
                $completedTasks += $project->completed_tasks_count;
                $totalProgress += $project->calculateProgress();
            }

            $overallProgress = $projects->isNotEmpty() ? round($totalProgress / $projects->count()) : 0;
            $tasksCompletedPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

            // Calculate on-time percentage (simplified - can be enhanced)
            $onTimePercentage = 85; // Placeholder - implement based on deadline logic

            return response()->json([
                'success' => true,
                'data' => [
                    'overall_progress' => $overallProgress,
                    'tasks_completed' => $completedTasks,
                    'total_tasks' => $totalTasks,
                    'on_time_percentage' => $onTimePercentage,
                    'projects' => $projects->map(function ($project) {
                        return [
                            'id' => $project->id,
                            'title' => $project->title,
                            'progress' => $project->calculateProgress(),
                            'tasks' => $project->total_tasks_count,
                            'completed' => $project->completed_tasks_count,
                            'deadline' => $project->deadline->format('M d, Y'),
                        ];
                    }),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dashboard progress: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get users for team member selection.
     */
    public function getUsers()
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $users = User::where('company_id', $user->company_id)
                ->where('status', 'active')
                ->orderBy('name')
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
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active time tracking records for current user with task information.
     */
    public function getActiveTimeTracking()
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $activeRecords = ProjectTimeTracking::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('start_time')
                ->whereNull('end_time')
                ->with(['task', 'project'])
                ->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'task_id' => $record->task_id,
                        'project_id' => $record->project_id,
                        'task_title' => $record->task ? $record->task->title : null,
                        'project_title' => $record->project ? $record->project->title : null,
                        'start_time' => $record->start_time,
                        'date' => $record->date,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $activeRecords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching active time tracking: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start time tracking for a task.
     */
    public function startTaskTimeTracking(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $request->validate([
                'project_id' => 'required|exists:projects,id',
                'task_id' => 'nullable|exists:tasks,id',
                'description' => 'nullable|string',
            ]);

            // Verify project belongs to user's company
            $project = Project::where('id', $request->project_id)
                ->where('company_id', $user->company_id)
                ->firstOrFail();

            // If task_id provided, verify it belongs to the project
            if ($request->task_id) {
                $task = Task::where('id', $request->task_id)
                    ->where('project_id', $request->project_id)
                    ->firstOrFail();
            }

            // Check if there's already an active time tracking record for this task
            $activeRecord = ProjectTimeTracking::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('end_time')
                ->where('task_id', $request->task_id)
                ->first();

            if ($activeRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active time tracking session for this task.',
                    'record' => $activeRecord,
                ], 400);
            }

            // Get current time
            $now = now();
            $date = $now->format('Y-m-d');
            $startTime = $now->format('H:i:s');

            // Create new project time tracking record
            $record = ProjectTimeTracking::create([
                'project_id' => $request->project_id,
                'task_id' => $request->task_id,
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'date' => $date,
                'start_time' => $startTime,
                'status' => 'active',
                'notes' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Time tracking started successfully.',
                'data' => [
                    'id' => $record->id,
                    'task_id' => $record->task_id,
                    'project_id' => $record->project_id,
                    'date' => $record->date->format('Y-m-d'),
                    'start_time' => $record->start_time,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting time tracking: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stop task time tracking with notes and progress update.
     */
    public function stopTaskTimeTracking(Request $request, $taskId)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $request->validate([
                'time_tracking_id' => 'required|exists:project_time_tracking,id',
                'notes' => 'required|string',
                'progress' => 'required|integer|min:0|max:100',
            ]);

            // Verify task belongs to user's company
            $task = Task::with('project')->findOrFail($taskId);
            if ($task->project->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found or access denied.',
                ], 404);
            }

            // Verify time tracking record belongs to user and task
            $timeRecord = ProjectTimeTracking::where('id', $request->time_tracking_id)
                ->where('user_id', $user->id)
                ->where('task_id', $taskId)
                ->where('status', 'active')
                ->whereNull('end_time')
                ->firstOrFail();

            DB::beginTransaction();

            // Get current time for end_time
            $now = now();
            $endTime = $now->format('H:i:s');
            $date = $timeRecord->date instanceof Carbon ? $timeRecord->date->format('Y-m-d') : $timeRecord->date->format('Y-m-d');

            // Calculate hours worked
            $startTimeStr = is_string($timeRecord->start_time) ? $timeRecord->start_time : $timeRecord->start_time->format('H:i:s');

            $startDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$startTimeStr);
            $endDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d').' '.$endTime);

            if ($endDateTime->lessThan($startDateTime)) {
                $endDateTime->addDay();
            }

            $hoursWorked = max(0, $endDateTime->timestamp - $startDateTime->timestamp);

            // Update time tracking record
            $timeRecord->update([
                'end_time' => $endTime,
                'hours_worked' => $hoursWorked,
                'status' => 'completed',
                'notes' => $request->notes,
            ]);

            // Update task progress and status
            $updateData = [
                'progress' => $request->progress,
            ];

            // Automatically set status to "done" if progress reaches 100%
            if ($request->progress >= 100) {
                $updateData['status'] = 'done';
            }

            $task->update($updateData);

            // Update project progress
            $project = $task->project;
            $project->progress = $project->calculateProgress();
            $project->save();

            DB::commit();

            // Format hours worked as HH:MM:SS
            $totalSeconds = (int) $hoursWorked;
            $hours = floor($totalSeconds / 3600);
            $minutes = floor(($totalSeconds % 3600) / 60);
            $seconds = $totalSeconds % 60;
            $hoursFormatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

            return response()->json([
                'success' => true,
                'message' => 'Time tracking stopped and task updated successfully.',
                'data' => [
                    'hours_worked' => $hoursFormatted,
                    'task_progress' => $task->progress,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error stopping time tracking: '.$e->getMessage(),
            ], 500);
        }
    }
}
