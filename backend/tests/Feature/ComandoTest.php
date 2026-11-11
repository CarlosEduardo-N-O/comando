<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cenário de aceite ponta a ponta somente pela API, como o frontend faria:
 * cadastros pelo administrador, abertura, atribuição, comentários com
 * anexo, resolução, confirmação, histórico e notificações.
 */
class ComandoTest extends TestCase
{
    use RefreshDatabase;

    public function test_ciclo_completo_pela_api(): void
    {
        Storage::fake('local');
        $this->withHeader('Referer', 'http://localhost/');

        // Instalação: setor básico + primeiro administrador.
        $this->artisan('comando:install', ['--nome' => 'Admin', '--email' => 'admin@empresa.com.br'])
            ->expectsQuestion('Senha (mínimo 10 caracteres, com letras e números)', 'SenhaAdmin2026')
            ->expectsQuestion('Confirme a senha', 'SenhaAdmin2026')
            ->assertSuccessful();
        // Reexecução não duplica nem pede dados.
        $this->artisan('comando:install')->assertSuccessful();
        $this->assertSame(1, User::query()->where('perfil', 'administrador')->count());

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@empresa.com.br', 'password' => 'SenhaAdmin2026'])->assertOk();
        $financeiro = $this->postJson('/api/v1/setores', ['nome' => 'Financeiro'])->assertCreated()->json('data.id');
        $compras = $this->postJson('/api/v1/setores', ['nome' => 'Compras'])->assertCreated()->json('data.id');
        $categoria = $this->postJson('/api/v1/categorias', ['nome' => 'Pagamentos'])->assertCreated()->json('data.id');

        $criar = fn (string $nome, string $email, int $setor, string $perfil) => $this->postJson('/api/v1/usuarios', [
            'name' => $nome, 'email' => $email, 'password' => 'SenhaUsuario2026', 'setor_id' => $setor, 'perfil' => $perfil,
        ])->assertCreated()->json('data.id');
        $gestor = $criar('Gestor Fin', 'gestor@empresa.com.br', $financeiro, 'gestor');
        $analista = $criar('Analista Fin', 'analista@empresa.com.br', $financeiro, 'colaborador');
        $comprador = $criar('Comprador', 'comprador@empresa.com.br', $compras, 'colaborador');
        $this->postJson('/api/v1/auth/logout')->assertNoContent();

        $comoUsuario = fn (int $id) => $this->actingAs(User::query()->findOrFail($id));

        // Comprador abre demanda para o Financeiro, sem responsável.
        $demanda = $comoUsuario($comprador)->post('/api/v1/demandas', [
            'titulo' => 'Pagamento de fornecedor', 'descricao' => 'Nota fiscal anexa.', 'categoria_id' => $categoria,
            'setor_destino_id' => $financeiro, 'prioridade' => 'alta',
            'anexos' => [UploadedFile::fake()->createWithContent('nf.txt', 'NF 123')],
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $url = '/api/v1/demandas/'.$demanda['id'];

        // Gestor recebe notificação e distribui.
        $comoUsuario($gestor)->getJson('/api/v1/notificacoes/contador')->assertJsonPath('data.nao_lidas', 1);
        $this->patchJson($url.'/atribuicao', ['responsavel_id' => $analista])->assertOk();

        // Analista atende, comenta com anexo e resolve.
        $comoUsuario($analista)->patchJson($url.'/status', ['status' => 'em_andamento'])->assertOk();
        $this->post($url.'/comentarios', ['mensagem' => 'Comprovante em anexo.', 'anexos' => [UploadedFile::fake()->createWithContent('comprovante.txt', 'pago')]], ['Accept' => 'application/json'])->assertCreated();
        $this->patchJson($url.'/status', ['status' => 'resolvida', 'justificativa' => 'Pagamento efetuado.'])->assertOk();

        // Solicitante consulta, baixa o anexo do comentário e confirma.
        $comoUsuario($comprador)->getJson('/api/v1/dashboard')->assertJsonPath('data.aguardando_minha_confirmacao', 1);
        $comentarios = $this->getJson($url.'/comentarios')->assertOk();
        $anexo = $comentarios->json('data.0.anexos.0.id');
        $this->get($url."/anexos/{$anexo}/download")->assertOk();
        $this->patchJson($url.'/status', ['status' => 'concluida'])->assertOk()->assertJsonPath('data.status', 'concluida');

        $this->getJson($url.'/historico')->assertOk()->assertJsonCount(5, 'data');
        $this->assertContains('concluida', collect($comoUsuario($analista)->getJson('/api/v1/notificacoes')->assertOk()->json('data'))->pluck('tipo')->all());
    }
}
