<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Administração de usuários. Não existe exclusão: usuários são inativados. */
class UsuarioController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('administrar');
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:200'],
            'setor_id' => ['nullable', 'integer'],
            'perfil' => ['nullable', Rule::in(User::PERFIS)],
            'ativo' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()->with('setor')->orderBy('name');
        if (! empty($filtros['busca'])) {
            $termo = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['busca']).'%';
            $query->where(fn ($q) => $q->where('name', 'ilike', $termo)->orWhere('email', 'ilike', $termo));
        }
        $query->when($filtros['setor_id'] ?? null, fn ($q, $setor) => $q->where('setor_id', $setor))
            ->when($filtros['perfil'] ?? null, fn ($q, $perfil) => $q->where('perfil', $perfil))
            ->when(isset($filtros['ativo']), fn ($q) => $q->where('ativo', filter_var($filtros['ativo'], FILTER_VALIDATE_BOOLEAN)));

        return UsuarioResource::collection($query->paginate($filtros['por_pagina'] ?? 20)->withQueryString());
    }

    public function store(UsuarioRequest $request, UsuarioService $usuarios): JsonResponse
    {
        $usuario = $usuarios->criar($request->validated());

        return (new UsuarioResource($usuario->load('setor')))->response()->setStatusCode(201);
    }

    public function show(User $usuario): UsuarioResource
    {
        Gate::authorize('administrar');

        return new UsuarioResource($usuario->load('setor'));
    }

    public function update(UsuarioRequest $request, User $usuario, UsuarioService $usuarios): UsuarioResource
    {
        return new UsuarioResource($usuarios->atualizar($usuario->id, $request->validated())->load('setor'));
    }
}
