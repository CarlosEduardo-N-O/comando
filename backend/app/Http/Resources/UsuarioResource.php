<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dados completos do usuário (próprio perfil ou administração). Nunca expõe
 * senha, remember_token ou dados de sessão.
 *
 * @mixin User
 */
class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'perfil' => $this->perfil,
            'ativo' => $this->ativo,
            'setor_id' => $this->setor_id,
            'setor' => $this->whenLoaded('setor', fn () => $this->setor ? ['id' => $this->setor->id, 'nome' => $this->setor->nome] : null),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
