# Decisões técnicas

Registro das escolhas relevantes feitas para concluir o escopo da primeira versão.

## Arquitetura

- **Frontend e API no mesmo endereço.** O Nginx (`web`) entrega o build do React e encaminha `/api`, `/sanctum` e `/health` ao PHP-FPM (`backend`) pela rede interna. Assim a autenticação usa somente cookie de sessão HttpOnly + CSRF (Sanctum SPA), sem tokens no navegador e sem CORS em produção.
- **Uma imagem para backend, worker e scheduler**, com a mesma configuração (`.env`). Worker e scheduler aguardam o banco (`comando:health --aguardar=120`) e dependem da política `restart: unless-stopped` para se recuperar de quedas.
- **Redes:** `internal` (marcada como `internal: true`, sem saída) liga Nginx, backend e PostgreSQL local; `egress` dá saída ao backend/worker/scheduler (para o RDS); `edge` publica apenas a porta do Nginx. O banco não publica porta (exceto no `compose.dev.yaml`, em `127.0.0.1`).
- **Volumes:** `postgres` (dados), `anexos` (arquivos das demandas) e `storage` (arquivos do Laravel). Separar `anexos` simplifica o backup e a futura migração para S3 (`ANEXOS_DISK`).
- **Processos sem root:** PHP-FPM/worker/scheduler rodam como `www-data`; o Nginx usa a imagem `nginx-unprivileged` (porta 8080).

## Regras de negócio

- **Máquina de estados centralizada** em `FluxoAtendimento` (tabela de transições + quem pode executar + evento de histórico). É testada isoladamente (matriz completa) e pela API.
- **Concorrência:** toda alteração de demanda roda em transação com `SELECT ... FOR UPDATE` na linha da demanda. Ao assumir, a segunda requisição encontra o responsável já definido e recebe **409**. Responsável, setor e categoria são lidos com bloqueio compartilhado para impedir inativação/mudança de setor simultâneas. O teste `ConcorrenciaTest` usa dois processos reais (`pcntl_fork`) contra o PostgreSQL.
- **Protocolo** gerado por uma sequência dedicada (`demanda_protocolo_seq`): `nextval` é atômico, então aberturas simultâneas nunca repetem número. Formato `CMD-000001` (cresce além de 6 dígitos se necessário).
- **Campos controlados pelo servidor** (solicitante, setor de origem, status, protocolo, perfil, setor e situação do usuário) nunca são preenchidos em massa: os modelos de demanda usam `$guarded = ['*']` e os serviços atribuem cada campo explicitamente.
- **Distribuição sem responsável:** permitida apenas em demandas "Aberta" (uma demanda em andamento sempre tem responsável). Para redirecionar uma demanda em andamento, usa-se a transferência.
- **Transferência** exige setor diferente do atual (para trocar só o responsável usa-se a atribuição), volta a demanda para "Aberta" e limpa o prazo de atendimento.
- **Mensagens de fluxo** (informação solicitada, solução, motivo de rejeição/cancelamento) ficam na justificativa do histórico; a API expõe a última informação solicitada e a última solução nos detalhes.
- **Comentário em demanda encerrada** retorna 422 (regra de negócio), não 403, pois o participante continua tendo acesso de leitura.
- **Último administrador:** alterações de perfil/situação de usuários são serializadas por `pg_advisory_xact_lock`, evitando que duas requisições simultâneas removam os dois últimos administradores.
- **Revogação de sessões:** sessões ficam na tabela `sessions` (driver `database`); inativação e troca de senha apagam as sessões do usuário; o middleware `ativo` também encerra qualquer sessão de usuário inativo.

## Histórico e notificações

- **Histórico imutável:** sem rotas de alteração; o modelo lança exceção em `update`/`delete`. Cada evento grava autor, ação, justificativa e apenas os campos alterados (antes/depois), incluindo nomes de responsável e setor para leitura futura.
- **Notificações** usam a tabela padrão `notifications` do Laravel com duas colunas extras: `evento` (ex.: `historico:15`) com índice único por usuário — impedindo duplicidade — e `demanda_id`. São gravadas na mesma transação da alteração. A prévia contém apenas protocolo e texto genérico. A listagem recalcula `demanda_disponivel` com a regra de visibilidade atual.
- Gestores são notificados quando a demanda chega **sem responsável** ao setor (abertura, transferência, remoção de responsável) e em comentários/cancelamentos de demandas sem responsável. Administradores não recebem notificações de todas as demandas (evita ruído); eles têm a visão "Todas as demandas".
- Sem WebSocket: o frontend consulta o contador a cada 60 s e ao voltar para a aba.

## Filas

- Driver `database`, com `after_commit = true`: jobs despachados dentro de transações só entram na fila após o commit.
- Job agendado `LimparAnexosOrfaos` (diário, 03:00 UTC): remove arquivos do diretório de anexos sem registro no banco com mais de 24 h — rede de segurança caso um processo seja interrompido entre gravar o arquivo e o rollback (a limpeza imediata já ocorre no tratamento de erro do upload).

## Anexos

- Validação no backend de quantidade (5), tamanho (10 MB), extensão declarada **e** tipo real pelo conteúdo (`mimes`, via `finfo`).
- Nome interno `anexos/AAAA/MM/<uuid>.<ext>`; nome original sanitizado só para exibição/download.
- Download sempre pela rota autenticada, com `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff` e CSP `sandbox`.
- Limites do PHP (12 MB por arquivo, 64 MB por requisição, 20 arquivos) ficam acima dos limites da aplicação para que o usuário receba mensagens de validação claras.

## Datas e fuso

- Colunas `timestamp with time zone`, aplicação em UTC; a API devolve ISO 8601 em UTC.
- O frontend formata no fuso de `DISPLAY_TIMEZONE` (padrão `America/Sao_Paulo`), obtido em `/api/v1/opcoes/configuracao`. Filtros de período (`criado_de`/`criado_ate`) são interpretados nesse fuso no servidor.
- O prazo desejado é uma data (sem hora); o prazo de atendimento é data e hora.

## Frontend

- React + React Router + Axios, sem biblioteca de componentes: CSS próprio, fontes do sistema (nenhuma dependência externa em tempo de execução, compatível com a CSP `default-src 'self'`).
- Filtros, página e ordenação ficam na URL (preservados ao abrir uma demanda e voltar).
- Ações exibidas a partir da lista `acoes` calculada pelo backend (`DemandaPolicy::acoes`); o servidor revalida tudo.
- Botões ficam desabilitados durante envios (sem duplicidade); cancelamento e transferência exigem confirmação explícita.

## Testes

- PHPUnit em PostgreSQL isolado (`compose.test.yaml`, projeto `comando-test`, dados em `tmpfs`). `tests/TestCase.php` recusa executar se o banco não for `postgres-test/comando_test`.
- Os testes são executados com `vendor/bin/phpunit` (o `php artisan test` tenta ler um `.env` inexistente na imagem e gera avisos).
