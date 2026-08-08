import { ApiClientError, StorefrontApiClient } from '@obscurify/api-client'
import { afterEach, describe, expect, it, vi } from 'vitest'

function jsonResponse(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

describe('StorefrontApiClient', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sends credentials so the guest-cart cookie is included', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { items: [], items_subtotal: 0, total: 0, currency: 'RUB', id: 'cart-1' } }))
    vi.stubGlobal('fetch', fetchMock)

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })
    await client.cart.get()

    expect(fetchMock).toHaveBeenCalledWith(
      'http://store-a.localhost:8000/api/v1/storefront/cart',
      expect.objectContaining({ credentials: 'include' }),
    )
  })

  it('never sends a store selector header — tenant comes from baseUrl host only', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })
    await client.products.list()

    const [, init] = fetchMock.mock.calls[0]
    const headers = new Headers(init.headers)
    expect(headers.has('X-Store-Id')).toBe(false)
    expect(headers.has('Authorization')).toBe(false)
  })

  it('builds the products query string from list params', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })
    await client.products.list({ collection: 'featured', sort: 'price_asc', page: 2 })

    const [url] = fetchMock.mock.calls[0]
    expect(url).toBe('http://store-a.localhost:8000/api/v1/storefront/products?collection=featured&sort=price_asc&page=2')
  })

  it('throws ApiClientError with the parsed body on a non-OK response', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ message: 'Only 2 unit(s) available.', errors: { quantity: ['Only 2 unit(s) available.'] } }, 422)))

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })

    await expect(client.cart.addItem({ variant_id: 'v1', quantity: 99 })).rejects.toMatchObject({
      status: 422,
      message: 'Only 2 unit(s) available.',
    })
  })

  it('treats a 204 response as no content', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 204 })))

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })
    await expect(client.cart.removeItem('item-1')).resolves.toBeUndefined()
  })
})

describe('ApiClientError', () => {
  it('is the error type thrown by StorefrontApiClient', () => {
    const error = new ApiClientError(404, { message: 'Not found' })
    expect(error).toBeInstanceOf(Error)
    expect(error.status).toBe(404)
  })
})
