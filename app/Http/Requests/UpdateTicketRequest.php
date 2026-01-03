<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['sometimes','string','max:255'],
            'description' => ['sometimes','string'],
            'priority' => ['sometimes','nullable','in:low,medium,high'],
            'status' => ['sometimes','in:open,in_progress,waiting,resolved,closed'],
        ];
    }
}