# Order Hub — Módulo de Gestão de Pedidos

Sistema full stack para gerenciamento de pedidos de e-commerce com múltiplos afiliados,
construído sobre dados da [FakeStore API](https://fakestoreapi.com).

## Estrutura do repositório

```
/
├── backend/          Laravel 11 — API REST
├── frontend/         Vue 3 + Tailwind CSS — SPA
├── n8n/workflows/    Workflows N8N exportados como JSON
├── docker/           Dockerfiles e config do Nginx
├── docker-compose.yml
└── queries.sql       Queries SQL avançadas (A–E)
```

## Pré-requisitos

- Docker Desktop (versão ≥ 24)
- Git

## Instalação e execução — 1 comando

```bash
git clone https://github.com/seu-usuario/teste-fullstack-seu-nome.git
cd teste-fullstack-seu-nome
docker compose up -d
```

O entrypoint do container backend faz automaticamente:
1. `composer install` se `vendor/` não existir
2. Copia `.env.example` → `.env` se `.env` não existir
3. Gera `APP_KEY` automaticamente
4. Aguarda o MySQL ficar disponível (health check)
5. Executa `php artisan migrate`

Após os containers subirem (~60s no primeiro boot), importe os dados:

```bash
docker compose exec backend php artisan orders:sync
```

Acesse:
- **Frontend**: http://localhost
- **API**: http://localhost/api/orders
- **N8N**: http://localhost:5678 (usuário: admin / senha: definida no .env)
- **Health check**: http://localhost/api/health

## Execução local (sem Docker)

```bash
# Backend
cd backend
composer install
cp .env.example .env
# Edite o .env com suas credenciais de MySQL e Redis locais
php artisan key:generate
php artisan migrate
php artisan orders:sync
php artisan queue:work &
php artisan serve

# Frontend (outro terminal)
cd frontend
npm install
npm run dev   # http://localhost:3000
```

## Como rodar os testes

Os testes usam SQLite em memória — não precisam de banco externo.

```bash
cd backend
php artisan test
# ou filtrar por suite:
php artisan test --filter=OrderStatusMachineTest
php artisan test --filter=MetricsEndpointTest
php artisan test --filter=OrdersSyncCommandTest
```

## Como importar e testar workflows do N8N

1. Acesse http://localhost:5678 e faça login
2. Vá em **Workflows → Import from file**
3. Importe cada arquivo em `n8n/workflows/`
4. Ative os workflows
5. Os webhooks estarão disponíveis em:
   - `POST http://localhost:5678/webhook/order-approved`
   - `POST http://localhost:5678/webhook/order-cancelled`

Para simular um evento:
```bash
curl -X POST http://localhost:5678/webhook/order-approved \
  -H "Content-Type: application/json" \
  -d '{
    "event": "order.status_changed",
    "order_id": 42,
    "affiliate_id": 7,
    "previous_status": "pending",
    "new_status": "approved",
    "total_value": 259.90,
    "occurred_at": "2024-03-15T14:32:00Z"
  }'
```

> **Nota:** Os nós de Slack/Gmail foram substituídos por `HTTP Request → webhook.site`
> para demonstrar o fluxo sem credenciais reais. Basta trocar a URL para integrar
> com o destino real.

## Endpoints da API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/orders` | Lista paginada com filtros e ordenação |
| GET | `/api/orders/{id}` | Detalhe com itens e histórico |
| GET | `/api/orders/metrics` | Métricas agregadas (cache Redis 5min) |
| POST | `/api/orders/{id}/status` | Atualiza status (máquina de estados) |
| GET | `/api/affiliates/{id}/summary` | Resumo do afiliado |
| GET | `/api/health` | Status dos serviços dependentes |

### Parâmetros de filtro em `GET /api/orders`

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `affiliate_id` | integer | Filtrar por afiliado |
| `status` | string | `pending`, `approved`, `cancelled`, `refunded` |
| `date_from` | date | Data inicial (YYYY-MM-DD) |
| `date_to` | date | Data final |
| `min_value` | decimal | Valor mínimo |
| `max_value` | decimal | Valor máximo |
| `sort_by` | string | `id`, `total_value`, `status`, `created_at` |
| `sort_dir` | string | `asc` ou `desc` |

### Máquina de estados

```
pending ──→ approved ──→ refunded
pending ──→ cancelled
```

Transições inválidas retornam `422 Unprocessable Entity`.

## Decisões técnicas e trade-offs

### Backend

**Repository Pattern**
Isolei as queries em `OrderRepository` e a orquestração em `OrderService`, deixando os
controllers apenas para parsing de request/response. Isso facilita testes unitários
do service com mocks do repositório.

**Upsert para idempotência**
O comando `orders:sync` pode ser rodado múltiplas vezes. O `upsert()` do Eloquent
garante que reexecuções não criem duplicatas — apenas atualizam campos que mudaram.
O status do pedido **nunca é sobrescrito** pelo sync, pois é gerenciado pela máquina
de estados.

**Rate limiting no sync**
Usei `RateLimiter` do Laravel para limitar a 5 requisições/segundo à FakeStore API,
evitando 429s. Em produção real, usaria um semáforo Redis distribuído para múltiplos
workers.

**Cache com invalidação por evento**
O cache de métricas usa `Cache::forget('orders:metrics')` no `OrderService::updateStatus`,
garantindo que dados stale não vazem após mudanças de estado. O TTL de 5 minutos
é o fallback.

**Jobs com backoff exponencial**
`SendWebhookNotification` usa `backoff() → [30, 60, 120]` (30s, 1min, 2min) com
3 tentativas. Falhas no N8N não propagam exceção para o fluxo principal do pedido —
o evento é disparado dentro de um `try` no listener, não na transação.

**Índices compostos**
Criei `(affiliate_id, status, created_at)` como índice composto principal, seguindo
o padrão de filtros mais usado pela API. Evitei usar `DATE()` em condições WHERE
(que cancela o uso de índice) — use `created_at >= '...'` ao invés de
`DATE(created_at) >= '...'`.

**SoftDeletes em orders**
Preserva histórico de pedidos deletados para auditoria e relatórios.

### Frontend

**URL como fonte de verdade**
Filtros e ordenação são sincronizados com `router.replace({ query })`, permitindo
copiar/colar a URL com os filtros aplicados. A inicialização lê `route.query` para
restaurar o estado.

**Debounce de 400ms**
Inputs de texto disparam `useDebounceFn` do VueUse antes de chamar a API,
evitando requisições a cada tecla.

**Estado centralizado com Pinia**
O `ordersStore` gerencia pedidos, métricas e seleção múltipla. A invalidação de cache
no backend é espelhada no frontend via refresh de métricas após cada mudança de status.

**Drawer com transitions**
O painel lateral usa `<transition>` com `transform: translateX(100%)` para slide,
e backdrop com `opacity` para fade, sem dependências de animação externas.

### DevOps

**Separação de container worker**
O worker de filas roda em container separado do web server, permitindo escalar
independentemente (ex: múltiplos workers para alta carga) sem afetar requests HTTP.

**Non-root containers**
O Dockerfile cria usuário `appuser` com uid 1000 para não rodar como root.

**N8N como camada de automação desacoplada**
A integração com N8N é disparada via evento `OrderStatusChanged → listener → job`,
garantindo que o fluxo de pedido não falhe se o N8N estiver indisponível.

## O que ficaria diferente com mais tempo

1. **Autenticação**: Implementar Sanctum ou JWT para proteger a API e registrar
   o `changed_by` real do usuário no status log.

2. **Paginação cursor-based**: Para tabelas com 500k+ registros, paginação por cursor
   (baseada em `id`) é mais eficiente que `OFFSET`.

3. **Testes E2E**: Playwright para cobrir o fluxo completo de filtros + drawer + mudança
   de status no navegador.

4. **Horizon**: Para monitoramento visual das filas em produção (substituindo
   `queue:work` manual).

5. **CI/CD**: Pipeline GitHub Actions com `php artisan test`, `npm run build` e
   deploy automático via Docker.

6. **Observabilidade**: Structured logging (JSON) + integração com Datadog/Sentry
   para monitorar jobs falhos e latência de endpoints.
