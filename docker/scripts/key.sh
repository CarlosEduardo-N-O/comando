#!/bin/sh
# Gera a APP_KEY dentro de um container e grava no .env (Linux/macOS).
# Executar na raiz do projeto:  sh docker/scripts/key.sh
# A chave é gerada uma única vez: se já existir, o script não a altera.
set -eu

[ -f compose.yaml ] || { echo 'Execute na raiz do projeto (onde está o compose.yaml).' >&2; exit 1; }
[ -f .env ] || { cp .env.example .env; chmod 600 .env; echo '.env criado a partir do .env.example.'; }

if grep -Eq '^APP_KEY=.+' .env; then
  echo 'APP_KEY já definida no .env. Ela foi preservada (não a regenere: sessões e dados cifrados dependem dela).'
  exit 0
fi

echo 'Gerando a APP_KEY com a imagem do backend...'
docker compose -f compose.yaml build backend
app_key=$(docker compose -f compose.yaml run --rm --no-deps -T backend php artisan key:generate --show --no-ansi | tr -d '\r' | grep '^base64:' | tail -n 1)
[ -n "$app_key" ] || { echo 'Falha ao gerar a APP_KEY.' >&2; exit 1; }

tmp=$(mktemp .env.XXXXXX)
if grep -q '^APP_KEY=' .env; then
  sed "s|^APP_KEY=.*|APP_KEY=${app_key}|" .env > "$tmp"
else
  cat .env > "$tmp"; printf '\nAPP_KEY=%s\n' "$app_key" >> "$tmp"
fi
chmod 600 "$tmp"
mv "$tmp" .env

echo 'APP_KEY gravada no .env. Guarde uma cópia segura do .env (sem ela os dados cifrados e as sessões são perdidos).'
