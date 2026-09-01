<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Services\LeadStoreganiseMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadStoreganiseMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_lead_fields_to_storeganise_custom_fields(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-mapper',
            'status' => 'active',
            'email' => 'mapper@lns.test',
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'title' => 'Ms',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address' => '81 Newport Blvd',
            'city' => 'Pasay',
            'postal_code' => '1300',
            'date_of_birth' => '1990-05-15',
            'company_name' => 'Loc & Stor',
            'source' => 'Facebook',
            'customer_type' => Lead::CUSTOMER_TYPE_RESIDENTIAL,
            'residential_type' => 'Condominium',
            'storage_reason' => 'Moving',
            'alt_title' => 'Mr',
            'alt_first_name' => 'Bea',
            'alt_last_name' => 'Santos',
            'alt_address' => '12 Rizal St',
            'alt_city' => 'Pasig',
            'alt_postal_code' => '1600',
            'notes' => 'Needs climate control',
            'status' => 'new',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'bea@example.com', 'Alternate');
        $lead->addIdentity(LeadIdentity::TYPE_PHONE, '09179876543', 'Alternate');

        $payload = (new LeadStoreganiseMapper)->toUserPayload(
            $lead,
            ['id' => 'site-nwp', 'code' => 'nwp'],
            'jane@example.com',
            includePassword: false,
        );

        $this->assertSame('1990-05-15', $payload['dateOfBirth']);
        $this->assertSame('Needs climate control', $payload['note']);
        $this->assertSame('Loc & Stor', $payload['companyName']);

        $custom = $payload['customFields'];
        $this->assertSame('Ms', $custom['lns_mrms']);
        $this->assertSame('Pasay', $custom['lns_city']);
        $this->assertSame('1300', $custom['lns_postal']);
        $this->assertSame('1990-05-15', $custom['lns_dob']);
        $this->assertSame('Facebook', $custom['lns_hearAbout']);
        $this->assertSame('Residential', $custom['lns_customerType']);
        $this->assertSame('Condominium', $custom['lns_residentialType']);
        $this->assertSame('Moving', $custom['lns_residentialReason']);
        $this->assertSame('nwp', $custom['lns_siteCode']);
        $this->assertSame('Mr', $custom['lns_altTitle']);
        $this->assertSame('Bea', $custom['lns_altFirstName']);
        $this->assertSame('Santos', $custom['lns_altLastName']);
        $this->assertSame('12 Rizal St', $custom['lns_altAddress']);
        $this->assertSame('Pasig', $custom['lns_altCity']);
        $this->assertSame('1600', $custom['lns_altPostal']);
        $this->assertSame('09179876543', $custom['lns_altPhone']);
        $this->assertSame('bea@example.com', $custom['lns_altEmail']);
    }
}
