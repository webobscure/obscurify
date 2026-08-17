<template>
  <nav v-if="lastPage > 1" class="pagination" :aria-label="t('common.pagination')">
    <span class="summary">{{ t('common.pagination_summary', { from, to, total }) }}</span>
    <div class="controls">
      <button type="button" class="step" :disabled="page <= 1" @click="emit('update:page', page - 1)">
        {{ t('common.previous') }}
      </button>
      <span class="current" aria-current="page">{{ t('common.page_of', { page, lastPage }) }}</span>
      <button type="button" class="step" :disabled="page >= lastPage" @click="emit('update:page', page + 1)">
        {{ t('common.next') }}
      </button>
    </div>
  </nav>
</template>

<script setup lang="ts">
const props = defineProps<{ page: number; lastPage: number; perPage: number; total: number }>()
const emit = defineEmits<{ 'update:page': [number] }>()
const { t } = useI18n()

const from = computed(() => (props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1))
const to = computed(() => Math.min(props.page * props.perPage, props.total))
</script>

<style scoped>
.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  padding-top: var(--space-3);
  font-size: var(--text-sm);
}

.summary {
  color: var(--color-text-muted);
}

.controls {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.current {
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

.step {
  height: 28px;
  padding: 0 var(--space-3);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--text-sm);
}

.step:disabled {
  opacity: var(--disabled-opacity, 0.5);
  cursor: not-allowed;
}

.step:not(:disabled):hover {
  background: var(--color-surface-muted);
}
</style>
