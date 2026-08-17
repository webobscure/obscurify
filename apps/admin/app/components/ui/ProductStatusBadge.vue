<template>
  <StatusBadge :label="label" :variant="variant" />
</template>

<script setup lang="ts">
import type { StatusBadgeVariant } from './StatusBadge.vue'

/**
 * Maps the real ProductStatus enum (draft/active/archived — see
 * apps/api/app/Domain/Catalog/Enums/ProductStatus.php) to a StatusBadge
 * variant. Kept as a tiny domain-specific wrapper around the generic
 * primitive rather than hardcoding a color per page, per
 * docs/design/DESIGN_SYSTEM.md section 12.
 */
const props = defineProps<{ status: 'draft' | 'active' | 'archived' | string }>()

const { t } = useI18n()

const variant = computed<StatusBadgeVariant>(() => {
  switch (props.status) {
    case 'active': return 'success'
    case 'archived': return 'warning'
    case 'draft': return 'neutral'
    default: return 'neutral'
  }
})

const label = computed(() => {
  switch (props.status) {
    case 'active': return t('productStatus.active')
    case 'archived': return t('productStatus.archived')
    case 'draft': return t('productStatus.draft')
    default: return props.status
  }
})
</script>
