<template>
  <p>Loading…</p>
</template>

<script setup lang="ts">
// Milestone 27 (Navigation Refactor): the front door now lands on Orders,
// the first daily module — /stores moved into the Settings workspace and
// is no longer a sensible landing page for it to redirect to.
const router = useRouter()

onMounted(() => {
  router.replace('/orders')
})
</script>
