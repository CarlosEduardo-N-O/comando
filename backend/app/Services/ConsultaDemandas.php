<?php

namespace App\Services;

use App\Models\Demanda;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Monta as consultas de listagem por contexto, sempre a partir das regras de
 * visibilidade do servidor. Filtros, ordenação e paginação são aplicados aqui.
 */
class ConsultaDemandas
{
    public const CONTEXTOS = ['minhas', 'atendimento', 'fila', 'setor', 'todas'];

    public const ORDENACOES = ['created_at', 'updated_at', 'prazo_atendimento', 'protocolo', 'prioridade', 'status'];

    /** @param array<string, mixed> $filtros */
    public function consulta(User $usuario, string $contexto, array $filtros): Builder
    {
        $query = Demanda::query();

        match ($contexto) {
            'minhas' => $query->where('solicitante_id', $usuario->id),
            'atendimento' => $query->where('responsavel_id', $usuario->id),
            // Fila resumida: demandas sem responsável destinadas ao setor do usuário.
            'fila' => $query->whereNull('responsavel_id')
                ->where('setor_destino_id', $usuario->setor_id ?? 0)
                ->whereIn('status', Demanda::STATUS_EM_ATENDIMENTO),
            'setor' => $this->contextoSetor($query, $usuario),
            'todas' => $this->contextoTodas($usuario),
        };

        if ($contexto !== 'fila') {
            // Defesa em profundidade: nenhum contexto ultrapassa a visibilidade do usuário.
            $query->visiveisPara($usuario);
        }

        $this->aplicarFiltros($query, $filtros, $contexto === 'fila');
        $this->ordenar($query, $filtros['ordem'] ?? 'created_at', $filtros['direcao'] ?? 'desc');

        return $query;
    }

    private function contextoSetor(Builder $query, User $usuario): void
    {
        if ($usuario->isAdministrador()) {
            return; // o administrador escolhe o setor pelo filtro setor_destino_id
        }
        if (! $usuario->isGestor()) {
            throw new AuthorizationException('Somente gestores acessam as demandas do setor.');
        }
        $query->where('setor_destino_id', $usuario->setor_id);
    }

    private function contextoTodas(User $usuario): void
    {
        if (! $usuario->isAdministrador()) {
            throw new AuthorizationException('Somente administradores acessam todas as demandas.');
        }
    }

    /** @param array<string, mixed> $f */
    private function aplicarFiltros(Builder $query, array $f, bool $resumida): void
    {
        if (! empty($f['busca'])) {
            $termo = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($f['busca'])).'%';
            $query->where(fn (Builder $q) => $q->where('protocolo', 'ilike', $termo)->orWhere('titulo', 'ilike', $termo));
        }

        foreach (['prioridade', 'categoria_id'] as $campo) {
            if (! empty($f[$campo])) {
                $query->where($campo, $f[$campo]);
            }
        }

        if ($resumida) {
            return; // a fila só aceita busca, prioridade e categoria
        }

        if (! empty($f['status'])) {
            $query->where('status', $f['status']);
        }
        foreach (['setor_destino_id', 'setor_origem_id'] as $campo) {
            if (! empty($f[$campo])) {
                $query->where($campo, $f[$campo]);
            }
        }
        if (! empty($f['responsavel_id'])) {
            $f['responsavel_id'] === 'nenhum'
                ? $query->whereNull('responsavel_id')
                : $query->where('responsavel_id', (int) $f['responsavel_id']);
        }

        // Datas do filtro chegam no fuso de exibição e são convertidas para UTC.
        $fuso = config('comando.fuso_exibicao');
        if (! empty($f['criado_de'])) {
            $query->where('created_at', '>=', Carbon::parse($f['criado_de'], $fuso)->startOfDay()->utc());
        }
        if (! empty($f['criado_ate'])) {
            $query->where('created_at', '<=', Carbon::parse($f['criado_ate'], $fuso)->endOfDay()->utc());
        }

        if (filter_var($f['atrasadas'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->atrasadas();
        }
    }

    private function ordenar(Builder $query, string $ordem, string $direcao): void
    {
        $direcao = $direcao === 'asc' ? 'asc' : 'desc';

        if ($ordem === 'prioridade') {
            $query->orderByRaw("CASE prioridade WHEN 'urgente' THEN 4 WHEN 'alta' THEN 3 WHEN 'normal' THEN 2 ELSE 1 END {$direcao}");
        } elseif ($ordem === 'prazo_atendimento') {
            $query->orderByRaw("prazo_atendimento {$direcao} NULLS LAST");
        } else {
            $query->orderBy(in_array($ordem, self::ORDENACOES, true) ? $ordem : 'created_at', $direcao);
        }

        $query->orderBy('id', $direcao);
    }
}
