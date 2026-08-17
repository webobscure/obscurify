/**
 * Stamps the persisted theme preference onto <html> as early in client
 * boot as possible — same "apply before the page is meaningfully
 * visible" precedent as hydrate-auth.client.ts, just for `data-theme`
 * instead of the auth token.
 */
export default defineNuxtPlugin(() => {
  const { preference, apply } = useColorMode()
  apply(preference.value)
})
