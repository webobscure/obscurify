<template>
  <div>
    <PageHeader title="Shipping Methods" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="methods.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Provider / Service</th>
            <th>Status</th>
            <th>Price</th>
            <th>Delivery</th>
            <th>Zones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="method in methods" :key="method.id">
            <td>{{ method.name }}</td>
            <td>{{ method.provider }}<span v-if="method.service_code"> / {{ method.service_code }}</span></td>
            <td>
              <button type="button" class="status-toggle" @click="toggleStatus(method)">
                {{ method.status }}
              </button>
            </td>
            <td>{{ formatMoney({ amount: method.price_amount, currency: method.currency }) }}</td>
            <td>
              <template v-if="method.estimated_days_min || method.estimated_days_max">
                {{ method.estimated_days_min }}–{{ method.estimated_days_max }} days
              </template>
              <template v-else>—</template>
            </td>
            <td>{{ method.zone_ids?.length ?? 0 }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>No shipping methods yet.</p>

      <h2>Create a shipping method</h2>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.name" type="text" placeholder="Name (e.g. Standard Shipping)" required >
        <input v-model="form.code" type="text" placeholder="Code (e.g. standard)" required >
        <select v-model="form.service_code">
          <option value="standard">standard</option>
          <option value="express">express</option>
          <option value="pickup">pickup</option>
        </select>
        <input v-model.number="form.price_amount" type="number" min="0" placeholder="Price (minor units)" required >
        <input v-model="form.currency" type="text" maxlength="3" placeholder="Currency (RUB)" required >
        <input v-model.number="form.estimated_days_min" type="number" min="0" placeholder="Min days" >
        <input v-model.number="form.estimated_days_max" type="number" min="0" placeholder="Max days" >

        <fieldset>
          <legend>Zones</legend>
          <label v-for="zone in zones" :key="zone.id" class="checkbox">
            <input v-model="form.zone_ids" type="checkbox" :value="zone.id">
            {{ zone.name }}
          </label>
          <p v-if="!zones.length" class="hint">No zones yet — create one on the <NuxtLink to="/shipping-zones">Shipping Zones</NuxtLink> page first.</p>
        </fieldset>

        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create method' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ShippingMethod, ShippingZone } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const methods = ref<ShippingMethod[]>([])
const zones = ref<ShippingZone[]>([])
const form = reactive({
  name: '',
  code: '',
  service_code: 'standard',
  price_amount: null as number | null,
  currency: 'RUB',
  estimated_days_min: null as number | null,
  estimated_days_max: null as number | null,
  zone_ids: [] as string[],
})
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const [methodsResponse, zonesResponse] = await Promise.all([
    useApi().shippingMethods.list(),
    useApi().shippingZones.list(),
  ])
  methods.value = methodsResponse.data
  zones.value = zonesResponse.data
}

async function handleCreate() {
  if (form.price_amount === null) return

  creating.value = true
  error.value = null
  try {
    await useApi().shippingMethods.create({
      name: form.name,
      code: form.code,
      provider: 'fake',
      service_code: form.service_code,
      price_amount: form.price_amount,
      currency: form.currency,
      estimated_days_min: form.estimated_days_min,
      estimated_days_max: form.estimated_days_max,
      zone_ids: form.zone_ids,
    })
    form.name = ''
    form.code = ''
    form.price_amount = null
    form.estimated_days_min = null
    form.estimated_days_max = null
    form.zone_ids = []
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

async function toggleStatus(method: ShippingMethod) {
  const status = method.status === 'active' ? 'inactive' : 'active'
  await useApi().shippingMethods.update(method.id, { status })
  await load()
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.grid {
  display: grid;
  gap: 0.5rem;
  max-width: 420px;
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

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0;
}

.status-toggle {
  background: none;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  cursor: pointer;
  padding: 0.1rem 0.5rem;
}
</style>
