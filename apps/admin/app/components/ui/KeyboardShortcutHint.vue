<template>
  <kbd class="hint"><slot /></kbd>
</template>

<style scoped>
.hint {
  display: inline-flex;
  align-items: center;
  padding: 1px var(--space-1);
  border: var(--border-width) solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: var(--text-xs);
  line-height: 1.4;
}
</style>
