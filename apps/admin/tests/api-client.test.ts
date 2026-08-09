import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiClient, ApiClientError } from '@obscurify/api-client'

describe('ApiClientError', () => {
  it('exposes the HTTP status and parsed body', () => {
    const error = new ApiClientError(428, { message: 'No active store.', error: 'tenant_context_missing' })

    expect(error.status).toBe(428)
    expect(error.message).toBe('No active store.')
    expect(error.body.error).toBe('tenant_context_missing')
  })
})

function mockFetchOnce(status: number, body: unknown = {}) {
  return vi.fn().mockResolvedValue({
    status,
    ok: status >= 200 && status < 300,
    statusText: 'Error',
    json: () => Promise.resolve(body),
  })
}

describe('ApiClient onUnauthorized', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('fires onUnauthorized when a request comes back 401, so a stale token never stays trusted', async () => {
    vi.stubGlobal('fetch', mockFetchOnce(401, { message: 'Unauthenticated.' }))

    const onUnauthorized = vi.fn()
    const client = new ApiClient({ baseUrl: 'http://api.test', getToken: () => 'stale-token', onUnauthorized })

    await expect(client.auth.me()).rejects.toBeInstanceOf(ApiClientError)
    expect(onUnauthorized).toHaveBeenCalledTimes(1)
  })

  it('does not fire onUnauthorized for other error statuses', async () => {
    vi.stubGlobal('fetch', mockFetchOnce(422, { message: 'Validation failed.' }))

    const onUnauthorized = vi.fn()
    const client = new ApiClient({ baseUrl: 'http://api.test', getToken: () => 'token', onUnauthorized })

    await expect(client.auth.me()).rejects.toBeInstanceOf(ApiClientError)
    expect(onUnauthorized).not.toHaveBeenCalled()
  })

  it('does not fire onUnauthorized on a successful request', async () => {
    vi.stubGlobal('fetch', mockFetchOnce(200, { data: { id: '1' } }))

    const onUnauthorized = vi.fn()
    const client = new ApiClient({ baseUrl: 'http://api.test', getToken: () => 'token', onUnauthorized })

    await client.auth.me()
    expect(onUnauthorized).not.toHaveBeenCalled()
  })
})
