<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Services\LeadActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeadAssignedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignee_is_notified_when_a_new_lead_is_assigned_to_them(): void
    {
        [$company, $manager, $agent] = $this->companyWithManagerAndAgent();
        Notification::fake();

        $this->actingAs($manager);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'status' => 'new',
            'assigned_to' => $agent->id,
        ]);

        app(LeadActivityService::class)->recordAssignment($lead, null, $agent->id, reason: 'created');

        Notification::assertSentTo($agent, LeadAssignedNotification::class, function (LeadAssignedNotification $notification) use ($lead) {
            $payload = $notification->toArray($notification->lead->assignedUser ?? new User);

            return $notification->lead->is($lead)
                && $notification->isNew === true
                && $payload['type'] === 'lead_assigned'
                && $payload['event'] === 'created'
                && str_contains($payload['summary'], 'New lead assigned to you');
        });
        Notification::assertNotSentTo($manager, LeadAssignedNotification::class);
    }

    public function test_assignee_is_notified_when_an_existing_lead_is_reassigned_to_them(): void
    {
        [$company, $manager, $agent] = $this->companyWithManagerAndAgent();
        Notification::fake();

        $this->actingAs($manager);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Acme Storage',
            'status' => 'new',
            'assigned_to' => $manager->id,
        ]);
        $lead->wasRecentlyCreated = false;

        app(LeadActivityService::class)->recordAssignment($lead, $manager->id, $agent->id);

        Notification::assertSentTo($agent, LeadAssignedNotification::class, function (LeadAssignedNotification $notification) {
            return $notification->isNew === false
                && str_contains($notification->toArray(new User)['summary'], 'was assigned to you');
        });
    }

    public function test_self_assignment_does_not_notify_the_actor(): void
    {
        [$company, $manager] = $this->companyWithManagerAndAgent();
        Notification::fake();

        $this->actingAs($manager);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Self Assign',
            'status' => 'new',
        ]);

        app(LeadActivityService::class)->recordAssignment($lead, null, $manager->id);

        Notification::assertNothingSent();
    }

    public function test_header_notifications_api_includes_assigned_leads(): void
    {
        [$company, $manager, $agent] = $this->companyWithManagerAndAgent();

        $this->actingAs($manager);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Header Lead',
            'status' => 'new',
            'assigned_to' => $agent->id,
        ]);
        app(LeadActivityService::class)->recordAssignment($lead, null, $agent->id, reason: 'created');

        $response = $this->actingAs($agent)->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 1);

        $this->assertSame('lead_assigned', $response->json('data.notifications.0.data.type'));
        $this->assertSame($lead->id, $response->json('data.notifications.0.data.lead_id'));
        $this->assertStringContainsString('/leads?lead='.$lead->id, $response->json('data.notifications.0.data.url'));
    }

    /**
     * @return array{0: Company, 1: User, 2: User}
     */
    private function companyWithManagerAndAgent(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns',
            'status' => 'active',
            'email' => 'admin@lns.test',
        ]);

        $manager = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $agent = User::query()->create([
            'name' => 'Agent',
            'email' => 'agent@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        return [$company, $manager, $agent];
    }
}
