<template>
  <label class="language-switcher">
    <span class="visually-hidden">{{ t('chrome.language') }}</span>
    <select :value="locale" @change="handleChange">
      <option v-for="l in locales" :key="l.code" :value="l.code">{{ l.name }}</option>
    </select>
  </label>
</template>

<script setup lang="ts">
/**
 * Runtime language switching (spec section 4/14) — `setLocale()` both
 * updates the reactive `$t()` output immediately and persists the
 * choice via Vue I18n's own `admin_locale` cookie (spec: "Persist
 * language preference"). When an admin session is active, ALSO saves
 * it server-side onto the User row (`PATCH /me/locale`) — that's what
 * LocaleResolver actually reads for a future *server-rendered* concern
 * (email/notification locale for this admin user), which a
 * client-only cookie can never drive.
 */
const { t, locale, locales, setLocale } = useI18n()
const auth = useAuth()

async function handleChange(event: Event) {
  const next = (event.target as HTMLSelectElement).value as 'ru' | 'en' | 'de'

  await setLocale(next)

  if (auth.isAuthenticated.value) {
    try {
      await useApi().auth.updateLocale(next)
    } catch {
      // A failed server-side save never blocks the language actually
      // switching in the UI — the cookie-based preference above
      // already took effect regardless.
    }
  }
}
</script>

<style scoped>
.language-switcher select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-bg);
  color: inherit;
  font-size: var(--text-sm);
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
