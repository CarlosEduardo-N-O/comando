<?php

namespace App\Http\Controllers;

use App\Http\Requests\Demanda\AtribuicaoRequest;
use App\Http\Requests\Demanda\CriarDemandaRequest;
use App\Http\Requests\Demanda\DestinoRequest;
use App\Http\Requests\Demanda\ListarDemandasRequest;
use App\Http\Requests\Demanda\PrazoRequest;
use App\Http\Requests\Demanda\PrioridadeRequest;
use App\Http\Requests\Demanda\StatusRequest;
use App\Http\Resources\DemandaFilaResource;
use App\Http\Resources\DemandaResource;
use App\Models\Demanda;
use App\Services\ConsultaDemandas;
use App\Services\DemandaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DemandaController extends Controller
{
    public function __construct(private readonly DemandaService $servico)
    {
    }

    /**
     * Contextos: minhas (solicitante), atendimento (responsável), fila (resumo
     * do próprio setor, sem responsável), setor (gestor) e todas (administrador).
     */
    public function index(ListarDemandasRequest $request, ConsultaDemandas $consultas): AnonymousResourceCollection
    {
        $contexto = $request->validated('contexto') ?? 'minhas';
        $porPagina = (int) ($request->validated('por_pagina') ?? config('comando.paginacao.padrao'));

        $query = $consultas->consulta($request->user(), $contexto, $request->validated());

        if ($contexto === 'fila') {
            $pagina = $query->with('categoria:id,nome')
                ->select(['id', 'protocolo', 'titulo', 'categoria_id', 'prioridade', 'created_at', 'setor_destino_id', 'responsavel_id', 'solicitante_id', 'status'])
                ->paginate($porPagina)->withQueryString();

            return DemandaFilaResource::collection($pagina);
        }

        $pagina = $query->with(['categoria:id,nome', 'solicitante:id,name', 'responsavel:id,name', 'setorOrigem:id,nome', 'setorDestino:id,nome'])
            ->paginate($porPagina)->withQueryString();

        return DemandaResource::collection($pagina);
    }

    public function store(CriarDemandaRequest $request): JsonResponse
    {
        $dados = $request->safe()->except('anexos');
        $demanda = $this->servico->criar($request->user(), $dados, $request->file('anexos', []));

        return $this->detalhe($demanda)->response()->setStatusCode(201);
    }

    public function show(Demanda $demanda): DemandaResource
    {
        Gate::authorize('view', $demanda);

        return $this->detalhe($demanda);
    }

    public function atribuicao(AtribuicaoRequest $request, Demanda $demanda): DemandaResource
    {
        $atualizada = $request->boolean('assumir')
            ? $this->servico->assumir($request->user(), $demanda->id)
            : $this->servico->atribuir($request->user(), $demanda->id, $request->validated('responsavel_id'));

        return $this->detalhe($atualizada);
    }

    public function destino(DestinoRequest $request, Demanda $demanda): DemandaResource
    {
        return $this->detalhe($this->servico->transferir(
            $request->user(),
            $demanda->id,
            (int) $request->validated('setor_destino_id'),
            $request->validated('responsavel_id'),
            (string) $request->validated('justificativa'),
        ));
    }

    public function prioridade(PrioridadeRequest $request, Demanda $demanda): DemandaResource
    {
        return $this->detalhe($this->servico->alterarPrioridade(
            $request->user(), $demanda->id, $request->validated('prioridade'), $request->validated('justificativa')
        ));
    }

    public function prazo(PrazoRequest $request, Demanda $demanda): DemandaResource
    {
        return $this->detalhe($this->servico->definirPrazo(
            $request->user(), $demanda->id, $request->validated('prazo_atendimento'), $request->validated('justificativa')
        ));
    }

    public function status(StatusRequest $request, Demanda $demanda): DemandaResource
    {
        return $this->detalhe($this->servico->alterarStatus(
            $request->user(), $demanda->id, $request->validated('status'), $request->validated('justificativa')
        ));
    }

    private function detalhe(Demanda $demanda): DemandaResource
    {
        $demanda->refresh()->load(['categoria:id,nome', 'solicitante:id,name', 'responsavel:id,name', 'setorOrigem:id,nome', 'setorDestino:id,nome']);

        // Após uma reatribuição/transferência o autor pode ter perdido o acesso.
        if (! Gate::allows('view', $demanda)) {
            return (new DemandaResource($demanda))->additional(['meta' => ['acesso_perdido' => true]]);
        }

        return (new DemandaResource($demanda))->detalhada();
    }
}
