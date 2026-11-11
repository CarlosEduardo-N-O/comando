<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consultas limitadas para preencher formulários, disponíveis a qualquer
 * usuário autenticado. Retornam apenas itens ativos com id e nome (e o setor
 * do usuário) — sem e-mail, perfil ou dados administrativos.
 */
class OpcoesController extends Controller
{
    public function setores(): JsonResponse
    {
        return response()->json(['data' => Setor::query()->ativos()->orderBy('nome')->get(['id', 'nome'])]);
    }

    public function categorias(): JsonResponse
    {
        return response()->json(['data' => Categoria::query()->ativos()->orderBy('nome')->get(['id', 'nome'])]);
    }

    public function usuarios(Request $request): JsonResponse
    {
        $filtros = $request->validate(['setor_id' => ['nullable', 'integer']]);

        $usuarios = User::query()->ativos()
            ->whereHas('setor', fn ($q) => $q->where('ativo', true))
            ->when($filtros['setor_id'] ?? null, fn ($q, $setor) => $q->where('setor_id', $setor))
            ->orderBy('name')
            ->get(['id', 'name', 'setor_id']);

        return response()->json(['data' => $usuarios]);
    }

    /** Configurações públicas para o frontend (fuso de exibição e limites de anexos). */
    public function configuracao(): JsonResponse
    {
        return response()->json(['data' => [
            'fuso' => config('comando.fuso_exibicao'),
            'anexos' => [
                'max_arquivos' => config('comando.anexos.max_arquivos'),
                'max_mb' => intdiv((int) config('comando.anexos.max_kb'), 1024),
                'extensoes' => config('comando.anexos.extensoes'),
            ],
        ]]);
    }
}
