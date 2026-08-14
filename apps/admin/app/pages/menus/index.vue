<template>
  <div>
    <PageHeader title="Menus" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="menus.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Handle</th>
            <th>Created</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="menu in menus" :key="menu.id">
            <td>{{ menu.name }}</td>
            <td><code>{{ menu.handle }}</code></td>
            <td>{{ menu.created_at }}</td>
            <td><NuxtLink :to="`/menus/${menu.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No menus yet.</p>

      <h2>Create a menu</h2>
      <p class="hint">
        The handle is what the storefront asks for by name
        (<code>/storefront/menus/{handle}</code>), so it should stay stable
        once anything links to it. Items are added on the menu's own page.
      </p>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.name" type="text" placeholder="Name (Main navigation)" required >
        <input v-model="form.handle" type="text" placeholder="Handle (main-menu)" required >
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create menu' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Menu } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const menus = ref<Menu[]>([])
const form = reactive<{ name: string, handle: string }>({ name: '', handle: '' })
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().menus.list()
  menus.value = response.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().menus.create({ name: form.name, handle: form.handle })
    form.name = ''
    form.handle = ''
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
