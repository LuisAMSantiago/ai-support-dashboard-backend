<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(3),
            'ai_summary' => null,
            'ai_suggested_reply' => null,
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'status' => 'open',
            'assigned_to' => null,
            'closed_at' => null,
        ];
    }

    public function highPriority(): static
    {
        return $this->state(fn () => ['priority' => 'high']);
    }

    public function lowPriority(): static
    {
        return $this->state(fn () => ['priority' => 'low']);
    }
}