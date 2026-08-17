# Merchant Admin Theming — Light & Dark

Implements the theme system required alongside the Products module redesign. Extends `docs/design/DESIGN_SYSTEM.md` §1 (which already specified this exact dark palette on paper) — this document describes what's actually built: `apps/admin/app/assets/css/tokens.css`, `app/composables/useColorMode.ts`, `app/plugins/apply-theme.client.ts`, and the theme switcher in `UserMenu.vue`.

## 1. Architecture

Two CSS layers, one JS composable:

- **Primitive tokens** (`--gray-*`, `--indigo-*`, `--green-*`, `--amber-*`, `--red-*`, `--blue-*`) — raw color ramps. No component ever references these directly.
- **Semantic tokens** (`--color-bg`, `--color-surface`, `--color-text`, `--color-accent`, `--color-danger`, …) — the only names a component's `<style>` block may use. A component that reaches for a literal hex value or `white`/`black` is a bug, not a style choice — see §9.
- **`useColorMode()`** — the one place theme *preference* (as opposed to theme *tokens*) is read or written. Components never touch `document.documentElement` or the `admin_theme` cookie directly.

Theme resolution is **3-state**, not a light/dark toggle:

| Preference | What gets stamped on `<html>` | What resolves the actual colors |
|---|---|---|
| `system` (default) | nothing — no `data-theme` attribute at all | the browser's `prefers-color-scheme` media query |
| `light` | `data-theme="light"` | the bare `:root` block (light values) |
| `dark` | `data-theme="dark"` | the `:root[data-theme="dark"]` block |

This is why `tokens.css` has *three* places semantic tokens are defined, not two:

```css
:root { /* light — the default, unguarded */ }

@media (prefers-color-scheme: dark) {
  :root:not([data-theme='light']) { /* dark, but only if the user hasn't explicitly chosen light */ }
}

:root[data-theme='dark'] { /* dark, explicit choice — wins over the OS regardless of media query */ }
```

Without the `:not([data-theme='light'])` guard, a user on a dark-OS who explicitly picks Light would still get dark colors from the media query fighting the (missing) attribute. Without the third, unguarded `[data-theme='dark']` block, a user on a light-OS who explicitly picks Dark would get nothing, since the media query itself never matches.

## 2. Design tokens

Full semantic set (`apps/admin/app/assets/css/tokens.css`):

```
--color-bg, --color-surface, --color-surface-muted, --color-surface-raised
--color-border, --color-border-strong
--color-text, --color-text-muted, --color-text-subtle, --color-text-on-accent
--color-accent, --color-accent-hover, --color-accent-active, --color-accent-bg
--color-danger, --color-danger-hover, --color-danger-bg, --color-danger-border
--color-success, --color-success-hover, --color-success-bg, --color-success-border
--color-warning, --color-warning-hover, --color-warning-bg, --color-warning-border
--color-info, --color-info-hover, --color-info-bg, --color-info-border
--color-overlay
--color-sidebar-bg, --color-sidebar-text, --color-sidebar-text-muted,
--color-sidebar-border, --color-sidebar-hover-bg, --color-sidebar-active-bg,
--color-sidebar-menu-bg, --color-sidebar-badge-success(-bg), --color-sidebar-badge-warning(-bg)
```

Every one of these existed conceptually in `DESIGN_SYSTEM.md` §1.3 already — this pass is the first time they're actually defined in `tokens.css` and consumed. **Every light-mode value is byte-identical to what shipped before this change** (the primitive ramp was reverse-engineered from the existing hex literals, not invented fresh), so no page anywhere in the app changes appearance under `system`/no-preference on a light OS — this is additive, not a repaint of the whole admin.

### Sidebar is theme-invariant chrome, not a themed surface

The sidebar (`AdminSidebar`, `StoreSwitcher`, `UserMenu`'s own dropdown-on-dark styling) stays a dark rail in **both** page themes — the same convention documented in `DESIGN_SYSTEM.md` §1.3 and already true before this change (Linear/Vercel both do this). It gets its *own* small token set (`--color-sidebar-*`) that is redefined once for the light-theme default and again inside the dark-theme blocks — not because the sidebar becomes light in light mode (it never does), but because a dark rail sitting on a *dark* page needs to read as slightly less saturated/closer to the page background than the same dark rail sitting on a *light* page, or it looks like a rendering error rather than a deliberate two-tone shell. Compare `--color-sidebar-bg: #15151a` (light theme) against `#0a0a0c` (dark theme) in `tokens.css`.

## 3. Supported themes & extensibility

Light and Dark ship now. Adding a third theme later (e.g. a high-contrast mode) means:

1. Add a new guarded block, e.g. `:root[data-theme='hc']`, defining the same semantic token list with new values — no primitive ramp changes required unless the new theme needs new base colors.
2. Add the option to `themeOptions` in `UserMenu.vue` and extend the `ColorModePreference` union type in `useColorMode.ts`.

No component changes are required for a new theme, which is the entire point of the semantic-token indirection — components were never told which theme they're in, only which *role* a color plays.

## 4. Switching logic & persistence (`useColorMode.ts`)

```ts
export type ColorModePreference = 'light' | 'dark' | 'system'

export function useColorMode() {
  const cookie = useCookie<ColorModePreference>('admin_theme', {
    default: () => 'system', maxAge: 60 * 60 * 24 * 365, sameSite: 'lax',
  })
  // ...apply() stamps/removes data-theme on document.documentElement
  // preference is a computed wrapping the cookie, so reading/writing it
  // is the entire public API — no component reaches into document.* itself
}
```

- **Persistence**: a plain cookie, `admin_theme`, one year — the exact same mechanism `@nuxtjs/i18n` already uses for `admin_locale` (`nuxt.config.ts`'s `detectBrowserLanguage.cookieKey`), so this doesn't introduce a new persistence pattern to the codebase, it reuses the one already trusted for a similar per-user preference.
- **Applying before paint**: `app/plugins/apply-theme.client.ts` calls `apply()` during client boot, before the app mounts — the same "run early on client boot" role `plugins/hydrate-auth.client.ts` already plays for the auth token. Given the app is `ssr:false` (a pure SPA — see `nuxt.config.ts`), there is no server-rendered HTML to mismatch against; the tradeoff is identical to the one already accepted for auth state.
- **Switcher UI**: `UserMenu.vue` gained a `role="group"` segmented control (Light / Dark / System, `role="menuitemradio"` each) between the account block and Sign out — no new page, no new route, matches the spec's "inside the user menu" placement exactly.

## 5. Contrast

Dark values are **not** an inversion filter over the light palette — each was chosen independently against its own background:

- `--color-bg: #0d0d0f` / `--color-text: #f6f6f8` — off-black and off-white, never `#000`/`#fff` (a pure-black ground crushes shadow/elevation differences to nothing; pure-white text on a dark ground vibrates at small sizes).
- Accent shifts from `#4f46e5` (light) to `#8079ef` (dark) — the same hue, lightened, because the light-mode indigo fails AA against a near-black background at the sizes it's used at (14px body text, button labels).
- All four status colors (success/warning/danger/info) get a lightened foreground **and** a low-opacity `rgba()` background rather than the light theme's flat pastel — a flat light pastel background reads as a light-mode leftover on a dark page; the translucent version reads as "this surface's own dark background, tinted," which is what Linear/GitHub Dark actually do (studied for the *pattern*, not copied for the *values* — every hex/rgba here is this codebase's own).

## 6. Product Editor coverage

The redesigned Product Editor consumes only semantic tokens throughout, so every part called out in the requirement resolves for free once the token layer is correct — no per-component dark-mode branch exists anywhere:

- **Tables** (Products list, Variants management table) — header row `--color-surface-muted`, row hover `--color-surface-muted`, dividers `--color-border`.
- **Media grid** — tile placeholder gradient runs between `--color-accent-bg` and `--color-surface-muted`, both theme-aware; upload dropzone border `--color-border-strong`.
- **Sticky sidebar rail** — background `--color-surface-muted`, hairlines `--color-border`.
- **Inputs** — `--color-surface` fill, `--color-border-strong` border, focus ring `--color-accent` (global `:focus-visible` rule, unchanged, already token-based).
- **Buttons** — primary `--color-accent`/`--color-text-on-accent`; the header's quiet save-state cluster uses `--color-success` / `--color-warning` / `--color-danger` dots, never a hardcoded color.
- **Badges / StatusBadge** — `--color-*-bg` + `--color-*` foreground pairs per status bucket, per `DESIGN_SYSTEM.md` §12.
- **Variant drawer** — panel `--color-surface`, scrim `--color-overlay` (new token, replaces the two remaining hardcoded `rgba(15,15,20,.4)` overlays that existed in `GlobalSearch.vue`/`AdminSidebar.vue` before this change).
- **Empty / loading states** — `EmptyState`/`Skeleton` (Design System §13) use `--color-surface-muted` and `--color-text-subtle` exclusively.

## 7. Charts & analytics widgets

No chart library is in use in `apps/admin` today (`AnalyticsWidget.vue` renders its own summary numbers, not a canvas/SVG chart) — there is nothing to retrofit right now. The architectural requirement is satisfied prospectively: any future chart must read its stroke/fill colors from `--color-accent`/the semantic status set at render time (not bake a hex palette into chart config), the same rule as every other component. Documented here so it's caught in review, not discovered later.

## 8. Code blocks

No syntax-highlighted code block component exists in `apps/admin` yet (no developer-console/API-key page ships code samples today). Same treatment as §7: the requirement is a constraint on whatever renders that content later (a highlighter theme pair, both keyed to `--color-surface`/`--color-text`/`--color-border`), not something to build now against nothing.

## 9. Icons

Already correct, unchanged: `AppIcon.vue`'s `<svg>` root uses `stroke="currentColor"`, and every consumer sets `color` via a semantic token — so icons already re-tint automatically with zero icon-specific dark-mode code. Verified, not modified.

## 10. Images

Uploaded product/media images are never filtered, inverted, or theme-adjusted — `MediaGrid` (Products redesign) renders `<img>` as-is. The tile *chrome around* an untouched image (border, placeholder gradient before an image loads) is theme-aware, but the small overlay chips/controls that sit directly on top of the photo itself (primary/alt-missing flags, reorder/delete buttons, the alt-text field) deliberately use a fixed `rgba(0,0,0,…)` scrim + white text, not a semantic token — those need to stay readable against arbitrary photo content regardless of which theme is active, and a token like `--color-text` would go near-white in dark mode, which fails against a bright photo. Documented at the point of use in `MediaGrid.vue`.

## 11. Accessibility

- Every semantic foreground/background pair above targets **WCAG AA (4.5:1) at 14px**, in both themes — this was already the design intent recorded in `DESIGN_SYSTEM.md` §1.4; this pass is where the values actually ship.
- Global `:focus-visible` ring (`app.vue`) is unchanged and already token-driven (`--color-accent`), so it stays visible and on-brand in both themes without any new rule.
- The `role="group"`/`role="menuitemradio"` theme switcher exposes `aria-checked` per option — a screen reader announces the current theme, not just three unlabeled buttons.
- Known minor follow-up, not a defect: a handful of `color: white` literals remain inside the sidebar's own always-dark chrome (e.g. the active-nav-item and store-switcher hover states) — harmless because that surface never changes with the page theme, but not yet tokenized for consistency. Left as a flagged cleanup rather than silently ignored.

## 12. Performance

Switching theme is a single DOM attribute write (`setAttribute('data-theme', …)`) — every visual change is CSS custom properties re-resolving, which the browser does synchronously on the next paint. There is no route change, no component remount, no `location.reload()` anywhere in `useColorMode.ts`. No blanket global `transition` was added on `color`/`background-color` for the switch — CSS variables already update instantly without one, and a blanket transition risks adding unwanted lag to unrelated hover/focus state changes elsewhere in the app; deliberately omitted rather than a gap.

## 13. Storefront reuse

`apps/storefront` is untouched — no shared token file exists between the two Nuxt apps today (each has its own `assets/css`). The architecture here (primitive → semantic layer, 3-state `data-theme` resolution, one `useColorMode` composable) has no admin-specific assumption in it and is the pattern to lift into the storefront when that redesign starts; doing so now would be scope creep against an app this task explicitly excludes.

## 14. Known gaps

- The ~74 admin pages outside the Products module were not swept for hardcoded hex colors as part of this pass (that sweep is `docs/design/UI_AUDIT.md`'s documented, pre-existing finding — three drifting reds, undefined `--color-*-muted` references, etc.) — this change fixes the *token layer* those pages should eventually migrate onto, it does not migrate them. Fixing the shell chrome's own remaining literals (`GlobalSearch`/`AdminSidebar` overlays, `UserMenu`/`StoreSwitcher`/`app.vue` submit-button text color) was in scope and is done.
- No automated contrast-ratio test exists yet (`DESIGN_SYSTEM.md` §15 flagged this as a migration risk already) — the values above were hand-checked against their stated AA target, not run through an automated checker.
