# ADR-019: Theme Engine — Normalize Types, Embed Instances, Version Whole Snapshots

## Status
Accepted

## Context
Milestone 13 needed a theme engine general enough that the storefront
never hardcodes a page (spec section 9), that supports Shopify-style
draft/publish/rollback (spec section 12: "publish v1, v2, v3...
rollback must be possible"), and where "only one theme may be active"
per store (spec section 2) while a merchant keeps editing a separate
in-progress copy. Three design questions mattered most: how a section's
*schema* (what fields it has) relates to a section's *instance* (one
placement of it on one page, with specific values); what "version" means
— snapshot a whole theme, or track history per field; and what a
draft/publish/rollback state machine looks like that a frontend can
reason about without a "what if there's no draft" branch.

## Options

**Section/block schema vs. instance:**
1. One flat structure — a section instance carries its own field
   definitions inline (no separate "type" concept at all).
2. Fully normalized — both the type (schema) and every instance
   (placement + overrides) are their own database rows.
3. Hybrid — the type (schema) is a normalized row (`ThemeSection`/
   `ThemeBlock`), but instances (placement, order, per-instance
   overrides) live as a jsonb array on `ThemeTemplate.sections`.

**Versioning granularity:**
1. Track history per-field/per-row (an audit log of every setting
   change), with "the current state" computed by replaying it.
2. Snapshot the *entire* theme's content (sections, blocks, templates,
   settings) into a new `ThemeVersion` on every publish; older versions
   stay immutable rows a rollback can repoint to directly.

**Draft/publish state machine:**
1. A single mutable "live" version plus a separate "is this published"
   flag — editing and live rendering share the same rows.
2. Exactly one `draft` version per theme at all times (freshly opened
   the moment a theme is created, and again the moment its predecessor
   is published), enforced as a lifecycle invariant rather than a
   runtime check — published versions become permanently immutable via
   `ThemeVersion::assertEditable()`.

## Decision

**Schema/instance: Option 3, the hybrid.** Option 1 makes "what fields
does a hero section have" ill-defined once two hero instances exist —
there is no single place a form (or a future schema-discovery endpoint)
could read it from. Option 2 makes every instance edit a multi-row
write (reorder a page = update N foreign keys) for no real benefit,
since instance *ordering* and *nesting* (blocks inside sections) is
exactly what jsonb is good at and a relational join is not. The hybrid
gets both: `ThemeSection.schema`/`ThemeBlock.schema` is the one place a
field's type/label/default lives (`ThemeSection::defaultSettings()`),
while `ThemeTemplate.update()` can replace an entire page's section
order in one `UPDATE` (the same "replace children in full" pattern
`ShippingZoneRegion` already established for a jsonb-backed ordered
collection elsewhere in this codebase).

**Versioning: Option 2, whole-snapshot.** A per-field audit log
(Option 1) would make "what does the storefront show right now"
expensive to compute and rollback expensive to execute (replay to a
point in time). Whole-snapshot rollback is instead a single `UPDATE` on
`ActiveTheme.theme_version_id` (`ActivateTheme`) — the entire history is
already materialized as real, directly-queryable rows.

**State machine: Option 2, invariant draft.** Verified as sound because
it removes an entire class of "no draft exists" error handling from
every caller — `ThemeController::preview`, the admin UI's
`draftVersion` computed property, and `DuplicateTheme`'s source-version
resolution all rely on it. The cost is that `PublishThemeVersion` must
itself guarantee the invariant it depends on (create the next draft in
the same transaction as freezing the current one) rather than leaving
draft-creation to a separate step a caller could forget.

## Consequences

### Positive
- A section type's schema exists in exactly one place
  (`ThemeSection.schema`), so `ThemeRenderer::resolveSection()` always
  merges against a single source of truth, never a per-instance copy
  that could drift from its type.
- Rollback is `O(1)` — repoint one foreign key — regardless of how many
  versions exist or how large a version's content is, verified in
  `ThemeLifecycleTest`'s rollback test (roll back to v1 after v1 and v2
  are both published; the draft opened after v2's publish stays
  untouched).
- No admin code path needs a "theme has no draft" branch — confirmed by
  deliberately leaving it out of `apps/admin/app/pages/themes/[id].vue`
  and having nothing break, since the invariant is enforced server-side
  at every version-creating action (`CreateTheme`, `PublishThemeVersion`,
  `DuplicateTheme`).

### Negative
- Publishing physically copies every section/block/template/setting row
  into a new draft (`CloneThemeVersionContent`) rather than sharing
  unchanged rows by reference — a large theme's storage grows roughly
  linearly with publish count. Accepted: matches the "whole-snapshot"
  decision directly (a shared row could not be independently frozen
  later), and theme content sizes here are small text/jsonb, not
  binaries — `ThemeAsset` (the one genuinely large kind of content)
  already exists as the deliberate exception, referenced rather than
  copied.
- The hybrid schema/instance split means there is currently no single
  endpoint that answers "what sections and fields can this theme use" —
  an admin caller has to infer available handles from what a template
  already references. Flagged as a real gap (not a design flaw) in
  docs/architecture/themes.md §7/§8: the schema data already exists on
  `ThemeSection`/`ThemeBlock` rows, so exposing it is additive, not a
  redesign.
- `ThemeVersion::assertEditable()` throwing on any write to a published
  version means a merchant cannot fix a typo on something they just
  published without first publishing a new version (or waiting for the
  auto-opened draft, editing it, and republishing) — accepted as the
  direct, intended consequence of "published means immutable" rather
  than a bug; the auto-opened draft exists specifically to make that
  correction path immediate rather than a multi-step recovery.

## Security Requirements
- Every `Theme`/`ThemeVersion`/`ThemeSection`/`ThemeBlock`/
  `ThemeTemplate`/`ThemeSetting`/`ThemeAsset`/`ThemePreset`/
  `ActiveTheme` row uses `BelongsToTenant` — verified with dedicated
  cross-tenant tests, not just individually-scoped resources
  (`ThemeLifecycleTest`'s "never lets Store A read, edit, publish, or
  duplicate a Store B theme").
- The storefront's public render route
  (`GET /api/v1/theme/{template}`) sits behind `storefront.tenant`
  only, never `auth:sanctum` — structurally incapable of serving a
  preview, since there is no merchant session to request one from. Draft
  rendering exists exclusively on the admin-authenticated
  `ThemeController::preview` endpoint.
- `RollbackTheme`/`ActivateTheme` both reject activating anything but an
  already-`published` version — a draft (unpublished, unreviewed
  content) can never become what visitors see, checked at the
  application layer regardless of what a caller passes.
