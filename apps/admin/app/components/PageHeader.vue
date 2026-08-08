<template>
  <header class="page-header">
    <AppBreadcrumb v-if="breadcrumbs?.length" :items="breadcrumbs" />
    <div class="row">
      <h1>{{ title }}</h1>
      <div v-if="$slots.actions" class="actions">
        <slot name="actions" />
      </div>
    </div>
    <p v-if="description" class="description">{{ description }}</p>
  </header>
</template>

<script setup lang="ts">
import type { BreadcrumbItem } from './AppBreadcrumb.vue'

/**
 * The one place every admin page declares its own identity (title,
 * optional breadcrumb trail back to a list page, optional description) —
 * see spec section 8/11: pages inherit the shell instead of hand-rolling
 * their own heading/back-link markup.
 */
defineProps<{
  title: string
  breadcrumbs?: BreadcrumbItem[]
  description?: string
}>()
</script>

<style scoped>
.page-header {
  margin-bottom: var(--space-6);
}

.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
}

h1 {
  margin: 0;
  font-size: var(--text-2xl);
  font-weight: var(--font-weight-semibold);
  letter-spacing: -0.01em;
}

.description {
  margin: var(--space-2) 0 0;
  color: var(--color-text-muted);
  font-size: var(--text-base);
}

.actions {
  display: flex;
  gap: var(--space-2);
  flex-shrink: 0;
}
</style>
