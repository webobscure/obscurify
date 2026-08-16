<template>
  <div class="section">
    <p v-if="labelKey" class="section-label">{{ t(labelKey) }}</p>
    <ul>
      <li v-for="item in items" :key="item.to">
        <NuxtLink
          :to="item.to"
          class="nav-item"
          :class="{ active: isNavItemActive(route.path, item) }"
          :aria-current="isNavItemActive(route.path, item) ? 'page' : undefined"
          @click="emit('navigate')"
        >
          <AppIcon :name="item.icon" />
          <span>{{ t(item.labelKey) }}</span>
        </NuxtLink>

        <!-- Nested items are always expanded — an expand/collapse
             interaction is future scope, not needed for the one real
             nesting case (Locations under Inventory) this app has. -->
        <ul v-if="item.children?.length" class="children">
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
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { NavigationItem } from '~/config/navigation'
import { isNavItemActive } from '~/config/navigation'

defineProps<{
  labelKey?: string
  items: NavigationItem[]
}>()

const emit = defineEmits<{ navigate: [] }>()
const route = useRoute()
const { t } = useI18n()
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

.nav-item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  color: var(--color-sidebar-text);
  text-decoration: none;
  font-size: var(--text-base);
  transition: background-color var(--transition-fast), color var(--transition-fast);
}

.nav-item:hover,
.nav-item:focus-visible {
  background: var(--color-sidebar-hover-bg);
  color: white;
}

.nav-item.active {
  background: var(--color-sidebar-active-bg);
  color: white;
  font-weight: var(--font-weight-medium);
}

.children {
  margin: 1px 0 1px var(--space-5);
  padding-left: var(--space-3);
  border-left: 1px solid var(--color-sidebar-border);
}

.nav-item--child {
  font-size: var(--text-sm);
}
</style>
