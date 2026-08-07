import { describe, expect, it } from 'vitest'
import { ApiClientError } from '@obscurify/api-client'

describe('ApiClientError', () => {
  it('exposes the HTTP status and parsed body', () => {
    const error = new ApiClientError(428, { message: 'No active store.', error: 'tenant_context_missing' })

    expect(error.status).toBe(428)
    expect(error.message).toBe('No active store.')
    expect(error.body.error).toBe('tenant_context_missing')
  })
})
