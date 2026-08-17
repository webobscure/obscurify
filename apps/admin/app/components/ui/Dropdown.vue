<template>
  <Popover align="end">
    <template #trigger="{ open, toggle, panelId }">
      <button
        type="button"
        class="dropdown-trigger"
        :aria-haspopup="variant"
        :aria-expanded="open"
        :aria-controls="panelId"
        @click="toggle"
      >
        <slot name="trigger" :open="open" />
      </button>
    </template>
    <template #default="{ close }">
      <div :role="variant" class="dropdown-menu" @click="onItemClick(close, $event)">
        <slot :close="close" />
      </div>
    </template>
  </Popover>
</template>

<script setup lang="ts">
/**
 * Generic "click trigger, show a list of actions/options" primitive —
 * formalizes what StoreSwitcher.vue/UserMenu.vue currently each hand-roll
 * (not migrated onto this yet, see the migration plan). `menu` (role
 * menu/menuitem) and `listbox` (role listbox/option) are two distinct
 * ARIA patterns per docs/design/ADMIN_DESIGN_SYSTEM.md §Dropdown —
 * consumers render their own `role="menuitem"`/`role="option"` items via
 * the default slot; this component only supplies positioning + dismissal.
 */
withDefaults(defineProps<{ variant?: 'menu' | 'listbox' }>(), { variant: 'menu' })

function onItemClick(close: () => void, event: MouseEvent) {
  const item = (event.target as HTMLElement).closest('[role="menuitem"], [role="option"]')
  if (item) close()
}
</script>

<style scoped>
.dropdown-trigger {
  display: inline-flex;
}

.dropdown-menu {
  min-width: 180px;
  max-width: 320px;
  padding: var(--space-1);
}

.dropdown-menu :deep([role='menuitem']),
.dropdown-menu :deep([role='option']) {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  background: none;
  border: none;
  color: var(--color-text);
  font-size: var(--text-base);
  text-align: left;
  cursor: pointer;
}

.dropdown-menu :deep([role='menuitem']:hover),
.dropdown-menu :deep([role='option']:hover),
.dropdown-menu :deep([role='menuitem']:focus-visible),
.dropdown-menu :deep([role='option']:focus-visible) {
  background: var(--color-surface-muted);
}

.dropdown-menu :deep(.group + .group) {
  margin-top: var(--space-1);
  padding-top: var(--space-1);
  border-top: var(--border-width) solid var(--color-border);
}
</style>
