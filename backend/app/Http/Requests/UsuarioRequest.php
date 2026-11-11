<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Criação (POST) e alteração parcial (PATCH) de usuários pelo administrador. */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar') ?? false;
    }

    public function rules(): array
    {
        $criando = $this->isMethod('post');
        $obrigatorio = $criando ? 'required' : 'sometimes';
        $id = $this->route('usuario');

        return [
            'name' => [$obrigatorio, 'string', 'min:2', 'max:255'],
            'email' => [$obrigatorio, 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$criando ? 'required' : 'nullable', 'string', Password::min(10)->letters()->numbers()],
            'setor_id' => [$obrigatorio, 'integer', Rule::exists('setores', 'id')],
            'perfil' => [$obrigatorio, Rule::in(User::PERFIS)],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }
}
