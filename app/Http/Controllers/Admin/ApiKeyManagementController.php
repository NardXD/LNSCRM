<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\McpServerController;
use App\Models\Company;
use App\Models\McpApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiKeyManagementController extends Controller
{
    /**
     * Display API key management for all companies.
     */
    public function index(): View
    {
        $companies = Company::query()->orderBy('name')->get();
        $apiKeys = McpApiKey::query()->with('company')->latest()->get();
        $toolGroups = McpServerController::availableTools();

        return view('admin.api-key-management', compact('companies', 'apiKeys', 'toolGroups'));
    }

    /**
     * Create a new API key for a company. The plaintext key is shown once.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'can_write' => ['nullable', 'boolean'],
            'allowed_tools' => ['nullable', 'array'],
            'allowed_tools.*' => ['string', 'in:'.implode(',', McpServerController::toolNames())],
        ]);

        $plainKey = McpApiKey::generateKey();

        McpApiKey::create([
            'company_id' => (int) $validated['company_id'],
            'name' => $validated['name'],
            'key_hash' => McpApiKey::hashKey($plainKey),
            'key_prefix' => McpApiKey::getKeyPrefix($plainKey),
            'can_write' => $request->boolean('can_write'),
            'allowed_tools' => empty($validated['allowed_tools']) ? null : array_values($validated['allowed_tools']),
        ]);

        return redirect()->route('admin.api-key-management')
            ->with('success', 'API key created.')
            ->with('new_api_key', $plainKey);
    }

    /**
     * Update an existing key's label, access level, and allowed endpoints.
     */
    public function update(Request $request, McpApiKey $apiKey): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'can_write' => ['nullable', 'boolean'],
            'allowed_tools' => ['nullable', 'array'],
            'allowed_tools.*' => ['string', 'in:'.implode(',', McpServerController::toolNames())],
        ]);

        $apiKey->update([
            'name' => $validated['name'],
            'can_write' => $request->boolean('can_write'),
            'allowed_tools' => empty($validated['allowed_tools']) ? null : array_values($validated['allowed_tools']),
        ]);

        return redirect()->route('admin.api-key-management')
            ->with('success', 'API key updated.');
    }

    /**
     * Revoke (delete) an API key.
     */
    public function destroy(McpApiKey $apiKey): RedirectResponse
    {
        $apiKey->delete();

        return redirect()->route('admin.api-key-management')
            ->with('success', 'API key revoked.');
    }
}
