<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O caminho interno nunca é exposto pela API: o download passa por rota
 * autenticada que revalida o acesso à demanda a cada requisição.
 */
class Anexo extends Model
{
    protected $table = 'demanda_anexos';

    protected $guarded = ['*'];

    protected $hidden = ['caminho', 'disco'];

    protected function casts(): array
    {
        return ['tamanho' => 'integer'];
    }

    public function demanda(): BelongsTo
    {
        return $this->belongsTo(Demanda::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }
}
