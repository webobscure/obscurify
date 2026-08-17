<template>
  <table class="kv">
    <tbody>
      <tr v-for="row in rows" :key="row.label">
        <th scope="row">{{ row.label }}</th>
        <td>{{ row.value }}</td>
      </tr>
      <slot />
    </tbody>
  </table>
</template>

<script setup lang="ts">
/**
 * Formalizes the `.kv` global class (app.vue) — the key/value detail
 * table convention already used ad hoc by ~15 pages (orders, customers,
 * fulfillments, shipments, payments, refunds, returns, menus, pages,
 * apps, themes, russian-commerce receipts per the milestone-27 audit) —
 * into a real component. `rows` covers the plain-string case; the
 * default slot covers rows needing rich content (a link, a StatusBadge)
 * beyond a plain string, which several of those pages need.
 */
defineProps<{ rows?: Array<{ label: string; value: string | number }> }>()
</script>

<style scoped>
.kv {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

.kv th {
  text-align: left;
  padding: var(--space-2) var(--space-4) var(--space-2) 0;
  color: var(--color-text-muted);
  font-weight: var(--font-weight-medium);
  width: 1%;
  white-space: nowrap;
  vertical-align: top;
}

.kv td {
  padding: var(--space-2) 0;
  color: var(--color-text);
  vertical-align: top;
}

.kv :deep(tr:not(:first-child) th),
.kv :deep(tr:not(:first-child) td) {
  border-top: var(--border-width) solid var(--color-border);
}
</style>
