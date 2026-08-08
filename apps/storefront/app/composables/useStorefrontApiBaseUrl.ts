/**
 * The API is resolved by hostname (see EnsureStorefrontTenantContext on
 * the backend), so this app must call it on the SAME hostname the visitor
 * is already using — never a fixed URL. Locally that's
 * store-a.localhost:3001 (this app) -> store-a.localhost:8000 (API); in
 * production the reverse proxy is expected to route both from the same
 * public hostname, so only the port swap matters either way.
 */
export function useStorefrontApiBaseUrl(): string {
  const config = useRuntimeConfig()
  const url = useRequestURL()

  return `${url.protocol}//${url.hostname}:${config.public.apiPort}`
}
