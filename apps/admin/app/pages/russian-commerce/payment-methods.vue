<template>
  <div>
    <PageHeader title="Payment Methods" :breadcrumbs="[{ label: 'Russian Commerce', to: '/russian-commerce/legal-profile' }, { label: 'Payment Methods' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="pending" class="hint">Loading…</p>

      <section v-else class="settings-card">
        <p class="hint">
          No provider makes a real HTTP call for any method this milestone — this only controls
          which method names the storefront may offer (see docs/architecture/russian-commerce.md §6).
        </p>
        <form @submit.prevent="handleSave">
          <label v-for="method in allMethods" :key="method" class="checkbox">
            <input v-model="enabled[method]" type="checkbox">
            {{ methodLabels[method] }}
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PaymentMethodSettings, RussianPaymentMethod } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const settings = ref<PaymentMethodSettings | null>(null)
const pending = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const allMethods: RussianPaymentMethod[] = ['bank_card', 'sbp', 'bank_transfer', 'cash', 'credit']
const methodLabels: Record<RussianPaymentMethod, string> = {
  bank_card: 'Bank card',
  sbp: 'SBP (Fast Payments System)',
  bank_transfer: 'Bank transfer / invoice',
  cash: 'Cash',
  credit: 'Credit',
}

const enabled = reactive<Record<RussianPaymentMethod, boolean>>({
  bank_card: false,
  sbp: false,
  bank_transfer: false,
  cash: false,
  credit: false,
})

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const response = await useApi().russianCommerce.paymentMethodSettings.get()
    settings.value = response.data
    for (const method of allMethods) {
      enabled[method] = settings.value.enabled_methods.includes(method)
    }
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleSave() {
  saving.value = true
  error.value = null
  try {
    const enabledMethods = allMethods.filter(method => enabled[method])
    const response = await useApi().russianCommerce.paymentMethodSettings.update(enabledMethods)
    settings.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.settings-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  padding: var(--space-4);
  margin-bottom: var(--space-6);
  max-width: 32rem;
}

.settings-card form {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.settings-card label.checkbox {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
  color: var(--color-text);
}

.form-actions {
  display: flex;
  margin-top: var(--space-2);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
