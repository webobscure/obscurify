<template>
  <div>
    <PageHeader title="Customer Groups" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <section class="create">
        <h2>Create a group</h2>
        <form @submit.prevent="handleCreate">
          <input v-model="form.name" type="text" placeholder="Name" required>
          <select v-model="form.type">
            <option value="manual">Manual</option>
            <option value="dynamic">Dynamic (rule-based)</option>
            <option value="protected">Protected system group</option>
          </select>
          <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create group' }}</button>
        </form>
      </section>

      <table v-if="groups.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Status</th>
            <th>Members</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="group in groups" :key="group.id">
            <td>{{ group.name }}</td>
            <td>{{ group.type }}</td>
            <td>{{ group.status }}</td>
            <td>{{ group.member_count ?? '—' }}</td>
            <td><NuxtLink :to="`/customer-groups/${group.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No customer groups yet.</p>

      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { CustomerGroup, CustomerGroupType } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()
const groups = ref<CustomerGroup[]>([])
const error = ref<string | null>(null)
const creating = ref(false)

const form = reactive<{ name: string, type: CustomerGroupType }>({ name: '', type: 'manual' })

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    groups.value = (await useApi().customerGroups.list()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().customerGroups.create({ name: form.name, type: form.type })
    form.name = ''
    form.type = 'manual'
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
.create {
  margin-bottom: var(--space-4);
}

.create form {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.create input,
.create select {
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
</style>
