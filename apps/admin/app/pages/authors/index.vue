<template>
  <div>
    <PageHeader title="Authors" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p class="hint">Bylines for blog posts — see <NuxtLink to="/blogs">Blogs</NuxtLink>.</p>

      <table v-if="authors.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Bio</th>
            <th>Avatar path</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="author in authors" :key="author.id">
            <td><input v-model="drafts[author.id]!.name" type="text" ></td>
            <td><input v-model="drafts[author.id]!.bio" type="text" ></td>
            <td><input v-model="drafts[author.id]!.avatar_path" type="text" ></td>
            <td class="actions">
              <button type="button" :disabled="savingId === author.id" @click="handleSave(author.id)">
                {{ savingId === author.id ? 'Saving…' : 'Save' }}
              </button>
              <button type="button" :disabled="deletingId === author.id" @click="handleDelete(author.id)">
                {{ deletingId === author.id ? 'Deleting…' : 'Delete' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>No authors yet.</p>

      <h2>Create an author</h2>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.name" type="text" placeholder="Name" required >
        <textarea v-model="form.bio" rows="3" placeholder="Bio" />
        <input v-model="form.avatar_path" type="text" placeholder="Avatar path" >
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create author' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Author } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const authors = ref<Author[]>([])
/** Rebuilt wholesale on every load so a deleted author leaves no stale draft. */
const drafts = ref<Record<string, { name: string, bio: string, avatar_path: string }>>({})
const form = reactive<{ name: string, bio: string, avatar_path: string }>({ name: '', bio: '', avatar_path: '' })
const creating = ref(false)
const savingId = ref<string | null>(null)
const deletingId = ref<string | null>(null)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().authors.list()
  authors.value = response.data

  drafts.value = Object.fromEntries(authors.value.map(author => [
    author.id,
    { name: author.name, bio: author.bio ?? '', avatar_path: author.avatar_path ?? '' },
  ]))
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().authors.create({
      name: form.name,
      bio: form.bio || null,
      avatar_path: form.avatar_path || null,
    })
    form.name = ''
    form.bio = ''
    form.avatar_path = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

async function handleSave(authorId: string) {
  const draft = drafts.value[authorId]
  if (!draft) return

  savingId.value = authorId
  error.value = null
  try {
    await useApi().authors.update(authorId, {
      name: draft.name,
      bio: draft.bio || null,
      avatar_path: draft.avatar_path || null,
    })
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingId.value = null
  }
}

async function handleDelete(authorId: string) {
  deletingId.value = authorId
  error.value = null
  try {
    await useApi().authors.remove(authorId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    deletingId.value = null
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
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
  margin: 0 0 1rem;
}

td input {
  width: 100%;
}
</style>
