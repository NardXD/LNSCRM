<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\McpApiKey;
use Illuminate\Console\Command;

class CreateMcpApiKey extends Command
{
    protected $signature = 'mcp:create-key 
                            {--company= : Company ID (required)}
                            {--name= : Optional label for the key}
                            {--write : Grant write access (create/update records)}
                            {--tools= : Comma-separated allowed tool names (default: all)}';

    protected $description = 'Generate a new MCP API key for Claude AI integration';

    public function handle(): int
    {
        $companyId = (int) $this->option('company');
        if ($companyId <= 0) {
            $this->error('Company ID is required. Use: php artisan mcp:create-key --company=1');

            return 1;
        }

        $company = Company::find($companyId);
        if (! $company) {
            $this->error("Company with ID {$companyId} not found.");

            return 1;
        }

        $plainKey = McpApiKey::generateKey();
        $hash = McpApiKey::hashKey($plainKey);
        $prefix = McpApiKey::getKeyPrefix($plainKey);

        $canWrite = (bool) $this->option('write');

        $allowedTools = null;
        if ($this->option('tools')) {
            $allowedTools = collect(explode(',', (string) $this->option('tools')))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->values()
                ->all();
        }

        McpApiKey::create([
            'company_id' => $company->id,
            'name' => $this->option('name') ?: 'Claude MCP Key',
            'key_hash' => $hash,
            'key_prefix' => $prefix,
            'can_write' => $canWrite,
            'allowed_tools' => $allowedTools,
        ]);

        $baseUrl = config('app.url', 'https://crm.airbs.com');
        $mcpUrl = rtrim($baseUrl, '/').'/mcp';

        $this->newLine();
        $this->info('MCP API key created successfully!');
        $this->newLine();
        $this->line('<fg=yellow>API Key (save this - it will not be shown again):</>');
        $this->line($plainKey);
        $this->newLine();
        $this->line('<fg=cyan>Access level:</> '.($canWrite ? 'read + write' : 'read-only'));
        $this->line('<fg=cyan>Allowed endpoints:</> '.($allowedTools ? implode(', ', $allowedTools) : 'all'));
        $this->newLine();
        $this->line('<fg=cyan>MCP Server URL:</> '.$mcpUrl);
        $this->newLine();
        $this->line('Add to Claude:');
        $this->line("  Header: X-API-Key: {$plainKey}");
        $this->newLine();

        return 0;
    }
}
