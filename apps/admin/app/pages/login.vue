<template>
  <div>
    <h1>{{ t('auth.login.title') }}</h1>
    <form @submit.prevent="handleSubmit">
      <input v-model="email" type="email" :placeholder="t('auth.login.email_placeholder')" required autocomplete="username" >
      <input v-model="password" type="password" :placeholder="t('auth.login.password_placeholder')" required autocomplete="current-password" >
      <button type="submit" :disabled="loading">{{ loading ? t('auth.login.submitting') : t('auth.login.submit') }}</button>
    </form>
    <p v-if="error" class="error">{{ error }}</p>
    <p><NuxtLink to="/register">{{ t('auth.login.register_prompt') }}</NuxtLink></p>
    <LanguageSwitcher />
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'auth' })

const { t } = useI18n()
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
    error.value = e instanceof ApiClientError ? e.message : t('common.something_went_wrong')
  } finally {
    loading.value = false
  }
}
</script>
