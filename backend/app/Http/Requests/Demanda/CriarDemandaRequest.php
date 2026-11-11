<?php

namespace App\Http\Requests\Demanda;

use App\Http\Requests\Concerns\RegrasAnexos;
use App\Models\Demanda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitante, setor de origem, status e protocolo não são aceitos aqui:
 * o servidor os define a partir do usuário autenticado.
 */
class CriarDemandaRequest extends FormRequest
{
    use RegrasAnexos;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'min:3', 'max:200'],
            'descricao' => ['required', 'string', 'max:20000'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('ativo', true)],
            'setor_destino_id' => ['required', 'integer', Rule::exists('setores', 'id')->where('ativo', true)],
            'responsavel_id' => ['nullable', 'integer'],
            'prioridade' => ['required', Rule::in(Demanda::PRIORIDADES)],
            'prazo_desejado' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            ...$this->regrasAnexos(),
        ];
    }

    public function messages(): array
    {
        return [
            'categoria_id.exists' => 'Selecione uma categoria ativa.',
            'setor_destino_id.exists' => 'Selecione um setor de destino ativo.',
            ...$this->mensagensAnexos(),
        ];
    }
}
