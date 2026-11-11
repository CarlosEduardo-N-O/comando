<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Prévia sem conteúdo sensível. "demanda_disponivel" é calculado com a regra
 * de visibilidade atual: se o usuário perdeu o acesso, a interface informa
 * que a demanda não está mais disponível.
 *
 * @mixin DatabaseNotification
 */
class NotificacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dados = is_array($this->data) ? $this->data : (json_decode((string) $this->data, true) ?: []);

        return [
            'id' => $this->id,
            'tipo' => $dados['tipo'] ?? null,
            'mensagem' => $dados['mensagem'] ?? '',
            'protocolo' => $dados['protocolo'] ?? null,
            'demanda_id' => $dados['demanda_id'] ?? null,
            'demanda_disponivel' => (bool) ($this->demanda_disponivel ?? false),
            'lida' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
