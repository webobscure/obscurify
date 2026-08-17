<template>
  <div class="section">
    <p v-if="labelKey" class="section-label">{{ t(labelKey) }}</p>
    <ul>
      <li v-for="item in items" :key="item.to">
        <div class="nav-row">
          <NuxtLink
            :to="item.to"
            class="nav-item"
            :class="{ active: isNavItemActive(route.path, item), 'parent-active': isBranchActive(item) }"
            :aria-current="isNavItemActive(route.path, item) ? 'page' : undefined"
            @click="emit('navigate')"
          >
            <AppIcon :name="item.icon" />
            <span>{{ t(item.labelKey) }}</span>
          </NuxtLink>

          <button
            v-if="item.children?.length"
            type="button"
            class="disclosure"
            :aria-expanded="isExpanded(item)"
            :aria-label="t('chrome.toggle_section', { section: t(item.labelKey) })"
            @click="toggle(item)"
          >
            <AppIcon name="chevron" class="chevron" :class="{ open: isExpanded(item) }" />
          </button>
        </div>

        <Transition name="collapse">
          <div v-if="item.children?.length && isExpanded(item)" class="children-wrapper">
            <ul class="children">
              <li v-for="child in item.children" :key="child.to">
                <NuxtLink
                  :to="child.to"
                  class="nav-item nav-item--child"
                  :class="{ active: isNavItemActive(route.path, child) }"
                  :aria-current="isNavItemActive(route.path, child) ? 'page' : undefined"
                  @click="emit('navigate')"
                >
                  <AppIcon :name="child.icon" />
                  <span>{{ t(child.labelKey) }}</span>
                </NuxtLink>
              </li>
            </ul>
          </div>
        </Transition>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { NavigationItem } from '~/config/navigation'
import { isNavItemActive } from '~/config/navigation'

const props = defineProps<{
  labelKey?: string
  items: NavigationItem[]
}>()

const emit = defineEmits<{ navigate: [] }>()
const route = useRoute()
const { t } = useI18n()
const { isBranchActive, syncFromItems, isExpanded, toggle } = useNavigationExpansion()

watch(() => route.path, () => syncFromItems(props.items), { immediate: true })
</script>

<style scoped>
.section + .section {
  margin-top: var(--space-5);
}

.section-label {
  margin: 0 0 var(--space-2) var(--space-3);
  font-size: var(--text-xs);
  font-weight: var(--font-weight-semibold);
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-sidebar-text-muted);
}

ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.nav-row {
  display: flex;
  align-items: stretch;
  gap: 1px;
}

.nav-item {
  flex: 1;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  color: var(--color-sidebar-text);
  text-decoration: none;
  font-size: var(--text-base);
  position: relative;
  transition: background-color var(--transition-fast), color var(--transition-fast);
}

.nav-item:hover,
.nav-item:focus-visible {
  background: var(--color-sidebar-hover-bg);
  color: white;
}

.nav-item.parent-active {
  color: white;
}

.nav-item.active {
  background: var(--color-sidebar-active-bg);
  color: white;
  font-weight: var(--font-weight-medium);
  box-shadow: inset 3px 0 0 var(--color-accent);
}

.nav-item :deep(svg) {
  color: inherit;
}

.disclosure {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  border: none;
  background: transparent;
  color: var(--color-sidebar-text-muted);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background-color var(--transition-fast), color var(--transition-fast);
}

.disclosure:hover,
.disclosure:focus-visible {
  background: var(--color-sidebar-hover-bg);
  color: white;
}

.chevron {
  transition: transform var(--transition-fast);
}

.chevron.open {
  transform: rotate(180deg);
}

.children {
  list-style: none;
  margin: 1px 0 1px var(--space-5);
  padding: 0 0 0 var(--space-3);
  border-left: 1px solid var(--color-sidebar-border);
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-height: 0;
}

.nav-item--child {
  font-size: var(--text-sm);
}

.children-wrapper {
  display: grid;
  grid-template-rows: 1fr;
}

.children-wrapper > .children {
  overflow: hidden;
}

.collapse-enter-active,
.collapse-leave-active {
  transition: grid-template-rows var(--transition-fast);
}

.collapse-enter-from,
.collapse-leave-to {
  grid-template-rows: 0fr;
}
</style>
