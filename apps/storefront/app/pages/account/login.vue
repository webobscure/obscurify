<template>
  <div class="narrow">
    <h1>Log in</h1>

    <form @submit.prevent="handleSubmit">
      <label>
        Email
        <input v-model="email" type="email" required autocomplete="email">
      </label>
      <label>
        Password
        <input v-model="password" type="password" required autocomplete="current-password">
      </label>

      <button type="submit" :disabled="loading">
        {{ loading ? 'Logging in…' : 'Log in' }}
      </button>
    </form>

    <p v-if="error" class="error">{{ error }}</p>

    <p class="links">
      <NuxtLink to="/account/register">Create an account</NuxtLink>
      &middot;
      <NuxtLink to="/account/forgot-password">Forgot your password?</NuxtLink>
    </p>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

const auth = useCustomerAuth()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

async function handleSubmit() {
  loading.value = true
  error.value = null
  try {
    await auth.login({ email: email.value, password: password.value })
    await navigateTo('/account')
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

useSeoMeta({
  title: 'Log in',
  robots: 'noindex',
})
</script>

<style scoped>
.narrow {
  max-width: 26rem;
}

form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
}

label {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.9rem;
  color: #555;
}

input {
  padding: 0.5rem;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  font: inherit;
  color: #1a1a1a;
}

button {
  align-self: flex-start;
  padding: 0.6rem 1.25rem;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
  cursor: default;
}

.links {
  margin-top: 1.25rem;
  color: #777;
}
</style>
