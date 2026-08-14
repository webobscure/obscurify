<template>
  <div class="node">
    <p class="label"><code>{{ handle }}</code> <span v-if="label !== handle">{{ label }}</span></p>

    <h2 v-if="heading">{{ heading }}</h2>
    <p v-for="(paragraph, index) in paragraphs" :key="index">{{ paragraph }}</p>
    <img v-if="image" :src="image" :alt="heading ?? handle" >
    <video v-if="video" :src="video" controls />
    <a v-if="buttonLabel && buttonUrl" class="button" :href="buttonUrl">{{ buttonLabel }}</a>

    <dl v-if="rest.length">
      <template v-for="[key, value] in rest" :key="key">
        <dt>{{ key }}</dt>
        <dd>{{ typeof value === 'string' ? value : JSON.stringify(value) }}</dd>
      </template>
    </dl>

    <BuilderSectionPreview
      v-for="block in blocks"
      :key="block.id ?? block.handle"
      :label="block.handle"
      :handle="block.handle"
      :settings="block.settings"
    />
  </div>
</template>

<script setup lang="ts">
import type { RenderedBlock } from '@obscurify/types'

/**
 * A deliberately best-effort mock renderer for the builder's preview
 * panel. There is no real theme template/CSS system in this codebase to
 * render against — ThemeRenderer returns resolved settings, not markup —
 * and no schema endpoint describing what fields a section or block
 * actually takes, so this guesses from a handful of conventional key
 * names and dumps whatever is left as a key/value list. It is a preview
 * aid, not a storefront renderer; do not grow it into one.
 *
 * Recursive (self-referencing SFC) purely so a section's blocks render
 * with the same heuristics as the section itself.
 */
const props = defineProps<{
  label: string
  handle: string
  settings: Record<string, unknown>
  blocks?: RenderedBlock[]
}>()

/** Every key rendered specially above, so the `dl` fallback skips them. */
const specialKeys = new Set(['heading', 'subheading', 'text', 'caption', 'image', 'video_url', 'button_label', 'button_url'])

function str(key: string): string | null {
  const value = props.settings[key]

  return typeof value === 'string' && value.trim() !== '' ? value : null
}

/** Absolute or root-relative only — anything else is shown as plain text. */
function url(key: string): string | null {
  const value = str(key)

  return value !== null && /^(https?:\/\/|\/)/.test(value) ? value : null
}

const heading = computed(() => str('heading'))
const paragraphs = computed(() => ['subheading', 'text', 'caption'].map(str).filter((value): value is string => value !== null))
const image = computed(() => url('image'))
const video = computed(() => url('video_url'))
const buttonLabel = computed(() => str('button_label'))
const buttonUrl = computed(() => str('button_url'))
const rest = computed(() => Object.entries(props.settings).filter(([key]) => !specialKeys.has(key)))
</script>

<style scoped>
.node {
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-sm);
  padding: var(--space-3);
  margin-bottom: var(--space-2);
}

.node .node {
  margin-top: var(--space-2);
  margin-bottom: 0;
}

.label {
  margin: 0 0 var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

h2 {
  margin: 0 0 var(--space-2);
  font-size: var(--text-xl);
}

img,
video {
  max-width: 100%;
  display: block;
  border-radius: var(--radius-sm);
}

.button {
  display: inline-block;
  margin-top: var(--space-2);
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-sm);
  background: var(--color-accent);
  color: #fff;
  text-decoration: none;
  font-size: var(--text-sm);
}

dl {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: var(--space-1) var(--space-3);
  margin: var(--space-2) 0 0;
  font-size: var(--text-xs);
}

dt {
  color: var(--color-text-muted);
}

dd {
  margin: 0;
  overflow-wrap: anywhere;
}
</style>
