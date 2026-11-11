<?php

namespace App\Http\Resources;

use App\Models\Historico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Historico */
class HistoricoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'acao' => $this->acao,
            'autor' => $this->autor ? ['id' => $this->autor->id, 'name' => $this->autor->name] : null,
            'justificativa' => $this->justificativa,
            'antes' => $this->antes,
            'depois' => $this->depois,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
