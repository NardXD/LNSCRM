<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::first();
        $year = now()->year;

        return [
            'company_id' => $company?->id ?? 1,
            'client_id' => null,
            'client_name' => fake()->company(),
            'ticket_number' => 'TKT-'.$year.'-'.str_pad((string) fake()->unique()->numberBetween(100, 99999), 5, '0', STR_PAD_LEFT),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'assigned_to' => User::where('company_id', $company?->id ?? 1)->inRandomOrder()->first()?->id,
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => fake()->randomElement(['open', 'in-progress', 'pending', 'resolved', 'closed']),
            'category' => fake()->randomElement(['technical', 'billing', 'feature', 'general', null]),
            'sla' => fake()->randomElement(['compliant', 'warning', 'breached']),
            'image_path' => null,
        ];
    }
}
