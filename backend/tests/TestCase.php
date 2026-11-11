<?php

namespace Tests;

use App\Models\Categoria;
use App\Models\Demanda;
use App\Models\Setor;
use App\Models\User;
use App\Services\DemandaService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Proteção: os testes recriam o schema e só podem rodar no banco isolado de testes.
        $conexao = config('database.connections.pgsql');
        if (config('database.default') !== 'pgsql' || $conexao['host'] !== 'postgres-test' || $conexao['database'] !== 'comando_test') {
            throw new RuntimeException('Os testes só executam no PostgreSQL isolado postgres-test/comando_test (compose.test.yaml).');
        }

        return $app;
    }

    protected function setor(string $nome, bool $ativo = true): Setor
    {
        return Setor::query()->create(['nome' => $nome, 'ativo' => $ativo]);
    }

    protected function categoria(string $nome = 'Geral'): Categoria
    {
        return Categoria::query()->firstOrCreate(['nome' => $nome]);
    }

    protected function usuario(Setor $setor, string $perfil = User::PERFIL_COLABORADOR, array $extra = []): User
    {
        return User::factory()->noSetor($setor)->perfil($perfil)->create($extra);
    }

    /** Cria uma demanda pelo serviço (como o solicitante faria pela API). */
    protected function demanda(User $solicitante, Setor $destino, ?User $responsavel = null, array $extra = []): Demanda
    {
        return app(DemandaService::class)->criar($solicitante, array_merge([
            'titulo' => 'Configurar acesso ao ERP',
            'descricao' => 'Conteúdo reservado da demanda.',
            'categoria_id' => $this->categoria()->id,
            'setor_destino_id' => $destino->id,
            'responsavel_id' => $responsavel?->id,
            'prioridade' => 'normal',
        ], $extra));
    }
}
