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

  it('sends the Idempotency-Key header on checkout completion', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 'order-1', number: 1001 } }, 201))
    vi.stubGlobal('fetch', fetchMock)

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })
    await client.checkout.complete('key-123')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('http://store-a.localhost:8000/api/v1/storefront/checkout/complete')
    expect(init.method).toBe('POST')
    const headers = new Headers(init.headers)
    expect(headers.get('Idempotency-Key')).toBe('key-123')
  })

  it('opens and updates a checkout', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 'checkout-1', status: 'open' } }))
    vi.stubGlobal('fetch', fetchMock)

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })
    await client.checkout.open()
    await client.checkout.update({ email: 'buyer@example.com', shipping_address: { city: 'Testville' } })

    expect(fetchMock).toHaveBeenNthCalledWith(1, 'http://store-a.localhost:8000/api/v1/storefront/checkout', expect.objectContaining({ method: 'POST' }))
    expect(fetchMock).toHaveBeenNthCalledWith(2, 'http://store-a.localhost:8000/api/v1/storefront/checkout', expect.objectContaining({
      method: 'PATCH',
      body: JSON.stringify({ email: 'buyer@example.com', shipping_address: { city: 'Testville' } }),
    }))
  })

  it('fetches an order confirmation by id', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 'order-1', number: 1001 } }))
    vi.stubGlobal('fetch', fetchMock)

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })
    await client.orders.get('order-1')

    expect(fetchMock).toHaveBeenCalledWith('http://store-a.localhost:8000/api/v1/storefront/orders/order-1', expect.anything())
  })

  it('sends the Idempotency-Key header and provider body on payment creation', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 'payment-1', status: 'processing' } }, 201))
    vi.stubGlobal('fetch', fetchMock)

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })
    await client.payments.create('order-1', 'fake', 'idem-key-1')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('http://store-a.localhost:8000/api/v1/storefront/orders/order-1/payments')
    expect(init.method).toBe('POST')
    expect(init.body).toBe(JSON.stringify({ provider: 'fake' }))
    expect(new Headers(init.headers).get('Idempotency-Key')).toBe('idem-key-1')
  })

  it('fetches fake payment info and posts an outcome', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { payment_id: 'payment-1', order_number: 1001, amount: 1000, currency: 'RUB', status: 'processing' } }))
    vi.stubGlobal('fetch', fetchMock)

    const client = new StorefrontApiClient({ baseUrl: 'http://store-a.localhost:8000' })
    await client.fakePayments.get('fake_abc')
    await client.fakePayments.outcome('fake_abc', 'success')

    expect(fetchMock).toHaveBeenNthCalledWith(1, 'http://store-a.localhost:8000/api/v1/fake-payments/fake_abc', expect.anything())
    expect(fetchMock).toHaveBeenNthCalledWith(2, 'http://store-a.localhost:8000/api/v1/fake-payments/fake_abc/outcome', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ outcome: 'success' }),
    }))
  })
})

describe('ApiClientError', () => {
  it('is the error type thrown by StorefrontApiClient', () => {
    const error = new ApiClientError(404, { message: 'Not found' })
    expect(error).toBeInstanceOf(Error)
    expect(error.status).toBe(404)
  })
})
