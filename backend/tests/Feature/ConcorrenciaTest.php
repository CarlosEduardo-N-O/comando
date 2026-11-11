<?php

namespace Tests\Feature;

use App\Exceptions\RegraNegocioException;
use App\Models\User;
use App\Services\DemandaService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Concorrência real: dois processos (pcntl_fork) com conexões próprias ao
 * PostgreSQL tentam assumir a mesma demanda ao mesmo tempo. Usa
 * DatabaseMigrations porque os dados precisam estar confirmados (commit)
 * para serem vistos pelos processos filhos.
 */
class ConcorrenciaTest extends TestCase
{
    use DatabaseMigrations;

    public function test_dois_processos_nao_assumem_a_mesma_demanda(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Extensão pcntl indisponível.');
        }

        $setor = $this->setor('Concorrência');
        $solicitante = $this->usuario($this->setor('Origem'));
        $candidatos = [$this->usuario($setor), $this->usuario($setor)];
        $demanda = $this->demanda($solicitante, $setor);

        $resultados = $this->emParalelo(array_map(
            fn (User $u) => fn () => app(DemandaService::class)->assumir($u, $demanda->id),
            $candidatos
        ));

        sort($resultados);
        $this->assertSame(['409', 'ok'], $resultados);
        $this->assertContains($demanda->fresh()->responsavel_id, [$candidatos[0]->id, $candidatos[1]->id]);
        $this->assertSame(1, $demanda->historicos()->where('acao', 'assuncao')->count());
    }

    public function test_protocolos_sem_duplicidade_em_aberturas_simultaneas(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Extensão pcntl indisponível.');
        }

        $destino = $this->setor('Destino');
        $solicitantes = array_map(fn () => $this->usuario($this->setor('Origem '.uniqid())), range(1, 4));

        $resultados = $this->emParalelo(array_map(fn (User $u) => fn () => $this->demanda($u, $destino), $solicitantes));

        $this->assertSame(['ok', 'ok', 'ok', 'ok'], $resultados);
        $this->assertSame(4, DB::table('demandas')->distinct()->count('protocolo'));
    }

    /**
     * @param  list<callable>  $tarefas
     * @return list<string> "ok", código HTTP ou mensagem de erro de cada processo
     */
    private function emParalelo(array $tarefas): array
    {
        DB::disconnect();
        $inicio = microtime(true) + 0.5;
        $arquivos = [];
        $pids = [];

        foreach ($tarefas as $tarefa) {
            $arquivo = tempnam(sys_get_temp_dir(), 'concorrencia-');
            $arquivos[] = $arquivo;
            $pid = pcntl_fork();

            if ($pid === 0) {
                DB::purge();
                while (microtime(true) < $inicio) {
                    usleep(500);
                }
                try {
                    $tarefa();
                    $saida = 'ok';
                } catch (RegraNegocioException $e) {
                    $saida = (string) $e->render()->getStatusCode();
                } catch (\Throwable $e) {
                    $saida = get_class($e).': '.$e->getMessage();
                }
                file_put_contents($arquivo, $saida);
                // Encerra o filho sem executar destrutores/shutdown do PHPUnit.
                function_exists('posix_kill') ? posix_kill(getmypid(), SIGKILL) : exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $estado);
        }
        DB::purge();

        $resultados = array_map(fn ($a) => (string) file_get_contents($a), $arquivos);
        array_map('unlink', $arquivos);

        return $resultados;
    }
}
