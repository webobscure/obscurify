<template>
  <div
    v-if="sidebar.mobileOpen.value"
    class="backdrop"
    aria-hidden="true"
    @click="sidebar.close()"
  />
  <aside class="sidebar" :class="{ 'mobile-open': sidebar.mobileOpen.value }">
    <StoreSwitcher />

    <NuxtLink to="/orders" class="back-link">
      <AppIcon name="arrow-left" size="sm" />
      <span>{{ t('nav.back_to_admin') }}</span>
    </NuxtLink>

    <p class="workspace-label">{{ t('nav.settings') }}</p>

    <Input v-model="query" size="sm" icon="search" clearable :placeholder="t('nav.settings_search_placeholder')" class="settings-search" />

    <nav class="settings-nav" :aria-label="t('nav.settings')">
      <SidebarSection
        v-for="(section, index) in filteredSections"
        :key="index"
        :label-key="section.labelKey"
        :items="section.items"
        @navigate="sidebar.close()"
      />
      <p v-if="!filteredSections.length" class="no-results">{{ t('common.no_matching_pages') }}</p>
    </nav>
  </aside>
</template>

<script setup lang="ts">
/**
 * The Settings workspace's own left-nav — same dark chrome/tokens as
 * AdminSidebar.vue (reused, not redesigned): the store switcher stays at
 * the top (spec §9 — settings are store-scoped, reuse the existing
 * StoreSwitcher rather than a second tenant-selection mechanism), then a
 * "back to the daily admin" link, then the flat grouped
 * `settingsNavigation` sections instead of the accordion daily tree.
 * Rendered by SettingsShell.vue for every page that opted into
 * `definePageMeta({ layout: 'settings' })`.
 */
import { settingsNavigation } from '~/config/navigation'

const { t } = useI18n()
const sidebar = useAdminSidebar()
const query = ref('')

/**
 * Client-side filter over the same navigation metadata the sidebar
 * itself renders from (spec §11: "only if it can be implemented using
 * the approved navigation metadata" — no backend, no second data source).
 */
const filteredSections = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return settingsNavigation

  return settingsNavigation
    .map(section => ({
      ...section,
      items: section.items.filter(item => t(item.labelKey).toLowerCase().includes(q)),
    }))
    .filter(section => section.items.length > 0)
})
</script>

<style scoped>
.sidebar {
  width: var(--sidebar-width);
  flex-shrink: 0;
  background: var(--color-sidebar-bg);
  color: var(--color-sidebar-text);
  display: flex;
  flex-direction: column;
  padding: var(--space-3);
  box-sizing: border-box;
  overflow-y: auto;
}

.back-link {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  margin-bottom: var(--space-3);
  border-radius: var(--radius-md);
  color: var(--color-sidebar-text-muted);
  text-decoration: none;
  font-size: var(--text-sm);
}

.back-link:hover,
.back-link:focus-visible {
  background: var(--color-sidebar-hover-bg);
  color: var(--color-sidebar-text);
}

.workspace-label {
  margin: 0 0 var(--space-2) var(--space-3);
  font-size: var(--text-lg);
  font-weight: var(--font-weight-semibold);
  color: white;
}

.settings-search {
  margin-bottom: var(--space-3);
}
.settings-search :deep(.input) {
  background: var(--color-sidebar-hover-bg);
  border-color: var(--color-sidebar-border);
  color: var(--color-sidebar-text);
}
.settings-search :deep(.input::placeholder) {
  color: var(--color-sidebar-text-muted);
}
.settings-search :deep(.leading-icon) {
  color: var(--color-sidebar-text-muted);
}

.settings-nav {
  flex: 1;
  overflow-y: auto;
}

.no-results {
  padding: var(--space-2) var(--space-3);
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-sidebar-text-muted);
}

.backdrop {
  display: none;
}

@media (max-width: 900px) {
  .sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 41;
    transform: translateX(-100%);
    transition: transform var(--transition-fast);
  }

  .sidebar.mobile-open {
    transform: translateX(0);
  }

  .backdrop {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 40;
    background: var(--color-overlay);
  }
}
</style>
