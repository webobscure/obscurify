<template>
  <ol class="timeline" :aria-label="ariaLabel">
    <li v-for="group in groups" :key="group.day" class="day-group">
      <p class="day-label">{{ formatDay(group.day) }}</p>
      <ul class="entries">
        <li v-for="(entry, index) in group.entries" :key="entry.id" class="entry">
          <span class="rail" aria-hidden="true">
            <span class="dot"><AppIcon :name="entry.icon" size="sm" /></span>
            <span v-if="!(index === group.entries.length - 1 && group === groups[groups.length - 1])" class="connector" />
          </span>
          <div class="content">
            <p class="title">
              <slot name="title" :entry="entry">{{ entry.title }}</slot>
            </p>
            <p v-if="entry.description" class="description">{{ entry.description }}</p>
            <time class="time" :datetime="entry.occurredAt">{{ formatTime(entry.occurredAt) }}</time>
          </div>
        </li>
      </ul>
    </li>
  </ol>
</template>

<script setup lang="ts">
/**
 * Generic chronological activity feed — day-grouped, icon + subtle
 * connector between entries (GitHub/Linear reference pattern, spec:
 * "merge everything into one chronological feed... group events by
 * date... subtle connectors"). Framework for *any* merged timeline, not
 * Orders-specific — Order Editor's activity feed is its first consumer
 * (see app/utils/orderTimeline.ts for how those entries are built).
 */
export interface TimelineDisplayEntry {
  id: string
  icon: string
  title: string
  description?: string | null
  occurredAt: string
}

defineProps<{
  groups: Array<{ day: string; entries: TimelineDisplayEntry[] }>
  ariaLabel: string
}>()

const { locale } = useI18n()

function intlLocale() {
  return locale.value === 'ru' ? 'ru-RU' : locale.value
}

function formatDay(day: string) {
  return new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'long' }).format(new Date(`${day}T00:00:00`))
}

function formatTime(iso: string) {
  return new Intl.DateTimeFormat(intlLocale(), { timeStyle: 'short' }).format(new Date(iso))
}
</script>

<style scoped>
.timeline {
  list-style: none;
  margin: 0;
  padding: 0;
}

.day-group + .day-group {
  margin-top: var(--space-4);
}

.day-label {
  margin: 0 0 var(--space-2);
  font-size: var(--text-xs);
  font-weight: var(--font-weight-semibold);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-subtle);
}

.entries {
  list-style: none;
  margin: 0;
  padding: 0;
}

.entry {
  display: flex;
  gap: var(--space-3);
}

.rail {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.dot {
  width: 26px;
  height: 26px;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  border: var(--border-width) solid var(--color-border);
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.connector {
  flex: 1;
  width: 1px;
  min-height: var(--space-3);
  background: var(--color-border);
  margin: 2px 0;
}

.content {
  flex: 1;
  min-width: 0;
  padding-bottom: var(--space-4);
}

.title {
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-text);
  line-height: 26px;
}

.description {
  margin: 2px 0 0;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.time {
  display: block;
  margin-top: 2px;
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
  font-variant-numeric: tabular-nums;
}
</style>
