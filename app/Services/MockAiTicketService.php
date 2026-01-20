<?php

namespace App\Services;

use App\Contracts\AiTicketServiceInterface;
use App\Models\Ticket;
use Illuminate\Support\Str;

class MockAiTicketService implements AiTicketServiceInterface
{
    public function summarize(Ticket $ticket): string
    {
        $text = Str::of($ticket->description)->squish()->toString();
        $short = mb_substr($text, 0, 240);
        if (mb_strlen($text) > 240) $short .= '...';

        return "[MOCK] {$short}";
    }

    public function suggestReply(Ticket $ticket): string
    {
        return "[MOCK] Olá! Obrigado por avisar. Para eu te ajudar mais rápido, pode confirmar: "
            . "(1) seu e-mail de acesso, (2) se o erro acontece em outro navegador/dispositivo e (3) o horário aproximado do ocorrido. "
            . "Se puder, envie um print da mensagem. Com isso eu te passo os próximos passos.";
    }

    public function classifyPriority(Ticket $ticket): string
    {
        $d = mb_strtolower($ticket->title . ' ' . $ticket->description);

        if (str_contains($d, 'pagamento') || str_contains($d, 'cobran') || str_contains($d, 'bloque') || str_contains($d, 'não consigo entrar') || str_contains($d, 'não entra')) {
            return 'high';
        }

        if (str_contains($d, 'erro') || str_contains($d, 'falha') || str_contains($d, 'bug') || str_contains($d, 'instável')) {
            return 'medium';
        }

        return 'low';
    }
}