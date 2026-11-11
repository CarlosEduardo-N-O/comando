<?php

namespace App\Http\Controllers;

use App\Http\Requests\CadastroBasicoRequest;
use App\Http\Resources\CadastroBasicoResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Base para setores e categorias (somente administrador). Itens inativos
 * permanecem no histórico, mas não podem ser usados em novas demandas.
 */
abstract class CadastroBasicoController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelo(): string;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('administrar');
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:200'],
            'ativo' => ['nullable', 'in:0,1,true,false'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->modelo()::query()->orderBy('nome');
        if (! empty($filtros['busca'])) {
            $query->where('nome', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['busca']).'%');
        }
        if (isset($filtros['ativo'])) {
            $query->where('ativo', filter_var($filtros['ativo'], FILTER_VALIDATE_BOOLEAN));
        }

        return CadastroBasicoResource::collection($query->paginate($filtros['por_pagina'] ?? 20)->withQueryString());
    }

    public function store(CadastroBasicoRequest $request): JsonResponse
    {
        $item = new ($this->modelo());
        $item->fill($request->validated())->save();

        return (new CadastroBasicoResource($item))->response()->setStatusCode(201);
    }

    protected function exibir(Model $item): CadastroBasicoResource
    {
        Gate::authorize('administrar');

        return new CadastroBasicoResource($item);
    }

    protected function alterar(CadastroBasicoRequest $request, Model $item): CadastroBasicoResource
    {
        $item->fill($request->validated())->save();

        return new CadastroBasicoResource($item);
    }
}
