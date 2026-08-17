<template>
  <span class="badge" :class="variant">
    <slot />
    <IconButton
      v-if="removable"
      icon="close"
      size="sm"
      :ariaLabel="t('common.remove_item', { item: removeLabel ?? '' })"
      class="remove"
      @click="emit('remove')"
    />
  </span>
</template>

<script setup lang="ts">
/**
 * Generic small label — counts, tags, non-status metadata. Distinct from
 * StatusBadge, which is bound to the domain status→bucket map (see
 * docs/design/ADMIN_DESIGN_SYSTEM.md §12/§Badge/§StatusBadge).
 */
withDefaults(defineProps<{
  variant?: 'neutral' | 'accent' | 'outline'
  removable?: boolean
  removeLabel?: string
}>(), { variant: 'neutral' })

const emit = defineEmits<{ remove: [] }>()
const { t } = useI18n()
</script>

<style scoped>
.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  height: 20px;
  padding: 0 var(--space-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-weight-medium);
  white-space: nowrap;
}

.badge.neutral { background: var(--color-surface-muted); color: var(--color-text-muted); }
.badge.accent { background: var(--color-accent-bg); color: var(--color-accent); }
.badge.outline { background: transparent; border: var(--border-width) solid var(--color-border-strong); color: var(--color-text); }

.remove {
  width: 14px;
  height: 14px;
}
.remove :deep(svg) { width: 9px; height: 9px; }
</style>
