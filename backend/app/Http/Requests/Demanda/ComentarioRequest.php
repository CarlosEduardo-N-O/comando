<?php

namespace App\Http\Requests\Demanda;

use App\Http\Requests\Concerns\RegrasAnexos;
use Illuminate\Foundation\Http\FormRequest;

class ComentarioRequest extends FormRequest
{
    use RegrasAnexos;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'mensagem' => ['required', 'string', 'max:20000'],
            ...$this->regrasAnexos(),
        ];
    }

    public function messages(): array
    {
        return $this->mensagensAnexos();
    }
}
