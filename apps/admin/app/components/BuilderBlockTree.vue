<template>
  <ul>
    <li
      v-for="(block, index) in blocks"
      :key="block.id"
      draggable="true"
      @dragstart.stop="dragIndex = index"
      @dragend.stop="dragIndex = null"
      @dragover.prevent.stop
      @drop.prevent.stop="handleDrop(index)"
    >
      <div class="row" :class="{ selected: isSelected(index) }">
        <span class="grip" aria-hidden="true">⠿</span>
        <button type="button" class="select" @click="emit('select', [...path, index])">
          <code>{{ block.block_handle }}</code>
        </button>
        <button type="button" @click="emit('addNested', block.blocks)">Add nested block</button>
        <button type="button" @click="emit('duplicate', { blocks, index })">Duplicate</button>
        <button type="button" @click="emit('remove', { blocks, index })">Delete</button>
      </div>

      <BuilderBlockTree
        v-if="block.blocks.length"
        :blocks="block.blocks"
        :path="[...path, index]"
        :selected-path="selectedPath"
        @select="emit('select', $event)"
        @add-nested="emit('addNested', $event)"
        @duplicate="emit('duplicate', $event)"
        @remove="emit('remove', $event)"
        @reorder="emit('reorder', $event)"
      />
    </li>
  </ul>
</template>

<script setup lang="ts">
import type { BlockInstance } from '@obscurify/types'

/**
 * A section's nested block tree. Recursive (a SFC may reference itself by
 * filename) and purely presentational, the same shape MenuItemTree uses:
 * every mutation is emitted to the builder page, which owns the one
 * reactive `sections` array and therefore all autosave/API work.
 *
 * Identity is positional, not by `id`: the backend deletes and recreates
 * every SectionInstance/BlockInstance row on save
 * (ReplaceSectionInstancesFromArray), so ids churn on every round-trip
 * while positions are preserved exactly. `path` is the index path to this
 * list's parent — `[...path, index]` addresses a block from the root.
 *
 * Known limitation (this milestone): drag-and-drop only reorders siblings
 * at one nesting level. `dragIndex` is per-component-instance, so a drop
 * landing on a different level simply finds no recorded source and is
 * ignored rather than corrupting either list; `.stop` on dragstart/drop
 * keeps a nested drag from bubbling into an ancestor level's handler.
 * Insert/duplicate/delete/settings-editing do work at every depth.
 */
const props = defineProps<{
  blocks: BlockInstance[]
  path: number[]
  selectedPath: number[] | null
}>()

const emit = defineEmits<{
  select: [path: number[]]
  addNested: [blocks: BlockInstance[]]
  duplicate: [payload: { blocks: BlockInstance[], index: number }]
  remove: [payload: { blocks: BlockInstance[], index: number }]
  reorder: [payload: { blocks: BlockInstance[], from: number, to: number }]
}>()

const dragIndex = ref<number | null>(null)

function isSelected(index: number): boolean {
  const selected = props.selectedPath
  const own = [...props.path, index]

  return selected !== null && selected.length === own.length && selected.every((value, i) => value === own[i])
}

function handleDrop(index: number) {
  const from = dragIndex.value
  dragIndex.value = null

  if (from === null || from === index) return

  emit('reorder', { blocks: props.blocks, from, to: index })
}
</script>

<style scoped>
ul {
  list-style: none;
  margin: 0;
  padding: 0;
}

/* Only nested levels indent — a section's own block list sits flush. */
ul ul {
  margin-left: var(--space-4);
  border-left: 1px solid var(--color-border);
  padding-left: var(--space-3);
}

.row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-sm);
}

.row.selected {
  background: var(--color-accent-bg);
}

.grip {
  cursor: grab;
  color: var(--color-text-subtle);
}

.select {
  background: none;
  border: 0;
  padding: 0;
  cursor: pointer;
  color: inherit;
}
</style>
