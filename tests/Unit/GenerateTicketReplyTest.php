<?php

namespace Tests\Unit\Jobs;

use App\Contracts\AiTicketServiceInterface;
use App\Jobs\GenerateTicketReply;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateTicketReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_quando_falha_marca_failed_e_salva_erro(): void
    {
        $this->app->bind(AiTicketServiceInterface::class, function () {
            return new class implements AiTicketServiceInterface {
                public function summarize(Ticket $ticket): string
                {
                    return 'Resumo';
                }

                public function suggestReply(Ticket $ticket): string
                {
                    throw new \RuntimeException('IA caiu');
                }

                public function classifyPriority(Ticket $ticket): string
                {
                    return 'low';
                }
            };
        });

        $ticket = Ticket::factory()->create([
            'ai_reply_status' => 'queued',
            'ai_last_error' => null,
        ]);

        $job = new GenerateTicketReply($ticket->id);

        try {
            $job->handle(app(AiTicketServiceInterface::class));
            $this->fail('Era esperado lançar exception');
        } catch (\RuntimeException $e) {
            // esperado
        }

        $ticket->refresh();

        $this->assertSame('failed', $ticket->ai_reply_status);
        $this->assertNotNull($ticket->ai_last_error);
        $this->assertStringContainsString('IA caiu', $ticket->ai_last_error);
    }
}