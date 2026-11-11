<?php

namespace App\Http\Controllers;

use App\Http\Requests\CadastroBasicoRequest;
use App\Http\Resources\CadastroBasicoResource;
use App\Models\Categoria;

class CategoriaController extends CadastroBasicoController
{
    protected function modelo(): string
    {
        return Categoria::class;
    }

    public function show(Categoria $categoria): CadastroBasicoResource
    {
        return $this->exibir($categoria);
    }

    public function update(CadastroBasicoRequest $request, Categoria $categoria): CadastroBasicoResource
    {
        return $this->alterar($request, $categoria);
    }
}
