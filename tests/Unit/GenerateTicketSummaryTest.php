<?php

namespace Tests\Unit\Jobs;

use App\Contracts\AiTicketServiceInterface;
use App\Jobs\GenerateTicketSummary;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateTicketSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_gera_summary_e_marca_done(): void
    {
        // Fake da IA
        $this->app->bind(AiTicketServiceInterface::class, function () {
            return new class implements AiTicketServiceInterface {
                public function summarize(Ticket $ticket): string
                {
                    return 'Resumo gerado';
                }

                public function suggestReply(Ticket $ticket): string
                {
                    return 'Resposta';
                }

                public function classifyPriority(Ticket $ticket): string
                {
                    return 'low';
                }
            };
        });

        $ticket = Ticket::factory()->create([
            'ai_summary' => null,
            'ai_summary_status' => 'queued',
            'ai_last_error' => null,
        ]);

        $job = new GenerateTicketSummary($ticket->id);
        $job->handle(app(AiTicketServiceInterface::class));

        $ticket->refresh();

        $this->assertSame('Resumo gerado', $ticket->ai_summary);
        $this->assertSame('done', $ticket->ai_summary_status);
        $this->assertNull($ticket->ai_last_error);
    }
}