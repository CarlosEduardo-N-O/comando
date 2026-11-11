<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Setores e categorias: nome, descrição e situação. */
class CadastroBasicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar') ?? false;
    }

    public function rules(): array
    {
        $tabela = $this->routeIs('setores.*') ? 'setores' : 'categorias';
        $id = $this->route('setor') ?? $this->route('categoria');
        $obrigatorio = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'nome' => [$obrigatorio, 'string', 'min:2', 'max:120', Rule::unique($tabela, 'nome')->ignore($id)],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
