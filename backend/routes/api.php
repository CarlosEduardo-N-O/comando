<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemandaController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InteracaoController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\OpcoesController;
use App\Http\Controllers\SetorController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| Rotas com prefixo /api/v1 (definido em bootstrap/app.php).
| A autenticação é por sessão (Sanctum SPA): o frontend chama primeiro
| GET /sanctum/csrf-cookie e envia o cabeçalho X-XSRF-TOKEN nas mutações.
*/

Route::get('/', fn () => response()->json(['nome' => 'Comando', 'versao' => 'v1', 'documentacao' => '/docs/']))->name('api.raiz');
Route::get('health', HealthController::class)->name('health');

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

Route::middleware(['auth:sanctum', 'ativo', 'throttle:api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::patch('auth/me', [AuthController::class, 'atualizar'])->name('auth.atualizar');
    Route::put('auth/password', [AuthController::class, 'senha'])->name('auth.senha');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Consultas limitadas para formulários (qualquer usuário autenticado).
    Route::prefix('opcoes')->name('opcoes.')->group(function () {
        Route::get('setores', [OpcoesController::class, 'setores'])->name('setores');
        Route::get('categorias', [OpcoesController::class, 'categorias'])->name('categorias');
        Route::get('usuarios', [OpcoesController::class, 'usuarios'])->name('usuarios');
        Route::get('configuracao', [OpcoesController::class, 'configuracao'])->name('configuracao');
    });

    // Administração (Gate "administrar" verificado em cada ação).
    Route::apiResource('usuarios', UsuarioController::class)->except('destroy')
        ->parameters(['usuarios' => 'usuario'])->whereNumber('usuario');
    Route::apiResource('setores', SetorController::class)->except('destroy')
        ->parameters(['setores' => 'setor'])->whereNumber('setor');
    Route::apiResource('categorias', CategoriaController::class)->except('destroy')
        ->parameters(['categorias' => 'categoria'])->whereNumber('categoria');

    Route::prefix('demandas')->name('demandas.')->group(function () {
        Route::get('/', [DemandaController::class, 'index'])->name('index');
        Route::post('/', [DemandaController::class, 'store'])->name('store');

        Route::whereNumber(['demanda', 'anexo'])->group(function () {
            Route::get('{demanda}', [DemandaController::class, 'show'])->name('show');
            Route::patch('{demanda}/atribuicao', [DemandaController::class, 'atribuicao'])->name('atribuicao');
            Route::patch('{demanda}/destino', [DemandaController::class, 'destino'])->name('destino');
            Route::patch('{demanda}/prioridade', [DemandaController::class, 'prioridade'])->name('prioridade');
            Route::patch('{demanda}/prazo', [DemandaController::class, 'prazo'])->name('prazo');
            Route::patch('{demanda}/status', [DemandaController::class, 'status'])->name('status');

            Route::get('{demanda}/comentarios', [InteracaoController::class, 'comentarios'])->name('comentarios');
            Route::post('{demanda}/comentarios', [InteracaoController::class, 'comentar'])->name('comentar');
            Route::get('{demanda}/historico', [InteracaoController::class, 'historico'])->name('historico');
            Route::get('{demanda}/anexos/{anexo}/download', [InteracaoController::class, 'download'])->name('anexos.download');
        });
    });

    Route::prefix('notificacoes')->name('notificacoes.')->group(function () {
        Route::get('/', [NotificacaoController::class, 'index'])->name('index');
        Route::get('contador', [NotificacaoController::class, 'contador'])->name('contador');
        Route::patch('ler-todas', [NotificacaoController::class, 'lerTodas'])->name('ler-todas');
        Route::patch('{id}/ler', [NotificacaoController::class, 'ler'])->whereUuid('id')->name('ler');
    });
});
