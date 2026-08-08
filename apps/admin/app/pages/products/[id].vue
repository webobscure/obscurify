<template>
  <div v-if="product">
    <p><NuxtLink to="/products">&larr; Products</NuxtLink></p>
    <h1>{{ product.title }}</h1>
    <p v-if="error" class="error">{{ error }}</p>

    <section>
      <h2>Details</h2>
      <form class="grid" @submit.prevent="saveDetails">
        <label>Title <input v-model="details.title" type="text" required ></label>
        <label>Slug <input v-model="details.slug" type="text" ></label>
        <label>Vendor <input v-model="details.vendor" type="text" ></label>
        <label>Product type <input v-model="details.product_type" type="text" ></label>
        <label>Status
          <select v-model="details.status">
            <option value="draft">draft</option>
            <option value="active">active</option>
            <option value="archived">archived</option>
          </select>
        </label>
        <label>Description <textarea v-model="details.description" /></label>
        <label>SEO title <input v-model="details.seo_title" type="text" ></label>
        <label>SEO description <textarea v-model="details.seo_description" /></label>
        <button type="submit" :disabled="savingDetails">{{ savingDetails ? 'Saving…' : 'Save details' }}</button>
      </form>
    </section>

    <section>
      <h2>Options</h2>
      <div v-for="option in product.options" :key="option.id" class="card">
        <strong>{{ option.name }}</strong>
        <ul>
          <li v-for="value in option.values" :key="value.id">
            {{ value.value }}
            <button type="button" @click="deleteOptionValue(option.id, value.id)">Remove</button>
          </li>
        </ul>
        <form class="inline" @submit.prevent="addOptionValue(option.id)">
          <input v-model="newValueByOption[option.id]" type="text" placeholder="New value" required >
          <button type="submit">Add value</button>
        </form>
        <button type="button" @click="deleteOption(option.id)">Delete option</button>
      </div>

      <form class="inline" @submit.prevent="addOption">
        <input v-model="newOptionName" type="text" placeholder="Option name (e.g. Color)" required >
        <button type="submit">Add option</button>
      </form>
    </section>

    <section>
      <h2>Variants</h2>
      <table v-if="product.variants?.length">
        <thead>
          <tr>
            <th>Options</th>
            <th>SKU</th>
            <th>Price</th>
            <th>Compare at</th>
            <th>Currency</th>
            <th>Status</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="variant in product.variants" :key="variant.id">
            <td>{{ variant.title }}</td>
            <td><input v-model="variantEdits[variant.id]!.sku" type="text" ></td>
            <td><input v-model.number="variantEdits[variant.id]!.price_amount" type="number" ></td>
            <td><input v-model.number="variantEdits[variant.id]!.compare_at_price_amount" type="number" ></td>
            <td>{{ variant.currency }}</td>
            <td>
              <select v-model="variantEdits[variant.id]!.status">
                <option value="draft">draft</option>
                <option value="active">active</option>
                <option value="archived">archived</option>
              </select>
            </td>
            <td>
              <button type="button" @click="saveVariant(variant.id)">Save</button>
              <button type="button" @click="deleteVariant(variant.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>No variants yet.</p>

      <form class="grid" @submit.prevent="addVariant">
        <label v-for="option in product.options" :key="option.id">
          {{ option.name }}
          <select v-model="newVariantValues[option.id]">
            <option value="">—</option>
            <option v-for="value in option.values" :key="value.id" :value="value.id">{{ value.value }}</option>
          </select>
        </label>
        <label>Price (minor units) <input v-model.number="newVariant.price_amount" type="number" required ></label>
        <label>SKU <input v-model="newVariant.sku" type="text" ></label>
        <button type="submit">Add variant</button>
      </form>
    </section>

    <section>
      <h2>Images</h2>
      <div class="thumbs">
        <figure v-for="media in product.media" :key="media.id">
          <img :src="media.url" :alt="media.alt ?? ''" width="120" >
          <figcaption>
            <button type="button" @click="deleteMedia(media.id)">Remove</button>
          </figcaption>
        </figure>
      </div>
      <form @submit.prevent="uploadImage">
        <input ref="fileInput" type="file" accept="image/*" required >
        <button type="submit" :disabled="uploading">{{ uploading ? 'Uploading…' : 'Upload image' }}</button>
      </form>
    </section>

    <section>
      <h2>Collections</h2>
      <ul class="checklist">
        <li v-for="collection in collections" :key="collection.id">
          <label>
            <input
              type="checkbox"
              :checked="isInCollection(collection.id)"
              @change="toggleCollection(collection.id, ($event.target as HTMLInputElement).checked)"
            >
            {{ collection.title }}
          </label>
        </li>
      </ul>
    </section>

    <section>
      <h2>Inventory</h2>
      <div v-for="variant in product.variants" :key="variant.id" class="card">
        <strong>{{ variant.title }}</strong> ({{ variant.sku || 'no SKU' }})
        <form class="inline" @submit.prevent="adjustInventory(variant.id)">
          <select v-model="adjustForm[variant.id]!.location_id" required>
            <option value="" disabled>Location…</option>
            <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
          </select>
          <input v-model.number="adjustForm[variant.id]!.quantity_delta" type="number" placeholder="+/- quantity" required >
          <select v-model="adjustForm[variant.id]!.reason">
            <option value="manual_adjustment">manual_adjustment</option>
            <option value="initial_stock">initial_stock</option>
            <option value="import">import</option>
            <option value="return">return</option>
            <option value="damage">damage</option>
            <option value="correction">correction</option>
          </select>
          <button type="submit">Adjust</button>
        </form>
      </div>
    </section>
  </div>
  <p v-else>Loading…</p>
</template>

<script setup lang="ts">
import type { Collection, Location, Product } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const productId = route.params.id as string

const product = ref<Product | null>(null)
const collections = ref<Collection[]>([])
const locations = ref<Location[]>([])
const error = ref<string | null>(null)
const savingDetails = ref(false)
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const details = reactive({
  title: '',
  slug: '',
  vendor: '',
  product_type: '',
  status: 'draft',
  description: '',
  seo_title: '',
  seo_description: '',
})

const variantEdits = reactive<Record<string, { sku: string; price_amount: number; compare_at_price_amount: number | null; status: string }>>({})
const newOptionName = ref('')
const newValueByOption = reactive<Record<string, string>>({})
const newVariant = reactive<{ price_amount: number | null; sku: string }>({ price_amount: null, sku: '' })
const newVariantValues = reactive<Record<string, string>>({})
const adjustForm = reactive<Record<string, { location_id: string; quantity_delta: number | null; reason: string }>>({})

function syncFromProduct() {
  if (!product.value) return

  details.title = product.value.title
  details.slug = product.value.slug
  details.vendor = product.value.vendor ?? ''
  details.product_type = product.value.product_type ?? ''
  details.status = product.value.status
  details.description = product.value.description ?? ''
  details.seo_title = product.value.seo_title ?? ''
  details.seo_description = product.value.seo_description ?? ''

  for (const variant of product.value.variants ?? []) {
    variantEdits[variant.id] = {
      sku: variant.sku ?? '',
      price_amount: variant.price_amount,
      compare_at_price_amount: variant.compare_at_price_amount,
      status: variant.status,
    }
    adjustForm[variant.id] ??= { location_id: '', quantity_delta: null, reason: 'manual_adjustment' }
  }
}

async function load() {
  const [productResponse, collectionsResponse, locationsResponse] = await Promise.all([
    useApi().products.get(productId),
    useApi().collections.list(),
    useApi().locations.list(),
  ])
  product.value = productResponse.data
  collections.value = collectionsResponse.data
  locations.value = locationsResponse.data
  syncFromProduct()
}

async function withError(fn: () => Promise<void>) {
  error.value = null
  try {
    await fn()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function saveDetails() {
  savingDetails.value = true
  await withError(async () => {
    const response = await useApi().products.update(productId, { ...details })
    product.value = { ...product.value!, ...response.data }
  })
  savingDetails.value = false
}

async function addOption() {
  await withError(async () => {
    await useApi().productOptions.create(productId, { name: newOptionName.value })
    newOptionName.value = ''
    await load()
  })
}

async function deleteOption(optionId: string) {
  await withError(async () => {
    await useApi().productOptions.remove(productId, optionId)
    await load()
  })
}

async function addOptionValue(optionId: string) {
  await withError(async () => {
    await useApi().productOptions.values.create(productId, optionId, { value: newValueByOption[optionId] ?? '' })
    newValueByOption[optionId] = ''
    await load()
  })
}

async function deleteOptionValue(optionId: string, valueId: string) {
  await withError(async () => {
    await useApi().productOptions.values.remove(productId, optionId, valueId)
    await load()
  })
}

async function addVariant() {
  await withError(async () => {
    const optionValueIds = Object.values(newVariantValues).filter((id): id is string => Boolean(id))
    await useApi().productVariants.create(productId, {
      price_amount: newVariant.price_amount!,
      sku: newVariant.sku || undefined,
      option_value_ids: optionValueIds.length ? optionValueIds : undefined,
    })
    newVariant.price_amount = null
    newVariant.sku = ''
    for (const key of Object.keys(newVariantValues)) newVariantValues[key] = ''
    await load()
  })
}

async function saveVariant(variantId: string) {
  await withError(async () => {
    const edit = variantEdits[variantId]
    if (!edit) return

    await useApi().productVariants.update(productId, variantId, {
      sku: edit.sku || null,
      price_amount: edit.price_amount,
      compare_at_price_amount: edit.compare_at_price_amount,
      status: edit.status,
    })
    await load()
  })
}

async function deleteVariant(variantId: string) {
  await withError(async () => {
    await useApi().productVariants.remove(productId, variantId)
    await load()
  })
}

async function uploadImage() {
  const file = fileInput.value?.files?.[0]
  if (!file) return

  uploading.value = true
  await withError(async () => {
    await useApi().media.attachToProduct(productId, file)
    if (fileInput.value) fileInput.value.value = ''
    await load()
  })
  uploading.value = false
}

async function deleteMedia(mediaId: string) {
  await withError(async () => {
    await useApi().media.remove(mediaId)
    await load()
  })
}

function isInCollection(collectionId: string): boolean {
  return product.value?.collection_ids?.includes(collectionId) ?? false
}

async function toggleCollection(collectionId: string, checked: boolean) {
  await withError(async () => {
    if (checked) {
      await useApi().collections.addProduct(collectionId, productId)
    } else {
      await useApi().collections.removeProduct(collectionId, productId)
    }
    await load()
  })
}

async function adjustInventory(variantId: string) {
  await withError(async () => {
    const form = adjustForm[variantId]
    const item = inventoryItemsByVariant.value[variantId]
    if (!form || !item) {
      error.value = 'Inventory item not loaded yet.'
      return
    }
    await useApi().inventory.adjust(item.id, {
      location_id: form.location_id,
      quantity_delta: form.quantity_delta!,
      reason: form.reason,
    })
    form.quantity_delta = null
  })
}

const inventoryItemsByVariant = ref<Record<string, { id: string }>>({})

async function loadInventoryItems() {
  const response = await useApi().inventory.list()
  const map: Record<string, { id: string }> = {}
  for (const item of response.data) {
    map[item.product_variant_id] = { id: item.id }
  }
  inventoryItemsByVariant.value = map
}

onMounted(async () => {
  await load()
  await loadInventoryItems()
})
</script>

<style scoped>
section {
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e0e0e0;
}

.grid {
  display: grid;
  gap: 0.75rem;
  max-width: 480px;
}

.grid label {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.9rem;
}

.card {
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  padding: 0.75rem;
  margin-bottom: 0.75rem;
}

.inline {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin: 0.5rem 0;
}

.thumbs {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.checklist {
  list-style: none;
  padding: 0;
}
</style>
