<?php

namespace Tests\Feature;

use App\Models\Historico;
use App\Models\User;
use LogicException;

class HistoricoNotificacaoTest extends ApiTestCase
{
    public function test_historico_registra_autor_acao_e_valores(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh);
        $this->actingAs($this->gestorRh)->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->atendenteRh->id])->assertOk();

        $historico = $this->getJson($this->url($demanda->id, 'historico'))->assertOk();
        $historico->assertJsonPath('data.0.acao', 'atribuicao')
            ->assertJsonPath('data.0.autor.id', $this->gestorRh->id)
            ->assertJsonPath('data.0.antes.responsavel_id', null)
            ->assertJsonPath('data.0.depois.responsavel_id', $this->atendenteRh->id)
            ->assertJsonPath('data.0.depois.responsavel', $this->atendenteRh->name)
            ->assertJsonPath('data.1.acao', 'criacao');
    }

    public function test_historico_e_imutavel(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh);
        $evento = Historico::query()->where('demanda_id', $demanda->id)->firstOrFail();

        $this->expectException(LogicException::class);
        $evento->forceFill(['acao' => 'adulterado'])->save();
    }

    public function test_nova_demanda_sem_responsavel_notifica_gestores_do_setor(): void
    {
        $segundoGestor = $this->usuario($this->rh, User::PERFIL_GESTOR);
        $this->demanda($this->solicitante, $this->rh);

        $this->assertSame(1, $this->gestorRh->notifications()->count());
        $this->assertSame(1, $segundoGestor->notifications()->count());
        $this->assertSame(0, $this->atendenteRh->notifications()->count());
        $this->assertSame(0, $this->solicitante->notifications()->count(), 'o autor não é notificado');
        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_notificacoes_por_evento_sem_duplicidade_nem_autor(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        $this->assertSame('atribuida', $this->ultimoTipo($this->atendenteRh));

        // Gestor reatribui: novo responsável, antigo responsável e solicitante; gestor (autor) não.
        $this->actingAs($this->gestorRh)->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->outroRh->id])->assertOk();
        $this->assertSame('atribuida', $this->ultimoTipo($this->outroRh));
        $this->assertSame('reatribuida', $this->ultimoTipo($this->atendenteRh));
        $this->assertSame('responsavel_alterado', $this->ultimoTipo($this->solicitante));
        $this->assertSame(0, $this->gestorRh->notifications()->count());

        // Comentário do responsável notifica apenas o solicitante.
        $this->actingAs($this->outroRh)->postJson($this->url($demanda->id, 'comentarios'), ['mensagem' => 'Oi'])->assertCreated();
        $this->assertSame('comentario', $this->ultimoTipo($this->solicitante));
        $this->assertSame(1, $this->outroRh->notifications()->count());

        $status = $this->url($demanda->id, 'status');
        $this->patchJson($status, ['status' => 'em_andamento'])->assertOk();
        $this->patchJson($status, ['status' => 'aguardando_solicitante', 'justificativa' => 'Detalhe'])->assertOk();
        $this->assertSame('solicitacao_informacoes', $this->ultimoTipo($this->solicitante));
        $this->patchJson($status, ['status' => 'em_andamento'])->assertOk();
        $this->patchJson($status, ['status' => 'resolvida', 'justificativa' => 'Pronto'])->assertOk();
        $this->assertSame('resolvida', $this->ultimoTipo($this->solicitante));

        $this->actingAs($this->solicitante)->patchJson($status, ['status' => 'em_andamento', 'justificativa' => 'Não resolveu'])->assertOk();
        $this->assertSame('rejeitada', $this->ultimoTipo($this->outroRh));

        $this->actingAs($this->outroRh)->patchJson($status, ['status' => 'resolvida', 'justificativa' => 'Agora sim'])->assertOk();
        $this->actingAs($this->solicitante)->patchJson($status, ['status' => 'concluida'])->assertOk();
        $this->assertSame('concluida', $this->ultimoTipo($this->outroRh));

        // Uma notificação por pessoa e evento.
        $duplicadas = \DB::table('notifications')->selectRaw('notifiable_id, evento, count(*) c')->groupBy('notifiable_id', 'evento')->havingRaw('count(*) > 1')->count();
        $this->assertSame(0, $duplicadas);
    }

    public function test_transferencia_e_cancelamento_notificam_participantes(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh);
        $gestorTi = $this->usuario($this->ti, User::PERFIL_GESTOR);

        $this->actingAs($this->admin)->patchJson($this->url($demanda->id, 'destino'), ['setor_destino_id' => $this->ti->id, 'justificativa' => 'Competência da TI'])->assertOk();
        $this->assertSame('transferida', $this->ultimoTipo($this->solicitante));
        $this->assertSame('transferida', $this->ultimoTipo($this->atendenteRh));
        $this->assertSame('nova_no_setor', $this->ultimoTipo($gestorTi));

        $this->actingAs($this->solicitante)->patchJson($this->url($demanda->id, 'status'), ['status' => 'cancelada', 'justificativa' => 'Resolvido por fora'])->assertOk();
        $this->assertSame('cancelada', $this->ultimoTipo($gestorTi));
    }

    public function test_api_de_notificacoes(): void
    {
        $demanda = $this->demanda($this->solicitante, $this->rh, $this->atendenteRh, ['titulo' => 'Título confidencial']);
        $this->demanda($this->colegaTi, $this->rh, $this->atendenteRh);

        $this->actingAs($this->atendenteRh);
        $lista = $this->getJson('/api/v1/notificacoes')->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['data', 'meta', 'links']);
        $this->assertStringNotContainsString('Título confidencial', $lista->getContent());
        $this->assertStringNotContainsString('Conteúdo reservado', $lista->getContent());
        $lista->assertJsonPath('data.0.demanda_disponivel', true);

        $this->getJson('/api/v1/notificacoes/contador')->assertJsonPath('data.nao_lidas', 2);
        $this->patchJson('/api/v1/notificacoes/'.$lista->json('data.0.id').'/ler')->assertOk();
        $this->getJson('/api/v1/notificacoes/contador')->assertJsonPath('data.nao_lidas', 1);
        $this->getJson('/api/v1/notificacoes?nao_lidas=1')->assertJsonCount(1, 'data');
        $this->patchJson('/api/v1/notificacoes/ler-todas')->assertOk()->assertJsonPath('data.marcadas', 1);
        $this->getJson('/api/v1/notificacoes/contador')->assertJsonPath('data.nao_lidas', 0);

        // Notificação de outra pessoa: 404.
        $alheia = $this->solicitante->notifications()->first();
        if ($alheia) {
            $this->patchJson('/api/v1/notificacoes/'.$alheia->id.'/ler')->assertNotFound();
        }

        // Após reatribuição, a demanda deixa de estar disponível para o antigo responsável.
        $this->actingAs($this->gestorRh)->patchJson($this->url($demanda->id, 'atribuicao'), ['responsavel_id' => $this->outroRh->id])->assertOk();
        $itens = collect($this->actingAs($this->atendenteRh)->getJson('/api/v1/notificacoes')->json('data'));
        $this->assertFalse($itens->firstWhere('demanda_id', $demanda->id)['demanda_disponivel']);
        $this->getJson($this->url($demanda->id))->assertForbidden();
    }
}
