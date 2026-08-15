<template>
  <div>
    <PageHeader title="Customer Tags" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <section class="create">
        <h2>Create a tag</h2>
        <form @submit.prevent="handleCreate">
          <input v-model="name" type="text" placeholder="Name (e.g. Wholesale, Fraud Risk)" required>
          <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create tag' }}</button>
        </form>
      </section>

      <table v-if="tags.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Assignments</th>
            <th>Kind</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tag in tags" :key="tag.id">
            <td>{{ tag.name }}</td>
            <td><code>{{ tag.slug }}</code></td>
            <td>{{ tag.assignment_count ?? 0 }}</td>
            <td>
              <span v-if="tag.is_system" class="badge">System (automatic)</span>
              <span v-else class="muted">Manual</span>
            </td>
            <td>
              <button v-if="!tag.is_system" type="button" @click="handleDelete(tag.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>No customer tags yet.</p>

      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { CustomerTag } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()
const tags = ref<CustomerTag[]>([])
const error = ref<string | null>(null)
const creating = ref(false)
const name = ref('')

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    tags.value = (await useApi().customerTags.list()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().customerTags.create(name.value)
    name.value = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

async function handleDelete(tagId: string) {
  if (!confirm('Delete this tag? It will be removed from every customer it is assigned to.')) return
  error.value = null
  try {
    await useApi().customerTags.remove(tagId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.create {
  margin-bottom: var(--space-4);
}

.create form {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.create input {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  min-width: 20rem;
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
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.muted {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
