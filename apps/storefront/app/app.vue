<template>
  <div class="shell">
    <NuxtRouteAnnouncer />
    <header class="topbar">
      <NuxtLink to="/" class="logo">{{ store?.name ?? 'Store' }}</NuxtLink>
      <nav>
        <NuxtLink to="/products">Products</NuxtLink>
      </nav>
      <div class="spacer" />
      <NuxtLink to="/cart" class="cart-indicator">
        Cart<span v-if="itemCount > 0">({{ itemCount }})</span>
      </NuxtLink>
    </header>
    <main>
      <NuxtPage />
    </main>
    <footer class="footer">
      <p>&copy; {{ new Date().getFullYear() }} {{ store?.name ?? 'Store' }}</p>
    </footer>
  </div>
</template>

<script setup lang="ts">
const store = await useStorefrontStore()
const { itemCount } = useCart()

useSeoMeta({
  titleTemplate: title => title ? `${title} — ${store.value?.name ?? 'Store'}` : (store.value?.name ?? 'Store'),
})
</script>

<style>
body {
  margin: 0;
  font-family: system-ui, sans-serif;
  color: #1a1a1a;
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
  border-bottom: 1px solid #e0e0e0;
}

.logo {
  font-weight: 700;
  text-decoration: none;
  color: inherit;
}

.topbar nav a {
  color: #444;
  text-decoration: none;
}

.spacer {
  flex: 1;
}

.cart-indicator {
  color: inherit;
  text-decoration: none;
  font-weight: 600;
}

main {
  flex: 1;
  padding: 1.5rem;
  max-width: 960px;
  width: 100%;
  margin: 0 auto;
  box-sizing: border-box;
}

.footer {
  padding: 1.5rem;
  text-align: center;
  color: #777;
  border-top: 1px solid #e0e0e0;
}

.error {
  color: #b00020;
}
</style>
