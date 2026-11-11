<?php

namespace App\Http\Requests\Demanda;

use App\Models\Demanda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrioridadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prioridade' => ['required', Rule::in(Demanda::PRIORIDADES)],
            'justificativa' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
