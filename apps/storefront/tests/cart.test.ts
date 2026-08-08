import type { CartItem } from '@obscurify/types'
import { describe, expect, it } from 'vitest'
import { computeItemCount } from '../app/utils/cart'

function item(quantity: number): CartItem {
  return {
    id: 'item',
    variant_id: 'variant',
    title: 'Item',
    sku: null,
    quantity,
    price: { amount: 100, currency: 'RUB' },
    line_total: { amount: 100 * quantity, currency: 'RUB' },
    media: [],
  }
}

describe('computeItemCount', () => {
  it('sums quantities across lines', () => {
    expect(computeItemCount([item(2), item(3)])).toBe(5)
  })

  it('is zero for an empty cart', () => {
    expect(computeItemCount([])).toBe(0)
  })
})
