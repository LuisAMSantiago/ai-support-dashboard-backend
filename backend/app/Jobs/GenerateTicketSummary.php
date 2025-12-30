<?php

namespace App\Jobs;

use App\Contracts\AiTicketServiceInterface;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateTicketSummary implements ShouldQueue
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
            return; // ticket deletado, nada a fazer
        }

        $ticket->ai_summary = $ai->summarize($ticket);
        $ticket->save();
    }
}