# Validacao do deploy automatico - AWS Academy

Objetivo: comprovar o funcionamento do pipeline CI/CD.

Fluxo:
1. Push no GitHub.
2. Execucao do GitHub Actions.
3. Analise SAST pelo SonarCloud.
4. Aprovacao do CI.
5. Identificacao da nova versao pelo timer da EC2.
6. Deploy automatico.
7. Validacao dos containers e endpoint /health.

Teste realizado em: 22/11/2026.
Status: aguardando execucao.

---

## Segundo teste de CI/CD - AWS Academy

Data: 22/11/2026

Objetivo: validar uma segunda execucao do pipeline
CI/CD com novo commit na branch main.

Verificacoes:
- Testes automatizados Laravel.
- Build do React.
- Analise SAST pelo SonarCloud.
- Execucao do workflow CD.
- Identificacao do novo SHA pelo deploy automatico.
- Verificacao da aplicacao na EC2.

Status: aguardando validacao.
