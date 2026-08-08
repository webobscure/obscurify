import type { StorefrontProductVariant } from '@obscurify/types'
import { describe, expect, it } from 'vitest'
import { buildOptionGroups, matchVariant } from '../app/utils/variantSelection'

function variant(id: string, options: { option: string, value: string }[]): StorefrontProductVariant {
  return {
    id,
    title: options.map(o => o.value).join(' / ') || 'Default Title',
    sku: null,
    price: { amount: 1000, currency: 'RUB' },
    compare_at_price: null,
    options,
    availability: { tracked: true, available: 5, in_stock: true },
    media: [],
  }
}

describe('buildOptionGroups', () => {
  it('collects unique option values across variants, in first-seen order', () => {
    const variants = [
      variant('1', [{ option: 'Color', value: 'Black' }]),
      variant('2', [{ option: 'Color', value: 'White' }]),
      variant('3', [{ option: 'Color', value: 'Black' }]),
    ]

    expect(buildOptionGroups(variants)).toEqual([
      { option: 'Color', values: ['Black', 'White'] },
    ])
  })

  it('returns an empty array for a product with no options', () => {
    expect(buildOptionGroups([variant('1', [])])).toEqual([])
  })
})

describe('matchVariant', () => {
  const variants = [
    variant('black-s', [{ option: 'Color', value: 'Black' }, { option: 'Size', value: 'S' }]),
    variant('black-m', [{ option: 'Color', value: 'Black' }, { option: 'Size', value: 'M' }]),
    variant('white-s', [{ option: 'Color', value: 'White' }, { option: 'Size', value: 'S' }]),
  ]

  it('finds the variant matching the full selection', () => {
    expect(matchVariant(variants, { Color: 'Black', Size: 'M' }, 2)?.id).toBe('black-m')
  })

  it('returns undefined for a partial or non-existent combination', () => {
    expect(matchVariant(variants, { Color: 'White', Size: 'M' }, 2)).toBeUndefined()
    expect(matchVariant(variants, { Color: 'Black' }, 2)).toBeUndefined()
  })

  it('always returns the single variant when the product has no options', () => {
    const simple = [variant('default', [])]
    expect(matchVariant(simple, {}, 0)?.id).toBe('default')
  })
})
