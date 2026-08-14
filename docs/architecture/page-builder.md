# Visual Page Builder + Theme Customizer

Milestone 15 — the visual editing layer on top of Milestone 13's theme
engine and Milestone 14's CMS. See [ADR-021](../adr/021-visual-page-builder.md)
for the key design decisions. Per the milestone brief: the rendering
engine is not redesigned, AI page generation and marketplace themes are
out of scope, and the focus is the editor's own architecture.

## 1. Builder domain

A dedicated `App\Domain\Builder` module. It depends on `App\Domain\Cms`
and `App\Domain\Themes`; neither of those is touched to make it possible
(with one narrow, additive exception — see §4) — the same one-way
dependency direction ADR-020 established for Cms depending on Themes.

```text
PageLayout       Builder\Models   the structured root for one PageVersion's draft content
SectionInstance  Builder\Models   one placed section — the relational form of one entry in PageVersion.sections
BlockInstance    Builder\Models   one placed block, optionally nested inside another block
BuilderRevision  Builder\Models   an immutable snapshot of a PageLayout's sections, for undo/redo + the revision timeline
BuilderHistory   Builder\Models   the undo/redo cursor — one row per PageLayout
BuilderPreset    Builder\Models   a named starting configuration — the Section/Block Library
```

## 2. "Builder stores configuration only. ThemeRenderer performs rendering."

This is the milestone brief's own framing, and it is the load-bearing
design constraint everything else follows from. `SectionInstance`/
`BlockInstance` are a **normalized mirror** of the exact same jsonb
array `PageVersion.sections` already stores (`[{id, section_handle,
settings, blocks: [...]}]`) — not a new content model. Every Builder
write (`SaveBuilderLayout`) replaces the relational rows wholesale from
the submitted array (the same "replace children in full" pattern
`ThemeTemplateController`/`PageVersionController` already established
for a jsonb-backed ordered collection), then **re-derives the canonical
jsonb form from what was actually written** (`SerializeSectionInstances`)
and writes it back to `PageVersion.sections` — the one column
`ThemeRenderer` has ever read, unchanged since Milestone 14. Reading a
Builder-edited page's storefront output is bit-for-bit indistinguishable
from reading one edited through Milestone 14's raw-JSON textarea, because
they end up in the exact same column, in the exact same shape.

The relational rows exist purely to serve the *editor's* own needs —
structured drag-and-drop mutation, position tracking, revision
snapshots — not because `ThemeRenderer` needs them. `ThemeRenderer`
never queries `SectionInstance`/`BlockInstance`; it is not even aware
`App\Domain\Builder` exists.

## 3. Lazy bootstrap — zero changes to Cms's own lifecycle

`CreatePage`, `PublishPageVersion`, `ClonePageVersionContent`, and
`RollbackPage` (all Milestone 14, `App\Domain\Cms`) are **untouched**.
A `PageLayout` does not get created when a page is created or published
— it is bootstrapped lazily, the first time the visual Builder actually
opens a page (`FindOrCreatePageLayout`), by parsing whatever is already
sitting in that draft `PageVersion.sections` (empty for a brand-new
page; already populated if the page was ever edited through Milestone
14's textarea; correctly cloned forward if the page was just published,
since `ClonePageVersionContent` already copies `sections` into the new
draft with no Builder involvement at all) into fresh `SectionInstance`/
`BlockInstance` rows, plus a baseline `BuilderRevision`/`BuilderHistory`
so undo has something to step back to.

This is why publish/rollback/duplicate on the Builder's own API
(`POST /builder/pages/{id}/publish` etc.) are **thin passthroughs** to
`PublishPageVersion`/`RollbackPage`/`DuplicatePage` — not
reimplementations. The next time the Builder opens the page that came
out of one of those calls, `FindOrCreatePageLayout` bootstraps a fresh
`PageLayout` for whichever `PageVersion` is now the draft, from its
already-correct `sections` column.

## 4. Nested blocks — the one deliberate rendering-engine extension

Spec section 2 requires "nested blocks" (a block containing child
blocks — e.g. items inside an Accordion). `BlockInstance.
parent_block_instance_id` (self-referencing, same adjacency-list
pattern `MenuItem` already established) makes this representable and
editable. But `ThemeRenderer::resolveSection()`'s block-resolution loop,
before this milestone, only ever read one flat level of
`instance['blocks']` — a block instance carrying its own nested
`blocks` key would have been silently invisible on the storefront,
which is not acceptable for a WYSIWYG editor: what the merchant builds
must be what renders.

The fix is a **minimal, additive extraction**: the block-resolution
logic was pulled into a `resolveBlock()` method that recurses into a
block's own `blocks` key the same way `resolveSection()` already
recurses into a section's `blocks` key — extending `RenderedSection`'s
block shape from `{id, handle, settings}` to `{id, handle, settings,
blocks}` (recursive, empty array when there are no children). This is
exactly the kind of change ADR-019/020 already precedent for
(`renderCmsPage()` was the same shape of addition in Milestone 14):
every non-nested block from before this milestone is unaffected — an
instance with no `blocks` key simply resolves to `'blocks' => []`, the
same as it always implicitly did. Verified by rerunning every
Milestone 13/14 Theme/Cms test unchanged immediately after the change,
before writing anything Builder-specific.

## 5. Undo / redo / revision timeline

`BuilderRevision` is an immutable, append-only snapshot of a
`PageLayout`'s full `sections` array — every save (manual or
autosaved; there is no backend distinction between the two, see §7)
appends one, tagged with a per-`PageLayout` monotonic `sequence`.
`BuilderHistory` is a single cursor row (`current_revision_id`) per
`PageLayout`. Undo/redo (`UndoBuilderLayout`/`RedoBuilderLayout`) find
the adjacent `sequence` and delegate to `RestoreBuilderRevision`, which
also backs the revision-timeline "restore to any point" action
(`POST /builder/pages/{id}/revisions/{revision}/restore`) — undo/redo
and "restore" are the same operation, just with different target
resolution.

Saving a new change after an undo does not delete the "future"
revisions it stepped back from — a fresh save always gets
`max(sequence) + 1`, so those orphaned revisions simply stop being
reachable by a simple adjacent-sequence redo. They are abandoned, not
deleted (the same branch-is-abandoned-not-erased semantics most
editors use), and remain visible/restorable from the full revision
timeline if a merchant explicitly wants one back.

This is a genuinely new capability relative to Milestone 13/14 —
`ThemeVersion`/`PageVersion`'s own draft/publish/rollback (ADR-019/020)
is about published-vs-draft history, not about undoing a change made
*while still drafting*, which Theme/Cms never needed and Builder does.

## 6. Section Library / Block Library

`BuiltInLibrary` (a plain array catalog, the same "easy to extend, not
a hard enum" reasoning `AppScope::known()` already established for
OAuth scopes) defines 12 built-in section types and 13 built-in block
types with their field schemas. `SeedBuilderLibrary` registers them as
real `ThemeSection`/`ThemeBlock` rows (idempotent — safe to call on
every `GET /builder/presets` request) plus one `BuilderPreset` per type
so the picker has sensible defaults to insert with.

`ThemeBlock` is section-scoped by design (Milestone 13's schema — a
block type belongs to exactly one `ThemeSection` via a required FK,
unchanged here since "do not redesign the rendering engine" applies to
the theme engine's schema, not only `ThemeRenderer`'s code). A block
meant to be usable on every section (e.g. "Button") is therefore
registered once *per section* — the cross product of 12 sections × 13
blocks — rather than needing `ThemeBlock` to grow a "global block"
concept it was never designed to have.

## 7. Draft workflow, autosave, and responsive editing

The visual Builder never edits a published version — every mutation
targets the page's current draft `PageVersion` (`assertEditable()`
already guards this, unchanged from Milestone 14), and publishing
still atomically freezes it and opens a new draft exactly as before.
"Autosave" (spec section 9) is not a separate code path: the editor
mutates a local in-memory array on every drag/insert/delete, and
`PATCH /builder/pages/{id}` — the same endpoint a manual "Save" click
uses — is simply called on a debounce timer. There is nothing for the
backend to distinguish, since every save already creates a revision
regardless of what triggered it.

Responsive editing (Desktop/Tablet/Mobile preview) is purely a frontend
concern — the backend has no notion of breakpoints. `sections[].settings`
is the same object regardless of viewport; a genuinely per-breakpoint
override system (different settings values at different widths) was
not requested and is not built.

## 8. Theme Customizer

Not a new content model — every customizer field (logo, typography,
colors, buttons, border radius, spacing, container width, header,
footer, announcement bar, social links, favicon) is a plain
`ThemeSetting` row, the exact mechanism Milestone 13 already built.
`ThemeCustomizerSchema` is a static field-metadata list (key, label,
input `type`, display `group`) purely so the admin UI can render an
appropriate input per field instead of Milestone 13's raw-JSON
settings textarea; `ThemeRenderer` already returns every `ThemeSetting`
as `globalSettings` on every rendered page (unchanged), so a storefront
theme reads e.g. `globalSettings.color_primary` the same way it reads
any other setting — there is nothing new to render. `GET
/builder/theme-customizer` is a read-only convenience (resolves the
active theme's current draft version so the admin UI doesn't need to
chain a lookup first); saving reuses `PATCH
/theme-versions/{id}/settings` unmodified.

## 9. Theme Assets — closing a Milestone 13 gap

`ThemeAsset` (model, migration, `BelongsToTenant` scoping) has existed
since Milestone 13, but no controller ever exposed it — flagged
explicitly as future work in `docs/architecture/themes.md` §8. The
Builder's asset picker (spec section 10: "Allow selecting: Images,
Videos, Icons, Backgrounds, Uploaded files") is what actually needs it
now, so this milestone adds upload/list/delete
(`ThemeAssetController`) — file content goes to the configured disk
via Laravel's `Storage` facade exactly as `ThemeAsset::url()`/
`contents()` already assumed it would; the row is metadata only,
unchanged from Milestone 13's design.

## 10. Admin API

```
GET/PATCH     /api/v1/builder/pages/{page}                              layout state
POST          /api/v1/builder/pages/{page}/publish                       -> Cms's PublishPageVersion
POST          /api/v1/builder/pages/{page}/duplicate                      -> Cms's DuplicatePage
POST          /api/v1/builder/pages/{page}/rollback                       body: {page_version_id}  -> Cms's RollbackPage
POST          /api/v1/builder/pages/{page}/undo | /redo
GET           /api/v1/builder/pages/{page}/revisions
POST          /api/v1/builder/pages/{page}/revisions/{revision}/restore
GET           /api/v1/builder/presets[?type=section|block]
GET           /api/v1/builder/theme-customizer
GET/POST      /api/v1/theme-versions/{themeVersion}/assets
DELETE        /api/v1/theme-assets/{themeAsset}
```

`rollback` takes a **page** id in the URL (the milestone brief's own
literal route shape) plus the target published version in the body —
unlike Cms's own `POST /page-versions/{pageVersion}/rollback`, which
the version id alone already scopes. The version is validated to
actually belong to the given page before delegating to the same
`RollbackPage` action either way.

## 11. Admin UI

`apps/admin/app/pages/builder/pages/[id].vue` — a drag-and-drop canvas
(native HTML5 drag-and-drop events, no new dependency) for reordering/
inserting/duplicating/deleting sections and blocks, a settings panel
(raw-JSON per selected section/block — the same schema-discovery gap
Milestone 13/14 already have, see their own docs §7-8; this is not a
new limitation), a responsive width toggle over a best-effort generic
preview renderer, undo/redo, a revision timeline, and publish/
duplicate/rollback. `apps/admin/app/pages/theme-customizer/index.vue`
renders `ThemeCustomizerSchema`'s fields grouped and typed.
`apps/admin/app/pages/{section-library,block-library}/index.vue` are
read-only browse pages for the preset catalog.

## 12. Explicitly not implemented

- **AI page generation, marketplace themes, collaborative/real-time
  multi-user editing, multilingual editing** — out of scope per the
  milestone brief itself, not attempted.
- **Cross-nesting-level block drag-and-drop** — a block can be
  reordered among its siblings at whatever level it's at, and
  inserted/duplicated/deleted/edited at any level, but dragging a block
  from one nesting depth to another is not built in this pass.
- **Section/block field-schema discovery** — same gap Milestone 13/14
  already carry forward; a selected section/block's settings are
  edited as raw JSON, not a generated form.
- **Per-breakpoint setting overrides** — responsive *preview* exists;
  a section rendering differently by configured value per breakpoint
  does not.
- **Product/Collection asset types beyond images in the customizer
  picker** — the asset upload endpoint accepts any `ThemeAssetType`,
  but the customizer's own picker UI is scoped to what its 19 known
  fields actually need.
