<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Route to permission slug mapping
     */
    protected $routePermissionMap = [
        'dashboard' => 'view_dashboard',
        'time-tracking' => 'view_time_tracking',
        'user-management' => 'view_user_management',
        'employee-monitoring' => 'view_employee_monitoring',
        'phone-system' => 'view_phone_system',
        'payroll' => 'view_payroll',
        'payroll.sales-reps' => 'view_payroll_sales_rep_report',
        'pnl' => 'view_pnl',
        'wise-recipients' => 'view_wise_recipients',
        'project-management' => 'view_project_management',
        'team-management' => 'view_team_management',
        'leave-management' => 'view_leave_management',
        'messaging' => 'view_messaging',
        'inbox' => 'view_inbox',
        'viber' => 'view_viber',
        'whatsapp' => 'view_whatsapp',
        'facebook' => 'view_facebook',
        'billing' => 'view_billing',
        'client-management' => 'view_client_management',
        'leads' => 'view_client_management',
        'hiring-queue' => 'view_client_management',
        'tickets' => 'view_tickets',
        'knowledge-base' => 'view_knowledge_base',
        'integrations' => 'view_integrations',
        'quotation-builder' => 'view_quotation_builder',
        'quotation-item-templates' => 'view_quotation_builder',
        'contracts' => 'view_contracts',
        'calendar' => 'view_calendar',
        'email-tracking' => 'view_email_tracking',
        'openai' => 'view_ai_assistant',
        'change-password' => 'view_change_password',
        'admin.*' => 'view_admin_control',
    ];

    /**
     * Route name to module slug mapping (for company module access check)
     */
    protected $routeModuleMap = [
        'dashboard' => 'dashboard',
        'time-tracking' => 'time-tracking',
        'user-management' => 'user-management',
        'employee-monitoring' => 'employee-monitoring',
        'payroll' => 'payroll',
        'payroll.sales-reps' => 'payroll',
        'pnl' => 'pnl',
        'wise-recipients' => 'payroll',
        'project-management' => 'project-management',
        'team-management' => 'team-management',
        'leave-management' => 'leave-management',
        'messaging' => 'messaging',
        'inbox' => 'inbox',
        'viber' => 'viber',
        'whatsapp' => 'whatsapp',
        'facebook' => 'facebook',
        'billing' => 'billing',
        'client-management' => 'client-management',
        'leads' => 'client-management',
        'hiring-queue' => 'client-management',
        'tickets' => 'tickets',
        'knowledge-base' => 'knowledge-base',
        'integrations' => 'integrations',
        'quotation-builder' => 'quotation-builder',
        'quotation-item-templates' => 'quotation-builder',
        'contracts' => 'contracts',
        'calendar' => 'calendar',
        'email-tracking' => 'email-tracking',
        'openai' => 'openai',
        'change-password' => 'change-password',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = Auth::user();

        if (! $user) {
            // Return JSON response for AJAX/API requests
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                    'redirect' => route('login'),
                ], 401);
            }

            return redirect()->route('login');
        }

        // Get permission from parameter or route name
        $requiredPermission = $permission ?? $this->getPermissionFromRoute($request);

        if (! $requiredPermission) {
            // If no permission mapping, allow access (fail open - you may want to change this)
            return $next($request);
        }

        // Super admins (is_admin) implicitly have view_admin_control
        if ($requiredPermission === 'view_admin_control' && $user->is_admin) {
            return $next($request);
        }

        // Support OR logic: "perm1|perm2" means user needs at least one
        $permissions = array_map('trim', explode('|', $requiredPermission));
        $hasAnyPermission = collect($permissions)->contains(fn ($p) => $user->hasPermission($p));

        if (! $hasAnyPermission) {
            return $this->denyAccess($request, 'You do not have access to this module. Please contact your administrator.');
        }

        $moduleSlug = $this->getModuleFromRoute($request);

        // P&L routes: when the company has any module assignments, require the pnl module (even for is_admin users with a company).
        if ($moduleSlug === 'pnl' && $user->company_id) {
            $company = $user->company;
            if ($company && $company->modules()->exists() && ! $company->hasModuleAccess('pnl')) {
                return $this->denyAccess($request, 'Your company does not have access to this module. Please contact your administrator.');
            }
        }

        // Check company module access (skip for admins)
        if (! $user->is_admin && $user->company_id) {
            if ($moduleSlug !== null) {
                $company = $user->company;
                $companyModuleSlugs = $company ? $company->modules()->wherePivot('is_enabled', true)->pluck('slug')->toArray() : [];
                if (! empty($companyModuleSlugs) && ! in_array($moduleSlug, $companyModuleSlugs)) {
                    return $this->denyAccess($request, 'Your company does not have access to this module. Please contact your administrator.');
                }
            }
        }

        return $next($request);
    }

    /**
     * Get permission slug from route name
     */
    protected function getPermissionFromRoute(Request $request): ?string
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return null;
        }

        // Check exact match first
        if (isset($this->routePermissionMap[$routeName])) {
            return $this->routePermissionMap[$routeName];
        }

        // Check wildcard patterns
        foreach ($this->routePermissionMap as $pattern => $permission) {
            if (str_contains($pattern, '*')) {
                $regex = '/^'.str_replace('*', '.*', $pattern).'$/';
                if (preg_match($regex, $routeName)) {
                    return $permission;
                }
            }
        }

        return null;
    }

    /**
     * Get module slug from route name for company module check.
     */
    protected function getModuleFromRoute(Request $request): ?string
    {
        $routeName = $request->route()?->getName();
        if (! $routeName) {
            return null;
        }

        if (isset($this->routeModuleMap[$routeName])) {
            return $this->routeModuleMap[$routeName];
        }

        foreach ($this->routeModuleMap as $pattern => $module) {
            if (str_contains($pattern, '*')) {
                continue;
            }
            if (str_starts_with($routeName, str_replace('.*', '', $pattern))) {
                return $module;
            }
        }

        // Check api.quotation-builder.* etc
        if (str_starts_with($routeName, 'api.quotation-builder')) {
            return 'quotation-builder';
        }
        if (str_starts_with($routeName, 'api.contracts')) {
            return 'contracts';
        }
        if (str_starts_with($routeName, 'api.tickets')) {
            return 'tickets';
        }
        if (str_starts_with($routeName, 'api.knowledge-base')) {
            return 'knowledge-base';
        }
        if (str_starts_with($routeName, 'api.billing')) {
            return 'billing';
        }
        if (str_starts_with($routeName, 'api.calendar')) {
            return 'calendar';
        }
        if (str_starts_with($routeName, 'api.openai')) {
            return 'openai';
        }
        if (str_starts_with($routeName, 'api.hiring-queue')) {
            return 'client-management';
        }
        if (str_starts_with($routeName, 'api.leads')) {
            return 'client-management';
        }
        if ($routeName === 'api.payroll.pnl-invoice-basis') {
            return 'pnl';
        }
        if (str_starts_with($routeName, 'api.payroll')) {
            return 'payroll';
        }

        return null;
    }

    /**
     * Return access denied response.
     */
    protected function denyAccess(Request $request, string $message): Response
    {
        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()->route('dashboard')
            ->with('error', $message);
    }
}
