<template>
  <div>
    <PageHeader
      :title="post?.title ?? 'Post'"
      :breadcrumbs="breadcrumbs"
    />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else-if="post">
      <table class="kv">
        <tbody>
          <tr><th>Status</th><td>{{ post.status }}</td></tr>
          <tr><th>Published</th><td>{{ post.published_at ?? '—' }}</td></tr>
          <tr><th>Scheduled</th><td>{{ post.scheduled_at ?? '—' }}</td></tr>
        </tbody>
      </table>

      <div class="actions">
        <button type="button" :disabled="publishing" @click="handlePublish">
          {{ publishing ? 'Publishing…' : 'Publish now' }}
        </button>
      </div>
      <p class="hint">Publishing clears any schedule and stamps the post live immediately.</p>

      <p v-if="error" class="error">{{ error }}</p>

      <section>
        <h2>Content</h2>
        <form class="grid" @submit.prevent="handleSave">
          <input v-model="form.title" type="text" placeholder="Title" required >
          <input v-model="form.slug" type="text" placeholder="Slug" required >
          <select v-model="form.author_id">
            <option value="">No author</option>
            <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
          </select>
          <textarea v-model="form.excerpt" rows="2" placeholder="Excerpt" />
          <textarea v-model="form.body" rows="12" placeholder="Body" required />
          <input v-model="form.featured_image_path" type="text" placeholder="Featured image path" >
          <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save post' }}</button>
        </form>
        <p v-if="saveError" class="error">{{ saveError }}</p>
      </section>

      <section>
        <h2>SEO</h2>
        <p class="hint">Editable at any time — a blog post has no frozen-version rule the way a page does.</p>
        <form class="grid" @submit.prevent="handleSaveSeo">
          <input v-model="seoForm.meta_title" type="text" placeholder="Meta title" maxlength="255" >
          <textarea v-model="seoForm.meta_description" rows="3" placeholder="Meta description" maxlength="500" />
          <input v-model="seoForm.canonical_url" type="text" placeholder="Canonical URL" >
          <input v-model="seoForm.og_image" type="text" placeholder="OG image path" >
          <button type="submit" :disabled="savingSeo">{{ savingSeo ? 'Saving…' : 'Save SEO' }}</button>
        </form>
        <p v-if="seoError" class="error">{{ seoError }}</p>
      </section>
    </template>
    <p v-else-if="pending">Loading…</p>
    <p v-else class="error">That post no longer exists.</p>
  </div>
</template>

<script setup lang="ts">
import type { Author, Blog, BlogPost, SeoMetadata } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const postId = computed(() => route.params.id as string)
const activeStore = useActiveStore()

const post = ref<BlogPost | null>(null)
const blog = ref<Blog | null>(null)
const authors = ref<Author[]>([])
const form = reactive<{ title: string, slug: string, author_id: string, excerpt: string, body: string, featured_image_path: string }>({
  title: '',
  slug: '',
  author_id: '',
  excerpt: '',
  body: '',
  featured_image_path: '',
})
const seoForm = reactive<Record<keyof SeoMetadata, string>>({
  meta_title: '',
  meta_description: '',
  canonical_url: '',
  og_image: '',
})

const pending = ref(true)
const saving = ref(false)
const publishing = ref(false)
const savingSeo = ref(false)
const error = ref<string | null>(null)
const saveError = ref<string | null>(null)
const seoError = ref<string | null>(null)

const breadcrumbs = computed(() => [
  { label: 'Blogs', to: '/blogs' },
  ...(blog.value ? [{ label: blog.value.title, to: `/blogs/${blog.value.id}` }] : []),
  { label: post.value?.title ?? 'Post' },
])

async function findPost(id: string): Promise<{ post: BlogPost, blog: Blog } | null> {
  try {
    const postResponse = await useApi().blogPosts.get(id)
    const blogResponse = await useApi().blogs.get(postResponse.data.blog_id)

    return { post: postResponse.data, blog: blogResponse.data }
  } catch (e) {
    if (e instanceof ApiClientError && e.status === 404) return null
    throw e
  }
}

function applyPost(loaded: BlogPost) {
  post.value = loaded
  form.title = loaded.title
  form.slug = loaded.slug
  form.author_id = loaded.author?.id ?? ''
  form.excerpt = loaded.excerpt ?? ''
  form.body = loaded.body
  form.featured_image_path = loaded.featured_image_path ?? ''
}

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  try {
    const [found, authorsResponse] = await Promise.all([
      findPost(postId.value),
      useApi().authors.list(),
    ])
    authors.value = authorsResponse.data

    if (!found) {
      post.value = null
      return
    }

    blog.value = found.blog
    applyPost(found.post)

    const seoResponse = await useApi().blogPosts.seo.get(found.post.id)
    for (const key of Object.keys(seoForm) as (keyof SeoMetadata)[]) {
      seoForm[key] = seoResponse.data[key] ?? ''
    }
    seoError.value = null
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleSave() {
  saving.value = true
  saveError.value = null
  try {
    const response = await useApi().blogPosts.update(postId.value, {
      title: form.title,
      slug: form.slug,
      author_id: form.author_id || null,
      excerpt: form.excerpt || null,
      body: form.body,
      featured_image_path: form.featured_image_path || null,
    })
    applyPost(response.data)
  } catch (e) {
    saveError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function handlePublish() {
  publishing.value = true
  error.value = null
  try {
    const response = await useApi().blogPosts.publish(postId.value)
    applyPost(response.data)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    publishing.value = false
  }
}

async function handleSaveSeo() {
  savingSeo.value = true
  seoError.value = null
  try {
    // A full upsert of all four columns, so an emptied input clears the
    // stored value rather than leaving the old one behind.
    const response = await useApi().blogPosts.seo.update(postId.value, {
      meta_title: seoForm.meta_title || null,
      meta_description: seoForm.meta_description || null,
      canonical_url: seoForm.canonical_url || null,
      og_image: seoForm.og_image || null,
    })
    for (const key of Object.keys(seoForm) as (keyof SeoMetadata)[]) {
      seoForm[key] = response.data[key] ?? ''
    }
  } catch (e) {
    seoError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingSeo.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
watch(postId, load)
</script>

<style scoped>
.grid {
  display: grid;
  gap: 0.5rem;
  max-width: 640px;
}

.actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.5rem;
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
