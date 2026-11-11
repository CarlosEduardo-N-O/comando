<?php

namespace App\Services;

use App\Exceptions\RegraNegocioException;
use App\Models\Demanda;
use App\Models\Historico;
use App\Models\User;
use App\Policies\DemandaPolicy;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Máquina de estados do atendimento. É o único ponto que decide se uma
 * transição de status é permitida, quem pode executá-la e qual evento de
 * histórico ela gera.
 */
class FluxoAtendimento
{
    /** @var array<string, list<string>> */
    public const TRANSICOES = [
        Demanda::STATUS_ABERTA => [Demanda::STATUS_EM_ANDAMENTO, Demanda::STATUS_CANCELADA],
        Demanda::STATUS_EM_ANDAMENTO => [Demanda::STATUS_AGUARDANDO, Demanda::STATUS_RESOLVIDA, Demanda::STATUS_CANCELADA],
        Demanda::STATUS_AGUARDANDO => [Demanda::STATUS_EM_ANDAMENTO, Demanda::STATUS_CANCELADA],
        Demanda::STATUS_RESOLVIDA => [Demanda::STATUS_CONCLUIDA, Demanda::STATUS_EM_ANDAMENTO],
        Demanda::STATUS_CONCLUIDA => [],
        Demanda::STATUS_CANCELADA => [],
    ];

    public function __construct(private readonly DemandaPolicy $policy)
    {
    }

    public static function permitida(string $de, string $para): bool
    {
        return in_array($para, self::TRANSICOES[$de] ?? [], true);
    }

    /**
     * Valida a transição e devolve a ação de histórico correspondente.
     *
     * @throws AuthorizationException quando o usuário não participa como exigido
     * @throws RegraNegocioException quando a transição ou os dados são inválidos
     */
    public function validar(Demanda $demanda, string $novoStatus, User $usuario, ?string $justificativa): string
    {
        if ($demanda->encerrada()) {
            throw new RegraNegocioException('Demandas concluídas ou canceladas não podem ser alteradas.');
        }

        if (! self::permitida($demanda->status, $novoStatus)) {
            throw new RegraNegocioException('Transição de status não permitida.', 'status');
        }

        $temJustificativa = $justificativa !== null && trim($justificativa) !== '';

        // Resolvida → Concluída / Em andamento: decisão exclusiva do solicitante.
        if ($demanda->status === Demanda::STATUS_RESOLVIDA) {
            $this->autorizar($this->policy->avaliarSolucao($usuario, $demanda), 'Somente o solicitante pode confirmar ou rejeitar a solução.');

            if ($novoStatus === Demanda::STATUS_CONCLUIDA) {
                return Historico::CONCLUSAO;
            }
            $this->exigir($temJustificativa, 'Informe o motivo da rejeição da solução.');

            return Historico::REJEICAO;
        }

        if ($novoStatus === Demanda::STATUS_CANCELADA) {
            $this->autorizar($this->policy->cancelar($usuario, $demanda), 'Somente o solicitante, o gestor do setor de destino ou um administrador pode cancelar.');
            $this->exigir($temJustificativa, 'Informe a justificativa do cancelamento.');

            return Historico::CANCELAMENTO;
        }

        // Demais transições conduzem o atendimento.
        $this->autorizar($this->policy->conduzir($usuario, $demanda), 'Somente o responsável, o gestor do setor de destino ou um administrador pode conduzir o atendimento.');

        if ($demanda->responsavel_id === null) {
            throw new RegraNegocioException('Defina um responsável antes de iniciar o atendimento.', 'responsavel_id');
        }

        if ($novoStatus === Demanda::STATUS_AGUARDANDO) {
            $this->exigir($temJustificativa, 'Descreva a informação necessária do solicitante.');

            return Historico::SOLICITACAO_INFORMACOES;
        }

        if ($novoStatus === Demanda::STATUS_RESOLVIDA) {
            $this->exigir($temJustificativa, 'Descreva a solução aplicada.');

            return Historico::RESOLUCAO;
        }

        // Em andamento: início (a partir de Aberta) ou retomada (a partir de Aguardando solicitante).
        return $demanda->status === Demanda::STATUS_ABERTA ? Historico::INICIO : Historico::RETOMADA;
    }

    private function autorizar(bool $permitido, string $mensagem): void
    {
        if (! $permitido) {
            throw new AuthorizationException($mensagem);
        }
    }

    private function exigir(bool $condicao, string $mensagem): void
    {
        if (! $condicao) {
            throw new RegraNegocioException($mensagem, 'justificativa');
        }
    }
}
