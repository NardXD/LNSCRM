<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientNote;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ClientManagementController extends Controller
{
    /**
     * Display the client management page.
     */
    public function index()
    {
        return view('dashboard.client-management');
    }

    /**
     * Get all clients for the authenticated user's company.
     */
    public function getClients(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $query = Client::where('company_id', $companyId)
            ->with(['contacts', 'employees.department', 'projects'])
            ->orderBy('created_at', 'desc');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Industry filter
        if ($request->has('industry') && $request->industry !== 'all') {
            $query->where('industry', $request->industry);
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $clients = $query->paginate($perPage);

        // Format clients with employees having proper photo URLs
        $formattedClients = collect($clients->items())->map(function ($client) {
            $clientData = $client->toArray();

            // Format employees with photo URLs
            if (isset($clientData['employees']) && is_array($clientData['employees'])) {
                $clientData['employees'] = array_map(function ($employee) {
                    if (! empty($employee['photo'])) {
                        $employee['photo'] = public_media_url($employee['photo']);
                    } else {
                        $employee['photo'] = null;
                    }

                    return $employee;
                }, $clientData['employees']);
            }

            return $clientData;
        });

        return response()->json([
            'success' => true,
            'data' => $formattedClients->all(),
            'pagination' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    /**
     * Get client statistics.
     */
    public function getStats(): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $totalClients = Client::where('company_id', $companyId)->count();
        $activeClients = Client::where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        $thisMonth = Client::where('company_id', $companyId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $lastMonth = Client::where('company_id', $companyId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        $growth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : ($thisMonth > 0 ? 100 : 0);

        $totalRevenue = Client::where('company_id', $companyId)
            ->sum('revenue');

        return response()->json([
            'success' => true,
            'data' => [
                'total_clients' => $totalClients,
                'active_clients' => $activeClients,
                'active_percentage' => $totalClients > 0 ? round(($activeClients / $totalClients) * 100) : 0,
                'new_this_month' => $thisMonth,
                'growth_percentage' => $growth,
                'total_revenue' => (float) $totalRevenue,
            ],
        ]);
    }

    /**
     * Search clients by name for autocomplete.
     */
    public function searchClients(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        $search = $request->get('q', '');

        if (empty($search)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $clients = Client::where('company_id', $companyId)
            ->where('name', 'like', "%{$search}%")
            ->limit(10)
            ->get(['id', 'name'])
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Store a newly created client.
     */
    public function store(StoreClientRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();

            $client = Client::create([
                'company_id' => $user->company_id,
                'name' => $request->name,
                'contact_person' => $request->contact_person,
                'email' => $request->email,
                'phone' => $request->phone,
                'industry' => $request->industry,
                'status' => $request->status,
                'website' => $request->website,
                'address' => $request->address,
                'revenue' => $request->revenue ?? 0,
            ]);

            // Create contacts if provided
            if ($request->has('contacts') && is_array($request->contacts)) {
                foreach ($request->contacts as $contactData) {
                    ClientContact::create([
                        'client_id' => $client->id,
                        'name' => $contactData['name'],
                        'role' => $contactData['role'] ?? null,
                        'email' => $contactData['email'] ?? null,
                        'phone' => $contactData['phone'] ?? null,
                    ]);
                }
            }

            DB::commit();

            $client->load('contacts');

            return response()->json([
                'success' => true,
                'message' => 'Client created successfully.',
                'data' => $client,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create client: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific client.
     */
    public function show(Client $client): JsonResponse
    {
        $user = Auth::user();

        // Ensure the client belongs to the user's company
        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $client->load(['contacts', 'employees.department', 'projects', 'notes.user']);

        // Convert to array and format employees with photo URLs
        $clientData = $client->toArray();

        // Format employees with photo URLs
        if (isset($clientData['employees']) && is_array($clientData['employees'])) {
            $clientData['employees'] = array_map(function ($employee) {
                $employee['photo'] = ! empty($employee['photo'])
                    ? public_media_url($employee['photo'])
                    : null;

                return $employee;
            }, $clientData['employees']);
        }

        return response()->json([
            'success' => true,
            'data' => $clientData,
        ]);
    }

    /**
     * Get projects for a specific client.
     */
    public function getClientProjects(Client $client): JsonResponse
    {
        $user = Auth::user();

        // Ensure the client belongs to the user's company
        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $projects = Project::where('client_id', $client->id)
            ->with(['teamMembers', 'tasks'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($project) {
                $progress = $project->calculateProgress();

                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'client' => $project->client,
                    'status' => $project->status,
                    'progress' => $progress,
                    'tasks' => $project->total_tasks_count,
                    'completed' => $project->completed_tasks_count,
                    'deadline' => $project->deadline->format('M d, Y'),
                    'deadline_raw' => $project->deadline->format('Y-m-d'),
                    'description' => $project->description,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Get available employees for assignment to a client.
     */
    public function getAvailableEmployees(Client $client): JsonResponse
    {
        $user = Auth::user();

        // Ensure the client belongs to the user's company
        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        // Get all employees from the company with department relationship
        $allEmployees = User::where('company_id', $user->company_id)
            ->with('department')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department_id', 'photo'])
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'department' => $employee->department ? $employee->department->name : null,
                    'photo' => $employee->photo ? public_media_url($employee->photo) : null,
                ];
            });

        // Get already assigned employee IDs
        $assignedIds = $client->employees->pluck('id')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'all_employees' => $allEmployees,
                'assigned_ids' => $assignedIds,
            ],
        ]);
    }

    /**
     * Assign employees to a client.
     */
    public function assignEmployees(Request $request, Client $client): JsonResponse
    {
        $user = Auth::user();

        // Ensure the client belongs to the user's company
        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:users,id',
        ]);

        // Verify all employees belong to the same company
        $employeeIds = $request->employee_ids;
        $employees = User::whereIn('id', $employeeIds)
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->toArray();

        if (count($employees) !== count($employeeIds)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more employees do not belong to your company.',
            ], 422);
        }

        // Get existing employee IDs and merge with new ones (to add, not replace)
        $existingEmployeeIds = $client->employees->pluck('id')->toArray();
        $allEmployeeIds = array_unique(array_merge($existingEmployeeIds, $employees));

        // Sync employees
        $client->employees()->sync($allEmployeeIds);

        $client->load('employees.department');

        // Format employees with photo URLs
        $employees = $client->employees->map(function ($employee) {
            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'department' => $employee->department ? $employee->department->name : null,
                'photo' => $employee->photo ? public_media_url($employee->photo) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Employees assigned successfully.',
            'data' => $employees,
        ]);
    }

    /**
     * Remove an employee from a client.
     */
    public function removeEmployee(Request $request, Client $client): JsonResponse
    {
        $user = Auth::user();

        // Ensure the client belongs to the user's company
        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $client->employees()->detach($request->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Employee removed successfully.',
        ]);
    }

    /**
     * Update the specified client.
     */
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        try {
            $user = Auth::user();

            // Ensure the client belongs to the user's company
            if ($client->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found.',
                ], 404);
            }

            DB::beginTransaction();

            $client->update($request->only([
                'name',
                'contact_person',
                'email',
                'phone',
                'industry',
                'status',
                'website',
                'address',
                'revenue',
            ]));

            // Update contacts if provided
            if ($request->has('contacts')) {
                // Delete existing contacts
                $client->contacts()->delete();

                // Create new contacts
                if (is_array($request->contacts)) {
                    foreach ($request->contacts as $contactData) {
                        ClientContact::create([
                            'client_id' => $client->id,
                            'name' => $contactData['name'],
                            'role' => $contactData['role'] ?? null,
                            'email' => $contactData['email'] ?? null,
                            'phone' => $contactData['phone'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();

            $client->load('contacts');

            return response()->json([
                'success' => true,
                'message' => 'Client updated successfully.',
                'data' => $client,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update client: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified client.
     */
    public function destroy(Client $client): JsonResponse
    {
        try {
            $user = Auth::user();

            // Ensure the client belongs to the user's company
            if ($client->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found.',
                ], 404);
            }

            $client->delete();

            return response()->json([
                'success' => true,
                'message' => 'Client deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete client: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export clients data.
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $query = Client::where('company_id', $companyId)
            ->with('contacts');

        // Apply filters if provided
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('industry') && $request->industry !== 'all') {
            $query->where('industry', $request->industry);
        }

        $clients = $query->get();

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Get notes for a specific client.
     */
    public function getClientNotes(Client $client): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($client->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found.',
                ], 404);
            }

            $notes = $client->notes()->with('user')->get()->map(function ($note) {
                return [
                    'id' => $note->id,
                    'note' => $note->note,
                    'author' => $note->user->name,
                    'created_at' => $note->created_at->format('M d, Y'),
                    'created_at_raw' => $note->created_at->toISOString(),
                    'time_ago' => $note->created_at->diffForHumans(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $notes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching notes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new note for a client.
     */
    public function storeClientNote(Request $request, Client $client): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($client->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found.',
                ], 404);
            }

            $request->validate([
                'note' => ['required', 'string', 'max:5000'],
            ]);

            $note = ClientNote::create([
                'client_id' => $client->id,
                'user_id' => $user->id,
                'note' => $request->note,
            ]);

            $note->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully.',
                'data' => [
                    'id' => $note->id,
                    'note' => $note->note,
                    'author' => $note->user->name,
                    'created_at' => $note->created_at->format('M d, Y'),
                    'created_at_raw' => $note->created_at->toISOString(),
                    'time_ago' => $note->created_at->diffForHumans(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating note: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a note.
     */
    public function deleteClientNote(Client $client, ClientNote $note): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($client->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found.',
                ], 404);
            }

            if ($note->client_id !== $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note not found.',
                ], 404);
            }

            // Only allow users to delete their own notes or users with permission
            if ($note->user_id !== $user->id && ! $user->hasPermission('delete_client_management')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this note.',
                ], 403);
            }

            $note->delete();

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting note: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get client users (portal users) for a specific client.
     */
    public function getClientUsers(Client $client): JsonResponse
    {
        $user = Auth::user();

        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $clientUsers = \App\Models\ClientUser::where('client_id', $client->id)
            ->orderBy('name')
            ->get()
            ->map(function ($clientUser) {
                return [
                    'id' => $clientUser->id,
                    'name' => $clientUser->name,
                    'email' => $clientUser->email,
                    'phone' => $clientUser->phone,
                    'position' => $clientUser->position,
                    'status' => $clientUser->status,
                    'created_at' => $clientUser->created_at->format('M d, Y'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $clientUsers,
        ]);
    }

    /**
     * Store a new client user (portal user).
     */
    public function storeClientUser(Request $request, Client $client): JsonResponse
    {
        $user = Auth::user();

        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:client_users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $clientUser = \App\Models\ClientUser::create([
                'client_id' => $client->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'phone' => $request->phone,
                'position' => $request->position,
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Client user created successfully.',
                'data' => [
                    'id' => $clientUser->id,
                    'name' => $clientUser->name,
                    'email' => $clientUser->email,
                    'phone' => $clientUser->phone,
                    'position' => $clientUser->position,
                    'status' => $clientUser->status,
                    'created_at' => $clientUser->created_at->format('M d, Y'),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating client user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a client user.
     */
    public function updateClientUser(Request $request, Client $client, \App\Models\ClientUser $clientUser): JsonResponse
    {
        $user = Auth::user();

        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        if ($clientUser->client_id !== $client->id) {
            return response()->json([
                'success' => false,
                'message' => 'Client user not found.',
            ], 404);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:client_users,email,'.$clientUser->id],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        try {
            $updateData = $request->only(['name', 'email', 'phone', 'position', 'status']);

            if ($request->filled('password')) {
                $updateData['password'] = $request->password;
            }

            $clientUser->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Client user updated successfully.',
                'data' => [
                    'id' => $clientUser->id,
                    'name' => $clientUser->name,
                    'email' => $clientUser->email,
                    'phone' => $clientUser->phone,
                    'position' => $clientUser->position,
                    'status' => $clientUser->status,
                    'created_at' => $clientUser->created_at->format('M d, Y'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating client user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a client user.
     */
    public function deleteClientUser(Client $client, \App\Models\ClientUser $clientUser): JsonResponse
    {
        $user = Auth::user();

        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        if ($clientUser->client_id !== $client->id) {
            return response()->json([
                'success' => false,
                'message' => 'Client user not found.',
            ], 404);
        }

        try {
            $clientUser->delete();

            return response()->json([
                'success' => true,
                'message' => 'Client user deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting client user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get signed contracts for a client.
     */
    public function getClientContracts(Client $client): JsonResponse
    {
        $user = Auth::user();

        if ($client->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
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
                'pdf_url' => route('api.client-management.clients.contracts.pdf', [$client, $contract]),
            ]);

        return response()->json([
            'success' => true,
            'data' => $contracts,
        ]);
    }

    /**
     * Download a signed contract PDF for a client.
     */
    public function downloadClientContractPdf(Client $client, Contract $contract): Response
    {
        $user = Auth::user();

        if ($client->company_id !== $user->company_id || $contract->client_id !== $client->id) {
            abort(404);
        }

        if ($contract->status !== 'signed') {
            abort(404);
        }

        $contract->load(['client', 'company', 'signers']);

        $pdf = Pdf::loadView('contract.pdf', ['contract' => $contract])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);

        return $pdf->download('contract-'.$contract->contract_number.'.pdf');
    }
}
