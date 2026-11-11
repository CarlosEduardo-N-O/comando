<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Verificação de saúde sem dados sensíveis (sem versões, hosts ou mensagens de erro). */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $banco = 'ok';
        } catch (Throwable) {
            $banco = 'indisponivel';
        }

        return response()->json(
            ['status' => $banco === 'ok' ? 'ok' : 'degradado', 'banco' => $banco],
            $banco === 'ok' ? 200 : 503
        )->header('Cache-Control', 'no-store');
    }
}
