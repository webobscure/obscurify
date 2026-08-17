<template>
  <button
    ref="el"
    type="button"
    class="btn"
    :class="[variant, size]"
    :disabled="disabled || loading"
    :aria-busy="loading"
    :style="minWidthStyle"
  >
    <Spinner v-if="loading" :size="size === 'lg' ? 'md' : 'sm'" :variant="variant === 'secondary' || variant === 'ghost' ? 'default' : 'on-accent'" />
    <AppIcon v-if="icon && !loading" :name="icon" size="md" />
    <span :class="{ 'sr-only': loading && hideLabelWhenLoading }"><slot /></span>
  </button>
</template>

<script setup lang="ts">
/**
 * Replaces the bare `button[type=submit]` global reset in app.vue —
 * see docs/design/ADMIN_DESIGN_SYSTEM.md's Button spec. `min-width` is
 * locked to the button's own rendered width the first time `loading`
 * turns on, so the label->spinner swap never shrinks the button (jarring
 * given how long Russian labels routinely run).
 */
const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
  icon?: string
  loading?: boolean
  disabled?: boolean
  /** Hide the visible label while loading but keep it in the accessible name (screen readers shouldn't hear just "spinner"). */
  hideLabelWhenLoading?: boolean
}>(), { variant: 'primary', size: 'md', loading: false, disabled: false, hideLabelWhenLoading: false })

const el = ref<HTMLButtonElement | null>(null)
const lockedWidth = ref<number | null>(null)

watch(() => props.loading, (loading) => {
  if (loading && el.value && lockedWidth.value === null) {
    lockedWidth.value = el.value.getBoundingClientRect().width
  }
})

const minWidthStyle = computed(() => (lockedWidth.value ? { minWidth: `${lockedWidth.value}px` } : undefined))
</script>

<style scoped>
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  border-radius: var(--radius-sm);
  font-family: inherit;
  font-weight: var(--font-weight-medium);
  white-space: nowrap;
  cursor: pointer;
  transition: background-color var(--transition-fast), border-color var(--transition-fast), color var(--transition-fast);
}

.btn.sm { height: 28px; padding: 0 var(--space-3); font-size: var(--text-sm); }
.btn.md { height: 36px; padding: 0 var(--space-4); font-size: var(--text-base); }
.btn.lg { height: 44px; padding: 0 var(--space-5); font-size: var(--text-lg); }

.btn.primary {
  background: var(--color-accent);
  border: var(--border-width) solid var(--color-accent);
  color: var(--color-text-on-accent);
}
.btn.primary:not(:disabled):hover { background: var(--color-accent-hover); border-color: var(--color-accent-hover); }
.btn.primary:not(:disabled):active { background: var(--color-accent-active); border-color: var(--color-accent-active); }

.btn.secondary {
  background: var(--color-surface);
  border: var(--border-width) solid var(--color-border-strong);
  color: var(--color-text);
}
.btn.secondary:not(:disabled):hover { background: var(--color-surface-muted); }

.btn.danger {
  background: var(--color-danger);
  border: var(--border-width) solid var(--color-danger);
  color: var(--color-text-on-accent);
}
.btn.danger:not(:disabled):hover { background: var(--color-danger-hover); border-color: var(--color-danger-hover); }

.btn.ghost {
  background: transparent;
  border: var(--border-width) solid transparent;
  color: var(--color-accent);
}
.btn.ghost:not(:disabled):hover { background: var(--color-accent-bg); }

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}
</style>
