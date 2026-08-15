<template>
  <div class="narrow">
    <h1>Create an account</h1>

    <form @submit.prevent="handleSubmit">
      <label>
        Email
        <input v-model="email" type="email" required autocomplete="email">
      </label>
      <label>
        Password
        <input v-model="password" type="password" required autocomplete="new-password">
      </label>
      <label>
        Confirm password
        <input v-model="passwordConfirmation" type="password" required autocomplete="new-password">
      </label>
      <label>
        First name
        <input v-model="firstName" type="text" autocomplete="given-name">
      </label>
      <label>
        Last name
        <input v-model="lastName" type="text" autocomplete="family-name">
      </label>
      <label>
        Phone
        <input v-model="phone" type="tel" autocomplete="tel">
      </label>

      <button type="submit" :disabled="loading">
        {{ loading ? 'Creating…' : 'Create account' }}
      </button>
    </form>

    <p v-if="error" class="error">{{ error }}</p>

    <p class="links">
      Already have an account? <NuxtLink to="/account/login">Log in</NuxtLink>
    </p>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

const auth = useCustomerAuth()

const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const firstName = ref('')
const lastName = ref('')
const phone = ref('')
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
    await auth.register({
      email: email.value,
      password: password.value,
      first_name: firstName.value || undefined,
      last_name: lastName.value || undefined,
      phone: phone.value || undefined,
    })
    await navigateTo('/account')
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}

useSeoMeta({
  title: 'Create an account',
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
