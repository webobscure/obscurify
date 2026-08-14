<template>
  <div class="picker">
    <div class="head">
      <strong>{{ title }}</strong>
      <button type="button" @click="emit('close')">Close</button>
    </div>

    <p v-if="!presets.length" class="hint">
      No presets available. Presets are seeded against the active theme's
      current draft version — activate a theme first.
    </p>
    <ul v-else>
      <li v-for="preset in presets" :key="preset.id">
        <button type="button" class="preset" @click="emit('pick', preset)">
          <span class="name">{{ preset.name }}</span>
          <code>{{ preset.handle }}</code>
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { BuilderPreset } from '@obscurify/types'

/**
 * The Section/Block library picker the Builder opens when adding a
 * section, a block, or a nested block. Purely presentational — the
 * builder page loads both preset lists once and owns every API call, the
 * same division MenuItemTree uses.
 */
defineProps<{ title: string, presets: BuilderPreset[] }>()

const emit = defineEmits<{ pick: [preset: BuilderPreset], close: [] }>()
</script>

<style scoped>
.picker {
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: var(--space-3);
  margin-bottom: var(--space-3);
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
}

ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: var(--space-2);
}

.preset {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-1);
  text-align: left;
}

.name {
  font-weight: var(--font-weight-medium);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0;
}
</style>
