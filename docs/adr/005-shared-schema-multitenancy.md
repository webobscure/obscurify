# ADR-005: Shared Schema Multi-Tenancy

## Status
Accepted

## Context
The platform is multi-tenant. Each store owns its own data.

## Options
1. Database per tenant
2. Schema per tenant
3. Shared schema using store_id

## Decision
Use a shared database and shared schema with store_id.

## Consequences
### Positive
- Easier operations
- Simpler migrations
- Lower infrastructure costs

### Negative
- Tenant isolation must be enforced and tested

## Security Requirements
Never trust store_id from client requests.
Resolve the tenant through TenantContext.

## Future migration path
Move tenants to shards if future scale requires it.
