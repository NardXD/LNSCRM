<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\OutlookMailAccount;
use App\Models\SharedInbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CleanupPersonalInboxContaminationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_contaminated_personal_inbox_without_deleting(): void
    {
        [$user, $inbox] = $this->contaminatedPersonal();

        $this->artisan('inbox:cleanup-personal-contamination')
            ->expectsOutputToContain((string) $inbox->id)
            ->expectsOutputToContain('Dry-run only')
            ->assertSuccessful();

        $this->assertDatabaseHas('inbox_conversations', [
            'shared_inbox_id' => $inbox->id,
        ]);
        $this->assertNotNull($inbox->fresh()->outlook_mail_account_id);
    }

    public function test_apply_wipes_conversations_and_can_disconnect(): void
    {
        [$user, $inbox] = $this->contaminatedPersonal();

        $this->artisan('inbox:cleanup-personal-contamination', [
            '--apply' => true,
            '--disconnect' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('inbox_conversations', [
            'shared_inbox_id' => $inbox->id,
        ]);

        $inbox->refresh();
        $this->assertNull($inbox->outlook_mail_account_id);
        $this->assertNull($inbox->external_mailbox);
        $this->assertNull($inbox->folder_sync_state);
    }

    public function test_clean_personal_inbox_is_not_flagged(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-personal-clean',
            'status' => 'active',
            'email' => 'admin-clean@lns.test',
        ]);
        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);
        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'alice@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Personal',
            'email' => 'alice@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        $this->artisan('inbox:cleanup-personal-contamination')
            ->expectsOutputToContain('No contaminated personal inboxes found.')
            ->assertSuccessful();
    }

    /**
     * @return array{0: User, 1: SharedInbox}
     */
    private function contaminatedPersonal(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-personal-contam',
            'status' => 'active',
            'email' => 'admin-contam@lns.test',
        ]);
        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);
        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'alice@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Personal',
            'email' => 'alice@example.com',
            'external_mailbox' => 'sales@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
            'folder_sync_state' => [
                'inbox' => [
                    'next_link' => 'https://graph.microsoft.com/v1.0/users/sales@example.com/mailFolders/inbox/messages',
                    'fetched' => 25,
                    'backfill_done' => false,
                ],
            ],
        ]);

        InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-foreign-1',
            'status' => 'open',
            'subject' => 'Team mailbox thread',
            'from_email' => 'customer@example.com',
            'from_name' => 'Customer',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);

        return [$user, $inbox];
    }
}
