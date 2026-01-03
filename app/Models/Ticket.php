<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'created_by',
        'updated_by',
        'closed_by',
        'reopened_by',
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
        'updated_by' => 'integer',
        'closed_by' => 'integer',
        'reopened_by' => 'integer',
        'ai_last_run_at' => 'datetime',
        'ai_summary_status' => 'string',
        'ai_reply_status' => 'string',
        'ai_priority_status' => 'string',
        'ai_last_error' => 'string',
    ];

    protected $attributes = [
        'priority' => 'medium',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopener()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

}
