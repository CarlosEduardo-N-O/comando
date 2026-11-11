<?php

namespace App\Http\Requests\Demanda;

use Illuminate\Foundation\Http\FormRequest;

/** Prazo de atendimento em ISO 8601 (ex.: 2026-03-10T18:00:00-03:00) ou null para remover. */
class PrazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prazo_atendimento' => ['present', 'nullable', 'date', 'after:now'],
            'justificativa' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return ['prazo_atendimento.after' => 'O prazo de atendimento deve ser uma data futura.'];
    }
}
