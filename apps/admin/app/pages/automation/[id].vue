<template>
  <div>
    <PageHeader
      :title="workflow?.name ?? 'Workflow'"
      :breadcrumbs="[{ label: 'Automation', to: '/automation' }, { label: workflow?.name ?? '' }]"
    />

    <p v-if="error" class="error">{{ error }}</p>

    <template v-if="workflow">
      <div class="status-row">
        <span class="badge" :class="workflow.status">{{ workflow.status }}</span>

        <button v-if="workflow.status === 'draft' || workflow.status === 'published'" type="button" :disabled="publishing" @click="handlePublish">
          {{ publishing ? 'Publishing…' : 'Publish' }}
        </button>
        <button v-if="workflow.status === 'published'" type="button" :disabled="lifecycleBusy" @click="handleDisable">Disable</button>
        <button v-if="workflow.status === 'disabled'" type="button" :disabled="lifecycleBusy" @click="handleEnable">Enable</button>
        <button v-if="workflow.status !== 'archived'" type="button" class="danger" :disabled="lifecycleBusy" @click="handleArchive">Archive</button>
      </div>

      <form class="details" @submit.prevent="handleSave">
        <label>
          Name
          <input v-model="form.name" type="text">
        </label>
        <label>
          Description
          <input v-model="form.description" type="text">
        </label>

        <h2>Trigger</h2>
        <p class="hint">A workflow runs whenever this platform event fires.</p>
        <select v-model="form.triggerEventType">
          <option value="">Select an event…</option>
          <option v-for="t in triggers" :key="t.event_type" :value="t.event_type">
            {{ t.label }} ({{ t.origin }})
          </option>
        </select>

        <h2>Conditions</h2>
        <p class="hint">Combined with AND at the top level. No conditions means the workflow always runs.</p>
        <datalist id="workflow-variable-keys">
          <option v-for="v in variables" :key="`${v.source}.${v.key}`" :value="`${v.source}.${v.key}`">{{ v.label }}</option>
        </datalist>
        <WorkflowConditionBuilder :nodes="form.conditions" @update="form.conditions = $event" />

        <h2>Actions</h2>
        <WorkflowActionBuilder :actions="form.actions" @update="form.actions = $event" />

        <div class="save-row">
          <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
        </div>
      </form>

      <section class="versions">
        <h2>Version history</h2>
        <table v-if="versions.length">
          <thead>
            <tr>
              <th>Version</th>
              <th>Status</th>
              <th>Trigger</th>
              <th/>
            </tr>
          </thead>
          <tbody>
            <tr v-for="version in versions" :key="version.id">
              <td>{{ version.version_number }}</td>
              <td>{{ version.status }}</td>
              <td>{{ version.trigger?.event_type ?? '—' }}</td>
              <td>
                <button v-if="version.status === 'archived'" type="button" :disabled="lifecycleBusy" @click="handleRollback(version.id)">
                  Roll back to this version
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { Workflow, WorkflowActionInput, WorkflowConditionInput, WorkflowTriggerCatalogEntry, WorkflowVariableCatalogEntry, WorkflowVersion } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const workflowId = route.params.id as string

const workflow = ref<Workflow | null>(null)
const versions = ref<WorkflowVersion[]>([])
const triggers = ref<WorkflowTriggerCatalogEntry[]>([])
const variables = ref<WorkflowVariableCatalogEntry[]>([])

const form = reactive<{ name: string, description: string, triggerEventType: string, conditions: WorkflowConditionInput[], actions: WorkflowActionInput[] }>({
  name: '',
  description: '',
  triggerEventType: '',
  conditions: [],
  actions: [],
})

const pending = ref(true)
const saving = ref(false)
const publishing = ref(false)
const lifecycleBusy = ref(false)
const error = ref<string | null>(null)

function toConditionInput(node: { boolean_operator: string | null, variable_key: string | null, operator: string | null, value: unknown, children: unknown[] }): WorkflowConditionInput {
  if (node.boolean_operator) {
    return { boolean_operator: node.boolean_operator as WorkflowConditionInput['boolean_operator'], children: (node.children as typeof node[]).map(toConditionInput) }
  }

  return { variable_key: node.variable_key ?? undefined, operator: node.operator as WorkflowConditionInput['operator'], value: node.value }
}

async function load() {
  pending.value = true
  error.value = null
  try {
    const api = useApi()
    const [workflowResponse, versionsResponse, triggersResponse, variablesResponse] = await Promise.all([
      api.automation.workflows.get(workflowId),
      api.automation.workflows.versions(workflowId),
      api.automation.triggers(),
      api.automation.variables(),
    ])

    workflow.value = workflowResponse.data
    versions.value = versionsResponse.data
    triggers.value = triggersResponse.data
    variables.value = variablesResponse.data

    const version = workflowResponse.data.version
    form.name = workflowResponse.data.name
    form.description = workflowResponse.data.description ?? ''
    form.triggerEventType = version?.trigger?.event_type ?? ''
    form.conditions = (version?.conditions ?? []).map(toConditionInput)
    form.actions = (version?.actions ?? []).map(a => ({ type: a.type, config: a.config }))
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleSave() {
  saving.value = true
  error.value = null
  try {
    const response = await useApi().automation.workflows.update(workflowId, {
      name: form.name,
      description: form.description || null,
      trigger: form.triggerEventType ? { event_type: form.triggerEventType } : null,
      conditions: form.conditions,
      actions: form.actions,
    })
    workflow.value = response.data
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function handlePublish() {
  publishing.value = true
  error.value = null
  try {
    await useApi().automation.workflows.publish(workflowId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    publishing.value = false
  }
}

async function handleDisable() {
  lifecycleBusy.value = true
  error.value = null
  try {
    await useApi().automation.workflows.disable(workflowId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    lifecycleBusy.value = false
  }
}

async function handleEnable() {
  lifecycleBusy.value = true
  error.value = null
  try {
    await useApi().automation.workflows.enable(workflowId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    lifecycleBusy.value = false
  }
}

async function handleArchive() {
  if (!confirm('Archive this workflow? It will stop running and this cannot be undone.')) return
  lifecycleBusy.value = true
  error.value = null
  try {
    await useApi().automation.workflows.archive(workflowId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    lifecycleBusy.value = false
  }
}

async function handleRollback(versionId: string) {
  if (!confirm('Roll back to this version? A new version will be created with its content and published.')) return
  lifecycleBusy.value = true
  error.value = null
  try {
    await useApi().automation.workflows.rollback(workflowId, versionId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    lifecycleBusy.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.status-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
}

.status-row button {
  padding: var(--space-1) var(--space-3);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
}

.status-row .danger {
  border-color: var(--color-danger, #c00);
  color: var(--color-danger, #c00);
}

.badge {
  display: inline-block;
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.badge.published {
  background: #e6f4ea;
  color: #1e7e34;
}

.badge.disabled {
  background: #fdf3e0;
  color: #a06400;
}

.badge.archived {
  background: #f3f3f3;
  color: #888;
}

.details {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  max-width: 48rem;
  margin-bottom: var(--space-6);
}

.details > label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.details input,
.details select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0;
}

.save-row {
  display: flex;
}

.save-row button {
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.versions table {
  width: 100%;
  border-collapse: collapse;
}

.versions th,
.versions td {
  text-align: left;
  padding: var(--space-2);
  border-bottom: 1px solid var(--color-border);
}
</style>
