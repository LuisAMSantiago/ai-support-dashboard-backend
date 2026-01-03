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
            $searchTerm = '%' . strtolower($q) . '%';
            $query->where(function ($sub) use ($searchTerm) {
                $sub->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm]);
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

    public function trashed(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $query = Ticket::onlyTrashed()->where('created_by', auth()->id());

        if ($q = $request->query('q')) {
            $searchTerm = '%' . strtolower($q) . '%';
            $query->where(function ($sub) use ($searchTerm) {
                $sub->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm]);
            });
        }

        $sort = $request->query('sort', '-deleted_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, ['created_at', 'updated_at', 'deleted_at', 'priority', 'status'], true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('deleted_at', 'desc');
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
        $data['priority'] = $data['priority'] ?? 'medium';

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
        $previousStatus = $ticket->status;
        $currentUserId = auth()->id();

        $ticket->fill($request->validated());

        // Sempre atualiza updated_by quando editar
        $ticket->updated_by = $currentUserId;

        // regra: fechou -> seta closed_at e closed_by; reabriu -> limpa e seta reopened_by
        if ($ticket->isDirty('status')) {
            if ($ticket->status === 'closed') {
                // Fechando o ticket
                $ticket->closed_at = now();
                $ticket->closed_by = $currentUserId;
                $ticket->reopened_by = null; // limpa se havia reabertura anterior
            } elseif ($previousStatus === 'closed') {
                // Reabrindo o ticket (status mudou de 'closed' para outro)
                $ticket->closed_at = null;
                $ticket->reopened_by = $currentUserId;
                // closed_by permanece com o valor anterior (quem fechou)
            } else {
                // Mudança de status que não é fechamento nem reabertura
                $ticket->closed_at = null;
                $ticket->reopened_by = null;
            }
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

    public function restore(int $id)
    {
        // Busca o ticket deletado (soft delete)
        $ticket = Ticket::onlyTrashed()->findOrFail($id);

        // Verifica se o usuário tem permissão para restaurar
        $this->authorize('restore', $ticket);

        // Restaura o ticket
        $ticket->restore();

        // Atualiza updated_by para rastrear quem restaurou
        $ticket->updated_by = auth()->id();
        $ticket->save();

        $data = (new TicketResource($ticket))->response()->getData(true)['data'];
        return Response::apiSuccess($data, ['message' => 'Ticket restaurado com sucesso', 'code' => 200]);
    }

    public function forceDelete(int $id)
    {
        // Busca o ticket deletado (soft delete)
        $ticket = Ticket::onlyTrashed()->findOrFail($id);

        // Verifica se o usuário tem permissão para excluir permanentemente
        $this->authorize('forceDelete', $ticket);

        // Exclui permanentemente o ticket
        $ticket->forceDelete();

        return Response::apiSuccess(null, ['message' => 'Ticket excluído permanentemente', 'code' => 200]);
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