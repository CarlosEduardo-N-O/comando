<?php

namespace App\Http\Requests\Demanda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * { "assumir": true } — colaborador do destino assume a demanda.
 * { "responsavel_id": 10 | null } — gestor/administrador distribui ou reatribui.
 */
class AtribuicaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'assumir' => ['sometimes', 'boolean'],
            'responsavel_id' => ['exclude_if:assumir,true', 'present', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['responsavel_id.present' => 'Informe o responsável ou envie "assumir": true.'];
    }
}
