<template>
  <div>
    <h1>Addresses</h1>
    <p class="links"><NuxtLink to="/account">Back to my account</NuxtLink></p>

    <ClientOnly>
      <p v-if="loading">Loading…</p>
      <template v-else>
        <ul v-if="addresses.length" class="address-list">
          <li v-for="address in addresses" :key="address.id">
            <p>
              {{ address.first_name }} {{ address.last_name }}<br>
              {{ address.address_line1 }}<br>
              <template v-if="address.address_line2">{{ address.address_line2 }}<br></template>
              {{ address.city }}<span v-if="address.region">, {{ address.region }}</span> {{ address.postal_code }}<br>
              {{ address.country_code }}
              <template v-if="address.phone"><br>{{ address.phone }}</template>
            </p>
            <p>
              <span v-if="address.is_default_shipping" class="badge">Default shipping</span>
              <span v-if="address.is_default_billing" class="badge">Default billing</span>
            </p>
            <p>
              <button type="button" @click="startEdit(address)">Edit</button>
              <button type="button" @click="handleDelete(address.id)">Delete</button>
            </p>
          </li>
        </ul>
        <p v-else class="muted">You have no saved addresses yet.</p>

        <section>
          <h2>{{ editingId ? 'Edit address' : 'Add an address' }}</h2>

          <form @submit.prevent="handleSubmit">
            <label>
              First name
              <input v-model="form.first_name" type="text">
            </label>
            <label>
              Last name
              <input v-model="form.last_name" type="text">
            </label>
            <label>
              Phone
              <input v-model="form.phone" type="tel">
            </label>
            <label>
              Country code (2 letters)
              <input v-model="form.country_code" type="text" required maxlength="2">
            </label>
            <label>
              Region
              <input v-model="form.region" type="text">
            </label>
            <label>
              City
              <input v-model="form.city" type="text" required>
            </label>
            <label>
              Postal code
              <input v-model="form.postal_code" type="text">
            </label>
            <label>
              Address line 1
              <input v-model="form.address_line1" type="text" required>
            </label>
            <label>
              Address line 2
              <input v-model="form.address_line2" type="text">
            </label>
            <label class="checkbox">
              <input v-model="form.is_default_shipping" type="checkbox">
              Default shipping address
            </label>
            <label class="checkbox">
              <input v-model="form.is_default_billing" type="checkbox">
              Default billing address
            </label>

            <p class="actions">
              <button type="submit" :disabled="saving">
                {{ saving ? 'Saving…' : (editingId ? 'Save address' : 'Add address') }}
              </button>
              <button v-if="editingId" type="button" @click="resetForm">Cancel</button>
            </p>
          </form>
        </section>
      </template>

      <p v-if="error" class="error">{{ error }}</p>

      <template #fallback>
        <p>Loading…</p>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import type { CustomerAddress } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ middleware: 'auth' })

interface AddressForm {
  first_name: string
  last_name: string
  phone: string
  country_code: string
  region: string
  city: string
  postal_code: string
  address_line1: string
  address_line2: string
  is_default_billing: boolean
  is_default_shipping: boolean
}

function emptyForm(): AddressForm {
  return {
    first_name: '',
    last_name: '',
    phone: '',
    country_code: '',
    region: '',
    city: '',
    postal_code: '',
    address_line1: '',
    address_line2: '',
    is_default_billing: false,
    is_default_shipping: false,
  }
}

const addresses = ref<CustomerAddress[]>([])
const form = ref<AddressForm>(emptyForm())
const editingId = ref<string | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

function resetForm() {
  form.value = emptyForm()
  editingId.value = null
}

function startEdit(address: CustomerAddress) {
  editingId.value = address.id
  form.value = {
    first_name: address.first_name ?? '',
    last_name: address.last_name ?? '',
    phone: address.phone ?? '',
    country_code: address.country_code ?? '',
    region: address.region ?? '',
    city: address.city ?? '',
    postal_code: address.postal_code ?? '',
    address_line1: address.address_line1 ?? '',
    address_line2: address.address_line2 ?? '',
    is_default_billing: address.is_default_billing,
    is_default_shipping: address.is_default_shipping,
  }
}

function payload() {
  return {
    first_name: form.value.first_name || null,
    last_name: form.value.last_name || null,
    phone: form.value.phone || null,
    country_code: form.value.country_code.toUpperCase(),
    region: form.value.region || null,
    city: form.value.city,
    postal_code: form.value.postal_code || null,
    address_line1: form.value.address_line1,
    address_line2: form.value.address_line2 || null,
    is_default_billing: form.value.is_default_billing,
    is_default_shipping: form.value.is_default_shipping,
  }
}

async function refresh() {
  addresses.value = (await useStorefrontApi().account.addresses.list()).data
}

async function handleSubmit() {
  saving.value = true
  error.value = null
  try {
    const api = useStorefrontApi().account.addresses
    if (editingId.value) {
      await api.update(editingId.value, payload())
    } else {
      await api.create(payload())
    }
    await refresh()
    resetForm()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(addressId: string) {
  error.value = null
  try {
    await useStorefrontApi().account.addresses.remove(addressId)
    if (editingId.value === addressId) resetForm()
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(async () => {
  try {
    await refresh()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
})

useSeoMeta({
  title: 'Addresses',
  robots: 'noindex',
})
</script>

<style scoped>
.address-list {
  list-style: none;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
  gap: 1rem;
}

.address-list li {
  padding: 1rem;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
}

.address-list p {
  margin: 0 0 0.5rem;
}

section {
  margin-top: 1.75rem;
}

form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
  max-width: 26rem;
}

label {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.9rem;
  color: #555;
}

label.checkbox {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}

input {
  padding: 0.5rem;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  font: inherit;
  color: #1a1a1a;
}

input[type='checkbox'] {
  width: auto;
}

.actions {
  display: flex;
  gap: 0.5rem;
  margin: 0;
}

button {
  padding: 0.5rem 1rem;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
  cursor: default;
}

.badge {
  display: inline-block;
  margin-right: 0.5rem;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: #f0f0f0;
  color: #555;
  font-size: 0.75rem;
}

.muted {
  color: #777;
}

.links a {
  color: #1a1a1a;
}
</style>
