<template>
  <div ref="root" class="switcher">
    <button
      type="button"
      class="trigger"
      aria-haspopup="listbox"
      :aria-expanded="open"
      :aria-label="t('chrome.switch_store')"
      @click="toggle"
    >
      <span class="icon"><AppIcon name="stores" /></span>
      <span class="label">
        <strong>{{ activeStore.store.value?.name ?? t('chrome.select_a_store') }}</strong>
        <small v-if="activeStore.store.value">
          {{ activeStore.store.value.slug }}
          <span class="status" :class="statusClass">{{ activeStore.store.value.status }}</span>
        </small>
      </span>
      <AppIcon name="chevron" class="chev" :class="{ open }" />
    </button>

    <ul v-if="open" class="menu" role="listbox" :aria-label="t('nav.stores')">
      <li v-for="store in stores" :key="store.id">
        <button
          type="button"
          role="option"
          :aria-selected="store.id === activeStore.storeId.value"
          :class="{ active: store.id === activeStore.storeId.value }"
          @click="select(store.id)"
        >
          <span>{{ store.name }}</span>
          <span class="slug">{{ store.slug }}</span>
        </button>
      </li>
      <li v-if="!stores.length" class="empty">{{ t('chrome.no_stores_yet') }}</li>
      <li class="manage">
        <NuxtLink to="/stores" @click="open = false">{{ t('chrome.manage_stores') }}</NuxtLink>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { Store } from '@obscurify/types'

const { t } = useI18n()
const activeStore = useActiveStore()
const stores = ref<Store[]>([])
const open = ref(false)
const root = ref<HTMLElement | null>(null)

// "Current environment" (spec section 3) — this app has no separate
// staging/production concept, so the store's own real status
// (active/suspended) is what's actually meaningful to show here rather
// than inventing an environment label with no backing data.
const statusClass = computed(() => (activeStore.store.value?.status === 'active' ? 'is-active' : 'is-suspended'))

async function toggle() {
  open.value = !open.value
  if (open.value && !stores.value.length) {
    const response = await useApi().stores.list()
    stores.value = response.data
  }
}

async function select(storeId: string) {
  if (storeId !== activeStore.storeId.value) {
    await activeStore.activate(storeId)
  }
  open.value = false
}

function handleClickOutside(event: MouseEvent) {
  if (open.value && root.value && !root.value.contains(event.target as Node)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.switcher {
  position: relative;
  margin-bottom: var(--space-3);
}

.trigger {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-2) var(--space-3);
  background: var(--color-sidebar-hover-bg);
  border: 1px solid var(--color-sidebar-border);
  border-radius: var(--radius-md);
  color: white;
  cursor: pointer;
  text-align: left;
}

.trigger:hover,
.trigger:focus-visible {
  background: var(--color-sidebar-active-bg);
}

.icon {
  display: flex;
  color: var(--color-sidebar-text-muted);
}

.label {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.label strong {
  font-size: var(--text-sm);
  font-weight: var(--font-weight-semibold);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.label small {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-sidebar-text-muted);
}

.status {
  text-transform: capitalize;
  padding: 0 var(--space-1);
  border-radius: var(--radius-sm);
  font-size: 0.65rem;
  line-height: 1.4;
}

.status.is-active {
  color: var(--color-sidebar-badge-success);
  background: var(--color-sidebar-badge-success-bg);
}

.status.is-suspended {
  color: var(--color-sidebar-badge-warning);
  background: var(--color-sidebar-badge-warning-bg);
}

.chev {
  color: var(--color-sidebar-text-muted);
  transition: transform var(--transition-fast);
}

.chev.open {
  transform: rotate(180deg);
}

.menu {
  position: absolute;
  z-index: 20;
  top: calc(100% + var(--space-2));
  left: 0;
  right: 0;
  background: var(--color-sidebar-menu-bg);
  border: 1px solid var(--color-sidebar-border);
  border-radius: var(--radius-md);
  list-style: none;
  margin: 0;
  padding: var(--space-1);
  /* Dropdown pattern — elevation 1 per docs/design/DESIGN_SYSTEM.md
     section 7, same level as UserMenu's dropdown. */
  box-shadow: var(--shadow-sm);
}

.menu button {
  display: flex;
  justify-content: space-between;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-2) var(--space-3);
  background: none;
  border: none;
  border-radius: var(--radius-sm);
  color: var(--color-sidebar-text);
  cursor: pointer;
  font-size: var(--text-base);
  text-align: left;
}

.menu button:hover,
.menu button:focus-visible {
  background: var(--color-sidebar-hover-bg);
}

.menu button.active {
  background: var(--color-sidebar-active-bg);
  color: white;
  font-weight: var(--font-weight-medium);
}

.menu .slug {
  color: var(--color-sidebar-text-muted);
  font-size: var(--text-xs);
}

.menu .empty {
  padding: var(--space-2) var(--space-3);
  color: var(--color-sidebar-text-muted);
  font-size: var(--text-sm);
}

.menu .manage {
  border-top: 1px solid var(--color-sidebar-border);
  margin-top: var(--space-1);
  padding-top: var(--space-1);
}

.menu .manage a {
  display: block;
  padding: var(--space-2) var(--space-3);
  color: var(--color-sidebar-text);
  text-decoration: none;
  font-size: var(--text-sm);
  border-radius: var(--radius-sm);
}

.menu .manage a:hover {
  background: var(--color-sidebar-hover-bg);
}
</style>
