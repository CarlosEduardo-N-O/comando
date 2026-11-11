<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro imutável dos eventos da demanda. Não há rota de alteração e o
 * modelo recusa atualizações e exclusões.
 */
class Historico extends Model
{
    public const CRIACAO = 'criacao';
    public const ATRIBUICAO = 'atribuicao';
    public const REATRIBUICAO = 'reatribuicao';
    public const ASSUNCAO = 'assuncao';
    public const TRANSFERENCIA = 'transferencia';
    public const PRIORIDADE = 'prioridade';
    public const PRAZO = 'prazo';
    public const INICIO = 'inicio_atendimento';
    public const RETOMADA = 'retomada_atendimento';
    public const SOLICITACAO_INFORMACOES = 'solicitacao_informacoes';
    public const RESOLUCAO = 'resolucao';
    public const REJEICAO = 'rejeicao';
    public const CONCLUSAO = 'conclusao';
    public const CANCELAMENTO = 'cancelamento';

    public const UPDATED_AT = null;

    protected $table = 'demanda_historicos';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['antes' => 'array', 'depois' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('O histórico não pode ser alterado.'));
        static::deleting(fn () => throw new LogicException('O histórico não pode ser excluído.'));
    }

    public function demanda(): BelongsTo
    {
        return $this->belongsTo(Demanda::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
