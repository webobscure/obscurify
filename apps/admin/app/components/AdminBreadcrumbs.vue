<template>
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
      <li v-for="(item, index) in items" :key="index">
        <NuxtLink v-if="item.to && index < items.length - 1" :to="item.to">{{ item.label }}</NuxtLink>
        <span v-else :aria-current="index === items.length - 1 ? 'page' : undefined">{{ item.label }}</span>
        <span v-if="index < items.length - 1" class="sep" aria-hidden="true">/</span>
      </li>
    </ol>
  </nav>
</template>

<script setup lang="ts">
export interface BreadcrumbItem {
  label: string
  to?: string
}

defineProps<{ items: BreadcrumbItem[] }>()
</script>

<style scoped>
.breadcrumb ol {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  list-style: none;
  margin: 0 0 var(--space-2);
  padding: 0;
  font-size: var(--text-sm);
}

.breadcrumb li {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.breadcrumb a {
  color: var(--color-text-muted);
  text-decoration: none;
}

.breadcrumb a:hover,
.breadcrumb a:focus-visible {
  color: var(--color-text);
  text-decoration: underline;
}

.breadcrumb span[aria-current='page'] {
  color: var(--color-text);
  font-weight: var(--font-weight-medium);
}

.sep {
  color: var(--color-text-subtle);
}
</style>
