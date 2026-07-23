<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\McpApiKey;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class McpServerToolsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private string $apiKey;

    private string $writeApiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'MCP Co',
            'subdomain' => 'mcpco',
            'status' => 'active',
            'email' => 'admin@mcpco.test',
        ]);

        $this->user = User::query()->create([
            'name' => 'Jane Employee',
            'email' => 'jane@mcpco.test',
            'password' => Hash::make('password123'),
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $this->apiKey = McpApiKey::generateKey();
        McpApiKey::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Read Only Key',
            'key_hash' => McpApiKey::hashKey($this->apiKey),
            'key_prefix' => McpApiKey::getKeyPrefix($this->apiKey),
            'can_write' => false,
        ]);

        $this->writeApiKey = McpApiKey::generateKey();
        McpApiKey::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Write Key',
            'key_hash' => McpApiKey::hashKey($this->writeApiKey),
            'key_prefix' => McpApiKey::getKeyPrefix($this->writeApiKey),
            'can_write' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(string $name, array $arguments = [], ?string $key = null): TestResponse
    {
        return $this->withHeader('X-API-Key', $key ?? $this->apiKey)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => $arguments],
            ]);
    }

    public function test_mcp_requires_api_key(): void
    {
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertUnauthorized();
    }

    public function test_tools_list_includes_new_module_tools(): void
    {
        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
            ]);

        $response->assertOk();

        $toolNames = collect($response->json('result.tools'))->pluck('name')->all();

        foreach (['get_tasks', 'get_employees', 'get_time_tracking', 'get_leave_requests', 'get_quotations', 'get_payroll_reports', 'get_knowledge_base', 'get_teams', 'get_departments'] as $expected) {
            $this->assertContains($expected, $toolNames);
        }
    }

    public function test_get_employees_returns_company_users(): void
    {
        $response = $this->callTool('get_employees');

        $response->assertOk()->assertJsonPath('result.isError', false);

        $payload = json_decode($response->json('result.content.0.text'), true);

        $this->assertCount(1, $payload['employees']);
        $this->assertSame('Jane Employee', $payload['employees'][0]['name']);
        $this->assertSame('jane@mcpco.test', $payload['employees'][0]['email']);
    }

    public function test_get_teams_returns_team_with_leader(): void
    {
        Team::query()->create([
            'company_id' => $this->company->id,
            'leader_id' => $this->user->id,
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $response = $this->callTool('get_teams', ['active' => true]);

        $response->assertOk();

        $payload = json_decode($response->json('result.content.0.text'), true);

        $this->assertCount(1, $payload['teams']);
        $this->assertSame('Engineering', $payload['teams'][0]['name']);
        $this->assertSame('Jane Employee', $payload['teams'][0]['leader']);
    }

    public function test_tools_are_scoped_to_api_key_company(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'Other Co',
            'subdomain' => 'otherco',
            'status' => 'active',
            'email' => 'admin@otherco.test',
        ]);

        User::query()->create([
            'name' => 'Outsider',
            'email' => 'outsider@otherco.test',
            'password' => Hash::make('password123'),
            'company_id' => $otherCompany->id,
            'status' => 'active',
        ]);

        $response = $this->callTool('get_employees');

        $payload = json_decode($response->json('result.content.0.text'), true);

        $this->assertCount(1, $payload['employees']);
        $this->assertSame('jane@mcpco.test', $payload['employees'][0]['email']);
    }

    public function test_key_restricted_to_allowed_tools(): void
    {
        $restricted = McpApiKey::generateKey();
        McpApiKey::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Restricted Key',
            'key_hash' => McpApiKey::hashKey($restricted),
            'key_prefix' => McpApiKey::getKeyPrefix($restricted),
            'can_write' => false,
            'allowed_tools' => ['get_employees'],
        ]);

        $this->callTool('get_employees', [], $restricted)
            ->assertOk()
            ->assertJsonPath('result.isError', false);

        $blocked = $this->callTool('get_clients', [], $restricted);
        $blocked->assertOk()->assertJsonPath('result.isError', true);
        $this->assertStringContainsString('not permitted', $blocked->json('result.content.0.text'));
    }

    public function test_read_only_key_cannot_use_write_tools(): void
    {
        $response = $this->callTool('create_client', ['name' => 'Blocked Client']);

        $response->assertOk()->assertJsonPath('result.isError', true);

        $this->assertStringContainsString('read-only', $response->json('result.content.0.text'));
        $this->assertDatabaseMissing('clients', ['name' => 'Blocked Client']);
    }

    public function test_write_key_can_create_client(): void
    {
        $response = $this->callTool('create_client', [
            'name' => 'Acme Corp',
            'email' => 'hello@acme.test',
        ], $this->writeApiKey);

        $response->assertOk()->assertJsonPath('result.isError', false);

        $payload = json_decode($response->json('result.content.0.text'), true);
        $this->assertTrue($payload['success']);

        $this->assertDatabaseHas('clients', [
            'company_id' => $this->company->id,
            'name' => 'Acme Corp',
            'email' => 'hello@acme.test',
        ]);
    }

    public function test_write_key_can_update_client(): void
    {
        $client = Client::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Old Name',
            'contact_person' => 'Contact',
            'email' => 'old@client.test',
            'status' => 'active',
        ]);

        $this->callTool('update_client', [
            'id' => $client->id,
            'name' => 'New Name',
        ], $this->writeApiKey)->assertOk()->assertJsonPath('result.isError', false);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'New Name',
        ]);
    }

    public function test_create_client_validates_required_fields(): void
    {
        $response = $this->callTool('create_client', [], $this->writeApiKey);

        $payload = json_decode($response->json('result.content.0.text'), true);

        $this->assertSame('Validation failed', $payload['error']);
        $this->assertArrayHasKey('name', $payload['details']);
    }

    public function test_write_tools_are_company_scoped(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'Other Co',
            'subdomain' => 'otherco',
            'status' => 'active',
            'email' => 'admin@otherco.test',
        ]);

        $otherClient = Client::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Client',
            'contact_person' => 'Contact',
            'email' => 'foreign@client.test',
            'status' => 'active',
        ]);

        $response = $this->callTool('update_client', [
            'id' => $otherClient->id,
            'name' => 'Hacked',
        ], $this->writeApiKey);

        $payload = json_decode($response->json('result.content.0.text'), true);
        $this->assertSame('Client not found', $payload['error']);

        $this->assertDatabaseHas('clients', [
            'id' => $otherClient->id,
            'name' => 'Foreign Client',
        ]);
    }
}
