<template>
  <ul>
    <li v-for="item in items" :key="item.id">
      <div class="row">
        <span class="label">{{ item.label }}</span>
        <code>{{ item.target_type }}</code>
        <span class="target">{{ item.target_type === 'url' ? item.url ?? '—' : item.target_id ?? '—' }}</span>
        <span class="position">#{{ item.position }}</span>
        <button type="button" @click="emit('edit', item)">Edit</button>
        <button type="button" :disabled="deletingId === item.id" @click="emit('remove', item)">
          {{ deletingId === item.id ? 'Deleting…' : 'Delete' }}
        </button>
      </div>

      <MenuItemTree
        v-if="item.children?.length"
        :items="item.children"
        :deleting-id="deletingId"
        @edit="emit('edit', $event)"
        @remove="emit('remove', $event)"
      />
    </li>
  </ul>
</template>

<script setup lang="ts">
import type { MenuItem } from '@obscurify/types'

/**
 * Renders a menu's nested item tree. Purely presentational and recursive
 * (a SFC may reference itself by filename) — every mutation is emitted
 * back to the page, which owns all API calls, so nested levels never
 * fetch on their own.
 */
defineProps<{ items: MenuItem[], deletingId: string | null }>()

const emit = defineEmits<{ edit: [item: MenuItem], remove: [item: MenuItem] }>()
</script>

<style scoped>
ul {
  list-style: none;
  margin: 0;
  padding: 0;
}

/* Only nested levels indent — the top-level list sits flush. */
ul ul {
  margin-left: var(--space-4);
  border-left: 1px solid var(--color-border);
  padding-left: var(--space-3);
}

.row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0;
}

.label {
  font-weight: var(--font-weight-semibold);
}

.target,
.position {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
