<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use Throwable;

trait AguardaBanco
{
    /** Tenta conectar por até $segundos, tolerando indisponibilidade temporária do banco. */
    protected function aguardarBanco(int $segundos): bool
    {
        $limite = time() + $segundos;

        while (true) {
            try {
                DB::select('SELECT 1');

                return true;
            } catch (Throwable) {
                if (time() >= $limite) {
                    return false;
                }
                $this->line('<comment>Banco indisponível; nova tentativa em 2 segundos...</comment>');
                sleep(2);
                DB::purge(); // força nova conexão na próxima tentativa
            }
        }
    }
}
