<?php

namespace App\Http\Requests\Demanda;

use App\Models\Demanda;
use App\Services\ConsultaDemandas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarDemandasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'contexto' => ['nullable', Rule::in(ConsultaDemandas::CONTEXTOS)],
            'busca' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(Demanda::STATUS)],
            'prioridade' => ['nullable', Rule::in(Demanda::PRIORIDADES)],
            'categoria_id' => ['nullable', 'integer'],
            'setor_destino_id' => ['nullable', 'integer'],
            'setor_origem_id' => ['nullable', 'integer'],
            'responsavel_id' => ['nullable', 'regex:/^(\d+|nenhum)$/'],
            'criado_de' => ['nullable', 'date_format:Y-m-d'],
            'criado_ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:criado_de'],
            'atrasadas' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'ordem' => ['nullable', Rule::in(ConsultaDemandas::ORDENACOES)],
            'direcao' => ['nullable', Rule::in(['asc', 'desc'])],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:'.config('comando.paginacao.maximo')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
