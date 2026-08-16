<template>
  <div>
    <PageHeader title="Russian Legal Details" :breadcrumbs="[{ label: 'Russian Commerce', to: '/russian-commerce/legal-profile' }, { label: 'Legal Details' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="pending" class="hint">Loading…</p>

      <section v-else class="settings-card">
        <p class="hint">
          Optional — a store with no legal details configured is treated as outside the Russian fiscal
          system (no order snapshots, no fiscal receipts). See docs/architecture/russian-commerce.md.
        </p>
        <form @submit.prevent="handleSave">
          <label>
            Legal entity type
            <select v-model="form.legal_entity_type">
              <option value="legal_entity">Legal entity (ООО/АО)</option>
              <option value="individual_entrepreneur">Individual entrepreneur (ИП)</option>
              <option value="self_employed">Self-employed (самозанятый)</option>
            </select>
          </label>
          <label>
            Legal name
            <input v-model="form.legal_name" type="text" required>
          </label>
          <label>
            Short name
            <input v-model="form.short_name" type="text">
          </label>
          <label>
            INN
            <input v-model="form.inn" type="text" required placeholder="10 or 12 digits">
          </label>
          <label v-if="form.legal_entity_type === 'legal_entity'">
            KPP
            <input v-model="form.kpp" type="text" placeholder="9 digits">
          </label>
          <label>
            OGRN
            <input v-model="form.ogrn" type="text" placeholder="13 digits">
          </label>
          <label>
            OGRNIP
            <input v-model="form.ogrnip" type="text" placeholder="15 digits">
          </label>
          <label>
            Contact email
            <input v-model="form.email" type="email">
          </label>
          <label>
            Contact phone
            <input v-model="form.phone" type="text" placeholder="+7XXXXXXXXXX">
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save legal details' }}</button>
          </div>
        </form>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { LegalEntityType, StoreLegalProfile } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const profile = ref<StoreLegalProfile | null>(null)
const pending = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive<{
  legal_entity_type: LegalEntityType
  legal_name: string
  short_name: string
  inn: string
  kpp: string
  ogrn: string
  ogrnip: string
  email: string
  phone: string
}>({
  legal_entity_type: 'legal_entity',
  legal_name: '',
  short_name: '',
  inn: '',
  kpp: '',
  ogrn: '',
  ogrnip: '',
  email: '',
  phone: '',
})

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const response = await useApi().russianCommerce.legalProfile.get()
    profile.value = response.data
    if (profile.value) {
      form.legal_entity_type = profile.value.legal_entity_type
      form.legal_name = profile.value.legal_name
      form.short_name = profile.value.short_name ?? ''
      form.inn = profile.value.inn
      form.kpp = profile.value.kpp ?? ''
      form.ogrn = profile.value.ogrn ?? ''
      form.ogrnip = profile.value.ogrnip ?? ''
      form.email = profile.value.email ?? ''
      form.phone = profile.value.phone ?? ''
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
    const response = await useApi().russianCommerce.legalProfile.update({
      legal_entity_type: form.legal_entity_type,
      legal_name: form.legal_name,
      short_name: form.short_name || null,
      inn: form.inn,
      kpp: form.legal_entity_type === 'legal_entity' ? (form.kpp || null) : null,
      ogrn: form.ogrn || null,
      ogrnip: form.ogrnip || null,
      legal_address: profile.value?.legal_address ?? null,
      actual_address: profile.value?.actual_address ?? null,
      email: form.email || null,
      phone: form.phone || null,
    })
    profile.value = response.data
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
  max-width: 48rem;
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

.settings-card input,
.settings-card select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.form-actions {
  display: flex;
  width: 100%;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
