<template>
  <div class="table-wrap">
    <div v-if="selectable && selected.length" class="bulk-bar">
      <span class="bulk-count">{{ t('common.bulk_selected_count', { count: selected.length }) }}</span>
      <div class="bulk-actions"><slot name="bulk-actions" :selected="selected" /></div>
    </div>

    <div class="scroll">
      <table class="data-table" :class="density">
        <thead>
          <tr>
            <th v-if="selectable" class="check-col">
              <Checkbox :model-value="allSelected" :indeterminate="someSelected && !allSelected" :aria-label="t('common.select_all')" @update:model-value="toggleAll" />
            </th>
            <th
              v-for="col in columns"
              :key="col.key"
              :class="col.align === 'right' ? 'num' : ''"
              :aria-sort="col.sortable ? ariaSortFor(col.key) : undefined"
            >
              <button v-if="col.sortable" type="button" class="sort-btn" @click="toggleSort(col.key)">
                {{ col.label }}
                <AppIcon name="chevron" size="sm" class="sort-icon" :class="{ active: sortKey === col.key, desc: sortKey === col.key && sortDir === 'desc' }" />
              </button>
              <template v-else>{{ col.label }}</template>
            </th>
            <th v-if="$slots['row-actions']" class="actions-col" />
          </tr>
        </thead>

        <tbody v-if="loading">
          <tr v-for="n in loadingRows" :key="n">
            <td v-if="selectable"><Skeleton variant="text" width="18px" /></td>
            <td v-for="col in columns" :key="col.key"><Skeleton variant="text" /></td>
            <td v-if="$slots['row-actions']"><Skeleton variant="text" width="24px" /></td>
          </tr>
        </tbody>

        <tbody v-else-if="error">
          <tr>
            <td :colspan="totalCols">
              <Alert variant="danger">
                {{ error }}
                <button type="button" class="retry" @click="emit('retry')">{{ t('common.retry') }}</button>
              </Alert>
            </td>
          </tr>
        </tbody>

        <tbody v-else-if="!rows.length">
          <tr>
            <td :colspan="totalCols">
              <EmptyState :title="emptyTitle" :description="emptyDescription">
                <template v-if="$slots['empty-action']" #action><slot name="empty-action" /></template>
              </EmptyState>
            </td>
          </tr>
        </tbody>

        <tbody v-else>
          <tr
            v-for="row in rows"
            :key="rowKey(row)"
            class="row"
            :class="{ selected: selectable && selected.includes(rowKey(row)) }"
          >
            <td v-if="selectable" class="check-col" @click.stop>
              <Checkbox
                :model-value="selected.includes(rowKey(row))"
                :aria-label="t('common.select_row', { row: String(rowLabel ? rowLabel(row) : rowKey(row)) })"
                @update:model-value="(checked) => toggleOne(rowKey(row), checked)"
              />
            </td>
            <td v-for="col in columns" :key="col.key" :class="col.align === 'right' ? 'num' : ''">
              <slot :name="`cell-${col.key}`" :row="row" :value="(row as Record<string, unknown>)[col.key]">
                {{ (row as Record<string, unknown>)[col.key] }}
              </slot>
            </td>
            <td v-if="$slots['row-actions']" class="actions-col" @click.stop>
              <slot name="row-actions" :row="row" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * The single reusable table implementation — see docs/design/
 * ADMIN_DESIGN_SYSTEM.md §Data Table. Consolidates the pattern
 * VariantTable.vue proved out on the Product Editor (compact density,
 * sticky header, tabular-nums right-aligned numeric columns, mandatory
 * horizontal scroll wrapper) into a generic, column-config-driven
 * component every other list page can adopt during migration.
 */
export interface DataTableColumn {
  key: string
  label: string
  sortable?: boolean
  align?: 'left' | 'right'
}

const props = withDefaults(defineProps<{
  columns: DataTableColumn[]
  rows: unknown[]
  rowKey: (row: unknown) => string
  rowLabel?: (row: unknown) => string
  density?: 'compact' | 'comfortable'
  selectable?: boolean
  selected?: string[]
  sortKey?: string | null
  sortDir?: 'asc' | 'desc' | null
  loading?: boolean
  loadingRows?: number
  error?: string | null
  emptyTitle?: string
  emptyDescription?: string
}>(), {
  density: 'compact',
  selectable: false,
  selected: () => [],
  sortKey: null,
  sortDir: null,
  loading: false,
  loadingRows: 5,
  error: null,
  emptyTitle: '',
  emptyDescription: '',
})

const emit = defineEmits<{
  'update:selected': [string[]]
  sort: [{ key: string; dir: 'asc' | 'desc' | null }]
  retry: []
}>()

const { t } = useI18n()

const totalCols = computed(() => props.columns.length + (props.selectable ? 1 : 0) + (1))

const allSelected = computed(() => props.rows.length > 0 && props.selected.length === props.rows.length)
const someSelected = computed(() => props.selected.length > 0)

function toggleAll(checked: boolean) {
  emit('update:selected', checked ? props.rows.map(props.rowKey) : [])
}

function toggleOne(key: string, checked: boolean) {
  emit('update:selected', checked ? [...props.selected, key] : props.selected.filter(k => k !== key))
}

function ariaSortFor(key: string): 'ascending' | 'descending' | 'none' {
  if (props.sortKey !== key || !props.sortDir) return 'none'
  return props.sortDir === 'asc' ? 'ascending' : 'descending'
}

function toggleSort(key: string) {
  if (props.sortKey !== key) {
    emit('sort', { key, dir: 'asc' })
  } else if (props.sortDir === 'asc') {
    emit('sort', { key, dir: 'desc' })
  } else {
    emit('sort', { key, dir: null })
  }
}
</script>

<style scoped>
.table-wrap { display: flex; flex-direction: column; }

.bulk-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  margin-bottom: var(--space-2);
  background: var(--color-accent-bg);
  border-radius: var(--radius-md);
}

.bulk-count { font-size: var(--text-sm); font-weight: var(--font-weight-medium); color: var(--color-accent); }
.bulk-actions { display: flex; gap: var(--space-2); }

.scroll { overflow-x: auto; }

.data-table {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
}

.data-table.compact { font-size: var(--text-sm); }
.data-table.comfortable { font-size: var(--text-base); }

.data-table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  text-align: left;
  font-weight: var(--font-weight-medium);
  font-size: var(--text-xs);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-subtle);
  background: var(--color-surface-muted);
  border-bottom: var(--border-width) solid var(--color-border);
}

.data-table.compact thead th { padding: var(--space-2) var(--space-3); }
.data-table.comfortable thead th { padding: var(--space-3) var(--space-4); }

.sort-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  background: none;
  border: none;
  color: inherit;
  font: inherit;
  text-transform: inherit;
  letter-spacing: inherit;
  cursor: pointer;
}

.sort-icon { opacity: 0; transition: opacity var(--transition-fast), transform var(--transition-fast); }
.sort-btn:hover .sort-icon { opacity: 0.6; }
.sort-icon.active { opacity: 1; }
.sort-icon.active.desc { transform: rotate(180deg); }

.data-table td {
  border-bottom: var(--border-width) solid var(--color-border);
  color: var(--color-text);
  white-space: nowrap;
}

.data-table.compact td { padding: var(--space-1) var(--space-3); height: 40px; box-sizing: border-box; }
.data-table.comfortable td { padding: var(--space-2) var(--space-4); height: 48px; box-sizing: border-box; }

.data-table td.num, .data-table th.num { text-align: right; font-variant-numeric: tabular-nums; }

.check-col { width: 32px; }
.actions-col { width: 1%; }

.row:hover { background: var(--color-surface-muted); }
.row.selected {
  background: var(--color-accent-bg);
  box-shadow: inset var(--border-width-thick) 0 0 var(--color-accent);
}

.retry {
  margin-left: var(--space-2);
  font-weight: var(--font-weight-medium);
  text-decoration: underline;
}
</style>
