# Codex Task: Foundation Milestone

You are implementing the first foundation milestone of a new multi-tenant SaaS e-commerce platform.

Before making changes:

1. Read `ARCHITECTURE.md`.
2. Read every file in `docs/adr/`.
3. Treat accepted ADRs as architectural constraints.
4. Inspect the repository before assuming anything.
5. If repository reality conflicts with an ADR, report the conflict before changing the architecture.

## Goal

Build only the technical foundation:

- Laravel 13 backend;
- Nuxt 4 merchant admin;
- Nuxt 4 storefront shell;
- PostgreSQL;
- Redis;
- Docker development environment;
- User;
- Store;
- Store membership;
- TenantContext;
- minimal Domain model;
- minimal Product/ProductVariant foundation;
- authentication;
- versioned REST API;
- tenant isolation;
- automated tenant isolation tests;
- CI quality gates.

Do NOT implement the later commerce product yet.

## Hard scope boundaries

Do not implement:

- cart;
- checkout;
- orders;
- customers;
- payments;
- YooKassa;
- shipping;
- CDEK;
- inventory reservation;
- discounts;
- theme editor;
- DNS verification;
- SSL automation;
- billing;
- subscriptions;
- marketplace integrations;
- OAuth apps;
- analytics pipeline;
- microservices;
- Kafka;
- Elasticsearch/OpenSearch.

If one of these is needed only as a future boundary, create documentation or an interface placeholder only when it materially improves the foundation. Do not create speculative abstractions.

---

## Required repository structure

Target structure:

```text
/
├── apps/
│   ├── api/
│   ├── admin/
│   ├── storefront/
│   └── platform-admin/
├── packages/
│   ├── ui/
│   ├── api-client/
│   └── types/
├── infra/
│   ├── docker/
│   └── nginx/
├── docs/
│   └── adr/
├── ARCHITECTURE.md
├── docker-compose.yml
├── pnpm-workspace.yaml
├── Makefile
└── .env.example
```

`platform-admin` can remain a minimal placeholder in this milestone.

Do not reorganize accepted documentation unless there is a concrete reason.

---

## Backend requirements

Use:

- PHP 8.4+;
- Laravel 13;
- PostgreSQL;
- Redis;
- Laravel Sanctum;
- Laravel Horizon;
- Pest;
- Laravel Pint;
- PHPStan/Larastan.

Prefer a modular/domain-oriented organization.

Do not put all business logic into controllers or Eloquent models.

Suggested starting structure:

```text
apps/api/app/
├── Domain/
│   ├── Identity/
│   ├── Stores/
│   └── Catalog/
├── Shared/
│   ├── Tenancy/
│   ├── Http/
│   └── Support/
└── Providers/
```

Keep the implementation pragmatic.

Do not introduce repositories/interfaces/value objects merely to imitate DDD.

---

## Database requirements

Use migrations.

Use ULIDs for core public domain identifiers where practical.

### users

Use Laravel-compatible user fields.

Do NOT add `store_id` to `users`.

### stores

Required fields:

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

Use a suitable PostgreSQL type for `settings`.

### store_users

Required fields:

```text
id
store_id
user_id
role
status
created_at
updated_at
```

Add foreign keys and uniqueness preventing duplicate membership.

### domains

Required fields:

```text
id
store_id
domain
type
is_primary
verified_at
created_at
updated_at
```

No DNS/SSL implementation yet.

### products

Required fields:

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

Require a unique tenant-scoped product slug.

### product_variants

Required fields:

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

Money must be stored as integer minor units.

Create useful tenant-scoped indexes.

Do not use floating point for money.

---

## TenantContext requirements

This is the most important part of the milestone.

Create an explicit TenantContext abstraction.

It must:

- hold the active Store identity;
- have a clear lifecycle per request/job;
- fail closed when tenant-owned operations require a tenant but none exists;
- not trust `store_id` from request bodies for authorization;
- support a future storefront/domain resolution strategy;
- support future queue jobs;
- avoid tenant context leaking between long-running worker jobs.

Merchant tenant selection must validate that the authenticated user belongs to the selected Store.

A user may belong to several stores.

Do not make `User -> Store` one-to-one.

---

## Tenant-aware persistence

Tenant-owned entities include at least:

- Product;
- ProductVariant;
- Domain.

The implementation must make cross-tenant record resolution difficult by default.

Do not rely solely on frontend filtering.

Do not write controller code like:

```php
Product::findOrFail($id);
```

for tenant-owned operations unless the query is guaranteed to be scoped by active tenant.

Route model binding must not expose another store's record.

Platform-level bypass behavior, if any exists, must be explicit and separately authorized.

---

## Authentication

Implement merchant/platform-user authentication using Laravel Sanctum.

Required endpoints or equivalent behavior:

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/me
```

If registration is useful for the initial E2E flow, add it deliberately.

Use secure Laravel defaults.

Add login throttling.

Do not implement storefront customer authentication.

---

## Store API

Implement enough Store functionality to:

1. create a Store;
2. list Stores available to the authenticated user;
3. select/activate a Store safely;
4. retrieve current Store information.

When a Store is created:

- creator becomes owner;
- membership is created atomically;
- use a DB transaction.

Do not allow a user to activate a Store they are not a member of.

---

## Product API

Implement minimal tenant-scoped CRUD.

Suggested API:

```text
GET    /api/v1/products
POST   /api/v1/products
GET    /api/v1/products/{product}
PATCH  /api/v1/products/{product}
DELETE /api/v1/products/{product}
```

All endpoints require authentication and active tenant.

Create/update requests must not accept `store_id` as an authorization mechanism.

The backend assigns ownership from TenantContext.

Use:

- request validation;
- application actions/services;
- policies where useful;
- API Resources.

Keep controllers thin.

---

## API conventions

All foundation APIs live under:

```text
/api/v1
```

Add:

```text
GET /api/v1/health
```

Return predictable JSON errors.

Do not expose stack traces in production.

---

## Frontend requirements

### admin

Nuxt 4 + Vue 3 + TypeScript.

Minimum UI:

- login;
- store list;
- create store;
- select active store;
- product list;
- create product.

Do not build a full design system.

Focus on a clean functional shell.

Create an API client boundary rather than scattering raw fetch calls throughout components.

### storefront

Create a minimal Nuxt 4 application that boots successfully.

No commerce behavior yet.

Prepare the app so future hostname-based Store resolution can be added without coupling it to merchant admin behavior.

---

## pnpm workspace

Use pnpm workspaces.

At minimum configure:

```text
apps/admin
apps/storefront
packages/api-client
packages/types
```

Share only genuinely reusable code.

Do not tightly couple storefront theme UI and merchant admin UI.

---

## Redis / queues

Configure Redis.

Install/configure Laravel Horizon.

A full job architecture is not required.

Make sure queue infrastructure boots and is testable.

Do not add Kafka/RabbitMQ.

---

## Docker

Provide a working local development environment.

Required services:

```text
nginx
api
postgres
redis
minio
mailpit
```

Nuxt dev servers may run on the host if this produces simpler/faster HMR.

Document the choice.

Create:

```text
docker-compose.yml
.env.example
Makefile
```

Expected commands:

```text
make up
make down
make install
make test
```

Commands must actually work or be clearly documented if a host dependency is required.

---

## Tests

Use Pest.

Tenant isolation tests are mandatory and are the acceptance criteria of this milestone.

Create tests proving:

### Membership isolation

- User A can activate Store A.
- User A cannot activate Store B without membership.
- User belonging to both stores can activate either.

### Product isolation

Given Store A and Store B:

- Store A user can list Store A products.
- Store A user cannot see Store B products in list.
- Store A user cannot GET Store B product by ID.
- Store A user cannot PATCH Store B product.
- Store A user cannot DELETE Store B product.
- Store A user cannot resolve Store B product through route model binding.
- Creating a Product while Store A is active always assigns Store A even if the payload contains another `store_id`.
- No active tenant => tenant-owned Product endpoint fails closed.

### Variant isolation

At least one test must prove ProductVariant cannot cross Store boundaries.

### Positive cases

Do not only write denial tests.

Also prove valid same-store operations work.

---

## Tenant isolation implementation review

After implementation, actively try to break your own isolation model.

Search for:

```text
find(
findOrFail(
whereKey(
resolveRouteBinding
withoutGlobalScopes
store_id
```

Inspect every tenant-owned access path.

Do not assume tests are enough if the implementation contains an obvious bypass.

---

## Static analysis and formatting

Run and fix:

```text
composer validate
vendor/bin/pint
vendor/bin/phpstan analyse
php artisan test
```

Frontend:

```text
pnpm lint
pnpm typecheck
pnpm test
pnpm build
```

Do not stop after generating code.

Continue until required checks pass.

If a tool/configuration is unavailable, state exactly what is missing and what remains unverified.

---

## CI

Create GitHub Actions workflows for:

Backend:
- Composer install;
- PostgreSQL/Redis services if needed;
- Pint check;
- PHPStan/Larastan;
- Pest.

Frontend:
- pnpm install;
- lint;
- typecheck;
- tests;
- build.

Keep workflows understandable.

---

## Documentation

Update or add a short Foundation section if implementation choices materially refine `ARCHITECTURE.md`.

Create a concise developer README covering:

```text
requirements
installation
environment
make commands
tests
admin dev server
storefront dev server
Horizon
```

Do not duplicate the full architecture documentation into README.

---

## Implementation method

Work in this order:

1. Inspect repository.
2. Read architecture and ADRs.
3. Produce a short implementation plan.
4. Set up repository/workspaces.
5. Set up Laravel/PostgreSQL/Redis.
6. Create User/Store/StoreUser/Domain schema.
7. Implement TenantContext.
8. Write tenant context and membership tests.
9. Implement Product/ProductVariant schema.
10. Implement tenant-scoped Product API.
11. Write cross-tenant Product/Variant tests.
12. Implement Sanctum auth.
13. Build minimal Nuxt admin shell.
14. Build minimal storefront shell.
15. Add Docker/Makefile.
16. Add CI.
17. Run all checks.
18. Review tenant isolation manually.
19. Fix failures.
20. Produce final report.

Do not postpone mandatory tests until the very end.

Implement tenant tests as soon as the tenancy foundation exists.

---

## Final report

When complete, output:

### Implemented
- repository structure;
- backend modules;
- database tables;
- TenantContext approach;
- authentication;
- API endpoints;
- frontend apps;
- Docker services;
- CI.

### Tests
List exactly which commands were run and their result.

### Tenant isolation
Explain how isolation is enforced and list the tests proving it.

### Deviations
List every deviation from `ARCHITECTURE.md` or ADRs and why.

### Known risks
List remaining foundation risks.

### Explicitly not implemented
Confirm that checkout, orders, payments, shipping, themes and other later milestones were not implemented.

### Next recommended milestone
Recommend only the next milestone, not a giant multi-feature implementation.

The task is complete only when the Foundation is working and tenant isolation is demonstrably enforced.
