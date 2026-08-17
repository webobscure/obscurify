<template>
  <div class="filter-bar" role="search">
    <div class="search-field">
      <input
        type="search"
        class="search-input"
        :value="search"
        :data-testid="testid"
        :placeholder="searchPlaceholder"
        :aria-label="searchPlaceholder"
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      >
    </div>
    <div class="filters"><slot /></div>
    <button v-if="hasActiveFilters" type="button" class="reset" @click="emit('reset')">
      {{ t('common.reset_filters') }}
    </button>
  </div>
</template>

<script setup lang="ts">
defineProps<{ search: string; searchPlaceholder: string; hasActiveFilters: boolean; testid?: string }>()
const emit = defineEmits<{ 'update:search': [string]; reset: [] }>()
const { t } = useI18n()
</script>

<style scoped>
.filter-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
}

.search-field {
  flex-shrink: 0;
}

.search-input {
  width: 220px;
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--text-sm);
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.filters :deep(select) {
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--text-sm);
}

.reset {
  font-size: var(--text-sm);
  color: var(--color-accent);
  padding: var(--space-2) var(--space-2);
}
.reset:hover { text-decoration: underline; }
</style>
