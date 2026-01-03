<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->title) ? trim($this->title) : $this->title,
            'description' => is_string($this->description) ? trim($this->description) : $this->description,
            'priority' => is_string($this->priority) ? trim($this->priority) : $this->priority,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título é obrigatório.',
            'title.string' => 'O título deve ser um texto.',
            'title.min' => 'O título deve ter no mínimo :min caracteres.',
            'title.max' => 'O título deve ter no máximo :max caracteres.',

            'description.required' => 'A descrição é obrigatória.',
            'description.string' => 'A descrição deve ser um texto.',
            'description.min' => 'A descrição deve ter no mínimo :min caracteres.',
            'description.max' => 'A descrição deve ter no máximo :max caracteres.',

            'priority.in' => 'A prioridade deve ser: low, medium ou high.',
        ];
    }
}
