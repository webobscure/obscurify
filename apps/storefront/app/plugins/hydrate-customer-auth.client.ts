export default defineNuxtPlugin(async () => {
  const auth = useCustomerAuth()

  auth.hydrate()

  // A token in localStorage only proves a session existed at some point.
  // Access tokens are short-lived and refresh tokens are single-use, so
  // verify against the API before rendering anything as signed-in.
  if (auth.accessToken.value) {
    try {
      await auth.fetchCustomer()
    } catch {
      auth.clearSession()
    }
  }
})
