# ADR-007: Redis Queues

## Status
Accepted

## Context
Background jobs are needed for notifications, imports and integrations.

## Options
1. Redis
2. RabbitMQ
3. Kafka

## Decision
Use Redis + Laravel Horizon.

## Future migration path
Move to a dedicated broker only if workload requires it.
