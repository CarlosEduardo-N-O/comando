<?php

namespace App\Http\Controllers;

use App\Http\Requests\Demanda\ComentarioRequest;
use App\Http\Resources\ComentarioResource;
use App\Http\Resources\HistoricoResource;
use App\Models\Anexo;
use App\Models\Demanda;
use App\Services\DemandaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Comentários, histórico e anexos: exigem acesso completo à demanda. */
class InteracaoController extends Controller
{
    public function comentarios(Demanda $demanda): AnonymousResourceCollection
    {
        Gate::authorize('view', $demanda);

        return ComentarioResource::collection(
            $demanda->comentarios()->with(['autor:id,name', 'anexos'])->orderBy('id')->paginate(50)
        );
    }

    public function comentar(ComentarioRequest $request, Demanda $demanda, DemandaService $servico): JsonResponse
    {
        $comentario = $servico->comentar($request->user(), $demanda->id, $request->validated('mensagem'), $request->file('anexos', []));

        return (new ComentarioResource($comentario))->response()->setStatusCode(201);
    }

    public function historico(Demanda $demanda): AnonymousResourceCollection
    {
        Gate::authorize('view', $demanda);

        return HistoricoResource::collection(
            $demanda->historicos()->with('autor:id,name')->orderByDesc('id')->paginate(50)
        );
    }

    /** A autorização é verificada a cada download; não existem URLs públicas. */
    public function download(Demanda $demanda, Anexo $anexo): StreamedResponse
    {
        Gate::authorize('view', $demanda);
        abort_unless($anexo->demanda_id === $demanda->id, 404);
        abort_unless(Storage::disk($anexo->disco)->exists($anexo->caminho), 404, 'Arquivo não encontrado no armazenamento.');

        return Storage::disk($anexo->disco)->download($anexo->caminho, $anexo->nome_original, [
            'Content-Type' => $anexo->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
