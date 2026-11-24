# syntax=docker/dockerfile:1
# Contexto de build: raiz do repositório (precisa de docker/nginx e docs/openapi.json).

ARG NODE_IMAGE=node:22.14.0-alpine
ARG NGINX_IMAGE=nginxinc/nginx-unprivileged:1.28.0-alpine3.21

# ---------------------------------------------------------------------------
# Desenvolvimento: servidor Vite (usado pelo compose.dev.yaml).
FROM ${NODE_IMAGE} AS development
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]

# ---------------------------------------------------------------------------
# Build de produção do frontend + documentação Swagger.
FROM development AS build
RUN npm run build \
 && cp node_modules/swagger-ui-dist/swagger-ui.css node_modules/swagger-ui-dist/swagger-ui-bundle.js dist/docs/
COPY docs/openapi.json /app/dist/docs/openapi.json

# ---------------------------------------------------------------------------
# Nginx sem privilégios (porta 8080, usuário nginx) servindo o build.
FROM ${NGINX_IMAGE} AS production
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/readiness-local.conf /etc/nginx/comando-readiness/placeholder.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 8080
