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
            'ai_summary' => $this->ai_summary,
            'ai_suggested_reply' => $this->ai_suggested_reply,
            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}