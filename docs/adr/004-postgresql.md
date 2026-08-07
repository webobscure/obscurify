# ADR-004: Use PostgreSQL

## Status
Accepted

## Context
The platform requires strong transactional guarantees, JSONB support and advanced indexing.

## Options
1. PostgreSQL
2. MySQL

## Decision
Use PostgreSQL.

## Consequences
### Positive
- Strong consistency
- JSONB
- Excellent indexing

### Negative
- Requires PostgreSQL knowledge

## Future migration path
Introduce partitioning and sharding only when needed.
