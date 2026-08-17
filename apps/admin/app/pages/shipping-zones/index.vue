<template>
  <div>
    <PageHeader title="Shipping Zones" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="zones.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th>Regions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="zone in zones" :key="zone.id">
            <td>{{ zone.name }}</td>
            <td>{{ zone.status }}</td>
            <td>
              <span v-for="region in zone.regions" :key="region.id" class="region-chip">
                {{ region.country_code }}<template v-if="region.region">/{{ region.region }}</template>
              </span>
              <span v-if="!zone.regions?.length">—</span>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>No shipping zones yet.</p>

      <h2>Create a shipping zone</h2>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.name" type="text" placeholder="Name (e.g. Russia)" required >

        <div v-for="(region, index) in form.regions" :key="index" class="region-row">
          <input v-model="region.country_code" type="text" placeholder="Country (RU)" maxlength="2" required >
          <input v-model="region.region" type="text" placeholder="Region (optional)" >
          <input v-model="region.postal_code_pattern" type="text" placeholder="Postal prefix (optional)" >
          <button type="button" class="remove" @click="form.regions.splice(index, 1)">Remove</button>
        </div>
        <button type="button" @click="form.regions.push({ country_code: '', region: '', postal_code_pattern: '' })">
          Add region
        </button>

        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create zone' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ShippingZone } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const zones = ref<ShippingZone[]>([])
const form = reactive<{ name: string, regions: { country_code: string, region: string, postal_code_pattern: string }[] }>({
  name: '',
  regions: [{ country_code: '', region: '', postal_code_pattern: '' }],
})
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().shippingZones.list()
  zones.value = response.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().shippingZones.create({
      name: form.name,
      regions: form.regions
        .filter(r => r.country_code)
        .map(r => ({
          country_code: r.country_code,
          region: r.region || null,
          postal_code_pattern: r.postal_code_pattern || null,
        })),
    })
    form.name = ''
    form.regions = [{ country_code: '', region: '', postal_code_pattern: '' }]
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

.region-row {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr auto;
  gap: 0.5rem;
}

.region-chip {
  display: inline-block;
  margin-right: 0.4rem;
  padding: 0.1rem 0.4rem;
  border-radius: var(--radius-sm);
  background: var(--color-bg);
  font-size: var(--text-xs);
}

.remove {
  background: transparent;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  cursor: pointer;
}
</style>
