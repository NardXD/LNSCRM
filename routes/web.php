<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\ApiKeyManagementController;
use App\Http\Controllers\Admin\BillingManagementController;
use App\Http\Controllers\Admin\CompanyAccessControlController;
use App\Http\Controllers\Admin\CompanyManagementController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\ScreenRecordingManagementController;
use App\Http\Controllers\Admin\SmtpSettingsController;
use App\Http\Controllers\Admin\SupportOverrideController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\ClientManagementController;
use App\Http\Controllers\EmployeeMonitoringController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\TimeTrackingController;
use App\Http\Controllers\Twilio\CallController;
use App\Http\Controllers\Twilio\PhoneSystemController;
use App\Models\Company;
use App\Models\TwilioIntegration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// MCP Server (Model Context Protocol) - for Claude AI integration
// Requires X-API-Key header. Use: php artisan mcp:create-key --company=1
Route::match(['get', 'post'], '/mcp', [\App\Http\Controllers\McpServerController::class, 'handle'])
    ->middleware('mcp.api_key')
    ->name('mcp');

Route::get('/', [\App\Http\Controllers\CompanyLandingController::class, 'index'])->name('landing');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');

Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout.get');

Route::post('/leave-impersonation', [ImpersonationController::class, 'leave'])
    ->middleware('auth')
    ->name('leave-impersonation');

Route::get('/auth/impersonate', [ImpersonationController::class, 'accept'])->name('auth.impersonate');

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.submit');

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request');

Route::get('/start', [\App\Http\Controllers\CompanyLandingController::class, 'hiringAssistant'])->name('landing.start');

Route::post('/api/hiring-assistant/chat', [\App\Http\Controllers\HiringAssistantController::class, 'chat'])->name('api.hiring-assistant.chat');
Route::post('/api/hiring-queue/save-from-assistant', [\App\Http\Controllers\HiringQueueController::class, 'storeFromAssistant'])->name('api.hiring-queue.save-from-assistant');

Route::post('/forgot-password', function () {
    // Handle password reset email logic here
})->name('password.email');

Route::post('/webhooks/stripe/company/{company}', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])->name('webhooks.stripe');
Route::post('/webhooks/wise/company/{company}', [\App\Http\Controllers\WiseWebhookController::class, 'handle'])->name('webhooks.wise')->where('company', '[0-9]+');

// Twilio Webhook Routes (Public - no authentication required)
// These routes must be accessible to Twilio's servers without authentication
// CSRF protection is excluded in bootstrap/app.php
Route::prefix('twilio')->group(function () {
    // TwiML webhook endpoint - returns XML instructions for Twilio calls
    // Twilio can call this with either GET or POST, so we handle both
    Route::match(['get', 'post'], '/voice', function (Request $request) {
        // Log immediately to verify webhook is being called
        try {
            \Illuminate\Support\Facades\Log::info('=== TWILIO VOICE WEBHOOK HIT ===', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Even if logging fails, continue
        }

        // Log all incoming parameters for debugging
        \Illuminate\Support\Facades\Log::info('Twilio voice webhook received', [
            'all_params' => $request->all(),
            'To' => $request->input('To'),
            'From' => $request->input('From'),
            'CallSid' => $request->input('CallSid'),
            'Called' => $request->input('Called'),
            'Caller' => $request->input('Caller'),
            'Direction' => $request->input('Direction'),
        ]);

        $called = $request->input('Called') ?: $request->input('To');
        $caller = $request->input('Caller') ?: $request->input('From');
        $direction = $request->input('Direction', 'outbound-api');
        $fromClient = $request->input('From'); // For browser calls, this will be the client identifier

        // Check if this is an INBOUND call (someone calling your Twilio number)
        $isInbound = $direction === 'inbound';

        // Check if this is a browser-based OUTBOUND call (has FromClient or From is a user ID)
        $isBrowserCall = $request->has('FromClient') || (is_numeric($fromClient) && strlen($fromClient) < 10);

        $recordingCallback = htmlspecialchars(route('twilio.recording-callback'), ENT_XML1);
        $dialRecordAttrs = 'record="record-from-answer" recordingStatusCallback="'.$recordingCallback.'" recordingStatusCallbackEvent="completed" recordingTrack="both"';

        // Build TwiML response
        $twiml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $twiml .= '<Response>'."\n";

        if ($isInbound) {
            // INBOUND CALL: Someone is calling your Twilio phone number
            // Route to all registered browser clients (users) in the company
            // Get company from the called number or use a default approach

            try {
                // Try to find company by phone number
                $company = Company::whereHas('users', function ($query) use ($called) {
                    $query->where('twilio_number', $called);
                })->first();

                // If not found, try to get from TwilioIntegration
                if (! $company) {
                    $integration = TwilioIntegration::where('is_active', true)->first();
                    $company = $integration ? $integration->company : null;
                }

                if ($company) {
                    // Get ONLY the user(s) whose twilio_number matches the called number
                    // This ensures only the specific user assigned to that number receives the call
                    $users = $company->users()
                        ->where('twilio_number', $called)
                        ->get();

                    \Illuminate\Support\Facades\Log::info('Inbound call - routing to specific user(s)', [
                        'company_id' => $company->id,
                        'called' => $called,
                        'caller' => $caller,
                        'user_count' => $users->count(),
                        'user_ids' => $users->pluck('id')->toArray(),
                        'user_emails' => $users->pluck('email')->toArray(),
                    ]);

                    if ($users->count() > 0) {
                        // Brief notice for compliance before connecting the agent
                        $twiml .= '    <Say voice="alice">This call may be recorded.</Say>'."\n";
                        // Dial only the user(s) with matching twilio_number
                        $twiml .= '    <Dial timeout="30" answerOnMedia="true" '.$dialRecordAttrs.' action="'.route('twilio.status-callback').'">'."\n";

                        foreach ($users as $user) {
                            // Dial each user as a Client (browser client identity is user ID)
                            // IMPORTANT: The identity in the capability token MUST match the user ID
                            $twiml .= '        <Client>'.htmlspecialchars((string) $user->id, ENT_XML1).'</Client>'."\n";
                            \Illuminate\Support\Facades\Log::info('Dialing client', [
                                'user_id' => $user->id,
                                'user_email' => $user->email,
                                'twilio_number' => $user->twilio_number,
                                'called_number' => $called,
                            ]);
                        }

                        $twiml .= '    </Dial>'."\n";
                    } else {
                        // No user found with matching twilio_number
                        \Illuminate\Support\Facades\Log::warning('Inbound call - no user found with matching twilio_number', [
                            'called' => $called,
                            'company_id' => $company->id,
                        ]);
                        $twiml .= '    <Say voice="alice">No agent is available for this number. Please try again later.</Say>'."\n";
                    }
                } else {
                    // Company not found - try to find user by twilio_number directly (fallback)
                    $users = \App\Models\User::where('twilio_number', $called)->get();

                    \Illuminate\Support\Facades\Log::info('Inbound call - company not found, searching by twilio_number directly', [
                        'called' => $called,
                        'user_count' => $users->count(),
                        'user_ids' => $users->pluck('id')->toArray(),
                    ]);

                    if ($users->count() > 0) {
                        $twiml .= '    <Say voice="alice">This call may be recorded.</Say>'."\n";
                        $twiml .= '    <Dial timeout="30" answerOnMedia="true" '.$dialRecordAttrs.' action="'.route('twilio.status-callback').'">'."\n";
                        foreach ($users as $user) {
                            $twiml .= '        <Client>'.htmlspecialchars((string) $user->id, ENT_XML1).'</Client>'."\n";
                            \Illuminate\Support\Facades\Log::info('Dialing client (fallback)', [
                                'user_id' => $user->id,
                                'user_email' => $user->email,
                                'twilio_number' => $user->twilio_number,
                            ]);
                        }
                        $twiml .= '    </Dial>'."\n";
                    } else {
                        \Illuminate\Support\Facades\Log::warning('Inbound call - no user found with matching twilio_number (fallback)', [
                            'called' => $called,
                        ]);
                        $twiml .= '    <Say voice="alice">No agent is available for this number. Please try again later.</Say>'."\n";
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error handling inbound call', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $twiml .= '    <Say voice="alice">An error occurred. Please try again later.</Say>'."\n";
            }
        } elseif ($isBrowserCall && $called) {
            // Browser-based OUTBOUND call: dial the destination number
            // The browser client is already connected, just need to dial the destination
            $twiml .= '    <Dial timeout="60" answerOnMedia="true" '.$dialRecordAttrs.' callerId="'.htmlspecialchars($caller, ENT_XML1).'">'."\n";
            $twiml .= '        <Number>'.htmlspecialchars($called, ENT_XML1).'</Number>'."\n";
            $twiml .= '    </Dial>'."\n";
        } elseif ($called) {
            // API-based OUTBOUND call: dial the destination number to establish audio bridge
            $twiml .= '    <Dial timeout="60" answerOnMedia="true" '.$dialRecordAttrs.'>'."\n";
            $twiml .= '        <Number>'.htmlspecialchars($called, ENT_XML1).'</Number>'."\n";
            $twiml .= '    </Dial>'."\n";
        } else {
            // Fallback if no number provided
            $twiml .= '    <Say voice="alice">Call connected.</Say>'."\n";
        }

        $twiml .= '</Response>';

        \Illuminate\Support\Facades\Log::info('Twilio voice webhook response', [
            'twiml' => $twiml,
            'called' => $called,
            'caller' => $caller,
            'direction' => $direction,
            'is_inbound' => $isInbound,
        ]);

        return response($twiml, 200)
            ->header('Content-Type', 'text/xml');
    })->name('twilio.voice');

    // Status callback endpoint - receives call status updates from Twilio
    Route::post('/status-callback', [CallController::class, 'statusCallback'])->name('twilio.status-callback');
    Route::match(['get', 'post'], '/recording-callback', [CallController::class, 'recordingStatusCallback'])->name('twilio.recording-callback');
    Route::post('/sms-webhook', [PhoneSystemController::class, 'smsWebhook'])->name('twilio.sms-webhook');
    Route::post('/sms-status', [PhoneSystemController::class, 'smsStatus'])->name('twilio.sms-status');
});

// Viber (Twilio Messaging) inbound webhook (public, CSRF-exempt)
Route::post('/webhooks/viber/{webhookKey}', [\App\Http\Controllers\ViberController::class, 'webhook'])
    ->name('webhooks.viber');

// WhatsApp (Twilio Messaging) inbound webhook (public, CSRF-exempt)
Route::post('/webhooks/whatsapp/{webhookKey}', [\App\Http\Controllers\WhatsAppController::class, 'webhook'])
    ->name('webhooks.whatsapp');

// Facebook / Instagram Messenger webhooks (public, CSRF-exempt; GET = verify, POST = events)
Route::match(['get', 'post'], '/webhooks/facebook/{webhookKey}', [\App\Http\Controllers\FacebookController::class, 'webhook'])
    ->name('webhooks.facebook');

// Public contract signing routes (no auth required)
Route::get('/contracts/sign/{token}', [\App\Http\Controllers\ContractController::class, 'showSigningPage'])->name('contracts.sign');
Route::post('/contracts/sign/{token}', [\App\Http\Controllers\ContractController::class, 'submitSignature'])->name('contracts.sign.submit');

// Protected Dashboard Routes - Require Authentication
Route::middleware(['auth', 'company.active'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->middleware('permission:view_dashboard')->name('dashboard');

    Route::get('/time-tracking', [TimeTrackingController::class, 'index'])->middleware('permission:view_time_tracking')->name('time-tracking');

    Route::get('/client-management', [ClientManagementController::class, 'index'])->middleware('permission:view_client_management')->name('client-management');

    Route::get('/hiring-queue', [\App\Http\Controllers\HiringQueueController::class, 'index'])->middleware('permission:view_client_management')->name('hiring-queue');
    Route::get('/api/hiring-queue', [\App\Http\Controllers\HiringQueueController::class, 'getItems'])->middleware('permission:view_client_management')->name('api.hiring-queue.index');
    Route::post('/api/hiring-queue', [\App\Http\Controllers\HiringQueueController::class, 'store'])->middleware('permission:view_client_management')->name('api.hiring-queue.store');
    Route::get('/api/hiring-queue/{item}', [\App\Http\Controllers\HiringQueueController::class, 'show'])->middleware('permission:view_client_management')->name('api.hiring-queue.show');
    Route::patch('/api/hiring-queue/{item}', [\App\Http\Controllers\HiringQueueController::class, 'update'])->middleware('permission:view_client_management')->name('api.hiring-queue.update');
    Route::get('/api/hiring-queue/{item}/comments', [\App\Http\Controllers\HiringQueueController::class, 'getComments'])->middleware('permission:view_client_management')->name('api.hiring-queue.comments');
    Route::post('/api/hiring-queue/{item}/comments', [\App\Http\Controllers\HiringQueueController::class, 'storeComment'])->middleware('permission:view_client_management')->name('api.hiring-queue.comments.store');
    Route::delete('/api/hiring-queue/{item}/comments/{comment}', [\App\Http\Controllers\HiringQueueController::class, 'destroyComment'])->middleware('permission:view_client_management')->name('api.hiring-queue.comments.destroy');
    Route::get('/api/hiring-queue/{item}/pdf', [\App\Http\Controllers\HiringQueueController::class, 'pdf'])->middleware('permission:view_client_management')->name('api.hiring-queue.pdf');
    Route::patch('/api/hiring-queue/{item}/status', [\App\Http\Controllers\HiringQueueController::class, 'updateStatus'])->middleware('permission:view_client_management')->name('api.hiring-queue.status.update');
    Route::get('/api/hiring-queue/{item}/candidates', [\App\Http\Controllers\HiringQueueController::class, 'getCandidates'])->middleware('permission:view_client_management')->name('api.hiring-queue.candidates');
    Route::post('/api/hiring-queue/{item}/candidates', [\App\Http\Controllers\HiringQueueController::class, 'storeCandidate'])->middleware('permission:view_client_management')->name('api.hiring-queue.candidates.store');
    Route::patch('/api/hiring-queue/{item}/candidates/{candidate}', [\App\Http\Controllers\HiringQueueController::class, 'updateCandidate'])->middleware('permission:view_client_management')->name('api.hiring-queue.candidates.update');
    Route::patch('/api/hiring-queue/{item}/candidates/{candidate}/status', [\App\Http\Controllers\HiringQueueController::class, 'updateCandidateStatus'])->middleware('permission:view_client_management')->name('api.hiring-queue.candidates.status.update');

    // Time Tracking API Routes
    Route::prefix('api/time-tracking')->group(function () {
        Route::post('/start-recording', [TimeTrackingController::class, 'startRecording'])->name('api.time-tracking.start-recording');
        Route::post('/stop-recording', [TimeTrackingController::class, 'stopRecording'])->name('api.time-tracking.stop-recording');
        Route::post('/stop-recording-session', [TimeTrackingController::class, 'stopRecordingSession'])->name('api.time-tracking.stop-recording-session');
        Route::post('/upload-recording', [TimeTrackingController::class, 'uploadRecording'])->name('api.time-tracking.upload-recording');
        Route::post('/time-in', [TimeTrackingController::class, 'timeIn'])->name('api.time-tracking.time-in');
        Route::post('/time-out', [TimeTrackingController::class, 'timeOut'])->name('api.time-tracking.time-out');
        Route::get('/records', [TimeTrackingController::class, 'getRecords'])->name('api.time-tracking.records');
        Route::get('/active-record', [TimeTrackingController::class, 'getActiveRecord'])->name('api.time-tracking.active-record');
        Route::get('/today-recordings', [TimeTrackingController::class, 'getTodayRecordings'])->name('api.time-tracking.today-recordings');
        Route::get('/recording/{id}/view', [TimeTrackingController::class, 'viewRecording'])->name('api.time-tracking.view-recording');
    });

    Route::get('/user-management', [\App\Http\Controllers\UserManagementController::class, 'index'])->middleware('permission:view_user_management')->name('user-management');

    // User Management API Routes
    Route::prefix('api/user-management')->group(function () {
        Route::get('/employees', [\App\Http\Controllers\UserManagementController::class, 'getEmployees'])->name('api.user-management.employees');
        Route::get('/twilio-number-options', [\App\Http\Controllers\UserManagementController::class, 'getTwilioNumberOptions'])->name('api.user-management.twilio-number-options');
        Route::get('/employees/{employee}/clients', [\App\Http\Controllers\UserManagementController::class, 'getEmployeeClients'])->name('api.user-management.employees.clients');
        Route::get('/clients', [\App\Http\Controllers\UserManagementController::class, 'getClientsList'])->name('api.user-management.clients');
        Route::post('/employees', [\App\Http\Controllers\UserManagementController::class, 'storeEmployee'])->name('api.user-management.employees.store');
        Route::match(['PUT', 'POST'], '/employees/{employee}', [\App\Http\Controllers\UserManagementController::class, 'updateEmployee'])->name('api.user-management.employees.update');
        Route::delete('/employees/{employee}', [\App\Http\Controllers\UserManagementController::class, 'destroyEmployee'])->name('api.user-management.employees.destroy');

        Route::get('/sales-reps', [\App\Http\Controllers\UserManagementController::class, 'getSalesReps'])->name('api.user-management.sales-reps.index');
        Route::post('/sales-reps', [\App\Http\Controllers\UserManagementController::class, 'storeSalesRep'])->name('api.user-management.sales-reps.store');
        Route::put('/sales-reps/{salesRep}', [\App\Http\Controllers\UserManagementController::class, 'updateSalesRep'])->name('api.user-management.sales-reps.update');
        Route::delete('/sales-reps/{salesRep}', [\App\Http\Controllers\UserManagementController::class, 'destroySalesRep'])->name('api.user-management.sales-reps.destroy');

        Route::get('/departments', [\App\Http\Controllers\UserManagementController::class, 'getDepartments'])->name('api.user-management.departments');
        Route::post('/departments', [\App\Http\Controllers\UserManagementController::class, 'storeDepartment'])->name('api.user-management.departments.store');
        Route::match(['PUT', 'POST'], '/departments/{department}', [\App\Http\Controllers\UserManagementController::class, 'updateDepartment'])->name('api.user-management.departments.update');
        Route::delete('/departments/{department}', [\App\Http\Controllers\UserManagementController::class, 'destroyDepartment'])->name('api.user-management.departments.destroy');

        Route::get('/roles', [\App\Http\Controllers\UserManagementController::class, 'getRoles'])->name('api.user-management.roles');
        Route::post('/roles', [\App\Http\Controllers\UserManagementController::class, 'storeRole'])->name('api.user-management.roles.store');
        Route::put('/roles/{role}', [\App\Http\Controllers\UserManagementController::class, 'updateRole'])->name('api.user-management.roles.update');
        Route::delete('/roles/{role}', [\App\Http\Controllers\UserManagementController::class, 'destroyRole'])->name('api.user-management.roles.destroy');

        Route::get('/permissions', [\App\Http\Controllers\UserManagementController::class, 'getPermissions'])->name('api.user-management.permissions');
        Route::get('/roles/{role}/permissions', [\App\Http\Controllers\UserManagementController::class, 'getRolePermissions'])->name('api.user-management.roles.permissions.get');
        Route::put('/roles/{role}/permissions', [\App\Http\Controllers\UserManagementController::class, 'updateRolePermissions'])->name('api.user-management.roles.permissions.update');

        Route::post('/company/settings', [\App\Http\Controllers\UserManagementController::class, 'updateCompanySettings'])->name('api.user-management.company.settings.update');
    });

    // Client Management API Routes
    Route::prefix('api/client-management')->group(function () {
        Route::get('/clients', [ClientManagementController::class, 'getClients'])->name('api.client-management.clients');
        Route::get('/clients/search', [ClientManagementController::class, 'searchClients'])->name('api.client-management.clients.search');
        Route::get('/stats', [ClientManagementController::class, 'getStats'])->name('api.client-management.stats');
        Route::post('/clients', [ClientManagementController::class, 'store'])->name('api.client-management.clients.store');
        Route::get('/clients/{client}', [ClientManagementController::class, 'show'])->name('api.client-management.clients.show');
        Route::match(['PUT', 'PATCH'], '/clients/{client}', [ClientManagementController::class, 'update'])->name('api.client-management.clients.update');
        Route::delete('/clients/{client}', [ClientManagementController::class, 'destroy'])->name('api.client-management.clients.destroy');
        Route::get('/export', [ClientManagementController::class, 'export'])->name('api.client-management.export');

        // Employee assignment routes
        Route::get('/clients/{client}/available-employees', [ClientManagementController::class, 'getAvailableEmployees'])->name('api.client-management.clients.available-employees');
        Route::post('/clients/{client}/assign-employees', [ClientManagementController::class, 'assignEmployees'])->name('api.client-management.clients.assign-employees');
        Route::post('/clients/{client}/remove-employee', [ClientManagementController::class, 'removeEmployee'])->name('api.client-management.clients.remove-employee');

        Route::get('/clients/{client}/projects', [ClientManagementController::class, 'getClientProjects'])->name('api.client-management.clients.projects');
        Route::get('/clients/{client}/contracts', [ClientManagementController::class, 'getClientContracts'])->name('api.client-management.clients.contracts');
        Route::get('/clients/{client}/contracts/{contract}/pdf', [ClientManagementController::class, 'downloadClientContractPdf'])->name('api.client-management.clients.contracts.pdf');

        // Notes routes
        Route::get('/clients/{client}/notes', [ClientManagementController::class, 'getClientNotes'])->name('api.client-management.clients.notes');
        Route::post('/clients/{client}/notes', [ClientManagementController::class, 'storeClientNote'])->name('api.client-management.clients.notes.store');
        Route::delete('/clients/{client}/notes/{note}', [ClientManagementController::class, 'deleteClientNote'])->name('api.client-management.clients.notes.delete');

        // Client users (portal users) routes
        Route::get('/clients/{client}/users', [ClientManagementController::class, 'getClientUsers'])->name('api.client-management.clients.users');
        Route::post('/clients/{client}/users', [ClientManagementController::class, 'storeClientUser'])->name('api.client-management.clients.users.store');
        Route::put('/clients/{client}/users/{clientUser}', [ClientManagementController::class, 'updateClientUser'])->name('api.client-management.clients.users.update');
        Route::delete('/clients/{client}/users/{clientUser}', [ClientManagementController::class, 'deleteClientUser'])->name('api.client-management.clients.users.delete');
    });

    Route::get('/employee-monitoring', [EmployeeMonitoringController::class, 'index'])->middleware('permission:view_employee_monitoring')->name('employee-monitoring');

    // Employee Monitoring API Routes
    Route::prefix('api/live-view')->group(function () {
        Route::get('/ice-config', [\App\Http\Controllers\LiveViewController::class, 'iceConfig'])->name('api.live-view.ice-config');
        Route::post('/heartbeat', [\App\Http\Controllers\LiveViewController::class, 'heartbeat'])->middleware('throttle:live-view-heartbeat')->name('api.live-view.heartbeat');
        Route::post('/heartbeat/clear', [\App\Http\Controllers\LiveViewController::class, 'clearHeartbeat'])->name('api.live-view.heartbeat.clear');
        Route::get('/signals', [\App\Http\Controllers\LiveViewController::class, 'pullSignals'])->middleware('throttle:live-view-signals')->name('api.live-view.signals.pull');
        Route::post('/signals', [\App\Http\Controllers\LiveViewController::class, 'sendSignal'])->middleware('throttle:live-view-signals')->name('api.live-view.signals.send');
        Route::get('/sessions', [\App\Http\Controllers\LiveViewController::class, 'listSessions'])->middleware('permission:view_live_screen')->name('api.live-view.sessions.index');
        Route::post('/sessions', [\App\Http\Controllers\LiveViewController::class, 'startSession'])->middleware('permission:view_live_screen')->name('api.live-view.sessions.start');
        Route::get('/sessions/{liveViewSession}', [\App\Http\Controllers\LiveViewController::class, 'getSession'])->name('api.live-view.sessions.show');
        Route::post('/sessions/{liveViewSession}/end', [\App\Http\Controllers\LiveViewController::class, 'endSession'])->name('api.live-view.sessions.end');
        Route::get('/sessions/{liveViewSession}/messages', [\App\Http\Controllers\LiveViewController::class, 'listMessages'])->name('api.live-view.sessions.messages.index');
        Route::post('/sessions/{liveViewSession}/messages', [\App\Http\Controllers\LiveViewController::class, 'sendMessage'])->name('api.live-view.sessions.messages.send');
    });

    Route::prefix('api/employee-monitoring')->middleware('permission:view_employee_monitoring')->group(function () {
        Route::get('/employees', [EmployeeMonitoringController::class, 'getEmployees'])->name('api.employee-monitoring.employees');
        Route::get('/employees/{employeeId}/recordings', [EmployeeMonitoringController::class, 'getEmployeeRecordings'])->name('api.employee-monitoring.employee-recordings');
        Route::get('/sync-health', [EmployeeMonitoringController::class, 'getSyncHealth'])->name('api.employee-monitoring.sync-health');
        Route::get('/recording/{id}/view', [EmployeeMonitoringController::class, 'viewRecording'])->name('api.employee-monitoring.view-recording');
        Route::delete('/recording/{id}', [EmployeeMonitoringController::class, 'deleteRecording'])->name('api.employee-monitoring.delete-recording');
        Route::delete('/recordings', [EmployeeMonitoringController::class, 'deleteRecordings'])->name('api.employee-monitoring.delete-recordings');
    });

    // Twilio Call Routes (Protected - require auth)
    Route::prefix('twilio')->middleware('permission:view_phone_system')->group(function () {
        Route::get('/test-call', [CallController::class, 'call'])->name('twilio.test-call');
        Route::get('/call', [CallController::class, 'index'])->name('twilio.call');
        Route::get('/call-status', [CallController::class, 'callStatus'])->name('twilio.call-status');
        Route::post('/hangup', [CallController::class, 'hangup'])->name('twilio.hangup');
        Route::post('/send-digits', [CallController::class, 'sendDigits'])->name('twilio.send-digits');
        Route::get('/capability-token', [CallController::class, 'getCapabilityToken'])->name('twilio.capability-token');

        Route::get('/call-history', [PhoneSystemController::class, 'callHistory'])
            ->middleware('permission:view_call_history')
            ->name('twilio.call-history');
        Route::get('/call-history/{phoneCallLog}/recording', [PhoneSystemController::class, 'streamRecording'])
            ->middleware('permission:view_call_history')
            ->name('twilio.call-recording');

        Route::middleware('permission:manage_phone_contacts')->group(function () {
            Route::get('/contacts', [PhoneSystemController::class, 'contacts'])->name('twilio.contacts');
            Route::post('/contacts', [PhoneSystemController::class, 'storeContact'])->name('twilio.contacts.store');
            Route::put('/contacts/{phoneContact}', [PhoneSystemController::class, 'updateContact'])->name('twilio.contacts.update');
            Route::delete('/contacts/{phoneContact}', [PhoneSystemController::class, 'destroyContact'])->name('twilio.contacts.destroy');
        });

        Route::middleware('permission:view_sms')->group(function () {
            Route::get('/sms/messages', [PhoneSystemController::class, 'smsMessages'])->name('twilio.sms.messages');
            Route::get('/sms/threads', [PhoneSystemController::class, 'smsThreads'])->name('twilio.sms.threads');
        });

        Route::post('/sms/send', [PhoneSystemController::class, 'sendSms'])
            ->middleware('permission:send_sms')
            ->name('twilio.sms.send');

        Route::middleware('permission:manage_twilio_numbers')->prefix('numbers')->group(function () {
            Route::get('/', [PhoneSystemController::class, 'numbers'])->name('twilio.numbers');
            Route::get('/search', [PhoneSystemController::class, 'searchAvailableNumbers'])->name('twilio.numbers.search');
            Route::get('/employees', [PhoneSystemController::class, 'employeesForAssignment'])->name('twilio.numbers.employees');
            Route::post('/purchase', [PhoneSystemController::class, 'purchaseNumber'])->name('twilio.numbers.purchase');
            Route::post('/sync', [PhoneSystemController::class, 'syncNumbers'])->name('twilio.numbers.sync');
            Route::post('/{twilioPhoneNumber}/assign', [PhoneSystemController::class, 'assignNumber'])->name('twilio.numbers.assign');
            Route::post('/{twilioPhoneNumber}/unassign', [PhoneSystemController::class, 'unassignNumber'])->name('twilio.numbers.unassign');
        });
    });

    Route::get('/payroll', [PayrollController::class, 'index'])->middleware('permission:view_payroll')->name('payroll');
    Route::get('/payroll/sales-reps', [PayrollController::class, 'salesRepSummary'])->middleware('permission:view_payroll_sales_rep_report')->name('payroll.sales-reps');
    Route::get('/pnl', [PayrollController::class, 'pnl'])->middleware('permission:view_pnl')->name('pnl');

    // Payroll API Routes
    Route::prefix('api/payroll')->group(function () {
        // Time In/Out Routes
        Route::get('/time-tracking-records', [PayrollController::class, 'getTimeTrackingRecords'])->middleware('permission:view_time_in_out')->name('api.payroll.time-tracking-records');
        Route::get('/employees', [PayrollController::class, 'getEmployees'])->name('api.payroll.employees');
        Route::get('/clients', [PayrollController::class, 'getClients'])->middleware('permission:view_payroll_report|generate_payroll_report')->name('api.payroll.clients');
        Route::put('/time-tracking-records/{id}', [PayrollController::class, 'updateTimeTrackingRecord'])->middleware('permission:edit_time_in_out')->name('api.payroll.time-tracking-records.update');
        Route::get('/time-tracking-records/{id}/history', [PayrollController::class, 'getEditHistory'])->middleware('permission:view_time_in_out')->name('api.payroll.time-tracking-records.history');

        // Salary Computation Routes
        Route::get('/salary-computation', [PayrollController::class, 'getSalaryComputation'])->middleware('permission:view_salary_computation')->name('api.payroll.salary-computation');
        Route::post('/salary-computation', [PayrollController::class, 'saveSalaryComputation'])->middleware('permission:save_salary_computation')->name('api.payroll.salary-computation.save');
        Route::get('/salary-computation/{userId}/history', [PayrollController::class, 'getSalaryComputationHistory'])->middleware('permission:view_salary_computation')->name('api.payroll.salary-computation.history');
        Route::get('/saved-computations', [PayrollController::class, 'getSavedComputations'])->middleware('permission:view_salary_computation')->name('api.payroll.saved-computations');

        // Payroll Report Routes
        Route::get('/payroll-report', [PayrollController::class, 'getPayrollReport'])->middleware('permission:view_payroll_report|generate_payroll_report')->name('api.payroll.report');
        Route::get('/payroll-report/sales-reps', [PayrollController::class, 'getSalesRepPayrollSummary'])->middleware('permission:view_payroll_sales_rep_report')->name('api.payroll.report.sales-reps');
        Route::get('/payroll-report/sales-reps/{payrollPeriodInvoice}', [PayrollController::class, 'getSalesRepPayrollReportDetails'])->middleware('permission:view_payroll_sales_rep_report')->name('api.payroll.report.sales-reps.details');
        Route::get('/pnl-invoice-basis', [PayrollController::class, 'getPnlInvoiceBasis'])->middleware('permission:view_pnl')->name('api.payroll.pnl-invoice-basis');
        Route::get('/pnl-expenses', [PayrollController::class, 'getPnlManualExpenses'])->middleware('permission:view_pnl')->name('api.payroll.pnl-expenses');
        Route::post('/pnl-expenses', [PayrollController::class, 'storePnlManualExpense'])->middleware('permission:view_pnl')->name('api.payroll.pnl-expenses.store');
        Route::put('/pnl-expenses/{pnlManualExpense}', [PayrollController::class, 'updatePnlManualExpense'])->middleware('permission:view_pnl')->name('api.payroll.pnl-expenses.update');
        Route::delete('/pnl-expenses/{pnlManualExpense}', [PayrollController::class, 'deletePnlManualExpense'])->middleware('permission:view_pnl')->name('api.payroll.pnl-expenses.delete');
        Route::get('/payroll-report/converted-invoices', [PayrollController::class, 'getConvertedInvoicesList'])->middleware('permission:generate_payroll_report')->name('api.payroll.report.converted-invoices');
        Route::delete('/payroll-report/converted-invoice', [PayrollController::class, 'deleteConvertedInvoice'])->middleware('permission:generate_payroll_report')->name('api.payroll.report.delete-converted-invoice');
        Route::post('/payroll-report/convert-to-invoice', [PayrollController::class, 'convertPayrollToInvoice'])->middleware('permission:generate_payroll_report')->name('api.payroll.report.convert-to-invoice');
        Route::match(['get', 'post'], '/payroll-report/export', [PayrollController::class, 'exportPayrollReport'])->middleware('permission:export_payroll_report')->name('api.payroll.report.export');
        Route::put('/employees/{employee}/required-work-hours', [PayrollController::class, 'updateEmployeeRequiredWorkHours'])->middleware('permission:generate_payroll_report')->name('api.payroll.employees.required-work-hours');
        Route::put('/employees/{employee}/base-salary', [PayrollController::class, 'updateEmployeeBaseSalary'])->middleware('permission:generate_payroll_report')->name('api.payroll.employees.base-salary');
        Route::put('/employees/{employee}/client-invoice-amount', [PayrollController::class, 'updateEmployeeClientInvoiceAmount'])->middleware('permission:generate_payroll_report')->name('api.payroll.employees.client-invoice-amount');
        Route::post('/payroll-report/save', [PayrollController::class, 'savePayrollReport'])->middleware('permission:generate_payroll_report')->name('api.payroll.report.save');
        Route::get('/payroll-report/saved', [PayrollController::class, 'getSavedPayrollReports'])->middleware('permission:view_saved_for_wise')->name('api.payroll.report.saved');
        Route::get('/payroll-report/{payrollReport}/export/excel', [PayrollController::class, 'exportSavedReportExcel'])->middleware('permission:view_saved_for_wise')->name('api.payroll.report.export.excel');
        Route::get('/payroll-report/{payrollReport}/export/pdf', [PayrollController::class, 'exportSavedReportPdf'])->middleware('permission:view_saved_for_wise')->name('api.payroll.report.export.pdf');
        Route::post('/payroll-report/{payrollReport}/send-wise', [PayrollController::class, 'sendPayrollToWise'])->middleware('permission:view_saved_for_wise')->name('api.payroll.report.send-wise');
        Route::post('/payroll-report/bulk-send-wise', [PayrollController::class, 'bulkSendPayrollToWise'])->middleware('permission:view_saved_for_wise')->name('api.payroll.report.bulk-send-wise');
        Route::post('/payroll-report-item/{payrollReportItem}/send-wise', [PayrollController::class, 'sendPayrollItemToWise'])->middleware('permission:view_saved_for_wise')->name('api.payroll.report-item.send-wise');
        Route::delete('/payroll-report/{payrollReport}', [PayrollController::class, 'deletePayrollReport'])->middleware('permission:view_saved_for_wise')->name('api.payroll.report.delete');
        Route::delete('/payroll-report-item/{payrollReportItem}', [PayrollController::class, 'deletePayrollReportItem'])->middleware('permission:view_saved_for_wise')->name('api.payroll.report-item.delete');
        Route::post('/payroll-report/bulk-delete', [PayrollController::class, 'bulkDeletePayrollReports'])->middleware('permission:view_saved_for_wise')->name('api.payroll.report.bulk-delete');
    });

    Route::get('/project-management', function () {
        return view('dashboard.project-management');
    })->middleware('permission:view_project_management')->name('project-management');

    Route::get('/team-management', [\App\Http\Controllers\TeamManagementController::class, 'index'])->middleware('permission:view_team_management')->name('team-management');

    // Team Management API Routes
    Route::prefix('api/team-management')->group(function () {
        Route::get('/teams', [\App\Http\Controllers\TeamManagementController::class, 'getTeams'])->name('api.team-management.teams');
        Route::get('/teams/{team}', [\App\Http\Controllers\TeamManagementController::class, 'getTeam'])->name('api.team-management.teams.show');
        Route::post('/teams', [\App\Http\Controllers\TeamManagementController::class, 'store'])->name('api.team-management.teams.store');
        Route::put('/teams/{team}', [\App\Http\Controllers\TeamManagementController::class, 'update'])->name('api.team-management.teams.update');
        Route::delete('/teams/{team}', [\App\Http\Controllers\TeamManagementController::class, 'destroy'])->name('api.team-management.teams.destroy');

        Route::get('/teams/{team}/members', [\App\Http\Controllers\TeamManagementController::class, 'getTeamMembers'])->name('api.team-management.teams.members');
        Route::post('/teams/{team}/members', [\App\Http\Controllers\TeamManagementController::class, 'addMembers'])->name('api.team-management.teams.members.add');
        Route::delete('/teams/{team}/members/{member}', [\App\Http\Controllers\TeamManagementController::class, 'removeMember'])->name('api.team-management.teams.members.remove');
        Route::put('/teams/{team}/members/{member}/role', [\App\Http\Controllers\TeamManagementController::class, 'updateMemberRole'])->name('api.team-management.teams.members.role');

        Route::get('/teams/{team}/tasks', [\App\Http\Controllers\TeamManagementController::class, 'getTeamTasks'])->name('api.team-management.teams.tasks');
        Route::get('/teams/{team}/tasks/{task}/time-tracking', [\App\Http\Controllers\TeamManagementController::class, 'getTaskTimeTracking'])->name('api.team-management.teams.tasks.time-tracking');

        Route::get('/teams/{team}/time-tracking', [\App\Http\Controllers\TeamManagementController::class, 'getTeamTimeTracking'])->name('api.team-management.teams.time-tracking');
        Route::get('/teams/{team}/recordings', [\App\Http\Controllers\TeamManagementController::class, 'getTeamRecordings'])->name('api.team-management.teams.recordings');
        Route::get('/teams/{team}/recordings/{recording}/view', [\App\Http\Controllers\TeamManagementController::class, 'viewRecording'])->name('api.team-management.teams.recordings.view');
        Route::get('/teams/{team}/stats', [\App\Http\Controllers\TeamManagementController::class, 'getTeamStats'])->name('api.team-management.teams.stats');

        Route::get('/users', [\App\Http\Controllers\TeamManagementController::class, 'getAvailableUsers'])->name('api.team-management.users');
    });

    Route::get('/leave-management', [\App\Http\Controllers\LeaveManagementController::class, 'index'])->middleware('permission:view_leave_management')->name('leave-management');

    // Leave Management API Routes
    Route::prefix('api/leave-management')->group(function () {
        Route::get('/leave-requests', [\App\Http\Controllers\LeaveManagementController::class, 'getLeaveRequests'])->name('api.leave-management.leave-requests');
        Route::post('/leave-requests', [\App\Http\Controllers\LeaveManagementController::class, 'storeLeaveRequest'])->name('api.leave-management.leave-requests.store');
        Route::put('/leave-requests/{leaveRequest}', [\App\Http\Controllers\LeaveManagementController::class, 'updateLeaveRequest'])->name('api.leave-management.leave-requests.update');
        Route::post('/leave-requests/{leaveRequest}/cancel', [\App\Http\Controllers\LeaveManagementController::class, 'cancelLeaveRequest'])->name('api.leave-management.leave-requests.cancel');
        Route::get('/leave-requests/{leaveRequest}/attachment', [\App\Http\Controllers\LeaveManagementController::class, 'viewAttachment'])->name('api.leave-management.leave-requests.attachment');

        Route::get('/leave-credits', [\App\Http\Controllers\LeaveManagementController::class, 'getLeaveCredits'])->name('api.leave-management.leave-credits');
        Route::post('/leave-credits', [\App\Http\Controllers\LeaveManagementController::class, 'storeLeaveCredit'])->name('api.leave-management.leave-credits.store');

        Route::get('/users', [\App\Http\Controllers\LeaveManagementController::class, 'getAvailableUsers'])->name('api.leave-management.users');
        Route::get('/my-credits', [\App\Http\Controllers\LeaveManagementController::class, 'getMyLeaveCredits'])->name('api.leave-management.my-credits');
        Route::get('/calendar', [\App\Http\Controllers\LeaveManagementController::class, 'getLeaveCalendar'])->name('api.leave-management.calendar');
        Route::get('/employees-on-leave', [\App\Http\Controllers\LeaveManagementController::class, 'getEmployeesOnLeave'])->name('api.leave-management.employees-on-leave');
        Route::get('/stats', [\App\Http\Controllers\LeaveManagementController::class, 'getLeaveStats'])->name('api.leave-management.stats');
    });

    // Project Management API Routes
    Route::prefix('api/project-management')->group(function () {
        Route::get('/projects', [\App\Http\Controllers\ProjectManagementController::class, 'getProjects'])->name('api.project-management.projects');
        Route::get('/projects/stats', [\App\Http\Controllers\ProjectManagementController::class, 'getProjectStats'])->name('api.project-management.projects.stats');
        Route::get('/projects/{project}', [\App\Http\Controllers\ProjectManagementController::class, 'getProject'])->name('api.project-management.projects.show');
        Route::post('/projects', [\App\Http\Controllers\ProjectManagementController::class, 'storeProject'])->name('api.project-management.projects.store');
        Route::put('/projects/{project}', [\App\Http\Controllers\ProjectManagementController::class, 'updateProject'])->name('api.project-management.projects.update');
        Route::delete('/projects/{project}', [\App\Http\Controllers\ProjectManagementController::class, 'deleteProject'])->name('api.project-management.projects.destroy');

        Route::get('/tasks', [\App\Http\Controllers\ProjectManagementController::class, 'getTasks'])->name('api.project-management.tasks');
        Route::post('/tasks', [\App\Http\Controllers\ProjectManagementController::class, 'storeTask'])->name('api.project-management.tasks.store');
        Route::put('/tasks/{task}', [\App\Http\Controllers\ProjectManagementController::class, 'updateTask'])->name('api.project-management.tasks.update');
        Route::delete('/tasks/{task}', [\App\Http\Controllers\ProjectManagementController::class, 'deleteTask'])->name('api.project-management.tasks.destroy');

        Route::get('/time-tracking', [\App\Http\Controllers\ProjectManagementController::class, 'getTimeTracking'])->name('api.project-management.time-tracking');
        Route::get('/time-tracking/summary', [\App\Http\Controllers\ProjectManagementController::class, 'getTimeTrackingSummary'])->name('api.project-management.time-tracking.summary');

        Route::get('/dashboard/progress', [\App\Http\Controllers\ProjectManagementController::class, 'getDashboardProgress'])->name('api.project-management.dashboard.progress');

        Route::get('/users', [\App\Http\Controllers\ProjectManagementController::class, 'getUsers'])->name('api.project-management.users');

        Route::get('/time-tracking/active-record', [\App\Http\Controllers\ProjectManagementController::class, 'getActiveTimeTracking'])->name('api.project-management.time-tracking.active-record');
        Route::post('/time-tracking/start', [\App\Http\Controllers\ProjectManagementController::class, 'startTaskTimeTracking'])->name('api.project-management.time-tracking.start');
        Route::post('/tasks/{task}/stop-tracking', [\App\Http\Controllers\ProjectManagementController::class, 'stopTaskTimeTracking'])->name('api.project-management.tasks.stop-tracking');
    });

    Route::get('/messaging', [\App\Http\Controllers\MessagingController::class, 'index'])
        ->middleware('permission:view_messaging')->name('messaging');

    Route::get('/viber', [\App\Http\Controllers\ViberController::class, 'index'])
        ->middleware('permission:view_viber')->name('viber');

    Route::prefix('api/viber')->middleware('permission:view_viber')->group(function () {
        Route::get('/bootstrap', [\App\Http\Controllers\ViberController::class, 'bootstrap'])->name('api.viber.bootstrap');
        Route::get('/conversations', [\App\Http\Controllers\ViberController::class, 'conversations'])->name('api.viber.conversations');
        Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\ViberController::class, 'messages'])->name('api.viber.messages');
        Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\ViberController::class, 'sendMessage'])->name('api.viber.messages.store');
        Route::get('/conversations/{conversation}/call-link', [\App\Http\Controllers\ViberController::class, 'callLink'])->name('api.viber.call-link');
        Route::post('/media', [\App\Http\Controllers\ViberController::class, 'uploadMedia'])->name('api.viber.media.store');
    });

    Route::get('/whatsapp', [\App\Http\Controllers\WhatsAppController::class, 'index'])
        ->middleware('permission:view_whatsapp')->name('whatsapp');

    Route::prefix('api/whatsapp')->middleware('permission:view_whatsapp')->group(function () {
        Route::get('/bootstrap', [\App\Http\Controllers\WhatsAppController::class, 'bootstrap'])->name('api.whatsapp.bootstrap');
        Route::get('/conversations', [\App\Http\Controllers\WhatsAppController::class, 'conversations'])->name('api.whatsapp.conversations');
        Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\WhatsAppController::class, 'messages'])->name('api.whatsapp.messages');
        Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\WhatsAppController::class, 'sendMessage'])->name('api.whatsapp.messages.store');
        Route::get('/conversations/{conversation}/call-link', [\App\Http\Controllers\WhatsAppController::class, 'callLink'])->name('api.whatsapp.call-link');
        Route::post('/media', [\App\Http\Controllers\WhatsAppController::class, 'uploadMedia'])->name('api.whatsapp.media.store');
    });

    Route::get('/facebook', [\App\Http\Controllers\FacebookController::class, 'index'])
        ->middleware('permission:view_facebook')->name('facebook');

    Route::prefix('api/facebook')->middleware('permission:view_facebook')->group(function () {
        Route::get('/bootstrap', [\App\Http\Controllers\FacebookController::class, 'bootstrap'])->name('api.facebook.bootstrap');
        Route::get('/conversations', [\App\Http\Controllers\FacebookController::class, 'conversations'])->name('api.facebook.conversations');
        Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\FacebookController::class, 'messages'])->name('api.facebook.messages');
        Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\FacebookController::class, 'sendMessage'])->name('api.facebook.messages.store');
        Route::post('/media', [\App\Http\Controllers\FacebookController::class, 'uploadMedia'])->name('api.facebook.media.store');
    });

    Route::get('/sms', [\App\Http\Controllers\SmsController::class, 'index'])
        ->middleware('permission:view_sms')->name('sms');

    Route::prefix('api/sms')->middleware('permission:view_sms')->group(function () {
        Route::get('/bootstrap', [\App\Http\Controllers\SmsController::class, 'bootstrap'])->name('api.sms.bootstrap');
        Route::get('/conversations', [\App\Http\Controllers\SmsController::class, 'conversations'])->name('api.sms.conversations');
        Route::post('/conversations', [\App\Http\Controllers\SmsController::class, 'startConversation'])
            ->middleware('permission:send_sms')
            ->name('api.sms.conversations.store');
        Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\SmsController::class, 'messages'])->name('api.sms.messages');
        Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\SmsController::class, 'sendMessage'])
            ->middleware('permission:send_sms')
            ->name('api.sms.messages.store');
        Route::get('/conversations/{conversation}/call-link', [\App\Http\Controllers\SmsController::class, 'callLink'])->name('api.sms.call-link');
    });

    Route::prefix('api/messaging')->middleware('permission:view_messaging')->group(function () {
        Route::get('/unread-count', [\App\Http\Controllers\MessagingController::class, 'getUnreadCount'])->name('api.messaging.unread-count');
        Route::get('/conversations', [\App\Http\Controllers\MessagingController::class, 'getConversations'])->name('api.messaging.conversations');
        Route::get('/users', [\App\Http\Controllers\MessagingController::class, 'getUsers'])->name('api.messaging.users');
        Route::post('/conversations', [\App\Http\Controllers\MessagingController::class, 'createConversation'])->name('api.messaging.conversations.store');
        Route::get('/conversations/{conversation}', [\App\Http\Controllers\MessagingController::class, 'getConversation'])->name('api.messaging.conversations.show');
        Route::post('/conversations/{conversation}/update', [\App\Http\Controllers\MessagingController::class, 'updateConversation'])->name('api.messaging.conversations.update');
        Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\MessagingController::class, 'getMessages'])->name('api.messaging.messages');
        Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\MessagingController::class, 'sendMessage'])->name('api.messaging.messages.store');
        Route::post('/conversations/{conversation}/members', [\App\Http\Controllers\MessagingController::class, 'addMember'])->name('api.messaging.conversations.members.store');
        Route::post('/conversations/{conversation}/members/{user}/remove', [\App\Http\Controllers\MessagingController::class, 'removeMember'])->name('api.messaging.conversations.members.destroy');
        Route::post('/conversations/{conversation}/transfer-ownership', [\App\Http\Controllers\MessagingController::class, 'transferOwnership'])->name('api.messaging.conversations.transfer-ownership');
        Route::delete('/conversations/{conversation}', [\App\Http\Controllers\MessagingController::class, 'destroyConversation'])->name('api.messaging.conversations.destroy');
        Route::post('/attachments', [\App\Http\Controllers\MessagingController::class, 'uploadAttachment'])->name('api.messaging.attachments.store');
        Route::post('/attachments/discard', [\App\Http\Controllers\MessagingController::class, 'discardAttachment'])->name('api.messaging.attachments.discard');
    });

    // Shared / personal Outlook inbox (Front-style)
    Route::get('/inbox', [\App\Http\Controllers\InboxController::class, 'index'])
        ->middleware('permission:view_inbox')
        ->name('inbox');
    Route::get('/inbox/connect/outlook', [\App\Http\Controllers\InboxController::class, 'redirectOutlook'])
        ->middleware('permission:view_inbox')
        ->name('inbox.connect.outlook');
    Route::get('/inbox/connect/outlook/callback', [\App\Http\Controllers\InboxController::class, 'callbackOutlook'])
        ->middleware('permission:view_inbox')
        ->name('inbox.connect.outlook.callback');

    Route::prefix('api/inbox')->middleware('permission:view_inbox')->group(function () {
        Route::get('/bootstrap', [\App\Http\Controllers\InboxController::class, 'bootstrap'])->name('api.inbox.bootstrap');
        Route::post('/disconnect', [\App\Http\Controllers\InboxController::class, 'disconnectMail'])->name('api.inbox.disconnect');
        Route::post('/sync', [\App\Http\Controllers\InboxController::class, 'sync'])->name('api.inbox.sync');
        Route::post('/sync-totals', [\App\Http\Controllers\InboxController::class, 'syncTotals'])->name('api.inbox.sync-totals');
        Route::get('/conversations', [\App\Http\Controllers\InboxController::class, 'listConversations'])->name('api.inbox.conversations');
        Route::get('/email-suggestions', [\App\Http\Controllers\InboxController::class, 'suggestEmails'])->name('api.inbox.email-suggestions');
        Route::get('/conversations/{conversation}', [\App\Http\Controllers\InboxController::class, 'showConversation'])->name('api.inbox.conversations.show');
        Route::post('/conversations/{conversation}/assign', [\App\Http\Controllers\InboxController::class, 'assign'])->name('api.inbox.conversations.assign');
        Route::patch('/conversations/{conversation}/status', [\App\Http\Controllers\InboxController::class, 'updateStatus'])->name('api.inbox.conversations.status');
        Route::post('/conversations/{conversation}/tags', [\App\Http\Controllers\InboxController::class, 'syncTags'])->name('api.inbox.conversations.tags');
        Route::post('/conversations/{conversation}/reply', [\App\Http\Controllers\InboxController::class, 'reply'])->name('api.inbox.conversations.reply');
        Route::post('/conversations/{conversation}/comments', [\App\Http\Controllers\InboxController::class, 'storeComment'])->name('api.inbox.conversations.comments.store');
        Route::get('/conversations/{conversation}/comments/{comment}/attachments/{index}', [\App\Http\Controllers\InboxController::class, 'downloadCommentAttachment'])->name('api.inbox.conversations.comments.attachments');
        Route::get('/conversations/{conversation}/messages/{message}/attachments/{index}', [\App\Http\Controllers\InboxController::class, 'downloadMessageAttachment'])->name('api.inbox.conversations.messages.attachments')->whereNumber('index');
        Route::post('/compose', [\App\Http\Controllers\InboxController::class, 'compose'])->name('api.inbox.compose');
        Route::post('/inboxes', [\App\Http\Controllers\InboxController::class, 'storeInbox'])->name('api.inbox.inboxes.store');
        Route::put('/inboxes/{sharedInbox}/members', [\App\Http\Controllers\InboxController::class, 'updateInboxMembers'])->name('api.inbox.inboxes.members');
        Route::post('/tags', [\App\Http\Controllers\InboxController::class, 'storeTag'])->name('api.inbox.tags.store');
        Route::delete('/tags/{tag}', [\App\Http\Controllers\InboxController::class, 'destroyTag'])->name('api.inbox.tags.destroy');
        Route::put('/pinned-tags', [\App\Http\Controllers\InboxController::class, 'syncPinnedTags'])->name('api.inbox.pinned-tags');
        Route::post('/templates', [\App\Http\Controllers\InboxController::class, 'storeTemplate'])->name('api.inbox.templates.store');
        Route::put('/templates/{template}', [\App\Http\Controllers\InboxController::class, 'updateTemplate'])->name('api.inbox.templates.update');
        Route::delete('/templates/{template}', [\App\Http\Controllers\InboxController::class, 'destroyTemplate'])->name('api.inbox.templates.destroy');
        Route::post('/templates/import', [\App\Http\Controllers\InboxController::class, 'importTemplates'])->name('api.inbox.templates.import');
        Route::post('/rules', [\App\Http\Controllers\InboxController::class, 'storeRule'])->name('api.inbox.rules.store');
        Route::patch('/rules/{rule}', [\App\Http\Controllers\InboxController::class, 'updateRule'])->name('api.inbox.rules.update');
        Route::delete('/rules/{rule}', [\App\Http\Controllers\InboxController::class, 'destroyRule'])->name('api.inbox.rules.destroy');
    });

    Route::prefix('api/notifications')->group(function () {
        Route::get('/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount'])->name('api.notifications.unread-count');
        Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('api.notifications.read-all');
        Route::post('/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('api.notifications.read');
    });

    Route::get('/billing', [\App\Http\Controllers\BillingInvoiceController::class, 'page'])
        ->middleware('permission:view_billing')->name('billing');

    Route::prefix('api/billing-invoices')->middleware('permission:view_billing')->group(function () {
        Route::get('/', [\App\Http\Controllers\BillingInvoiceController::class, 'index'])->name('api.billing-invoices.index');
        Route::get('/stats', [\App\Http\Controllers\BillingInvoiceController::class, 'stats'])->name('api.billing-invoices.stats');
        Route::get('/clients', [\App\Http\Controllers\BillingInvoiceController::class, 'getClients'])->name('api.billing-invoices.clients');
        Route::get('/clients/{client}/employees', [\App\Http\Controllers\BillingInvoiceController::class, 'getClientEmployees'])->name('api.billing-invoices.client-employees')->where('client', '[0-9]+');
        Route::get('/next-number', [\App\Http\Controllers\BillingInvoiceController::class, 'getNextInvoiceNumber'])->name('api.billing-invoices.next-number');
        Route::get('/payment-tracking', [\App\Http\Controllers\BillingInvoiceController::class, 'paymentTracking'])->name('api.billing-invoices.payment-tracking');
        Route::get('/stripe-dashboard', [\App\Http\Controllers\BillingInvoiceController::class, 'stripeDashboard'])->name('api.billing-invoices.stripe-dashboard');
        Route::get('/wise-dashboard', [\App\Http\Controllers\BillingInvoiceController::class, 'wiseDashboard'])->name('api.billing-invoices.wise-dashboard');
        Route::post('/stripe-payment-link', [\App\Http\Controllers\BillingInvoiceController::class, 'createStripePaymentLink'])->name('api.billing-invoices.stripe-payment-link');
        Route::post('/bulk-send-email', [\App\Http\Controllers\BillingInvoiceController::class, 'bulkSendEmail'])->name('api.billing-invoices.bulk-send-email');
        Route::post('/bulk-stripe-payment-link', [\App\Http\Controllers\BillingInvoiceController::class, 'bulkStripePaymentLink'])->name('api.billing-invoices.bulk-stripe-payment-link');
        Route::match(['get', 'post'], '/wise-default-link', [\App\Http\Controllers\BillingInvoiceController::class, 'wiseDefaultLink'])->name('api.billing-invoices.wise-default-link');
        Route::get('/wise-webhook-status', [\App\Http\Controllers\BillingInvoiceController::class, 'wiseWebhookStatus'])->name('api.billing-invoices.wise-webhook-status');
        Route::post('/wise-webhook/enable', [\App\Http\Controllers\BillingInvoiceController::class, 'enableWiseWebhook'])->name('api.billing-invoices.wise-webhook.enable');
        Route::post('/wise-webhook/disable', [\App\Http\Controllers\BillingInvoiceController::class, 'disableWiseWebhook'])->name('api.billing-invoices.wise-webhook.disable');
        Route::get('/wise-incoming-payments', [\App\Http\Controllers\BillingInvoiceController::class, 'wiseIncomingPayments'])->name('api.billing-invoices.wise-incoming-payments');
        Route::post('/{invoice}/mark-wise-paid', [\App\Http\Controllers\BillingInvoiceController::class, 'markWisePaid'])->name('api.billing-invoices.mark-wise-paid')->where('invoice', '[0-9]+');
        Route::get('/subscriptions', [\App\Http\Controllers\BillingSubscriptionController::class, 'index'])->name('api.billing-subscriptions.index');
        Route::post('/subscriptions', [\App\Http\Controllers\BillingSubscriptionController::class, 'store'])->name('api.billing-subscriptions.store');
        Route::get('/subscriptions/{subscription}/payment-link', [\App\Http\Controllers\BillingSubscriptionController::class, 'paymentLink'])->name('api.billing-subscriptions.payment-link')->where('subscription', '[0-9]+');
        Route::post('/subscriptions/{subscription}/cancel', [\App\Http\Controllers\BillingSubscriptionController::class, 'cancel'])->name('api.billing-subscriptions.cancel')->where('subscription', '[0-9]+');
        Route::get('/subscriptions/{subscription}', [\App\Http\Controllers\BillingSubscriptionController::class, 'show'])->name('api.billing-subscriptions.show')->where('subscription', '[0-9]+');
        Route::put('/subscriptions/{subscription}', [\App\Http\Controllers\BillingSubscriptionController::class, 'update'])->name('api.billing-subscriptions.update')->where('subscription', '[0-9]+');
        Route::delete('/subscriptions/{subscription}', [\App\Http\Controllers\BillingSubscriptionController::class, 'destroy'])->name('api.billing-subscriptions.destroy')->where('subscription', '[0-9]+');
        Route::post('/', [\App\Http\Controllers\BillingInvoiceController::class, 'store'])->name('api.billing-invoices.store');
        Route::get('/{invoice}/pdf', [\App\Http\Controllers\BillingInvoiceController::class, 'pdf'])->name('api.billing-invoices.pdf')->where('invoice', '[0-9]+');
        Route::post('/{invoice}/send-email', [\App\Http\Controllers\BillingInvoiceController::class, 'sendEmail'])->name('api.billing-invoices.send-email')->where('invoice', '[0-9]+');
        Route::get('/{invoice}', [\App\Http\Controllers\BillingInvoiceController::class, 'show'])->name('api.billing-invoices.show')->where('invoice', '[0-9]+');
        Route::put('/{invoice}', [\App\Http\Controllers\BillingInvoiceController::class, 'update'])->name('api.billing-invoices.update')->where('invoice', '[0-9]+');
        Route::delete('/{invoice}', [\App\Http\Controllers\BillingInvoiceController::class, 'destroy'])->middleware('permission:delete_billing')->name('api.billing-invoices.destroy')->where('invoice', '[0-9]+');
    });

    Route::get('/client-management', function () {
        return view('dashboard.client-management');
    })->middleware('permission:view_client_management')->name('client-management');

    Route::get('/tickets', [\App\Http\Controllers\TicketController::class, 'index'])
        ->middleware('permission:view_tickets')
        ->name('tickets');

    // Tickets API Routes
    Route::prefix('api/tickets')->middleware('permission:view_tickets')->group(function () {
        Route::get('/form-data', [\App\Http\Controllers\TicketController::class, 'getFormData'])->name('api.tickets.form-data');
        Route::get('/', [\App\Http\Controllers\TicketController::class, 'getTickets'])->name('api.tickets.index');
        Route::post('/', [\App\Http\Controllers\TicketController::class, 'store'])->name('api.tickets.store');
        Route::get('/{ticket}', [\App\Http\Controllers\TicketController::class, 'show'])->name('api.tickets.show');
        Route::put('/{ticket}', [\App\Http\Controllers\TicketController::class, 'update'])->name('api.tickets.update');
        Route::post('/{ticket}/comments', [\App\Http\Controllers\TicketController::class, 'storeComment'])->name('api.tickets.comments.store');
    });

    Route::get('/knowledge-base', [\App\Http\Controllers\KnowledgeBaseController::class, 'index'])
        ->middleware('permission:view_knowledge_base')
        ->name('knowledge-base');

    Route::prefix('api/knowledge-base')->middleware('permission:view_knowledge_base')->group(function () {
        Route::post('/categories', [\App\Http\Controllers\KnowledgeBaseController::class, 'storeCategory'])->middleware('permission:create_knowledge_base')->name('api.knowledge-base.categories.store');
        Route::post('/articles', [\App\Http\Controllers\KnowledgeBaseController::class, 'storeArticle'])->middleware('permission:create_knowledge_base')->name('api.knowledge-base.articles.store');
        Route::put('/articles/{id}', [\App\Http\Controllers\KnowledgeBaseController::class, 'updateArticle'])->middleware('permission:edit_knowledge_base')->name('api.knowledge-base.articles.update');
        Route::delete('/articles/{id}', [\App\Http\Controllers\KnowledgeBaseController::class, 'destroyArticle'])->middleware('permission:delete_knowledge_base')->name('api.knowledge-base.articles.destroy');
        Route::post('/faqs', [\App\Http\Controllers\KnowledgeBaseController::class, 'storeFaq'])->middleware('permission:create_knowledge_base')->name('api.knowledge-base.faqs.store');
        Route::put('/faqs/{id}', [\App\Http\Controllers\KnowledgeBaseController::class, 'updateFaq'])->middleware('permission:edit_knowledge_base')->name('api.knowledge-base.faqs.update');
        Route::delete('/faqs/{id}', [\App\Http\Controllers\KnowledgeBaseController::class, 'destroyFaq'])->middleware('permission:delete_knowledge_base')->name('api.knowledge-base.faqs.destroy');
        Route::post('/guides', [\App\Http\Controllers\KnowledgeBaseController::class, 'storeGuide'])->middleware('permission:create_knowledge_base')->name('api.knowledge-base.guides.store');
        Route::put('/guides/{id}', [\App\Http\Controllers\KnowledgeBaseController::class, 'updateGuide'])->middleware('permission:edit_knowledge_base')->name('api.knowledge-base.guides.update');
        Route::delete('/guides/{id}', [\App\Http\Controllers\KnowledgeBaseController::class, 'destroyGuide'])->middleware('permission:delete_knowledge_base')->name('api.knowledge-base.guides.destroy');
    });

    Route::get('/integrations', function () {
        return view('dashboard.integrations');
    })->middleware('permission:view_integrations')->name('integrations');

    Route::get('/wise-recipients', [\App\Http\Controllers\IntegrationController::class, 'wiseRecipientsPage'])
        ->name('wise-recipients');

    // Integration API Routes
    Route::prefix('api/integrations')->group(function () {
        Route::get('/twilio', [\App\Http\Controllers\IntegrationController::class, 'getTwilioIntegration'])->name('api.integrations.twilio.get');
        Route::post('/twilio', [\App\Http\Controllers\IntegrationController::class, 'storeTwilioIntegration'])->name('api.integrations.twilio.store');
        Route::delete('/twilio', [\App\Http\Controllers\IntegrationController::class, 'deleteTwilioIntegration'])->name('api.integrations.twilio.delete');
        Route::get('/viber', [\App\Http\Controllers\IntegrationController::class, 'getViberIntegration'])->name('api.integrations.viber.get');
        Route::post('/viber', [\App\Http\Controllers\IntegrationController::class, 'storeViberIntegration'])->name('api.integrations.viber.store');
        Route::delete('/viber', [\App\Http\Controllers\IntegrationController::class, 'deleteViberIntegration'])->name('api.integrations.viber.delete');
        Route::get('/whatsapp', [\App\Http\Controllers\IntegrationController::class, 'getWhatsAppIntegration'])->name('api.integrations.whatsapp.get');
        Route::post('/whatsapp', [\App\Http\Controllers\IntegrationController::class, 'storeWhatsAppIntegration'])->name('api.integrations.whatsapp.store');
        Route::delete('/whatsapp', [\App\Http\Controllers\IntegrationController::class, 'deleteWhatsAppIntegration'])->name('api.integrations.whatsapp.delete');
        Route::get('/facebook', [\App\Http\Controllers\IntegrationController::class, 'getFacebookIntegration'])->name('api.integrations.facebook.get');
        Route::post('/facebook', [\App\Http\Controllers\IntegrationController::class, 'storeFacebookIntegration'])->name('api.integrations.facebook.store');
        Route::delete('/facebook', [\App\Http\Controllers\IntegrationController::class, 'deleteFacebookIntegration'])->name('api.integrations.facebook.delete');
        Route::get('/gmail', [\App\Http\Controllers\IntegrationController::class, 'getGmailIntegration'])->name('api.integrations.gmail.get');
        Route::post('/gmail', [\App\Http\Controllers\IntegrationController::class, 'storeGmailIntegration'])->name('api.integrations.gmail.store');
        Route::delete('/gmail', [\App\Http\Controllers\IntegrationController::class, 'deleteGmailIntegration'])->name('api.integrations.gmail.delete');
        Route::get('/stripe', [\App\Http\Controllers\IntegrationController::class, 'getStripeIntegration'])->name('api.integrations.stripe.get');
        Route::post('/stripe', [\App\Http\Controllers\IntegrationController::class, 'storeStripeIntegration'])->name('api.integrations.stripe.store');
        Route::delete('/stripe', [\App\Http\Controllers\IntegrationController::class, 'deleteStripeIntegration'])->name('api.integrations.stripe.delete');
        Route::get('/wise', [\App\Http\Controllers\IntegrationController::class, 'getWiseIntegration'])->name('api.integrations.wise.get');
        Route::match(['get', 'post'], '/wise/profiles', [\App\Http\Controllers\IntegrationController::class, 'getWiseProfiles'])->name('api.integrations.wise.profiles');
        Route::get('/wise/recipients', [\App\Http\Controllers\IntegrationController::class, 'getWiseRecipients'])->name('api.integrations.wise.recipients');
        Route::post('/wise/recipients/requirements', [\App\Http\Controllers\IntegrationController::class, 'getWiseRecipientRequirements'])->name('api.integrations.wise.recipients.requirements');
        Route::post('/wise/recipients/requirements/refresh', [\App\Http\Controllers\IntegrationController::class, 'postWiseRecipientRequirements'])->name('api.integrations.wise.recipients.requirements.refresh');
        Route::post('/wise/recipients', [\App\Http\Controllers\IntegrationController::class, 'createWiseRecipient'])->name('api.integrations.wise.recipients.create');
        Route::post('/wise/contacts', [\App\Http\Controllers\IntegrationController::class, 'createWiseContact'])->name('api.integrations.wise.contacts.create');
        Route::put('/wise/employees/{user}/wise-account', [\App\Http\Controllers\IntegrationController::class, 'updateEmployeeWiseAccount'])->name('api.integrations.wise.employees.wise-account');
        Route::post('/wise', [\App\Http\Controllers\IntegrationController::class, 'storeWiseIntegration'])->name('api.integrations.wise.store');
        Route::delete('/wise', [\App\Http\Controllers\IntegrationController::class, 'deleteWiseIntegration'])->name('api.integrations.wise.delete');
        Route::get('/openai', [\App\Http\Controllers\IntegrationController::class, 'getOpenAiIntegration'])->name('api.integrations.openai.get');
        Route::post('/openai', [\App\Http\Controllers\IntegrationController::class, 'storeOpenAiIntegration'])->name('api.integrations.openai.store');
        Route::delete('/openai', [\App\Http\Controllers\IntegrationController::class, 'deleteOpenAiIntegration'])->name('api.integrations.openai.delete');
    });

    Route::get('/billing-plan', function () {
        return view('dashboard.billing-plan');
    })->name('billing-plan');

    Route::get('/quotation-builder', [\App\Http\Controllers\QuotationController::class, 'index'])->middleware('permission:view_quotation_builder')->name('quotation-builder');

    // Quotation Builder API Routes
    Route::prefix('api/quotation-builder')->group(function () {
        Route::get('/quotations', [\App\Http\Controllers\QuotationController::class, 'getQuotations'])->name('api.quotation-builder.quotations');
        Route::get('/stats', [\App\Http\Controllers\QuotationController::class, 'getStats'])->name('api.quotation-builder.stats');
        Route::get('/clients', [\App\Http\Controllers\QuotationController::class, 'getClients'])->name('api.quotation-builder.clients');
        Route::get('/next-quotation-number', [\App\Http\Controllers\QuotationController::class, 'getNextQuotationNumber'])->name('api.quotation-builder.next-quotation-number');
        Route::post('/quotations', [\App\Http\Controllers\QuotationController::class, 'store'])->name('api.quotation-builder.quotations.store');
        Route::get('/quotations/{quotation}', [\App\Http\Controllers\QuotationController::class, 'show'])->name('api.quotation-builder.quotations.show');
        Route::get('/quotations/{quotation}/pdf', [\App\Http\Controllers\QuotationController::class, 'pdf'])->name('api.quotation-builder.quotations.pdf');
        Route::get('/quotations/{quotation}/status-history', [\App\Http\Controllers\QuotationController::class, 'getStatusHistory'])->name('api.quotation-builder.quotations.status-history');
        Route::post('/quotations/{quotation}/send-email', [\App\Http\Controllers\QuotationController::class, 'sendEmail'])->name('api.quotation-builder.quotations.send-email');
        Route::put('/quotations/{quotation}', [\App\Http\Controllers\QuotationController::class, 'update'])->name('api.quotation-builder.quotations.update');
        Route::patch('/quotations/{quotation}/status', [\App\Http\Controllers\QuotationController::class, 'updateStatus'])->name('api.quotation-builder.quotations.status.update');
        Route::delete('/quotations/{quotation}', [\App\Http\Controllers\QuotationController::class, 'destroy'])->name('api.quotation-builder.quotations.destroy');

        // Item templates autocomplete
        Route::get('/item-templates/search', [\App\Http\Controllers\QuotationItemTemplateController::class, 'search'])->name('api.quotation-builder.item-templates.search');
    });

    // Quotation Item Templates Routes
    Route::get('/quotation-item-templates', [\App\Http\Controllers\QuotationItemTemplateController::class, 'index'])->middleware('permission:view_quotation_builder')->name('quotation-item-templates');

    Route::prefix('api/quotation-item-templates')->group(function () {
        Route::get('/templates', [\App\Http\Controllers\QuotationItemTemplateController::class, 'getTemplates'])->name('api.quotation-item-templates.templates');
        Route::post('/templates', [\App\Http\Controllers\QuotationItemTemplateController::class, 'store'])->name('api.quotation-item-templates.templates.store');
        Route::get('/templates/{quotationItemTemplate}', [\App\Http\Controllers\QuotationItemTemplateController::class, 'show'])->name('api.quotation-item-templates.templates.show');
        Route::put('/templates/{quotationItemTemplate}', [\App\Http\Controllers\QuotationItemTemplateController::class, 'update'])->name('api.quotation-item-templates.templates.update');
        Route::delete('/templates/{quotationItemTemplate}', [\App\Http\Controllers\QuotationItemTemplateController::class, 'destroy'])->name('api.quotation-item-templates.templates.destroy');
    });

    // Contracts & E-Sign Routes
    Route::get('/contracts', [\App\Http\Controllers\ContractController::class, 'index'])
        ->middleware('permission:view_contracts')
        ->name('contracts');

    Route::prefix('api/contracts')->middleware('permission:view_contracts')->group(function () {
        Route::get('/', [\App\Http\Controllers\ContractController::class, 'getContracts'])->name('api.contracts.index');
        Route::get('/stats', [\App\Http\Controllers\ContractController::class, 'getStats'])->name('api.contracts.stats');
        Route::get('/clients', [\App\Http\Controllers\ContractController::class, 'getClients'])->name('api.contracts.clients');
        Route::get('/next-number', [\App\Http\Controllers\ContractController::class, 'getNextContractNumber'])->name('api.contracts.next-number');
        Route::post('/', [\App\Http\Controllers\ContractController::class, 'store'])
            ->middleware('permission:create_contracts')
            ->name('api.contracts.store');
        Route::get('/{contract}', [\App\Http\Controllers\ContractController::class, 'show'])->name('api.contracts.show');
        Route::get('/{contract}/pdf', [\App\Http\Controllers\ContractController::class, 'pdf'])->name('api.contracts.pdf');
        Route::get('/{contract}/status-history', [\App\Http\Controllers\ContractController::class, 'getStatusHistory'])->name('api.contracts.status-history');
        Route::put('/{contract}', [\App\Http\Controllers\ContractController::class, 'update'])
            ->middleware('permission:create_contracts')
            ->name('api.contracts.update');
        Route::delete('/{contract}', [\App\Http\Controllers\ContractController::class, 'destroy'])
            ->middleware('permission:delete_contracts')
            ->name('api.contracts.destroy');
        Route::post('/{contract}/send', [\App\Http\Controllers\ContractController::class, 'sendForSignature'])
            ->middleware('permission:send_contracts')
            ->name('api.contracts.send');
        Route::post('/{contract}/cancel', [\App\Http\Controllers\ContractController::class, 'cancel'])
            ->middleware('permission:create_contracts')
            ->name('api.contracts.cancel');
    });

    Route::get('/calendar', function () {
        return view('dashboard.calendar');
    })->middleware('permission:view_calendar')->name('calendar');

    // Calendar OAuth (Google & Outlook)
    Route::get('/calendar/connect/google', [\App\Http\Controllers\CalendarController::class, 'redirectGoogle'])
        ->middleware('permission:view_calendar')
        ->name('calendar.connect.google');
    Route::get('/calendar/connect/google/callback', [\App\Http\Controllers\CalendarController::class, 'callbackGoogle'])
        ->middleware('permission:view_calendar')
        ->name('calendar.connect.google.callback');
    Route::get('/calendar/connect/outlook', [\App\Http\Controllers\CalendarController::class, 'redirectOutlook'])
        ->middleware('permission:view_calendar')
        ->name('calendar.connect.outlook');
    Route::get('/calendar/connect/outlook/callback', [\App\Http\Controllers\CalendarController::class, 'callbackOutlook'])
        ->middleware('permission:view_calendar')
        ->name('calendar.connect.outlook.callback');
    Route::post('/api/calendar/disconnect', [\App\Http\Controllers\CalendarController::class, 'disconnect'])
        ->middleware('permission:view_calendar')
        ->name('api.calendar.disconnect');
    Route::get('/api/calendar/status', [\App\Http\Controllers\CalendarController::class, 'status'])
        ->middleware('permission:view_calendar')
        ->name('api.calendar.status');
    Route::get('/api/calendar/events', [\App\Http\Controllers\CalendarController::class, 'events'])
        ->middleware('permission:view_calendar')
        ->name('api.calendar.events');
    Route::get('/api/calendar/oauth-settings', [\App\Http\Controllers\CalendarController::class, 'getOauthSettings'])
        ->middleware('permission:view_calendar')
        ->name('api.calendar.oauth-settings');
    Route::post('/api/calendar/oauth-settings', [\App\Http\Controllers\CalendarController::class, 'storeOauthSettings'])
        ->middleware('permission:view_calendar')
        ->name('api.calendar.oauth-settings.store');

    Route::get('/email-tracking', function () {
        return view('dashboard.email-tracking');
    })->middleware('permission:view_email_tracking')->name('email-tracking');

    Route::get('/e-signature', function () {
        return redirect()->route('contracts');
    })->middleware('permission:view_contracts')->name('e-signature');

    Route::get('/openai', function () {
        return view('dashboard.openai');
    })->middleware('permission:view_ai_assistant')->name('openai');

    Route::post('/api/openai/chat', [\App\Http\Controllers\OpenAiController::class, 'chat'])
        ->middleware('permission:view_ai_assistant')
        ->name('api.openai.chat');

    // Change Password Routes
    Route::get('/change-password', [ChangePasswordController::class, 'index'])->middleware('permission:view_change_password')->name('change-password');
    Route::post('/api/change-password', [ChangePasswordController::class, 'update'])->name('api.change-password.update');
});

// Admin Authentication Routes (Public)
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])
        ->name('admin.login');

    Route::post('/login', [AdminAuthController::class, 'login'])
        ->name('admin.login.submit');

    Route::get('/auth/restore', [ImpersonationController::class, 'restore'])->name('admin.auth.restore');
});

// Admin Protected Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout'])
        ->name('admin.logout');

    Route::get('/logout', [AdminAuthController::class, 'logout'])
        ->name('admin.logout.get');

    Route::get('/control', [AdminController::class, 'index'])->name('admin-control');

    Route::get('/smtp-settings', [SmtpSettingsController::class, 'index'])->name('admin.smtp-settings');
    Route::put('/smtp-settings', [SmtpSettingsController::class, 'update'])->name('admin.smtp-settings.update');
    Route::post('/smtp-settings/test', [SmtpSettingsController::class, 'test'])->name('admin.smtp-settings.test');

    Route::get('/ai-settings', [AiSettingsController::class, 'index'])->name('admin.ai-settings');
    Route::put('/ai-settings', [AiSettingsController::class, 'update'])->name('admin.ai-settings.update');
    Route::put('/ai-settings/companies/{company}/token-limit', [AiSettingsController::class, 'updateCompanyLimit'])->name('admin.ai-settings.company-limit');

    Route::get('/billing-management', [BillingManagementController::class, 'index'])->name('admin.billing-management');
    Route::post('/billing-management/plans', [BillingManagementController::class, 'storePlan'])->name('admin.billing-management.plans.store');
    Route::put('/billing-management/plans/{plan}', [BillingManagementController::class, 'updatePlan'])->name('admin.billing-management.plans.update');
    Route::delete('/billing-management/plans/{plan}', [BillingManagementController::class, 'destroyPlan'])->name('admin.billing-management.plans.destroy');

    Route::get('/company-management', [CompanyManagementController::class, 'index'])->name('admin.company-management');
    Route::post('/company-management', [CompanyManagementController::class, 'store'])->name('admin.company-management.store');
    Route::put('/company-management/{company}', [CompanyManagementController::class, 'update'])->name('admin.company-management.update');
    Route::patch('/company-management/{company}/status', [CompanyManagementController::class, 'updateStatus'])->name('admin.company-management.status');
    Route::post('/company-management/{company}/login-as-admin', [ImpersonationController::class, 'loginAsCompanyAdmin'])->name('admin.company-management.login-as-admin');

    Route::get('/company-access-control', [CompanyAccessControlController::class, 'index'])->name('admin.company-access-control');
    Route::put('/company-access-control/{company}/modules', [CompanyAccessControlController::class, 'updateModuleAccess'])->name('admin.company-access-control.modules.update');
    Route::post('/company-access-control/{company}/modules/{module}/toggle', [CompanyAccessControlController::class, 'toggleModuleAccess'])->name('admin.company-access-control.modules.toggle');

    Route::get('/support-override', [SupportOverrideController::class, 'index'])->name('admin.support-override');

    Route::get('/user-management', [UserManagementController::class, 'index'])->name('admin.user-management');

    Route::get('/screen-recording-management', [ScreenRecordingManagementController::class, 'index'])->name('admin.screen-recording-management');
    Route::post('/screen-recording-management/bulk-delete', [ScreenRecordingManagementController::class, 'bulkDelete'])->name('admin.screen-recording-management.bulk-delete');

    Route::get('/api-key-management', [ApiKeyManagementController::class, 'index'])->name('admin.api-key-management');
    Route::post('/api-key-management', [ApiKeyManagementController::class, 'store'])->name('admin.api-key-management.store');
    Route::put('/api-key-management/{apiKey}', [ApiKeyManagementController::class, 'update'])->name('admin.api-key-management.update');
    Route::delete('/api-key-management/{apiKey}', [ApiKeyManagementController::class, 'destroy'])->name('admin.api-key-management.destroy');

    // API Routes for AJAX calls
    Route::prefix('api')->group(function () {
        // Plans API
        Route::get('/plans', [BillingManagementController::class, 'apiPlans'])->name('admin.api.plans');
        Route::get('/plans/{plan}', [BillingManagementController::class, 'apiGetPlan'])->name('admin.api.plans.show');
        Route::post('/plans', [BillingManagementController::class, 'apiStorePlan'])->name('admin.api.plans.store');
        Route::put('/plans/{plan}', [BillingManagementController::class, 'apiUpdatePlan'])->name('admin.api.plans.update');
        Route::delete('/plans/{plan}', [BillingManagementController::class, 'apiDestroyPlan'])->name('admin.api.plans.destroy');

        // Companies & Billing API
        Route::get('/companies', [BillingManagementController::class, 'apiCompanies'])->name('admin.api.companies');
        Route::get('/payments', [BillingManagementController::class, 'apiPayments'])->name('admin.api.payments');

        // Modules API
        Route::get('/modules', [CompanyAccessControlController::class, 'apiModules'])->name('admin.api.modules');
        Route::get('/companies/{company}/modules', [CompanyAccessControlController::class, 'apiCompanyModules'])->name('admin.api.companies.modules');
        Route::put('/companies/{company}/modules', [CompanyAccessControlController::class, 'apiUpdateCompanyModules'])->name('admin.api.companies.modules.update');
        Route::get('/companies/{company}/history', [CompanyManagementController::class, 'apiCompanyHistory'])->name('admin.api.companies.history');

        // System Settings API
        // User Management API
        Route::get('/user-management/users', [UserManagementController::class, 'apiUsers'])->name('admin.api.user-management.users');
        Route::get('/user-management/users/{user}', [UserManagementController::class, 'apiGetUser'])->name('admin.api.user-management.users.show');
        Route::post('/user-management/users', [UserManagementController::class, 'apiStoreUser'])->name('admin.api.user-management.users.store');
        Route::put('/user-management/users/{user}', [UserManagementController::class, 'apiUpdateUser'])->name('admin.api.user-management.users.update');
        Route::delete('/user-management/users/{user}', [UserManagementController::class, 'apiDestroyUser'])->name('admin.api.user-management.users.destroy');

        Route::get('/user-management/roles', [UserManagementController::class, 'apiRoles'])->name('admin.api.user-management.roles');
        Route::get('/user-management/roles/{role}', [UserManagementController::class, 'apiGetRole'])->name('admin.api.user-management.roles.show');
        Route::post('/user-management/roles', [UserManagementController::class, 'apiStoreRole'])->name('admin.api.user-management.roles.store');
        Route::put('/user-management/roles/{role}', [UserManagementController::class, 'apiUpdateRole'])->name('admin.api.user-management.roles.update');
        Route::delete('/user-management/roles/{role}', [UserManagementController::class, 'apiDestroyRole'])->name('admin.api.user-management.roles.destroy');

        Route::get('/user-management/permissions', [UserManagementController::class, 'apiPermissions'])->name('admin.api.user-management.permissions');
        Route::post('/user-management/permissions', [UserManagementController::class, 'apiStorePermission'])->name('admin.api.user-management.permissions.store');
        Route::put('/user-management/permissions/{permission}', [UserManagementController::class, 'apiUpdatePermission'])->name('admin.api.user-management.permissions.update');
        Route::delete('/user-management/permissions/{permission}', [UserManagementController::class, 'apiDestroyPermission'])->name('admin.api.user-management.permissions.destroy');

        Route::get('/user-management/stats', [UserManagementController::class, 'apiStats'])->name('admin.api.user-management.stats');

        // Stats API
        Route::get('/stats', [AdminController::class, 'apiStats'])->name('admin.api.stats');

        // Screen Recording Management API
        Route::get('/screen-recording-management/preview', [ScreenRecordingManagementController::class, 'previewCount'])->name('admin.screen-recording-management.preview');
        Route::get('/screen-recording-management/sync-overview', [ScreenRecordingManagementController::class, 'syncOverview'])->name('admin.screen-recording-management.sync-overview');
    });
});

// Client Portal Authentication Routes (Public)
Route::prefix('client')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Client\ClientAuthController::class, 'showLoginForm'])
        ->name('client.login');

    Route::post('/login', [\App\Http\Controllers\Client\ClientAuthController::class, 'login'])
        ->name('client.login.submit');
});

// Client Portal Protected Routes
Route::middleware(['auth:client', 'client.company.active'])->prefix('client')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Client\ClientAuthController::class, 'logout'])
        ->name('client.logout');

    Route::get('/portal', [\App\Http\Controllers\Client\ClientPortalController::class, 'dashboard'])
        ->name('client.portal.dashboard');

    Route::get('/portal/projects', [\App\Http\Controllers\Client\ClientPortalController::class, 'projects'])
        ->name('client.portal.projects');

    Route::get('/portal/billing', [\App\Http\Controllers\Client\ClientPortalController::class, 'billing'])
        ->name('client.portal.billing');

    Route::get('/portal/documents', [\App\Http\Controllers\Client\ClientPortalController::class, 'documents'])
        ->name('client.portal.documents');

    Route::get('/contracts/{contractId}/pdf', [\App\Http\Controllers\Client\ClientPortalController::class, 'downloadContractPdf'])
        ->name('client.portal.contracts.pdf');

    // Client Portal API Routes
    Route::prefix('api')->group(function () {
        // Billing
        Route::get('/billing/invoices', [\App\Http\Controllers\Client\ClientPortalController::class, 'getInvoices'])
            ->name('client.portal.api.billing.invoices');

        Route::get('/billing/stats', [\App\Http\Controllers\Client\ClientPortalController::class, 'getBillingStats'])
            ->name('client.portal.api.billing.stats');

        Route::get('/contracts', [\App\Http\Controllers\Client\ClientPortalController::class, 'getContracts'])
            ->name('client.portal.api.contracts');

        // Employee monitoring
        Route::get('/employees', [\App\Http\Controllers\Client\ClientPortalController::class, 'getAssignedEmployees'])
            ->name('client.portal.employees');

        Route::get('/employees/{employeeId}/recordings', [\App\Http\Controllers\Client\ClientPortalController::class, 'getEmployeeRecordings'])
            ->name('client.portal.employee-recordings');

        Route::get('/recording/{id}/view', [\App\Http\Controllers\Client\ClientPortalController::class, 'viewRecording'])
            ->name('client.portal.view-recording');

        // Project management
        Route::get('/projects', [\App\Http\Controllers\Client\ClientPortalController::class, 'getProjects'])
            ->name('client.portal.api.projects');

        Route::get('/projects/{projectId}', [\App\Http\Controllers\Client\ClientPortalController::class, 'getProject'])
            ->name('client.portal.api.project');

        Route::get('/projects/{projectId}/time-tracking', [\App\Http\Controllers\Client\ClientPortalController::class, 'getProjectTimeTracking'])
            ->name('client.portal.api.project-time-tracking');

        Route::get('/time-tracking/summary', [\App\Http\Controllers\Client\ClientPortalController::class, 'getTimeTrackingSummary'])
            ->name('client.portal.api.time-tracking-summary');
    });

    // Client Portal Live View Routes
    Route::prefix('api/live-view')->group(function () {
        Route::get('/ice-config', [\App\Http\Controllers\Client\ClientLiveViewController::class, 'iceConfig'])
            ->name('client.portal.live-view.ice-config');

        Route::post('/sessions', [\App\Http\Controllers\Client\ClientLiveViewController::class, 'startSession'])
            ->name('client.portal.live-view.sessions.start');

        Route::post('/sessions/{liveViewSession}/end', [\App\Http\Controllers\Client\ClientLiveViewController::class, 'endSession'])
            ->name('client.portal.live-view.sessions.end');

        Route::get('/signals', [\App\Http\Controllers\Client\ClientLiveViewController::class, 'pullSignals'])
            ->middleware('throttle:live-view-signals')
            ->name('client.portal.live-view.signals.pull');

        Route::post('/signals', [\App\Http\Controllers\Client\ClientLiveViewController::class, 'sendSignal'])
            ->middleware('throttle:live-view-signals')
            ->name('client.portal.live-view.signals.send');
    });
});
