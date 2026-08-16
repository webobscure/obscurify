<template>
  <div>
    <PageHeader title="Tax / VAT Settings" :breadcrumbs="[{ label: 'Russian Commerce', to: '/russian-commerce/legal-profile' }, { label: 'Tax / VAT Settings' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="pending" class="hint">Loading…</p>

      <section v-else-if="settings" class="settings-card">
        <p class="hint">
          The default VAT rate applies to any product/variant with no fiscal profile of its own —
          see the "Fiscal profile" tab on a product's variant to override per product.
        </p>
        <form @submit.prevent="handleSave">
          <label>
            Default VAT rate
            <select v-model="form.default_vat_rate">
              <option value="none">None (not a VAT payer)</option>
              <option value="vat_0">0%</option>
              <option value="vat_5">5%</option>
              <option value="vat_7">7%</option>
              <option value="vat_10">10%</option>
              <option value="vat_20">20%</option>
            </select>
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
import type { FiscalizationSettings, VatRate } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const settings = ref<FiscalizationSettings | null>(null)
const pending = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive<{ default_vat_rate: VatRate }>({ default_vat_rate: 'none' })

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const response = await useApi().russianCommerce.fiscalizationSettings.get()
    settings.value = response.data
    form.default_vat_rate = settings.value.default_vat_rate
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
    const response = await useApi().russianCommerce.fiscalizationSettings.update({ default_vat_rate: form.default_vat_rate })
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
  max-width: 40rem;
}

.settings-card form {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: flex-end;
  margin-top: var(--space-2);
}

.settings-card label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.settings-card select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
