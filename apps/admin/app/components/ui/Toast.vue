<template>
  <Teleport to="body">
    <div class="toast-stack" aria-live="polite">
      <TransitionGroup name="toast">
        <div v-for="entry in toasts" :key="entry.id" class="toast" :class="entry.variant">
          <AppIcon :name="iconFor(entry.variant)" size="md" class="icon" />
          <p class="message">{{ entry.message }}</p>
          <IconButton icon="close" size="sm" :ariaLabel="t('common.close')" class="dismiss" @click="dismiss(entry.id)" />
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
/**
 * The single, persistent live region every `useToast()` call renders
 * into — mounted once in AdminShell. See docs/design/
 * ADMIN_DESIGN_SYSTEM.md §Toast.
 */
import type { ToastVariant } from '~/composables/useToast'

const { toasts, dismiss } = useToast()
const { t } = useI18n()

function iconFor(variant: ToastVariant) {
  return { danger: 'close', warning: 'notifications', success: 'check', info: 'search' }[variant]
}
</script>

<style scoped>
.toast-stack {
  position: fixed;
  z-index: 200;
  bottom: var(--space-6);
  right: var(--space-6);
  display: flex;
  flex-direction: column-reverse;
  gap: var(--space-2);
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  width: 360px;
  max-width: calc(100vw - var(--space-8));
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  border: var(--border-width) solid;
  background: var(--color-surface-raised);
  box-shadow: var(--shadow-md);
}

.toast.danger { border-color: var(--color-danger-border); }
.toast.warning { border-color: var(--color-warning-border); }
.toast.success { border-color: var(--color-success-border); }
.toast.info { border-color: var(--color-info-border); }

.icon { flex-shrink: 0; margin-top: 1px; }
.toast.danger .icon { color: var(--color-danger); }
.toast.warning .icon { color: var(--color-warning); }
.toast.success .icon { color: var(--color-success); }
.toast.info .icon { color: var(--color-info); }

.message {
  flex: 1;
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-text);
  line-height: var(--leading-normal);
}

.dismiss { flex-shrink: 0; }

.toast-enter-active { transition: opacity var(--duration-slow) var(--ease-decelerate), transform var(--duration-slow) var(--ease-decelerate); }
.toast-leave-active { transition: opacity var(--duration-base) var(--ease-accelerate), transform var(--duration-base) var(--ease-accelerate); position: absolute; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(16px); }
</style>
