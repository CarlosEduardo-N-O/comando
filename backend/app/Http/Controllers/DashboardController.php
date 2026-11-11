<?php

namespace App\Http\Controllers;

use App\Models\Demanda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Indicadores calculados somente sobre demandas que o usuário pode ver. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $visiveis = fn () => Demanda::query()->visiveisPara($usuario);

        $indicadores = [
            'minhas_abertas' => $visiveis()->where('solicitante_id', $usuario->id)
                ->whereNotIn('status', Demanda::STATUS_ENCERRADOS)->count(),
            'atribuidas_a_mim' => $visiveis()->where('responsavel_id', $usuario->id)
                ->whereIn('status', Demanda::STATUS_EM_ATENDIMENTO)->count(),
            'aguardando_minha_resposta' => $visiveis()->where('solicitante_id', $usuario->id)
                ->where('status', Demanda::STATUS_AGUARDANDO)->count(),
            'aguardando_minha_confirmacao' => $visiveis()->where('solicitante_id', $usuario->id)
                ->where('status', Demanda::STATUS_RESOLVIDA)->count(),
            'atrasadas' => $visiveis()->atrasadas()->count(),
            'fila_do_setor' => Demanda::query()->whereNull('responsavel_id')
                ->where('setor_destino_id', $usuario->setor_id ?? 0)
                ->whereIn('status', Demanda::STATUS_EM_ATENDIMENTO)->count(),
        ];

        return response()->json(['data' => $indicadores]);
    }
}
