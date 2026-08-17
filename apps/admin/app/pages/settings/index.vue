<template>
  <p>Loading…</p>
</template>

<script setup lang="ts">
/**
 * /settings itself has no content of its own — same "just a redirect"
 * pattern as the pre-refactor root index.vue, landing on the first real
 * settings section.
 */
const router = useRouter()

onMounted(() => {
  router.replace('/stores')
})
</script>
