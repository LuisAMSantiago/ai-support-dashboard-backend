<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TicketController;

Route::get('/health', fn () => response()->json(['ok' => true]));

Route::get('/tickets', [TicketController::class, 'index']);
Route::post('/tickets', [TicketController::class, 'store']);
Route::patch('/tickets/{ticket}', [TicketController::class, 'update']);
Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

Route::get('/tickets/{ticket}', [TicketController::class, 'show']);

Route::post('/tickets/{ticket}/ai-summary', [TicketController::class, 'aiSummary']);
Route::post('/tickets/{ticket}/ai-reply', [TicketController::class, 'aiReply']);
Route::post('/tickets/{ticket}/ai-priority', [TicketController::class, 'aiPriority']);