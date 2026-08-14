<template>
  <div>
    <PageHeader :title="app?.name ?? 'App'" />

    <template v-if="app">
      <table class="kv">
        <tbody>
          <tr><th>Type</th><td>{{ app.type }}</td></tr>
          <tr><th>Slug</th><td>{{ app.slug }}</td></tr>
          <tr><th>Developer</th><td>{{ app.developer ?? '—' }}</td></tr>
          <tr><th>Status</th><td>{{ app.status }}</td></tr>
          <tr><th>Client ID</th><td>{{ app.client_id ?? '—' }}</td></tr>
          <tr><th>Redirect URLs</th><td>{{ app.redirect_urls.join(', ') }}</td></tr>
          <tr><th>Requested scopes</th><td>{{ app.requested_scopes.join(', ') || '—' }}</td></tr>
        </tbody>
      </table>

      <section>
        <h2>Installation on this store</h2>

        <template v-if="installedApp">
          <table class="kv">
            <tbody>
              <tr><th>Status</th><td>{{ installedApp.status }}</td></tr>
              <tr><th>Granted scopes</th><td>{{ (installedApp.scopes ?? []).join(', ') || '—' }}</td></tr>
              <tr><th>Installed at</th><td>{{ installedApp.installed_at }}</td></tr>
              <tr v-if="installedApp.uninstalled_at"><th>Uninstalled at</th><td>{{ installedApp.uninstalled_at }}</td></tr>
            </tbody>
          </table>

          <button v-if="installedApp.status === 'active'" type="button" :disabled="uninstalling" @click="handleUninstall">
            {{ uninstalling ? 'Uninstalling…' : 'Uninstall' }}
          </button>

          <h3>API tokens</h3>
          <table v-if="tokens.length">
            <thead><tr><th>Type</th><th>Scope</th><th>Expires</th><th>Revoked</th></tr></thead>
            <tbody>
              <tr v-for="token in tokens" :key="token.id">
                <td>{{ token.type }}</td>
                <td>{{ token.scope.join(', ') }}</td>
                <td>{{ token.expires_at }}</td>
                <td>{{ token.revoked_at ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else>No tokens issued yet — this app has not completed the OAuth flow.</p>

          <h3>Webhook subscriptions</h3>
          <table v-if="webhooks.length">
            <thead><tr><th>Name</th><th>Target</th><th>Events</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="webhook in webhooks" :key="webhook.id">
                <td>{{ webhook.name }}</td>
                <td>{{ webhook.target_url }}</td>
                <td>{{ webhook.event_types.join(', ') }}</td>
                <td>{{ webhook.status }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else>No webhook subscriptions yet.</p>
        </template>

        <template v-else>
          <p>Not installed on this store.</p>
          <button type="button" :disabled="installing" @click="handleInstall">
            {{ installing ? 'Installing…' : 'Install' }}
          </button>
        </template>

        <p v-if="error" class="error">{{ error }}</p>
      </section>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { App, AppToken, InstalledApp } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const appId = route.params.id as string
const activeStore = useActiveStore()

const app = ref<App | null>(null)
const installedApp = ref<InstalledApp | null>(null)
const tokens = ref<AppToken[]>([])
const webhooks = ref<{ id: string, name: string, target_url: string, event_types: string[], status: string }[]>([])
const pending = ref(true)
const installing = ref(false)
const uninstalling = ref(false)
const error = ref<string | null>(null)

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  try {
    const [appResponse, installedResponse] = await Promise.all([
      useApi().apps.get(appId),
      useApi().installedApps.list(),
    ])
    app.value = appResponse.data
    installedApp.value = installedResponse.data.find(row => row.app_id === appId) ?? null

    if (installedApp.value) {
      const [tokensResponse, webhooksResponse] = await Promise.all([
        useApi().installedApps.tokens(installedApp.value.id),
        useApi().installedApps.webhooks(installedApp.value.id),
      ])
      tokens.value = tokensResponse.data
      webhooks.value = webhooksResponse.data
    }
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleInstall() {
  installing.value = true
  error.value = null
  try {
    await useApi().apps.install(appId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    installing.value = false
  }
}

async function handleUninstall() {
  if (!installedApp.value) return
  uninstalling.value = true
  error.value = null
  try {
    await useApi().installedApps.uninstall(installedApp.value.id)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    uninstalling.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.kv th {
  text-align: left;
  padding-right: 1rem;
  color: var(--color-text-muted);
}

section {
  margin-top: 1.5rem;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 0.75rem;
}

th, td {
  padding: 0.3rem 0.5rem;
  text-align: left;
  border-bottom: 1px solid var(--color-border);
}
</style>
