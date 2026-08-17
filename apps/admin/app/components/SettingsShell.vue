<template>
  <div class="admin-shell">
    <SettingsSidebar />
    <div class="admin-main">
      <AdminTopbar />
      <main class="admin-content">
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
 */
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

@media (max-width: 900px) {
  .admin-content {
    padding: var(--space-4);
  }
}
</style>
