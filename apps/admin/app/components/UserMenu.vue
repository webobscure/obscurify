<template>
  <div ref="root" class="user-menu">
    <button
      type="button"
      class="trigger"
      aria-haspopup="menu"
      :aria-expanded="open"
      aria-label="User menu"
      @click="open = !open"
    >
      <span class="avatar">{{ initial }}</span>
    </button>

    <div v-if="open" class="menu" role="menu">
      <div class="who">
        <strong>{{ auth.user.value?.name ?? 'Account' }}</strong>
        <small>{{ auth.user.value?.email }}</small>
      </div>
      <hr>
      <button type="button" role="menuitem" class="danger" @click="handleLogout">Sign out</button>
    </div>
  </div>
</template>

<script setup lang="ts">
// Only Sign out is a real, implemented account action — no Profile/
// Account/Settings entries invented for a page that doesn't exist yet
// (spec: "Do not invent missing backend features").
const auth = useAuth()
const activeStore = useActiveStore()
const router = useRouter()
const open = ref(false)
const root = ref<HTMLElement | null>(null)

const initial = computed(() => (auth.user.value?.name?.trim()?.[0] ?? '?').toUpperCase())

async function handleLogout() {
  open.value = false
  await auth.logout()
  activeStore.clear()
  router.push('/login')
}

function handleClickOutside(event: MouseEvent) {
  if (open.value && root.value && !root.value.contains(event.target as Node)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.user-menu {
  position: relative;
}

.trigger {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: var(--radius-full);
  border: none;
  background: var(--color-text);
  color: white;
  cursor: pointer;
  font-weight: var(--font-weight-semibold);
}

.trigger:hover,
.trigger:focus-visible {
  background: var(--color-accent);
}

.menu {
  position: absolute;
  z-index: 20;
  top: calc(100% + var(--space-2));
  right: 0;
  min-width: 220px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
  padding: var(--space-2);
}

.who {
  display: flex;
  flex-direction: column;
  padding: var(--space-2) var(--space-3);
  line-height: 1.3;
}

.who small {
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.menu hr {
  border: none;
  border-top: 1px solid var(--color-border);
  margin: var(--space-2) 0;
}

.menu button {
  display: block;
  width: 100%;
  text-align: left;
  padding: var(--space-2) var(--space-3);
  background: none;
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: var(--text-sm);
  color: var(--color-text);
}

.menu button:hover,
.menu button:focus-visible {
  background: var(--color-bg);
}

.menu button.danger {
  color: var(--color-danger);
}

.menu button.danger:hover,
.menu button.danger:focus-visible {
  background: var(--color-danger-bg);
}
</style>
