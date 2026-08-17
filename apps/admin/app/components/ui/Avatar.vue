<template>
  <span class="avatar" :class="size">
    <img v-if="src" :src="src" :alt="name ?? ''">
    <span v-else aria-hidden="true">{{ initial }}</span>
  </span>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  name?: string
  src?: string
  size?: 'sm' | 'md' | 'lg'
}>(), { size: 'md' })

const initial = computed(() => (props.name?.trim()?.[0] ?? '?').toUpperCase())
</script>

<style scoped>
.avatar {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  background: var(--color-text);
  color: var(--color-surface);
  font-weight: var(--font-weight-semibold);
  overflow: hidden;
}

.avatar.sm { width: 24px; height: 24px; font-size: var(--text-xs); }
.avatar.md { width: 36px; height: 36px; font-size: var(--text-sm); }
.avatar.lg { width: 48px; height: 48px; font-size: var(--text-base); }

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
