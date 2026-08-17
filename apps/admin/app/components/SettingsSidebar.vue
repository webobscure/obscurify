<template>
  <div
    v-if="sidebar.mobileOpen.value"
    class="backdrop"
    aria-hidden="true"
    @click="sidebar.close()"
  />
  <aside class="sidebar" :class="{ 'mobile-open': sidebar.mobileOpen.value }">
    <NuxtLink to="/orders" class="back-link">
      <AppIcon name="arrow-left" size="sm" />
      <span>{{ t('nav.back_to_admin') }}</span>
    </NuxtLink>

    <p class="workspace-label">{{ t('nav.settings') }}</p>

    <nav class="settings-nav" :aria-label="t('nav.settings')">
      <SidebarSection
        v-for="(section, index) in settingsNavigation"
        :key="index"
        :label-key="section.labelKey"
        :items="section.items"
        @navigate="sidebar.close()"
      />
    </nav>
  </aside>
</template>

<script setup lang="ts">
/**
 * The Settings workspace's own left-nav — same dark chrome/tokens as
 * AdminSidebar.vue (reused, not redesigned), different content: a "back
 * to the daily admin" link instead of the store switcher, and the flat
 * grouped `settingsNavigation` sections instead of the accordion daily
 * tree. Rendered by layouts/settings.vue for every page that opted into
 * `definePageMeta({ layout: 'settings' })`.
 */
import { settingsNavigation } from '~/config/navigation'

const { t } = useI18n()
const sidebar = useAdminSidebar()
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

.settings-nav {
  flex: 1;
  overflow-y: auto;
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
