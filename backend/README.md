# Comando — backend (Laravel)

API REST do Comando (`/api/v1`). Instalação, operação e testes estão no
[README principal](../README.md); decisões técnicas em [docs/decisoes.md](../docs/decisoes.md).

Mapa rápido do código:

| Pasta | Conteúdo |
|---|---|
| `app/Services/FluxoAtendimento.php` | Máquina de estados (transições, quem pode, evento de histórico) |
| `app/Services/DemandaService.php` | Abertura, atribuição/assunção atômicas, transferência, prioridade, prazo, status, comentários |
| `app/Services/ConsultaDemandas.php` | Listagens por contexto, filtros, ordenação |
| `app/Policies/DemandaPolicy.php` | Participação: ver, gerenciar, conduzir, assumir, transferir, cancelar, avaliar solução |
| `app/Services/NotificacaoService.php` | Notificações persistidas sem duplicidade e sem notificar o autor |
| `app/Services/AnexoService.php` | Armazenamento privado com limpeza em caso de falha |
| `app/Http/Requests` / `app/Http/Resources` | Validação de entrada / formato de saída |
| `app/Console/Commands` | `comando:install`, `comando:demo`, `comando:health` |
| `tests/` | Unitários e de API (PostgreSQL isolado via `compose.test.yaml`) |
