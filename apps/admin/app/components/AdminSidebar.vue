<template>
  <div
    v-if="sidebar.mobileOpen.value"
    class="backdrop"
    aria-hidden="true"
    @click="sidebar.close()"
  />
  <aside class="sidebar" :class="{ 'mobile-open': sidebar.mobileOpen.value }">
    <StoreSwitcher />

    <AdminNavigation :sections="primaryNavigation" @navigate="sidebar.close()" />

    <div class="bottom">
      <SidebarSection :items="bottomItems" @navigate="sidebar.close()" />
    </div>
  </aside>
</template>

<script setup lang="ts">
import { isRouteInSection, primaryNavigation, secondaryNavigation, settingsNavigation } from '~/config/navigation'

const sidebar = useAdminSidebar()
const route = useRoute()

/**
 * Settings' own `to`/`activePattern` ('/settings') only literally matches
 * the redirect landing page — every real Settings destination
 * (/notifications, /russian-commerce/tax-settings, ...) has a different
 * URL. Swap in the *current* path as the active-match target whenever
 * we're actually inside settingsNavigation, so the Settings entry stays
 * visibly active for the whole time the user is in the workspace, not
 * just on the redirect flash (spec §7).
 */
const bottomItems = computed(() => {
  if (!isRouteInSection(route.path, settingsNavigation)) return secondaryNavigation.items

  return secondaryNavigation.items.map(item =>
    item.to === '/settings' ? { ...item, activePattern: route.path } : item,
  )
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
  transition: width var(--transition-fast);
}

.bottom {
  border-top: 1px solid var(--color-sidebar-border);
  padding-top: var(--space-3);
  margin-top: var(--space-3);
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
