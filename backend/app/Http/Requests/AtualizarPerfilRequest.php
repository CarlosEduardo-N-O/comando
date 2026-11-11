<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** O próprio usuário altera apenas o nome. Demais campos são ignorados. */
class AtualizarPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:255']];
    }
}
