<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Comentários não podem ser editados nem excluídos nesta versão. */
class Comentario extends Model
{
    protected $table = 'demanda_comentarios';

    protected $guarded = ['*'];

    public function demanda(): BelongsTo
    {
        return $this->belongsTo(Demanda::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(Anexo::class, 'comentario_id');
    }
}
