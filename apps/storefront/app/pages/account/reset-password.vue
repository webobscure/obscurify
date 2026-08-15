<template>
  <div class="narrow">
    <h1>Set a new password</h1>

    <p v-if="!token" class="error">This reset link is missing its token. Request a new one from the <NuxtLink to="/account/forgot-password">forgot password</NuxtLink> page.</p>

    <template v-else>
      <form @submit.prevent="handleSubmit">
        <label>
          New password
          <input v-model="password" type="password" required autocomplete="new-password">
        </label>
        <label>
          Confirm password
          <input v-model="passwordConfirmation" type="password" required autocomplete="new-password">
        </label>

        <button type="submit" :disabled="loading">
          {{ loading ? 'Saving…' : 'Set new password' }}
        </button>
      </form>

      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

const token = computed(() => {
  const value = useRoute().query.token
  return typeof value === 'string' ? value : ''
})

const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

async function handleSubmit() {
  if (password.value !== passwordConfirmation.value) {
    error.value = 'Passwords do not match.'
    return
  }

  loading.value = true
  error.value = null
  try {
    await useStorefrontApi().account.resetPassword(token.value, password.value)
    await navigateTo('/account/login')
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

useSeoMeta({
  title: 'Set a new password',
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
</style>
