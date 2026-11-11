<?php

namespace Tests\Feature;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cenário base: setores TI e RH, um administrador, um gestor de RH e
 * colaboradores nos dois setores.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected Setor $ti;
    protected Setor $rh;
    protected User $admin;
    protected User $gestorRh;
    protected User $solicitante;   // colaborador de TI
    protected User $colegaTi;      // mesmo setor do solicitante
    protected User $atendenteRh;   // colaborador de RH
    protected User $outroRh;       // outro colaborador de RH

    protected function setUp(): void
    {
        parent::setUp();

        // Requisições "do próprio domínio" para o Sanctum iniciar a sessão.
        $this->withHeader('Referer', 'http://localhost/');

        $this->ti = $this->setor('TI');
        $this->rh = $this->setor('RH');
        $this->admin = $this->usuario($this->ti, User::PERFIL_ADMINISTRADOR);
        $this->gestorRh = $this->usuario($this->rh, User::PERFIL_GESTOR);
        $this->solicitante = $this->usuario($this->ti);
        $this->colegaTi = $this->usuario($this->ti);
        $this->atendenteRh = $this->usuario($this->rh);
        $this->outroRh = $this->usuario($this->rh);
    }

    /** @return array<string, mixed> */
    protected function payload(array $extra = []): array
    {
        return array_merge([
            'titulo' => 'Preciso de suporte',
            'descricao' => 'Contexto reservado',
            'categoria_id' => $this->categoria()->id,
            'setor_destino_id' => $this->rh->id,
            'prioridade' => 'normal',
        ], $extra);
    }

    /** Tipo da notificação mais recente do usuário. */
    protected function ultimoTipo(User $usuario): ?string
    {
        return $usuario->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id')->first()?->data['tipo'];
    }

    protected function url(int $id, string $sufixo = ''): string
    {
        return "/api/v1/demandas/{$id}".($sufixo ? "/{$sufixo}" : '');
    }
}
