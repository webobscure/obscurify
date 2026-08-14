<template>
  <div>
    <PageHeader
      :title="blog?.title ?? 'Blog'"
      :breadcrumbs="[{ label: 'Blogs', to: '/blogs' }, { label: blog?.title ?? 'Blog' }]"
    />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else-if="blog">
      <table class="kv">
        <tbody>
          <tr><th>Slug</th><td>{{ blog.slug }}</td></tr>
          <tr><th>Posts</th><td>{{ blog.posts_count }}</td></tr>
        </tbody>
      </table>

      <section>
        <h2>Posts</h2>
        <table v-if="posts.length">
          <thead>
            <tr>
              <th>Title</th>
              <th>Slug</th>
              <th>Author</th>
              <th>Status</th>
              <th>Published</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="post in posts" :key="post.id">
              <td>{{ post.title }}</td>
              <td>{{ post.slug }}</td>
              <td>{{ post.author?.name ?? '—' }}</td>
              <td>{{ post.status }}</td>
              <td>{{ post.published_at ?? post.scheduled_at ?? '—' }}</td>
              <td><NuxtLink :to="`/blog-posts/${post.id}`">Edit</NuxtLink></td>
            </tr>
          </tbody>
        </table>
        <p v-else>No posts yet.</p>
      </section>

      <section>
        <h2>Write a post</h2>
        <p class="hint">
          A post starts as a draft, or as <code>scheduled</code> if you give it
          a future publish date — the status follows from that date, it is not
          chosen here.
        </p>
        <form class="grid" @submit.prevent="handleCreate">
          <input v-model="form.title" type="text" placeholder="Title" required >
          <input v-model="form.slug" type="text" placeholder="Slug (hello-world)" required >
          <select v-model="form.author_id">
            <option value="">No author</option>
            <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
          </select>
          <textarea v-model="form.excerpt" rows="2" placeholder="Excerpt" />
          <textarea v-model="form.body" rows="10" placeholder="Body" required />
          <label class="field">
            Schedule for
            <input v-model="form.scheduled_at" type="datetime-local" >
          </label>
          <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create post' }}</button>
        </form>
        <p v-if="error" class="error">{{ error }}</p>
      </section>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { Author, Blog, BlogPost } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const blogId = computed(() => route.params.id as string)
const activeStore = useActiveStore()

const blog = ref<Blog | null>(null)
const posts = ref<BlogPost[]>([])
const authors = ref<Author[]>([])
const form = reactive<{ title: string, slug: string, author_id: string, excerpt: string, body: string, scheduled_at: string }>({
  title: '',
  slug: '',
  author_id: '',
  excerpt: '',
  body: '',
  scheduled_at: '',
})

const pending = ref(true)
const creating = ref(false)
const error = ref<string | null>(null)

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  try {
    const [blogResponse, postsResponse, authorsResponse] = await Promise.all([
      useApi().blogs.get(blogId.value),
      useApi().blogs.posts(blogId.value),
      useApi().authors.list(),
    ])
    blog.value = blogResponse.data
    posts.value = postsResponse.data
    authors.value = authorsResponse.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().blogPosts.create(blogId.value, {
      title: form.title,
      slug: form.slug,
      author_id: form.author_id || null,
      excerpt: form.excerpt || null,
      body: form.body,
      scheduled_at: form.scheduled_at || null,
    })
    form.title = ''
    form.slug = ''
    form.author_id = ''
    form.excerpt = ''
    form.body = ''
    form.scheduled_at = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
watch(blogId, load)
</script>

<style scoped>
.grid {
  display: grid;
  gap: 0.5rem;
  max-width: 640px;
}

.field {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}

textarea {
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 0.5rem;
}
</style>
