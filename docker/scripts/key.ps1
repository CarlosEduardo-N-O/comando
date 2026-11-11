# Gera a APP_KEY dentro de um container e grava no .env (Windows PowerShell).
# Executar na raiz do projeto:  powershell -ExecutionPolicy Bypass -File docker\scripts\key.ps1
# A chave é gerada uma única vez: se já existir, o script não a altera.
$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath 'compose.yaml')) { throw 'Execute na raiz do projeto (onde está o compose.yaml).' }
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
    Write-Host '.env criado a partir do .env.example.'
}

$envPath = (Resolve-Path -LiteralPath '.env').Path
$conteudo = [IO.File]::ReadAllText($envPath)
if ($conteudo -match '(?m)^APP_KEY=\S+') {
    Write-Host 'APP_KEY já definida no .env. Ela foi preservada (não a regenere: sessões e dados cifrados dependem dela).'
    exit 0
}

Write-Host 'Gerando a APP_KEY com a imagem do backend...'
docker compose -f compose.yaml build backend
if ($LASTEXITCODE -ne 0) { throw 'Falha no build da imagem do backend.' }

$saida = docker compose -f compose.yaml run --rm --no-deps -T backend php artisan key:generate --show --no-ansi
if ($LASTEXITCODE -ne 0) { throw 'Falha ao gerar a APP_KEY.' }
$chave = ($saida | ForEach-Object { $_.Trim() } | Where-Object { $_ -like 'base64:*' } | Select-Object -Last 1)
if (-not $chave) { throw 'Falha ao gerar a APP_KEY.' }

if ($conteudo -match '(?m)^APP_KEY=.*$') {
    $conteudo = [regex]::Replace($conteudo, '(?m)^APP_KEY=.*$', "APP_KEY=$chave")
} else {
    $conteudo = $conteudo.TrimEnd() + "`nAPP_KEY=$chave`n"
}
# UTF-8 sem BOM e quebras de linha LF (lidas corretamente pelo Docker Compose).
[IO.File]::WriteAllText($envPath, $conteudo.Replace("`r`n", "`n"), [Text.UTF8Encoding]::new($false))

Write-Host 'APP_KEY gravada no .env. Guarde uma cópia segura do .env (sem ela os dados cifrados e as sessões são perdidos).'
