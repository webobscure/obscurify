<template>
  <div class="narrow">
    <h1>Email verification</h1>

    <ClientOnly>
      <p v-if="loading">Verifying…</p>
      <template v-else-if="verified">
        <p class="notice">Your email address has been verified.</p>
        <p class="links"><NuxtLink to="/account">Go to your account</NuxtLink></p>
      </template>
      <p v-else class="error">{{ error ?? 'This verification link is invalid or has expired.' }}</p>

      <template #fallback>
        <p>Verifying…</p>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'

const loading = ref(true)
const verified = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  const token = useRoute().query.token

  if (typeof token !== 'string' || !token) {
    error.value = 'This verification link is missing its token.'
    loading.value = false
    return
  }

  try {
    await useStorefrontApi().account.verifyEmail(token)
    verified.value = true
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
})

useSeoMeta({
  title: 'Email verification',
  robots: 'noindex',
})
</script>

<style scoped>
.narrow {
  max-width: 26rem;
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
