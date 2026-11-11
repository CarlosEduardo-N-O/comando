<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $setor_id
 * @property string $perfil
 * @property bool $ativo
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PERFIL_COLABORADOR = 'colaborador';
    public const PERFIL_GESTOR = 'gestor';
    public const PERFIL_ADMINISTRADOR = 'administrador';

    public const PERFIS = [
        self::PERFIL_COLABORADOR,
        self::PERFIL_GESTOR,
        self::PERFIL_ADMINISTRADOR,
    ];

    /**
     * Campos controlados pelo servidor (setor_id, perfil, ativo) só chegam
     * aqui pelo UsuarioService, acessível apenas a administradores. As rotas
     * de autoatendimento validam e repassam somente nome e senha.
     */
    protected $fillable = ['name', 'email', 'password', 'setor_id', 'perfil', 'ativo'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'perfil' => self::PERFIL_COLABORADOR,
        'ativo' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
            'setor_id' => 'integer',
        ];
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function isAdministrador(): bool
    {
        return $this->perfil === self::PERFIL_ADMINISTRADOR;
    }

    public function isGestor(): bool
    {
        return $this->perfil === self::PERFIL_GESTOR;
    }

    /** Gestor do setor informado (não inclui administradores). */
    public function isGestorDoSetor(?int $setorId): bool
    {
        return $this->isGestor() && $setorId !== null && $this->setor_id === $setorId;
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeGestoresDoSetor(Builder $query, int $setorId): Builder
    {
        return $query->ativos()->where('perfil', self::PERFIL_GESTOR)->where('setor_id', $setorId);
    }
}
