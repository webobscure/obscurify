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
 * Runtime language switching (spec sections 4/15). `setLocale()`
 * updates the reactive `$t()` output and Vue I18n's own cookie
 * immediately; `useStorefrontApi().locale.update()` additionally
 * persists the SAME preference server-side as the `storefront_locale`
 * cookie the backend's own LocaleResolver reads (see
 * StorefrontLocaleController) — both sides end up agreeing on one
 * cookie name/value, never a client-only preference the API doesn't
 * know about.
 */
const { t, locale, locales, setLocale } = useI18n()

async function handleChange(event: Event) {
  const next = (event.target as HTMLSelectElement).value as 'ru' | 'en' | 'de'

  await setLocale(next)

  try {
    await useStorefrontApi().locale.update(next)
  } catch {
    // The client-side switch above already took effect regardless of
    // whether the backend round-trip succeeds.
  }
}
</script>

<style scoped>
.language-switcher select {
  padding: 0.25rem 0.5rem;
  border: 1px solid #e0e0e0;
  border-radius: 0.25rem;
  background: white;
  color: inherit;
  font-size: 0.85rem;
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
