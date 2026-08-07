# ADR-002: Use Laravel for Backend

## Status
Accepted

## Context
The backend must support REST APIs, queues, authentication, integrations and long-term maintainability.

## Options
1. Laravel
2. Yii2
3. NestJS

## Decision
Use Laravel 13 with PHP 8.4+.

## Consequences
### Positive
- Excellent ecosystem
- Horizon, Sanctum, Queues
- Strong testing support

### Negative
- Requires architectural discipline around Eloquent

## Future migration path
Keep domain logic framework-independent where practical.
