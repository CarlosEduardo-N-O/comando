<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

class AutenticacaoTest extends ApiTestCase
{
    public function test_login_me_e_logout(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => $this->solicitante->email, 'password' => 'SenhaTeste123'])
            ->assertOk()
            ->assertJsonPath('data.id', $this->solicitante->id)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $this->solicitante->email);

        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_credenciais_invalidas_e_usuario_inativo_nao_autenticam(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => $this->solicitante->email, 'password' => 'errada123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->solicitante->forceFill(['ativo' => false])->save();
        $this->postJson('/api/v1/auth/login', ['email' => $this->solicitante->email, 'password' => 'SenhaTeste123'])
            ->assertUnprocessable();
    }

    public function test_limita_tentativas_de_login(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $this->solicitante->email, 'password' => 'errada'.$i])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => $this->solicitante->email, 'password' => 'SenhaTeste123'])
            ->assertStatus(429);
    }

    public function test_rotas_protegidas_exigem_autenticacao(): void
    {
        $this->getJson('/api/v1/demandas')->assertUnauthorized()->assertJsonPath('message', 'Sessão não autenticada. Entre novamente.');
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_usuario_inativado_perde_acesso_na_sessao_atual(): void
    {
        $this->actingAs($this->solicitante)->getJson('/api/v1/auth/me')->assertOk();
        $this->solicitante->forceFill(['ativo' => false])->save();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_usuario_altera_apenas_o_proprio_nome(): void
    {
        $this->actingAs($this->solicitante)->patchJson('/api/v1/auth/me', [
            'name' => 'Nome Novo',
            'perfil' => 'administrador',
            'setor_id' => $this->rh->id,
            'ativo' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Nome Novo');

        $atual = $this->solicitante->fresh();
        $this->assertSame('colaborador', $atual->perfil);
        $this->assertSame($this->ti->id, $atual->setor_id);
        $this->assertTrue($atual->ativo);
    }

    public function test_troca_de_senha_exige_senha_atual_e_revoga_outras_sessoes(): void
    {
        DB::table('sessions')->insert(['id' => 'outra-sessao', 'user_id' => $this->solicitante->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->solicitante)->putJson('/api/v1/auth/password', [
            'current_password' => 'incorreta',
            'password' => 'NovaSenha2026',
            'password_confirmation' => 'NovaSenha2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'SenhaTeste123',
            'password' => 'NovaSenha2026',
            'password_confirmation' => 'NovaSenha2026',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'outra-sessao']);
        $this->assertTrue(password_verify('NovaSenha2026', $this->solicitante->fresh()->password));
    }

    public function test_health_nao_expoe_dados_sensiveis(): void
    {
        $resposta = $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok', 'banco' => 'ok']);
        $this->assertStringNotContainsString('postgres', $resposta->getContent());
    }
}
