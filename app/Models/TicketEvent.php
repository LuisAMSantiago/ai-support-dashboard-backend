<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvent extends Model
{
    protected $fillable = [
        'ticket_id',
        'type',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function ($event) {
            $event->created_at = now();
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Helper method to create a ticket event
     */
    public static function createEvent(
        int $ticketId,
        string $type,
        ?array $meta = null,
        ?int $userId = null
    ): self {
        return self::create([
            'ticket_id' => $ticketId,
            'type' => $type,
            'meta' => $meta,
            'created_by' => $userId ?? auth()->id(),
        ]);
    }
}
