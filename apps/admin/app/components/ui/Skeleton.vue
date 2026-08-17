<template>
  <span class="skeleton" :class="variant" :style="style" aria-hidden="true" />
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  variant?: 'text' | 'block' | 'table-row'
  width?: string
  height?: string
  /** table-row only — matches DataTable's compact (40px) / comfortable (48px) row height. */
  density?: 'compact' | 'comfortable'
}>(), { variant: 'text', density: 'compact' })

const style = computed(() => {
  if (props.variant === 'table-row') {
    return { width: props.width ?? '100%', height: props.density === 'compact' ? '40px' : '48px' }
  }
  return {
    width: props.width,
    height: props.variant === 'text' ? undefined : props.height,
  }
})
</script>

<style scoped>
.skeleton {
  display: block;
  background: var(--color-surface-muted);
  border-radius: var(--radius-sm);
  position: relative;
  overflow: hidden;
}

.skeleton.text {
  height: 0.9em;
  width: 100%;
}

.skeleton::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, transparent, var(--color-border), transparent);
  animation: shimmer 1.6s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
  .skeleton::after { animation: none; }
}

@keyframes shimmer {
  from { transform: translateX(-100%); }
  to { transform: translateX(100%); }
}
</style>
