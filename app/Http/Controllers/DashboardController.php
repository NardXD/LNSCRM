<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\ScreenRecording;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $companyId = $user?->company_id;

        $stats = $this->getStats($companyId);
        $recentQuotations = $this->getRecentQuotations($companyId);
        $recentTickets = $this->getRecentTickets($companyId);
        $activeProjects = $this->getActiveProjects($companyId);
        $upcomingDeadlines = $this->getUpcomingDeadlines($companyId);
        $recentActivity = $this->getRecentActivity($companyId);

        return view('dashboard.index', compact(
            'stats',
            'recentQuotations',
            'recentTickets',
            'activeProjects',
            'upcomingDeadlines',
            'recentActivity',
        ));
    }

    protected function getStats(?int $companyId): array
    {
        if (! $companyId) {
            return $this->emptyStats();
        }

        $today = Carbon::today();
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Total Revenue: sum of paid invoices (all time)
        $totalRevenue = (float) Invoice::where('company_id', $companyId)
            ->where('status', 'paid')
            ->sum('total');

        $revenueThisMonth = (float) Invoice::where('company_id', $companyId)
            ->where('status', 'paid')
            ->where('updated_at', '>=', $thisMonthStart)
            ->sum('total');

        $revenueLastMonth = (float) Invoice::where('company_id', $companyId)
            ->where('status', 'paid')
            ->whereBetween('updated_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total');

        $revenueChange = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100 : 0);

        // Total Clients
        $totalClients = Client::where('company_id', $companyId)->count();
        $clientsThisMonth = Client::where('company_id', $companyId)
            ->where('created_at', '>=', $thisMonthStart)
            ->count();

        // Active Projects
        $activeProjectsCount = Project::where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        $avgProgress = Project::where('company_id', $companyId)
            ->where('status', 'active')
            ->avg('progress');
        $avgProgress = $avgProgress !== null ? (int) round($avgProgress) : 0;

        // Open Tickets (open, in-progress, pending)
        $openTicketsCount = Ticket::where('company_id', $companyId)
            ->whereIn('status', ['open', 'in-progress', 'pending'])
            ->count();

        $inProgressTickets = Ticket::where('company_id', $companyId)
            ->where('status', 'in-progress')
            ->count();

        // Pending Invoices (sent, draft, overdue)
        $pendingInvoicesQuery = Invoice::where('company_id', $companyId)
            ->whereIn('status', ['sent', 'draft', 'overdue']);
        $pendingInvoicesAmount = (float) (clone $pendingInvoicesQuery)->sum('total');
        $pendingInvoicesCount = (clone $pendingInvoicesQuery)->count();

        // Active Employees
        $activeEmployeesCount = User::where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        // Recordings Today - screen recordings created today (viewable)
        $recordingsToday = ScreenRecording::where('company_id', $companyId)
            ->whereDate('date', $today)
            ->count();

        // On Leave Today
        $onLeaveToday = LeaveRequest::where('company_id', $companyId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();

        return [
            'total_revenue' => $totalRevenue,
            'revenue_change' => $revenueChange,
            'total_clients' => $totalClients,
            'clients_this_month' => $clientsThisMonth,
            'active_projects' => $activeProjectsCount,
            'avg_progress' => $avgProgress,
            'open_tickets' => $openTicketsCount,
            'in_progress_tickets' => $inProgressTickets,
            'pending_invoices_amount' => $pendingInvoicesAmount,
            'pending_invoices_count' => $pendingInvoicesCount,
            'active_employees' => $activeEmployeesCount,
            'recordings_today' => $recordingsToday,
            'on_leave_today' => $onLeaveToday,
        ];
    }

    protected function emptyStats(): array
    {
        return [
            'total_revenue' => 0,
            'revenue_change' => 0,
            'total_clients' => 0,
            'clients_this_month' => 0,
            'active_projects' => 0,
            'avg_progress' => 0,
            'open_tickets' => 0,
            'in_progress_tickets' => 0,
            'pending_invoices_amount' => 0,
            'pending_invoices_count' => 0,
            'active_employees' => 0,
            'recordings_today' => 0,
            'on_leave_today' => 0,
        ];
    }

    protected function getRecentQuotations(?int $companyId)
    {
        if (! $companyId) {
            return collect();
        }

        return Quotation::where('company_id', $companyId)
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get();
    }

    protected function getRecentTickets(?int $companyId)
    {
        if (! $companyId) {
            return collect();
        }

        return Ticket::where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get();
    }

    protected function getActiveProjects(?int $companyId)
    {
        if (! $companyId) {
            return collect();
        }

        return Project::where('company_id', $companyId)
            ->where('status', 'active')
            ->with('client')
            ->orderBy('deadline', 'asc')
            ->limit(4)
            ->get();
    }

    protected function getUpcomingDeadlines(?int $companyId)
    {
        if (! $companyId) {
            return collect();
        }

        $today = Carbon::today();
        $future = $today->copy()->addDays(60);

        $projectDeadlines = Project::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('deadline', [$today, $future])
            ->orderBy('deadline', 'asc')
            ->limit(4)
            ->get()
            ->map(function ($p) use ($today) {
                $days = $today->diffInDays($p->deadline, false);
                return [
                    'title' => "Project: {$p->title}",
                    'deadline' => $p->deadline->format('M d, Y'),
                    'days' => $days,
                    'priority' => $days <= 7 ? 'urgent' : ($days <= 14 ? 'high' : 'medium'),
                    'type' => 'project',
                ];
            });

        $invoiceDeadlines = Invoice::where('company_id', $companyId)
            ->whereIn('status', ['sent', 'draft'])
            ->whereBetween('due_date', [$today, $future])
            ->orderBy('due_date', 'asc')
            ->limit(2)
            ->get()
            ->map(function ($i) use ($today) {
                $days = $today->diffInDays($i->due_date, false);
                return [
                    'title' => "Invoice: {$i->invoice_number}",
                    'deadline' => $i->due_date->format('M d, Y'),
                    'days' => $days,
                    'priority' => $days <= 7 ? 'urgent' : ($days <= 14 ? 'high' : 'medium'),
                    'type' => 'invoice',
                ];
            });

        // Use base Collection to avoid Eloquent Collection's merge() which expects models
        return Collection::make($projectDeadlines->all())
            ->merge($invoiceDeadlines->all())
            ->sortBy('days')
            ->take(4)
            ->values();
    }

    protected function getRecentActivity(?int $companyId)
    {
        if (! $companyId) {
            return collect();
        }

        $items = collect();

        Quotation::where('company_id', $companyId)
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->limit(2)
            ->get()
            ->each(fn ($q) => $items->push([
                'type' => 'quotation',
                'text' => 'New quotation created for ' . ($q->client?->name ?? 'Unknown'),
                'amount' => '$' . number_format($q->total, 0),
                'at' => $q->created_at,
                'icon' => 'blue',
            ]));

        Ticket::where('company_id', $companyId)
            ->orderBy('updated_at', 'desc')
            ->limit(2)
            ->get()
            ->each(fn ($t) => $items->push([
                'type' => 'ticket',
                'text' => $t->status === 'resolved' || $t->status === 'closed'
                    ? "Ticket {$t->ticket_number} was resolved"
                    : "Ticket {$t->ticket_number} updated",
                'at' => $t->updated_at,
                'icon' => 'green',
            ]));

        Invoice::where('company_id', $companyId)
            ->where('status', 'paid')
            ->with('client')
            ->orderBy('updated_at', 'desc')
            ->limit(2)
            ->get()
            ->each(fn ($i) => $items->push([
                'type' => 'payment',
                'text' => 'Payment received from ' . ($i->client?->name ?? 'Unknown'),
                'amount' => '$' . number_format($i->total, 0),
                'at' => $i->updated_at,
                'icon' => 'green',
            ]));

        Project::where('company_id', $companyId)
            ->orderBy('updated_at', 'desc')
            ->limit(2)
            ->get()
            ->each(fn ($p) => $items->push([
                'type' => 'project',
                'text' => "Project \"{$p->title}\" updated",
                'at' => $p->updated_at,
                'icon' => 'purple',
            ]));

        Client::where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->limit(2)
            ->get()
            ->each(fn ($c) => $items->push([
                'type' => 'client',
                'text' => "New client \"{$c->name}\" added",
                'at' => $c->created_at,
                'icon' => 'blue',
            ]));

        return $items->sortByDesc(fn ($i) => $i['at'])->take(6)->values();
    }
}
