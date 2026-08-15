<template>
  <div>
    <h1>Notifications</h1>
    <p class="links"><NuxtLink to="/account">Back to my account</NuxtLink></p>

    <ClientOnly>
      <p v-if="loading">Loading…</p>

      <template v-else>
        <section>
          <h2>Preferences</h2>
          <form @submit.prevent="handleSavePreferences">
            <label class="checkbox">
              <input v-model="preferences.email_enabled" type="checkbox">
              Email notifications
            </label>
            <label class="checkbox">
              <input v-model="preferences.sms_enabled" type="checkbox">
              SMS notifications
            </label>
            <label class="checkbox">
              <input v-model="preferences.push_enabled" type="checkbox">
              Push notifications
            </label>
            <label class="checkbox">
              <input v-model="preferences.marketing_opt_in" type="checkbox">
              Marketing messages
            </label>
            <button type="submit" :disabled="savingPreferences">{{ savingPreferences ? 'Saving…' : 'Save preferences' }}</button>
            <span v-if="savedPreferences" class="muted">Saved.</span>
          </form>
        </section>

        <section>
          <h2>History</h2>
          <table v-if="recipients.length">
            <thead>
              <tr>
                <th>Message</th>
                <th>Channel</th>
                <th>Received</th>
                <th/>
              </tr>
            </thead>
            <tbody>
              <tr v-for="recipient in recipients" :key="recipient.id" :class="{ unread: !recipient.read_at }">
                <td>{{ recipient.notification?.subject ?? recipient.notification?.event_type ?? 'Notification' }}</td>
                <td>{{ recipient.notification?.channel }}</td>
                <td>{{ recipient.notification ? formatDate(recipient.notification.created_at) : '—' }}</td>
                <td>
                  <button v-if="!recipient.read_at" type="button" @click="handleMarkRead(recipient.id)">Mark read</button>
                  <span v-else class="muted">Read</span>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="muted">No notifications yet.</p>
        </section>

        <p v-if="error" class="error">{{ error }}</p>
      </template>

      <template #fallback>
        <p>Loading…</p>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import type { NotificationPreference, NotificationRecipient } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ middleware: 'auth' })

const recipients = ref<NotificationRecipient[]>([])
const preferences = reactive<{ email_enabled: boolean, sms_enabled: boolean, push_enabled: boolean, marketing_opt_in: boolean }>({
  email_enabled: true,
  sms_enabled: false,
  push_enabled: false,
  marketing_opt_in: false,
})
const loading = ref(true)
const savingPreferences = ref(false)
const savedPreferences = ref(false)
const error = ref<string | null>(null)

function formatDate(value: string): string {
  return new Date(value).toLocaleString('ru-RU')
}

function fillPreferences(data: NotificationPreference) {
  preferences.email_enabled = data.email_enabled
  preferences.sms_enabled = data.sms_enabled
  preferences.push_enabled = data.push_enabled
  preferences.marketing_opt_in = data.marketing_opt_in
}

async function handleSavePreferences() {
  savingPreferences.value = true
  savedPreferences.value = false
  error.value = null
  try {
    const response = await useStorefrontApi().account.notificationPreferences.update({ ...preferences })
    fillPreferences(response.data)
    savedPreferences.value = true
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingPreferences.value = false
  }
}

async function handleMarkRead(recipientId: string) {
  error.value = null
  try {
    const response = await useStorefrontApi().account.notifications.markRead(recipientId)
    const index = recipients.value.findIndex(r => r.id === recipientId)
    if (index !== -1) recipients.value[index] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(async () => {
  try {
    const api = useStorefrontApi()
    const [preferencesResponse, historyResponse] = await Promise.all([
      api.account.notificationPreferences.show(),
      api.account.notifications.list(),
    ])
    fillPreferences(preferencesResponse.data)
    recipients.value = historyResponse.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
})

useSeoMeta({
  title: 'Notifications',
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
  align-items: flex-start;
  gap: 0.75rem;
  margin-top: 1rem;
  max-width: 26rem;
}

.checkbox {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: #555;
}

button {
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

tr.unread td {
  font-weight: 600;
}

.muted {
  color: #777;
}

.links a {
  color: #1a1a1a;
}
</style>
