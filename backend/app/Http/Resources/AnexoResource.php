<?php

namespace App\Http\Resources;

use App\Models\Anexo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sem caminho interno ou URL pública: o download usa a rota autenticada
 * /api/v1/demandas/{demanda}/anexos/{anexo}/download.
 *
 * @mixin Anexo
 */
class AnexoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome_original,
            'mime' => $this->mime,
            'tamanho' => $this->tamanho,
            'comentario_id' => $this->comentario_id,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
