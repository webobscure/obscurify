<template>
  <div>
    <PageHeader title="Redirects" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="redirects.length">
        <thead>
          <tr>
            <th>From</th>
            <th>To</th>
            <th>Code</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="redirect in redirects" :key="redirect.id">
            <td><input v-model="drafts[redirect.id]!.from_path" type="text" ></td>
            <td><input v-model="drafts[redirect.id]!.to_path" type="text" ></td>
            <td>
              <select v-model.number="drafts[redirect.id]!.status_code">
                <option :value="301">301</option>
                <option :value="302">302</option>
              </select>
            </td>
            <td class="actions">
              <button type="button" :disabled="savingId === redirect.id" @click="handleSave(redirect.id)">
                {{ savingId === redirect.id ? 'Saving…' : 'Save' }}
              </button>
              <button type="button" :disabled="deletingId === redirect.id" @click="handleDelete(redirect.id)">
                {{ deletingId === redirect.id ? 'Deleting…' : 'Delete' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>No redirects yet.</p>

      <h2>Create a redirect</h2>
      <p class="hint">The from-path must start with a slash. 301 is permanent, 302 temporary.</p>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.from_path" type="text" placeholder="From (/old-page)" required >
        <input v-model="form.to_path" type="text" placeholder="To (/new-page)" required >
        <select v-model.number="form.status_code">
          <option :value="301">301 — permanent</option>
          <option :value="302">302 — temporary</option>
        </select>
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create redirect' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Redirect } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const redirects = ref<Redirect[]>([])
/** Rebuilt wholesale on every load so a deleted redirect leaves no stale draft. */
const drafts = ref<Record<string, { from_path: string, to_path: string, status_code: number }>>({})
const form = reactive<{ from_path: string, to_path: string, status_code: number }>({ from_path: '', to_path: '', status_code: 301 })
const creating = ref(false)
const savingId = ref<string | null>(null)
const deletingId = ref<string | null>(null)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().redirects.list()
  redirects.value = response.data

  drafts.value = Object.fromEntries(redirects.value.map(redirect => [
    redirect.id,
    { from_path: redirect.from_path, to_path: redirect.to_path, status_code: redirect.status_code },
  ]))
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().redirects.create({ from_path: form.from_path, to_path: form.to_path, status_code: form.status_code })
    form.from_path = ''
    form.to_path = ''
    form.status_code = 301
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

async function handleSave(redirectId: string) {
  const draft = drafts.value[redirectId]
  if (!draft) return

  savingId.value = redirectId
  error.value = null
  try {
    await useApi().redirects.update(redirectId, { ...draft })
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingId.value = null
  }
}

async function handleDelete(redirectId: string) {
  deletingId.value = redirectId
  error.value = null
  try {
    await useApi().redirects.remove(redirectId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    deletingId.value = null
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

.actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}

td input {
  width: 100%;
}
</style>
