<?php

namespace App\Jobs;

use App\Contracts\AiTicketServiceInterface;
use App\Models\Ticket;
use App\Models\TicketEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyTicketPriority implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 3;

    public function __construct(public int $ticketId) {}

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(AiTicketServiceInterface $ai): void
    {
        $ticket = Ticket::find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $ticket->ai_priority_status = 'processing';
        $ticket->ai_last_error = null;
        $ticket->save();

        try {
            $oldPriority = $ticket->priority;
            $ticket->priority = $ai->classifyPriority($ticket);
            $ticket->ai_priority_status = 'done';
            $ticket->ai_last_run_at = now();
            $ticket->save();

            // Registrar evento de AI concluído
            TicketEvent::createEvent(
                $ticket->id,
                'ai_priority_done',
                [
                    'before' => $oldPriority,
                    'after' => $ticket->priority,
                ],
                null // Jobs não têm usuário autenticado
            );
        } catch (\Throwable $e) {
            $ticket->ai_priority_status = 'failed';
            $ticket->ai_last_error = $e->getMessage();
            $ticket->ai_last_run_at = now();
            $ticket->save();

            throw $e;
        }
    }
}