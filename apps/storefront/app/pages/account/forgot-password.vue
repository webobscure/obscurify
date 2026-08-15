<template>
  <div class="narrow">
    <h1>Forgot your password?</h1>

    <template v-if="sent">
      <p class="notice">If an account exists for that email, we've sent a password reset link.</p>
      <p class="links"><NuxtLink to="/account/login">Back to log in</NuxtLink></p>
    </template>

    <template v-else>
      <p>Enter your email and we'll send you a reset link.</p>

      <form @submit.prevent="handleSubmit">
        <label>
          Email
          <input v-model="email" type="email" required autocomplete="email">
        </label>

        <button type="submit" :disabled="loading">
          {{ loading ? 'Sending…' : 'Send reset link' }}
        </button>
      </form>

      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

const email = ref('')
const loading = ref(false)
const sent = ref(false)
const error = ref<string | null>(null)

async function handleSubmit() {
  loading.value = true
  error.value = null
  try {
    await useStorefrontApi().account.forgotPassword(email.value)
    sent.value = true
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

useSeoMeta({
  title: 'Forgot your password?',
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

.notice {
  padding: 0.75rem 1rem;
  background: #e6f4ea;
  color: #1e4620;
  border-radius: 4px;
}

.links {
  margin-top: 1.25rem;
  color: #777;
}
</style>
