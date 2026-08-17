<template>
  <svg class="spinner" :class="[size, variant]" viewBox="0 0 24 24" fill="none" role="status" :aria-label="label">
    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="42 100" />
  </svg>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ size?: 'sm' | 'md' | 'lg'; variant?: 'default' | 'on-accent'; label?: string }>(), { size: 'sm', variant: 'default', label: 'Загрузка' })
</script>

<style scoped>
.spinner {
  color: currentColor;
  animation: spin 0.7s linear infinite;
}
.spinner.on-accent { color: var(--color-text-on-accent); }
.spinner.sm { width: var(--icon-size-sm, 14px); height: var(--icon-size-sm, 14px); }
.spinner.md { width: var(--icon-size-md, 18px); height: var(--icon-size-md, 18px); }
.spinner.lg { width: 32px; height: 32px; }

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
  .spinner { animation-duration: 1.4s; }
}
</style>
