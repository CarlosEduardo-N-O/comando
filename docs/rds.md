# Banco externo no Amazon RDS for PostgreSQL

O RDS hospeda **somente o banco**. A aplicação continua em containers no servidor
e os anexos continuam no volume `anexos` (futura integração com S3 via `ANEXOS_DISK`).
Nenhum recurso AWS é criado por este projeto.

## 1. Requisitos do RDS

- PostgreSQL 15 ou superior (validado localmente com PostgreSQL 17.9; prefira 17.x).
- Não é necessário superusuário nem extensões. Basta um usuário dono do banco da aplicação.
- Exemplo (executado uma vez com o usuário mestre do RDS, por qualquer cliente `psql`):

```sql
CREATE ROLE comando LOGIN PASSWORD 'senha-forte';
CREATE DATABASE comando OWNER comando;
```

Sem instalar o `psql` localmente:

```bash
docker run --rm -it postgres:17.9-alpine3.22 psql "host=SEU-ENDPOINT.rds.amazonaws.com port=5432 dbname=postgres user=USUARIO_MESTRE sslmode=require"
```

## 2. Rede entre o servidor da aplicação e o RDS

- O servidor (ex.: EC2 ou servidor on-premises com VPN/Direct Connect) precisa alcançar o endpoint do RDS na porta **5432/TCP**.
- No *security group* do RDS, libere entrada 5432 **somente** a partir do security group (ou IP) do servidor da aplicação.
- Mantenha o RDS sem acesso público (`Publicly accessible = No`) quando o servidor estiver na mesma VPC.
- O servidor precisa resolver o DNS do endpoint (o hostname é usado na validação TLS).
- Saída do container: backend, worker e scheduler estão na rede `egress` do Compose, que permite conexões de saída.

Teste de conectividade a partir do servidor:

```bash
docker run --rm postgres:17.9-alpine3.22 pg_isready -h SEU-ENDPOINT.rds.amazonaws.com -p 5432
```

## 3. TLS com validação de certificado e hostname

Baixe o bundle de autoridades do RDS para `docker/certs/` (a pasta é montada somente leitura em `/etc/comando/certs`):

Linux:

```bash
curl -fsSL -o docker/certs/global-bundle.pem https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem
```

Windows PowerShell:

```powershell
Invoke-WebRequest -Uri https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem -OutFile docker\certs\global-bundle.pem
```

No `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=SEU-ENDPOINT.rds.amazonaws.com
DB_PORT=5432
DB_DATABASE=comando
DB_USERNAME=comando
DB_PASSWORD=senha-forte
DB_SSLMODE=verify-full
DB_SSLROOTCERT=/etc/comando/certs/global-bundle.pem
```

`verify-full` valida a cadeia do certificado **e** que o hostname conectado corresponde ao certificado.
Use sempre o endpoint DNS do RDS em `DB_HOST` (não o IP).

## 4. Iniciar e instalar sem o PostgreSQL local

Não inclua `compose.local-db.yaml`:

```bash
docker compose -f compose.yaml up -d --build
docker compose -f compose.yaml run --rm backend php artisan comando:install
```

`comando:install` verifica a conexão (com novas tentativas), executa as migrations pendentes e cria o primeiro administrador, sem apagar dados.

## 5. Migrar dados do PostgreSQL local para o RDS

1. Pare a aplicação para congelar os dados (o banco local continua no ar):

```bash
docker compose -f compose.yaml -f compose.local-db.yaml stop web backend worker scheduler
```

2. Gere o dump do banco local (formato custom):

```bash
docker compose -f compose.yaml -f compose.local-db.yaml exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc --no-owner --no-privileges -f /tmp/comando.dump'
docker compose -f compose.yaml -f compose.local-db.yaml cp postgres:/tmp/comando.dump ./backups/comando-para-rds.dump
```

3. Restaure no RDS com um container cliente (banco de destino vazio, criado no passo 1):

Linux:

```bash
docker run --rm -v "$PWD/backups:/backups:ro" -v "$PWD/docker/certs:/certs:ro" postgres:17.9-alpine3.22 \
  pg_restore --no-owner --no-privileges --single-transaction \
  -d "host=SEU-ENDPOINT.rds.amazonaws.com port=5432 dbname=comando user=comando sslmode=verify-full sslrootcert=/certs/global-bundle.pem" \
  /backups/comando-para-rds.dump
```

Windows PowerShell (uma linha):

```powershell
docker run --rm -it -v "${PWD}/backups:/backups:ro" -v "${PWD}/docker/certs:/certs:ro" postgres:17.9-alpine3.22 pg_restore --no-owner --no-privileges --single-transaction -d "host=SEU-ENDPOINT.rds.amazonaws.com port=5432 dbname=comando user=comando sslmode=verify-full sslrootcert=/certs/global-bundle.pem" /backups/comando-para-rds.dump
```

(Será solicitada a senha do usuário `comando`.)

4. Ajuste o `.env` (seção 3), remova o banco local dos comandos e suba a aplicação:

```bash
docker compose -f compose.yaml up -d --build
docker compose -f compose.yaml run --rm backend php artisan migrate:status
```

5. Confira a aplicação. O volume `postgres` local permanece intacto até você decidir removê-lo.

## 6. Backups com RDS

Use os snapshots automáticos/manuais do RDS. Para um dump lógico adicional:

```bash
docker run --rm -v "$PWD/backups:/backups" -v "$PWD/docker/certs:/certs:ro" postgres:17.9-alpine3.22 \
  pg_dump -Fc -f /backups/comando-rds.dump \
  "host=SEU-ENDPOINT.rds.amazonaws.com port=5432 dbname=comando user=comando sslmode=verify-full sslrootcert=/certs/global-bundle.pem"
```

Os anexos continuam no volume `anexos` do servidor: mantenha o backup de anexos descrito no README.
