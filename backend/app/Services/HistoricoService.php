<?php

namespace App\Services;

use App\Models\Demanda;
use App\Models\Historico;
use App\Models\Setor;
use App\Models\User;

/**
 * Registra eventos com os valores anteriores e novos apenas dos campos que
 * mudaram. Nomes de responsável e setor são gravados junto com os IDs para
 * que o histórico continue legível mesmo após alterações de cadastro.
 */
class HistoricoService
{
    /** @return array<string, mixed> */
    public function estado(Demanda $demanda): array
    {
        return [
            'status' => $demanda->status,
            'prioridade' => $demanda->prioridade,
            'prazo_atendimento' => $demanda->prazo_atendimento?->toIso8601ZuluString(),
            'responsavel_id' => $demanda->responsavel_id,
            'responsavel' => $demanda->responsavel_id ? User::query()->whereKey($demanda->responsavel_id)->value('name') : null,
            'setor_destino_id' => $demanda->setor_destino_id,
            'setor_destino' => Setor::query()->whereKey($demanda->setor_destino_id)->value('nome'),
        ];
    }

    /**
     * @param  array<string, mixed>  $antes  estado() capturado antes da alteração
     */
    public function registrar(Demanda $demanda, User $autor, string $acao, array $antes = [], ?string $justificativa = null): Historico
    {
        $depois = $this->estado($demanda);

        if ($antes === []) {
            $diffAntes = null;
            $diffDepois = $depois;
        } else {
            $alterados = array_keys(array_filter($depois, fn ($valor, $campo) => ($antes[$campo] ?? null) !== $valor, ARRAY_FILTER_USE_BOTH));
            $diffAntes = array_intersect_key($antes, array_flip($alterados)) ?: null;
            $diffDepois = array_intersect_key($depois, array_flip($alterados)) ?: null;
        }

        $historico = new Historico;
        $historico->forceFill([
            'demanda_id' => $demanda->id,
            'autor_id' => $autor->id,
            'acao' => $acao,
            'justificativa' => $justificativa !== null && trim($justificativa) !== '' ? trim($justificativa) : null,
            'antes' => $diffAntes,
            'depois' => $diffDepois,
        ])->save();

        return $historico;
    }
}
