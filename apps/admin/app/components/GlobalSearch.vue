<template>
  <div class="search">
    <button type="button" class="trigger" :aria-label="t('chrome.open_navigation_search')" @click="openPalette">
      <AppIcon name="search" />
      <span class="placeholder">{{ t('chrome.search_placeholder') }}</span>
      <kbd>{{ shortcutLabel }}</kbd>
    </button>

    <Teleport to="body">
      <div v-if="open" class="overlay" @click.self="close">
        <div class="palette" role="dialog" aria-modal="true" :aria-label="t('chrome.navigation_search_dialog')">
          <input
            ref="inputRef"
            v-model="query"
            type="text"
            :placeholder="t('chrome.search_placeholder')"
            @keydown.esc="close"
            @keydown.enter="filtered.length && go(filtered[0]!)"
          >
          <ul>
            <li v-for="item in filtered" :key="item.to">
              <button type="button" @click="go(item)">{{ t(item.labelKey) }}</button>
            </li>
            <li v-if="!filtered.length" class="empty">{{ t('common.no_matching_pages') }}</li>
          </ul>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import type { NavigationItem } from '~/config/navigation'
import { flattenNavigationItems, primaryNavigation, secondaryNavigation, settingsNavigation } from '~/config/navigation'

/**
 * There is no global search backend yet (out of scope for this shell
 * refactor) — this is a scoped navigation palette over the app's own
 * real routes (spec section 5: "build only the UI component" / "do not
 * invent fake search results"). Includes settingsNavigation (Milestone
 * 27) so ⌘K can still jump straight to a Settings page without the user
 * needing to know it moved out of the daily sidebar first.
 */
const allItems: NavigationItem[] = [
  ...flattenNavigationItems(primaryNavigation),
  ...flattenNavigationItems(settingsNavigation),
  ...secondaryNavigation.items,
]

const { t } = useI18n()

const open = ref(false)
const query = ref('')
const inputRef = ref<HTMLInputElement | null>(null)
const router = useRouter()

const shortcutLabel = computed(() => (import.meta.client && /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl+K'))

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return allItems
  return allItems.filter(item => t(item.labelKey).toLowerCase().includes(q))
})

async function openPalette() {
  open.value = true
  query.value = ''
  await nextTick()
  inputRef.value?.focus()
}

function close() {
  open.value = false
}

function go(item: NavigationItem) {
  close()
  router.push(item.to)
}

function handleShortcut(event: KeyboardEvent) {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    openPalette()
  }
}

onMounted(() => document.addEventListener('keydown', handleShortcut))
onUnmounted(() => document.removeEventListener('keydown', handleShortcut))
</script>

<style scoped>
.trigger {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  max-width: 420px;
  padding: var(--space-2) var(--space-3);
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  cursor: pointer;
  text-align: left;
}

.trigger:hover,
.trigger:focus-visible {
  background: var(--color-border);
}

.placeholder {
  flex: 1;
  font-size: var(--text-sm);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

kbd {
  font-size: var(--text-xs);
  padding: 0 var(--space-2);
  background: var(--color-surface);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  color: var(--color-text-subtle);
}

.overlay {
  position: fixed;
  inset: 0;
  background: var(--color-overlay);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 12vh;
  z-index: 100;
}

.palette {
  width: 100%;
  max-width: 520px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}

.palette input {
  width: 100%;
  box-sizing: border-box;
  padding: var(--space-4);
  border: none;
  border-bottom: 1px solid var(--color-border);
  font-size: var(--text-lg);
  outline: none;
}

.palette ul {
  list-style: none;
  margin: 0;
  padding: var(--space-2);
  max-height: 320px;
  overflow-y: auto;
}

.palette button {
  display: block;
  width: 100%;
  text-align: left;
  padding: var(--space-2) var(--space-3);
  background: none;
  border: none;
  border-radius: var(--radius-sm);
  font-size: var(--text-base);
  cursor: pointer;
}

.palette button:hover,
.palette button:focus-visible {
  background: var(--color-bg);
}

.palette .empty {
  padding: var(--space-2) var(--space-3);
  color: var(--color-text-subtle);
  font-size: var(--text-sm);
}
</style>
