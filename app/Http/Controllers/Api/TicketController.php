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
use App\Models\TicketEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TicketController extends Controller
{
    public function __construct(private readonly AiTicketServiceInterface $ai)
    {
        $this->authorizeResource(Ticket::class, 'ticket');
    }

    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $query = Ticket::with(['author', 'updater', 'closer', 'reopener'])
            ->where('created_by', auth()->id());

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
            'links' => [
                'first' => $paginated->url(1),
                'last' => $paginated->url($paginated->lastPage()),
                'prev' => $paginated->previousPageUrl(),
                'next' => $paginated->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'from' => $paginated->firstItem(),
                'last_page' => $paginated->lastPage(),
                'path' => $paginated->path(),
                'per_page' => $paginated->perPage(),
                'to' => $paginated->lastItem(),
                'total' => $paginated->total(),
            ],
        ];

        return response()->json($payload, 200);
    }

    public function trashed(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $query = Ticket::onlyTrashed()
            ->with(['author', 'updater', 'closer', 'reopener'])
            ->where('created_by', auth()->id());

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
            'links' => [
                'first' => $paginated->url(1),
                'last' => $paginated->url($paginated->lastPage()),
                'prev' => $paginated->previousPageUrl(),
                'next' => $paginated->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'from' => $paginated->firstItem(),
                'last_page' => $paginated->lastPage(),
                'path' => $paginated->path(),
                'per_page' => $paginated->perPage(),
                'to' => $paginated->lastItem(),
                'total' => $paginated->total(),
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
        $ticket->load(['author', 'updater', 'closer', 'reopener']);

        // Registrar evento de criação
        TicketEvent::createEvent(
            $ticket->id,
            'created',
            [
                'title' => $ticket->title,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
            ],
            auth()->id()
        );

        $data = (new TicketResource($ticket))->response()->getData(true)['data'];
        return Response::apiSuccess($data, ['code' => 201]);
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['author', 'updater', 'closer', 'reopener']);
        $data = (new TicketResource($ticket))->response()->getData(true)['data'];
        return Response::apiSuccess($data);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket)
    {
        $previousStatus = $ticket->status;
        $currentUserId = auth()->id();

        // Capturar valores antes da mudança
        $originalData = $ticket->getOriginal();
        $dirtyFields = [];

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

        // Capturar campos alterados
        foreach ($ticket->getDirty() as $field => $newValue) {
            $dirtyFields[$field] = [
                'before' => $originalData[$field] ?? null,
                'after' => $newValue,
            ];
        }

        // Verificar se status mudou antes de salvar
        $statusChanged = $ticket->isDirty('status');
        
        $ticket->save();
        $ticket->load(['author', 'updater', 'closer', 'reopener']);

        // Registrar eventos
        if ($statusChanged) {
            // Evento de mudança de status
            TicketEvent::createEvent(
                $ticket->id,
                'status_changed',
                [
                    'before' => $previousStatus,
                    'after' => $ticket->status,
                ],
                $currentUserId
            );
        }

        if (!empty($dirtyFields)) {
            // Evento de atualização (se houver campos alterados além do status)
            $updateFields = array_filter($dirtyFields, fn($field) => $field !== 'status', ARRAY_FILTER_USE_KEY);
            if (!empty($updateFields)) {
                TicketEvent::createEvent(
                    $ticket->id,
                    'updated',
                    [
                        'changed_fields' => $updateFields,
                    ],
                    $currentUserId
                );
            }
        }

        $data = (new TicketResource($ticket))->response()->getData(true)['data'];
        return Response::apiSuccess($data);
    }

    public function destroy(Ticket $ticket)
    {
        // Registrar evento antes de deletar
        TicketEvent::createEvent(
            $ticket->id,
            'deleted',
            [
                'title' => $ticket->title,
                'status' => $ticket->status,
            ],
            auth()->id()
        );

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
        $ticket->load(['author', 'updater', 'closer', 'reopener']);

        // Registrar evento de restauração
        TicketEvent::createEvent(
            $ticket->id,
            'restored',
            [
                'title' => $ticket->title,
                'status' => $ticket->status,
            ],
            auth()->id()
        );

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
        // Evita enfileirar se já está queued ou processing
        if (in_array($ticket->ai_summary_status, ['queued', 'processing'], true)) {
            $ticket->load(['author', 'updater', 'closer', 'reopener']);
            $data = (new TicketResource($ticket))->response()->getData(true)['data'];
            return Response::apiSuccess($data, [
                'code' => 409,
                'message' => 'Job já está em execução ou na fila',
                'status' => $ticket->ai_summary_status,
                'job' => 'summary',
            ]);
        }

        $ticket->ai_summary_status = 'queued';
        $ticket->ai_last_error = null;
        $ticket->save();
        $ticket->load(['author', 'updater', 'closer', 'reopener']);

        GenerateTicketSummary::dispatch($ticket->id);

        $data = (new TicketResource($ticket))
            ->additional(['meta' => ['status' => 'queued', 'job' => 'summary']])
            ->response()
            ->getData(true)['data'];

        return Response::apiSuccess($data, ['code' => 202, 'status' => 'queued', 'job' => 'summary']);
    }

    public function aiReply(Ticket $ticket)
    {
        // Evita enfileirar se já está queued ou processing
        if (in_array($ticket->ai_reply_status, ['queued', 'processing'], true)) {
            $ticket->load(['author', 'updater', 'closer', 'reopener']);
            $data = (new TicketResource($ticket))->response()->getData(true)['data'];
            return Response::apiSuccess($data, [
                'code' => 409,
                'message' => 'Job já está em execução ou na fila',
                'status' => $ticket->ai_reply_status,
                'job' => 'reply',
            ]);
        }

        $ticket->ai_reply_status = 'queued';
        $ticket->ai_last_error = null;
        $ticket->save();
        $ticket->load(['author', 'updater', 'closer', 'reopener']);

        GenerateTicketReply::dispatch($ticket->id);

        $data = (new TicketResource($ticket))
            ->additional(['meta' => ['status' => 'queued', 'job' => 'reply']])
            ->response()
            ->getData(true)['data'];

        return Response::apiSuccess($data, ['code' => 202, 'status' => 'queued', 'job' => 'reply']);
    }

    public function aiPriority(Ticket $ticket)
    {
        // Evita enfileirar se já está queued ou processing
        if (in_array($ticket->ai_priority_status, ['queued', 'processing'], true)) {
            $ticket->load(['author', 'updater', 'closer', 'reopener']);
            $data = (new TicketResource($ticket))->response()->getData(true)['data'];
            return Response::apiSuccess($data, [
                'code' => 409,
                'message' => 'Job já está em execução ou na fila',
                'status' => $ticket->ai_priority_status,
                'job' => 'priority',
            ]);
        }

        $ticket->ai_priority_status = 'queued';
        $ticket->ai_last_error = null;
        $ticket->save();
        $ticket->load(['author', 'updater', 'closer', 'reopener']);

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

    public function summary(Request $request)
    {
        $userId = auth()->id();
        $baseQuery = Ticket::where('created_by', $userId);

        // Total de tickets ativos (não deletados e não fechados)
        $totalActive = (clone $baseQuery)
            ->where('status', '!=', 'closed')
            ->count();

        // Por status
        $byStatus = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Por prioridade
        $byPriority = (clone $baseQuery)
            ->select('priority', DB::raw('count(*) as count'))
            ->whereNotNull('priority')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Fechados hoje
        $closedToday = (clone $baseQuery)
            ->where('status', 'closed')
            ->whereDate('closed_at', Carbon::today())
            ->count();

        // Fechados nos últimos 7 dias
        $closedLast7Days = (clone $baseQuery)
            ->where('status', 'closed')
            ->where('closed_at', '>=', Carbon::now()->subDays(7))
            ->count();

        // Fechados nos últimos 30 dias
        $closedLast30Days = (clone $baseQuery)
            ->where('status', 'closed')
            ->where('closed_at', '>=', Carbon::now()->subDays(30))
            ->count();

        // Tempo médio até fechar (em horas)
        $closedTickets = (clone $baseQuery)
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->whereNotNull('created_at')
            ->get();

        $avgTimeToClose = null;
        if ($closedTickets->count() > 0) {
            $totalHours = $closedTickets->sum(function ($ticket) {
                return $ticket->created_at->diffInHours($ticket->closed_at);
            });
            $avgTimeToClose = round($totalHours / $closedTickets->count(), 2);
        }

        $data = [
            'total_active' => $totalActive,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'closed' => [
                'today' => $closedToday,
                'last_7_days' => $closedLast7Days,
                'last_30_days' => $closedLast30Days,
            ],
            'average_time_to_close_hours' => $avgTimeToClose,
        ];

        return Response::apiSuccess($data);
    }

    public function backlog(Request $request)
    {
        $userId = auth()->id();
        $baseQuery = Ticket::where('created_by', $userId)
            ->where('status', '!=', 'closed');

        // Tickets abertos há mais de 2 dias
        $olderThan2Days = (clone $baseQuery)
            ->where('created_at', '<', Carbon::now()->subDays(2))
            ->count();

        // Tickets abertos há mais de 7 dias
        $olderThan7Days = (clone $baseQuery)
            ->where('created_at', '<', Carbon::now()->subDays(7))
            ->count();

        // Tickets abertos há mais de 14 dias
        $olderThan14Days = (clone $baseQuery)
            ->where('created_at', '<', Carbon::now()->subDays(14))
            ->count();

        // Top 10 mais antigos abertos
        $oldestOpen = (clone $baseQuery)
            ->with(['author', 'updater'])
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get();

        $data = [
            'counts' => [
                'older_than_2_days' => $olderThan2Days,
                'older_than_7_days' => $olderThan7Days,
                'older_than_14_days' => $olderThan14Days,
            ],
            'oldest_open' => TicketResource::collection($oldestOpen)->response()->getData(true)['data'],
        ];

        return Response::apiSuccess($data);
    }

    public function activity(Request $request)
    {
        $userId = auth()->id();
        $perPage = min((int) $request->query('per_page', 50), 100);

        // Buscar eventos dos tickets do usuário
        $events = TicketEvent::whereHas('ticket', function ($query) use ($userId) {
            $query->where('created_by', $userId);
        })
        ->with(['ticket:id,title,status'])
        ->orderBy('created_at', 'desc')
        ->limit($perPage)
        ->get();

        $activities = $events->map(function ($event) {
            $type = $event->type;
            $aiType = null;

            if (in_array($event->type, ['ai_summary_done', 'ai_reply_done', 'ai_priority_done'], true)) {
                $type = 'ai_done';
                $aiType = str_replace('ai_', '', str_replace('_done', '', $event->type));
            }

            $activity = [
                'type' => $type,
                'ticket_id' => $event->ticket_id,
                'ticket_title' => $event->ticket->title ?? 'Ticket #' . $event->ticket_id,
                'user_id' => $event->created_by,
                'timestamp' => $event->created_at->toIso8601String(),
                'meta' => $event->meta,
            ];

            // Adicionar subtype para status_changed
            if ($event->type === 'status_changed' && $event->meta) {
                $activity['subtype'] = $event->meta['after'] === 'closed' ? 'closed' : 'reopened';
                $activity['status'] = $event->meta['after'] ?? null;
            }

            // Adicionar ai_type para eventos de AI
            if ($aiType) {
                $activity['ai_type'] = $aiType;
            }

            return $activity;
        })->toArray();

        return Response::apiSuccess($activities);
    }

    public function ticketActivity(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $perPage = min((int) $request->query('per_page', 20), 100);

        $events = TicketEvent::where('ticket_id', $ticket->id)
            ->with(['user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($events);
    }
}
