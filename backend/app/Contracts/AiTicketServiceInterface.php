<?php

namespace App\Contracts;

use App\Models\Ticket;

interface AiTicketServiceInterface
{
    public function summarize(Ticket $ticket): string;
    public function suggestReply(Ticket $ticket): string;
    public function classifyPriority(Ticket $ticket): string; // low|medium|high
}