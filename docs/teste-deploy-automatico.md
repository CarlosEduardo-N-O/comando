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
