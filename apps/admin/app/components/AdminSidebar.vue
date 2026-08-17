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
      <SidebarSection :items="secondaryNavigation.items" @navigate="sidebar.close()" />
    </div>
  </aside>
</template>

<script setup lang="ts">
import { primaryNavigation, secondaryNavigation } from '~/config/navigation'

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
