# ADR-010: Store Money in Minor Units

## Status
Accepted

## Context
Floating-point values are unsuitable for money.

## Options
1. FLOAT
2. DECIMAL
3. Integer minor units

## Decision
Store money as integer minor units with a currency code.

Example:
149999 RUB = 1499.99 RUB

## Future migration path
Introduce a Money value object if pricing becomes more complex.
