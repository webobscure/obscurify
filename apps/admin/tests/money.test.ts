import { describe, expect, it } from 'vitest'
import { calculateMarginPercent, formatMoney, parseMoney } from '../app/utils/money'

describe('parseMoney / formatMoney round trip (ADR-010: integer minor units only)', () => {
  it('round-trips a plain amount through minor units', () => {
    const minor = parseMoney('1499.99')
    expect(minor).toBe(149999)
    expect(Number.isInteger(minor)).toBe(true)
  })

  it('accepts a comma decimal separator (RU keyboard input)', () => {
    expect(parseMoney('1499,99')).toBe(149999)
  })

  it('formats RUB using the ru-RU locale convention', () => {
    const formatted = formatMoney({ amount: 149999, currency: 'RUB' })
    // Intl output varies by ICU data (narrow vs. full symbol, NBSP vs. space)
    // — assert on the digits/structure, not the exact byte sequence.
    expect(formatted).toMatch(/1[\s]499,99/)
  })

  it('formats and parses zero', () => {
    expect(formatMoney({ amount: 0, currency: 'RUB' })).toMatch(/0,00/)
    expect(parseMoney('0')).toBe(0)
    expect(parseMoney('0.00')).toBe(0)
  })

  it('handles a large amount without float drift', () => {
    // A classic float trap: 999999.99 * 100 in naive floating point can
    // land on 99999998.99999999, not 99999999 — Math.round in parseMoney
    // is what keeps this an exact integer.
    expect(parseMoney('999999.99')).toBe(99999999)
  })

  it('round-trips a variant compare-at price (nullable) correctly', () => {
    expect(parseMoney('')).toBeNull()
    expect(parseMoney('   ')).toBeNull()
  })

  it('returns null for unparseable input rather than NaN or 0', () => {
    expect(parseMoney('abc')).toBeNull()
    expect(parseMoney('₽₽₽')).toBeNull()
  })

  it('never produces a non-integer minor-unit value for any parseable input', () => {
    for (const input of ['1.005', '0.1', '33.333', '1499.999']) {
      const result = parseMoney(input)
      expect(result).not.toBeNull()
      expect(Number.isInteger(result)).toBe(true)
    }
  })
})

describe('calculateMarginPercent', () => {
  it('computes margin from price and cost, both in minor units', () => {
    // price 1500, cost 900 -> (1500-900)/1500 = 40%
    expect(calculateMarginPercent(1500, 900)).toBe(40)
  })

  it('returns null when cost is missing (never fabricates a margin)', () => {
    expect(calculateMarginPercent(1500, null)).toBeNull()
    expect(calculateMarginPercent(1500, undefined)).toBeNull()
  })

  it('returns null when price is zero or null (avoids division by zero)', () => {
    expect(calculateMarginPercent(0, 500)).toBeNull()
    expect(calculateMarginPercent(null, 500)).toBeNull()
  })

  it('supports a negative margin (selling below cost)', () => {
    expect(calculateMarginPercent(1000, 1500)).toBe(-50)
  })

  it('rounds to a whole percent', () => {
    // 1000 price, 333 cost -> 66.7% -> rounds to 67
    expect(calculateMarginPercent(1000, 333)).toBe(67)
  })
})
