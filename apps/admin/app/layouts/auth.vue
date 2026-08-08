<template>
  <div class="auth-shell">
    <div class="auth-card">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg);
}

.auth-card {
  width: 100%;
  max-width: 360px;
  padding: var(--space-8);
}
</style>
