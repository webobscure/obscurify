<template>
  <div class="page">
    <PageHeader title="Admin Design System" description="Living component gallery — every shared primitive with its states. Not user-facing; a developer reference for docs/design/ADMIN_DESIGN_SYSTEM.md.">
      <template #status><StatusBadge label="v2" variant="info" /></template>
    </PageHeader>

    <Tabs v-model="tab" :tabs="sections">
      <template #foundations>
        <Card class="stack">
          <template #header>Color</template>
          <div class="swatches">
            <div v-for="c in colorSwatches" :key="c.name" class="swatch">
              <span class="swatch-box" :style="{ background: `var(${c.token})` }" />
              <span class="swatch-label">{{ c.name }}</span>
            </div>
          </div>
        </Card>
        <Card class="stack">
          <template #header>Spacing</template>
          <div class="stack-rows">
            <div v-for="s in spacingTokens" :key="s" class="space-row">
              <span class="space-name">--space-{{ s }}</span>
              <span class="space-bar" :style="{ width: `var(--space-${s})` }" />
            </div>
          </div>
        </Card>
        <Card class="stack">
          <template #header>Typography</template>
          <p class="text-xs">--text-xs — table meta, timestamps</p>
          <p class="text-sm">--text-sm — secondary body, labels</p>
          <p class="text-base">--text-base — default body</p>
          <p class="text-lg">--text-lg — h2</p>
          <p class="text-xl">--text-xl — h1</p>
          <p class="text-2xl">--text-2xl — PageHeader title</p>
        </Card>
      </template>

      <template #actions>
        <Card class="stack">
          <template #header>Button</template>
          <div class="row">
            <Button variant="primary">Primary</Button>
            <Button variant="secondary">Secondary</Button>
            <Button variant="danger">Danger</Button>
            <Button variant="ghost">Ghost</Button>
          </div>
          <div class="row">
            <Button size="sm">Small</Button>
            <Button size="md">Medium</Button>
            <Button size="lg">Large</Button>
          </div>
          <div class="row">
            <Button icon="collections">With icon</Button>
            <Button :loading="btnLoading" @click="triggerLoading">{{ btnLoading ? 'Saving…' : 'Trigger loading' }}</Button>
            <Button disabled>Disabled</Button>
          </div>
        </Card>
        <Card class="stack">
          <template #header>IconButton</template>
          <div class="row">
            <IconButton icon="collections" ariaLabel="Collections" size="sm" />
            <IconButton icon="collections" ariaLabel="Collections" size="md" />
            <IconButton icon="collections" ariaLabel="Collections" size="lg" />
            <IconButton icon="close" ariaLabel="Close" variant="danger-ghost" />
            <IconButton icon="search" ariaLabel="Toggle" active />
          </div>
        </Card>
        <Card class="stack">
          <template #header>KeyboardShortcutHint</template>
          <p><KeyboardShortcutHint>⌘</KeyboardShortcutHint> <KeyboardShortcutHint>K</KeyboardShortcutHint> opens search</p>
        </Card>
      </template>

      <template #forms>
        <Card class="stack">
          <template #header>Input / Textarea / Select</template>
          <div class="form-grid">
            <Input v-model="demo.text" label="Label" placeholder="Placeholder" help="Helper text" />
            <Input v-model="demo.text" label="With icon" icon="search" clearable />
            <Input v-model="demo.text" label="Invalid" error="This field is required" />
            <Select v-model="demo.select" label="Select" placeholder="Choose one">
              <option value="a">Option A</option>
              <option value="b">Option B</option>
            </Select>
            <Textarea v-model="demo.textarea" label="Textarea" :rows="3" />
          </div>
        </Card>
        <Card class="stack">
          <template #header>Checkbox / Radio / Switch</template>
          <div class="row">
            <Checkbox v-model="demo.checkbox">Checked state</Checkbox>
            <Checkbox :model-value="false" indeterminate>Indeterminate</Checkbox>
            <Checkbox :model-value="false" disabled>Disabled</Checkbox>
          </div>
          <div class="row">
            <Radio v-model="demo.radio" value="a" name="gallery-radio">Option A</Radio>
            <Radio v-model="demo.radio" value="b" name="gallery-radio">Option B</Radio>
          </div>
          <div class="row">
            <Radio v-model="demo.radioCard" value="standard" name="gallery-radio-card" variant="card">Standard shipping — 5-7 days</Radio>
            <Radio v-model="demo.radioCard" value="express" name="gallery-radio-card" variant="card">Express — 1-2 days</Radio>
          </div>
          <div class="row">
            <Switch v-model="demo.switch" ariaLabel="Notifications enabled" />
            <Switch :model-value="true" ariaLabel="Loading example" loading />
          </div>
        </Card>
        <Card class="stack">
          <template #header>SearchInput</template>
          <SearchInput v-model="demo.search" placeholder="Search…" :searching="searching" />
        </Card>
      </template>

      <template #feedback>
        <Card class="stack">
          <template #header>Alert</template>
          <Alert variant="info" title="Heads up">Informational message with a title.</Alert>
          <Alert variant="success">Saved successfully.</Alert>
          <Alert variant="warning">Some fields need review.</Alert>
          <Alert variant="danger" dismissible>Something went wrong.</Alert>
        </Card>
        <Card class="stack">
          <template #header>Banner</template>
          <Banner variant="info">Store-wide notice rendered full-bleed at the top of a page.</Banner>
        </Card>
        <Card class="stack">
          <template #header>Toast</template>
          <div class="row">
            <Button size="sm" variant="secondary" @click="toast.success('Product saved')">Trigger success</Button>
            <Button size="sm" variant="secondary" @click="toast.danger('Save failed — network error')">Trigger danger</Button>
            <Button size="sm" variant="secondary" @click="toast.warning('3 items low on stock')">Trigger warning</Button>
            <Button size="sm" variant="secondary" @click="toast.info('New version available')">Trigger info</Button>
          </div>
        </Card>
        <Card class="stack">
          <template #header>Spinner / Skeleton</template>
          <div class="row">
            <Spinner size="sm" /> <Spinner size="md" /> <Spinner size="lg" />
          </div>
          <Skeleton variant="text" width="60%" />
          <Skeleton variant="block" height="60px" />
          <Skeleton variant="table-row" />
        </Card>
      </template>

      <template #overlays>
        <Card class="stack">
          <template #header>Modal</template>
          <Button @click="modalOpen = true">Open modal</Button>
          <Modal v-model:open="modalOpen" title="Confirm action" size="sm">
            <p>This is a Modal body. Danger variant swaps the confirm action to a danger Button.</p>
            <template #actions>
              <Button variant="secondary" @click="modalOpen = false">Cancel</Button>
              <Button variant="danger" @click="modalOpen = false">Delete</Button>
            </template>
          </Modal>
        </Card>
        <Card class="stack">
          <template #header>Drawer</template>
          <Button @click="drawerOpen = true">Open drawer</Button>
          <Drawer v-model:open="drawerOpen" title="Detail panel">
            <p>Side-anchored panel for secondary content — same a11y contract as Modal.</p>
          </Drawer>
        </Card>
        <Card class="stack">
          <template #header>Tooltip</template>
          <Tooltip text="Explains what this button does">
            <template #trigger><IconButton icon="collections" ariaLabel="Hover or focus me" /></template>
          </Tooltip>
        </Card>
        <Card class="stack">
          <template #header>Dropdown (menu)</template>
          <Dropdown variant="menu">
            <template #trigger="{ open }">
              Actions <AppIcon name="chevron" size="sm" :style="{ transform: open ? 'rotate(180deg)' : '' }" />
            </template>
            <button role="menuitem" type="button">Edit</button>
            <button role="menuitem" type="button">Duplicate</button>
            <div class="group">
              <button role="menuitem" type="button" class="danger-item">Delete</button>
            </div>
          </Dropdown>
        </Card>
      </template>

      <template #data-display>
        <Card class="stack">
          <template #header>Badge / StatusBadge</template>
          <div class="row">
            <Badge>Neutral</Badge>
            <Badge variant="accent">Accent</Badge>
            <Badge variant="outline">Outline</Badge>
            <Badge removable remove-label="tag">Removable</Badge>
          </div>
          <div class="row">
            <StatusBadge label="Active" variant="success" />
            <StatusBadge label="Pending" variant="warning" />
            <StatusBadge label="Cancelled" variant="danger" />
            <StatusBadge label="Draft" variant="info" />
            <StatusBadge label="Archived" variant="neutral" />
          </div>
        </Card>
        <Card class="stack">
          <template #header>Avatar</template>
          <div class="row">
            <Avatar name="Maria Ivanova" size="sm" />
            <Avatar name="Maria Ivanova" size="md" />
            <Avatar name="Maria Ivanova" size="lg" />
          </div>
        </Card>
        <Card interactive class="stack">
          <template #header>Card (this one is interactive)</template>
          <p>Formalizes the app.vue `section` global styling with header/body/footer slots.</p>
          <template #footer><Button size="sm" variant="secondary">Action</Button></template>
        </Card>
        <Card class="stack">
          <template #header>KeyValueTable</template>
          <KeyValueTable :rows="[{ label: 'Created', value: '17 Aug 2026' }, { label: 'Updated', value: '17 Aug 2026' }, { label: 'Owner', value: 'Maria Ivanova' }]" />
        </Card>
        <Card class="stack">
          <template #header>EmptyState</template>
          <EmptyState title="No results" description="Try adjusting your filters.">
            <template #icon><AppIcon name="search" /></template>
          </EmptyState>
        </Card>
      </template>

      <template #tables>
        <Card class="stack">
          <template #header>Pagination</template>
          <Pagination :page="page" :last-page="4" :per-page="20" :total="73" @update:page="page = $event" />
        </Card>
        <Card class="stack">
          <template #header>DataTable</template>
          <div class="row">
            <Button size="sm" variant="secondary" @click="tableState = 'data'">Populated</Button>
            <Button size="sm" variant="secondary" @click="tableState = 'loading'">Loading</Button>
            <Button size="sm" variant="secondary" @click="tableState = 'empty'">Empty</Button>
            <Button size="sm" variant="secondary" @click="tableState = 'error'">Error</Button>
          </div>
          <DataTable
            :columns="tableColumns"
            :rows="tableState === 'data' ? demoRows : []"
            :row-key="(r) => (r as DemoRow).id"
            :row-label="(r) => (r as DemoRow).name"
            selectable
            :selected="tableSelected"
            :sort-key="tableSort.key"
            :sort-dir="tableSort.dir"
            :loading="tableState === 'loading'"
            :error="tableState === 'error' ? 'Failed to load rows.' : null"
            empty-title="No rows"
            empty-description="Nothing matches the current filters."
            @update:selected="tableSelected = $event"
            @sort="tableSort = $event"
            @retry="tableState = 'data'"
          >
            <template #cell-status="{ row }">
              <StatusBadge :label="(row as DemoRow).status" :variant="statusVariant((row as DemoRow).status)" />
            </template>
            <template #row-actions>
              <IconButton icon="collections" ariaLabel="Row actions" size="sm" />
            </template>
            <template #bulk-actions>
              <Button size="sm" variant="secondary">Archive</Button>
              <Button size="sm" variant="danger">Delete</Button>
            </template>
          </DataTable>
        </Card>
      </template>
    </Tabs>
  </div>
</template>

<script setup lang="ts">
/**
 * Internal developer reference, not a merchant-facing route (not in
 * sidebar nav config) — every shared primitive in app/components/ui/
 * with its documented states, one screen. See docs/design/
 * ADMIN_DESIGN_SYSTEM.md.
 */
interface DemoRow { id: string; name: string; sku: string; price: number; status: string }

const tab = ref('foundations')
const sections = [
  { value: 'foundations', label: 'Foundations' },
  { value: 'actions', label: 'Actions' },
  { value: 'forms', label: 'Forms' },
  { value: 'feedback', label: 'Feedback' },
  { value: 'overlays', label: 'Overlays' },
  { value: 'data-display', label: 'Data display' },
  { value: 'tables', label: 'Tables' },
]

const colorSwatches = [
  { name: 'bg', token: '--color-bg' },
  { name: 'surface', token: '--color-surface' },
  { name: 'surface-muted', token: '--color-surface-muted' },
  { name: 'border', token: '--color-border' },
  { name: 'accent', token: '--color-accent' },
  { name: 'danger', token: '--color-danger' },
  { name: 'success', token: '--color-success' },
  { name: 'warning', token: '--color-warning' },
  { name: 'info', token: '--color-info' },
]
const spacingTokens = [1, 2, 3, 4, 5, 6, 8, 10, 12]

const demo = reactive({ text: '', select: '', textarea: '', checkbox: true, radio: 'a', radioCard: 'standard', switch: true, search: '' })
const searching = ref(false)
const btnLoading = ref(false)
const modalOpen = ref(false)
const drawerOpen = ref(false)
const page = ref(2)
const toast = useToast()

function triggerLoading() {
  btnLoading.value = true
  setTimeout(() => { btnLoading.value = false }, 1500)
}

const tableColumns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'sku', label: 'SKU' },
  { key: 'price', label: 'Price', align: 'right' as const, sortable: true },
  { key: 'status', label: 'Status' },
]

const demoRows: DemoRow[] = [
  { id: '1', name: 'Denim Jacket', sku: 'JCKT-01', price: 299, status: 'Active' },
  { id: '2', name: 'Cotton Tee', sku: 'TEE-04', price: 49, status: 'Draft' },
  { id: '3', name: 'Wool Scarf', sku: 'SCF-02', price: 89, status: 'Archived' },
]

const tableState = ref<'data' | 'loading' | 'empty' | 'error'>('data')
const tableSelected = ref<string[]>([])
const tableSort = ref<{ key: string; dir: 'asc' | 'desc' | null }>({ key: 'name', dir: 'asc' })

function statusVariant(status: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
  return { Active: 'success', Draft: 'info', Archived: 'neutral' }[status] as 'success' | 'info' | 'neutral' ?? 'neutral'
}
</script>

<style scoped>
.page {
  max-width: var(--content-max-width);
  margin-inline: auto;
  padding-inline: var(--content-padding-x);
}

.stack {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin-top: var(--space-4);
}

.row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--space-4);
}

.swatches {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
}

.swatch {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-1);
}

.swatch-box {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-md);
  border: var(--border-width) solid var(--color-border);
}

.swatch-label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.stack-rows {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.space-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.space-name {
  width: 90px;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  font-family: ui-monospace, monospace;
}

.space-bar {
  height: 12px;
  background: var(--color-accent);
  border-radius: var(--radius-sm);
}

.text-xs { font-size: var(--text-xs); }
.text-sm { font-size: var(--text-sm); }
.text-base { font-size: var(--text-base); }
.text-lg { font-size: var(--text-lg); }
.text-xl { font-size: var(--text-xl); }
.text-2xl { font-size: var(--text-2xl); }

.danger-item { color: var(--color-danger); }
</style>
