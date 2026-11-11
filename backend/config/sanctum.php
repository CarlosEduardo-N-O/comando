<?php

use Laravel\Sanctum\Sanctum;

/*
| Autenticação de SPA por sessão. Requisições vindas dos domínios abaixo
| (cabeçalho Origin/Referer) recebem sessão, cookie HttpOnly e proteção CSRF.
| O host de APP_URL é sempre incluído; SANCTUM_STATEFUL_DOMAINS acrescenta
| outros nomes/IPs pelos quais os usuários acessam a aplicação.
*/

$dominios = array_filter(array_map('trim', explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:8080,127.0.0.1,127.0.0.1:8080'))));
$atual = Sanctum::currentApplicationUrlWithPort();
if ($atual !== '') {
    $dominios[] = ltrim($atual, ',');
}

return [
    'stateful' => array_values(array_unique($dominios)),

    'guard' => ['web'],

    // Sem tokens pessoais nesta versão (apenas sessão).
    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
