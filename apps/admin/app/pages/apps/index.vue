<template>
  <div>
    <PageHeader title="Apps" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="apps.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Developer</th>
            <th>Status</th>
            <th>Scopes requested</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="app in apps" :key="app.id">
            <td>{{ app.name }}</td>
            <td>{{ app.type }}</td>
            <td>{{ app.developer ?? '—' }}</td>
            <td>{{ app.status }}</td>
            <td>{{ app.requested_scopes.join(', ') || '—' }}</td>
            <td><NuxtLink :to="`/apps/${app.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No apps yet.</p>

      <h2>Register a private app</h2>
      <p class="hint">
        A Public app (installable by any store) is registered by platform staff, not here —
        see docs/architecture/apps.md.
      </p>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.name" type="text" placeholder="Name" required >
        <input v-model="form.slug" type="text" placeholder="Slug (my-app)" required >
        <input v-model="form.developer" type="text" placeholder="Developer (optional)" >

        <div v-for="(url, index) in form.redirect_urls" :key="index" class="url-row">
          <input v-model="form.redirect_urls[index]" type="text" placeholder="https://app.example.com/oauth/callback" required >
          <button type="button" class="remove" @click="form.redirect_urls.splice(index, 1)">Remove</button>
        </div>
        <button type="button" @click="form.redirect_urls.push('')">Add redirect URL</button>

        <fieldset>
          <legend>Requested scopes</legend>
          <label v-for="scope in knownScopes" :key="scope" class="checkbox">
            <input v-model="form.requested_scopes" type="checkbox" :value="scope">
            {{ scope }}
          </label>
        </fieldset>

        <button type="submit" :disabled="creating">{{ creating ? 'Registering…' : 'Register app' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>

      <div v-if="lastSecret" class="secret-banner">
        <p><strong>Client secret (shown once — copy it now):</strong></p>
        <code>{{ lastSecret }}</code>
        <button type="button" @click="lastSecret = null">Dismiss</button>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { App } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const knownScopes = [
  'products.read', 'products.write', 'orders.read', 'orders.write', 'customers.read',
  'inventory.read', 'inventory.write', 'payments.read', 'shipping.read', 'webhooks.read', 'webhooks.write',
]

const apps = ref<App[]>([])
const form = reactive<{ name: string, slug: string, developer: string, redirect_urls: string[], requested_scopes: string[] }>({
  name: '',
  slug: '',
  developer: '',
  redirect_urls: [''],
  requested_scopes: [],
})
const creating = ref(false)
const error = ref<string | null>(null)
const lastSecret = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().apps.list()
  apps.value = response.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    const response = await useApi().apps.create({
      name: form.name,
      slug: form.slug,
      developer: form.developer || null,
      redirect_urls: form.redirect_urls.filter(Boolean),
      requested_scopes: form.requested_scopes,
    })
    lastSecret.value = response.data.client_secret ?? null
    form.name = ''
    form.slug = ''
    form.developer = ''
    form.redirect_urls = ['']
    form.requested_scopes = []
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.grid {
  display: grid;
  gap: 0.5rem;
  max-width: 480px;
}

.url-row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 0.5rem;
}

fieldset {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 0.5rem 0.75rem;
}

.checkbox {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: var(--text-sm);
}

.remove {
  background: transparent;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}

.secret-banner {
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-bg);
}

.secret-banner code {
  display: block;
  margin: 0.4rem 0;
  word-break: break-all;
}
</style>
