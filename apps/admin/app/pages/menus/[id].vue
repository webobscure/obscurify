<template>
  <div>
    <PageHeader
      :title="menu?.name ?? 'Menu'"
      :breadcrumbs="[{ label: 'Menus', to: '/menus' }, { label: menu?.name ?? 'Menu' }]"
    />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else-if="menu">
      <table class="kv">
        <tbody>
          <tr><th>Handle</th><td><code>{{ menu.handle }}</code></td></tr>
          <tr><th>Items</th><td>{{ flatItems.length }}</td></tr>
        </tbody>
      </table>

      <section>
        <h2>Items</h2>
        <MenuItemTree
          v-if="menu.items?.length"
          :items="menu.items"
          :deleting-id="deletingId"
          @edit="startEdit"
          @remove="handleDelete"
        />
        <p v-else>No items yet.</p>
        <p v-if="error" class="error">{{ error }}</p>
      </section>

      <section>
        <h2>{{ editingId ? 'Edit item' : 'Add an item' }}</h2>
        <p class="hint">
          Only a <code>url</code> item carries its own address; every other
          target type points at a record by raw ID — there is no lookup
          endpoint to pick a page or product by name yet.
        </p>
        <form class="grid" @submit.prevent="handleSubmit">
          <input v-model="form.label" type="text" placeholder="Label" required >

          <select v-model="form.target_type">
            <option v-for="type in menuItemTargetTypes" :key="type" :value="type">{{ type }}</option>
          </select>

          <input
            v-if="form.target_type === 'url'"
            v-model="form.url"
            type="text"
            placeholder="URL (https://… or /path)"
            required
          >
          <input v-else v-model="form.target_id" type="text" placeholder="Target ID" required >

          <select v-model="form.parent_id">
            <option value="">No parent — top level</option>
            <option v-for="option in parentOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>

          <input v-model.number="form.position" type="number" min="0" placeholder="Position" >

          <div class="actions">
            <button type="submit" :disabled="submitting">
              {{ submitting ? 'Saving…' : editingId ? 'Save item' : 'Add item' }}
            </button>
            <button v-if="editingId" type="button" @click="resetForm">Cancel</button>
          </div>
        </form>
        <p v-if="formError" class="error">{{ formError }}</p>
      </section>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { Menu, MenuItem, MenuItemTargetType } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'
import { menuItemTargetTypes } from '@obscurify/types'

const route = useRoute()
const menuId = computed(() => route.params.id as string)
const activeStore = useActiveStore()

const menu = ref<Menu | null>(null)
const form = reactive<{
  label: string
  target_type: MenuItemTargetType
  target_id: string
  url: string
  parent_id: string
  position: number
}>({ label: '', target_type: 'url', target_id: '', url: '', parent_id: '', position: 0 })

const editingId = ref<string | null>(null)
const pending = ref(true)
const submitting = ref(false)
const deletingId = ref<string | null>(null)
const error = ref<string | null>(null)
const formError = ref<string | null>(null)

/** Depth-first walk of the nested tree — the parent select needs one flat list. */
function flatten(items: MenuItem[], depth = 0): { id: string, label: string }[] {
  return items.flatMap(item => [
    { id: item.id, label: `${'— '.repeat(depth)}${item.label}` },
    ...flatten(item.children ?? [], depth + 1),
  ])
}

const flatItems = computed(() => flatten(menu.value?.items ?? []))

/**
 * An item cannot be its own parent, and the backend also rejects a
 * `parent_id` from another menu — so the only case to filter here is the
 * item being edited. Its descendants stay listed: reparenting under one
 * would orphan the branch, but that is the backend's rule to enforce.
 */
const parentOptions = computed(() => flatItems.value.filter(option => option.id !== editingId.value))

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  try {
    const response = await useApi().menus.get(menuId.value)
    menu.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

function resetForm() {
  editingId.value = null
  form.label = ''
  form.target_type = 'url'
  form.target_id = ''
  form.url = ''
  form.parent_id = ''
  form.position = 0
  formError.value = null
}

function startEdit(item: MenuItem) {
  editingId.value = item.id
  form.label = item.label
  form.target_type = item.target_type
  form.target_id = item.target_id ?? ''
  form.url = item.url ?? ''
  form.parent_id = item.parent_id ?? ''
  form.position = item.position
  formError.value = null
}

async function handleSubmit() {
  submitting.value = true
  formError.value = null

  // The backend requires `url` only for a url target and `target_id` only
  // for every other one — send exactly the relevant half so a stale value
  // left in the other input never reaches it.
  const payload = {
    label: form.label,
    target_type: form.target_type,
    target_id: form.target_type === 'url' ? null : form.target_id,
    url: form.target_type === 'url' ? form.url : null,
    parent_id: form.parent_id || null,
    // `v-model.number` yields '' for a cleared number input, which the
    // backend's `integer` rule would reject — fall back to the front.
    position: Number.isFinite(form.position) ? form.position : 0,
  }

  try {
    if (editingId.value) {
      await useApi().menuItems.update(editingId.value, payload)
    } else {
      await useApi().menus.addItem(menuId.value, payload)
    }
    resetForm()
    await load()
  } catch (e) {
    formError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    submitting.value = false
  }
}

async function handleDelete(item: MenuItem) {
  deletingId.value = item.id
  error.value = null
  try {
    await useApi().menuItems.remove(item.id)
    if (editingId.value === item.id) resetForm()
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    deletingId.value = null
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
watch(menuId, load)
</script>

<style scoped>
.grid {
  display: grid;
  gap: 0.5rem;
  max-width: 480px;
}

.actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}
</style>
