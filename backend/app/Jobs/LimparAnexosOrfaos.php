<?php

namespace App\Jobs;

use App\Models\Anexo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Rede de segurança para falhas de upload: remove arquivos do diretório de
 * anexos que não possuem registro no banco e têm mais de 24 horas (por
 * exemplo, se o processo foi interrompido entre a gravação do arquivo e o
 * rollback). Executado pelo worker; agendado pelo scheduler.
 */
class LimparAnexosOrfaos implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        $disco = Storage::disk(config('comando.anexos.disco'));
        $limite = now()->subDay()->getTimestamp();
        $removidos = 0;

        foreach ($disco->allFiles(config('comando.anexos.diretorio')) as $caminho) {
            if ($disco->lastModified($caminho) > $limite) {
                continue;
            }
            if (! Anexo::query()->where('caminho', $caminho)->exists()) {
                $disco->delete($caminho);
                $removidos++;
            }
        }

        if ($removidos > 0) {
            Log::info('Anexos órfãos removidos.', ['quantidade' => $removidos]);
        }
    }
}
