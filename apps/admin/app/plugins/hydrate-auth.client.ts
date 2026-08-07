export default defineNuxtPlugin(() => {
  useAuth().hydrate()
  useActiveStore().hydrate()
})
