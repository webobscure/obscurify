<template>
  <div>
    <h1>My account</h1>

    <ClientOnly>
      <p v-if="loading">Loading…</p>
      <template v-else-if="customer">
        <section>
          <h2>Profile</h2>
          <p class="muted">
            {{ customer.email }}
            <span v-if="customer.verified_at" class="badge verified">Verified</span>
            <span v-else class="badge unverified">Not verified</span>
          </p>

          <p v-if="!customer.verified_at">
            <button type="button" :disabled="resending" @click="handleResendVerification">
              {{ resending ? 'Sending…' : 'Resend verification email' }}
            </button>
            <span v-if="resent" class="muted">Verification email sent.</span>
          </p>

          <form @submit.prevent="handleSaveProfile">
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

            <button type="submit" :disabled="saving">
              {{ saving ? 'Saving…' : 'Save profile' }}
            </button>
          </form>
          <p v-if="saved" class="muted">Profile saved.</p>
        </section>

        <section>
          <h2>Shortcuts</h2>
          <p class="links">
            <NuxtLink to="/account/orders">Order history</NuxtLink>
            &middot;
            <NuxtLink to="/account/addresses">Addresses</NuxtLink>
          </p>
        </section>

        <section>
          <h2>Sessions</h2>
          <table v-if="sessions.length">
            <thead>
              <tr>
                <th>Device</th>
                <th>IP</th>
                <th>Last used</th>
                <th/>
              </tr>
            </thead>
            <tbody>
              <tr v-for="session in sessions" :key="session.id">
                <td>
                  {{ session.user_agent ?? 'Unknown device' }}
                  <span v-if="session.is_current" class="badge verified">This device</span>
                </td>
                <td>{{ session.ip_address ?? '—' }}</td>
                <td>{{ formatDate(session.last_used_at) }}</td>
                <td>
                  <button v-if="!session.is_current" type="button" @click="handleRevokeSession(session.id)">Log out</button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="muted">No other sessions.</p>
        </section>

        <section>
          <button type="button" @click="handleLogout">Log out</button>
        </section>
      </template>

      <p v-if="error" class="error">{{ error }}</p>

      <template #fallback>
        <p>Loading…</p>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import type { CustomerSession } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ middleware: 'auth' })

const auth = useCustomerAuth()
const { customer } = auth

const sessions = ref<CustomerSession[]>([])
const firstName = ref('')
const lastName = ref('')
const phone = ref('')
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const resending = ref(false)
const resent = ref(false)
const error = ref<string | null>(null)

function formatDate(value: string): string {
  return new Date(value).toLocaleString('ru-RU')
}

function fillForm() {
  firstName.value = customer.value?.first_name ?? ''
  lastName.value = customer.value?.last_name ?? ''
  phone.value = customer.value?.phone ?? ''
}

async function handleSaveProfile() {
  saving.value = true
  saved.value = false
  error.value = null
  try {
    const response = await useStorefrontApi().account.update({
      first_name: firstName.value || null,
      last_name: lastName.value || null,
      phone: phone.value || null,
    })
    customer.value = response.data
    saved.value = true
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function handleResendVerification() {
  resending.value = true
  resent.value = false
  error.value = null
  try {
    await useStorefrontApi().account.resendVerification()
    resent.value = true
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    resending.value = false
  }
}

async function handleRevokeSession(sessionId: string) {
  error.value = null
  try {
    await useStorefrontApi().account.sessions.revoke(sessionId)
    sessions.value = sessions.value.filter(session => session.id !== sessionId)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleLogout() {
  await auth.logout()
  await navigateTo('/')
}

onMounted(async () => {
  try {
    if (!customer.value) await auth.fetchCustomer()
    fillForm()
    sessions.value = (await useStorefrontApi().account.sessions.list()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
})

useSeoMeta({
  title: 'My account',
  robots: 'noindex',
})
</script>

<style scoped>
section {
  margin-top: 1.75rem;
}

form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
  max-width: 26rem;
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
  padding: 0.5rem 1rem;
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

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  text-align: left;
  padding: 0.5rem;
  border-bottom: 1px solid #e0e0e0;
}

.badge {
  display: inline-block;
  margin-left: 0.5rem;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  font-size: 0.75rem;
}

.verified {
  background: #e6f4ea;
  color: #1e4620;
}

.unverified {
  background: #fdecea;
  color: #b00020;
}

.muted {
  color: #777;
}

.links a {
  color: #1a1a1a;
}
</style>
