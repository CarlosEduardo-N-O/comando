<?php

namespace App\Http\Requests\Demanda;

use App\Models\Demanda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "justificativa" carrega a mensagem exigida por cada transição: informação
 * solicitada, descrição da solução, motivo da rejeição ou do cancelamento.
 */
class StatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Demanda::STATUS)],
            'justificativa' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
