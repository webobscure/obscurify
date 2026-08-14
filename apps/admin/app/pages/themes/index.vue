<template>
  <div>
    <PageHeader title="Themes" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="themes.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Status</th>
            <th>Live</th>
            <th>Versions</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="theme in themes" :key="theme.id">
            <td>{{ theme.name }}</td>
            <td>{{ theme.slug }}</td>
            <td>{{ theme.status }}</td>
            <td>{{ theme.is_active ? 'Yes' : '—' }}</td>
            <td>{{ theme.versions.length }}</td>
            <td><NuxtLink :to="`/themes/${theme.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No themes yet.</p>

      <h2>Create a theme</h2>
      <p class="hint">
        A new theme starts as a draft with one empty version and all nine template
        slots — publish it to make it available, activate it to serve the storefront.
      </p>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.name" type="text" placeholder="Name" required >
        <input v-model="form.slug" type="text" placeholder="Slug (my-theme)" required >
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create theme' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Theme } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const themes = ref<Theme[]>([])
const form = reactive<{ name: string, slug: string }>({ name: '', slug: '' })
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().themes.list()
  themes.value = response.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().themes.create({ name: form.name, slug: form.slug })
    form.name = ''
    form.slug = ''
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

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}
</style>
