<template>
  <nav class="sidebar-nav" aria-label="Primary">
    <SidebarSection
      v-for="(section, index) in sections"
      :key="index"
      :label="section.label"
      :items="section.items"
      @navigate="emit('navigate')"
    />
  </nav>
</template>

<script setup lang="ts">
import type { NavigationSection } from '~/config/navigation'

defineProps<{ sections: NavigationSection[] }>()
const emit = defineEmits<{ navigate: [] }>()
</script>

<style scoped>
.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  margin-top: var(--space-2);
}
</style>
