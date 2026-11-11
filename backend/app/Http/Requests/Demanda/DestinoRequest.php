<?php

namespace App\Http\Requests\Demanda;

use Illuminate\Foundation\Http\FormRequest;

class DestinoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'setor_destino_id' => ['required', 'integer'],
            'responsavel_id' => ['nullable', 'integer'],
            'justificativa' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return ['justificativa.required' => 'Informe a justificativa da transferência.'];
    }
}
