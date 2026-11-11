<?php

namespace Tests\Unit;

use App\Models\Demanda;
use App\Services\FluxoAtendimento;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Tabela de transições centralizada (sem banco). */
class FluxoAtendimentoTest extends TestCase
{
    public static function transicoes(): array
    {
        $permitidas = [
            ['aberta', 'em_andamento'],
            ['em_andamento', 'aguardando_solicitante'],
            ['aguardando_solicitante', 'em_andamento'],
            ['em_andamento', 'resolvida'],
            ['resolvida', 'concluida'],
            ['resolvida', 'em_andamento'],
            ['aberta', 'cancelada'],
            ['em_andamento', 'cancelada'],
            ['aguardando_solicitante', 'cancelada'],
        ];

        $casos = [];
        foreach (Demanda::STATUS as $de) {
            foreach (Demanda::STATUS as $para) {
                $casos["{$de} → {$para}"] = [$de, $para, in_array([$de, $para], $permitidas, true)];
            }
        }

        return $casos;
    }

    #[DataProvider('transicoes')]
    public function test_matriz_de_transicoes(string $de, string $para, bool $esperado): void
    {
        $this->assertSame($esperado, FluxoAtendimento::permitida($de, $para));
    }

    public function test_status_encerrados_nao_tem_saida_nem_reabertura(): void
    {
        $this->assertSame([], FluxoAtendimento::TRANSICOES['concluida']);
        $this->assertSame([], FluxoAtendimento::TRANSICOES['cancelada']);
    }
}
