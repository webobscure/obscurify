<template>
  <Drawer :open="open" :title="variant.title || t('productEditor.untitled')" :eyebrow="t('productEditor.variant_details')" testid="variant-drawer" @update:open="emit('update:open', $event)">
    <p v-if="error" class="error-text" data-testid="variant-drawer-error">{{ error }}</p>

    <div class="group">
      <h3 class="h">{{ t('common.edit') }}</h3>
      <label class="field">
        <span>SKU</span>
        <input v-model="form.sku" type="text" data-testid="variant-sku-input">
      </label>
      <label class="field">
        <span>{{ t('productEditor.barcode') }}</span>
        <input v-model="form.barcode" type="text" data-testid="variant-barcode-input">
      </label>
    </div>

    <div class="group">
      <h3 class="h">{{ t('productEditor.price') }}</h3>
      <div class="row">
        <label class="field"><span>{{ t('productEditor.price') }}</span><MoneyInput v-model="form.price_amount" :currency="variant.currency" testid="variant-price-input" /></label>
        <label class="field"><span>{{ t('productEditor.compare_at_price') }}</span><MoneyInput v-model="form.compare_at_price_amount" :currency="variant.currency" testid="variant-compare-at-input" /></label>
      </div>
      <label class="field"><span>{{ t('productEditor.cost') }}</span><MoneyInput v-model="form.cost_amount" :currency="variant.currency" testid="variant-cost-input" /></label>
      <p v-if="margin !== null" class="margin" data-testid="variant-margin">{{ t('productEditor.margin') }}: {{ margin }}%</p>
    </div>

    <div class="group">
      <h3 class="h">{{ t('productEditor.physical') }}</h3>
      <div class="row">
        <label class="field"><span>{{ t('productEditor.weight') }}</span><input v-model="form.weight" type="text" inputmode="decimal"></label>
        <label class="field"><span>{{ t('productEditor.length') }}</span><input v-model="form.length" type="text" inputmode="decimal"></label>
      </div>
      <div class="row">
        <label class="field"><span>{{ t('productEditor.width') }}</span><input v-model="form.width" type="text" inputmode="decimal"></label>
        <label class="field"><span>{{ t('productEditor.height') }}</span><input v-model="form.height" type="text" inputmode="decimal"></label>
      </div>
    </div>

    <div class="group">
      <h3 class="h">{{ t('productEditor.media') }}</h3>
      <MediaGrid :items="variant.media ?? []" :uploading="uploadingMedia" @upload="onUpload" @remove="onRemoveMedia" @reorder="onReorderMedia" @update-alt="onUpdateAlt" />
    </div>

    <div class="group">
      <h3 class="h">{{ t('productEditor.inventory_by_location') }}</h3>
      <div v-if="inventoryItem?.levels?.length" class="stack">
        <div v-for="level in inventoryItem.levels" :key="level.id" class="level-row">
          <span>{{ locationName(level.location_id) }}</span>
          <b>{{ level.available }}</b>
        </div>
      </div>
      <p v-else class="muted">{{ t('common.no_matching_pages') }}</p>

      <form class="adjust-form" @submit.prevent="submitAdjust">
        <select v-model="adjustLocationId" data-testid="inventory-adjust-location">
          <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
        </select>
        <input v-model.number="adjustDelta" type="number" placeholder="±0" data-testid="inventory-adjust-delta">
        <button type="submit" class="btn sm" data-testid="inventory-adjust-submit">{{ t('common.save') }}</button>
      </form>
    </div>

    <div class="group actions">
      <button type="button" class="btn danger" data-testid="variant-delete-button" @click="remove">{{ t('common.delete') }}</button>
      <button type="button" class="btn primary" data-testid="variant-save-button" @click="submit">{{ t('common.save') }}</button>
    </div>
  </Drawer>
</template>

<script setup lang="ts">
import { ApiClientError } from '@obscurify/api-client'
import type { InventoryItem, Location, ProductVariant } from '@obscurify/types'

const props = defineProps<{
  open: boolean
  productId: string
  variant: ProductVariant
  inventoryItem: InventoryItem | null
  locations: Location[]
}>()

const emit = defineEmits<{
  'update:open': [boolean]
  saved: []
  deleted: []
}>()

const { t } = useI18n()
const api = useApi()
const error = ref<string | null>(null)
const uploadingMedia = ref(false)
const adjustLocationId = ref(props.locations[0]?.id ?? '')
const adjustDelta = ref<number | null>(null)

const form = reactive({
  sku: props.variant.sku ?? '',
  barcode: props.variant.barcode ?? '',
  price_amount: props.variant.price_amount as number | null,
  compare_at_price_amount: props.variant.compare_at_price_amount,
  cost_amount: props.variant.cost_amount,
  weight: props.variant.weight ?? '',
  length: props.variant.length ?? '',
  width: props.variant.width ?? '',
  height: props.variant.height ?? '',
})

watch(() => props.variant.id, () => {
  form.sku = props.variant.sku ?? ''
  form.barcode = props.variant.barcode ?? ''
  form.price_amount = props.variant.price_amount
  form.compare_at_price_amount = props.variant.compare_at_price_amount
  form.cost_amount = props.variant.cost_amount
  form.weight = props.variant.weight ?? ''
  form.length = props.variant.length ?? ''
  form.width = props.variant.width ?? ''
  form.height = props.variant.height ?? ''
})

const margin = computed(() => calculateMarginPercent(form.price_amount, form.cost_amount))

function locationName(id: string) {
  return props.locations.find(l => l.id === id)?.name ?? id
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

async function submit() {
  const result = await withError(() => api.productVariants.update(props.productId, props.variant.id, {
    sku: form.sku || null,
    barcode: form.barcode || null,
    price_amount: form.price_amount ?? undefined,
    compare_at_price_amount: form.compare_at_price_amount,
    cost_amount: form.cost_amount,
    weight: form.weight ? Number(form.weight) : null,
    length: form.length ? Number(form.length) : null,
    width: form.width ? Number(form.width) : null,
    height: form.height ? Number(form.height) : null,
  }))
  if (result) emit('saved')
}

async function remove() {
  const result = await withError(() => api.productVariants.remove(props.productId, props.variant.id))
  if (result !== undefined) emit('deleted')
}

async function onUpload(file: File) {
  uploadingMedia.value = true
  await withError(() => api.media.attachToVariant(props.productId, props.variant.id, file))
  uploadingMedia.value = false
  emit('saved')
}

async function onRemoveMedia(mediaId: string) {
  await withError(() => api.media.remove(mediaId))
  emit('saved')
}

async function onUpdateAlt(mediaId: string, alt: string) {
  await withError(() => api.media.update(mediaId, { alt }))
  emit('saved')
}

async function onReorderMedia(mediaId: string, direction: -1 | 1) {
  const items = props.variant.media ?? []
  const index = items.findIndex(m => m.id === mediaId)
  const swapWith = items[index + direction]
  if (!swapWith) return
  const current = items[index]!
  await Promise.all([
    withError(() => api.media.update(current.id, { position: swapWith.position })),
    withError(() => api.media.update(swapWith.id, { position: current.position })),
  ])
  emit('saved')
}

async function submitAdjust() {
  if (!props.inventoryItem || !adjustLocationId.value || !adjustDelta.value) return
  const result = await withError(() => api.inventory.adjust(props.inventoryItem!.id, {
    location_id: adjustLocationId.value,
    quantity_delta: adjustDelta.value!,
    reason: 'manual_adjustment',
  }))
  if (result) {
    adjustDelta.value = null
    emit('saved')
  }
}
</script>

<style scoped>
.error-text {
  color: var(--color-danger);
  font-size: var(--text-sm);
  margin: 0 0 var(--space-3);
}

.group {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.h {
  margin: 0;
  font-size: 10.5px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-subtle);
  font-weight: var(--font-weight-semibold);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: var(--text-sm);
  flex: 1;
  /* Flex items default to min-width: auto, so a child input's intrinsic
     rendering width wins over the flex basis and the second field in
     each .row (Длина/Высота/Сравнительная) overflows the 360px drawer
     instead of sharing space with its sibling — this is what actually
     lets the field shrink to fit. */
  min-width: 0;
}
.field span { font-size: var(--text-xs); color: var(--color-text-muted); }
.field input {
  width: 100%;
  box-sizing: border-box;
  padding: var(--space-2);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
}

.row { display: flex; gap: var(--space-2); }

.margin {
  margin: 0;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.level-row {
  display: flex;
  justify-content: space-between;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}
.level-row b { color: var(--color-text); }

.muted { color: var(--color-text-subtle); font-size: var(--text-sm); margin: 0; }

.adjust-form {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-2);
}
.adjust-form select,
.adjust-form input {
  padding: var(--space-2);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--text-sm);
}
.adjust-form input { width: 80px; }

.actions {
  flex-direction: row;
  justify-content: space-between;
  padding-top: var(--space-3);
  border-top: 1px solid var(--color-border);
}

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
.btn.danger { background: transparent; border-color: var(--color-danger); color: var(--color-danger); }
</style>
