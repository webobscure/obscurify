<template>
  <div>
    <PageHeader title="Notification Center">
      <template #actions>
        <NuxtLink to="/notifications/templates">Templates</NuxtLink>
        ·
        <NuxtLink to="/notifications/channels">Channels</NuxtLink>
        ·
        <NuxtLink to="/notifications/providers">Providers</NuxtLink>
        ·
        <NuxtLink to="/notifications/deliveries">Delivery Log</NuxtLink>
      </template>
    </PageHeader>

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="composer">
        <h2>Compose a notification</h2>
        <form @submit.prevent="handleSend">
          <label>
            Channel
            <select v-model="form.channel">
              <option value="email">Email</option>
              <option value="sms">SMS</option>
              <option value="push">Push</option>
              <option value="in_app">In-app</option>
              <option value="webhook">Webhook</option>
            </select>
          </label>
          <label>
            Customer ID (or leave blank and set an address)
            <input v-model="form.customer_id" type="text" placeholder="Customer ID">
          </label>
          <label>
            Address (email/phone/URL override)
            <input v-model="form.address" type="text" placeholder="Optional override">
          </label>
          <label>
            Subject (email only)
            <input v-model="form.subject" type="text">
          </label>
          <label class="wide">
            Body
            <textarea v-model="form.body_text" rows="2" required />
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="sending">{{ sending ? 'Sending…' : 'Send' }}</button>
          </div>
        </form>
      </section>

      <div class="filters">
        <select v-model="statusFilter">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="delivered">Delivered</option>
          <option value="failed">Failed</option>
          <option value="partially_delivered">Partially delivered</option>
        </select>
      </div>

      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="notifications.length">
        <thead>
          <tr>
            <th>Channel</th>
            <th>Subject / Event</th>
            <th>Triggered by</th>
            <th>Status</th>
            <th>Sent</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="notification in notifications" :key="notification.id">
            <td>{{ notification.channel }}</td>
            <td>{{ notification.subject ?? notification.event_type ?? '—' }}</td>
            <td>{{ notification.triggered_by }}</td>
            <td><span class="badge" :class="notification.status">{{ notification.status }}</span></td>
            <td>{{ notification.created_at }}</td>
            <td><NuxtLink :to="`/notifications/${notification.id}`">Open</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No notifications yet.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { NotificationChannelType, NotificationStatus, NotificationSummary } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const notifications = ref<NotificationSummary[]>([])
const pending = ref(true)
const sending = ref(false)
const error = ref<string | null>(null)
const statusFilter = ref<NotificationStatus | ''>('')

const form = reactive<{ channel: NotificationChannelType, customer_id: string, address: string, subject: string, body_text: string }>({
  channel: 'email',
  customer_id: '',
  address: '',
  subject: '',
  body_text: '',
})

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    notifications.value = (await useApi().notifications.list({ status: statusFilter.value || undefined })).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleSend() {
  sending.value = true
  error.value = null
  try {
    await useApi().notifications.create({
      channel: form.channel,
      customer_id: form.customer_id || undefined,
      address: form.address || undefined,
      subject: form.subject || undefined,
      body_text: form.body_text,
    })
    form.customer_id = ''
    form.address = ''
    form.subject = ''
    form.body_text = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    sending.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
watch(statusFilter, load)
</script>

<style scoped>
.composer {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  padding: var(--space-4);
  margin-bottom: var(--space-6);
  max-width: 48rem;
}

.composer h2 {
  margin: 0 0 var(--space-3);
  font-size: var(--text-base);
}

.composer form {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: flex-end;
}

.composer label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.composer label.wide {
  flex-basis: 100%;
}

.composer input,
.composer select,
.composer textarea {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font: inherit;
}

.form-actions {
  display: flex;
}

.filters {
  margin-bottom: var(--space-3);
}

.filters select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  text-align: left;
  padding: var(--space-2);
  border-bottom: 1px solid var(--color-border);
}

.badge {
  display: inline-block;
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.badge.delivered {
  background: #e6f4ea;
  color: #1e7e34;
}

.badge.failed {
  background: #fdeaea;
  color: #b00020;
}

.badge.pending {
  background: #fdf3e0;
  color: #a06400;
}

.badge.partially_delivered {
  background: #fdf3e0;
  color: #a06400;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
