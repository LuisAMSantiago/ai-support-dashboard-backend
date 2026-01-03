<?php

namespace Tests\Feature;

use App\Jobs\GenerateTicketSummary;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketAiQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_summary_enfileira_job_e_marca_status_queued(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'ai_summary_status' => 'idle',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/tickets/{$ticket->id}/ai-summary");

        $response->assertStatus(202);

        $ticket->refresh();
        $this->assertSame('queued', $ticket->ai_summary_status);

        Queue::assertPushed(
            GenerateTicketSummary::class,
            fn ($job) => $job->ticketId === $ticket->id
        );
    }
}