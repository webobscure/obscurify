<template>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="paths[name]" />
</template>

<script setup lang="ts">
/**
 * One small inline-SVG icon set shared by the sidebar/topbar/menus — no
 * icon library dependency, kept intentionally minimal. `v-html` here is
 * safe: `paths` is a fixed, hardcoded lookup with no user/API-derived
 * content ever passed through it.
 */
defineProps<{ name: string }>()

const paths: Record<string, string> = {
  orders: '<path d="M4 7h16l-1.5 12h-13z"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/>',
  customers: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.7a3.5 3.5 0 0 1 0 6.6"/><path d="M18 14.4c2.1.8 3.5 2.6 3.5 5.1"/>',
  fulfillment: '<rect x="3" y="7" width="18" height="13" rx="1"/><path d="M3 11h18"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M9 15h6"/>',
  products: '<path d="M3 8l9-5 9 5-9 5-9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
  collections: '<rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  inventory: '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 12l9 4 9-4"/><path d="M3 17l9 4 9-4"/>',
  locations: '<path d="M12 21s7-6.5 7-12a7 7 0 0 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
  payments: '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19"/><path d="M6 14.5h4"/>',
  shipping: '<rect x="2" y="8" width="13" height="9" rx="1"/><path d="M15 11h3.5l3 3.5V17h-6.5z"/><circle cx="7" cy="18.5" r="1.5"/><circle cx="17" cy="18.5" r="1.5"/>',
  promotions: '<path d="M20.59 13.41L11 3.83A2 2 0 0 0 9.59 3.17L4 3v5.59a2 2 0 0 0 .66 1.41l9.59 9.59a2 2 0 0 0 2.83 0l3.51-3.51a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
  apps: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M17.5 14v7M14 17.5h7"/>',
  themes: '<path d="M12 3a9 9 0 0 0 0 18 2 2 0 0 0 1.6-3.2 2 2 0 0 1 1.6-3.2H18a3 3 0 0 0 3-3 9 9 0 0 0-9-8.6z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16.5" cy="11" r="1"/>',
  pages: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/>',
  menus: '<path d="M4 6h16"/><path d="M9 11h11"/><path d="M9 16h11"/><path d="M4 6v10h3"/>',
  blogs: '<path d="M4 5h11a2 2 0 0 1 2 2v12H6a2 2 0 0 1-2-2z"/><path d="M17 9h1.5a1.5 1.5 0 0 1 1.5 1.5V17a2 2 0 0 1-2 2"/><path d="M7.5 9h6M7.5 12.5h6"/>',
  authors: '<circle cx="9.5" cy="8.5" r="3.5"/><path d="M3.5 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M17.5 7.5h4M19.5 5.5v4"/>',
  redirects: '<path d="M4 17h7a5 5 0 0 0 5-5V7"/><path d="M12.5 10.5L16 7l3.5 3.5"/><circle cx="4" cy="17" r="1.5"/>',
  automation: '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
  analytics: '<path d="M4 20V10M10 20V4M16 20v-7M21 20H3"/>',
  notifications: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
  stores: '<path d="M4 9l1-5h14l1 5"/><path d="M4 9a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0"/><path d="M5 9v10h14V9"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
  'russian-commerce': '<path d="M6 3h12v18H6z"/><path d="M9 8h6M9 12h6M9 16h3"/><circle cx="17" cy="17" r="3.2"/><path d="M17 15.3v3.4M15.7 17h2.6"/>',
  chevron: '<path d="M6 9l6 6 6-6"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>',
  menu: '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
}
</script>

<style scoped>
svg {
  width: 1.1em;
  height: 1.1em;
  flex-shrink: 0;
}
</style>
