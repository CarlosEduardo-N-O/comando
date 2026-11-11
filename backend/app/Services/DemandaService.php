<?php

namespace App\Services;

use App\Exceptions\RegraNegocioException;
use App\Models\Categoria;
use App\Models\Comentario;
use App\Models\Demanda;
use App\Models\Historico;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Operações sobre demandas. Cada operação executa em uma única transação que
 * bloqueia a linha da demanda (SELECT ... FOR UPDATE), grava a alteração, o
 * histórico e as notificações. Assim, duas pessoas não conseguem assumir ou
 * alterar a mesma demanda ao mesmo tempo.
 */
class DemandaService
{
    private const TENTATIVAS = 3;

    public function __construct(
        private readonly FluxoAtendimento $fluxo,
        private readonly HistoricoService $historico,
        private readonly NotificacaoService $notificacoes,
        private readonly AnexoService $anexos,
    ) {
    }

    /**
     * Abre uma demanda. Solicitante e setor de origem vêm sempre do usuário
     * autenticado; os dados recebidos já foram validados pelo Form Request.
     *
     * @param  array{titulo: string, descricao: string, categoria_id: int, setor_destino_id: int, responsavel_id?: int|null, prioridade: string, prazo_desejado?: string|null}  $dados
     * @param  array<int, UploadedFile>  $arquivos
     */
    public function criar(User $autor, array $dados, array $arquivos = []): Demanda
    {
        return $this->comAnexos(function () use ($autor, $dados, $arquivos) {
            $solicitante = User::query()->whereKey($autor->id)->sharedLock()->firstOrFail();
            if (! $solicitante->ativo || ! $solicitante->setor_id) {
                throw new RegraNegocioException('Usuário sem setor ativo não pode abrir demandas.', 'usuario');
            }

            $setorDestino = $this->setorAtivo((int) $dados['setor_destino_id']);
            $categoria = Categoria::query()->whereKey($dados['categoria_id'])->sharedLock()->first();
            if (! $categoria?->ativo) {
                throw new RegraNegocioException('Selecione uma categoria ativa.', 'categoria_id');
            }

            $responsavelId = isset($dados['responsavel_id']) ? (int) $dados['responsavel_id'] : null;
            $this->validarResponsavel($responsavelId, $setorDestino->id, $solicitante->id);

            $numero = DB::selectOne("SELECT nextval('demanda_protocolo_seq') AS numero")->numero;

            $demanda = new Demanda;
            $demanda->forceFill([
                'protocolo' => 'CMD-'.str_pad((string) $numero, 6, '0', STR_PAD_LEFT),
                'titulo' => trim($dados['titulo']),
                'descricao' => trim($dados['descricao']),
                'categoria_id' => $categoria->id,
                'solicitante_id' => $solicitante->id,
                'setor_origem_id' => $solicitante->setor_id,
                'setor_destino_id' => $setorDestino->id,
                'responsavel_id' => $responsavelId,
                'prioridade' => $dados['prioridade'],
                'status' => Demanda::STATUS_ABERTA,
                'prazo_desejado' => $dados['prazo_desejado'] ?? null,
            ])->save();

            $this->anexos->armazenar($demanda, null, $solicitante, $arquivos);
            $evento = $this->historico->registrar($demanda, $solicitante, Historico::CRIACAO);

            $this->notificacoes->notificar($demanda, $solicitante, 'historico:'.$evento->id, $responsavelId
                ? [[$responsavelId, 'atribuida']]
                : NotificacaoService::todos($this->notificacoes->gestoresDoSetor($setorDestino->id), 'nova_no_setor'));

            return $demanda;
        });
    }

    /** Distribuição ou reatribuição pelo gestor do destino ou administrador. */
    public function atribuir(User $ator, int $demandaId, ?int $responsavelId): Demanda
    {
        return $this->transacao(function () use ($ator, $demandaId, $responsavelId) {
            $demanda = $this->bloquear($demandaId);
            Gate::forUser($ator)->authorize('gerenciar', $demanda);
            $this->exigirEmAtendimento($demanda, 'A distribuição só é permitida nos status Aberta, Em andamento ou Aguardando solicitante.');

            if ($responsavelId === $demanda->responsavel_id) {
                throw new RegraNegocioException('A pessoa selecionada já é a responsável pela demanda.', 'responsavel_id');
            }
            if ($responsavelId === null && $demanda->status !== Demanda::STATUS_ABERTA) {
                throw new RegraNegocioException('Somente demandas abertas podem ficar sem responsável. Use a transferência para redirecioná-la.', 'responsavel_id');
            }
            $this->validarResponsavel($responsavelId, $demanda->setor_destino_id, $demanda->solicitante_id);

            $antes = $this->historico->estado($demanda);
            $anterior = $demanda->responsavel_id;
            $demanda->responsavel_id = $responsavelId;
            $demanda->save();

            $evento = $this->historico->registrar($demanda, $ator, $anterior ? Historico::REATRIBUICAO : Historico::ATRIBUICAO, $antes);

            $destinatarios = [[$responsavelId, 'atribuida'], [$anterior, 'reatribuida'], [$demanda->solicitante_id, 'responsavel_alterado']];
            if ($responsavelId === null) {
                $destinatarios = [...$destinatarios, ...NotificacaoService::todos($this->notificacoes->gestoresDoSetor($demanda->setor_destino_id), 'nova_no_setor')];
            }
            $this->notificacoes->notificar($demanda, $ator, 'historico:'.$evento->id, $destinatarios);

            return $demanda;
        });
    }

    /**
     * Um colaborador do setor de destino assume a demanda sem responsável.
     * O bloqueio da linha garante que apenas a primeira requisição tenha
     * sucesso; as demais recebem 409.
     */
    public function assumir(User $ator, int $demandaId): Demanda
    {
        return $this->transacao(function () use ($ator, $demandaId) {
            $demanda = $this->bloquear($demandaId);
            $usuario = User::query()->whereKey($ator->id)->sharedLock()->firstOrFail();

            if ($usuario->setor_id !== $demanda->setor_destino_id || $usuario->id === $demanda->solicitante_id || ! $usuario->ativo) {
                throw new AuthorizationException('Você não pode assumir esta demanda.');
            }
            if ($demanda->responsavel_id !== null) {
                throw RegraNegocioException::conflito('Esta demanda já foi assumida por outra pessoa.');
            }
            $this->exigirEmAtendimento($demanda, 'Esta demanda não está disponível para atendimento.');

            $antes = $this->historico->estado($demanda);
            $demanda->responsavel_id = $usuario->id;
            $demanda->save();

            $evento = $this->historico->registrar($demanda, $usuario, Historico::ASSUNCAO, $antes);
            $this->notificacoes->notificar($demanda, $usuario, 'historico:'.$evento->id, [[$demanda->solicitante_id, 'responsavel_alterado']]);

            return $demanda;
        });
    }

    /** Transferência entre setores (somente administrador), com justificativa obrigatória. */
    public function transferir(User $ator, int $demandaId, int $setorDestinoId, ?int $responsavelId, string $justificativa): Demanda
    {
        return $this->transacao(function () use ($ator, $demandaId, $setorDestinoId, $responsavelId, $justificativa) {
            $demanda = $this->bloquear($demandaId);
            Gate::forUser($ator)->authorize('transferir', $demanda);
            $this->exigirEmAtendimento($demanda, 'A transferência só é permitida nos status Aberta, Em andamento ou Aguardando solicitante.');

            if (trim($justificativa) === '') {
                throw new RegraNegocioException('Informe a justificativa da transferência.', 'justificativa');
            }
            if ($setorDestinoId === $demanda->setor_destino_id) {
                throw new RegraNegocioException('A demanda já está neste setor. Para trocar o responsável, use a atribuição.', 'setor_destino_id');
            }
            $setor = $this->setorAtivo($setorDestinoId);
            $this->validarResponsavel($responsavelId, $setor->id, $demanda->solicitante_id);

            $antes = $this->historico->estado($demanda);
            $anterior = $demanda->responsavel_id;
            $demanda->forceFill([
                'setor_destino_id' => $setor->id,
                'responsavel_id' => $responsavelId,
                'status' => Demanda::STATUS_ABERTA,
                'prazo_atendimento' => null,
            ])->save();

            $evento = $this->historico->registrar($demanda, $ator, Historico::TRANSFERENCIA, $antes, $justificativa);

            $gestores = NotificacaoService::todos($this->notificacoes->gestoresDoSetor($setor->id), $responsavelId ? 'transferida' : 'nova_no_setor');
            $this->notificacoes->notificar($demanda, $ator, 'historico:'.$evento->id, [
                [$responsavelId, 'atribuida'],
                [$anterior, 'transferida'],
                [$demanda->solicitante_id, 'transferida'],
                ...$gestores,
            ]);

            return $demanda;
        });
    }

    public function alterarPrioridade(User $ator, int $demandaId, string $prioridade, ?string $justificativa = null): Demanda
    {
        return $this->transacao(function () use ($ator, $demandaId, $prioridade, $justificativa) {
            $demanda = $this->bloquear($demandaId);
            Gate::forUser($ator)->authorize('gerenciar', $demanda);
            $this->exigirEmAtendimento($demanda, 'A prioridade só pode ser ajustada durante o atendimento.');

            if ($demanda->prioridade === $prioridade) {
                throw new RegraNegocioException('A demanda já possui esta prioridade.', 'prioridade');
            }

            $antes = $this->historico->estado($demanda);
            $demanda->prioridade = $prioridade;
            $demanda->save();
            $this->historico->registrar($demanda, $ator, Historico::PRIORIDADE, $antes, $justificativa);

            return $demanda;
        });
    }

    public function definirPrazo(User $ator, int $demandaId, ?string $prazo, ?string $justificativa = null): Demanda
    {
        return $this->transacao(function () use ($ator, $demandaId, $prazo, $justificativa) {
            $demanda = $this->bloquear($demandaId);
            Gate::forUser($ator)->authorize('conduzir', $demanda);
            $this->exigirEmAtendimento($demanda, 'O prazo só pode ser definido durante o atendimento.');

            $antes = $this->historico->estado($demanda);
            $demanda->prazo_atendimento = $prazo;
            if ($antes['prazo_atendimento'] === $demanda->prazo_atendimento?->toIso8601ZuluString()) {
                throw new RegraNegocioException('O prazo informado é igual ao atual.', 'prazo_atendimento');
            }
            $demanda->save();
            $this->historico->registrar($demanda, $ator, Historico::PRAZO, $antes, $justificativa);

            return $demanda;
        });
    }

    /** Mudança de status conforme o FluxoAtendimento. */
    public function alterarStatus(User $ator, int $demandaId, string $novoStatus, ?string $justificativa = null): Demanda
    {
        return $this->transacao(function () use ($ator, $demandaId, $novoStatus, $justificativa) {
            $demanda = $this->bloquear($demandaId);
            if (! Gate::forUser($ator)->allows('view', $demanda)) {
                throw new AuthorizationException('Você não tem acesso a esta demanda.');
            }

            $acao = $this->fluxo->validar($demanda, $novoStatus, $ator, $justificativa);
            $antes = $this->historico->estado($demanda);

            $demanda->status = $novoStatus;
            match ($acao) {
                Historico::RESOLUCAO => $demanda->resolvida_em = now(),
                Historico::REJEICAO => $demanda->resolvida_em = null,
                Historico::CONCLUSAO => $demanda->concluida_em = now(),
                Historico::CANCELAMENTO => $demanda->cancelada_em = now(),
                default => null,
            };
            $demanda->save();

            $evento = $this->historico->registrar($demanda, $ator, $acao, $antes, $justificativa);

            $destinatarios = match ($acao) {
                Historico::SOLICITACAO_INFORMACOES => [[$demanda->solicitante_id, 'solicitacao_informacoes']],
                Historico::RESOLUCAO => [[$demanda->solicitante_id, 'resolvida']],
                Historico::REJEICAO => [[$demanda->responsavel_id, 'rejeitada']],
                Historico::CONCLUSAO => [[$demanda->responsavel_id, 'concluida']],
                Historico::CANCELAMENTO => [
                    [$demanda->solicitante_id, 'cancelada'],
                    [$demanda->responsavel_id, 'cancelada'],
                    ...($demanda->responsavel_id ? [] : NotificacaoService::todos($this->notificacoes->gestoresDoSetor($demanda->setor_destino_id), 'cancelada')),
                ],
                default => [],
            };
            $this->notificacoes->notificar($demanda, $ator, 'historico:'.$evento->id, $destinatarios);

            return $demanda;
        });
    }

    /**
     * Comentários não alteram o status. Participantes notificados: solicitante,
     * responsável e, quando não houver responsável, os gestores do destino.
     *
     * @param  array<int, UploadedFile>  $arquivos
     */
    public function comentar(User $ator, int $demandaId, string $mensagem, array $arquivos = []): Comentario
    {
        return $this->comAnexos(function () use ($ator, $demandaId, $mensagem, $arquivos) {
            $demanda = $this->bloquear($demandaId);
            Gate::forUser($ator)->authorize('view', $demanda);
            if ($demanda->encerrada()) {
                throw new RegraNegocioException('Demandas concluídas ou canceladas não recebem novos comentários.');
            }

            $comentario = new Comentario;
            $comentario->forceFill([
                'demanda_id' => $demanda->id,
                'autor_id' => $ator->id,
                'mensagem' => trim($mensagem),
            ])->save();

            $this->anexos->armazenar($demanda, $comentario, $ator, $arquivos);
            $demanda->touch();

            $this->notificacoes->notificar($demanda, $ator, 'comentario:'.$comentario->id, [
                [$demanda->solicitante_id, 'comentario'],
                [$demanda->responsavel_id, 'comentario'],
                ...($demanda->responsavel_id ? [] : NotificacaoService::todos($this->notificacoes->gestoresDoSetor($demanda->setor_destino_id), 'comentario')),
            ]);

            return $comentario->load(['autor:id,name', 'anexos']);
        });
    }

    private function bloquear(int $demandaId): Demanda
    {
        return Demanda::query()->whereKey($demandaId)->lockForUpdate()->firstOrFail();
    }

    private function exigirEmAtendimento(Demanda $demanda, string $mensagem): void
    {
        if ($demanda->encerrada()) {
            throw new RegraNegocioException('Demandas concluídas ou canceladas não podem ser alteradas.');
        }
        if (! $demanda->emAtendimento()) {
            throw new RegraNegocioException($mensagem);
        }
    }

    private function setorAtivo(int $setorId): Setor
    {
        $setor = Setor::query()->whereKey($setorId)->sharedLock()->first();
        if (! $setor?->ativo) {
            throw new RegraNegocioException('Selecione um setor de destino ativo.', 'setor_destino_id');
        }

        return $setor;
    }

    /**
     * O responsável deve estar ativo, pertencer ao setor de destino e ser
     * diferente do solicitante. O bloqueio compartilhado impede que o usuário
     * seja inativado ou mude de setor durante a operação.
     */
    private function validarResponsavel(?int $responsavelId, int $setorDestinoId, int $solicitanteId): void
    {
        if ($responsavelId === null) {
            return;
        }
        if ($responsavelId === $solicitanteId) {
            throw new RegraNegocioException('O solicitante não pode ser o responsável pela própria demanda.', 'responsavel_id');
        }

        $responsavel = User::query()->whereKey($responsavelId)->sharedLock()->first();
        if (! $responsavel || ! $responsavel->ativo || $responsavel->setor_id !== $setorDestinoId) {
            throw new RegraNegocioException('O responsável deve ser um usuário ativo do setor de destino.', 'responsavel_id');
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operacao
     * @return T
     */
    private function transacao(callable $operacao): mixed
    {
        return DB::transaction($operacao, self::TENTATIVAS);
    }

    /**
     * Transação com arquivos: se qualquer etapa falhar (inclusive o commit),
     * os arquivos já gravados são removidos para não ficarem órfãos.
     *
     * @template T
     *
     * @param  callable(): T  $operacao
     * @return T
     */
    private function comAnexos(callable $operacao): mixed
    {
        try {
            $resultado = DB::transaction(function () use ($operacao) {
                $this->anexos->descartar(); // arquivos de uma tentativa anterior (deadlock/retry)

                return $operacao();
            }, self::TENTATIVAS);
            $this->anexos->confirmar();

            return $resultado;
        } catch (Throwable $erro) {
            $this->anexos->descartar();
            throw $erro;
        }
    }
}
