<?php

namespace App\Http\Controllers;

use App\Http\Requests\CadastroBasicoRequest;
use App\Http\Resources\CadastroBasicoResource;
use App\Models\Setor;

class SetorController extends CadastroBasicoController
{
    protected function modelo(): string
    {
        return Setor::class;
    }

    public function show(Setor $setor): CadastroBasicoResource
    {
        return $this->exibir($setor);
    }

    public function update(CadastroBasicoRequest $request, Setor $setor): CadastroBasicoResource
    {
        return $this->alterar($request, $setor);
    }
}
