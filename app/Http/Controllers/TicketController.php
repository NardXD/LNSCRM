<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    /**
     * Display the tickets page.
     */
    public function index()
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $employees = User::where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => $this->getInitials($u->name),
            ]);

        $clients = Client::where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);

        return view('dashboard.tickets', [
            'employees' => $employees,
            'clients' => $clients,
        ]);
    }

    /**
     * Get clients and employees for the New Ticket form.
     */
    public function getFormData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $clients = collect();
        $employees = collect();

        if ($companyId) {
            $clients = Client::where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name']);

            $employees = User::where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'clients' => $clients->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
                'employees' => $employees->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            ],
        ]);
    }

    /**
     * Get tickets for the API (paginated, filterable).
     */
    public function getTickets(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $query = Ticket::where('company_id', $companyId)
            ->with(['assignedUser', 'client'])
            ->orderBy('created_at', 'desc');

        if ($request->boolean('assigned_to_me')) {
            $query->where('assigned_to', $user->id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 10);
        $tickets = $query->paginate($perPage);

        $baseUrl = $request->getSchemeAndHttpHost();
        $data = collect($tickets->items())->map(fn ($ticket) => $this->formatTicket($ticket, $baseUrl));

        return response()->json([
            'success' => true,
            'data' => $data->all(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
            'stats' => $this->getStats($companyId),
        ]);
    }

    /**
     * Store a new ticket.
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $ticketNumber = $this->generateTicketNumber($companyId);

        $client = Client::find($request->client_id);
        $data = [
            'company_id' => $companyId,
            'client_name' => $client->name,
            'client_id' => $request->client_id,
            'ticket_number' => $ticketNumber,
            'subject' => $request->subject,
            'description' => $request->description,
            'assigned_to' => $request->assigned_to,
            'priority' => $request->priority,
            'category' => $request->category,
        ];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('ticket-attachments', 'public');
            $data['image_path'] = $path;
        }

        $ticket = Ticket::create($data);
        $ticket->load('assignedUser');

        $baseUrl = $request->getSchemeAndHttpHost();

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            'data' => $this->formatTicket($ticket, $baseUrl),
        ], 201);
    }

    /**
     * Get a single ticket with comments.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();
        if ($ticket->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        $ticket->load(['assignedUser', 'client', 'comments.user']);

        $baseUrl = request()->getSchemeAndHttpHost();

        return response()->json([
            'success' => true,
            'data' => $this->formatTicketDetail($ticket, $baseUrl),
        ]);
    }

    /**
     * Update a ticket (status, priority).
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();
        if ($ticket->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            return response()->json(['success' => false, 'message' => 'This ticket cannot be edited.'], 422);
        }

        $updates = $request->only(['status', 'priority']);
        if (isset($updates['status']) && in_array($updates['status'], ['resolved', 'closed']) && ! $ticket->resolved_at) {
            $updates['resolved_at'] = now();
        }
        $ticket->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated.',
            'data' => $this->formatTicket($ticket->load('assignedUser'), request()->getSchemeAndHttpHost()),
        ]);
    }

    /**
     * Store a comment on a ticket.
     */
    public function storeComment(StoreTicketCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();
        if ($ticket->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            return response()->json(['success' => false, 'message' => 'Comments cannot be added to this ticket.'], 422);
        }

        $comment = $ticket->comments()->create([
            'user_id' => $user->id,
            'content' => $request->content,
        ]);

        if (! $ticket->first_response_at) {
            $ticket->update(['first_response_at' => now()]);
        }

        $comment->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Comment added.',
            'data' => [
                'id' => $comment->id,
                'author' => $comment->user->name,
                'initials' => $this->getInitials($comment->user->name),
                'content' => $comment->content,
                'created_at' => $comment->created_at->diffForHumans(),
            ],
        ], 201);
    }

    /**
     * Generate next ticket number for company.
     */
    protected function generateTicketNumber(int $companyId): string
    {
        $year = now()->year;
        $prefix = "TKT-{$year}-";

        $last = Ticket::where('company_id', $companyId)
            ->where('ticket_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->ticket_number);
            $seq = (int) ($parts[2] ?? 0) + 1;
        }

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Format ticket for list response.
     *
     * @return array<string, mixed>
     */
    protected function formatTicket(Ticket $ticket, string $baseUrl): array
    {
        $assignedTo = $ticket->assignedUser;
        $clientName = $ticket->client_id && $ticket->client
            ? $ticket->client->name
            : $ticket->client_name;

        return [
            'id' => $ticket->id,
            'ticketId' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'client' => $clientName,
            'assignedTo' => $assignedTo ? [
                'name' => $assignedTo->name,
                'initials' => $this->getInitials($assignedTo->name),
            ] : ['name' => 'Unassigned', 'initials' => '—'],
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'sla' => $ticket->sla,
            'category' => $ticket->category,
            'created' => $ticket->created_at->diffForHumans(),
            'image_url' => public_media_url($ticket->image_path),
        ];
    }

    /**
     * Format ticket for detail response.
     *
     * @return array<string, mixed>
     */
    protected function formatTicketDetail(Ticket $ticket, string $baseUrl): array
    {
        $base = $this->formatTicket($ticket, $baseUrl);

        $base['description'] = $ticket->description;
        $base['created_at'] = $ticket->created_at->format('M j, Y \a\t g:i A');
        $base['sla_tracking'] = $this->computeSlaTracking($ticket);
        $base['comments'] = $ticket->comments->map(fn ($c) => [
            'id' => $c->id,
            'author' => $c->user->name,
            'initials' => $this->getInitials($c->user->name),
            'text' => $c->content,
            'time' => $c->created_at->diffForHumans(),
        ])->toArray();
        $base['activities'] = $this->buildActivities($ticket);

        return $base;
    }

    /**
     * Build activity timeline for a ticket.
     *
     * @return array<int, array{text: string, time: string}>
     */
    protected function buildActivities(Ticket $ticket): array
    {
        $items = [];

        $items[] = [
            'text' => 'Ticket created',
            'time' => $ticket->created_at->diffForHumans(),
            'sort' => $ticket->created_at,
        ];

        if ($ticket->assigned_to && $ticket->assignedUser) {
            $items[] = [
                'text' => 'Assigned to '.$ticket->assignedUser->name,
                'time' => $ticket->created_at->diffForHumans(),
                'sort' => $ticket->created_at,
            ];
        }

        foreach ($ticket->comments as $comment) {
            $items[] = [
                'text' => $comment->user->name.' commented',
                'time' => $comment->created_at->diffForHumans(),
                'sort' => $comment->created_at,
            ];
        }

        if ($ticket->resolved_at) {
            $items[] = [
                'text' => 'Ticket resolved',
                'time' => $ticket->resolved_at->diffForHumans(),
                'sort' => $ticket->resolved_at,
            ];
        }

        usort($items, fn ($a, $b) => $b['sort']->timestamp <=> $a['sort']->timestamp);

        return array_map(fn ($i) => ['text' => $i['text'], 'time' => $i['time']], $items);
    }

    protected function computeSlaTracking(Ticket $ticket): array
    {
        $responseTargetHours = 4;
        $resolutionTargetHours = 24;
        $slaClass = $ticket->sla ?? 'compliant';

        $responseStatus = $slaClass;
        $responseText = 'Awaiting response';
        if ($ticket->first_response_at) {
            $mins = $ticket->created_at->diffInMinutes($ticket->first_response_at);
            $responseText = sprintf('Responded in %dh %dm (Target: %dh)', (int) floor($mins / 60), $mins % 60, $responseTargetHours);
            if ($mins <= $responseTargetHours * 60) {
                $responseStatus = 'compliant';
            } elseif ($mins <= $responseTargetHours * 60 * 1.5) {
                $responseStatus = 'warning';
            } else {
                $responseStatus = 'breached';
            }
        } else {
            $elapsedMins = $ticket->created_at->diffInMinutes(now());
            if ($elapsedMins > $responseTargetHours * 60) {
                $responseStatus = 'breached';
            } elseif ($elapsedMins > $responseTargetHours * 60 * 0.75) {
                $responseStatus = 'warning';
            }
        }

        $resolutionStatus = $slaClass;
        $resolutionText = 'In progress';
        if ($ticket->resolved_at) {
            $mins = $ticket->created_at->diffInMinutes($ticket->resolved_at);
            $resolutionText = sprintf('Resolved in %dh %dm (Target: %dh)', (int) floor($mins / 60), $mins % 60, $resolutionTargetHours);
            if ($mins <= $resolutionTargetHours * 60) {
                $resolutionStatus = 'compliant';
            } elseif ($mins <= $resolutionTargetHours * 60 * 1.5) {
                $resolutionStatus = 'warning';
            } else {
                $resolutionStatus = 'breached';
            }
        } elseif (! in_array($ticket->status, ['resolved', 'closed'])) {
            $elapsedMins = $ticket->created_at->diffInMinutes(now());
            $remainingMins = max(0, ($resolutionTargetHours * 60) - $elapsedMins);
            $resolutionText = sprintf('%dh %dm remaining (Target: %dh)', (int) floor($remainingMins / 60), $remainingMins % 60, $resolutionTargetHours);
            if ($remainingMins <= 0) {
                $resolutionStatus = 'breached';
            } elseif ($remainingMins <= $resolutionTargetHours * 60 * 0.25) {
                $resolutionStatus = 'warning';
            }
        }

        return [
            'response' => [
                'status' => $responseStatus,
                'text' => $responseText,
            ],
            'resolution' => [
                'status' => $resolutionStatus,
                'text' => $resolutionText,
            ],
        ];
    }

    protected function getInitials(string $name): string
    {
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr($words[count($words) - 1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    /**
     * Get ticket stats for the company.
     *
     * @return array<string, int>
     */
    protected function getStats(int $companyId): array
    {
        return [
            'open' => Ticket::where('company_id', $companyId)->where('status', 'open')->count(),
            'in_progress' => Ticket::where('company_id', $companyId)->where('status', 'in-progress')->count(),
            'pending' => Ticket::where('company_id', $companyId)->where('status', 'pending')->count(),
            'resolved' => Ticket::where('company_id', $companyId)->where('status', 'resolved')->count(),
            'closed' => Ticket::where('company_id', $companyId)->where('status', 'closed')->count(),
        ];
    }
}
