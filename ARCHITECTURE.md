# Architecture

## 1. Purpose

This repository contains the foundation of a multi-tenant SaaS e-commerce platform for the Russian market.

The long-term product direction is conceptually similar to Shopify:

- merchants create and manage stores;
- each store receives a hosted storefront;
- merchants manage products, inventory, customers and orders;
- stores can use custom domains;
- payments, delivery and external services are connected through adapters;
- third-party applications and webhooks can be added later;
- the platform itself has plans, subscriptions and feature limits.

The current goal is **not** to reproduce Shopify feature-for-feature.

The immediate goal is to build a technically sound foundation that can support an MVP and grow without a rewrite of the core domain model.

---

## 2. Architectural principles

### 2.1 Modular monolith first

The backend starts as one Laravel application.

We intentionally do not start with microservices.

Reasons:

- the product domain is still evolving;
- transactions between catalog, inventory, checkout and orders are important;
- deployment and local development remain simple;
- module boundaries can be established without network boundaries;
- modules can be extracted later only when operational pressure justifies it.

The application must still be organized into explicit domain modules and avoid a single global `Models/Services/Controllers` structure for all business logic.

### 2.2 Multi-tenant by design

The platform is multi-tenant.

The primary tenant is `Store`.

A user may belong to multiple stores.

Most business records belong to exactly one store.

For the initial architecture:

- one PostgreSQL cluster;
- shared schema;
- tenant-owned rows contain `store_id`;
- tenant context is derived by the backend;
- client-supplied `store_id` is never trusted as authorization;
- every tenant-sensitive query must be tenant-scoped;
- cross-tenant access must be covered by automated tests.

Future sharding must remain possible without changing domain semantics.

### 2.3 Explicit application boundaries

HTTP controllers must be thin.

Controllers are responsible for:

- parsing the request;
- authentication/authorization entry points;
- invoking application actions;
- mapping results to API resources.

Business behavior belongs in domain/application code.

External integrations must be behind contracts/adapters where this gives a meaningful boundary.

### 2.4 Transactions where consistency matters

Database transactions are required for operations that must be atomic.

Examples:

- membership changes;
- inventory reservation;
- checkout confirmation;
- order creation;
- payment state changes;
- refunds.

### 2.5 Asynchronous side effects

Non-critical side effects should not block the main HTTP request.

Examples:

- emails;
- webhooks;
- analytics events;
- image transformations;
- imports;
- marketplace synchronization.

Redis-backed Laravel queues are used initially.

### 2.6 API-first backend

Laravel is the source of truth for business rules.

Nuxt clients consume versioned APIs.

Initial API style: REST.

Initial version prefix:

`/api/v1`

Separate API contexts are expected in the future:

- Admin API;
- Storefront API;
- Platform API.

---

## 3. Technology stack

### Backend

- PHP 8.4+
- Laravel 13
- Laravel Sanctum
- Laravel Horizon
- Laravel Pennant
- Laravel Scout when search abstraction becomes necessary
- Pest
- Laravel Pint
- PHPStan / Larastan

### Frontend

- Nuxt 4
- Vue 3
- TypeScript
- Pinia
- pnpm
- Vitest
- Playwright
- Tailwind CSS
- Nuxt UI or an internal design system

### Data and infrastructure

- PostgreSQL
- Redis
- S3-compatible object storage
- MinIO in local development
- nginx
- Docker / Docker Compose
- GitHub Actions
- Sentry initially
- OpenTelemetry later if required

---

## 4. Repository layout

```text
/
├── apps/
│   ├── api/                 # Laravel
│   ├── admin/               # merchant backoffice, Nuxt
│   ├── storefront/          # public storefront, Nuxt
│   └── platform-admin/      # internal platform administration, Nuxt
│
├── packages/
│   ├── ui/
│   ├── storefront-ui/
│   ├── api-client/
│   └── types/
│
├── infra/
│   ├── docker/
│   └── nginx/
│
├── docs/
│   └── adr/
│
├── ARCHITECTURE.md
├── docker-compose.yml
├── pnpm-workspace.yaml
└── Makefile
```

For the first foundation milestone, `platform-admin` may remain an empty placeholder.

---

## 5. Backend module map

Long-term module map:

```text
Identity
Stores
Staff
Permissions

Catalog
Collections
Media

Inventory
Locations

Customers

Cart
Pricing
Discounts
Checkout

Orders
Payments
Refunds
Fulfillment
Shipping

Domains
Themes
Pages
Navigation

Notifications

Integrations
Webhooks
Apps

Billing
Plans
Subscriptions

Analytics
Audit
Platform Administration
```

Implemented through Milestone 7 (Fulfillment Core, see
`docs/architecture/fulfillment.md`):

```text
Identity
Stores
Staff / Membership (role column exists; not yet enforced — see technical-debt.md)
Tenant Context
Catalog (products, variants, options, categories, collections, media)
Inventory (locations, levels, reservations, movements)
Cart (storefront guest cart)
Checkout (storefront)
Orders
Payments (fake provider only — no real gateway integrated yet)
Shipping (fake provider only — no real carrier integrated yet; see docs/architecture/shipping.md)
Fulfillment (allocation, picking, packing, reservation consumption; independent of Shipping — see docs/architecture/fulfillment.md)
Domains
```

Everything else in the long-term map above remains documented but
unimplemented: Pricing, Discounts, Refunds, Themes, Pages, Navigation,
Notifications, Integrations, Webhooks, Apps, Billing, Plans,
Subscriptions, Analytics, Audit, Platform Administration, a real
(non-fake) payment provider, and a real (non-fake) shipping carrier (CDEK,
Russian Post, Boxberry). Customers exists as an internal write-side model
used by checkout (no admin-facing API or UI yet).

---

## 6. Laravel internal structure

Prefer vertical/domain modules over a global folder per technical layer.

Example:

```text
apps/api/app/
├── Domain/
│   ├── Identity/
│   ├── Stores/
│   └── Catalog/
│
├── Shared/
│   ├── Tenancy/
│   ├── Http/
│   └── Support/
│
└── Providers/
```

Example module:

```text
Domain/Catalog/
├── Application/
│   ├── CreateProduct.php
│   ├── UpdateProduct.php
│   └── DeleteProduct.php
├── Domain/
│   ├── Product.php
│   └── Events/
├── Infrastructure/
│   └── Persistence/
└── Http/
    ├── Controllers/
    ├── Requests/
    └── Resources/
```

Do not introduce repositories, aggregates, value objects or interfaces mechanically.

Create abstractions only when they protect a real boundary or business invariant.

---

## 7. Identity model

`User` represents a platform/merchant user.

A user may belong to multiple stores.

Do not place `store_id` directly on `users`.

Core tables:

```text
users

stores

store_users
```

Suggested `store_users` fields:

```text
id
store_id
user_id
role
status
created_at
updated_at
```

The initial foundation can use a simple role enum/string, while keeping the schema ready for a fuller permissions system.

---

## 8. Store model

Suggested fields:

```text
id
owner_id
name
slug
status
default_currency
default_locale
timezone
settings
created_at
updated_at
```

Preferred IDs: ULID.

`slug` is unique at platform level initially.

`settings` may use PostgreSQL JSONB for non-critical store configuration, but core searchable/business fields must remain explicit columns.

---

## 9. Tenant resolution

The current tenant is represented by an application-level `TenantContext`.

Tenant resolution depends on request type.

### Merchant Admin

```text
authenticated user
→ selected/current store
→ membership validation
→ TenantContext
```

### Storefront

Future flow:

```text
Host header
→ Domain record
→ Store
→ TenantContext
```

### API token/application

Future flow:

```text
API token
→ installation/store binding
→ TenantContext
```

The foundation milestone only needs merchant/admin tenant selection, but the abstraction must not assume this is the only resolution strategy.

---

## 10. Tenant isolation rules

These are architectural invariants.

1. `store_id` from request payload is never trusted to establish authorization.
2. Tenant context is established before tenant-owned application actions execute.
3. Tenant-owned records are always queried inside the active tenant scope.
4. Route model binding must not allow resolving records from another store.
5. Authorization must fail closed when no tenant is active.
6. Console jobs that operate on tenant data must explicitly restore tenant context.
7. Queue jobs must carry the tenant identifier when required.
8. Cache keys for tenant data must include tenant identity.
9. Cross-tenant access is covered by feature tests.
10. Platform-level administrative access must use a separate explicit authorization path rather than bypassing tenant filters silently.

---

## 11. Minimal catalog foundation

Foundation only requires:

```text
products
product_variants
```

Suggested `products` fields:

```text
id
store_id
title
slug
description
status
created_at
updated_at
deleted_at
```

Suggested `product_variants` fields:

```text
id
store_id
product_id
title
sku
price_amount
currency
status
created_at
updated_at
deleted_at
```

`price_amount` is an integer minor-unit amount.

Never store money as floating point.

The full flexible option/value model is intentionally deferred to the Catalog milestone.

---

## 12. Domains

Foundation model:

```text
domains

id
store_id
domain
type
is_primary
verified_at
created_at
updated_at
```

No DNS automation or SSL provisioning in Foundation.

The table exists so storefront tenant resolution has a stable future boundary.

---

## 13. Authentication

Merchant authentication uses Laravel Sanctum **personal access tokens**
(bearer, not cookie/session) — see ADR-011. The Nuxt admin sends
`Authorization: Bearer <token>` on every request; there is no stateful
cookie or CSRF flow for the admin.

Requirements:

- login throttling;
- password hashing through Laravel defaults;
- API authorization policies;
- no tenant selection solely from client input;
- the frontend must verify the token against the backend (`GET /api/v1/me`)
  at app boot rather than trusting `localStorage` presence, and must clear
  auth + active-store state together on any `401` from any endpoint.

The storefront's guest cart uses a separate `HttpOnly` cookie with
credentialed CORS (see `config/cors.php`) — that flow is unrelated to
merchant auth and must not be conflated with it.

Customer/storefront authentication is a separate future security context and must not reuse merchant assumptions.

---

## 14. Authorization

Foundation authorization layers:

```text
Authentication
→ Store membership
→ TenantContext
→ Policy / action authorization
```

`Owner` has full access to the store.

Initial roles may include:

- owner;
- administrator;
- manager.

Fine-grained permission tables may be introduced later.

The important requirement in Foundation is isolation, not a complete RBAC product.

---

## 15. PostgreSQL conventions

- ULID primary keys where practical;
- foreign keys;
- explicit indexes;
- unique constraints scoped by tenant where appropriate;
- `timestamptz` semantics through Laravel timestamps;
- JSONB only where justified;
- money as integer minor units;
- transactions for multi-write invariants.

Examples:

```text
UNIQUE(store_id, slug)
UNIQUE(store_id, sku)
INDEX(store_id)
INDEX(store_id, status)
```

---

## 16. Redis conventions

Foundation usage:

- queues;
- cache;
- rate limiting;
- sessions if configured;
- short-lived tenant/domain resolution cache later.

All tenant-specific cache keys must be namespaced.

Example:

```text
store:{storeId}:product:{productId}
```

---

## 17. Queue conventions

Initial queue backend: Redis.

Laravel Horizon is used for visibility.

Future queue groups:

```text
critical
payments
orders
inventory
webhooks
notifications
integrations
imports
analytics
default
```

Foundation only needs queue infrastructure and one smoke-test job if useful.

Do not implement Kafka or RabbitMQ now.

---

## 18. API conventions

Version prefix:

```text
/api/v1
```

Foundation endpoints may include:

```text
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/me

GET    /api/v1/stores
POST   /api/v1/stores
GET    /api/v1/stores/{store}

GET    /api/v1/products
POST   /api/v1/products
GET    /api/v1/products/{product}
PATCH  /api/v1/products/{product}
DELETE /api/v1/products/{product}

GET    /api/v1/health
```

The exact URL for selecting the active store may be adjusted during implementation, but selection must always validate membership.

JSON responses should use Laravel API Resources.

Validation uses Form Requests or equivalent request objects.

---

## 19. Observability

Foundation:

- structured application logs;
- request ID;
- active store ID where available;
- authenticated user ID where available;
- Sentry-compatible exception reporting.

Never log:

- passwords;
- access tokens;
- full payment credentials;
- secrets.

---

## 20. Security baseline

Foundation must account for:

- tenant isolation;
- CSRF;
- XSS-aware frontend output;
- SQL injection prevention through parameterized ORM/query builder use;
- rate limiting;
- brute-force login protection;
- authorization policies;
- secure cookies;
- secret management;
- safe CORS;
- file upload restrictions when uploads are introduced;
- audit logging later;
- webhook signature validation when webhooks are introduced.

The primary Foundation security deliverable is tenant isolation.

---

## 21. Testing strategy

### Backend

Use Pest.

Required Foundation test categories:

- authentication;
- store creation;
- membership;
- tenant context;
- product CRUD;
- tenant isolation.

Mandatory isolation cases:

```text
Store A user cannot read Store B product.
Store A user cannot update Store B product.
Store A user cannot delete Store B product.
Store A user cannot resolve Store B product through route model binding.
Store A user cannot activate Store B without membership.
Unauthenticated request cannot establish tenant context.
```

Also test positive cases for the same actions inside the active store.

### Frontend

- Vitest for composables/components;
- Playwright for critical flows.

Initial E2E:

```text
register/login
→ create store
→ activate store
→ create product
→ list product
```

---

## 22. CI quality gates

Backend:

```text
composer validate
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Frontend:

```text
pnpm lint
pnpm typecheck
pnpm test
pnpm build
```

E2E may run in a separate CI job.

No merge to main with failing required checks.

---

## 23. Development environment

Docker Compose services:

```text
nginx
api
postgres
redis
minio
mailpit
```

Nuxt development servers may run on the host for faster HMR.

Provide:

```text
.env.example
Makefile
docker-compose.yml
```

Expected commands:

```text
make up
make down
make install
make test
```

---

## 24. Foundation milestone

Foundation is complete when all of the following are true:

- Laravel 13 application boots;
- Nuxt admin boots;
- Nuxt storefront boots;
- PostgreSQL is configured;
- Redis is configured;
- Docker development environment works;
- User model exists;
- Store model exists;
- Store membership exists;
- TenantContext exists;
- active tenant can be established safely;
- Product and ProductVariant foundations exist;
- Domain foundation exists;
- `/api/v1` routes exist;
- authentication works;
- tenant-sensitive product CRUD works;
- cross-tenant access is prevented;
- mandatory isolation tests pass;
- Pint passes;
- PHPStan/Larastan passes;
- frontend lint/typecheck/build pass;
- CI exists.

---

## 25. Explicitly out of scope for Foundation

Do not implement:

- checkout;
- orders;
- payments;
- YooKassa;
- CDEK;
- customers;
- discounts;
- inventory reservation;
- theme editor;
- custom-domain verification;
- automatic SSL;
- analytics pipeline;
- app marketplace;
- OAuth apps;
- marketplace integrations;
- billing/subscriptions;
- full RBAC;
- microservices;
- Kafka;
- Elasticsearch/OpenSearch.

These belong to later milestones.

---

## 26. Planned milestones after Foundation

### Milestone 1 — Catalog & Inventory

- flexible product options;
- variants;
- collections/categories;
- media;
- locations;
- inventory levels;
- inventory movements.

### Milestone 2 — Storefront & Cart

- storefront tenant resolution;
- catalog pages;
- product pages;
- cart;
- SEO baseline.

### Milestone 3 — Checkout & Orders

- checkout;
- inventory reservations;
- customers;
- order snapshots;
- order state model.

### Milestone 4 — Payments & Shipping

- payment abstraction;
- YooKassa;
- shipping abstraction;
- CDEK;
- webhook handling;
- idempotency.

### Milestone 5 — Themes & Domains

- JSON section theme engine;
- drafts/publish/versioning;
- custom domains;
- DNS verification;
- SSL provisioning.

### Milestone 6 — Platform ecosystem

- webhooks;
- public API;
- apps;
- OAuth/scopes;
- integrations;
- SaaS billing;
- analytics.

---

## 27. Decision rule

When choosing between a sophisticated architecture and a simpler architecture that preserves the same future boundary, choose the simpler implementation.

Do not optimize for hypothetical scale before the product has real scale.

Do not compromise tenant isolation, transactional correctness or domain boundaries for short-term speed.
