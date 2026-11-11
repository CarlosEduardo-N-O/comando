<?php

use App\Jobs\LimparAnexosOrfaos;
use Illuminate\Support\Facades\Schedule;

/*
| Tarefas executadas pelo serviço "scheduler" (php artisan schedule:work).
| Jobs são enviados à fila "database" e processados pelo serviço "worker".
*/

Schedule::job(new LimparAnexosOrfaos)->dailyAt('03:00')->name('limpar-anexos-orfaos')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('auth:clear-resets')->weekly();
