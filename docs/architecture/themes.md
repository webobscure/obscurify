# Theme Engine + Storefront Rendering

Milestone 13 — the theme engine every storefront page renders through
(spec section 9: "Resolve active theme, resolve template, load sections,
merge settings, render storefront. Nuxt storefront must render through
ThemeRenderer... do not hardcode pages"). See
[ADR-019](../adr/019-theme-engine.md) for the key design decisions.

## 1. Themes domain

A dedicated `App\Domain\Themes` module. Commerce Core (Orders/Products/
Checkout/...) never references anything in it; only Storefront and Apps
controllers call `ThemeRenderer`.

```text
Theme          Themes\Models   the theme "product" a store owns — name/slug/status
ThemeVersion   Themes\Models   an immutable-once-published content snapshot
ActiveTheme    Themes\Models   one row per store — which version is live
ThemeSection   Themes\Models   a section TYPE definition, scoped to one ThemeVersion
ThemeBlock     Themes\Models   a block TYPE definition, scoped to one ThemeSection
ThemeTemplate  Themes\Models   one of 9 fixed slots, holding an ordered array of section INSTANCES
ThemeSetting   Themes\Models   a global key/value pair, scoped to one ThemeVersion
ThemeAsset     Themes\Models   metadata for a file-backed asset (disk/path, never file bytes)
ThemePreset    Themes\Models   a named bundle of settings a merchant can apply at once
ThemeRenderer  Themes\Support  resolves version -> template -> sections -> merged settings
```

Every table is `BelongsToTenant` (per-store), enforced the same way as
every other domain in this codebase and verified with dedicated
cross-tenant tests (`tests/Feature/Themes/ThemeLifecycleTest.php`'s
"never lets Store A read, edit, publish, or duplicate a Store B theme").

## 2. Type vs. instance — the schema/content split

The domain's central design choice is a hybrid: section and block
**types** are normalized rows (`ThemeSection`, `ThemeBlock`, each
carrying a `schema` array of `{id, type, label, default}` field
definitions); section and block **instances** — which types are placed
on a given page, in what order, with what overrides — live as plain
jsonb inside `ThemeTemplate.sections`:

```json
[{
  "id": "hero-1",
  "section_handle": "hero",
  "settings": { "heading": "Big Sale" },
  "blocks": [{ "id": "btn-1", "block_handle": "button", "settings": {} }]
}]
```

An instance only ever stores what the merchant actually *overrode* —
`ThemeRenderer` merges each instance's `settings` on top of its type's
`defaultSettings()` (schema field defaults) at render time, so the
storefront always receives fully resolved values and never sees a
schema or a raw override separately (`RenderedSection`/`RenderedBlock`).
See [ADR-019](../adr/019-theme-engine.md) for why this split exists
instead of two simpler alternatives.

## 3. Draft / publish / rollback lifecycle

Exactly one `draft` `ThemeVersion` per theme is the live working copy —
`ThemeSection`/`ThemeBlock`/`ThemeTemplate`/`ThemeSetting` rows scoped to
it are edited in place, freely, with no history kept per edit.
`ThemeVersion::assertEditable()` refuses any write once a version is no
longer a draft.

- **Publish** (`PublishThemeVersion`): freezes the current draft
  (`status -> published`, `published_at` set — permanently immutable
  from this point), activates it for the store (`ActivateTheme`), and
  immediately opens a brand new draft cloned from what was just
  published (`CloneThemeVersionContent`) — so editing continues without
  ever touching a live snapshot. A theme therefore always has exactly
  one draft version, a server-side invariant the admin UI relies on
  (`draftVersion.value.find(v => v.status === 'draft')!` needs no
  "no draft yet" fallback).
- **Rollback** (`RollbackTheme`): repoints `ActiveTheme` at an older
  *published* version of the same theme — never a draft (visitors must
  never see unpublished work). Never touches the current draft; a
  merchant who rolled back keeps their in-progress edits and can publish
  them whenever they're ready.
- **Duplicate** (`DuplicateTheme`): clones an entire theme into a new
  one — a new `Theme` row plus a single new draft `ThemeVersion`
  carrying a full copy of the source's *current* content (its draft if
  one exists, otherwise its latest version). Fully independent
  afterward; editing the copy never touches the original.
- **Preview** (`ThemeController::preview`): renders the theme's current
  draft through the exact same `ThemeRenderer` the storefront uses
  (`preview: true`), never touching `ActiveTheme`. Visitors always see
  the active theme (spec section 11); only an authenticated merchant
  request can ask for a preview.

`ThemeAsset` rows are deliberately **not** cloned by
`CloneThemeVersionContent` — they're file-backed (disk/path) and shared
by reference across versions of the same theme rather than physically
copied on disk for every new draft.

## 4. ThemeRenderer — the one rendering path

```
render(storeId, ThemeTemplateType, preview = false, previewVersionId = null): RenderedPage
```

1. **Resolve version**: live request -> `ActiveTheme.theme_version_id`
   (404 via `ThemeNotActiveException` if the store has none yet);
   preview request -> the theme's current draft, resolved from
   `ActiveTheme.theme_id` unless a specific `previewVersionId` was
   given (the admin's per-theme preview endpoint always passes one).
2. **Resolve template**: the one `ThemeTemplate` row matching the
   requested `ThemeTemplateType` for that version.
3. **Resolve sections**: for each instance in the template's `sections`
   array, look up its `ThemeSection` type by `section_handle` (silently
   dropped if the handle is unknown — a theme can be edited into a
   state referencing a handle that no longer exists without corrupting
   the render), merge `defaultSettings()` with the instance's own
   `settings`, and resolve its `blocks` the same way one level down.
4. **Resolve global settings**: every `ThemeSetting` row for the
   version, flattened to a `{key: value}` map.

The fixed template slots (`ThemeTemplateType`): `home`, `collection`,
`product`, `cart`, `checkout`, `search`, `blog`, `404`, `page`.
`checkout` and `blog` are listed now because a theme can define them,
but Commerce Core's actual checkout flow and Milestone 14's blog routes
don't render through `ThemeRenderer` yet.

## 5. Storefront integration

One route, `GET /api/v1/theme/{template}`
(`StorefrontThemeController::render`), behind `storefront.tenant`
middleware (resolves the store from the request's `Host` header) —
**never** `auth:sanctum`. This is deliberate: a storefront visitor has
no merchant session, so there is no way for this endpoint to ever
receive a preview request; draft rendering is exclusively the
admin-only `ThemeController::preview` endpoint. Verified in
`tests/Feature/Themes/ThemeRenderingTest.php` — the storefront endpoint
renders correctly with zero admin session state, and a store with no
active theme yet gets a clean 404, not a crash.

## 6. Admin API

```
GET/POST      /api/v1/themes                          list, create
GET/PATCH     /api/v1/themes/{theme}                   show, update (name/status)
POST          /api/v1/themes/{theme}/publish           publish current draft
POST          /api/v1/themes/{theme}/duplicate          clone the theme
GET           /api/v1/themes/{theme}/preview            render the draft
GET           /api/v1/themes/{theme}/versions           version history
POST          /api/v1/theme-versions/{v}/rollback        activate an older published version
GET/PATCH     /api/v1/theme-versions/{v}/settings         global settings, as a flat map
GET/PATCH     /api/v1/theme-versions/{v}/templates(/{type}) template list / one template's sections
```

No `DELETE /themes/{id}` exists on purpose: an unwanted theme is
archived via `status`, never deleted, so `ActiveTheme`/`DuplicateTheme`
lineage never dangles.

## 7. Admin UI

`apps/admin/app/pages/themes/{index,[id]}.vue` — a themes list with a
create form, and a detail page covering the full lifecycle: publish,
duplicate (navigates to the new copy — its own detail page, not a
reload of the current one), a versions table with rollback on any
published row, per-template section editing, global settings editing,
and a preview panel. Template sections and global settings are edited
as raw JSON in a `<textarea>` rather than a generated form — there is
currently no admin-facing endpoint that describes which section/block
handles a theme defines or what fields they take (`ThemeSection`/
`ThemeBlock` are readable only indirectly, through the resolved
`sections` a template already carries), so the UI cannot yet generate
one. Building that discovery endpoint and a schema-driven form is
additive future work; it does not change anything already built.

Covered end-to-end in
`apps/storefront/e2e/themes-admin.spec.ts`: create a theme, edit its
draft's home template, publish it twice (so there are two published
versions), roll back specifically to the older one, and render a
preview — exercising the real backend the whole way, not a mock.

## 8. Explicitly not implemented

- **Section/block schema discovery API** — see §7. The admin edits raw
  JSON today; a form generated from `ThemeSection.schema`/
  `ThemeBlock.schema` is future work.
- **`ThemeAsset` upload/list/delete endpoints** — the model, migration,
  and `BelongsToTenant` scoping exist (file content lives on
  disk/S3-compatible storage per `config/filesystems.php`, never in
  Postgres), but no controller exposes them yet. Asset references inside
  section/block settings (e.g. an `image` field) are plain strings today
  with no server-side validation that they resolve to a real
  `ThemeAsset` row.
- **`ThemePreset` application endpoint** — the model exists (a named
  bundle of settings a merchant could apply at once) but nothing lets a
  merchant create or apply one yet.
- **CMS integration** — pages, blog posts, and navigation menus
  rendering through `ThemeRenderer`'s `page`/`blog` template slots is
  Milestone 14's job, not this one's; the slots exist now so that work
  is additive.
