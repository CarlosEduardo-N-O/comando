<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\AguardaBanco;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * comando:health — usado nos healthchecks dos containers (código 0 = saudável).
 * comando:health --aguardar=60 — aguarda o banco antes de iniciar worker/scheduler.
 */
class Health extends Command
{
    use AguardaBanco;

    protected $signature = 'comando:health {--aguardar=0 : Segundos para aguardar o banco ficar disponível}';

    protected $description = 'Verifica a conexão com o banco de dados';

    public function handle(): int
    {
        $aguardar = (int) $this->option('aguardar');
        if ($aguardar > 0) {
            return $this->aguardarBanco($aguardar) ? self::SUCCESS : self::FAILURE;
        }

        try {
            DB::select('SELECT 1');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Banco indisponível.');

            return self::FAILURE;
        }
    }
}
