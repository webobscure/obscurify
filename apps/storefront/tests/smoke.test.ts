import { describe, expect, it } from 'vitest'
import type { Product } from '@obscurify/types'

describe('shared types', () => {
  it('are usable from the storefront workspace', () => {
    const product: Product = {
      id: '01H0000000000000000000000',
      store_id: '01H0000000000000000000001',
      title: 'Example',
      slug: 'example',
      description: null,
      status: 'active',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    expect(product.status).toBe('active')
  })
})
