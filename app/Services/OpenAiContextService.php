<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\KnowledgeBaseArticle;
use App\Models\Payment;
use App\Models\PayrollReport;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\TimeTracking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OpenAiContextService
{
    /**
     * Infer context type from user message keywords. Returns context_type or null if no match.
     */
    public static function inferContextTypeFromMessage(string $message): ?string
    {
        $text = strtolower(trim($message));
        $mappings = [
            'billing' => ['billing', 'invoice', 'invoices', 'payment', 'payments', 'outstanding', 'revenue', 'subscription', 'subscriptions'],
            'project' => ['project', 'projects', 'milestone', 'milestones'],
            'quotation' => ['quotation', 'quotations', 'quote', 'quotes', 'proposal', 'proposals'],
            'ticket' => ['ticket', 'tickets', 'support ticket', 'support issue', 'open issue', 'resolved ticket'],
            'client' => ['client', 'clients', 'customer', 'customers', 'contact', 'contacts'],
            'time-tracking' => ['time tracking', 'time-tracking', 'timesheet', 'timesheets', 'hours worked', 'time in', 'time out', 'clock in', 'clock out'],
            'knowledge-base' => ['knowledge base', 'knowledge-base', 'kb', 'article', 'articles', 'documentation'],
            'payroll' => ['payroll', 'employee pay', 'salary', 'salaries', 'wages'],
        ];

        foreach ($mappings as $contextType => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $contextType;
                }
            }
        }

        return null;
    }

    /**
     * Load CRM context data for the given type and company.
     */
    public function getContextForCompany(int $companyId, ?string $contextType): string
    {
        return match ($contextType) {
            'billing' => $this->getBillingContext($companyId),
            'project' => $this->getProjectContext($companyId),
            'quotation' => $this->getQuotationContext($companyId),
            'ticket' => $this->getTicketContext($companyId),
            'client' => $this->getClientContext($companyId),
            'time-tracking' => $this->getTimeTrackingContext($companyId),
            'knowledge-base' => $this->getKnowledgeBaseContext($companyId),
            'payroll' => $this->getPayrollContext($companyId),
            default => $this->getGeneralContext($companyId),
        };
    }

    private function getBillingContext(int $companyId): string
    {
        $invoices = Invoice::where('company_id', $companyId)
            ->with('client:id,name')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($i) => [
                'number' => $i->invoice_number,
                'client' => $i->client?->name,
                'total' => (float) $i->total,
                'status' => $i->status,
                'due_date' => $i->due_date?->format('Y-m-d'),
            ]);

        $payments = Payment::where('company_id', $companyId)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'amount' => (float) $p->amount,
                'status' => $p->status,
                'method' => $p->payment_method,
                'date' => $p->paid_at?->format('Y-m-d') ?? $p->created_at->format('Y-m-d'),
            ]);

        $subscription = Subscription::where('company_id', $companyId)
            ->where('status', 'active')
            ->with('plan:id,name')
            ->latest()
            ->first();

        $outstanding = Invoice::where('company_id', $companyId)
            ->whereIn('status', ['sent', 'overdue', 'pending'])
            ->sum('total');

        $paidTotal = Payment::where('company_id', $companyId)->sum('amount');

        $lines = [
            "=== BILLING DATA (Company ID: {$companyId}) ===",
            "Outstanding invoices total: {$outstanding}",
            "Total payments received: {$paidTotal}",
            'Active subscription: '.($subscription ? "{$subscription->plan->name} ({$subscription->billing_cycle})" : 'None'),
            '',
            'Recent invoices:',
            json_encode($invoices->toArray(), JSON_PRETTY_PRINT),
            '',
            'Recent payments:',
            json_encode($payments->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getProjectContext(int $companyId): string
    {
        $projects = Project::where('company_id', $companyId)
            ->with('client:id,name')
            ->withCount('tasks')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'title' => $p->title,
                'client' => $p->client?->name,
                'status' => $p->status,
                'progress' => $p->progress.'%',
                'tasks_count' => $p->tasks_count,
                'deadline' => $p->deadline?->format('Y-m-d'),
            ]);

        $byStatus = Project::where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $lines = [
            "=== PROJECT DATA (Company ID: {$companyId}) ===",
            'Projects by status: '.json_encode($byStatus),
            '',
            'Recent projects:',
            json_encode($projects->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getQuotationContext(int $companyId): string
    {
        $quotations = Quotation::where('company_id', $companyId)
            ->with('client:id,name')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($q) => [
                'number' => $q->quotation_number,
                'client' => $q->client?->name,
                'total' => (float) $q->total,
                'status' => $q->status,
                'valid_until' => $q->valid_until?->format('Y-m-d'),
            ]);

        $byStatus = Quotation::where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $lines = [
            "=== QUOTATION DATA (Company ID: {$companyId}) ===",
            'Quotations by status: '.json_encode($byStatus),
            '',
            'Recent quotations:',
            json_encode($quotations->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getTicketContext(int $companyId): string
    {
        $tickets = Ticket::where('company_id', $companyId)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($t) => [
                'number' => $t->ticket_number,
                'subject' => $t->subject,
                'status' => $t->status,
                'priority' => $t->priority,
                'client' => $t->client_name ?? $t->client?->name,
                'resolved_at' => $t->resolved_at?->format('Y-m-d H:i'),
            ]);

        $byStatus = Ticket::where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $openCount = Ticket::where('company_id', $companyId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();

        $lines = [
            "=== TICKET DATA (Company ID: {$companyId}) ===",
            "Open tickets: {$openCount}",
            'Tickets by status: '.json_encode($byStatus),
            '',
            'Recent tickets:',
            json_encode($tickets->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getClientContext(int $companyId): string
    {
        $clients = Client::where('company_id', $companyId)
            ->withCount(['projects', 'invoices', 'quotations'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'contact' => $c->contact_person,
                'email' => $c->email,
                'status' => $c->status,
                'projects_count' => $c->projects_count,
                'invoices_count' => $c->invoices_count,
                'quotations_count' => $c->quotations_count,
            ]);

        $totalClients = Client::where('company_id', $companyId)->count();
        $byStatus = Client::where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $lines = [
            "=== CLIENT DATA (Company ID: {$companyId}) ===",
            "Total clients: {$totalClients}",
            'Clients by status: '.json_encode($byStatus),
            '',
            'Recent clients:',
            json_encode($clients->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getTimeTrackingContext(int $companyId): string
    {
        $records = TimeTracking::where('company_id', $companyId)
            ->with(['user:id,name'])
            ->where('date', '>=', now()->subDays(30))
            ->orderByDesc('date')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'user' => $r->user?->name,
                'date' => $r->date?->format('Y-m-d'),
                'time_in' => $r->time_in ? (is_string($r->time_in) ? substr($r->time_in, 0, 5) : $r->time_in->format('H:i')) : null,
                'time_out' => $r->time_out ? (is_string($r->time_out) ? substr($r->time_out, 0, 5) : $r->time_out->format('H:i')) : null,
                'hours_worked' => $r->hours_worked ? round($r->hours_worked / 3600, 1) : null,
                'status' => $r->status,
                'description' => $r->description,
            ]);

        $byUserRaw = TimeTracking::where('company_id', $companyId)
            ->where('date', '>=', now()->subDays(30))
            ->whereNotNull('hours_worked')
            ->selectRaw('user_id, SUM(hours_worked) as total_seconds')
            ->groupBy('user_id')
            ->get();

        $userNames = User::whereIn('id', $byUserRaw->pluck('user_id'))->pluck('name', 'id');
        $byUser = $byUserRaw->map(fn ($r) => [
            'user' => $userNames[$r->user_id] ?? 'Unknown',
            'total_hours' => round($r->total_seconds / 3600, 1),
        ]);

        $lines = [
            "=== TIME TRACKING DATA (Company ID: {$companyId}, Last 30 days, from time_tracking_records) ===",
            'Hours by user:',
            json_encode($byUser->toArray(), JSON_PRETTY_PRINT),
            '',
            'Recent records:',
            json_encode($records->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getPayrollContext(int $companyId): string
    {
        $reports = PayrollReport::where('company_id', $companyId)
            ->with(['items' => fn ($q) => $q->latest()->limit(50)])
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'period_start' => $r->period_start_date?->format('Y-m-d'),
                'period_end' => $r->period_end_date?->format('Y-m-d'),
                'status' => $r->status,
                'total_amount' => (float) $r->total_amount,
                'currency' => $r->currency ?? 'USD',
                'items_count' => $r->items->count(),
                'items' => $r->items->map(fn ($i) => [
                    'employee_name' => $i->employee_name,
                    'net_pay' => (float) $i->net_pay,
                    'gross_pay' => (float) $i->gross_pay,
                    'base_salary' => (float) $i->base_salary,
                    'hours_worked' => (float) $i->hours_worked,
                    'overtime_hours' => (float) $i->overtime_hours,
                    'allowances' => (float) $i->allowances,
                    'deductions' => (float) $i->deductions,
                    'wise_status' => $i->wise_status,
                ])->toArray(),
            ]);

        $totals = PayrollReport::where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total_amount) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->map(fn ($r) => ['count' => $r->count, 'total_amount' => (float) $r->total])
            ->toArray();

        $lines = [
            "=== PAYROLL DATA (Company ID: {$companyId}) ===",
            'Totals by status:',
            json_encode($totals, JSON_PRETTY_PRINT),
            '',
            'Recent payroll reports with items:',
            json_encode($reports->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getKnowledgeBaseContext(int $companyId): string
    {
        $articles = KnowledgeBaseArticle::where('company_id', $companyId)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($a) => [
                'title' => $a->title,
                'category' => $a->category,
                'views' => $a->views,
                'excerpt' => mb_substr(strip_tags($a->excerpt ?? $a->content ?? ''), 0, 150),
            ]);

        $byCategory = KnowledgeBaseArticle::where('company_id', $companyId)
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $lines = [
            "=== KNOWLEDGE BASE DATA (Company ID: {$companyId}) ===",
            'Articles by category: '.json_encode($byCategory),
            '',
            'Recent articles:',
            json_encode($articles->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getGeneralContext(int $companyId): string
    {
        $counts = [
            'invoices' => Invoice::where('company_id', $companyId)->count(),
            'projects' => Project::where('company_id', $companyId)->count(),
            'quotations' => Quotation::where('company_id', $companyId)->count(),
            'tickets' => Ticket::where('company_id', $companyId)->count(),
            'clients' => Client::where('company_id', $companyId)->count(),
        ];

        return "=== CRM OVERVIEW (Company ID: {$companyId}) ===\n".
            'Key counts: '.json_encode($counts, JSON_PRETTY_PRINT)."\n".
            'Use a specific summary action (Billing, Project, Ticket, etc.) for detailed data.';
    }
}
