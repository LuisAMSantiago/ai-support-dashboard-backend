<?php

namespace App\Services;

use App\Contracts\AiTicketServiceInterface;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAiTicketService implements AiTicketServiceInterface
{
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1/chat/completions';
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    public function summarize(Ticket $ticket): string
    {
        $system = 'Você é um assistente de suporte técnico. Seu dever é resumir o ticket abaixo para agentes internos, em 2-3 frases. Mantenha o tom factual. Responda exclusivamente em português brasileiro. Responda apenas o resumo do ticket, sem introduções ou conclusões.';
        $user = $this->buildTicketPrompt($ticket);

        return $this->requestChatCompletion($system, $user);
    }

    public function suggestReply(Ticket $ticket): string
    {
        $system = 'Você é um assistente de suporte técnico. Seu dever é escrever uma resposta útil ao cliente. Use um tom amigavel e profissional. Mantenha abaixo de 120 palavras. Evite markdown. Responda exclusivamente em português brasileiro.';
        $user = $this->buildTicketPrompt($ticket);

        return $this->requestChatCompletion($system, $user);
    }

    public function classifyPriority(Ticket $ticket): string
    {
        $system = 'Você é um assistente de suporte técnico. Seu dever é classificar a prioridade do ticket abaixo como baixa, média ou alta, baseado na urgência e impacto relatados pelo cliente. Responda exclusivamente com uma única palavra: low, medium, ou high.';
        $user = $this->buildTicketPrompt($ticket);

        $result = Str::lower(trim($this->requestChatCompletion($system, $user)));

        if (in_array($result, ['low', 'medium', 'high'], true)) {
            return $result;
        }

        foreach (['high', 'medium', 'low'] as $candidate) {
            if (str_contains($result, $candidate)) {
                return $candidate;
            }
        }

        return 'low';
    }

    private function requestChatCompletion(string $systemPrompt, string $userPrompt): string
    {
        $apiKey = config('services.openai.key');

        if (!is_string($apiKey) || trim($apiKey) == '') {
            throw new \RuntimeException('OPENAI_API_KEY is not set.');
        }

        $baseUrl = config('services.openai.base_url');
        $model = config('services.openai.model');

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(20)
            ->post($baseUrl, [
                'model' => $model,
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            throw new \RuntimeException("OpenAI request failed: {$status} {$body}");
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (!is_string($content)) {
            throw new \RuntimeException('OpenAI response missing content.');
        }

        return trim($content);
    }

    private function buildTicketPrompt(Ticket $ticket): string
    {
        $title = $this->normalizeText($ticket->title, 400);
        $description = $this->normalizeText($ticket->description, 3000);

        return "Titulo do ticket: {$title}\nDescrição do ticket: {$description}";
    }

    private function normalizeText(?string $text, int $limit): string
    {
        $value = Str::of((string) $text)->squish()->toString();

        if (mb_strlen($value) > $limit) {
            $value = mb_substr($value, 0, $limit) . '...';
        }

        return $value;
    }
}