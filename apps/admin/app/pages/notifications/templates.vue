<template>
  <div>
    <PageHeader title="Notification Templates" :breadcrumbs="[{ label: 'Notification Center', to: '/notifications' }, { label: 'Templates' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="builder">
        <h2>{{ editingTemplate ? 'Edit template' : 'New template' }}</h2>
        <form @submit.prevent="handleSubmitTemplate">
          <label>
            Name
            <input v-model="templateForm.name" type="text" required>
          </label>
          <label>
            Channel
            <select v-model="templateForm.channel">
              <option value="email">Email</option>
              <option value="sms">SMS</option>
              <option value="push">Push</option>
              <option value="in_app">In-app</option>
              <option value="webhook">Webhook</option>
            </select>
          </label>
          <label v-if="templateForm.channel === 'email'">
            Subject
            <input v-model="templateForm.subject" type="text" placeholder="e.g. Order #{{order.number}}">
          </label>
          <label class="wide">
            Body (supports dot-path variables in double curly braces, e.g. customer.first_name)
            <textarea v-model="templateForm.body_text" rows="2" required />
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="savingTemplate">{{ savingTemplate ? 'Saving…' : editingTemplate ? 'Save template' : 'Create template' }}</button>
            <button v-if="editingTemplate" type="button" class="secondary" @click="cancelEditTemplate">Cancel</button>
          </div>
        </form>
      </section>

      <table v-if="templates.length" class="templates-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Channel</th>
            <th>Active</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="template in templates" :key="template.id">
            <td>{{ template.name }}</td>
            <td>{{ template.channel }}</td>
            <td>{{ template.is_active ? 'Yes' : 'No' }}</td>
            <td class="row-actions">
              <button type="button" class="link" @click="startEditTemplate(template)">Edit</button>
              <button type="button" class="link danger" @click="handleDeleteTemplate(template)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No templates yet.</p>

      <section class="routing">
        <h2>Event routing</h2>
        <p class="hint">When a Platform Event fires, route it to a template on a channel (spec: "Notifications may originate from Platform Events").</p>

        <form class="routing-form" @submit.prevent="handleCreateEvent">
          <label>
            Event type
            <input v-model="eventForm.event_type" type="text" placeholder="e.g. OrderCreated" required>
          </label>
          <label>
            Channel
            <select v-model="eventForm.channel">
              <option value="email">Email</option>
              <option value="sms">SMS</option>
              <option value="push">Push</option>
              <option value="in_app">In-app</option>
              <option value="webhook">Webhook</option>
            </select>
          </label>
          <label>
            Template
            <select v-model="eventForm.template_id" required>
              <option value="">Select a template…</option>
              <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }} ({{ template.channel }})</option>
            </select>
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="savingEvent">{{ savingEvent ? 'Adding…' : 'Add routing rule' }}</button>
          </div>
        </form>

        <table v-if="events.length" class="templates-table">
          <thead>
            <tr>
              <th>Event type</th>
              <th>Channel</th>
              <th>Template</th>
              <th>Enabled</th>
              <th/>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in events" :key="event.id">
              <td>{{ event.event_type }}</td>
              <td>{{ event.channel }}</td>
              <td>{{ event.template?.name ?? '—' }}</td>
              <td>{{ event.is_enabled ? 'Yes' : 'No' }}</td>
              <td class="row-actions">
                <button type="button" class="link" @click="toggleEvent(event)">{{ event.is_enabled ? 'Disable' : 'Enable' }}</button>
                <button type="button" class="link danger" @click="handleDeleteEvent(event)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="hint">No routing rules yet.</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { NotificationChannelType, NotificationEvent, NotificationTemplate } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const activeStore = useActiveStore()

const templates = ref<NotificationTemplate[]>([])
const events = ref<NotificationEvent[]>([])
const error = ref<string | null>(null)
const savingTemplate = ref(false)
const savingEvent = ref(false)
const editingTemplate = ref<NotificationTemplate | null>(null)

const templateForm = reactive<{ name: string, channel: NotificationChannelType, subject: string, body_text: string }>({
  name: '', channel: 'email', subject: '', body_text: '',
})

const eventForm = reactive<{ event_type: string, channel: NotificationChannelType, template_id: string }>({
  event_type: '', channel: 'email', template_id: '',
})

function resetTemplateForm() {
  templateForm.name = ''
  templateForm.channel = 'email'
  templateForm.subject = ''
  templateForm.body_text = ''
  editingTemplate.value = null
}

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    const api = useApi()
    const [templatesResponse, eventsResponse] = await Promise.all([
      api.notifications.templates.list(),
      api.notifications.events.list(),
    ])
    templates.value = templatesResponse.data
    events.value = eventsResponse.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

function startEditTemplate(template: NotificationTemplate) {
  editingTemplate.value = template
  templateForm.name = template.name
  templateForm.channel = template.channel
  templateForm.subject = template.subject ?? ''
  templateForm.body_text = template.body_text
}

function cancelEditTemplate() {
  resetTemplateForm()
}

async function handleSubmitTemplate() {
  savingTemplate.value = true
  error.value = null
  try {
    const api = useApi()
    const payload = {
      name: templateForm.name,
      channel: templateForm.channel,
      subject: templateForm.channel === 'email' ? (templateForm.subject || null) : null,
      body_text: templateForm.body_text,
    }

    if (editingTemplate.value) {
      const response = await api.notifications.templates.update(editingTemplate.value.id, payload)
      const index = templates.value.findIndex(t => t.id === editingTemplate.value!.id)
      if (index !== -1) templates.value[index] = response.data
    } else {
      const response = await api.notifications.templates.create(payload)
      templates.value.push(response.data)
    }
    resetTemplateForm()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingTemplate.value = false
  }
}

async function handleDeleteTemplate(template: NotificationTemplate) {
  if (!confirm(`Delete template "${template.name}"? Any routing rules using it will also be removed.`)) return
  error.value = null
  try {
    await useApi().notifications.templates.remove(template.id)
    templates.value = templates.value.filter(t => t.id !== template.id)
    events.value = events.value.filter(e => e.template_id !== template.id)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleCreateEvent() {
  savingEvent.value = true
  error.value = null
  try {
    const response = await useApi().notifications.events.create({
      event_type: eventForm.event_type,
      channel: eventForm.channel,
      template_id: eventForm.template_id,
    })
    events.value.push(response.data)
    eventForm.event_type = ''
    eventForm.template_id = ''
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingEvent.value = false
  }
}

async function toggleEvent(event: NotificationEvent) {
  error.value = null
  try {
    const response = await useApi().notifications.events.update(event.id, { is_enabled: !event.is_enabled })
    const index = events.value.findIndex(e => e.id === event.id)
    if (index !== -1) events.value[index] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleDeleteEvent(event: NotificationEvent) {
  if (!confirm(`Delete the routing rule for "${event.event_type}"?`)) return
  error.value = null
  try {
    await useApi().notifications.events.remove(event.id)
    events.value = events.value.filter(e => e.id !== event.id)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.builder {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  padding: var(--space-4);
  margin-bottom: var(--space-6);
  max-width: 48rem;
}

.builder h2 {
  margin: 0 0 var(--space-3);
  font-size: var(--text-base);
}

.builder form,
.routing-form {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: flex-end;
}

.builder label,
.routing-form label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.builder label.wide {
  flex-basis: 100%;
}

.builder input,
.builder select,
.builder textarea,
.routing-form input,
.routing-form select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font: inherit;
}

.form-actions {
  display: flex;
  gap: var(--space-2);
}

.form-actions .secondary {
  background: transparent;
  border: 1px solid var(--color-border);
}

.templates-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: var(--space-6);
}

.templates-table th,
.templates-table td {
  text-align: left;
  padding: var(--space-2);
  border-bottom: 1px solid var(--color-border);
}

.row-actions {
  display: flex;
  gap: var(--space-2);
}

.link {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}

.link.danger {
  color: var(--color-danger, #c00);
}

.routing {
  border-top: 1px solid var(--color-border);
  padding-top: var(--space-4);
}

.routing h2 {
  font-size: var(--text-base);
  margin: 0 0 var(--space-2);
}

.routing-form {
  margin-bottom: var(--space-4);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
