<template>
  <Popover trigger="hover" class="tooltip-host">
    <template #trigger="{ panelId }">
      <span :aria-describedby="panelId"><slot name="trigger" /></span>
    </template>
    <span role="tooltip" class="tooltip">{{ text }}</span>
  </Popover>
</template>

<script setup lang="ts">
/**
 * Shows on both hover AND focus (Popover's hover-trigger mode wires
 * focusin/focusout too) — hover-only would make it unreachable by
 * keyboard, a real gap the audit found (IconButtons had no explanatory
 * affordance at all). See docs/design/ADMIN_DESIGN_SYSTEM.md §Tooltip.
 */
defineProps<{ text: string }>()
</script>

<style scoped>
.tooltip-host { display: inline-flex; }
.tooltip-host :deep(.panel) { background: none; border: none; box-shadow: none; }

.tooltip {
  display: block;
  max-width: 240px;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-sm);
  background: var(--color-sidebar-bg);
  color: var(--color-sidebar-text);
  font-size: var(--text-xs);
  white-space: normal;
  animation: fade var(--duration-fast) var(--ease-standard);
}

@keyframes fade {
  from { opacity: 0; }
  to { opacity: 1; }
}
</style>
