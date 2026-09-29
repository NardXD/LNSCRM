<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TwilioFlexIntegration;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppSendQueuedTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithConnectedWhatsApp(): Company
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-wa-queue',
            'status' => 'active',
            'email' => 'admin-wa-queue@lns.test',
        ]);

        TwilioFlexIntegration::query()->create([
            'company_id' => $company->id,
            'account_sid' => 'AC'.str_repeat('a', 32),
            'auth_token' => Crypt::encryptString('secret-token'),
            'webhook_key' => 'test-webhook-key',
            'is_active' => true,
        ]);

        WhatsAppIntegration::query()->create([
            'company_id' => $company->id,
            'from_number' => '+15550001111',
            'webhook_key' => 'wa-webhook-key',
            'is_active' => true,
        ]);

        return $company;
    }

    private function makeAgent(Company $company): User
    {
        $role = Role::query()->create([
            'name' => 'Agent',
            'slug' => 'agent-wa-queue',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_whatsapp',
            'slug' => 'view_whatsapp',
            'display_name' => 'View WhatsApp',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        return User::query()->create([
            'name' => 'Agent',
            'email' => 'agent-wa-queue@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_sending_whatsapp_text_queues_the_twilio_call_instead_of_blocking(): void
    {
        Queue::fake();

        $company = $this->makeCompanyWithConnectedWhatsApp();
        $user = $this->makeAgent($company);

        $conversation = WhatsAppConversation::query()->create([
            'company_id' => $company->id,
            'wa_id' => '15559998888',
            'name' => 'Jane Doe',
            'unread_count' => 0,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/whatsapp/conversations/'.$conversation->id.'/messages', [
                'type' => 'text',
                'text' => 'Hello from the CRM',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'queued');

        $message = WhatsAppMessage::query()->where('whatsapp_conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame('queued', $message->status);
        $this->assertNull($message->wamid);

        Queue::assertPushed(SendWhatsAppMessageJob::class, function (SendWhatsAppMessageJob $job) use ($message) {
            return $job->whatsAppMessageId === $message->id && $job->body === 'Hello from the CRM';
        });
    }

    public function test_location_message_with_a_note_queues_the_exact_combined_body_sent_to_twilio(): void
    {
        Queue::fake();

        $company = $this->makeCompanyWithConnectedWhatsApp();
        $user = $this->makeAgent($company);
        $conversation = WhatsAppConversation::query()->create([
            'company_id' => $company->id,
            'wa_id' => '15559998888',
            'name' => 'Jane Doe',
            'unread_count' => 0,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/whatsapp/conversations/'.$conversation->id.'/messages', [
                'type' => 'location',
                'text' => 'Meet me here',
                'latitude' => 1.23,
                'longitude' => 4.56,
            ]);

        $response->assertCreated();

        $message = WhatsAppMessage::query()->where('whatsapp_conversation_id', $conversation->id)->firstOrFail();

        // The row keeps the raw note for display...
        $this->assertSame('Meet me here', $message->text);

        // ...but the job that will actually call Twilio must carry the note WITH
        // the coordinates folded in, matching what the old synchronous code sent.
        Queue::assertPushed(SendWhatsAppMessageJob::class, function (SendWhatsAppMessageJob $job) use ($message) {
            return $job->whatsAppMessageId === $message->id
                && $job->body === 'Meet me here Location: 1.23, 4.56';
        });
    }

    public function test_send_job_does_not_resend_a_message_that_already_left_the_queued_state(): void
    {
        $company = $this->makeCompanyWithConnectedWhatsApp();
        $conversation = WhatsAppConversation::query()->create([
            'company_id' => $company->id,
            'wa_id' => '15559998888',
            'name' => 'Jane Doe',
            'unread_count' => 0,
        ]);
        $message = WhatsAppMessage::query()->create([
            'company_id' => $company->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'text' => 'Already sent',
            'wamid' => 'SM'.str_repeat('a', 32),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        (new SendWhatsAppMessageJob($message->id, 'Already sent', null))
            ->handle(app(\App\Services\TwilioCompanyService::class));

        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('SM'.str_repeat('a', 32), $message->fresh()->wamid);
    }
}
