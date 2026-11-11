<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlterarSenhaRequest;
use App\Http\Requests\AtualizarPerfilRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação de SPA com Sanctum (sessão + cookie HttpOnly + CSRF).
 * Nenhum token é devolvido ao frontend.
 */
class AuthController extends Controller
{
    private const MAX_TENTATIVAS = 5;

    public function login(LoginRequest $request): UsuarioResource
    {
        if (! $request->hasSession()) {
            // O endereço usado no navegador não está em APP_URL nem em SANCTUM_STATEFUL_DOMAINS.
            abort(500, 'Endereço de acesso não configurado para sessões. Ajuste APP_URL ou SANCTUM_STATEFUL_DOMAINS no .env.');
        }

        $email = mb_strtolower(trim($request->validated('email')));
        $chave = 'login:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($chave, self::MAX_TENTATIVAS)) {
            $segundos = RateLimiter::availableIn($chave);
            throw ValidationException::withMessages([
                'email' => "Muitas tentativas de acesso. Tente novamente em {$segundos} segundos.",
            ])->status(429);
        }

        // Usuários inativos não autenticam (mesma mensagem para não revelar a situação da conta).
        $credenciais = ['email' => $email, 'password' => $request->validated('password'), 'ativo' => true];
        if (! Auth::guard('web')->attempt($credenciais)) {
            RateLimiter::hit($chave, 60);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();

        return new UsuarioResource($request->user()->load('setor'));
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user()->load('setor'));
    }

    /** O usuário altera apenas o próprio nome; perfil, setor e situação são ignorados. */
    public function atualizar(AtualizarPerfilRequest $request): UsuarioResource
    {
        $usuario = $request->user();
        $usuario->forceFill(['name' => trim($request->validated('name'))])->save();

        return new UsuarioResource($usuario->load('setor'));
    }

    /** Troca de senha: revoga as demais sessões e renova a atual. */
    public function senha(AlterarSenhaRequest $request, UsuarioService $usuarios): JsonResponse
    {
        $usuario = $request->user();
        $usuario->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $usuarios->revogarSessoes($usuario->id, $request->session()->getId());
        $request->session()->regenerate(true);
        // Mantém o hash de senha da sessão coerente para o middleware AuthenticateSession.
        Auth::guard('web')->setUser($usuario);

        return response()->json(['message' => 'Senha alterada. As demais sessões foram encerradas.']);
    }
}
