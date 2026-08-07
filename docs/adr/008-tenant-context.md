# ADR-008: TenantContext

## Status
Accepted

## Context
Tenant resolution must be centralized and secure.

## Options
1. Pass store_id everywhere
2. Global scopes only
3. Explicit TenantContext

## Decision
Use an explicit TenantContext.

## Consequences
### Positive
- Centralized tenant resolution
- Easier testing
- Works for HTTP, jobs and future storefront domains

### Negative
- Context lifecycle must be managed carefully

## Security Requirements
Operations requiring a tenant must fail if no TenantContext exists.
