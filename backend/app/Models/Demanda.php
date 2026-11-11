<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $protocolo
 * @property string $status
 * @property string $prioridade
 * @property int $solicitante_id
 * @property int|null $responsavel_id
 * @property int $setor_origem_id
 * @property int $setor_destino_id
 */
class Demanda extends Model
{
    public const STATUS_ABERTA = 'aberta';
    public const STATUS_EM_ANDAMENTO = 'em_andamento';
    public const STATUS_AGUARDANDO = 'aguardando_solicitante';
    public const STATUS_RESOLVIDA = 'resolvida';
    public const STATUS_CONCLUIDA = 'concluida';
    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS = [
        self::STATUS_ABERTA,
        self::STATUS_EM_ANDAMENTO,
        self::STATUS_AGUARDANDO,
        self::STATUS_RESOLVIDA,
        self::STATUS_CONCLUIDA,
        self::STATUS_CANCELADA,
    ];

    /** Status em atendimento: permitem distribuição, transferência e prazo, e contam para atraso. */
    public const STATUS_EM_ATENDIMENTO = [
        self::STATUS_ABERTA,
        self::STATUS_EM_ANDAMENTO,
        self::STATUS_AGUARDANDO,
    ];

    /** Status finais: a demanda não recebe alterações nem comentários. */
    public const STATUS_ENCERRADOS = [self::STATUS_CONCLUIDA, self::STATUS_CANCELADA];

    public const PRIORIDADES = ['baixa', 'normal', 'alta', 'urgente'];

    protected $table = 'demandas';

    /** Nada é preenchido em massa: o DemandaService define cada atributo explicitamente. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'prazo_desejado' => 'date:Y-m-d',
            'prazo_atendimento' => 'datetime',
            'resolvida_em' => 'datetime',
            'concluida_em' => 'datetime',
            'cancelada_em' => 'datetime',
            'solicitante_id' => 'integer',
            'responsavel_id' => 'integer',
            'setor_origem_id' => 'integer',
            'setor_destino_id' => 'integer',
            'categoria_id' => 'integer',
        ];
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function setorOrigem(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_origem_id');
    }

    public function setorDestino(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_destino_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class);
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(Anexo::class);
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(Historico::class);
    }

    public function encerrada(): bool
    {
        return in_array($this->status, self::STATUS_ENCERRADOS, true);
    }

    public function emAtendimento(): bool
    {
        return in_array($this->status, self::STATUS_EM_ATENDIMENTO, true);
    }

    public function atrasada(): bool
    {
        return $this->emAtendimento()
            && $this->prazo_atendimento !== null
            && $this->prazo_atendimento->isPast();
    }

    /**
     * Demandas com acesso completo: as solicitadas pelo usuário, as atribuídas
     * a ele, as destinadas ao setor que gerencia e, para administradores,
     * todas. Pertencer ao setor do solicitante não concede acesso.
     */
    public function scopeVisiveisPara(Builder $query, User $user): Builder
    {
        if ($user->isAdministrador()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('solicitante_id', $user->id)
                ->orWhere('responsavel_id', $user->id);

            if ($user->isGestor() && $user->setor_id) {
                $q->orWhere('setor_destino_id', $user->setor_id);
            }
        });
    }

    /** Prazo de atendimento vencido em status de atendimento (o prazo desejado não conta). */
    public function scopeAtrasadas(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_EM_ATENDIMENTO)
            ->whereNotNull('prazo_atendimento')
            ->where('prazo_atendimento', '<', now());
    }
}
