<template>
  <div>
    <h1>Log in</h1>
    <form @submit.prevent="handleSubmit">
      <input v-model="email" type="email" placeholder="Email" required autocomplete="username" >
      <input v-model="password" type="password" placeholder="Password" required autocomplete="current-password" >
      <button type="submit" :disabled="loading">{{ loading ? 'Logging in…' : 'Log in' }}</button>
    </form>
    <p v-if="error" class="error">{{ error }}</p>
    <p><NuxtLink to="/register">Need an account? Register</NuxtLink></p>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'auth' })

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const router = useRouter()
const auth = useAuth()

async function handleSubmit() {
  loading.value = true
  error.value = null
  try {
    await auth.login({ email: email.value, password: password.value })
    router.push('/stores')
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}
</script>
