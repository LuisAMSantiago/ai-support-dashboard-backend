<?php

namespace App\Jobs;

use App\Contracts\AiTicketServiceInterface;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateTicketReply implements ShouldQueue
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

        $ticket->ai_reply_status = 'processing';
        $ticket->ai_last_error = null;
        $ticket->save();

        try {
            $ticket->ai_suggested_reply = $ai->suggestReply($ticket);
            $ticket->ai_reply_status = 'done';
            $ticket->ai_last_run_at = now();
            $ticket->save();
        } catch (\Throwable $e) {
            $ticket->ai_reply_status = 'failed';
            $ticket->ai_last_error = $e->getMessage();
            $ticket->ai_last_run_at = now();
            $ticket->save();

            throw $e;
        }
    }
}