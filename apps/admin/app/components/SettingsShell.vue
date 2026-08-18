<template>
  <div class="admin-shell">
    <SettingsSidebar />
    <div class="admin-main">
      <AdminTopbar />
      <main class="admin-content">
        <AdminBreadcrumbs v-if="breadcrumbItems.length > 1" :items="breadcrumbItems" class="settings-crumbs" />
        <slot />
      </main>
    </div>
    <Toast />
  </div>
</template>

<script setup lang="ts">
/**
 * Mirrors AdminShell.vue exactly (same topbar, same content-outlet
 * padding, same Toast mount) with SettingsSidebar in place of
 * AdminSidebar — the Settings workspace is a different navigation
 * context, not a different visual system.
 *
 * The "Settings / {Section}" breadcrumb above every settings page's own
 * PageHeader is computed here, once, from `findSettingsSection` — the
 * same navigation metadata the sidebar itself renders from (spec §6/§13:
 * one source of truth, no page hand-rolling its own breadcrumb array).
 * Individual pages keep their own PageHeader title below it (e.g.
 * "Settings" → "Notification Center"), matching the two-line example the
 * spec gave ("Settings" / "Payments").
 */
import { findSettingsSection } from '~/config/navigation'

const { t } = useI18n()
const route = useRoute()

const breadcrumbItems = computed(() => {
  const section = findSettingsSection(route.path)
  if (!section?.labelKey) return [{ label: t('nav.settings'), to: '/settings' }]
  return [
    { label: t('nav.settings'), to: '/settings' },
    { label: t(section.labelKey) },
  ]
})
</script>

<style scoped>
.admin-shell {
  min-height: 100vh;
  display: flex;
}

.admin-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.admin-content {
  flex: 1;
  padding: var(--space-6) var(--space-8);
  box-sizing: border-box;
}

.settings-crumbs {
  margin-bottom: var(--space-3);
}

@media (max-width: 900px) {
  .admin-content {
    padding: var(--space-4);
  }
}
</style>
