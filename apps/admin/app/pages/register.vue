<template>
  <div>
    <h1>Create account</h1>
    <form @submit.prevent="handleSubmit">
      <input v-model="name" type="text" placeholder="Name" required autocomplete="name" >
      <input v-model="email" type="email" placeholder="Email" required autocomplete="username" >
      <input v-model="password" type="password" placeholder="Password" required autocomplete="new-password" >
      <input
        v-model="passwordConfirmation"
        type="password"
        placeholder="Confirm password"
        required
        autocomplete="new-password"
      >
      <button type="submit" :disabled="loading">{{ loading ? 'Creating…' : 'Create account' }}</button>
    </form>
    <p v-if="error" class="error">{{ error }}</p>
    <p><NuxtLink to="/login">Already have an account? Log in</NuxtLink></p>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const router = useRouter()
const auth = useAuth()

async function handleSubmit() {
  loading.value = true
  error.value = null
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    router.push('/stores')
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}
</script>
