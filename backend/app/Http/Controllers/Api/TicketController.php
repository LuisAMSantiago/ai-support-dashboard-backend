<?php

namespace App\Http\Controllers\Api;

use App\Jobs\GenerateTicketSummary;
use App\Jobs\GenerateTicketReply;
use App\Jobs\ClassifyTicketPriority;
use App\Contracts\AiTicketServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use Illuminate\Support\Facades\Response;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly AiTicketServiceInterface $ai)
    {
        $this->authorizeResource(Ticket::class, 'ticket');
    }

    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $query = Ticket::where('created_by', auth()->id());

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

        if (in_array($column, ['created_at', 'updated_at', 'priority', 'status'], true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $paginated = $query->paginate($perPage)->appends($request->query());

        $payload = [
            'data' => TicketResource::collection($paginated)->response()->getData(true)['data'],
            'meta' => [
                'success' => true,
                'code' => 200,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
        ];

        return response()->json($payload, 200);
    }

    public function store(StoreTicketRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $ticket = Ticket::create($data);

        $data = (new TicketResource($ticket))->response()->getData(true)['data'];
        return Response::apiSuccess($data, ['code' => 201]);
    }

    public function show(Ticket $ticket)
    {
        $data = (new TicketResource($ticket))->response()->getData(true)['data'];
        return Response::apiSuccess($data);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket)
    {
        $ticket->fill($request->validated());

        // regra: fechou -> seta closed_at; reabriu -> limpa
        if ($ticket->isDirty('status')) {
            $ticket->closed_at = $ticket->status === 'closed' ? now() : null;
        }

        $ticket->save();

        $data = (new TicketResource($ticket))->response()->getData(true)['data'];
        return Response::apiSuccess($data);
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        return Response::apiSuccess(null, ['message' => 'Deleted', 'code' => 200]);
    }

    public function aiSummary(Ticket $ticket)
    {   
        $ticket->ai_summary_status = 'queued';
        $ticket->ai_last_error = null;
        $ticket->save();

        GenerateTicketSummary::dispatch($ticket->id);

        $data = (new TicketResource($ticket))
            ->additional(['meta' => ['status' => 'queued', 'job' => 'summary']])
            ->response()
            ->getData(true)['data'];

        return Response::apiSuccess($data, ['code' => 202, 'status' => 'queued', 'job' => 'summary']);
    }

    public function aiReply(Ticket $ticket)
    {
        $ticket->ai_reply_status = 'queued';
        $ticket->ai_last_error = null;
        $ticket->save();

        GenerateTicketReply::dispatch($ticket->id);

        $data = (new TicketResource($ticket))
            ->additional(['meta' => ['status' => 'queued', 'job' => 'reply']])
            ->response()
            ->getData(true)['data'];

        return Response::apiSuccess($data, ['code' => 202, 'status' => 'queued', 'job' => 'reply']);
    }

    public function aiPriority(Ticket $ticket)
    {
        $ticket->ai_priority_status = 'queued';
        $ticket->ai_last_error = null;
        $ticket->save();

        ClassifyTicketPriority::dispatch($ticket->id);

        $data = (new TicketResource($ticket))
            ->additional(['meta' => ['status' => 'queued', 'job' => 'priority']])
            ->response()
            ->getData(true)['data'];

        return Response::apiSuccess($data, ['code' => 202, 'status' => 'queued', 'job' => 'priority']);
    }

    public function aiStatus(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $data = [
            'ai_summary_status' => $ticket->ai_summary_status,
            'ai_reply_status' => $ticket->ai_reply_status,
            'ai_priority_status' => $ticket->ai_priority_status,
            'ai_last_error' => $ticket->ai_last_error,
            'ai_last_run_at' => $ticket->ai_last_run_at,
        ];

        return Response::apiSuccess($data);
    }
}