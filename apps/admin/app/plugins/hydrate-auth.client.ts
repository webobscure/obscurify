export default defineNuxtPlugin(async () => {
  const auth = useAuth()
  const activeStore = useActiveStore()

  auth.hydrate()
  activeStore.hydrate()

  // A token in localStorage only proves a session existed at some point —
  // it says nothing about whether the backend still honors it. Verify
  // against Laravel before the app renders anything as "logged in", so a
  // stale/revoked token never shows an authenticated UI that then fails
  // every real request with "Unauthenticated".
  if (auth.token.value) {
    try {
      await auth.fetchMe()
    } catch {
      auth.clearSession()
      activeStore.clear()
    }
  }
})
