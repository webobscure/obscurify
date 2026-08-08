import { describe, expect, it } from 'vitest'
import { formatMoney } from '../app/utils/money'

describe('formatMoney', () => {
  it('divides integer minor units by 100 before formatting', () => {
    // ADR-010: 149999 minor units is 1499.99, not 149999.00. Match on
    // digits/decimal only — the thousands separator RUB formatting uses
    // is a non-breaking space, which eslint's no-irregular-whitespace
    // rule rejects as a literal character in source.
    expect(formatMoney({ amount: 149999, currency: 'RUB' })).toMatch(/1.499,99/)
  })

  it('formats zero correctly', () => {
    expect(formatMoney({ amount: 0, currency: 'RUB' })).toMatch(/^0,00/)
  })

  it('respects the given currency', () => {
    expect(formatMoney({ amount: 500, currency: 'USD' }, 'en-US')).toBe('$5.00')
  })
})
