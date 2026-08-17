<template>
  <div class="admin-shell">
    <AdminSidebar />
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
 * The merchant admin's top-level chrome: sidebar + topbar + content
 * outlet. Extracted out of layouts/default.vue (Admin Shell v2, see
 * docs/design/DESIGN_SYSTEM.md section 4) so the shell is a real,
 * independently referenceable component rather than markup inlined into
 * the layout file — the layout's only job is picking which shell/chrome
 * a route gets (this one, or layouts/auth.vue for /login, /register).
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

/* Content-area gutter shrinks on narrow viewports — the shell (sidebar
   drawer, topbar hamburger) already had a 900px breakpoint; the content
   outlet itself had none (DESIGN_SYSTEM.md section 4: --content-padding-x-sm). */
@media (max-width: 900px) {
  .admin-content {
    padding: var(--space-4);
  }
}
</style>
