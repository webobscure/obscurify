# ADR-003: Use Nuxt for Frontends

## Status
Accepted

## Context
Both the merchant dashboard and storefront require Vue, while the storefront also benefits from SSR.

## Options
1. Nuxt
2. Vue SPA
3. Next.js

## Decision
Use Nuxt 4 + Vue 3 + TypeScript.

## Consequences
### Positive
- SSR
- Shared component ecosystem
- Strong developer experience

### Negative
- Multiple frontend applications must be maintained

## Future migration path
Frontends can be deployed independently if required.
