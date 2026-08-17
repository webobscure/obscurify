<template>
  <span class="badge" :class="variant">{{ label }}</span>
</template>

<script setup lang="ts">
export type StatusBadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

defineProps<{ label: string; variant: StatusBadgeVariant }>()
</script>

<style scoped>
.badge {
  display: inline-flex;
  align-items: center;
  height: 20px;
  padding: 0 var(--space-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-weight-medium);
  white-space: nowrap;
}

.badge.success { background: var(--color-success-bg); color: var(--color-success); }
.badge.warning { background: var(--color-warning-bg); color: var(--color-warning); }
.badge.danger { background: var(--color-danger-bg); color: var(--color-danger); }
.badge.info { background: var(--color-info-bg); color: var(--color-info); }
.badge.neutral { background: var(--color-surface-muted); color: var(--color-text-muted); }
</style>
