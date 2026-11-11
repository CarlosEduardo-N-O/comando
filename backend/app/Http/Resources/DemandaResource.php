<?php

namespace App\Http\Resources;

use App\Models\Demanda;
use App\Models\Historico;
use App\Policies\DemandaPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação completa (somente para quem tem acesso à demanda). Em
 * listagens a descrição é omitida; nos detalhes são incluídos anexos de
 * abertura, últimas mensagens de fluxo e as ações permitidas ao usuário.
 *
 * @mixin Demanda
 */
class DemandaResource extends JsonResource
{
    private bool $detalhada = false;

    public function detalhada(): static
    {
        $this->detalhada = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $dados = [
            'id' => $this->id,
            'protocolo' => $this->protocolo,
            'titulo' => $this->titulo,
            'status' => $this->status,
            'prioridade' => $this->prioridade,
            'categoria' => $this->resumo($this->categoria, 'nome'),
            'solicitante' => $this->resumo($this->solicitante, 'name'),
            'responsavel' => $this->resumo($this->responsavel, 'name'),
            'setor_origem' => $this->resumo($this->setorOrigem, 'nome'),
            'setor_destino' => $this->resumo($this->setorDestino, 'nome'),
            'prazo_desejado' => $this->prazo_desejado?->format('Y-m-d'),
            'prazo_atendimento' => $this->prazo_atendimento?->toIso8601ZuluString(),
            'atrasada' => $this->atrasada(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            'resolvida_em' => $this->resolvida_em?->toIso8601ZuluString(),
            'concluida_em' => $this->concluida_em?->toIso8601ZuluString(),
            'cancelada_em' => $this->cancelada_em?->toIso8601ZuluString(),
        ];

        if (! $this->detalhada) {
            return $dados;
        }

        $ultima = fn (string $acao) => $this->historicos()->where('acao', $acao)->latest('id')->value('justificativa');

        return [
            ...$dados,
            'descricao' => $this->descricao,
            'anexos' => AnexoResource::collection($this->anexos()->whereNull('comentario_id')->orderBy('id')->get()),
            'informacao_solicitada' => $this->status === Demanda::STATUS_AGUARDANDO ? $ultima(Historico::SOLICITACAO_INFORMACOES) : null,
            'solucao' => in_array($this->status, [Demanda::STATUS_RESOLVIDA, Demanda::STATUS_CONCLUIDA], true) ? $ultima(Historico::RESOLUCAO) : null,
            'acoes' => $request->user() ? app(DemandaPolicy::class)->acoes($request->user(), $this->resource) : [],
        ];
    }

    private function resumo(?object $modelo, string $campo): ?array
    {
        return $modelo ? ['id' => $modelo->id, $campo => $modelo->{$campo}] : null;
    }
}
