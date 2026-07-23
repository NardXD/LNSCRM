<?php

namespace App\Providers;

use App\Models\BillingSubscription;
use App\Services\SmtpSettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('live-view-heartbeat', function ($request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('live-view-signals', function ($request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        Route::model('subscription', BillingSubscription::class);

        // Apply SMTP settings from DB if configured
        try {
            (new SmtpSettingsService)->applyToConfig();
        } catch (\Exception) {
            // DB may not exist yet during migrations — silently skip
        }

        // Register Blade directive for formatting dates with company timezone
        Blade::directive('companyDate', function ($expression) {
            return "<?php echo \App\Services\TimezoneService::format($expression); ?>";
        });

        // Register Blade directive for getting current date/time in company timezone
        Blade::directive('companyNow', function ($format = "'Y-m-d H:i:s'") {
            return "<?php echo \App\Services\TimezoneService::now()->format($format); ?>";
        });

        // Share company settings to all views
        view()->composer('*', function ($view) {
            $company = null;
            $companySettings = [];
            $userPermissions = [];
            $companyModuleSlugs = null;
            $companyHasPnlFeature = true;

            if (auth()->check() && auth()->user()->company) {
                $company = auth()->user()->company;

                // Load user permissions (company-scoped)
                $user = auth()->user();
                if ($user && $user->company_id) {
                    // Only get permissions for the user's company
                    $userPermissions = $user->getPermissionSlugs();
                    if ($company->modules()->exists()) {
                        $companyHasPnlFeature = $company->hasModuleAccess('pnl');
                    }
                    // Load company's enabled module slugs for sidebar/RBAC filtering (skip for admins)
                    if (! $user->is_admin) {
                        $slugs = $company->modules()
                            ->wherePivot('is_enabled', true)
                            ->pluck('slug')
                            ->toArray();
                        // Only filter when company has explicit module config (backward compat: empty = show all)
                        $companyModuleSlugs = empty($slugs) ? null : $slugs;
                    }
                } else {
                    $userPermissions = [];
                }
            } elseif (app()->bound('company')) {
                $company = app('company');
            } elseif (request()->has('company')) {
                $company = request('company');
            }

            if ($company) {
                // Get timezone from companies table
                $companySettings = [
                    'timezone' => $company->timezone ?? 'America/New_York',
                    'date_format' => 'MM-DD-YYYY',
                    'currency' => 'USD',
                    'language' => 'en',
                ];

                // Load other company settings from system_settings
                $settings = \App\Models\SystemSetting::where('group', 'company_'.$company->id)
                    ->whereNotIn('key', ['timezone']) // Exclude timezone as it's now in companies table
                    ->pluck('value', 'key')
                    ->toArray();

                $companySettings = array_merge($companySettings, $settings);
            }

            $view->with('currentCompany', $company);
            $view->with('companySettings', $companySettings);
            $view->with('userPermissions', $userPermissions);
            $view->with('companyModuleSlugs', $companyModuleSlugs);
            $view->with('companyHasPnlFeature', $companyHasPnlFeature);
        });
    }
}
