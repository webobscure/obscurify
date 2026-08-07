# Obscurify

Multi-tenant SaaS e-commerce platform foundation for the Russian market. See
[`ARCHITECTURE.md`](ARCHITECTURE.md) and [`docs/adr/`](docs/adr/) for the
architectural decisions this codebase follows.

This is the **Foundation milestone** only: auth, stores, membership,
tenant isolation, and a minimal product catalog. No cart, checkout, orders,
payments, or themes yet.

## Requirements

- PHP 8.4+
- Composer 2
- Node.js 22+
- pnpm 11+ (`corepack enable` or `npx pnpm`)
- Docker / Docker Compose
- PostgreSQL and Redis (via Docker, or your own local instances)

## Quickstart: run locally and verify it works

Zero to a working app, end to end.

```bash
# 1. env files
cp .env.example .env
cp apps/api/.env.example apps/api/.env

# 2. infra: postgres, redis, minio, mailpit (api/horizon/nginx stay off for this path —
#    faster to run Laravel/Nuxt directly on the host while developing)
docker compose up -d postgres redis minio mailpit

# 3. backend
cd apps/api
composer install
php artisan key:generate
php artisan migrate
php artisan serve            # http://localhost:8000

# 4. admin, in a second terminal
cd apps/admin
pnpm install                 # or `pnpm install` once at repo root for all workspaces
pnpm dev                     # http://localhost:3000
```

**Verify the backend** (third terminal):

```bash
curl http://localhost:8000/api/v1/health
# {"status":"ok"}

curl -X POST http://localhost:8000/api/v1/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Test","email":"test@example.com","password":"password123","password_confirmation":"password123"}'
# {"data":{"id":"...","name":"Test",...},"token":"1|..."}
```

If both return data instead of an error, the API, Postgres, and the auth
flow all work.

**Verify the admin app**: open `http://localhost:3000` in a browser →
you're redirected to `/login` → follow "Need an account? Register" → fill
the form → you land on `/stores` → create a store → click "Activate" →
go to Products → create a product → it appears in the list. That's the
full golden path (register → create store → activate → create product)
exercising tenant isolation end to end.

To also check the Docker-only path (nginx → php-fpm → Postgres, no host
PHP involved):

```bash
docker compose up -d --build   # adds api, horizon, nginx to the services above
curl http://localhost:8080/api/v1/health
```

## Installation

```bash
make install
```

This runs `composer install` in `apps/api` and `pnpm install` at the repo
root for `apps/admin`, `apps/storefront`, and the shared `packages/*`.

## Environment

Copy the example env files:

```bash
cp .env.example .env               # docker-compose (ports, credentials)
cp apps/api/.env.example apps/api/.env
cd apps/api && php artisan key:generate
```

By default the API expects Postgres on `localhost:5433` and Redis on
`localhost:6380` — non-standard ports, chosen so the stack doesn't collide
with other local Postgres/Redis instances. Both are set in `docker-compose.yml`.

## Docker

```bash
make up      # postgres, redis, minio, mailpit, api, horizon, nginx
make down
```

- API via nginx: `http://localhost:8080`
- MinIO console: `http://localhost:9001`
- Mailpit UI: `http://localhost:8025`

Nuxt apps are **not** containerized — run them on the host with `pnpm dev`
for fast HMR. Only the backend and its infrastructure run in Docker.

## Backend development

```bash
cd apps/api
composer install
php artisan migrate
php artisan serve          # http://localhost:8000
php artisan horizon        # queue dashboard at /horizon
```

Quality gates:

```bash
composer validate
vendor/bin/pint            # add --test for CI-style check-only
vendor/bin/phpstan analyse
php artisan test           # or vendor/bin/pest
```

## Admin development

```bash
pnpm --filter admin dev    # http://localhost:3000
```

Minimal flow: register/login → create a store → activate it → create a
product. The admin is a client-rendered (SPA) app — it talks to the API via
`@obscurify/api-client`, storing the auth token and active store id in
`localStorage`. `X-Store-Id` is sent on every tenant-scoped request; the
backend re-validates membership on every request regardless.

## Storefront development

```bash
pnpm --filter storefront dev   # http://localhost:3001
```

Currently a boot-only shell (SSR on, no catalog/cart/checkout). Reserved
for future hostname-based Store resolution.

## Testing

```bash
make test                  # Pest (backend) + workspace `test` scripts (frontend)
```

Backend tenant isolation tests live in `apps/api/tests/Feature/`:
`Stores/StoreMembershipTest.php`, `Catalog/ProductIsolationTest.php`,
`Catalog/ProductVariantIsolationTest.php`, `Tenancy/TenantContextTest.php`.

## Horizon

Redis-backed queues, monitored via Horizon:

```bash
cd apps/api && php artisan horizon
```

Dashboard at `/horizon` (served through nginx at `http://localhost:8080/horizon`
when running via Docker, or directly if running `php artisan serve`).

## Make commands

```bash
make up          # start Docker services
make down        # stop them
make install     # composer + pnpm install
make migrate     # run migrations
make fresh       # drop and re-migrate (destructive, local only)
make test        # Pest + frontend test scripts
make horizon     # run Horizon locally (outside Docker)
make admin       # pnpm --filter admin dev
make storefront  # pnpm --filter storefront dev
```
