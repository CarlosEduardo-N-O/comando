<?php

namespace App\Http\Resources;

use App\Models\Demanda;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Visão resumida da fila do setor: somente protocolo, título, categoria,
 * prioridade e data de abertura (além do id técnico e da indicação se o
 * usuário pode assumir). Descrição, solicitante e anexos ficam ocultos.
 *
 * @mixin Demanda
 */
class DemandaFilaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $usuario = $request->user();

        return [
            'id' => $this->id,
            'protocolo' => $this->protocolo,
            'titulo' => $this->titulo,
            'categoria' => $this->categoria ? ['id' => $this->categoria->id, 'nome' => $this->categoria->nome] : null,
            'prioridade' => $this->prioridade,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'pode_assumir' => $usuario !== null && $usuario->can('assumir', $this->resource),
        ];
    }
}
