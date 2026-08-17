<template>
  <Alert :variant="variant" :title="title" :dismissible="dismissible" class="banner" @dismiss="emit('dismiss')">
    <slot />
  </Alert>
</template>

<script setup lang="ts">
/**
 * Page-level wide variant of Alert — full-bleed, square corners, sits at
 * the top of a page/section rather than inline with content (e.g. a
 * store-wide notice). Reuses Alert for the actual visual/a11y contract
 * rather than duplicating it — see docs/design/ADMIN_DESIGN_SYSTEM.md.
 */
withDefaults(defineProps<{
  variant?: 'danger' | 'warning' | 'success' | 'info'
  title?: string
  dismissible?: boolean
}>(), { variant: 'info' })

const emit = defineEmits<{ dismiss: [] }>()
</script>

<style scoped>
.banner {
  border-radius: 0;
  border-left: none;
  border-right: none;
}
</style>
