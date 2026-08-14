# CMS — Pages, Navigation, Blog, SEO, Redirects

Milestone 14 — content management on top of Milestone 13's theme engine.
See [ADR-020](../adr/020-cms.md) for the key design decisions.

## 1. Cms domain

A dedicated `App\Domain\Cms` module. Themes never references it — the
dependency runs one way, Cms depends on `ThemeRenderer`, never the
reverse — mirroring the "Commerce Core never references Themes" rule
`ThemeRenderer`'s own docblock establishes.

```text
Page               Cms\Models   the page "product" a store owns — title/slug/status
PageVersion        Cms\Models   an immutable-once-published content snapshot
ActivePageVersion  Cms\Models   one row per page — which version is live
PageTemplate       Cms\Models   a named, reusable starting `sections` preset
Menu               Cms\Models   a named navigation menu
MenuItem           Cms\Models   one entry, self-nesting via parent_id
Author             Cms\Models   a blog post byline
Blog               Cms\Models   a named collection of posts
BlogPost           Cms\Models   a single post — draft/published/scheduled/archived
SeoMetadata        Cms\Models   meta fields for one PageVersion or BlogPost
Redirect           Cms\Models   a manual 301/302 URL redirect
```

Every table is `BelongsToTenant`, verified with dedicated cross-tenant
tests across every one of `tests/Feature/Cms/*.php`.

## 2. Page versioning reuses ADR-019's lifecycle exactly

A `Page` and its `PageVersion` rows follow the identical draft/publish/
rollback/duplicate state machine `Theme`/`ThemeVersion` established:
exactly one `draft` version at all times, publishing freezes it
(`status -> published`, `published_at` set, permanently immutable via
`PageVersion::assertEditable()`) and opens a fresh draft cloned from it
(`ClonePageVersionContent` — sections plus SEO metadata, the
`PageVersion`-scoped analogue of `CloneThemeVersionContent`).
`ActivePageVersion` mirrors `ActiveTheme`, scoped per-*page* instead of
per-*store* — many pages are live simultaneously, unlike themes, where
only one is active for the whole store — so rollback is the same O(1)
repoint of one foreign key.

**A page's `status` column and its "is it live" question are two
different things, on purpose (same as `Theme.status` vs. `is_active`):**
publishing a `PageVersion` never touches `Page.status` — only
`ActivePageVersion`. Whether a page renders on the storefront is decided
entirely by `ActivePageVersion` existing; `Page.status` is an
admin-facing label a merchant sets independently via `PATCH /pages/{id}`
(draft/published/archived). `StorefrontPageController` and
`StorefrontSitemapController` both check `ActivePageVersion`, never
`Page.status` — see their docblocks.

## 3. Page content is section-instance based, not a rich-text blob

`PageVersion.sections` is the *exact same* instance-array shape as
`ThemeTemplate.sections` — `[{id, section_handle, settings, blocks}]` —
resolved against the store's *currently active theme version*'s
`ThemeSection`/`ThemeBlock` type definitions at render time. A page
reuses the storefront's own section/block types rather than inventing a
second content model; the same "hero" section a theme's home page uses
can be dropped onto a CMS page. `ThemeRenderer::renderCmsPage()`
(Milestone 13's `ThemeRenderer`, extended, not duplicated) is the entry
point: it resolves the active theme version once, then delegates to the
exact same private section/block merge logic `render()` already used
for a built-in template slot — `ThemeRenderer` accepts a raw section
array and returns a `RenderedPage` with `template: 'page'`; it has no
idea the caller is `Cms`, preserving the one-way dependency.

`PageTemplate` is deliberately **not** theme-version-scoped and **not**
itself versioned — see [ADR-020](../adr/020-cms.md) for why an earlier,
more theme-coupled design was rejected. It is a plain, store-scoped
preset library: `sections` is copied into a new `PageVersion` once, at
creation time (`CreatePage`), never referenced afterward. Editing or
deleting a `PageTemplate` can never retroactively change a page that
started from it.

## 4. Navigation menus

`Menu` (a named list, identified by a stable `handle` a theme can
reference) has many `MenuItem` rows, self-nesting via `parent_id` — a
genuine adjacency-list tree, built in memory from one flat query
(`BuildMenuTree`, shared by the admin `MenuController` and the
storefront `StorefrontMenuController` — exactly one tree-building
implementation). A `MenuItem`'s target is `target_type`/`target_id`/
`url` — the same "not true DB polymorphism" pattern
`WebhookSubscription.owner_type`/`owner_id` established (ADR-018):
`target_type = 'url'` uses the item's own `url` column (an arbitrary
hand-typed or external link); every other type (`page`, `collection`,
`product`, `blog`, `blog_post`) stores the referenced row's id in
`target_id` and resolves its href at render time
(`ResolveMenuItemHref`) — a page/product/collection/blog(-post) can be
renamed or have its slug change without the menu item needing an
update. Resolution is deliberately lenient: a since-deleted target
resolves to `null` and is simply omitted from the rendered response,
never a fatal error for the whole menu (`GET
/storefront/menus/{handle}`).

## 5. Blog

`Blog` (a named collection, e.g. "News") has many `BlogPost` rows;
`Author` is a standalone byline entity — deliberately not the staff
`User` model, since a public "written by" name/bio/avatar is editorial
content that must survive a staff account being removed and has no
reason to be tied to a login-capable identity. A `BlogPost` is
**not** versioned like `Page`/`Theme` — one mutable row, its own
`status` (`draft`/`published`/`scheduled`/`archived`) is the entire
lifecycle, no rollback requirement. `scheduled_at` is honored by
`php artisan cms:publish-scheduled-posts`, a poll rather than a delayed
job: `scheduled_at` is merchant-editable right up until it fires, and a
queued job scheduled at creation time would need to be found and
re-scheduled on every edit, where a poll just re-reads the column.
Scheduler wiring itself is out of this milestone's scope, the same
deliberate cut ADR-018 made for `outbox:process`/
`webhooks:retry-failed`. `StorefrontBlogController` only ever shows
`status = published` posts — a `scheduled` post whose `scheduled_at` has
technically passed but hasn't been swept yet still stays invisible;
status, not the timestamp, is authoritative for visibility.

## 6. SEO metadata

One `SeoMetadata` row per subject, keyed by `subject_type`/`subject_id`
— the same owner_type/owner_id-style pattern as menu item targets.
`subject_type = 'page_version'` points at a `PageVersion`, not a `Page`
— SEO fields are part of what gets frozen at publish time, exactly like
`sections`, so they must snapshot with the version they describe rather
than being editable out from under an already-published page
(`PageVersionSeoController::update` calls `assertEditable()`, same
guard as the sections endpoint). `subject_type = 'blog_post'` points
directly at a `BlogPost`, which has no such immutability concern since
posts aren't versioned. `Product`/`Collection` are natural future
`SeoSubjectType` cases — a new enum case, not a schema change.

`PageVersionSeoController`/`BlogPostSeoController::update` explicitly
force a 200 response even on the very first save (`SeoMetadata::
updateOrCreate` inserting a brand-new row) rather than returning the
`JsonResource` directly — Laravel's default resource-response behavior
answers 201 when the wrapped model's `wasRecentlyCreated` is true, which
is technically accurate but a confusing surprise for a PATCH/upsert
endpoint (the same "set a field" semantics `ThemeSettingController::
update` already established, always 200 there too).

## 7. Sitemap and redirects

`GET /storefront/sitemap.xml` — a minimal, hand-built XML string (no
Blade view: see the controller's own docblock for why a `<?xml ...?>`
declaration inside a Blade template is actively dangerous — Blade's raw-
PHP-tag extraction pass scans the *entire raw source file* for `<?...?>`
before it ever parses `{{ }}`/`{!! !!}` boundaries, so a literal XML
declaration anywhere in a `.blade.php` file, even inside a quoted PHP
string, gets treated as a real embedded PHP tag and passed through
completely uncompiled; the exact same "`?>` ends a `//` comment
wherever it appears" foot-gun bit a comment in this very controller
during development). Lists every page with an `ActivePageVersion` and
every `published` `BlogPost`. There is no single "the storefront's base
URL" the way there might be for a single-tenant app — each store serves
its storefront on its own custom domain, resolved per-request from the
`Host` header — so the sitemap reads the requesting store's own primary
`Domain` row rather than a fixed config value.

`Redirect` is a flat `from_path` (unique per store) -> `to_path` +
`status_code` (301/302) mapping. The API never issues an HTTP redirect
itself — `GET /storefront/redirect?path=...` just resolves whether a
mapping exists; the Nuxt storefront SPA, which has already rendered its
own client-side routing by the time a path is known to be unresolvable,
performs the actual redirect itself rather than the API racing a
server-side `Location` header against a request the SPA already owns.

## 8. Admin API

```
GET/POST      /api/v1/pages                                  list, create
GET/PATCH     /api/v1/pages/{page}                             show, update (title/status)
POST          /api/v1/pages/{page}/publish                     publish current draft
POST          /api/v1/pages/{page}/duplicate                    clone the page
GET           /api/v1/pages/{page}/preview                      render the draft
GET           /api/v1/pages/{page}/versions                     version history
POST          /api/v1/page-versions/{v}/rollback                 activate an older published version
PATCH         /api/v1/page-versions/{v}/sections                  edit the draft's sections
GET/PATCH     /api/v1/page-versions/{v}/seo                       SEO for that version
GET/POST      /api/v1/page-templates, PATCH/DELETE .../{id}        preset library
GET/POST      /api/v1/menus, GET/PATCH/DELETE .../{id}
POST          /api/v1/menus/{menu}/items, PATCH/DELETE /menu-items/{id}
GET/POST      /api/v1/authors, PATCH/DELETE .../{id}
GET/POST      /api/v1/blogs, GET/PATCH/DELETE .../{id}
GET/POST      /api/v1/blogs/{blog}/posts
PATCH/DELETE  /api/v1/blog-posts/{id}, POST .../publish, GET/PATCH .../seo
GET/POST      /api/v1/redirects, PATCH/DELETE .../{id}
```

No `DELETE /pages/{id}`, matching Theme's "archive via status, never
delete" reasoning — `ActivePageVersion`/`DuplicatePage` lineage must
never dangle. Menus/Blogs/Authors/Redirects have no versioning
invariant to protect and support `DELETE`, the same pragmatic bar
Shipping/Promotions/Webhooks already set.

## 9. Admin UI

`apps/admin/app/pages/{pages,page-templates,menus,authors,blogs,
blog-posts,redirects}/` — Pages' detail page mirrors Themes' detail page
almost exactly (publish/duplicate/rollback/preview/draft-sections-
editing as raw JSON, same limitation as Themes: no section/block-type
discovery endpoint exists yet, so no generated form). Menus get a
recursive tree component for nested items, the one genuinely new UI
pattern this milestone needed relative to Milestone 13's admin work.

## 10. Explicitly not implemented

- **Section/block schema discovery** for pages — same gap
  docs/architecture/themes.md §7/§8 already documents for theme
  templates; a page's `sections` editor has the identical limitation.
- **A lookup/autocomplete for MenuItem `target_id`** — picking a page/
  product/collection/blog/post by name, not raw ID entry, is future UI
  work; the backend resolution (`ResolveMenuItemHref`) already supports
  it today, only the admin form doesn't offer a picker.
- **Product/Collection SEO** — `SeoSubjectType` only has `PageVersion`
  and `BlogPost` cases today; extending to catalog entities is additive.
- **Scheduler wiring** for `cms:publish-scheduled-posts` — an ops
  concern outside this milestone, same cut ADR-018 already made.
