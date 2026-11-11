<?php

namespace App\Policies;

use App\Models\Demanda;
use App\Models\User;

/**
 * Regras de participação na demanda. As verificações de status ficam no
 * FluxoAtendimento/DemandaService, que devolvem 422 com a explicação; aqui
 * só se decide QUEM pode agir (403 quando negado).
 */
class DemandaPolicy
{
    /** Acesso completo: solicitante, responsável, gestor do destino ou administrador. */
    public function view(User $user, Demanda $demanda): bool
    {
        return $user->ativo && (
            $user->isAdministrador()
            || $demanda->solicitante_id === $user->id
            || $demanda->responsavel_id === $user->id
            || $user->isGestorDoSetor($demanda->setor_destino_id)
        );
    }

    /** Distribuir, reatribuir e ajustar prioridade: gestor do destino ou administrador. */
    public function gerenciar(User $user, Demanda $demanda): bool
    {
        return $user->ativo && ($user->isAdministrador() || $user->isGestorDoSetor($demanda->setor_destino_id));
    }

    /** Conduzir o atendimento: responsável, gestor do destino ou administrador. */
    public function conduzir(User $user, Demanda $demanda): bool
    {
        return $this->gerenciar($user, $demanda)
            || ($user->ativo && $demanda->responsavel_id === $user->id);
    }

    /** Assumir: colaborador ativo do setor de destino, sem responsável e não solicitante. */
    public function assumir(User $user, Demanda $demanda): bool
    {
        return $user->ativo
            && $demanda->responsavel_id === null
            && $demanda->emAtendimento()
            && $user->setor_id === $demanda->setor_destino_id
            && $user->id !== $demanda->solicitante_id;
    }

    /** Transferir entre setores: somente administrador. */
    public function transferir(User $user, Demanda $demanda): bool
    {
        return $user->ativo && $user->isAdministrador();
    }

    public function comentar(User $user, Demanda $demanda): bool
    {
        return $this->view($user, $demanda) && ! $demanda->encerrada();
    }

    /** Cancelar: solicitante, gestor do destino ou administrador. */
    public function cancelar(User $user, Demanda $demanda): bool
    {
        return $user->ativo && ($demanda->solicitante_id === $user->id || $this->gerenciar($user, $demanda));
    }

    /** Confirmar a conclusão ou rejeitar a solução: somente o solicitante. */
    public function avaliarSolucao(User $user, Demanda $demanda): bool
    {
        return $user->ativo && $demanda->solicitante_id === $user->id;
    }

    /**
     * Ações oferecidas ao usuário na interface. O backend valida tudo de novo
     * a cada requisição; esta lista apenas evita mostrar botões inúteis.
     *
     * @return list<string>
     */
    public function acoes(User $user, Demanda $demanda): array
    {
        if ($demanda->encerrada() || ! $this->view($user, $demanda) && ! $this->assumir($user, $demanda)) {
            return [];
        }

        $acoes = [];
        $emAtendimento = $demanda->emAtendimento();

        if ($this->comentar($user, $demanda)) {
            $acoes[] = 'comentar';
        }
        if ($this->assumir($user, $demanda)) {
            $acoes[] = 'assumir';
        }
        if ($emAtendimento && $this->gerenciar($user, $demanda)) {
            array_push($acoes, 'atribuir', 'alterar_prioridade');
        }
        if ($emAtendimento && $this->transferir($user, $demanda)) {
            $acoes[] = 'transferir';
        }
        if ($emAtendimento && $this->conduzir($user, $demanda)) {
            $acoes[] = 'definir_prazo';
            match ($demanda->status) {
                Demanda::STATUS_ABERTA => $demanda->responsavel_id ? $acoes[] = 'iniciar' : null,
                Demanda::STATUS_EM_ANDAMENTO => array_push($acoes, 'solicitar_informacoes', 'resolver'),
                Demanda::STATUS_AGUARDANDO => $acoes[] = 'retomar',
            };
        }
        if ($demanda->status === Demanda::STATUS_RESOLVIDA && $this->avaliarSolucao($user, $demanda)) {
            array_push($acoes, 'concluir', 'rejeitar');
        }
        if ($emAtendimento && $this->cancelar($user, $demanda)) {
            $acoes[] = 'cancelar';
        }

        return $acoes;
    }
}
