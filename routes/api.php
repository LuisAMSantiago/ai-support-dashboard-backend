<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\AuthController;

Route::get('/health', fn () => response()->json(['ok' => true]));

Route::prefix('auth')->group(function () {
	Route::post('/register', [AuthController::class, 'register']);
	Route::post('/login', [AuthController::class, 'login']);
	Route::post('/logout', [AuthController::class, 'logout']);
	Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware('auth:sanctum')->group(function () {
	Route::get('/tickets', [TicketController::class, 'index']);
	Route::get('/tickets/summary', [TicketController::class, 'summary']);
	Route::get('/tickets/backlog', [TicketController::class, 'backlog']);
	Route::get('/tickets/activity', [TicketController::class, 'activity']);
	Route::get('/tickets/{ticket}/activity', [TicketController::class, 'ticketActivity']);
	Route::get('/tickets/trashed', [TicketController::class, 'trashed']);
	Route::post('/tickets', [TicketController::class, 'store']);
	Route::patch('/tickets/{ticket}', [TicketController::class, 'update']);
	Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

	Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
	
	Route::post('/tickets/{id}/restore', [TicketController::class, 'restore']);
	Route::delete('/tickets/{id}/force', [TicketController::class, 'forceDelete']);

	Route::post('/tickets/{ticket}/ai-summary', [TicketController::class, 'aiSummary']);
	Route::post('/tickets/{ticket}/ai-reply', [TicketController::class, 'aiReply']);
	Route::post('/tickets/{ticket}/ai-priority', [TicketController::class, 'aiPriority']);
	Route::get('/tickets/{ticket}/ai-status', [TicketController::class, 'aiStatus']);
});
