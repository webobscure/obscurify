<template>
  <div>
    <PageHeader title="Blogs" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="blogs.length">
        <thead>
          <tr>
            <th>Title</th>
            <th>Slug</th>
            <th>Posts</th>
            <th>Created</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="blog in blogs" :key="blog.id">
            <td>{{ blog.title }}</td>
            <td>{{ blog.slug }}</td>
            <td>{{ blog.posts_count }}</td>
            <td>{{ blog.created_at }}</td>
            <td><NuxtLink :to="`/blogs/${blog.id}`">Posts</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No blogs yet.</p>

      <h2>Create a blog</h2>
      <p class="hint">
        A blog is a container for posts — bylines come from
        <NuxtLink to="/authors">Authors</NuxtLink>.
      </p>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.title" type="text" placeholder="Title (News)" required >
        <input v-model="form.slug" type="text" placeholder="Slug (news)" required >
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create blog' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Blog } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const blogs = ref<Blog[]>([])
const form = reactive<{ title: string, slug: string }>({ title: '', slug: '' })
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().blogs.list()
  blogs.value = response.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().blogs.create({ title: form.title, slug: form.slug })
    form.title = ''
    form.slug = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
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

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}
</style>
