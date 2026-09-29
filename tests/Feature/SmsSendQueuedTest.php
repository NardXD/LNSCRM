<?php

namespace Tests\Feature;

use App\Jobs\SendSmsMessageJob;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Models\TwilioFlexIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsSendQueuedTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithConnectedTwilio(): Company
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-sms-queue',
            'status' => 'active',
            'email' => 'admin-sms-queue@lns.test',
        ]);

        TwilioFlexIntegration::query()->create([
            'company_id' => $company->id,
            'account_sid' => 'AC'.str_repeat('a', 32),
            'auth_token' => Crypt::encryptString('secret-token'),
            'webhook_key' => 'test-webhook-key',
            'is_active' => true,
        ]);

        return $company;
    }

    private function makeAgent(Company $company): User
    {
        $role = Role::query()->create([
            'name' => 'Agent',
            'slug' => 'agent-sms-queue',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $sendPermission = Permission::query()->create([
            'name' => 'send_sms',
            'slug' => 'send_sms',
            'display_name' => 'Send SMS',
            'company_id' => $company->id,
        ]);
        $viewPermission = Permission::query()->create([
            'name' => 'view_sms',
            'slug' => 'view_sms',
            'display_name' => 'View SMS',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach([$sendPermission->id, $viewPermission->id]);

        return User::query()->create([
            'name' => 'Agent',
            'email' => 'agent-sms-queue@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
            'twilio_sms_number' => '+15550001111',
        ]);
    }

    public function test_sending_sms_queues_the_twilio_call_instead_of_blocking(): void
    {
        Queue::fake();

        $company = $this->makeCompanyWithConnectedTwilio();
        $user = $this->makeAgent($company);

        $conversation = SmsConversation::query()->create([
            'company_id' => $company->id,
            'peer_phone' => '+15559998888',
            'name' => 'Jane Doe',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/sms/conversations/'.$conversation->id.'/messages', [
                'body' => 'Hello from the CRM',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'queued');

        $message = SmsMessage::query()->where('sms_conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame('queued', $message->status);
        $this->assertStringStartsWith('pending-', $message->message_sid);

        Queue::assertPushed(SendSmsMessageJob::class, fn (SendSmsMessageJob $job) => $job->smsMessageId === $message->id);
    }

    public function test_send_job_does_not_resend_a_message_that_already_left_the_queued_state(): void
    {
        $company = $this->makeCompanyWithConnectedTwilio();
        $conversation = SmsConversation::query()->create([
            'company_id' => $company->id,
            'peer_phone' => '+15559998888',
            'name' => 'Jane Doe',
        ]);
        $message = SmsMessage::query()->create([
            'company_id' => $company->id,
            'sms_conversation_id' => $conversation->id,
            'message_sid' => 'SM'.str_repeat('a', 32),
            'direction' => 'outbound',
            'from_number' => '+15550001111',
            'to_number' => '+15559998888',
            'body' => 'Already sent',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        (new SendSmsMessageJob($message->id))->handle(app(\App\Services\TwilioCompanyService::class));

        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('SM'.str_repeat('a', 32), $message->fresh()->message_sid);
    }

    public function test_send_job_marks_message_failed_when_twilio_is_not_connected(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-sms-noconn',
            'status' => 'active',
            'email' => 'admin-sms-noconn@lns.test',
        ]);
        $conversation = SmsConversation::query()->create([
            'company_id' => $company->id,
            'peer_phone' => '+15559998888',
            'name' => 'Jane Doe',
        ]);
        $message = SmsMessage::query()->create([
            'company_id' => $company->id,
            'sms_conversation_id' => $conversation->id,
            'message_sid' => 'pending-test',
            'direction' => 'outbound',
            'from_number' => '+15550001111',
            'to_number' => '+15559998888',
            'body' => 'No integration configured',
            'status' => 'queued',
            'sent_at' => now(),
        ]);

        (new SendSmsMessageJob($message->id))->handle(app(\App\Services\TwilioCompanyService::class));

        $this->assertSame('failed', $message->fresh()->status);
    }
}
