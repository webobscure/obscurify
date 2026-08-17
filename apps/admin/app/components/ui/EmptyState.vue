<template>
  <div class="empty" :class="compact ? 'compact' : ''" role="status">
    <div v-if="$slots.icon" class="icon"><slot name="icon" /></div>
    <p class="title">{{ title }}</p>
    <p v-if="description" class="desc">{{ description }}</p>
    <div v-if="$slots.action" class="action"><slot name="action" /></div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ title: string; description?: string; compact?: boolean }>(), { compact: false })
</script>

<style scoped>
.empty {
  text-align: center;
  padding: var(--space-8) var(--space-4);
}
.empty.compact {
  padding: var(--space-3) var(--space-4);
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-md);
}

.icon {
  display: flex;
  justify-content: center;
  color: var(--color-text-subtle);
  margin-bottom: var(--space-1);
}
.icon :deep(svg) { width: 20px; height: 20px; }

.title {
  margin: 0;
  font-size: var(--text-base);
  font-weight: var(--font-weight-medium);
  color: var(--color-text);
}
.empty.compact .title {
  font-size: var(--text-sm);
}

.desc {
  margin: var(--space-1) 0 0;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}
.empty.compact .desc {
  font-size: var(--text-xs);
}

.action {
  margin-top: var(--space-3);
}
</style>
