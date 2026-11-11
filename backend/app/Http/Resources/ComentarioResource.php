<?php

namespace App\Http\Resources;

use App\Models\Comentario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comentario */
class ComentarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mensagem' => $this->mensagem,
            'autor' => $this->autor ? ['id' => $this->autor->id, 'name' => $this->autor->name] : null,
            'anexos' => AnexoResource::collection($this->whenLoaded('anexos')),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
