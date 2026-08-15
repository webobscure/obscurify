<template>
  <div>
    <PageHeader
      title="Execution"
      :breadcrumbs="[{ label: 'Automation', to: '/automation' }, { label: 'Executions', to: '/automation/executions' }, { label: execution?.id ?? '' }]"
    />

    <p v-if="error" class="error">{{ error }}</p>

    <template v-if="execution">
      <dl class="summary">
        <div><dt>Status</dt><dd><span class="badge" :class="execution.status">{{ execution.status }}</span></dd></div>
        <div><dt>Attempts</dt><dd>{{ execution.attempts }}</dd></div>
        <div><dt>Depth</dt><dd>{{ execution.depth }}</dd></div>
        <div><dt>Started</dt><dd>{{ execution.started_at ?? '—' }}</dd></div>
        <div><dt>Completed</dt><dd>{{ execution.completed_at ?? '—' }}</dd></div>
        <div v-if="execution.next_retry_at"><dt>Next retry</dt><dd>{{ execution.next_retry_at }}</dd></div>
        <div v-if="execution.next_resume_at"><dt>Resumes at</dt><dd>{{ execution.next_resume_at }}</dd></div>
        <div v-if="execution.wait_until_event_type"><dt>Waiting for event</dt><dd>{{ execution.wait_until_event_type }}</dd></div>
        <div v-if="execution.error_message"><dt>Error</dt><dd class="error-text">{{ execution.error_message }}</dd></div>
      </dl>

      <h2>Steps</h2>
      <table v-if="execution.steps?.length">
        <thead>
          <tr>
            <th>#</th>
            <th>Type</th>
            <th>Status</th>
            <th>Output / error</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="step in execution.steps" :key="step.id">
            <td>{{ step.position }}</td>
            <td>{{ step.step_type }}</td>
            <td><span class="badge" :class="step.status">{{ step.status }}</span></td>
            <td>
              <code v-if="step.error_message" class="error-text">{{ step.error_message }}</code>
              <code v-else-if="step.output">{{ JSON.stringify(step.output) }}</code>
              <span v-else>—</span>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>No steps recorded yet.</p>

      <h2>Trigger context</h2>
      <pre class="context">{{ JSON.stringify(execution.context, null, 2) }}</pre>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { WorkflowExecution } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const executionId = route.params.id as string

const execution = ref<WorkflowExecution | null>(null)
const pending = ref(true)
const error = ref<string | null>(null)

async function load() {
  pending.value = true
  error.value = null
  try {
    execution.value = (await useApi().automation.executions.get(executionId)).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.summary {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
  gap: var(--space-3);
  margin-bottom: var(--space-6);
}

.summary dt {
  font-size: var(--text-xs);
  text-transform: uppercase;
  color: var(--color-text-muted);
  letter-spacing: 0.04em;
}

.summary dd {
  margin: 0;
}

.error-text {
  color: var(--color-danger, #c00);
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: var(--space-6);
}

th,
td {
  text-align: left;
  padding: var(--space-2);
  border-bottom: 1px solid var(--color-border);
  vertical-align: top;
}

.badge {
  display: inline-block;
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.badge.completed,
.badge.succeeded {
  background: #e6f4ea;
  color: #1e7e34;
}

.badge.failed,
.badge.dead_letter {
  background: #fdeaea;
  color: #c0392b;
}

.badge.waiting {
  background: #fdf3e0;
  color: #a06400;
}

.context {
  background: var(--color-surface-muted, #f7f7f7);
  padding: var(--space-3);
  border-radius: var(--radius-sm);
  overflow-x: auto;
  font-size: var(--text-sm);
}
</style>
