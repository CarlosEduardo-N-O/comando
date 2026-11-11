<?php

use App\Http\Middleware\Ativo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA: sessão + cookies + CSRF para requisições do próprio domínio.
        $middleware->statefulApi();
        $middleware->alias(['ativo' => Ativo::class]);
        // O backend só é alcançado pelo Nginx da rede interna dos containers.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*', 'sanctum/*') || $request->expectsJson());

        // Nunca registrar credenciais em logs.
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation']);
        // Regras de negócio violadas são respostas esperadas (422/409), não erros a registrar.
        $exceptions->dontReport([\App\Exceptions\RegraNegocioException::class]);

        $json = fn (string $mensagem, int $status) => response()->json(['message' => $mensagem], $status);

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->status === 429 ? collect($e->errors())->flatten()->first() : 'Verifique os dados informados.',
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });
        $exceptions->render(fn (AuthenticationException $e) => $json('Sessão não autenticada. Entre novamente.', 401));
        $exceptions->render(function (AccessDeniedHttpException $e) use ($json) {
            $mensagem = $e->getPrevious() instanceof AuthorizationException ? $e->getPrevious()->getMessage() : $e->getMessage();
            $padrao = in_array($mensagem, ['', 'This action is unauthorized.'], true);

            return $json($padrao ? 'Você não tem permissão para realizar esta ação.' : $mensagem, 403);
        });
        $exceptions->render(function (NotFoundHttpException $e) use ($json) {
            $mensagem = match (true) {
                $e->getPrevious() instanceof ModelNotFoundException, $e->getMessage() === '' => 'Registro não encontrado.',
                str_starts_with($e->getMessage(), 'The route') => 'Endereço não encontrado.',
                default => $e->getMessage(),
            };

            return $json($mensagem, 404);
        });
        $exceptions->render(fn (TokenMismatchException $e) => $json('Sessão expirada. Atualize a página e tente novamente.', 419));
        $exceptions->render(function (HttpException $e) use ($json) {
            if ($e->getStatusCode() === 419) {
                return $json('Sessão expirada. Atualize a página e tente novamente.', 419);
            }
        });
        $exceptions->render(fn (ThrottleRequestsException $e) => $json('Muitas requisições. Aguarde alguns instantes.', 429));
    })->create();
