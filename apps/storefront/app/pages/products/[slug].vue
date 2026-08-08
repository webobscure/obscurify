<template>
  <div v-if="product">
    <p><NuxtLink to="/products">&larr; Products</NuxtLink></p>
    <h1>{{ product.title }}</h1>

    <img v-if="activeMedia[0]" :src="activeMedia[0].url" :alt="activeMedia[0].alt ?? product.title" width="360">

    <p v-if="selectedVariant">{{ formatMoney(selectedVariant.price) }}
      <s v-if="selectedVariant.compare_at_price">{{ formatMoney(selectedVariant.compare_at_price) }}</s>
    </p>

    <div v-for="group in optionGroups" :key="group.option" class="option-group">
      <span class="option-label">{{ group.option }}</span>
      <button
        v-for="value in group.values"
        :key="value"
        type="button"
        :class="{ selected: selected[group.option] === value }"
        @click="selected[group.option] = value"
      >
        {{ value }}
      </button>
    </div>

    <p v-if="selectedVariant">
      <span v-if="selectedVariant.availability.tracked">
        {{ selectedVariant.availability.in_stock ? `${selectedVariant.availability.available} in stock` : 'Out of stock' }}
      </span>
      <span v-else>In stock</span>
    </p>

    <form @submit.prevent="handleAddToCart">
      <input v-model.number="quantity" type="number" min="1">
      <button type="submit" :disabled="!selectedVariant || !selectedVariant.availability.in_stock || adding">
        {{ adding ? 'Adding…' : 'Add to cart' }}
      </button>
    </form>
    <p v-if="cart.error.value" class="error">{{ cart.error.value }}</p>

    <p>{{ product.description }}</p>
  </div>
  <p v-else-if="pending">Loading…</p>
  <p v-else>Product not found.</p>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'
import { buildOptionGroups, matchVariant } from '~/utils/variantSelection'

const route = useRoute()
const slug = route.params.slug as string
const cart = useCart()

// A 404 here means "not found", not an app error: useAsyncData captures
// it into `error` rather than throwing, and the template's v-else branch
// (product.value === null) renders the not-found state below.
const { data, pending } = await useAsyncData(`product-${slug}`, () => useStorefrontApi().products.get(slug))

const product = computed(() => data.value?.data ?? null)
const optionGroups = computed(() => buildOptionGroups(product.value?.variants ?? []))

const selected = reactive<Record<string, string>>({})

watch(product, (value) => {
  const first = value?.variants[0]
  if (!first) return
  for (const opt of first.options ?? []) selected[opt.option] = opt.value
}, { immediate: true })

const selectedVariant = computed(() =>
  matchVariant(product.value?.variants ?? [], selected, optionGroups.value.length),
)

const activeMedia = computed(() => (selectedVariant.value?.media?.length ? selectedVariant.value.media : (product.value?.media ?? [])))

const quantity = ref(1)
const adding = ref(false)

async function handleAddToCart() {
  if (!selectedVariant.value) return
  adding.value = true
  try {
    await cart.addItem(selectedVariant.value.id, quantity.value)
  } catch (e) {
    if (!(e instanceof ApiClientError)) throw e
  } finally {
    adding.value = false
  }
}

useSeoMeta({
  title: () => product.value?.title,
  description: () => product.value?.seo.description ?? product.value?.description ?? undefined,
  ogTitle: () => product.value?.seo.title ?? product.value?.title,
  ogDescription: () => product.value?.seo.description ?? product.value?.description ?? undefined,
  ogImage: () => product.value?.media[0]?.url,
})

const canonicalUrl = useRequestURL().origin + `/products/${slug}`

useHead(() => ({
  link: [{ rel: 'canonical', href: canonicalUrl }],
  script: product.value
    ? [{
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'Product',
          name: product.value.title,
          description: product.value.description,
          image: product.value.media.map(m => m.url),
          offers: selectedVariant.value
            ? {
                '@type': 'Offer',
                priceCurrency: selectedVariant.value.price.currency,
                price: (selectedVariant.value.price.amount / 100).toFixed(2),
                availability: selectedVariant.value.availability.in_stock
                  ? 'https://schema.org/InStock'
                  : 'https://schema.org/OutOfStock',
              }
            : undefined,
        }),
      }]
    : [],
}))
</script>

<style scoped>
.option-group {
  margin: 0.75rem 0;
}

.option-label {
  display: inline-block;
  min-width: 4rem;
  font-weight: 600;
}

.option-group button {
  margin-right: 0.5rem;
  padding: 0.25rem 0.75rem;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: white;
  cursor: pointer;
}

.option-group button.selected {
  border-color: #1a1a1a;
  background: #1a1a1a;
  color: white;
}
</style>
