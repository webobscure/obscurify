<template>
  <header class="topbar">
    <button type="button" class="menu-toggle" :aria-label="t('chrome.toggle_navigation')" @click="sidebar.toggle()">
      <AppIcon name="menu" />
    </button>

    <GlobalSearch />

    <div class="spacer" />

    <LanguageSwitcher />
    <UserMenu />
  </header>
</template>

<script setup lang="ts">
const { t } = useI18n()
const sidebar = useAdminSidebar()
</script>

<style scoped>
.topbar {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  height: var(--topbar-height);
  padding: 0 var(--space-5);
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface);
  flex-shrink: 0;
}

.menu-toggle {
  display: none;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
}

.menu-toggle:hover,
.menu-toggle:focus-visible {
  background: var(--color-bg);
}

.spacer {
  flex: 1;
}

@media (max-width: 900px) {
  .menu-toggle {
    display: flex;
  }
}
</style>
