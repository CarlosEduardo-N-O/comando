<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificacaoResource;
use App\Models\Demanda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Notificações persistidas do próprio usuário (sem WebSocket: o frontend consulta periodicamente). */
class NotificacaoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'nao_lidas' => ['nullable', 'in:0,1,true,false'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $usuario = $request->user();

        $pagina = $usuario->notifications()
            ->reorder()->orderByDesc('created_at')->orderByDesc('id')
            ->when(filter_var($filtros['nao_lidas'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->whereNull('read_at'))
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();

        // Revalida o acesso a cada consulta: quem perdeu acesso vê a demanda como indisponível.
        $ids = $pagina->getCollection()->pluck('demanda_id')->filter()->unique()->all();
        $visiveis = Demanda::query()->visiveisPara($usuario)->whereIn('id', $ids)->pluck('id')->flip();
        $pagina->getCollection()->each(fn ($n) => $n->demanda_disponivel = $visiveis->has($n->demanda_id));

        return NotificacaoResource::collection($pagina);
    }

    public function contador(Request $request): JsonResponse
    {
        return response()->json(['data' => ['nao_lidas' => $request->user()->unreadNotifications()->count()]]);
    }

    public function ler(Request $request, string $id): JsonResponse
    {
        $notificacao = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notificacao->markAsRead();

        return response()->json(['data' => ['id' => $notificacao->id, 'lida' => true]]);
    }

    public function lerTodas(Request $request): JsonResponse
    {
        $atualizadas = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['marcadas' => $atualizadas]]);
    }
}
