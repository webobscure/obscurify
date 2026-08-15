<template>
  <div>
    <PageHeader title="Automation" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p class="links">
        <NuxtLink to="/automation/templates">Browse starter templates</NuxtLink>
        ·
        <NuxtLink to="/automation/executions">Execution history</NuxtLink>
      </p>

      <section class="create">
        <h2>Create a workflow</h2>
        <form @submit.prevent="handleCreate">
          <input v-model="name" type="text" placeholder="Name" required>
          <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create workflow' }}</button>
        </form>
      </section>

      <table v-if="workflows.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th>Created</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="workflow in workflows" :key="workflow.id">
            <td>{{ workflow.name }}</td>
            <td><span class="badge" :class="workflow.status">{{ workflow.status }}</span></td>
            <td>{{ workflow.created_at }}</td>
            <td><NuxtLink :to="`/automation/${workflow.id}`">Open</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No workflows yet.</p>

      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Workflow } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const workflows = ref<Workflow[]>([])
const error = ref<string | null>(null)
const creating = ref(false)
const name = ref('')
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    workflows.value = (await useApi().automation.workflows.list()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    const response = await useApi().automation.workflows.create({ name: name.value })
    await navigateTo(`/automation/${response.data.id}`)
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
.links {
  color: var(--color-text-muted);
  margin-bottom: var(--space-4);
}

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
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.badge.published {
  background: #e6f4ea;
  color: #1e7e34;
}

.badge.disabled {
  background: #fdf3e0;
  color: #a06400;
}

.badge.archived {
  background: #f3f3f3;
  color: #888;
}
</style>
