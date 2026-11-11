<?php

namespace Tests\Feature;

use App\Models\Demanda;
use App\Models\User;

class DemandaTest extends ApiTestCase
{
    public function test_abre_demanda_para_outro_setor_com_dados_do_servidor(): void
    {
        $resposta = $this->actingAs($this->solicitante)->postJson('/api/v1/demandas', $this->payload([
            'solicitante_id' => $this->colegaTi->id,
            'setor_origem_id' => $this->rh->id,
            'status' => 'concluida',
            'protocolo' => 'CMD-999999',
        ]))->assertCreated();

        $resposta->assertJsonPath('data.solicitante.id', $this->solicitante->id)
            ->assertJsonPath('data.setor_origem.id', $this->ti->id)
            ->assertJsonPath('data.setor_destino.id', $this->rh->id)
            ->assertJsonPath('data.status', 'aberta')
            ->assertJsonPath('data.responsavel', null);
        $this->assertMatchesRegularExpression('/^CMD-\d{6}$/', $resposta->json('data.protocolo'));
        $this->assertNotSame('CMD-999999', $resposta->json('data.protocolo'));
    }

    public function test_abre_demanda_no_proprio_setor_atribuida_a_colega(): void
    {
        $this->actingAs($this->solicitante)->postJson('/api/v1/demandas', $this->payload([
            'setor_destino_id' => $this->ti->id,
            'responsavel_id' => $this->colegaTi->id,
        ]))->assertCreated()->assertJsonPath('data.responsavel.id', $this->colegaTi->id);
    }

    public function test_valida_responsavel(): void
    {
        $this->actingAs($this->solicitante);

        // Si mesmo
        $this->postJson('/api/v1/demandas', $this->payload(['setor_destino_id' => $this->ti->id, 'responsavel_id' => $this->solicitante->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('responsavel_id');
        // Fora do setor de destino
        $this->postJson('/api/v1/demandas', $this->payload(['responsavel_id' => $this->colegaTi->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('responsavel_id');
        // Inativo
        $this->atendenteRh->forceFill(['ativo' => false])->save();
        $this->postJson('/api/v1/demandas', $this->payload(['responsavel_id' => $this->atendenteRh->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('responsavel_id');
        $this->assertSame(0, Demanda::query()->count());
    }

    public function test_protocolos_sao_unicos_e_sequenciais(): void
    {
        $a = $this->demanda($this->solicitante, $this->rh);
        $b = $this->demanda($this->solicitante, $this->rh);
        $this->assertNotSame($a->protocolo, $b->protocolo);
        $this->assertSame((int) substr($a->protocolo, 4) + 1, (int) substr($b->protocolo, 4));
    }

    public function test_visibilidade_por_participacao(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);

        $this->actingAs($this->solicitante)->getJson($this->url($demanda->id))->assertOk()->assertJsonPath('data.descricao', 'Conteúdo reservado da demanda.');
        $this->actingAs($this->atendenteRh)->getJson($this->url($demanda->id))->assertOk();
        $this->actingAs($this->gestorRh)->getJson($this->url($demanda->id))->assertOk();
        $this->actingAs($this->admin)->getJson($this->url($demanda->id))->assertOk();

        // Mesmo setor do solicitante não concede acesso; nem colega do setor de destino.
        $this->actingAs($this->colegaTi)->getJson($this->url($demanda->id))->assertForbidden();
        $this->actingAs($this->outroRh)->getJson($this->url($demanda->id))->assertForbidden();
        $this->actingAs($this->colegaTi)->getJson($this->url($demanda->id, 'comentarios'))->assertForbidden();
        $this->actingAs($this->colegaTi)->getJson($this->url($demanda->id, 'historico'))->assertForbidden();
    }

    public function test_fila_resumida_e_assumir(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh);

        $fila = $this->actingAs($this->outroRh)->getJson('/api/v1/demandas?contexto=fila')->assertOk();
        $this->assertEqualsCanonicalizing(
            ['id', 'protocolo', 'titulo', 'categoria', 'prioridade', 'created_at', 'pode_assumir'],
            array_keys($fila->json('data.0'))
        );
        $fila->assertJsonMissing(['descricao' => 'Conteúdo reservado da demanda.']);
        $this->getJson($this->url($demanda->id))->assertForbidden();

        // Colaborador de outro setor não vê a fila de RH.
        $this->actingAs($this->colegaTi)->getJson('/api/v1/demandas?contexto=fila')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson($this->url($demanda->id, 'atribuicao'), ['assumir' => true])->assertForbidden();

        $this->actingAs($this->outroRh)->patchJson($this->url($demanda->id, 'atribuicao'), ['assumir' => true])
            ->assertOk()->assertJsonPath('data.responsavel.id', $this->outroRh->id);
        $this->getJson($this->url($demanda->id))->assertOk();

        // Já assumida: conflito.
        $this->actingAs($this->atendenteRh)->patchJson($this->url($demanda->id, 'atribuicao'), ['assumir' => true])->assertStatus(409);
        $this->assertDatabaseHas('demanda_historicos', ['demanda_id' => $demanda->id, 'acao' => 'assuncao', 'autor_id' => $this->outroRh->id]);
    }

    public function test_solicitante_nao_assume_a_propria_demanda(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->ti);
        $this->actingAs($this->solicitante)->patchJson($this->url($demanda->id, 'atribuicao'), ['assumir' => true])->assertForbidden();
    }

    public function test_gestor_distribui_e_reatribui_e_antigo_responsavel_perde_acesso(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh);

        $this->actingAs($this->atendenteRh)->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->outroRh->id])->assertForbidden();

        $this->actingAs($this->gestorRh)->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->atendenteRh->id])->assertOk();
        $this->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->colegaTi->id])->assertUnprocessable();
        $this->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->outroRh->id])->assertOk();

        $this->actingAs($this->atendenteRh)->getJson($this->url($demanda->id))->assertForbidden();
        $this->assertDatabaseHas('demanda_historicos', ['demanda_id' => $demanda->id, 'acao' => 'atribuicao']);
        $this->assertDatabaseHas('demanda_historicos', ['demanda_id' => $demanda->id, 'acao' => 'reatribuicao']);

        // Gestor de outro setor não distribui.
        $gestorTi = $this->usuario($this->ti, User::PERFIL_GESTOR);
        $this->actingAs($gestorTi)->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->atendenteRh->id])->assertForbidden();
    }

    public function test_transferencia_somente_admin_com_justificativa(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        $this->actingAs($this->atendenteRh)->patchJson($this->url($demanda->id, 'status'), ['status' => 'em_andamento'])->assertOk();
        $this->patchJson($this->url($demanda->id, 'prazo'), ['prazo_atendimento' => now()->addDays(3)->toIso8601String()])->assertOk();

        $this->actingAs($this->gestorRh)->patchJson($this->url($demanda->id, 'destino'), ['setor_destino_id' => $this->ti->id, 'justificativa' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->patchJson($this->url($demanda->id, 'destino'), ['setor_destino_id' => $this->ti->id])
            ->assertUnprocessable()->assertJsonValidationErrors('justificativa');
        $this->patchJson($this->url($demanda->id, 'destino'), ['setor_destino_id' => $this->ti->id, 'responsavel_id' => $this->atendenteRh->id, 'justificativa' => 'Competência da TI'])
            ->assertUnprocessable()->assertJsonValidationErrors('responsavel_id');

        $this->patchJson($this->url($demanda->id, 'destino'), ['setor_destino_id' => $this->ti->id, 'responsavel_id' => $this->colegaTi->id, 'justificativa' => 'Competência da TI'])
            ->assertOk()
            ->assertJsonPath('data.status', 'aberta')
            ->assertJsonPath('data.prazo_atendimento', null)
            ->assertJsonPath('data.setor_destino.id', $this->ti->id)
            ->assertJsonPath('data.setor_origem.id', $this->ti->id);

        $historico = $demanda->historicos()->where('acao', 'transferencia')->firstOrFail();
        $this->assertSame('Competência da TI', $historico->justificativa);
        $this->assertSame($this->rh->id, $historico->antes['setor_destino_id']);
        $this->assertSame($this->ti->id, $historico->depois['setor_destino_id']);
        $this->assertSame('em_andamento', $historico->antes['status']);

        $this->actingAs($this->atendenteRh)->getJson($this->url($demanda->id))->assertForbidden();
    }

    public function test_setor_de_origem_historico_preservado(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh);
        $this->actingAs($this->admin)->patchJson("/api/v1/usuarios/{$this->solicitante->id}", ['setor_id' => $this->rh->id])->assertOk();
        $this->assertSame($this->ti->id, $demanda->fresh()->setor_origem_id);
    }

    public function test_prioridade_somente_gestor_do_destino_ou_admin_com_historico(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);

        $this->actingAs($this->solicitante)->patchJson($this->url($demanda->id, 'prioridade'), ['prioridade' => 'urgente'])->assertForbidden();
        $this->actingAs($this->atendenteRh)->patchJson($this->url($demanda->id, 'prioridade'), ['prioridade' => 'urgente'])->assertForbidden();
        $this->actingAs($this->gestorRh)->patchJson($this->url($demanda->id, 'prioridade'), ['prioridade' => 'urgente'])->assertOk()->assertJsonPath('data.prioridade', 'urgente');

        $historico = $demanda->historicos()->where('acao', 'prioridade')->firstOrFail();
        $this->assertSame(['prioridade' => 'normal'], $historico->antes);
        $this->assertSame(['prioridade' => 'urgente'], $historico->depois);
    }

    public function test_titulo_descricao_e_categoria_nao_sao_editaveis(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh);
        $this->actingAs($this->solicitante)->patchJson($this->url($demanda->id), ['titulo' => 'Outro'])->assertStatus(405);
        $this->putJson($this->url($demanda->id), ['titulo' => 'Outro'])->assertStatus(405);
    }

    public function test_contextos_de_listagem_respeitam_perfil(): void
    {
        $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        $this->demanda($this->colegaTi, $this->ti);

        $this->actingAs($this->solicitante)->getJson('/api/v1/demandas?contexto=minhas')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/demandas?contexto=setor')->assertForbidden();
        $this->getJson('/api/v1/demandas?contexto=todas')->assertForbidden();
        $this->getJson('/api/v1/demandas?contexto=atendimento')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->atendenteRh)->getJson('/api/v1/demandas?contexto=atendimento')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->gestorRh)->getJson('/api/v1/demandas?contexto=setor')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/demandas?contexto=todas')->assertForbidden();
        $this->actingAs($this->admin)->getJson('/api/v1/demandas?contexto=todas')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_filtros_e_ordenacao_no_servidor(): void
    {
        $alta = $this->demanda($this->solicitante, $this->rh, null, ['titulo' => 'Folha de pagamento', 'prioridade' => 'alta']);
        $this->demanda($this->solicitante, $this->rh, null, ['titulo' => 'Crachá', 'prioridade' => 'baixa']);
        $atrasada = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh, ['titulo' => 'Férias', 'prioridade' => 'urgente']);
        Demanda::query()->whereKey($atrasada->id)->update(['prazo_atendimento' => now()->subHour(), 'prazo_desejado' => now()->subDays(2)->toDateString()]);
        Demanda::query()->whereKey($alta->id)->update(['prazo_desejado' => now()->subDays(5)->toDateString()]);

        $this->actingAs($this->admin);
        $this->getJson('/api/v1/demandas?contexto=todas&busca=folha')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $alta->id);
        $this->getJson('/api/v1/demandas?contexto=todas&busca='.$alta->protocolo)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/demandas?contexto=todas&prioridade=baixa')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/demandas?contexto=todas&responsavel_id=nenhum')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/demandas?contexto=todas&atrasadas=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $atrasada->id);
        $this->getJson('/api/v1/demandas?contexto=todas&ordem=prioridade&direcao=desc')->assertJsonPath('data.0.prioridade', 'urgente');
        $this->getJson('/api/v1/demandas?contexto=todas&criado_de='.now('America/Sao_Paulo')->toDateString())->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/demandas?contexto=todas&criado_ate='.now()->subDays(3)->toDateString())->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/demandas?contexto=todas&por_pagina=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/demandas?contexto=todas&status=invalido')->assertUnprocessable();
    }

    public function test_indicadores_restritos_e_atraso_pelo_prazo_de_atendimento(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        Demanda::query()->whereKey($demanda->id)->update(['prazo_desejado' => now()->subDays(3)->toDateString()]);

        $this->actingAs($this->solicitante)->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.minhas_abertas', 1)
            ->assertJsonPath('data.atrasadas', 0);

        Demanda::query()->whereKey($demanda->id)->update(['prazo_atendimento' => now()->subHour()]);
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.atrasadas', 1);
        $this->actingAs($this->atendenteRh)->getJson('/api/v1/dashboard')->assertJsonPath('data.atribuidas_a_mim', 1)->assertJsonPath('data.atrasadas', 1);
        // Colega de setor do solicitante não enxerga nada da demanda.
        $this->actingAs($this->colegaTi)->getJson('/api/v1/dashboard')->assertJsonPath('data.minhas_abertas', 0)->assertJsonPath('data.atrasadas', 0);

        Demanda::query()->whereKey($demanda->id)->update(['status' => 'resolvida']);
        $this->actingAs($this->solicitante)->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.atrasadas', 0)
            ->assertJsonPath('data.aguardando_minha_confirmacao', 1);
    }

    public function test_acoes_disponiveis_por_participante(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);

        $acoesSolicitante = $this->actingAs($this->solicitante)->getJson($this->url($demanda->id))->json('data.acoes');
        $this->assertEqualsCanonicalizing(['comentar', 'cancelar'], $acoesSolicitante);

        $acoesResponsavel = $this->actingAs($this->atendenteRh)->getJson($this->url($demanda->id))->json('data.acoes');
        $this->assertEqualsCanonicalizing(['comentar', 'definir_prazo', 'iniciar'], $acoesResponsavel);

        $acoesGestor = $this->actingAs($this->gestorRh)->getJson($this->url($demanda->id))->json('data.acoes');
        $this->assertContains('atribuir', $acoesGestor);
        $this->assertNotContains('transferir', $acoesGestor);

        $this->assertContains('transferir', $this->actingAs($this->admin)->getJson($this->url($demanda->id))->json('data.acoes'));
    }
}
