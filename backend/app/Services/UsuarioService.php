<?php

namespace App\Services;

use App\Exceptions\RegraNegocioException;
use App\Models\Demanda;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro de usuários pelo administrador. Garante as invariantes:
 * - usuário com demandas não encerradas sob sua responsabilidade não muda de
 *   setor nem é inativado sem reatribuição prévia;
 * - sempre existe ao menos um administrador ativo;
 * - inativação e troca de senha revogam as sessões existentes.
 */
class UsuarioService
{
    /** Chave do advisory lock que serializa mudanças de perfil/situação. */
    private const LOCK_ADMINISTRADORES = 12026001;

    /** @param array<string, mixed> $dados */
    public function criar(array $dados): User
    {
        return DB::transaction(function () use ($dados) {
            $this->exigirSetorAtivo((int) $dados['setor_id']);

            $usuario = new User;
            $usuario->forceFill([
                'name' => trim($dados['name']),
                'email' => mb_strtolower(trim($dados['email'])),
                'password' => $dados['password'],
                'setor_id' => (int) $dados['setor_id'],
                'perfil' => $dados['perfil'],
                'ativo' => (bool) ($dados['ativo'] ?? true),
            ])->save();

            return $usuario;
        });
    }

    /** @param array<string, mixed> $dados */
    public function atualizar(int $usuarioId, array $dados): User
    {
        return DB::transaction(function () use ($usuarioId, $dados) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK_ADMINISTRADORES]);
            $usuario = User::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();

            $novoSetor = array_key_exists('setor_id', $dados) ? (int) $dados['setor_id'] : $usuario->setor_id;
            $novoPerfil = $dados['perfil'] ?? $usuario->perfil;
            $novoAtivo = array_key_exists('ativo', $dados) ? (bool) $dados['ativo'] : $usuario->ativo;

            $mudaSetor = $novoSetor !== $usuario->setor_id;
            $inativa = $usuario->ativo && ! $novoAtivo;

            if ($mudaSetor) {
                $this->exigirSetorAtivo($novoSetor);
            }

            if (($mudaSetor || $inativa) && $this->possuiDemandasEmAberto($usuario)) {
                throw new RegraNegocioException(
                    'Reatribua as demandas não encerradas sob responsabilidade deste usuário antes de alterar o setor ou inativá-lo.',
                    $mudaSetor ? 'setor_id' : 'ativo'
                );
            }

            $deixaDeSerAdmin = $usuario->isAdministrador() && $usuario->ativo && ($inativa || $novoPerfil !== User::PERFIL_ADMINISTRADOR);
            if ($deixaDeSerAdmin && User::query()->ativos()->where('perfil', User::PERFIL_ADMINISTRADOR)->count() <= 1) {
                throw new RegraNegocioException('Não é possível remover o último administrador ativo.', $inativa ? 'ativo' : 'perfil');
            }

            $alteraSenha = ! empty($dados['password']);

            $usuario->forceFill(array_filter([
                'name' => isset($dados['name']) ? trim($dados['name']) : null,
                'email' => isset($dados['email']) ? mb_strtolower(trim($dados['email'])) : null,
                'password' => $alteraSenha ? $dados['password'] : null,
            ], fn ($valor) => $valor !== null));
            $usuario->forceFill(['setor_id' => $novoSetor, 'perfil' => $novoPerfil, 'ativo' => $novoAtivo])->save();

            if ($inativa || $alteraSenha) {
                $this->revogarSessoes($usuario->id);
            }

            return $usuario;
        });
    }

    /** Remove todas as sessões do usuário (exceto, opcionalmente, a atual). */
    public function revogarSessoes(int $usuarioId, ?string $excetoSessao = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuarioId)
            ->when($excetoSessao, fn ($q) => $q->where('id', '!=', $excetoSessao))
            ->delete();
    }

    private function possuiDemandasEmAberto(User $usuario): bool
    {
        return Demanda::query()
            ->where('responsavel_id', $usuario->id)
            ->whereNotIn('status', Demanda::STATUS_ENCERRADOS)
            ->exists();
    }

    private function exigirSetorAtivo(int $setorId): void
    {
        if (! Setor::query()->whereKey($setorId)->where('ativo', true)->exists()) {
            throw new RegraNegocioException('Selecione um setor ativo.', 'setor_id');
        }
    }
}
