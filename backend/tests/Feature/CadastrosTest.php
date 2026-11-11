<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CadastrosTest extends ApiTestCase
{
    public function test_somente_administrador_gerencia_cadastros(): void
    {
        foreach (['usuarios', 'setores', 'categorias'] as $recurso) {
            $this->actingAs($this->gestorRh)->getJson("/api/v1/{$recurso}")->assertForbidden();
            $this->actingAs($this->solicitante)->postJson("/api/v1/{$recurso}", [])->assertForbidden();
            $this->actingAs($this->admin)->getJson("/api/v1/{$recurso}")->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
        }
    }

    public function test_administrador_cria_e_altera_usuario_sem_expor_senha(): void
    {
        $resposta = $this->actingAs($this->admin)->postJson('/api/v1/usuarios', [
            'name' => 'Nova Pessoa',
            'email' => 'Nova.Pessoa@Empresa.com.br',
            'password' => 'SenhaForte2026',
            'setor_id' => $this->rh->id,
            'perfil' => 'gestor',
        ])->assertCreated()->assertJsonMissingPath('data.password');

        $id = $resposta->json('data.id');
        $this->assertSame('nova.pessoa@empresa.com.br', $resposta->json('data.email'));

        $this->patchJson("/api/v1/usuarios/{$id}", ['perfil' => 'colaborador'])->assertOk()->assertJsonPath('data.perfil', 'colaborador');
        $this->postJson('/api/v1/usuarios', [
            'name' => 'Duplicado', 'email' => 'nova.pessoa@empresa.com.br', 'password' => 'SenhaForte2026',
            'setor_id' => $this->rh->id, 'perfil' => 'colaborador',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        // Não existe exclusão física.
        $this->deleteJson("/api/v1/usuarios/{$id}")->assertStatus(405);
    }

    public function test_bloqueia_remocao_do_ultimo_administrador(): void
    {
        $this->actingAs($this->admin)->patchJson("/api/v1/usuarios/{$this->admin->id}", ['ativo' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('ativo');
        $this->patchJson("/api/v1/usuarios/{$this->admin->id}", ['perfil' => 'gestor'])
            ->assertUnprocessable()->assertJsonValidationErrors('perfil');

        $segundo = $this->usuario($this->ti, User::PERFIL_ADMINISTRADOR);
        $this->patchJson("/api/v1/usuarios/{$segundo->id}", ['perfil' => 'colaborador'])->assertOk();
        $this->patchJson("/api/v1/usuarios/{$this->admin->id}", ['ativo' => false])->assertUnprocessable();
    }

    public function test_bloqueia_mudanca_de_setor_e_inativacao_com_demandas_em_aberto(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);

        $this->actingAs($this->admin)->patchJson("/api/v1/usuarios/{$this->atendenteRh->id}", ['setor_id' => $this->ti->id])
            ->assertUnprocessable()->assertJsonValidationErrors('setor_id');
        $this->patchJson("/api/v1/usuarios/{$this->atendenteRh->id}", ['ativo' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('ativo');

        // Após reatribuir, a alteração é permitida.
        $this->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->outroRh->id])->assertOk();
        $this->patchJson("/api/v1/usuarios/{$this->atendenteRh->id}", ['setor_id' => $this->ti->id])->assertOk();
    }

    public function test_inativacao_revoga_sessoes_e_impede_novas_demandas(): void
    {
        DB::table('sessions')->insert(['id' => 'sessao-ativa', 'user_id' => $this->outroRh->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->admin)->patchJson("/api/v1/usuarios/{$this->outroRh->id}", ['ativo' => false])->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-ativa']);

        $this->actingAs($this->solicitante)->postJson('/api/v1/demandas', $this->payload(['responsavel_id' => $this->outroRh->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('responsavel_id');
    }

    public function test_setor_e_categoria_inativos_nao_sao_selecionaveis(): void
    {
        $setor = $this->actingAs($this->admin)->postJson('/api/v1/setores', ['nome' => 'Jurídico', 'descricao' => 'Contratos'])
            ->assertCreated()->json('data.id');
        $categoria = $this->postJson('/api/v1/categorias', ['nome' => 'Contratos'])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/setores/{$setor}", ['ativo' => false])->assertOk()->assertJsonPath('data.ativo', false);
        $this->patchJson("/api/v1/categorias/{$categoria}", ['ativo' => false])->assertOk();

        $this->actingAs($this->solicitante)->postJson('/api/v1/demandas', $this->payload(['setor_destino_id' => $setor]))
            ->assertUnprocessable()->assertJsonValidationErrors('setor_destino_id');
        $this->postJson('/api/v1/demandas', $this->payload(['categoria_id' => $categoria]))
            ->assertUnprocessable()->assertJsonValidationErrors('categoria_id');

        $this->getJson('/api/v1/opcoes/setores')->assertOk()->assertJsonMissing(['nome' => 'Jurídico']);
    }

    public function test_opcoes_nao_expoem_dados_administrativos(): void
    {
        $resposta = $this->actingAs($this->solicitante)->getJson('/api/v1/opcoes/usuarios?setor_id='.$this->rh->id)->assertOk();

        $this->assertEqualsCanonicalizing(['id', 'name', 'setor_id'], array_keys($resposta->json('data.0')));
        $this->assertStringNotContainsString('@', $resposta->getContent());
        $this->assertCount(3, $resposta->json('data')); // gestor + dois colaboradores de RH
    }
}
