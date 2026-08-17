<template>
  <component :is="tag" class="card" :class="[variant, { interactive }]" :aria-labelledby="headerId">
    <header v-if="$slots.header" :id="headerId" class="head"><slot name="header" /></header>
    <div class="body"><slot /></div>
    <footer v-if="$slots.footer" class="foot"><slot name="footer" /></footer>
  </component>
</template>

<script setup lang="ts">
/**
 * Formalizes the `section` global styling in app.vue (border/radius/
 * padding, no shadow) as a real component with defined slots — same
 * visual spec, so existing bare `<section>` pages are unaffected.
 */
withDefaults(defineProps<{
  tag?: string
  variant?: 'default' | 'raised'
  interactive?: boolean
}>(), { tag: 'section', variant: 'default', interactive: false })

const headerId = useId()
</script>

<style scoped>
.card {
  background: var(--color-surface);
  border: var(--border-width) solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-5);
}

.card.raised {
  box-shadow: var(--shadow-sm);
}

.card.interactive {
  cursor: pointer;
  transition: border-color var(--transition-fast);
}
.card.interactive:hover {
  border-color: var(--color-border-strong);
}

.head {
  margin-bottom: var(--space-4);
  font-size: var(--text-lg);
  font-weight: var(--font-weight-semibold);
  line-height: var(--leading-tight);
}

.foot {
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  border-top: var(--border-width) solid var(--color-border);
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}
</style>
