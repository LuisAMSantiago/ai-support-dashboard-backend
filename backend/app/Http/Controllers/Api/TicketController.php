<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AiTicketServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly AiTicketServiceInterface $ai) {}

    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $query = Ticket::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $sort = $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, ['created_at','updated_at','priority','status'], true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return TicketResource::collection($query->paginate($perPage));
    }

    public function store(StoreTicketRequest $request)
    {
        $ticket = Ticket::create($request->validated());
        return response()->json($ticket, 201);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket)
    {
        $ticket->fill($request->validated());

        // regra: fechou -> seta closed_at; reabriu -> limpa
        if ($ticket->isDirty('status')) {
            if ($ticket->status === 'closed') {
                $ticket->closed_at = now();
            } else {
                $ticket->closed_at = null;
            }
        }

        $ticket->save();
        return response()->json($ticket);
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return response()->noContent();
    }

    public function show(Ticket $ticket)
    {
        return new TicketResource($ticket);
    }

    public function aiSummary(Ticket $ticket)
    {
        $ticket->ai_summary = $this->ai->summarize($ticket);
        $ticket->save();

        return response()->json($ticket);
    }

    public function aiReply(Ticket $ticket)
    {
        $ticket->ai_suggested_reply = $this->ai->suggestReply($ticket);
        $ticket->save();

        return response()->json($ticket);
    }

    public function aiPriority(Ticket $ticket)
    {
        $ticket->priority = $this->ai->classifyPriority($ticket);
        $ticket->save();

        return response()->json($ticket);
    }
}