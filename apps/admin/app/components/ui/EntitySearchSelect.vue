<template>
  <div ref="root" class="entity-select">
    <div class="chips">
      <span v-for="item in selectedItems" :key="item.id" class="chip">
        {{ item.label }}
        <button type="button" :aria-label="t('common.remove_item', { item: item.label })" @click="toggle(item.id)">✕</button>
      </span>
      <button type="button" class="trigger" aria-haspopup="listbox" :aria-expanded="open" @click="open = !open">
        {{ t('common.add') }}
      </button>
    </div>

    <div v-if="open" class="panel">
      <input
        ref="searchInput"
        v-model="query"
        type="text"
        class="search"
        :placeholder="placeholder"
      >
      <ul class="options" role="listbox" :aria-multiselectable="true">
        <li v-for="item in filtered" :key="item.id">
          <button
            type="button"
            role="option"
            :aria-selected="modelValue.includes(item.id)"
            :class="{ selected: modelValue.includes(item.id) }"
            @click="toggle(item.id)"
          >
            <span>{{ item.label }}</span>
            <span v-if="modelValue.includes(item.id)" aria-hidden="true">✓</span>
          </button>
        </li>
        <li v-if="!filtered.length" class="empty">{{ t('common.no_matching_pages') }}</li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
export interface EntityOption { id: string; label: string }

const props = defineProps<{ modelValue: string[]; items: EntityOption[]; placeholder?: string }>()
const emit = defineEmits<{ 'update:modelValue': [string[]] }>()
const { t } = useI18n()

const open = ref(false)
const query = ref('')
const root = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)

const selectedItems = computed(() => props.items.filter(i => props.modelValue.includes(i.id)))

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return props.items
  return props.items.filter(i => i.label.toLowerCase().includes(q))
})

function toggle(id: string) {
  const next = props.modelValue.includes(id)
    ? props.modelValue.filter(v => v !== id)
    : [...props.modelValue, id]
  emit('update:modelValue', next)
}

watch(open, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    searchInput.value?.focus()
  }
})

function handleClickOutside(event: MouseEvent) {
  if (open.value && root.value && !root.value.contains(event.target as Node)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.entity-select {
  position: relative;
}

.chips {
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
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: 3px var(--space-1) 3px var(--space-2);
  color: var(--color-text-muted);
}

.chip button {
  width: 14px;
  height: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  color: var(--color-text-subtle);
  font-size: 9px;
}
.chip button:hover { background: var(--color-surface-muted); color: var(--color-text); }

.trigger {
  font-size: var(--text-xs);
  color: var(--color-accent);
  padding: 3px var(--space-2);
  border-radius: var(--radius-full);
  border: 1px dashed var(--color-border-strong);
}
.trigger:hover { background: var(--color-accent-bg); }

.panel {
  position: absolute;
  z-index: 20;
  top: calc(100% + var(--space-1));
  left: 0;
  width: 240px;
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  padding: var(--space-1);
}

.search {
  width: 100%;
  box-sizing: border-box;
  padding: var(--space-2);
  border: none;
  border-bottom: 1px solid var(--color-border);
  font-size: var(--text-sm);
  background: transparent;
  color: var(--color-text);
}
.search:focus-visible { outline: none; }

.options {
  list-style: none;
  margin: 0;
  padding: var(--space-1) 0 0;
  max-height: 220px;
  overflow-y: auto;
}

.options button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: var(--space-2);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm);
  color: var(--color-text);
  text-align: left;
}
.options button:hover,
.options button:focus-visible { background: var(--color-surface-muted); }
.options button.selected { color: var(--color-accent); font-weight: var(--font-weight-medium); }

.empty {
  padding: var(--space-2);
  font-size: var(--text-sm);
  color: var(--color-text-subtle);
}
</style>
