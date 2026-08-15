<template>
  <div>
    <PageHeader :title="notification?.subject ?? notification?.event_type ?? 'Notification'" :breadcrumbs="[{ label: 'Notification Center', to: '/notifications' }, { label: 'Detail' }]" />

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="pending" class="hint">Loading…</p>

    <template v-else-if="notification">
      <div class="meta-row">
        <span class="badge" :class="notification.status">{{ notification.status }}</span>
        <span>{{ notification.channel }}</span>
        <span v-if="notification.event_type">Event: {{ notification.event_type }}</span>
        <span>Triggered by {{ notification.triggered_by }}</span>
      </div>

      <section class="body">
        <p v-if="notification.subject"><strong>Subject:</strong> {{ notification.subject }}</p>
        <p>{{ notification.body_text }}</p>
      </section>

      <section class="deliveries">
        <h2>Deliveries</h2>
        <table v-if="notification.deliveries.length">
          <thead>
            <tr>
              <th>Recipient</th>
              <th>Status</th>
              <th>Attempts</th>
              <th>Error</th>
              <th/>
            </tr>
          </thead>
          <tbody>
            <tr v-for="delivery in notification.deliveries" :key="delivery.id">
              <td>{{ recipientLabel(delivery.recipient_id) }}</td>
              <td><span class="badge" :class="delivery.status">{{ delivery.status }}</span></td>
              <td>{{ delivery.attempt_count }}</td>
              <td>{{ delivery.error_message ?? '—' }}</td>
              <td>
                <button v-if="delivery.status === 'failed'" type="button" class="link" :disabled="retrying === delivery.id" @click="handleRetry(delivery.id)">
                  {{ retrying === delivery.id ? 'Retrying…' : 'Retry' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="hint">No deliveries.</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Notification } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const notificationId = route.params.id as string

const notification = ref<Notification | null>(null)
const pending = ref(true)
const retrying = ref<string | null>(null)
const error = ref<string | null>(null)

function recipientLabel(recipientId: string): string {
  const recipient = notification.value?.recipients.find(r => r.id === recipientId)
  if (!recipient) return recipientId
  return recipient.address ?? recipient.customer_id ?? recipient.recipient_type
}

async function load() {
  pending.value = true
  error.value = null
  try {
    notification.value = (await useApi().notifications.get(notificationId)).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleRetry(deliveryId: string) {
  retrying.value = deliveryId
  error.value = null
  try {
    await useApi().notifications.deliveries.retry(deliveryId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    retrying.value = null
  }
}

onMounted(load)
</script>

<style scoped>
.meta-row {
  display: flex;
  gap: var(--space-3);
  align-items: center;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin-bottom: var(--space-4);
}

.badge {
  display: inline-block;
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.badge.delivered,
.badge.succeeded {
  background: #e6f4ea;
  color: #1e7e34;
}

.badge.failed,
.badge.exhausted {
  background: #fdeaea;
  color: #b00020;
}

.badge.pending,
.badge.sending,
.badge.partially_delivered {
  background: #fdf3e0;
  color: #a06400;
}

.badge.suppressed {
  background: #f3f3f3;
  color: #888;
}

.body {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  padding: var(--space-4);
  margin-bottom: var(--space-6);
  max-width: 48rem;
  white-space: pre-wrap;
}

.deliveries h2 {
  font-size: var(--text-base);
  margin: 0 0 var(--space-3);
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

th,
td {
  text-align: left;
  padding: var(--space-1) var(--space-2);
  border-bottom: 1px solid var(--color-border);
}

.link {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
