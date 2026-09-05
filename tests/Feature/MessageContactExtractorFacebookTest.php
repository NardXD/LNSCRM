<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Services\MessageContactExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageContactExtractorFacebookTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ignores_phones_and_emails_that_only_appear_in_outbound_messages(): void
    {
        $company = Company::create([
            'name' => 'LNS',
            'subdomain' => 'lns-extract-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-extract-'.uniqid().'@lns.test',
            'timezone' => 'UTC',
        ]);

        $conversation = FacebookConversation::create([
            'company_id' => $company->id,
            'channel' => 'messenger',
            'peer_id' => 'peer-'.uniqid(),
            'name' => 'Messenger User',
            'last_message_at' => now(),
        ]);

        FacebookMessage::create([
            'company_id' => $company->id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'text' => "Hello! I filled out your form.\n".
                'Email: murderbox04@gmail.com'."\n".
                'Full name: Jeson Broniola'."\n".
                'Phone number: 0951 332 0904',
            'sent_at' => now(),
        ]);

        // A canned "we're away" auto-reply that lists the company's own branch
        // numbers/support email — this must never be attributed to the customer.
        FacebookMessage::create([
            'company_id' => $company->id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'text' => "Greetings from Loc&Stor 24/7!\n".
                'Trunkline: (02) 7902 1898'."\n".
                'Pasig: 0916 567 3004'."\n".
                'North Edsa: 0917 703 4159'."\n".
                'Email us at support@locnstor247.com',
            'sent_at' => now()->addMinute(),
        ]);

        $extracted = app(MessageContactExtractor::class)->fromFacebookConversation($conversation);

        $this->assertSame(['+639513320904'], $extracted['phones']);
        $this->assertSame(['murderbox04@gmail.com'], $extracted['emails']);
    }
}
