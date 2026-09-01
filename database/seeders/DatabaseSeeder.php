<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ModuleSeeder::class);
        $this->call(PlanSeeder::class);
        $this->call(CompanySeeder::class);

        $this->call(AddLiveViewPermissionsSeeder::class);
        $this->call(AddSmsModuleSeeder::class);
        $this->call(AddBroadcastMessagingSeeder::class);
        $this->call(LeadFollowUpDaySeeder::class);

        $this->call(UserSeeder::class);
    }
}
