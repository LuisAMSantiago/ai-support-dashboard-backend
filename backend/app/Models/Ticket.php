<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'created_by',
        'ai_summary',
        'ai_suggested_reply',
        'ai_summary_status',
        'ai_reply_status',
        'ai_priority_status',
        'ai_last_error',
        'ai_last_run_at',
        'priority',
        'status',
        'assigned_to',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'assigned_to' => 'integer',
        'created_by' => 'integer',
        'ai_last_run_at' => 'datetime',
        'ai_summary_status' => 'string',
        'ai_reply_status' => 'string',
        'ai_priority_status' => 'string',
        'ai_last_error' => 'string',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
