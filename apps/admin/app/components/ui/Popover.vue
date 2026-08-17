<template>
  <div ref="root" class="popover-root">
    <div ref="triggerEl" class="trigger" @click="trigger === 'click' ? toggle() : undefined">
      <slot name="trigger" :open="open" :toggle="toggle" :panel-id="panelId" />
    </div>
    <div v-if="open" :id="panelId" class="panel" :class="placement" :style="panelStyle">
      <slot :close="close" />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Positioning primitive underneath Dropdown/Tooltip — floating panel
 * anchored to a trigger, flips above the trigger when there isn't room
 * below. Doesn't impose its own ARIA role (the consumer supplies
 * role="menu"/"listbox"/"tooltip" on top) — see docs/design/
 * ADMIN_DESIGN_SYSTEM.md §Popover.
 */
const props = withDefaults(defineProps<{
  trigger?: 'click' | 'hover'
  align?: 'start' | 'end'
}>(), { trigger: 'click', align: 'start' })

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const triggerEl = ref<HTMLElement | null>(null)
const panelId = useId()
const placement = ref<'below' | 'above'>('below')

function updatePlacement() {
  if (!triggerEl.value) return
  const rect = triggerEl.value.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom
  placement.value = spaceBelow < 240 && rect.top > spaceBelow ? 'above' : 'below'
}

function toggle() {
  if (!open.value) updatePlacement()
  open.value = !open.value
}

function close() {
  open.value = false
}

const panelStyle = computed(() => ({ [props.align === 'end' ? 'right' : 'left']: 0 }))

if (props.trigger === 'hover') {
  onMounted(() => {
    root.value?.addEventListener('mouseenter', () => { updatePlacement(); open.value = true })
    root.value?.addEventListener('mouseleave', () => { open.value = false })
    root.value?.addEventListener('focusin', () => { updatePlacement(); open.value = true })
    root.value?.addEventListener('focusout', (e) => {
      if (!root.value?.contains((e as FocusEvent).relatedTarget as Node)) open.value = false
    })
  })
} else {
  useClickOutside(root, open, close)
}

defineExpose({ close })
</script>

<style scoped>
.popover-root {
  position: relative;
  display: inline-flex;
}

.trigger {
  display: contents;
}

.panel {
  position: absolute;
  z-index: 30;
  min-width: 100%;
  background: var(--color-surface-raised);
  border: var(--border-width) solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
}

.panel.below { top: calc(100% + var(--space-1)); }
.panel.above { bottom: calc(100% + var(--space-1)); }
</style>
