<?php

namespace App\Services;

use App\Models\Demanda;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Notificações internas persistidas na tabela "notifications" (mesmo formato
 * das notificações de banco do Laravel). São gravadas na mesma transação da
 * alteração da demanda. A prévia contém apenas protocolo e um texto genérico,
 * nunca título, descrição ou comentários.
 */
class NotificacaoService
{
    public const TIPO = 'App\\Notifications\\DemandaNotificacao';

    private const MENSAGENS = [
        'atribuida' => 'A demanda %s foi atribuída a você.',
        'nova_no_setor' => 'Nova demanda %s sem responsável no seu setor.',
        'reatribuida' => 'A demanda %s foi atribuída a outra pessoa.',
        'responsavel_alterado' => 'O responsável pela demanda %s foi alterado.',
        'transferida' => 'A demanda %s foi transferida de setor.',
        'comentario' => 'Novo comentário na demanda %s.',
        'solicitacao_informacoes' => 'A demanda %s aguarda informações suas.',
        'resolvida' => 'A demanda %s foi resolvida e aguarda sua confirmação.',
        'rejeitada' => 'A solução da demanda %s foi rejeitada pelo solicitante.',
        'concluida' => 'A demanda %s foi concluída pelo solicitante.',
        'cancelada' => 'A demanda %s foi cancelada.',
    ];

    /**
     * @param  string  $evento  identificador único do evento (ex.: "historico:15")
     * @param  array<int, array{0: int|null, 1: string}>  $destinatarios  pares [usuário, tipo], em ordem de prioridade
     */
    public function notificar(Demanda $demanda, User $autor, string $evento, array $destinatarios): int
    {
        $porUsuario = [];
        foreach ($destinatarios as [$usuarioId, $tipo]) {
            // Sem notificar o próprio autor e sem duplicar para a mesma pessoa no mesmo evento.
            if ($usuarioId === null || $usuarioId === $autor->id || isset($porUsuario[$usuarioId])) {
                continue;
            }
            $porUsuario[$usuarioId] = $tipo;
        }

        if ($porUsuario === []) {
            return 0;
        }

        $ativos = User::query()->ativos()->whereIn('id', array_keys($porUsuario))->pluck('id')->all();
        $agora = now();
        $linhas = [];

        foreach ($ativos as $usuarioId) {
            $tipo = $porUsuario[$usuarioId];
            $linhas[] = [
                // UUID ordenado pelo tempo: desempata a ordenação de notificações do mesmo segundo.
                'id' => (string) Str::orderedUuid(),
                'type' => self::TIPO,
                'notifiable_type' => User::class,
                'notifiable_id' => $usuarioId,
                'evento' => $evento,
                'demanda_id' => $demanda->id,
                'data' => json_encode([
                    'tipo' => $tipo,
                    'demanda_id' => $demanda->id,
                    'protocolo' => $demanda->protocolo,
                    'mensagem' => sprintf(self::MENSAGENS[$tipo], $demanda->protocolo),
                ], JSON_UNESCAPED_UNICODE),
                'read_at' => null,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        // A restrição única (usuário, evento) garante a ausência de duplicidade mesmo em reprocessamentos.
        return DB::table('notifications')->insertOrIgnore($linhas);
    }

    /** @return list<int> */
    public function gestoresDoSetor(int $setorId): array
    {
        return User::query()->gestoresDoSetor($setorId)->pluck('id')->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{0: int, 1: string}>
     */
    public static function todos(array $ids, string $tipo): array
    {
        return array_map(fn (int $id) => [$id, $tipo], $ids);
    }
}
