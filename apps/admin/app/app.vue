<template>
  <div class="shell">
    <header v-if="auth.isAuthenticated.value" class="topbar">
      <strong>Obscurify Admin</strong>
      <nav>
        <NuxtLink to="/stores">Stores</NuxtLink>
        <NuxtLink to="/products">Products</NuxtLink>
      </nav>
      <div class="spacer" />
      <span v-if="activeStore.store.value">{{ activeStore.store.value.name }}</span>
      <button @click="handleLogout">Log out</button>
    </header>
    <main>
      <NuxtPage />
    </main>
  </div>
</template>

<script setup lang="ts">
const auth = useAuth()
const activeStore = useActiveStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  activeStore.clear()
  router.push('/login')
}
</script>

<style>
body {
  margin: 0;
  font-family: system-ui, sans-serif;
  color: #1a1a1a;
  background: #fafafa;
}

.shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.topbar {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 0.75rem 1.5rem;
  background: #1a1a1a;
  color: white;
}

.topbar a {
  color: #cfcfcf;
  text-decoration: none;
}

.topbar a.router-link-active {
  color: white;
  font-weight: 600;
}

.spacer {
  flex: 1;
}

.topbar button {
  background: transparent;
  border: 1px solid #555;
  color: white;
  padding: 0.35rem 0.75rem;
  border-radius: 4px;
  cursor: pointer;
}

main {
  padding: 1.5rem;
  max-width: 720px;
  width: 100%;
  margin: 0 auto;
}

form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 360px;
}

input {
  padding: 0.5rem;
  border: 1px solid #ccc;
  border-radius: 4px;
}

button[type='submit'] {
  padding: 0.5rem 1rem;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.error {
  color: #b00020;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  text-align: left;
  padding: 0.5rem;
  border-bottom: 1px solid #e0e0e0;
}
</style>
