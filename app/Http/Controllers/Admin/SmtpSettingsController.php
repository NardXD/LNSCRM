<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SmtpSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;

class SmtpSettingsController extends Controller
{
    public function __construct(protected SmtpSettingsService $smtp) {}

    public function index(): View
    {
        $settings = $this->smtp->getSettings();
        $isConfigured = $this->smtp->isConfigured();

        return view('admin.smtp-settings', compact('settings', 'isConfigured'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'mailer'       => 'required|in:smtp,sendmail,log',
            'host'         => 'required_if:mailer,smtp|nullable|string|max:255',
            'port'         => 'required_if:mailer,smtp|nullable|integer|min:1|max:65535',
            'encryption'   => 'nullable|in:tls,ssl,starttls',
            'username'     => 'nullable|string|max:255',
            'password'     => 'nullable|string|max:255',
            'from_address' => 'required|email|max:255',
            'from_name'    => 'required|string|max:255',
        ]);

        $this->smtp->saveSettings($request->only(
            'mailer', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'
        ));

        return redirect()->route('admin.smtp-settings')->with('success', 'SMTP settings saved successfully.');
    }

    public function test(Request $request): RedirectResponse
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        // Apply DB settings before sending test
        $this->smtp->applyToConfig();

        try {
            Mail::html(
                '<p>This is a test email from <strong>'.config('app.name').'</strong>. Your SMTP configuration is working correctly.</p>',
                function ($message) use ($request) {
                    $message->to($request->test_email)->subject('SMTP Test — '.config('app.name'));
                }
            );

            return redirect()->route('admin.smtp-settings')->with('success', 'Test email sent successfully to '.$request->test_email);
        } catch (\Exception $e) {
            return redirect()->route('admin.smtp-settings')->with('error', 'Failed to send test email: '.$e->getMessage());
        }
    }
}
