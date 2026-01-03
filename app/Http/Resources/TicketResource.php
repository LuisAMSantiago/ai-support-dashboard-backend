<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TicketResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'title' => $this->title,
            'description' => $this->description,

            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,

            'ai_summary' => $this->ai_summary,
            'ai_suggested_reply' => $this->ai_suggested_reply,
            'ai_summary_status' => $this->ai_summary_status,
            'ai_reply_status' => $this->ai_reply_status,
            'ai_priority_status' => $this->ai_priority_status,
            'ai_last_error' => $this->ai_last_error,
            'ai_last_run_at' => optional($this->ai_last_run_at)?->toISOString(),

            'closed_at' => optional($this->closed_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
