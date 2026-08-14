# ADR-021: Visual Page Builder — Normalize for Editing, Serialize for Rendering, Never Touch Cms's Own Lifecycle

## Status
Accepted

## Context
Milestone 15 needed a visual, drag-and-drop editor on top of Milestone
14's CMS pages and Milestone 13's theme engine, explicitly constrained
by the brief itself: "Reuse the Theme Engine and CMS architecture
already implemented. Do NOT redesign the rendering engine... Builder
stores configuration only. ThemeRenderer performs rendering." Three
design questions mattered most: how a drag-and-drop editor's naturally
relational needs (position, per-row mutation, undo history) coexist
with `PageVersion.sections` being one jsonb array; whether Builder gets
its own draft/publish lifecycle or reuses Cms's; and how "nested
blocks" — a feature `ThemeRenderer` had never needed before — gets
built without becoming a second rendering path.

## Options

**Content storage:**
1. `PageLayout`/`SectionInstance`/`BlockInstance` *become* the source
   of truth — `PageVersion.sections` is deprecated or kept only as a
   denormalized cache Builder writes to, and `ThemeRenderer` is taught
   to read the relational rows directly for a Builder-managed page.
2. `PageVersion.sections` stays the only thing `ThemeRenderer` ever
   reads, unchanged since Milestone 14. `SectionInstance`/
   `BlockInstance` are a normalized *mirror* Builder maintains purely
   for its own editing needs, replaced wholesale on every save and
   re-serialized back into the jsonb column immediately.

**Draft/publish lifecycle:**
1. `PageLayout` gets its own status/version concept, parallel to
   `PageVersion`'s draft/published/archived — Builder becomes a second
   place "is this published" is decided.
2. `PageLayout` has no lifecycle of its own at all — it exists 1:1
   with whichever `PageVersion` is currently the draft, created lazily
   the first time the Builder opens a page, and every Cms action that
   already exists (`PublishPageVersion`, `RollbackPage`,
   `DuplicatePage`, `ClonePageVersionContent`) stays completely
   untouched.

**Nested blocks:**
1. Represent nesting in `BlockInstance` (self-referencing
   `parent_block_instance_id`) for editing purposes only; at
   serialization time, flatten nested children into the parent
   section's flat block list (dropping the hierarchy) so
   `ThemeRenderer` needs no changes at all.
2. Represent nesting the same way, but extend `ThemeRenderer`'s
   existing block-resolution method to recurse into a block's own
   nested `blocks` key, the same way it already recurses into a
   section's `blocks` key — a small, additive, backward-compatible
   change.

## Decision

**Content storage: Option 2.** Option 1 would mean `ThemeRenderer`
gaining a second code path that behaves identically to the first
except for where it reads from — exactly the "duplicate rendering
logic" the brief explicitly rules out, and it would make
`SectionInstance`/`BlockInstance` a second source of truth `PageVersion.sections`
could drift from. Option 2 costs one serialize direction
(`SerializeSectionInstances`, relational → jsonb) and one replace
direction (`ReplaceSectionInstancesFromArray`, jsonb → relational,
"replace children in full" — the same pattern `ThemeTemplateController`/
`PageVersionController` already established for a jsonb-backed ordered
collection) and buys a guarantee: a page edited through the visual
Builder and a page edited through Milestone 14's raw-JSON textarea are
byte-for-byte indistinguishable to `ThemeRenderer`, because they are
the same column, in the same shape, always.

**Lifecycle: Option 2.** Verified as sound by building `FindOrCreatePageLayout`
to bootstrap from whatever is already in `PageVersion.sections` — empty
for a new page, already-populated if edited via the textarea, correctly
cloned forward by `ClonePageVersionContent` after a publish with zero
Builder involvement — and confirming in `BuilderPageTest` that publish/
duplicate/rollback through the Builder's own endpoints produce exactly
the same `ActivePageVersion`/version-count outcomes Milestone 14's own
`PageLifecycleTest` already asserts for the non-Builder path. A second
lifecycle concept was never needed because there was never a second
thing to track — "is this page live" is still answered entirely by
`ActivePageVersion`, exactly as Milestone 14 left it.

**Nested blocks: Option 2.** Option 1 was rejected specifically because
it would ship a builder feature that lies to the merchant — a nested
block would be fully editable, visibly nested in the UI, and then
silently vanish (or silently flatten to the wrong position) the moment
the page actually renders. A WYSIWYG editor that doesn't render what it
shows is worse than not having the feature. The extension itself is
small: the existing per-block resolution logic was extracted into
`resolveBlock()` and made to call itself on a block's own `blocks` key,
extending `RenderedSection`'s block shape from `{id, handle, settings}`
to `{id, handle, settings, blocks}` (recursive). Verified additive and
non-breaking by rerunning every Milestone 13/14 Theme/Cms test —
unmodified — immediately after making the change and before writing
anything Builder-specific; all passed unchanged, since a block with no
`blocks` key simply resolves to `'blocks' => []`, exactly what it
implicitly did before.

## Consequences

### Positive
- Zero duplicate rendering logic, verified by construction rather than
  by inspection: `ThemeRenderer` has exactly one method that resolves a
  section instance array (`resolveSection()`/`resolveVersion()`,
  unchanged in shape since Milestone 14) regardless of whether the
  array came from a built-in theme template, a Milestone 14 raw-JSON
  edit, or a Builder drag-and-drop save.
- Cms's own draft/publish/rollback/duplicate actions needed literally
  zero code changes — the entire Builder domain is additive, which is
  exactly what "reuse the CMS architecture already implemented, do not
  redesign" asked for. A regression in Builder cannot corrupt Cms's own
  invariants because Builder never writes anywhere Cms's own actions
  don't already expect writes to happen (the `sections` column itself).
- Nested blocks are a real, rendering-verified feature
  (`BuilderPageTest`'s nested-block test publishes a page with a
  2-level-deep block and asserts the storefront response actually
  contains both levels), not a cosmetic editor-only capability.

### Negative
- Every Builder save does a full delete-and-recreate of a page's
  `SectionInstance`/`BlockInstance` rows rather than diffing against
  what's already there. Accepted: a page's section/block count is
  always small (this is authored merchant content, not a data import),
  and diffing a tree against a drag-and-drop result correctly is
  materially harder to get right than "replace it" — the same trade-off
  `ThemeTemplateController` already made for template sections.
- `BuilderRevision` stores a full sections-array snapshot on every save
  rather than a diff, so a long editing session accumulates rows
  proportional to save count, not change size. Accepted for the same
  reason: undo/redo needs to be reliably correct far more than it needs
  to be storage-optimal, and a page's `sections` payload is small text/
  jsonb, not binary content.
- Cross-nesting-level block drag-and-drop (dragging a block out of one
  parent into a different one) is not implemented — a block can be
  reordered among siblings and inserted/deleted/duplicated at any
  level, but not dragged between levels. This is a real, acknowledged
  UI gap, not a backend limitation — `BlockInstance.parent_block_instance_id`
  supports it structurally; the interaction was simply out of scope for
  this pass.

## Security Requirements
- Every Builder table (`PageLayout`, `SectionInstance`, `BlockInstance`,
  `BuilderRevision`, `BuilderHistory`, `BuilderPreset`) uses
  `BelongsToTenant`, verified with dedicated cross-tenant tests in
  `BuilderPageTest`/`BuilderPresetTest`/`ThemeAssetTest` — Store A can
  never read, edit, publish, undo, or restore a Store B builder page,
  preset, or theme asset.
- `ThemeAssetController::store()` validates upload size (`max:20480`
  KB) and requires a real `ThemeAssetType` enum value — an unrecognized
  or oversized upload is rejected before ever touching storage.
- `SaveBuilderLayout`/`RestoreBuilderRevision` both call
  `PageVersion::assertEditable()` before writing — a published version
  remains exactly as immutable to the Builder as it already was to
  Milestone 14's raw-JSON textarea; there is no separate "Builder
  bypasses the immutability guard" code path.
