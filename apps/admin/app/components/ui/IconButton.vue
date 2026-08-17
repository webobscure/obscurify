<template>
  <button
    type="button"
    class="icon-btn"
    :class="[variant, size, { active }]"
    :aria-label="ariaLabel"
    :aria-pressed="active"
    :disabled="disabled"
  >
    <AppIcon :name="icon" :size="iconSize" />
  </button>
</template>

<script setup lang="ts">
/**
 * Icon-only trigger — table row actions, topbar icons, modal close.
 * `ariaLabel` is required: there's no visible text to fall back to, and
 * this is exactly the kind of gap the audit found missing app-wide.
 */
const props = withDefaults(defineProps<{
  icon: string
  ariaLabel: string
  size?: 'sm' | 'md' | 'lg'
  variant?: 'ghost' | 'danger-ghost'
  active?: boolean
  disabled?: boolean
}>(), { size: 'md', variant: 'ghost' })

const iconSize = computed(() => (props.size === 'lg' ? 'md' : 'sm'))
</script>

<style scoped>
.icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background-color var(--transition-fast), color var(--transition-fast);
}

.icon-btn.sm { width: max(28px, var(--icon-size-sm)); height: max(28px, var(--icon-size-sm)); }
.icon-btn.md { width: 36px; height: 36px; }
.icon-btn.lg { width: 44px; height: 44px; }

.icon-btn.ghost:not(:disabled):hover { background: var(--color-surface-muted); color: var(--color-text); }
.icon-btn.ghost.active { background: var(--color-accent-bg); color: var(--color-accent); }

.icon-btn.danger-ghost { color: var(--color-text-muted); }
.icon-btn.danger-ghost:not(:disabled):hover { background: var(--color-danger-bg); color: var(--color-danger); }
</style>
