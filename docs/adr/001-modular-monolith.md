# ADR-001: Use Modular Monolith

## Status
Accepted

## Context
The platform will grow to include catalog, inventory, orders, payments, shipping,
themes and integrations. Starting with microservices would add unnecessary
complexity.

## Options
1. Traditional Laravel monolith
2. Modular monolith
3. Microservices

## Decision
Use a modular monolith built on Laravel.

## Consequences
### Positive
- Simpler deployment
- Easier transactions
- Faster MVP development
- Clear module boundaries

### Negative
- Module boundaries rely on discipline
- Requires architectural reviews

## Future migration path
Extract modules such as Analytics, Search or Webhooks only when operationally justified.
