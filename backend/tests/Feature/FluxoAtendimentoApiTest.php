<?php

namespace Tests\Feature;

class FluxoAtendimentoApiTest extends ApiTestCase
{
    public function test_fluxo_completo_com_rejeicao_e_confirmacao(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        $status = $this->url($demanda->id, 'status');

        $this->actingAs($this->atendenteRh);
        $this->patchJson($status, ['status' => 'resolvida', 'justificativa' => 'ok'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson($status, ['status' => 'em_andamento'])->assertOk();

        $this->patchJson($status, ['status' => 'aguardando_solicitante'])->assertUnprocessable()->assertJsonValidationErrors('justificativa');
        $this->patchJson($status, ['status' => 'aguardando_solicitante', 'justificativa' => 'Qual o período?'])->assertOk()
            ->assertJsonPath('data.informacao_solicitada', 'Qual o período?');

        // Comentário do solicitante não muda o status.
        $this->actingAs($this->solicitante)->postJson($this->url($demanda->id, 'comentarios'), ['mensagem' => 'Janeiro'])->assertCreated();
        $this->assertSame('aguardando_solicitante', $demanda->fresh()->status);
        // Solicitante não conduz o atendimento.
        $this->patchJson($status, ['status' => 'em_andamento'])->assertForbidden();

        $this->actingAs($this->atendenteRh)->patchJson($status, ['status' => 'em_andamento'])->assertOk();
        $this->patchJson($status, ['status' => 'resolvida'])->assertUnprocessable()->assertJsonValidationErrors('justificativa');
        $this->patchJson($status, ['status' => 'resolvida', 'justificativa' => 'Ajuste realizado'])->assertOk()->assertJsonPath('data.solucao', 'Ajuste realizado');
        $this->assertNotNull($demanda->fresh()->resolvida_em);

        // Somente o solicitante confirma/rejeita.
        $this->patchJson($status, ['status' => 'concluida'])->assertForbidden();
        $this->actingAs($this->gestorRh)->patchJson($status, ['status' => 'concluida'])->assertForbidden();

        $this->actingAs($this->solicitante)->patchJson($status, ['status' => 'em_andamento'])->assertUnprocessable()->assertJsonValidationErrors('justificativa');
        $this->patchJson($status, ['status' => 'em_andamento', 'justificativa' => 'Falta um item'])->assertOk()->assertJsonPath('data.status', 'em_andamento');
        $this->assertNull($demanda->fresh()->resolvida_em);

        $this->actingAs($this->atendenteRh)->patchJson($status, ['status' => 'resolvida', 'justificativa' => 'Item corrigido'])->assertOk();
        $this->actingAs($this->solicitante)->patchJson($status, ['status' => 'concluida'])->assertOk()->assertJsonPath('data.acoes', []);
        $this->assertNotNull($demanda->fresh()->concluida_em);

        // Encerrada: sem alterações nem comentários.
        $this->postJson($this->url($demanda->id, 'comentarios'), ['mensagem' => 'mais'])->assertUnprocessable();
        $this->patchJson($status, ['status' => 'em_andamento', 'justificativa' => 'reabrir'])->assertUnprocessable();
        $this->actingAs($this->admin)->patchJson($this->url($demanda->id, 'prioridade'), ['prioridade' => 'alta'])->assertUnprocessable();

        $acoes = $demanda->historicos()->orderBy('id')->pluck('acao')->all();
        $this->assertSame([
            'criacao', 'inicio_atendimento', 'solicitacao_informacoes', 'retomada_atendimento',
            'resolucao', 'rejeicao', 'resolucao', 'conclusao',
        ], $acoes);
    }

    public function test_iniciar_exige_responsavel(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh);
        $this->actingAs($this->gestorRh)->patchJson($this->url($demanda->id, 'status'), ['status' => 'em_andamento'])
            ->assertUnprocessable()->assertJsonValidationErrors('responsavel_id');
    }

    public function test_gestor_e_admin_conduzem_atendimento(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        $this->actingAs($this->gestorRh)->patchJson($this->url($demanda->id, 'status'), ['status' => 'em_andamento'])->assertOk();
        $this->actingAs($this->admin)->patchJson($this->url($demanda->id, 'status'), ['status' => 'resolvida', 'justificativa' => 'Feito'])->assertOk();
        $this->actingAs($this->outroRh)->getJson($this->url($demanda->id))->assertForbidden();
    }

    public function test_cancelamento_exige_justificativa_e_perfil_autorizado(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        $status = $this->url($demanda->id, 'status');

        $this->actingAs($this->atendenteRh)->patchJson($status, ['status' => 'cancelada', 'justificativa' => 'Não quero'])->assertForbidden();
        $this->actingAs($this->solicitante)->patchJson($status, ['status' => 'cancelada'])->assertUnprocessable()->assertJsonValidationErrors('justificativa');
        $this->patchJson($status, ['status' => 'cancelada', 'justificativa' => 'Não é mais necessário'])->assertOk()->assertJsonPath('data.status', 'cancelada');

        $this->assertNotNull($demanda->fresh()->cancelada_em);
        $this->assertDatabaseHas('demanda_historicos', ['demanda_id' => $demanda->id, 'acao' => 'cancelamento', 'justificativa' => 'Não é mais necessário']);
        $this->actingAs($this->gestorRh)->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->outroRh->id])->assertUnprocessable();
    }

    public function test_gestor_do_destino_cancela_mas_resolvida_nao_pode_ser_cancelada(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        $status = $this->url($demanda->id, 'status');
        $this->actingAs($this->atendenteRh)->patchJson($status, ['status' => 'em_andamento'])->assertOk();
        $this->patchJson($status, ['status' => 'resolvida', 'justificativa' => 'Feito'])->assertOk();

        $this->actingAs($this->solicitante)->patchJson($status, ['status' => 'cancelada', 'justificativa' => 'x'])->assertUnprocessable();

        $outra = $this->demanda($this->solicitante, $this->rh);
        $this->actingAs($this->gestorRh)->patchJson($this->url($outra->id, 'status'), ['status' => 'cancelada', 'justificativa' => 'Duplicada'])->assertOk();
    }

    public function test_prazo_de_atendimento_pelo_responsavel(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        $prazo = $this->url($demanda->id, 'prazo');

        $this->actingAs($this->solicitante)->patchJson($prazo, ['prazo_atendimento' => now()->addDay()->toIso8601String()])->assertForbidden();
        $this->actingAs($this->atendenteRh)->patchJson($prazo, ['prazo_atendimento' => now()->subDay()->toIso8601String()])->assertUnprocessable();
        $this->patchJson($prazo, ['prazo_atendimento' => now()->addDay()->toIso8601String()])->assertOk();
        $this->patchJson($prazo, ['prazo_atendimento' => null])->assertOk()->assertJsonPath('data.prazo_atendimento', null);

        $this->assertSame(2, $demanda->historicos()->where('acao', 'prazo')->count());
    }
}
