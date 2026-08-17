<template>
  <div v-if="!product" class="loading-shell">
    <Skeleton variant="block" height="180px" />
    <Skeleton variant="text" width="40%" />
    <Skeleton variant="text" width="60%" />
  </div>

  <div v-else class="editor">
    <header class="header">
      <div>
        <AdminBreadcrumbs :items="[{ label: t('productEditor.back_to_products'), to: '/products' }, { label: product.title || t('productEditor.untitled') }]" />
        <div class="title-row">
          <input
            v-model="draft.title"
            type="text"
            class="title-field"
            data-testid="product-title-input"
            :placeholder="t('productEditor.title_placeholder')"
            :aria-label="t('productEditor.title_placeholder')"
          >
          <ProductStatusBadge :status="product.status" />
        </div>
      </div>
      <div class="header-actions">
        <SaveStateIndicator :state="saveState" @save="save" />
        <div ref="kebabRoot" class="kebab-wrap">
          <button type="button" class="icon-btn" :aria-label="t('productEditor.more_actions')" aria-haspopup="menu" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen">⋮</button>
          <div v-if="menuOpen" class="kebab-menu" role="menu">
            <button type="button" role="menuitem" class="danger" @click="confirmDelete = true; menuOpen = false">
              {{ t('productEditor.delete_product') }}
            </button>
          </div>
        </div>
      </div>
    </header>

    <p v-if="error" class="error-banner">{{ error }}</p>

    <div class="body">
      <div class="main">
        <textarea
          v-model="draft.description"
          class="desc-field"
          rows="2"
          data-testid="product-description-input"
          :placeholder="t('productEditor.description_placeholder')"
          :aria-label="t('productEditor.description_placeholder')"
        />

        <section>
          <h2 class="section-h">{{ t('productEditor.media') }}</h2>
          <!-- MediaGrid's own "+" tile is the empty-state affordance for
               Media (it's always present, unlike Options/Variants whose
               "add" controls live outside the item list) — a separate
               EmptyState block here duplicated the same message right
               next to the tile that already says it. -->
          <MediaGrid
            :items="product.media ?? []"
            :uploading="uploadingMedia"
            @upload="onUploadMedia"
            @remove="onRemoveMedia"
            @reorder="onReorderMedia"
            @update-alt="onUpdateMediaAlt"
          />
        </section>

        <section class="options-section">
          <div class="section-toolbar">
            <h2 class="section-h" style="margin:0">{{ t('productEditor.options') }}</h2>
            <form class="inline-form" @submit.prevent="addOption">
              <input v-model="newOptionName" type="text" :placeholder="t('productEditor.option_name_placeholder')" class="mini-input">
              <button type="submit" class="link">{{ t('productEditor.add_option') }}</button>
            </form>
          </div>
          <div v-if="product.options?.length" class="options">
            <div v-for="option in product.options" :key="option.id" class="option">
              <div class="option-head">
                <strong>{{ option.name }}</strong>
                <button type="button" class="link danger" @click="removeOption(option.id)">{{ t('common.delete') }}</button>
              </div>
              <div class="option-values">
                <span v-for="value in option.values" :key="value.id" class="chip">
                  {{ value.value }}
                  <button type="button" :aria-label="t('common.remove_item', { item: value.value })" @click="removeOptionValue(option.id, value.id)">✕</button>
                </span>
                <form class="inline-form" @submit.prevent="addOptionValue(option.id)">
                  <input v-model="newValueDraft[option.id]" type="text" :placeholder="t('productEditor.value_placeholder')" class="mini-input">
                  <button type="submit" class="link">{{ t('productEditor.add_value') }}</button>
                </form>
              </div>
            </div>
          </div>
          <p v-else class="options-empty-hint">{{ t('productEditor.empty_options_description') }}</p>
        </section>

        <section>
          <div class="section-toolbar">
            <h2 class="section-h" style="margin:0">{{ t('productEditor.variants') }} <span class="muted">· {{ product.variants?.length ?? 0 }}</span></h2>
            <div class="toolbar-actions">
              <input v-model="variantQuery" type="text" class="mini-input" data-testid="variant-search" :placeholder="t('productEditor.variants_search_placeholder')">
              <button type="button" class="btn primary sm" data-testid="add-variant-button" @click="openCreateVariant">+ {{ t('productEditor.add_variant') }}</button>
            </div>
          </div>
          <VariantTable
            v-if="filteredVariants.length"
            :variants="filteredVariants"
            :selected="selectedVariantIds"
            :inventory-by-variant-id="inventoryByVariantId"
            @open-detail="openVariantDetail"
            @toggle-one="toggleVariantSelected"
            @toggle-all="toggleAllVariants"
            @update-price="updateVariantPrice"
            @update-compare-at="updateVariantCompareAt"
            @update-status="updateVariantStatus"
          />
          <EmptyState
            v-else
            compact
            :title="t('productEditor.empty_variants_title')"
            :description="t('productEditor.empty_variants_description')"
          >
            <template #icon><AppIcon name="collections" /></template>
          </EmptyState>
        </section>
      </div>

      <aside class="sidebar">
        <div class="rail">
          <div class="rail-sec">
            <h3 class="rail-h">{{ t('productEditor.status') }}</h3>
            <select v-model="draft.status" class="rail-select">
              <option value="draft">{{ t('productStatus.draft') }}</option>
              <option value="active">{{ t('productStatus.active') }}</option>
              <option value="archived">{{ t('productStatus.archived') }}</option>
            </select>
            <div class="kv"><span>{{ t('productEditor.storefront_visibility') }}</span><b :class="draft.status === 'active' ? 'ok' : ''">{{ draft.status === 'active' ? t('productEditor.visible') : t('productEditor.hidden') }}</b></div>
          </div>

          <div class="rail-sec">
            <h3 class="rail-h">{{ t('productEditor.publishing') }}</h3>
            <div class="kv"><span>{{ t('productEditor.created_at') }}</span><b>{{ formatDate(product.created_at) }}</b></div>
            <div class="kv"><span>{{ t('productEditor.updated_at') }}</span><b>{{ formatDate(product.updated_at) }}</b></div>
          </div>

          <div class="rail-sec">
            <h3 class="rail-h">{{ t('productEditor.organization') }}</h3>
            <label class="rail-field">
              <span>{{ t('productEditor.vendor') }}</span>
              <input v-model="draft.vendor" type="text">
            </label>
            <label class="rail-field">
              <span>{{ t('productEditor.product_type') }}</span>
              <input v-model="draft.product_type" type="text">
            </label>
          </div>

          <div class="rail-sec">
            <h3 class="rail-h">{{ t('productEditor.collections') }}</h3>
            <EntitySearchSelect
              :model-value="product.collection_ids ?? []"
              :items="collectionOptions"
              :placeholder="t('productsList.filter_collection')"
              @update:model-value="onCollectionsChange"
            />
          </div>

          <div class="rail-sec">
            <h3 class="rail-h">{{ t('productEditor.categories') }}</h3>
            <p class="rail-note">{{ t('productEditor.categories_readonly_note') }}</p>
          </div>

          <div class="rail-sec">
            <h3 class="rail-h">{{ t('productEditor.seo') }}</h3>
            <label class="rail-field">
              <span>{{ t('productEditor.seo_title') }}</span>
              <input v-model="draft.seo_title" type="text">
            </label>
            <label class="rail-field">
              <span>{{ t('productEditor.seo_description') }}</span>
              <textarea v-model="draft.seo_description" rows="2" />
            </label>
          </div>

          <div class="rail-sec">
            <h3 class="rail-h">{{ t('productEditor.metadata') }}</h3>
            <div class="tags">
              <span v-for="(tag, index) in draft.tags" :key="tag" class="chip">
                {{ tag }}
                <button type="button" :aria-label="t('common.remove_item', { item: tag })" @click="draft.tags.splice(index, 1)">✕</button>
              </span>
            </div>
            <input
              type="text"
              class="mini-input"
              :placeholder="t('productEditor.tags_placeholder')"
              @keydown.enter.prevent="addTag(($event.target as HTMLInputElement))"
            >
            <label class="rail-field" style="margin-top:var(--space-2)">
              <span>{{ t('productEditor.slug') }}</span>
              <input v-model="draft.slug" type="text" class="mono">
            </label>
          </div>
        </div>
      </aside>
    </div>

    <VariantDetailDrawer
      v-if="activeVariant"
      :open="!!activeVariant"
      :product-id="productId"
      :variant="activeVariant"
      :inventory-item="activeInventoryItem"
      :locations="locations"
      @update:open="closeVariantDetail"
      @saved="onVariantDetailSaved"
      @deleted="onVariantDeleted"
    />

    <Modal v-model:open="createVariantOpen" :title="t('productEditor.add_variant')" size="sm">
      <form class="stack" @submit.prevent="submitCreateVariant">
        <label v-for="option in product.options ?? []" :key="option.id" class="field">
          <span>{{ option.name }}</span>
          <select v-model="newVariantValues[option.id]">
            <option v-for="value in option.values" :key="value.id" :value="value.id">{{ value.value }}</option>
          </select>
        </label>
        <label class="field">
          <span>{{ t('productEditor.price') }}</span>
          <MoneyInput v-model="newVariantPrice" :currency="defaultCurrency" testid="new-variant-price-input" />
        </label>
        <label class="field">
          <span>SKU</span>
          <input v-model="newVariantSku" type="text" data-testid="new-variant-sku-input">
        </label>
        <button type="submit" class="btn primary" data-testid="submit-create-variant">{{ t('common.create') }}</button>
      </form>
    </Modal>

    <Modal v-model:open="confirmDelete" :title="t('productsList.delete_confirm_title')" size="sm">
      <p>{{ t('productsList.delete_confirm_body') }}</p>
      <template #actions>
        <button type="button" class="btn" @click="confirmDelete = false">{{ t('common.cancel') }}</button>
        <button type="button" class="btn danger" @click="deleteProduct">{{ t('common.delete') }}</button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'
import type { InventoryItem, Location, Product, ProductVariant } from '@obscurify/types'

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const api = useApi()

const productId = route.params.id as string

const product = ref<Product | null>(null)
const error = ref<string | null>(null)
const uploadingMedia = ref(false)
const menuOpen = ref(false)
const confirmDelete = ref(false)
const kebabRoot = ref<HTMLElement | null>(null)

const collections = ref<Array<{ id: string; title: string }>>([])
const inventoryItems = ref<InventoryItem[]>([])
const locations = ref<Location[]>([])

const draft = reactive({
  title: '',
  description: '' as string | null,
  vendor: '' as string | null,
  product_type: '' as string | null,
  tags: [] as string[],
  status: 'draft',
  seo_title: '' as string | null,
  seo_description: '' as string | null,
  slug: '',
})

function syncDraftFromProduct(p: Product) {
  const next = productDraftFrom(p)
  draft.title = next.title
  draft.description = next.description
  draft.vendor = next.vendor
  draft.product_type = next.product_type
  draft.tags = next.tags
  draft.status = next.status
  draft.seo_title = next.seo_title
  draft.seo_description = next.seo_description
  draft.slug = next.slug
}

const isDirty = computed(() => {
  const p = product.value
  if (!p) return false
  return isProductDraftDirty(draft, productDraftFrom(p))
})

type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error'
const saving = ref(false)
const justSaved = ref(false)
const saveFailed = ref(false)

const saveState = computed<SaveState>(() => {
  if (saveFailed.value) return 'error'
  if (saving.value) return 'saving'
  if (isDirty.value) return 'dirty'
  if (justSaved.value) return 'saved'
  return 'idle'
})

async function load() {
  // Every immediate action (add option, create variant, upload image,
  // adjust inventory, toggle a collection) calls load() to refresh the
  // page with the server's new state — but load() unconditionally
  // resyncing the draft would silently discard an in-progress, unsaved
  // title/description/vendor/etc. edit the moment the merchant does any
  // of those unrelated actions. Only resync the draft when it's already
  // clean; a dirty draft's own fields are left alone (product.value
  // itself still refreshes normally, so variants/media/options stay
  // current either way).
  const wasDirty = isDirty.value

  const [productResponse, collectionsResponse, locationsResponse] = await Promise.all([
    api.products.get(productId),
    api.collections.list(),
    api.locations.list(),
  ])

  product.value = productResponse.data
  if (!wasDirty) syncDraftFromProduct(productResponse.data)
  collections.value = collectionsResponse.data.map(c => ({ id: c.id, title: c.title }))
  locations.value = locationsResponse.data

  const variantIds = (productResponse.data.variants ?? []).map(v => v.id)
  if (variantIds.length) {
    const inventoryResponse = await api.inventory.list({ productVariantId: variantIds })
    inventoryItems.value = inventoryResponse.data
  } else {
    inventoryItems.value = []
  }
}

async function withError<T>(action: () => Promise<T>): Promise<T | undefined> {
  try {
    error.value = null
    return await action()
  } catch (err) {
    error.value = err instanceof ApiClientError ? err.message : t('common.something_went_wrong')
    return undefined
  }
}

async function save() {
  if (!isDirty.value || saving.value) return
  saving.value = true
  saveFailed.value = false

  const result = await withError(() => api.products.update(productId, {
    title: draft.title,
    description: draft.description ?? undefined,
    vendor: draft.vendor ?? undefined,
    product_type: draft.product_type ?? undefined,
    tags: draft.tags,
    status: draft.status,
    seo_title: draft.seo_title ?? undefined,
    seo_description: draft.seo_description ?? undefined,
    slug: draft.slug,
  }))

  saving.value = false

  if (result) {
    product.value = result.data
    syncDraftFromProduct(result.data)
    justSaved.value = true
    setTimeout(() => { justSaved.value = false }, 2000)
  } else {
    saveFailed.value = true
  }
}

function handleShortcut(event: KeyboardEvent) {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    save()
  }
}

function handleClickOutsideKebab(event: MouseEvent) {
  if (menuOpen.value && kebabRoot.value && !kebabRoot.value.contains(event.target as Node)) {
    menuOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleShortcut)
  document.addEventListener('mousedown', handleClickOutsideKebab)
})
onUnmounted(() => {
  document.removeEventListener('keydown', handleShortcut)
  document.removeEventListener('mousedown', handleClickOutsideKebab)
})

function formatDate(value: string) {
  return new Intl.DateTimeFormat(locale.value === 'ru' ? 'ru-RU' : locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

// ---- Media ----
async function onUploadMedia(file: File) {
  if (isMediaFileTooLarge(file)) {
    error.value = t('productEditor.media_too_large', { max: '25 MB' })
    return
  }

  uploadingMedia.value = true
  await withError(() => api.media.attachToProduct(productId, file))
  uploadingMedia.value = false
  await load()
}

async function onRemoveMedia(mediaId: string) {
  await withError(() => api.media.remove(mediaId))
  await load()
}

async function onUpdateMediaAlt(mediaId: string, alt: string) {
  await withError(() => api.media.update(mediaId, { alt }))
  await load()
}

async function onReorderMedia(mediaId: string, direction: -1 | 1) {
  const items = product.value?.media ?? []
  const index = items.findIndex(m => m.id === mediaId)
  const swapWith = items[index + direction]
  if (!swapWith) return

  const current = items[index]!
  await Promise.all([
    withError(() => api.media.update(current.id, { position: swapWith.position })),
    withError(() => api.media.update(swapWith.id, { position: current.position })),
  ])
  await load()
}

// ---- Options ----
const newOptionName = ref('')
const newValueDraft = reactive<Record<string, string>>({})

async function addOption() {
  if (!newOptionName.value.trim()) return
  await withError(() => api.productOptions.create(productId, { name: newOptionName.value.trim() }))
  newOptionName.value = ''
  await load()
}

async function removeOption(optionId: string) {
  await withError(() => api.productOptions.remove(productId, optionId))
  await load()
}

async function addOptionValue(optionId: string) {
  const value = newValueDraft[optionId]?.trim()
  if (!value) return
  await withError(() => api.productOptions.values.create(productId, optionId, { value }))
  newValueDraft[optionId] = ''
  await load()
}

async function removeOptionValue(optionId: string, valueId: string) {
  await withError(() => api.productOptions.values.remove(productId, optionId, valueId))
  await load()
}

function addTag(input: HTMLInputElement) {
  const value = input.value.trim()
  if (value && !draft.tags.includes(value)) draft.tags.push(value)
  input.value = ''
}

// ---- Collections ----
const collectionOptions = computed(() => collections.value.map(c => ({ id: c.id, label: c.title })))

async function onCollectionsChange(next: string[]) {
  const current = product.value?.collection_ids ?? []
  const added = next.filter(id => !current.includes(id))
  const removed = current.filter(id => !next.includes(id))

  await Promise.all([
    ...added.map(id => withError(() => api.collections.addProduct(id, productId))),
    ...removed.map(id => withError(() => api.collections.removeProduct(id, productId))),
  ])
  await load()
}

// ---- Variants ----
const variantQuery = ref('')
const selectedVariantIds = ref<string[]>([])
const activeVariantId = ref<string | null>(null)

const filteredVariants = computed(() => {
  const variants = product.value?.variants ?? []
  const q = variantQuery.value.trim().toLowerCase()
  if (!q) return variants
  return variants.filter(v => v.sku?.toLowerCase().includes(q) || v.title.toLowerCase().includes(q))
})

const activeVariant = computed<ProductVariant | null>(() => {
  if (!activeVariantId.value) return null
  return product.value?.variants?.find(v => v.id === activeVariantId.value) ?? null
})

const activeInventoryItem = computed<InventoryItem | null>(() => {
  if (!activeVariantId.value) return null
  return inventoryItems.value.find(i => i.product_variant_id === activeVariantId.value) ?? null
})

const inventoryByVariantId = computed<Record<string, number>>(() => {
  const map: Record<string, number> = {}
  for (const item of inventoryItems.value) {
    map[item.product_variant_id] = (item.levels ?? []).reduce((sum, l) => sum + l.available, 0)
  }
  return map
})

function toggleVariantSelected(id: string, checked: boolean) {
  selectedVariantIds.value = checked
    ? [...selectedVariantIds.value, id]
    : selectedVariantIds.value.filter(v => v !== id)
}

function toggleAllVariants(checked: boolean) {
  selectedVariantIds.value = checked ? filteredVariants.value.map(v => v.id) : []
}

async function updateVariantPrice(variantId: string, amount: number) {
  await withError(() => api.productVariants.update(productId, variantId, { price_amount: amount }))
  await load()
}

async function updateVariantCompareAt(variantId: string, amount: number | null) {
  await withError(() => api.productVariants.update(productId, variantId, { compare_at_price_amount: amount }))
  await load()
}

async function updateVariantStatus(variantId: string, status: string) {
  await withError(() => api.productVariants.update(productId, variantId, { status }))
  await load()
}

function openVariantDetail(id: string) {
  activeVariantId.value = id
}

function closeVariantDetail() {
  activeVariantId.value = null
}

async function onVariantDetailSaved() {
  await load()
}

async function onVariantDeleted() {
  activeVariantId.value = null
  await load()
}

const defaultCurrency = computed(() => product.value?.variants?.[0]?.currency ?? 'RUB')
const createVariantOpen = ref(false)
const newVariantValues = reactive<Record<string, string>>({})
const newVariantPrice = ref<number | null>(null)
const newVariantSku = ref('')

function openCreateVariant() {
  createVariantOpen.value = true
  newVariantPrice.value = null
  newVariantSku.value = ''
}

async function submitCreateVariant() {
  if (newVariantPrice.value === null) return
  const optionValueIds = Object.values(newVariantValues).filter(Boolean)

  const result = await withError(() => api.productVariants.create(productId, {
    price_amount: newVariantPrice.value!,
    sku: newVariantSku.value || undefined,
    option_value_ids: optionValueIds.length ? optionValueIds : undefined,
  }))

  if (result) {
    createVariantOpen.value = false
    await load()
  }
}

async function deleteProduct() {
  const result = await withError(() => api.products.remove(productId))
  if (result !== undefined) {
    confirmDelete.value = false
    router.push('/products')
  }
}

await load()
</script>

<style scoped>
.loading-shell {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  max-width: 640px;
}

.editor {
  --sidebar-rail-width: 264px;
}

.header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  padding-bottom: var(--space-3);
  border-bottom: 1px solid var(--color-border);
  margin-bottom: var(--space-4);
}

.title-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-top: var(--space-1);
}

.title-field {
  border: none;
  border-bottom: 1px solid transparent;
  background: transparent;
  font-size: var(--text-2xl);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  padding: 2px 0;
  min-width: 200px;
}
.title-field:hover,
.title-field:focus {
  border-bottom-color: var(--color-border-strong);
}
.title-field:focus-visible { outline: none; }

.header-actions {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-shrink: 0;
}

.icon-btn {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
}
.icon-btn:hover { background: var(--color-surface-muted); }

.kebab-wrap { position: relative; }

.kebab-menu {
  position: absolute;
  top: calc(100% + var(--space-1));
  right: 0;
  min-width: 180px;
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  padding: var(--space-1);
  z-index: 20;
}
.kebab-menu button {
  display: block;
  width: 100%;
  text-align: left;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm);
  color: var(--color-danger);
}
.kebab-menu button:hover { background: var(--color-danger-bg); }

.error-banner {
  color: var(--color-danger);
  background: var(--color-danger-bg);
  border: 1px solid var(--color-danger-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  font-size: var(--text-sm);
  margin-bottom: var(--space-4);
}

.body {
  display: grid;
  grid-template-columns: 1fr var(--sidebar-rail-width);
  gap: var(--space-6);
  /* Not "start": with align-items:start the .sidebar grid cell shrinks
     to the rail's own height instead of matching the taller .main
     column, leaving position:sticky zero room to travel — the rail's
     container ends past the fold at the same scroll offset the rail
     itself does, so it never stays pinned while scrolling through a
     longer product. Stretching the cell to the row height fixes that
     without changing either column's width. */
  align-items: stretch;
}

@media (max-width: 900px) {
  .body { grid-template-columns: 1fr; }
}

.main {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-width: 0;
}

.desc-field {
  border: none;
  border-bottom: 1px solid transparent;
  background: transparent;
  font-size: var(--text-base);
  color: var(--color-text-muted);
  resize: vertical;
  padding: 2px 0;
  line-height: var(--leading-normal, 1.5);
}
.desc-field:hover,
.desc-field:focus { border-bottom-color: var(--color-border-strong); }
.desc-field:focus-visible { outline: none; }

.section-h {
  font-size: var(--text-xs);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-subtle);
  font-weight: var(--font-weight-semibold);
  margin: 0 0 var(--space-2);
}

.section-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
}

.toolbar-actions {
  display: flex;
  gap: var(--space-2);
}

.muted { color: var(--color-text-subtle); font-weight: normal; text-transform: none; }

.options-section .section-toolbar {
  margin-bottom: var(--space-3);
}

.options {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.options-empty-hint {
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-text-subtle);
}

.option {
  /* On very wide viewports an option's name/Delete would otherwise
     stretch edge-to-edge with nothing but dead whitespace between them
     (the row has no reason to track the full section width the way a
     section-header toolbar does) — capped so the name, its Delete
     action, and its value chips stay visually grouped. */
  max-width: 640px;
}

.option-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  font-size: var(--text-sm);
  margin-bottom: var(--space-1);
}

.option-values {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1);
  align-items: center;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: var(--text-xs);
  background: var(--color-surface-muted);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: 3px var(--space-1) 3px var(--space-2);
  color: var(--color-text-muted);
}
.chip button {
  width: 14px; height: 14px; display: flex; align-items: center; justify-content: center;
  border-radius: 50%; color: var(--color-text-subtle); font-size: 9px;
}
.chip button:hover { background: var(--color-surface); color: var(--color-text); }

.inline-form {
  display: flex;
  gap: var(--space-2);
  align-items: center;
}

.mini-input {
  font-size: var(--text-sm);
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  /* Placeholders like "Значение (напр. Чёрный)" clip at the browser's
     unstyled default input width — Russian placeholder/label text runs
     long (docs/design/DESIGN_SYSTEM.md's Russian text-length rule), so
     this needs a real minimum, not an assumed-adequate default. */
  min-width: 220px;
}

.link {
  background: none;
  border: none;
  padding: 0;
  font-size: var(--text-sm);
  font-weight: var(--font-weight-medium);
  color: var(--color-accent);
}
.link:hover { text-decoration: underline; }
.link.danger { color: var(--color-danger); }

/* Sidebar rail */
.sidebar { min-width: 0; }

.rail {
  position: sticky;
  top: calc(var(--topbar-height) + var(--space-4));
  background: var(--color-surface-muted);
  border-radius: var(--radius-lg);
  padding: 0 var(--space-4);
}

.rail-sec {
  padding: var(--space-4) 0;
  border-bottom: 1px solid var(--color-border);
}
.rail-sec:first-child { padding-top: var(--space-3); }
.rail-sec:last-child { padding-bottom: var(--space-3); border-bottom: none; }

.rail-h {
  margin: 0 0 var(--space-3);
  font-size: 10.5px;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--color-text-subtle);
  font-weight: var(--font-weight-semibold);
}

.rail-select {
  width: 100%;
  padding: var(--space-2);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--text-sm);
  margin-bottom: var(--space-2);
}

.rail-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: var(--space-2);
}
.rail-field span {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}
.rail-field input,
.rail-field textarea {
  padding: var(--space-2);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--text-sm);
  font-family: inherit;
}

.kv {
  display: flex;
  justify-content: space-between;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  padding: 3px 0;
}
.kv b { color: var(--color-text); font-weight: var(--font-weight-medium); }
.kv b.ok { color: var(--color-success); }

.rail-note {
  font-size: 11px;
  color: var(--color-text-subtle);
  font-style: italic;
  margin: 0;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1);
  margin-bottom: var(--space-2);
}

/* Shared button/field primitives used by modals */
.stack { display: flex; flex-direction: column; gap: var(--space-3); }
.field { display: flex; flex-direction: column; gap: 4px; font-size: var(--text-sm); }
.field span { color: var(--color-text-muted); font-size: var(--text-xs); }
.field select, .field input { padding: var(--space-2); border: 1px solid var(--color-border-strong); border-radius: var(--radius-sm); background: var(--color-surface); color: var(--color-text); }

.btn {
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm);
  font-weight: var(--font-weight-medium);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface);
  color: var(--color-text);
}
.btn.sm { padding: var(--space-1) var(--space-3); font-size: var(--text-xs); }
.btn.primary { background: var(--color-accent); border-color: var(--color-accent); color: var(--color-text-on-accent); }
.btn.danger { background: var(--color-danger); border-color: var(--color-danger); color: var(--color-text-on-accent); }

.mono { font-family: ui-monospace, monospace; }
</style>
