<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<style>
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: var(--font-sans);
  font-size: var(--text-base);
  color: var(--color-text);
  background: var(--color-bg);
}

h1,
h2,
h3 {
  font-weight: var(--font-weight-semibold);
}

h2 {
  font-size: var(--text-lg);
  margin: 0 0 var(--space-3);
}

form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  max-width: 360px;
}

input,
select,
textarea {
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  font-family: inherit;
  font-size: var(--text-base);
  color: var(--color-text);
  background: var(--color-surface);
}

input:focus-visible,
select:focus-visible,
textarea:focus-visible,
button:focus-visible,
a:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 1px;
}

button {
  font-family: inherit;
}

button[type='submit'] {
  padding: var(--space-2) var(--space-4);
  background: var(--color-text);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  font-size: var(--text-base);
  font-weight: var(--font-weight-medium);
  cursor: pointer;
}

button[type='submit']:hover {
  background: var(--color-accent);
}

button[type='submit']:disabled {
  opacity: 0.6;
  cursor: default;
}

.error {
  color: var(--color-danger);
  font-size: var(--text-sm);
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-base);
}

th,
td {
  text-align: left;
  padding: var(--space-3);
  border-bottom: 1px solid var(--color-border);
}

th {
  color: var(--color-text-muted);
  font-weight: var(--font-weight-medium);
  font-size: var(--text-sm);
}

a {
  color: var(--color-accent);
}

/*
 * Shared across every detail page's own template (Products/Orders/
 * Payments) — previously copy-pasted identically into each page's
 * scoped <style> block; consolidated here once (spec section 17: fix
 * component/style duplication before finishing).
 */
section {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  margin-bottom: var(--space-5);
}

section h2 {
  margin-top: 0;
}

.kv th {
  text-align: left;
  padding: var(--space-2) var(--space-4) var(--space-2) 0;
  color: var(--color-text-muted);
  font-weight: var(--font-weight-medium);
  width: 1%;
  white-space: nowrap;
}

.kv td {
  padding: var(--space-2) 0;
  border-bottom: none;
}
</style>
