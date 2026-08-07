# ADR-009: ULID Identifiers

## Status
Accepted

## Context
Public entities should use globally unique sortable identifiers.

## Options
1. BIGINT
2. UUID
3. ULID

## Decision
Use ULIDs for core public entities.

## Future migration path
Retain ULIDs even if services are split.
