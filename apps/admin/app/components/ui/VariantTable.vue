<template>
  <div class="variant-table-wrap">
    <table class="variant-table">
      <thead>
        <tr>
          <th class="check-col">
            <input
              type="checkbox"
              :aria-label="t('common.select_all')"
              :checked="allSelected"
              :indeterminate="someSelected && !allSelected"
              @change="emit('toggleAll', ($event.target as HTMLInputElement).checked)"
            >
          </th>
          <th>{{ t('productEditor.variant') }}</th>
          <th>SKU</th>
          <th class="num">{{ t('productEditor.price') }}</th>
          <th class="num">{{ t('productEditor.compare_at_price') }}</th>
          <th class="num">{{ t('productEditor.available') }}</th>
          <th>{{ t('productEditor.status') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="variant in variants"
          :key="variant.id"
          class="row"
          data-testid="variant-row"
          :data-variant-id="variant.id"
          :data-sku="variant.sku"
          tabindex="0"
          :aria-label="t('productEditor.variant_details')"
          @click="openDetail($event, variant.id)"
          @keydown.enter="emit('openDetail', variant.id)"
          @keydown.space.prevent="emit('openDetail', variant.id)"
        >
          <td class="check-col" @click.stop>
            <input
              type="checkbox"
              :aria-label="t('common.select_row', { row: variant.title || variant.sku || variant.id })"
              :checked="selected.includes(variant.id)"
              @change="emit('toggleOne', variant.id, ($event.target as HTMLInputElement).checked)"
            >
          </td>
          <td data-testid="variant-row-name">{{ variant.title || '—' }}</td>
          <td class="mono">{{ variant.sku || '—' }}</td>
          <td class="num" @click.stop>
            <MoneyInput
              :model-value="variant.price_amount"
              :currency="variant.currency"
              class="cell-input"
              :testid="`variant-row-price-${variant.id}`"
              @update:model-value="(v) => v !== null && emit('updatePrice', variant.id, v)"
            />
          </td>
          <td class="num" @click.stop>
            <MoneyInput
              :model-value="variant.compare_at_price_amount"
              :currency="variant.currency"
              class="cell-input"
              :testid="`variant-row-compare-at-${variant.id}`"
              @update:model-value="(v) => emit('updateCompareAt', variant.id, v)"
            />
          </td>
          <td class="num" :class="availabilityClass(variant.id)">{{ inventoryByVariantId[variant.id] ?? '—' }}</td>
          <td @click.stop>
            <select
              class="cell-select"
              :value="variant.status"
              :aria-label="t('productEditor.status')"
              @change="emit('updateStatus', variant.id, ($event.target as HTMLSelectElement).value)"
            >
              <option value="active">{{ t('productStatus.active') }}</option>
              <option value="draft">{{ t('productStatus.draft') }}</option>
              <option value="archived">{{ t('productStatus.archived') }}</option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import type { ProductVariant } from '@obscurify/types'

const props = defineProps<{
  variants: ProductVariant[]
  selected: string[]
  inventoryByVariantId: Record<string, number>
  lowStockThreshold?: number
}>()

const emit = defineEmits<{
  openDetail: [string]
  toggleOne: [string, boolean]
  toggleAll: [boolean]
  updatePrice: [string, number]
  updateCompareAt: [string, number | null]
  updateStatus: [string, string]
}>()

const { t } = useI18n()

const allSelected = computed(() => props.variants.length > 0 && props.selected.length === props.variants.length)
const someSelected = computed(() => props.selected.length > 0)

/**
 * Clicking a plain <td> never moves focus to its parent <tr> (only the
 * exact clicked element can become document.activeElement) — without
 * this, a mouse click opens the drawer but leaves focus wherever it was
 * before, so the drawer's own focus-return-to-trigger (useDismissable)
 * has nothing correct to return to. Keyboard activation (Enter/Space)
 * doesn't need this: the row is already focused by the time either
 * fires.
 */
function openDetail(event: MouseEvent, variantId: string) {
  ;(event.currentTarget as HTMLElement).focus()
  emit('openDetail', variantId)
}

function availabilityClass(variantId: string) {
  const qty = props.inventoryByVariantId[variantId]
  if (qty === undefined) return ''
  const threshold = props.lowStockThreshold ?? 5
  if (qty <= 0) return 'out'
  if (qty <= threshold) return 'low'
  return 'ok'
}
</script>

<style scoped>
.variant-table-wrap {
  overflow-x: auto;
}

.variant-table {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

.variant-table thead th {
  text-align: left;
  font-weight: var(--font-weight-medium);
  font-size: var(--text-xs);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-subtle);
  padding: var(--space-2) var(--space-2);
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface-muted);
  position: sticky;
  top: 0;
}

.variant-table td {
  padding: var(--space-1) var(--space-2);
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text);
  /* SKUs are identifiers, not prose — mid-string wrapping (the browser
     default) hurts scannability and makes them harder to copy. The
     table wrapper already has overflow-x: auto for anything genuinely
     too wide. */
  white-space: nowrap;
}

.variant-table td.num,
.variant-table th.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.check-col {
  width: 32px;
}

.row {
  cursor: pointer;
}
.row:hover {
  background: var(--color-surface-muted);
}
.row:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: -2px;
  background: var(--color-surface-muted);
}

.cell-input :deep(.field) {
  padding: var(--space-1) var(--space-2);
  font-size: var(--text-sm);
}
.cell-input {
  border-color: transparent;
  background: transparent;
}
.row:hover .cell-input,
.cell-input:focus-within {
  border-color: var(--color-border-strong);
  background: var(--color-surface);
}

.cell-select {
  border: 1px solid transparent;
  background: transparent;
  font-size: var(--text-sm);
  color: var(--color-text);
  border-radius: var(--radius-sm);
  padding: var(--space-1) var(--space-2);
  /* Without this, a native <select> can get squeezed narrower than its
     own longest option text ("Архивирован") once the SKU column's
     white-space: nowrap widens the table — better to grow the table
     (the wrapper already scrolls) than clip the status label. */
  min-width: 110px;
}
.row:hover .cell-select,
.cell-select:focus-visible {
  border-color: var(--color-border-strong);
  background: var(--color-surface);
}

.num.ok { color: var(--color-success); font-weight: var(--font-weight-medium); }
.num.low { color: var(--color-warning); font-weight: var(--font-weight-medium); }
.num.out { color: var(--color-danger); font-weight: var(--font-weight-medium); }
</style>
