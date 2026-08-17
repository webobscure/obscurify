<template>
  <div class="alert" :class="variant" :role="variant === 'danger' || variant === 'warning' ? 'alert' : 'status'">
    <AppIcon :name="iconName" size="md" class="icon" />
    <div class="content">
      <p v-if="title" class="title">{{ title }}</p>
      <p class="body"><slot /></p>
    </div>
    <IconButton v-if="dismissible" icon="close" size="sm" :ariaLabel="t('common.close')" class="dismiss" @click="emit('dismiss')" />
  </div>
</template>

<script setup lang="ts">
/**
 * Inline, persistent page/section-level message — replaces the bare
 * `<p class="error">` pattern with danger/warning/success/info variants.
 * See docs/design/ADMIN_DESIGN_SYSTEM.md §Alert.
 */
const props = withDefaults(defineProps<{
  variant?: 'danger' | 'warning' | 'success' | 'info'
  title?: string
  dismissible?: boolean
}>(), { variant: 'info' })

const emit = defineEmits<{ dismiss: [] }>()
const { t } = useI18n()

const iconName = computed(() => ({
  danger: 'close',
  warning: 'notifications',
  success: 'check',
  info: 'search',
}[props.variant]))
</script>

<style scoped>
.alert {
  display: flex;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  border: var(--border-width) solid;
}

.alert.danger { background: var(--color-danger-bg); border-color: var(--color-danger-border); color: var(--color-danger); }
.alert.warning { background: var(--color-warning-bg); border-color: var(--color-warning-border); color: var(--color-warning); }
.alert.success { background: var(--color-success-bg); border-color: var(--color-success-border); color: var(--color-success); }
.alert.info { background: var(--color-info-bg); border-color: var(--color-info-border); color: var(--color-info); }

.icon { flex-shrink: 0; margin-top: 1px; }

.content { flex: 1; min-width: 0; }

.title {
  margin: 0 0 2px;
  font-size: var(--text-base);
  font-weight: var(--font-weight-medium);
  color: var(--color-text);
}

.body {
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-text);
  line-height: var(--leading-normal);
}

.dismiss { flex-shrink: 0; color: inherit; }
.dismiss:hover { background: rgba(0, 0, 0, 0.06); }
</style>
