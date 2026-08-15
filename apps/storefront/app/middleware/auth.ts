/**
 * Named middleware — applied per page via definePageMeta, never global,
 * since the storefront is mostly public. The server never sees the
 * customer's token (it lives in localStorage), so the guard only runs on
 * the client; the pages themselves render their content inside
 * <ClientOnly> for the same reason.
 */
export default defineNuxtRouteMiddleware(() => {
  if (import.meta.server) return

  const auth = useCustomerAuth()
  auth.hydrate()

  if (!auth.isAuthenticated.value) {
    return navigateTo('/account/login')
  }
})
