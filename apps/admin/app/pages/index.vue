<template>
  <p>Loading…</p>
</template>

<script setup lang="ts">
const router = useRouter()

onMounted(() => {
  router.replace('/stores')
})
</script>
