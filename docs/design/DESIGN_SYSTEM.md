# Merchant Admin Design System v2

Source of truth: [`docs/design/UI_AUDIT.md`](./UI_AUDIT.md). This document is a **specification only** — no application pages, routes, business logic, or API behavior change here. It extends the existing `apps/admin/app/assets/css/tokens.css` (v1), fixes the gaps the audit found (undefined `--color-*-muted` tokens, 3-way red drift, missing dark theme, no page-content components), and specifies every shared component needed to close those gaps.

Russian is the default product locale (`defaultLocale: 'ru'`). Every size/spacing spec below is chosen assuming **Cyrillic labels run 15-40% longer than their English equivalent** (e.g. "Сохранить" vs "Save", "Способ доставки" vs "Shipping method") — no component below assumes single-word English labels fit.

---

## 1. Color Tokens

### 1.1 Structure

Tokens split into two layers, matching where the audit found drift:

- **Primitive tokens** — raw values (`--gray-100`, `--indigo-600`...). Not consumed directly by components.
- **Semantic tokens** — the only tokens components reference (`--color-bg`, `--color-danger`...). This is the layer that already exists in v1; v2 keeps every existing name (no breaking rename) and fills the gaps.

This two-layer split is *new* in v2 — v1 only has semantic tokens hardcoded to literal hex. Introducing primitives underneath is what makes a dark theme possible without a second, hand-maintained copy of every semantic value.

### 1.2 Primitives (new in v2)

```css
:root {
  --gray-0:   #ffffff;
  --gray-50:  #f6f6f8;
  --gray-100: #eeeef1;
  --gray-200: #e4e4e9;
  --gray-300: #d3d3da;
  --gray-400: #b3b3bd;
  --gray-500: #8f8f9c;
  --gray-600: #6b6b76;
  --gray-700: #4a4a54;
  --gray-800: #2c2c33;
  --gray-900: #17171a;
  --gray-950: #0d0d0f;

  --indigo-50:  #eef0ff;
  --indigo-100: #dde0ff;
  --indigo-500: #4f46e5;
  --indigo-600: #4338ca;
  --indigo-700: #3730a3;

  --green-50:  #e6f4ea;
  --green-100: #cceada;
  --green-600: #1e7e34;
  --green-700: #186428;

  --amber-50:  #fdf3e0;
  --amber-100: #fbe6bd;
  --amber-600: #92600a;
  --amber-700: #744c08;

  --red-50:  #fbeaea;
  --red-100: #f6d2d1;
  --red-600: #b3261e;
  --red-700: #8f1e18;

  --blue-50:  #e8f1ff;
  --blue-100: #d0e2ff;
  --blue-600: #1a56db;
  --blue-700: #1544ad;
}
```

Every hex value here is either lifted verbatim from the existing `tokens.css` (`--indigo-500` = current `--color-accent`, `--green-600` = current `--color-success`, etc.) or a newly-derived step on the same ramp (e.g. `-700` shades for hover/dark-mode). **`--red-600` is fixed to the token value, not the audit's most common drifted value (`#c00`, 16 occurrences)** — migration should converge on `--red-600`, not the majority hardcode.

### 1.3 Semantic tokens — light theme (default)

```css
:root,
:root[data-theme='light'] {
  /* Surface & text */
  --color-bg: var(--gray-50);
  --color-surface: var(--gray-0);
  --color-surface-muted: var(--gray-100);        /* NEW — fixes audit finding: referenced in 13+ files, never defined */
  --color-surface-raised: var(--gray-0);          /* NEW — modals/popovers/dropdowns, distinct from page surface for shadow contrast */
  --color-border: var(--gray-200);
  --color-border-strong: var(--gray-300);
  --color-text: var(--gray-900);
  --color-text-muted: var(--gray-600);
  --color-text-subtle: var(--gray-500);
  --color-text-on-accent: var(--gray-0);

  /* Accent */
  --color-accent: var(--indigo-500);
  --color-accent-hover: var(--indigo-600);
  --color-accent-active: var(--indigo-700);       /* NEW — pressed state, was missing */
  --color-accent-bg: var(--indigo-50);

  /* Semantic status colors — each gets fg / bg / border / muted-bg */
  --color-danger: var(--red-600);
  --color-danger-hover: var(--red-700);
  --color-danger-bg: var(--red-50);
  --color-danger-border: var(--red-100);

  --color-success: var(--green-600);
  --color-success-hover: var(--green-700);
  --color-success-bg: var(--green-50);
  --color-success-border: var(--green-100);

  --color-warning: var(--amber-600);
  --color-warning-hover: var(--amber-700);
  --color-warning-bg: var(--amber-50);
  --color-warning-border: var(--amber-100);

  --color-info: var(--blue-600);                  /* NEW — fixes audit finding: --color-info-muted referenced, never defined */
  --color-info-hover: var(--blue-700);
  --color-info-bg: var(--blue-50);
  --color-info-border: var(--blue-100);

  --color-warning-muted: var(--amber-50);          /* NEW — alias kept for the 13-file migration path, see §12 */
  --color-info-muted: var(--blue-50);              /* NEW — same */

  /* Sidebar (unchanged from v1 — dark chrome regardless of theme) */
  --color-sidebar-bg: #15151a;
  --color-sidebar-text: #c7c7cf;
  --color-sidebar-text-muted: #7d7d87;
  --color-sidebar-border: #26262e;
  --color-sidebar-hover-bg: #1e1e25;
  --color-sidebar-active-bg: #262630;

  /* Overlay */
  --color-overlay: rgba(13, 13, 15, 0.48);         /* NEW — modal/drawer scrim */
}
```

### 1.4 Semantic tokens — dark theme (new in v2)

Activated by `[data-theme='dark']` on `<html>`. Sidebar stays the same dark chrome in both themes (it's already dark — no change needed, which is why dark-mode adoption cost is lower than it looks).

```css
:root[data-theme='dark'] {
  --color-bg: var(--gray-950);
  --color-surface: var(--gray-900);
  --color-surface-muted: var(--gray-800);
  --color-surface-raised: var(--gray-800);
  --color-border: var(--gray-800);
  --color-border-strong: var(--gray-700);
  --color-text: var(--gray-50);
  --color-text-muted: var(--gray-400);
  --color-text-subtle: var(--gray-500);
  --color-text-on-accent: var(--gray-0);

  --color-accent: #6d64ea;      /* lightened indigo-500 for AA contrast on dark surface */
  --color-accent-hover: #8079ef;
  --color-accent-active: #4f46e5;
  --color-accent-bg: rgba(79, 70, 229, 0.16);

  --color-danger: #e5867f;
  --color-danger-hover: #ef9d97;
  --color-danger-bg: rgba(179, 38, 30, 0.18);
  --color-danger-border: rgba(179, 38, 30, 0.32);

  --color-success: #5fbf7a;
  --color-success-hover: #7bcf94;
  --color-success-bg: rgba(30, 126, 52, 0.18);
  --color-success-border: rgba(30, 126, 52, 0.32);

  --color-warning: #e0a940;
  --color-warning-hover: #ecbd63;
  --color-warning-bg: rgba(146, 96, 10, 0.2);
  --color-warning-border: rgba(146, 96, 10, 0.34);
  --color-warning-muted: var(--color-warning-bg);

  --color-info: #6ea3f2;
  --color-info-hover: #8fb8f5;
  --color-info-bg: rgba(26, 86, 219, 0.2);
  --color-info-border: rgba(26, 86, 219, 0.34);
  --color-info-muted: var(--color-info-bg);

  --color-overlay: rgba(0, 0, 0, 0.6);
}
```

Every dark-mode fg color above is manually tuned against its own `-bg` for **WCAG AA (4.5:1) at `--text-base` (14px)** — not a mechanical filter-invert of the light values, which is the usual cause of failing-contrast dark themes.

**Theme switching mechanism:** `data-theme` attribute on `<html>`, driven by a `useColorMode()`-style composable (new — none exists today) that reads `prefers-color-scheme`, persists an explicit override in the same `admin_locale`-style cookie pattern already used for i18n (`plugins/hydrate-auth.client.ts` is the existing precedent for cookie-hydrated client state). Default: follow system preference, override via a toggle in `UserMenu.vue`.

### 1.5 Semantic color usage rules

| Token pair | Use for |
|---|---|
| `--color-danger` / `-bg` / `-border` | Destructive actions, error states, failed/cancelled statuses |
| `--color-success` / `-bg` / `-border` | Completed/paid/fulfilled/active statuses, success toasts |
| `--color-warning` / `-bg` / `-border` | Pending/processing/partial statuses, non-blocking alerts |
| `--color-info` / `-bg` / `-border` | Informational badges (draft, new, system-generated), info toasts |
| `--color-accent` | Primary actions, active nav/tab state, links, focus ring base |
| `--color-surface-muted` | Table header background, disabled field background, hover row background |
| `--color-surface-raised` | Modal/drawer/popover/dropdown surfaces (needs to read as "above" page content even before shadow renders) |

No component may hardcode a hex value. This is enforced per §14 (stylelint rule).

---

## 2. Typography

Base font stack, sizes, and weights are **unchanged from v1** (already reasonably disciplined per the audit) — v2 fixes the two gaps the audit flagged and adds a line-height scale, which v1 never defined at all (components were relying on browser default line-height, invisible in the audit until Cyrillic's taller glyphs made it a real risk).

```css
:root {
  --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, Roboto, system-ui, sans-serif;

  --text-xs: 0.75rem;    /* 12px — table meta, timestamps, helper text */
  --text-sm: 0.8125rem;  /* 13px — secondary body, table headers, labels */
  --text-base: 0.875rem; /* 14px — default body, inputs, buttons */
  --text-lg: 1rem;       /* 16px — h2, emphasized body */
  --text-xl: 1.25rem;    /* 20px — h1 in PageHeader */
  --text-2xl: 1.5rem;    /* 24px — reserved, currently unused; keep for future dashboard KPI numbers */

  --font-weight-regular: 400;   /* NEW name — v1 had no explicit regular token, relied on browser default */
  --font-weight-medium: 500;
  --font-weight-semibold: 600;

  /* NEW — line-height scale, absent in v1 */
  --leading-tight: 1.25;   /* headings */
  --leading-normal: 1.5;   /* body text, table cells — generous enough for Cyrillic diacritics/ascenders */
  --leading-relaxed: 1.65; /* wrapped paragraph copy in Alerts/empty states */
}
```

### Heading rules (fixes audit §5.8, §5.9)

```css
h1 { font-size: var(--text-xl); font-weight: var(--font-weight-semibold); line-height: var(--leading-tight); margin: 0; }
h2 { font-size: var(--text-lg); font-weight: var(--font-weight-semibold); line-height: var(--leading-tight); margin: 0 0 var(--space-3); }
h3 { font-size: var(--text-base); font-weight: var(--font-weight-semibold); line-height: var(--leading-tight); margin: 0 0 var(--space-2); }
```

`h1` and `h3` now have explicit global rules (v1 left both undefined at the base level, only `h2` was set) — closes the exact gap the audit found on `login.vue`/`register.vue` (unstyled `h1`) and `customers/[id].vue` (oversized `h3`).

### Russian text-length rule

Any component spec below that quotes a fixed pixel width for label/button text must treat it as a **minimum**, not a target — buttons and form labels use `width: auto` / `min-width`, never a fixed `width`, specifically because Russian strings routinely run longer than the English string used while prototyping (e.g. "Экспортировать в CSV" vs "Export CSV"). Truncation with `text-overflow: ellipsis` + a native `title` tooltip is the fallback only for table cells and badges, never for primary action labels.

---

## 3. Spacing Scale

Unchanged base scale, extended with two steps v1 was missing (v1 jumps `--space-6` (24px) straight to `--space-8` (32px), which the audit's product-editor finding (§5.7) shows pages routinely fill with un-tokenized `0.75rem`/`1rem` because no token existed in that gap):

```css
:root {
  --space-0: 0;
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --space-6: 24px;
  --space-7: 28px;   /* NEW */
  --space-8: 32px;
  --space-10: 40px;  /* NEW — section-level vertical rhythm, modal padding */
  --space-12: 48px;  /* NEW — page-level top margin, empty-state vertical centering */
}
```

Rule: **every `margin`, `padding`, `gap` in a new or migrated component must resolve to one of these tokens.** No bare `rem`/`px` in component `<style>` blocks (enforced per §14).

---

## 4. Layout Grid

The admin is a fixed-sidebar SPA shell, not a marginal-column content grid — so "grid" here means the shell's structural regions plus the content-area breakpoints the audit found entirely missing (§4 Critical Issue 3: 0 `@media` rules across all 76 page bodies).

```css
:root {
  --sidebar-width: 250px;
  --sidebar-width-collapsed: 68px;   /* now wired to an actual collapse feature, see Component §5 Sidebar note */
  --topbar-height: 60px;

  /* NEW — content-area breakpoints, previously nonexistent below the shell */
  --bp-sm: 640px;   /* single-column forms, filter bar wraps */
  --bp-md: 900px;   /* sidebar drawer breakpoint — matches existing AdminSidebar/Topbar @media, unchanged */
  --bp-lg: 1200px;  /* table density can increase, side-by-side detail panels allowed */
  --bp-xl: 1440px;  /* max content width before centering with margin */

  --content-max-width: 1440px;  /* NEW — page content stops growing past this; currently unbounded, causes tables to over-stretch on ultrawide monitors */
  --content-padding-x: var(--space-8);
  --content-padding-x-sm: var(--space-4);  /* below --bp-sm */
}
```

Page body structure convention (new — replaces each page inventing its own top-level wrapper):

```
.page                 → max-width: var(--content-max-width); margin-inline: auto; padding-inline: var(--content-padding-x)
  PageHeader
  FilterBar (if list page)
  <content: DataTable | form sections | detail cards>
```

Below `--bp-sm`, `--content-padding-x` switches to `--content-padding-x-sm` — this single rule, applied once at the `.page` wrapper level, is what the audit's "zero page-level responsive handling" finding is missing; individual components don't each need their own breakpoint logic for the outer gutter.

---

## 5. Border Radius

Unchanged from v1 — already well-structured, audit found no issues here.

```css
:root {
  --radius-sm: 6px;    /* inputs, buttons, badges, small controls */
  --radius-md: 8px;    /* cards inside cards, popovers */
  --radius-lg: 12px;   /* section/Card, modal, drawer panel */
  --radius-full: 999px; /* pills, avatars, switch track */
}
```

---

## 6. Borders

```css
:root {
  --border-width: 1px;
  --border-width-thick: 2px;  /* NEW — focus ring fallback for forced-colors mode, selected-row left border in DataTable */
}
```

Convention: `1px solid var(--color-border)` is the default divider/container border; `1px solid var(--color-border-strong)` for interactive-element borders (inputs, buttons) that need to read as "clickable" against `--color-border`'s more passive dividers. This distinction already exists implicitly in v1 (`app.vue` uses `-strong` for inputs, plain `-border` for table rows) — v2 just names it as a rule so new components apply it consistently instead of guessing.

---

## 7. Shadows & Elevation

v1's 3-step shadow scale is kept, plus an explicit elevation-to-shadow mapping (didn't exist before — nothing in the app used `--shadow-md`/`--shadow-lg` at all per the audit, they were defined but orphaned tokens).

```css
:root {
  --shadow-sm: 0 1px 2px rgba(15, 15, 20, 0.06);
  --shadow-md: 0 8px 24px rgba(15, 15, 20, 0.12);
  --shadow-lg: 0 20px 48px rgba(15, 15, 20, 0.22);
}
:root[data-theme='dark'] {
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
  --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.4);
  --shadow-lg: 0 20px 48px rgba(0, 0, 0, 0.5);
}
```

| Elevation level | Shadow token | Used by |
|---|---|---|
| 0 — flush | none | Card/section (border-only, no shadow — matches existing `app.vue` `section` styling) |
| 1 — raised | `--shadow-sm` | Dropdown menu, Tooltip, Popover |
| 2 — floating | `--shadow-md` | Toast, Combobox listbox |
| 3 — modal | `--shadow-lg` | Modal, Drawer, Command Palette |

---

## 8. Icon Sizing

`AppIcon.vue`'s existing 24-icon centralized SVG set is kept as-is (audit rated it one of the cleanest parts of the system) — v2 adds a size scale, which didn't exist (icons were rendered at whatever ad-hoc size each call site picked).

```css
:root {
  --icon-size-sm: 14px;  /* inline with --text-sm/base text, table row action icons */
  --icon-size-md: 18px;  /* default — buttons, form field adornments, nav */
  --icon-size-lg: 24px;  /* empty-state illustration icon, page-level alerts */
}
```

`AppIcon` gets a `size` prop (`sm | md | lg`, default `md`) mapping to these tokens instead of accepting an arbitrary pixel value per call site.

---

## 9. Motion

Nonexistent in v1 beyond a single `--transition-fast: 150ms ease` token (used inconsistently). v2 defines a small, purposeful scale — this is an admin tool, not a marketing site, so motion stays fast and utilitarian.

```css
:root {
  --duration-fast: 100ms;    /* hover/active state changes, checkbox/switch toggle */
  --duration-base: 150ms;    /* v1's --transition-fast, kept as alias */
  --duration-slow: 220ms;    /* modal/drawer enter-exit, dropdown open */
  --ease-standard: cubic-bezier(0.2, 0, 0, 1);
  --ease-decelerate: cubic-bezier(0, 0, 0, 1);   /* entrances */
  --ease-accelerate: cubic-bezier(0.4, 0, 1, 1); /* exits */

  --transition-fast: var(--duration-base) ease;  /* kept for backward compat with existing components consuming it */
}
```

Rule: respect `prefers-reduced-motion: reduce` globally — one rule at the root, not per-component:

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

---

## 10. Focus States

v1's global `:focus-visible` rule (`app.vue:50-57`) is the single most solid a11y baseline the audit found — kept, generalized to also cover the new component set instead of only native `input/select/textarea/button/a`.

```css
:root {
  --focus-ring-color: var(--color-accent);
  --focus-ring-width: 2px;
  --focus-ring-offset: 1px;
}

:is(button, a, input, select, textarea, [tabindex]):focus-visible {
  outline: var(--focus-ring-width) solid var(--focus-ring-color);
  outline-offset: var(--focus-ring-offset);
}
```

Every interactive component spec below (Button, Checkbox, Switch, Tabs, custom Dropdown/Combobox options, DataTable rows in bulk-select mode) must be reachable by keyboard and must render this exact ring — no component may suppress `outline` without supplying an equivalent replacement (audit found zero instances of suppressed focus rings, so this is a "don't regress" rule, not a fix).

---

## 11. Disabled States

No consistent disabled convention existed pre-v2 beyond `button[type=submit]:disabled { opacity: 0.6 }`. v2 generalizes:

```css
:root {
  --disabled-opacity: 0.5;
}

[disabled], [aria-disabled='true'] {
  opacity: var(--disabled-opacity);
  cursor: not-allowed;
  pointer-events: none;
}
```

`pointer-events: none` on the disabled element itself, combined with requiring disabled interactive elements to still be wrapped in something that can carry a `Tooltip` explaining *why* (e.g. "Insufficient permissions") — a bare `disabled` attribute with no explanation is a UX-friction pattern the audit implicitly flagged in the forms review (no field-level messaging anywhere). Disabled form fields additionally get `background: var(--color-surface-muted)` so a disabled `<input>` is visually distinguishable from a read-only one at a glance.

---

## 12. Semantic Colors — Status Mapping

This section directly answers the audit's #1 Critical Issue (§4.1: four incompatible status-rendering patterns, three of them broken or absent). It is the contract `StatusBadge` (§13) implements against.

| Semantic bucket | Token | Example domain statuses |
|---|---|---|
| `success` | `--color-success` / `-bg` / `-border` | `paid`, `fulfilled`, `delivered`, `active`, `completed` |
| `warning` | `--color-warning` / `-bg` / `-border` | `pending`, `partially_fulfilled`, `processing`, `awaiting_payment` |
| `danger` | `--color-danger` / `-bg` / `-border` | `cancelled`, `failed`, `refunded`, `voided` |
| `info` | `--color-info` / `-bg` / `-border` | `draft`, `new`, `system` |
| `neutral` | `--color-text-muted` on `--color-surface-muted` | `archived`, `unknown`, fallback for any status string not in the domain map |

Each domain's status → bucket mapping (order status, payment status, fulfillment status, etc. — 10 distinct fields on Orders alone per the audit) is **data, not component logic** — a per-domain map object consumed by `StatusBadge`'s `status` prop, so adding a new backend status value never requires touching the component itself, only the map. This directly targets audit finding §12 pattern 4 (undefined-token badges) and pattern 2 (bound-but-unstyled classes on fulfillments/refunds/returns) — both were caused by ad-hoc per-page class strings with no central map to fall back to.

---

## 13. Component Specifications

Convention for every entry below: **Purpose, Sizes, Variants, States, Spacing, Typography, Accessibility.** All dimensions reference tokens from §1-§9 only.

### Button

- **Purpose:** primary interactive trigger for actions (save, submit, navigate-with-side-effect). Replaces the current bare `button[type=submit]` global styling in `app.vue`.
- **Sizes:** `sm` (28px height, `--text-sm`, `--space-2` `--space-3` padding), `md` (36px height, `--text-base`, `--space-2` `--space-4` padding — default), `lg` (44px height, `--text-lg`, `--space-3` `--space-5` padding, reserved for empty-state primary CTAs).
- **Variants:** `primary` (bg `--color-accent`, text `--color-text-on-accent`, hover `--color-accent-hover`, active `--color-accent-active`), `secondary` (bg `--color-surface`, border `--color-border-strong`, text `--color-text`), `danger` (bg `--color-danger`, text white, hover `--color-danger-hover` — for destructive confirm actions, replacing bare `window.confirm()` styling mismatch), `ghost` (transparent bg, text `--color-accent`, hover bg `--color-accent-bg` — table row actions, toolbar icons-with-label).
- **States:** default, hover, active/pressed, focus-visible (ring per §10), disabled (§11), loading (spinner replaces label, button keeps its committed width via `min-width` measured at mount to prevent layout shift — important since Russian labels are long and a shrink-then-grow on loading is jarring).
- **Spacing:** icon-plus-label buttons use `--space-2` gap between icon (`--icon-size-md`) and label.
- **Typography:** `--font-weight-medium`, `white-space: nowrap` with `min-width: auto` — never truncates, the button grows instead (per Russian text-length rule §2).
- **Accessibility:** native `<button>` always, never a styled `<div>`. Loading state sets `aria-busy="true"` and keeps the original label in the accessible name (visually hidden, not replaced) so screen readers don't announce just "spinner."

### IconButton

- **Purpose:** icon-only trigger — table row kebab/actions, topbar icons, modal close button. Currently nonexistent as a component; audit found ad-hoc `<button>` + `<AppIcon>` pairings with inconsistent hit targets.
- **Sizes:** `sm` (28×28px, `--icon-size-sm`), `md` (36×36px, `--icon-size-md` — default), `lg` (44×44px, `--icon-size-lg`). Minimum 28px enforced even for `sm` to meet a reasonable touch-target floor.
- **Variants:** `ghost` (default, ubiquitous — transparent, hover `--color-surface-muted`), `danger-ghost` (hover `--color-danger-bg`, icon `--color-danger`).
- **States:** same as Button, plus a distinct `active` (toggled) state — bg `--color-accent-bg`, icon `--color-accent` — for toggle-style icon buttons (e.g. filter panel open/closed).
- **Spacing:** icon centered, `border-radius: var(--radius-sm)`.
- **Accessibility:** **mandatory** `aria-label` prop (no visible text label exists to fall back to) — component should fail a lint/PropType check if omitted, since this is exactly the kind of gap the audit found missing app-wide (§9 accessibility findings: aria-* in only 4/93 files).

### Input

- **Purpose:** single-line text entry. Replaces bare `<input>` styling currently in `app.vue:38-48`.
- **Sizes:** `sm` (28px height), `md` (36px height — default), matching Button heights so mixed toolbars (search input + button) align on a shared baseline.
- **Variants:** `default`, `with-leading-icon` (search, currency prefix), `with-trailing-action` (clear button, password reveal).
- **States:** default, hover (`border-color: var(--color-border-strong)` — already default, so hover is a no-op border but a subtle `--color-surface` → unchanged bg to avoid dead-looking hover), focus (ring per §10 + `border-color: var(--color-accent)`), invalid (`border-color: var(--color-danger)`, paired with `aria-invalid="true"` and a field-level error message — closes audit gap §10: product editor's page-level-only error currently can't attribute a failure to one of 7 forms), disabled (§11).
- **Spacing:** `--space-2` `--space-3` padding (unchanged from v1), `--space-1` gap between input and its error/hint text below.
- **Typography:** `--text-base`, placeholder text uses `--color-text-subtle` (currently relies on browser default placeholder color, inconsistent across the app).
- **Accessibility:** always paired with a `<label>` via `FormField` wrapper (§ below) — **never placeholder-only**, closing the audit's Critical Issue #4 (unlabeled login/register/filter inputs). Invalid state sets `aria-invalid` + `aria-describedby` pointing at the error message id — currently 0 instances of this pattern exist anywhere in the app per the audit.

### Textarea

- **Purpose:** multi-line text (product description, notes, refund reason).
- **Sizes:** `rows` prop controls height directly (no sm/md/lg — height is content-driven), min-height equivalent to 3 rows.
- **Variants:** `default`, `resizable` (default, `resize: vertical` only — never horizontal, which would break the fixed-width form layouts the audit found, e.g. `products/[id].vue`'s 480px-max-width grid).
- **States:** identical set to Input.
- **Spacing/Typography:** same as Input; line-height `--leading-normal` (important for Cyrillic — taller ascenders/descenders need the breathing room v1 never defined, see §2).
- **Accessibility:** same labeling contract as Input.

### Select

- **Purpose:** single choice from a short, known list (status filters, country, currency) — native-element based, matching the audit's finding that native `<select>` is already used everywhere and works fine (32 files) — v2 wraps it for consistent styling, does not replace it with a custom listbox (native gives free keyboard/mobile/AT support the audit found nowhere else in the app).
- **Sizes:** `sm`/`md` matching Input.
- **Variants:** `default`, `with-placeholder` (disabled first `<option>`, e.g. "Выберите статус").
- **States:** same as Input, plus the native `<select>`'s own open/closed (browser-controlled, not styleable beyond the closed-state trigger).
- **Accessibility:** native `<select>` retained specifically for its built-in a11y — a custom-div dropdown would need to reimplement everything the browser gives for free, and the audit found the app's existing custom dropdowns (`StoreSwitcher`, `UserMenu`) already duplicate logic that should be centralized (see Dropdown below) rather than expanded to cover Select's use case too.

### Combobox

- **Purpose:** searchable single/multi-select from a long or async list (customer picker in order creation, product search-to-add, tag assignment). Does not exist today — every "pick from many" interaction in the audit was either a native `<select>` (fine for short lists) or absent entirely.
- **Sizes:** `md` only (44px trigger height — slightly taller than Input/Select to comfortably contain a chip row in multi-select mode).
- **Variants:** `single`, `multi` (selected items render as removable chips inside the trigger, using Badge's `neutral` visual style).
- **States:** default, focus (opens listbox), loading (async search in flight — Spinner replaces the trailing icon), empty-results (renders EmptyState's compact variant inside the listbox), disabled.
- **Spacing:** listbox positioned via Popover (§ below), `--space-1` vertical gap from trigger, options padded `--space-2` `--space-3`, `--space-1` gap between stacked options.
- **Accessibility:** ARIA 1.2 combobox pattern — `role="combobox"` on the input, `aria-expanded`, `aria-controls` pointing at `role="listbox"`, `aria-activedescendant` tracking the highlighted option, full Up/Down/Enter/Escape keyboard support, typeahead filters the list live with a debounced `aria-live="polite"` results-count announcement (e.g. "12 результатов") — this is new ground for the app (audit found zero `aria-live` regions anywhere) and is the most complex a11y contract in this spec; build it once, reuse everywhere multi-select is needed rather than letting a second implementation appear.

### Checkbox

- **Purpose:** binary/multi-select toggle — bulk-select table rows, boolean form fields, filter facets.
- **Sizes:** 18×18px box (single size — matches `--icon-size-md` so it aligns visually with adjacent icons in a table header row).
- **Variants:** `default`, `indeterminate` (bulk-select "some rows selected" state — required for DataTable's header checkbox, currently impossible since no Checkbox component exists).
- **States:** unchecked, checked, indeterminate, focus-visible, disabled. Checked state: bg `--color-accent`, checkmark white SVG from `AppIcon`.
- **Spacing:** `--space-2` gap between box and its label when used standalone in a form (in DataTable it has no adjacent label — see DataTable spec).
- **Accessibility:** native `<input type="checkbox">` under the hood (styled via a sibling SVG box, not a fully custom div) — preserves native keyboard/AT behavior. Indeterminate set via the DOM property (`el.indeterminate = true`), not an ARIA attribute, since native checkboxes support it directly.

### Radio

- **Purpose:** single choice from a small visible set (shipping method selection, mutually-exclusive settings).
- **Sizes:** 18×18px, matching Checkbox.
- **Variants:** `default`, `card` (radio + label wrapped in a bordered card, selected state gets `border-color: var(--color-accent)` + `--color-accent-bg` background — used for higher-stakes choices like shipping method where more context than a label fits).
- **States:** same set as Checkbox minus indeterminate.
- **Accessibility:** native `<input type="radio">` grouped via shared `name`, `fieldset`/`legend` wrapping the group with the legend as the group's accessible name (currently: zero `fieldset`/`legend` usage anywhere in the app per the audit's forms review — this is new discipline being introduced, not a regression risk).

### Switch

- **Purpose:** immediate-effect boolean toggle (notification channel enabled/disabled, feature flag) — distinct from Checkbox in that a Switch implies the change applies instantly, no separate Save action, whereas Checkbox is part of a form that's explicitly submitted.
- **Sizes:** 36×20px track, 16px thumb.
- **Variants:** `default` only.
- **States:** off (bg `--color-border-strong`), on (bg `--color-accent`), focus-visible, disabled, `loading` (thumb shows a tiny spinner while the immediate-effect API call is in flight — necessary since there's no surrounding form-submit spinner to communicate the pending state).
- **Accessibility:** `role="switch"` with `aria-checked`, native `<button>` base (not `<input>`) per the ARIA switch pattern, Space/Enter to toggle.

### Badge

- **Purpose:** generic small label — counts, tags, non-status metadata ("New", category tags on customer-tags page). Distinct from StatusBadge, which is semantically bound to the status map in §12.
- **Sizes:** single size — 20px height, `--text-xs`, `--space-1` `--space-2` padding, `--radius-full`.
- **Variants:** `neutral` (default — `--color-surface-muted` bg, `--color-text-muted` text), `accent` (`--color-accent-bg` / `--color-accent`), `outline` (transparent bg, `1px solid var(--color-border-strong)`).
- **States:** static (no interactive states) unless used as a removable chip (Combobox multi-select), in which case it gets a trailing IconButton (`sm`) with `aria-label="Удалить {label}"`.
- **Accessibility:** decorative by default (`aria-hidden` not needed since it carries real text content); removable-chip variant's remove button must be independently focusable and labeled.

### StatusBadge

- **Purpose:** renders a domain status value using the semantic bucket map from §12. **This is the direct fix for the audit's #1 Critical Issue.**
- **Sizes:** matches Badge (single size).
- **Variants:** driven entirely by the `bucket` resolved from the `status` + `domain` props (`success | warning | danger | info | neutral`) — never a manually-chosen variant, to prevent the exact drift the audit found (pages picking colors ad hoc per status string).
- **Props contract:** `status: string` (raw backend value, e.g. `"partially_refunded"`), `domain: 'order' | 'payment' | 'fulfillment' | 'shipment' | ...` (selects which status→bucket map to consult, since e.g. `"pending"` means `warning` for payments but could mean something else in another domain), optional `label` override for cases where the raw status string isn't presentation-ready (falls back to a humanized/i18n'd version of `status` otherwise — every status label must go through the i18n bundle, not render the raw snake_case API value, which is a smaller but real polish gap the audit's page-by-page section implicitly flagged).
- **States:** static; unknown/unmapped status values render the `neutral` bucket rather than throwing or rendering unstyled — this directly prevents a repeat of the audit's pattern-2 finding (fulfillments/refunds/returns binding a class that resolves to nothing).
- **Spacing/Typography:** identical box model to Badge; icon-optional (`hasIcon` prop draws a small leading dot or check/x/clock icon at `--icon-size-sm` for the small subset of statuses — paid, cancelled, pending — worth the extra non-color signal for colorblind users, since color alone is otherwise the only differentiator).
- **Accessibility:** the semantic bucket must never be color-only — the icon-optional variant above exists specifically so at least the highest-traffic statuses (paid/failed/pending) carry a shape signal too, not purely hue; text label is always present regardless (never an icon-only StatusBadge), so screen readers get the status regardless of bucket.

### Card

- **Purpose:** the existing `section` global styling in `app.vue:117-127`, formalized as a real component instead of a bare-tag selector — same visual spec, now with defined slots.
- **Sizes:** N/A — width is contextual, `padding: var(--space-5)` fixed.
- **Variants:** `default` (border + no shadow, matches current), `raised` (adds `--shadow-sm`, for cards inside a Modal or a card that needs to visually separate from a `--color-surface-muted` page background rather than `--color-surface`).
- **States:** none (static container); optional `interactive` variant (hover `border-color: var(--color-border-strong)`, cursor pointer) for card-as-navigation-target use cases like a future dashboard.
- **Spacing:** header slot bottom-margin `--space-4` before body; footer slot top-border + `--space-4` top-padding when actions are present.
- **Typography:** header slot defaults to `h2` styling (matches existing `section h2 { margin-top: 0 }` rule).
- **Accessibility:** plain `<section>` (or `<article>` when it represents one self-contained record, e.g. one order in a list-of-cards view) with an accessible name via `aria-labelledby` pointing at the header slot's id — not currently present anywhere (existing `section` elements have no such association).

### Alert

- **Purpose:** inline, persistent, page-level or section-level message — replaces the bare `<p class="error">` pattern (`app.vue:83-86`) with a component that also covers success/warning/info, none of which currently exist as anything beyond ad-hoc per-page markup.
- **Sizes:** single size, `padding: var(--space-3) var(--space-4)`.
- **Variants:** `danger`, `warning`, `success`, `info` — each pairs `-bg`/`-border`/fg from §1.5, plus a leading icon (`--icon-size-md`) reinforcing the semantic (not color-only, same reasoning as StatusBadge).
- **States:** static, optional `dismissible` (trailing IconButton, `aria-label="Закрыть"`).
- **Spacing:** icon-to-text gap `--space-2`, `border-radius: var(--radius-md)`, `1px solid` the variant's `-border` token.
- **Typography:** `--text-sm` body, optional bold lead-in title at `--text-base` `--font-weight-medium`.
- **Accessibility:** `role="alert"` for `danger`/`warning` (assertive, since these represent something the user must act on), `role="status"` for `success`/`info` (polite) — this distinction matters and is new; the audit found zero `role="alert"`/`role="status"` usage anywhere.

### Toast

- **Purpose:** transient, corner-anchored notification for the result of an action (save succeeded, bulk action completed) — does not exist today; the audit found no post-action confirmation pattern at all beyond the page re-rendering with new data, which is silent and easy to miss.
- **Sizes:** max-width 360px (accounts for Russian message length — an English-first 280px toast would wrap awkwardly).
- **Variants:** same 4 semantic variants as Alert, visually similar but positioned fixed (`bottom: var(--space-6); right: var(--space-6)`, stacking upward with `--space-2` gaps for multiple toasts).
- **States:** entering (slide + fade, `--duration-slow` `--ease-decelerate`), visible (auto-dismiss after 5s for success/info, **no auto-dismiss for danger** — errors must be manually dismissed since auto-hiding a failure the user didn't finish reading is a real friction risk given the audit's finding that error messages are often the only failure signal a page provides), exiting (`--ease-accelerate`).
- **Accessibility:** `aria-live="polite"` region (assertive would interrupt too aggressively for routine success toasts) that the Toast system renders into — a single persistent live region in the app shell, not one per toast, so screen readers announce each new toast without re-announcing the whole stack.

### Tabs

- **Purpose:** switch between related views within one page (e.g. order detail's Overview/Timeline/Notes, if introduced — doesn't exist as a pattern today, pages instead stack every section vertically, which the audit's product-editor finding (7 forms on one page) suggests is already straining).
- **Sizes:** tab height 40px, `--text-base`.
- **Variants:** `line` (underline indicator, default — matches the accent-colored active-nav-item convention already established in the sidebar), `segmented` (pill-group background, for a small fixed set of 2-4 options, e.g. a view-density toggle).
- **States:** active (`--color-accent` text + underline/pill), inactive (`--color-text-muted`), hover (`--color-text`), focus-visible, disabled (a tab representing a section with no data yet, e.g. "Notes" before any exist).
- **Accessibility:** full `role="tablist"`/`role="tab"`/`role="tabpanel"` pattern, `aria-selected`, roving `tabindex` (Left/Right arrow keys move focus between tabs, only the active tab is in the normal Tab order) — a keyboard pattern absent everywhere else in the app today, so this is new discipline, worth getting exactly right once since Tabs will likely appear on every detail page eventually.

### Breadcrumb

- **Purpose:** already exists as `AdminBreadcrumbs.vue` (renamed from `AppBreadcrumb.vue` during Admin Shell v2), correctly implemented per the audit (`nav aria-label="Breadcrumb"`, `aria-current="page"`). v2 keeps the contract as-is, only restyles to consume the token set above — **no behavior change**.
- **Sizes:** `--text-sm`, `--icon-size-sm` separators.
- **Variants:** none needed.
- **States:** current-page crumb is non-interactive text (`aria-current="page"`, no href); all ancestors are links with `--color-text-muted` (not `--color-accent`, to avoid competing visually with primary page actions in PageHeader).
- **Accessibility:** unchanged — already correct, cited as a positive in the audit.

### Dropdown

- **Purpose:** the generic "click trigger, show a list of actions/options" primitive — formalizes what `UserMenu.vue` and `StoreSwitcher.vue` currently each hand-roll independently, including the duplicated click-outside logic the audit flagged (§5.10).
- **Sizes:** trigger inherits Button/IconButton sizing; menu min-width 180px, max-width 320px.
- **Variants:** `menu` (list of actions, `role="menu"`/`role="menuitem"` — powers UserMenu-style triggers), `listbox` (list of selectable options with a checked-state indicator, `role="listbox"`/`role="option"` — powers StoreSwitcher-style triggers). These are two ARIA patterns, not one, and must not be collapsed into a single role set even though they look visually similar — this is exactly the kind of distinction the audit found already correctly made ad hoc (`StoreSwitcher` uses listbox roles, `UserMenu` uses menu roles) and v2 must preserve that correctness while removing the duplicated plumbing underneath.
- **States:** closed, open (Popover-positioned, §Popover below), item-hover, item-focus (keyboard), item-disabled.
- **Spacing:** items padded `--space-2` `--space-3`, `--space-1` vertical gap between items, `1px solid var(--color-border)` divider between logical item groups (e.g. UserMenu's account items vs. sign-out).
- **Accessibility:** single shared `useDismissable`/`useClickOutside` composable (replaces the duplicated logic in both existing components) plus Escape-to-close and focus-return-to-trigger-on-close — **focus-return is currently not confirmed present in either existing implementation** per the audit's read; v2 makes it a hard requirement.

### Popover

- **Purpose:** the positioning primitive underneath Dropdown/Combobox/DatePicker/Tooltip — floating panel anchored to a trigger, flips/shifts to stay in viewport. Doesn't exist as an extracted primitive today (each custom dropdown recalculates or hardcodes its own position).
- **Sizes:** content-driven.
- **Variants:** `click` (toggle open on trigger click, closes on outside click/Escape), `hover` (Tooltip's use case — see below).
- **States:** positioning is dynamic; visual state is just open/closed with the elevation-2 shadow (`--shadow-sm`, per §7's mapping — actually listed as elevation 1 there; Popover itself is elevation 1, elevation 2 (`--shadow-md`) is reserved for Toast/Combobox listbox specifically since those float above other floating content).
- **Accessibility:** manages `aria-expanded` on the trigger and connects trigger/panel via `aria-controls`; does not itself impose a role (the consumer — Dropdown, Combobox, DatePicker — supplies the correct ARIA pattern on top).

### Tooltip

- **Purpose:** short supplementary label on hover/focus — icon-only button explanations, truncated-text full values, disabled-state reasoning (per §11).
- **Sizes:** `--text-xs`, `padding: var(--space-1) var(--space-2)`, `max-width: 240px` (wraps rather than growing indefinitely — relevant given Russian text length, a tooltip is exactly the kind of place a long string could otherwise blow out the layout).
- **Variants:** `dark` (default — `--color-sidebar-bg` background regardless of theme, ensures contrast against any page background; this is the one component allowed to reference the sidebar token outside the shell, since Tooltip must stay legible over both light and dark app themes).
- **States:** hidden, visible (fade only, `--duration-fast`, no slide — tooltips should feel instantaneous, not animated in a way that delays reading).
- **Accessibility:** shows on both `:hover` and `:focus-visible` (not hover-only, which would make it unreachable by keyboard — a real gap since the audit found IconButtons today have no explanatory affordance at all). Uses `role="tooltip"` + `aria-describedby` from the trigger, not `aria-labelledby` (tooltip content supplements, doesn't replace, the trigger's accessible name — an IconButton still needs its own `aria-label` per that component's spec above).

### Modal

- **Purpose:** blocking, centered dialog for focused single-task flows (confirm destructive action, quick-create form) — replaces the 15-file `window.confirm()` pattern the audit flagged (§6, §14) as inconsistent chrome with no room for consequence detail.
- **Sizes:** `sm` (400px, confirmation dialogs), `md` (560px, short forms — default), `lg` (800px, complex forms/previews).
- **Variants:** `default`, `danger` (confirmation modals for destructive actions get a `--color-danger` accent on the primary action button and a warning Alert-style icon in the header — this is the direct replacement for `window.confirm()`, and unlike the native dialog it can show consequence detail, e.g. "Это отменит 3 связанных возврата").
- **States:** entering/visible/exiting (`--duration-slow`, backdrop fades via `--color-overlay` while panel scales+fades in), focus-trapped while open.
- **Spacing:** header `--space-5` padding bottom-bordered, body `--space-5`, footer `--space-5` top-bordered with actions right-aligned and `--space-2` gap between them (Cancel as `secondary` Button, confirm as `primary`/`danger` Button per variant).
- **Accessibility:** `role="dialog"` `aria-modal="true"` `aria-labelledby` (header) — matching the pattern `GlobalSearch.vue` already gets right, generalized into a reusable component. Full focus trap (Tab/Shift+Tab cycle within modal), initial focus on the first focusable element or the panel itself if the first action is destructive (never auto-focus the danger confirm button, to prevent an accidental Enter-key confirmation), Escape closes (unless an in-progress async action is running, in which case Escape is briefly disabled with the loading Button state communicating why), focus returns to the trigger on close.

### Drawer

- **Purpose:** side-anchored panel for secondary content that benefits from screen real estate a Modal can't offer while keeping page context partially visible (e.g. quick order-note editor, filter builder on narrow viewports, notification detail preview). Doesn't exist today.
- **Sizes:** `md` (420px — default), `lg` (640px), always full-height, right-anchored by default (left-anchored variant available for RTL-readiness even though Russian is LTR, since the token/component layer should not assume LTR-only given the multi-locale i18n setup already in place).
- **Variants:** `overlay` (default — backdrop + traps focus, same modal-like behavior but slides from the edge), `push` (rare — pushes page content instead of overlaying; reserved for a future dense-workflow case, not needed at launch).
- **States:** same enter/exit motion pattern as Modal but translates on the anchored axis instead of scaling.
- **Accessibility:** identical contract to Modal (`role="dialog"`, focus trap, Escape, focus return) — Drawer is a Modal with a different geometry and motion, not a separately-designed a11y pattern.

### Command Palette

- **Purpose:** already exists and is correctly built (`GlobalSearch.vue` — `role="dialog"` `aria-modal="true"`, reuses the navigation config). v2 keeps behavior as-is, restyles to consume tokens, and notes it as the reference implementation other overlay components (Modal, Drawer) should structurally match.
- **Sizes:** unchanged (existing implementation's sizing is fine).
- **Accessibility:** unchanged — audit found no issues here.

### Pagination

- **Purpose:** page-through control for DataTable and any other paginated list — directly closes the audit's #2 Critical Issue (pagination present on only 2/76 list pages).
- **Sizes:** control height 32px, matching `sm` Button.
- **Variants:** `full` (Previous/Next + numbered pages + "Показано 1–20 из 143" summary text — default for DataTable), `simple` (Previous/Next + "Страница 2 из 8" text only, for lower-traffic lists where exact counts matter less).
- **States:** default page button, current page (`--color-accent-bg` fill, `aria-current="page"`), disabled Previous/Next at the boundaries, loading (brief disabled state while the next page fetches — prevents the double-fetch/race-condition risk of rapid clicking, currently unguarded anywhere pagination exists at all).
- **Spacing:** `--space-1` gap between page number buttons, summary text separated by `--space-4`.
- **Typography:** `--text-sm`, numbers `--font-weight-medium` when current.
- **Accessibility:** `nav aria-label="Pagination"` wrapping the control, each page button gets `aria-label="Страница {n}"` (number alone isn't enough context for AT users), current page additionally gets `aria-current="page"`.

### Data Table

This is the highest-leverage component in the entire system — the audit found 63 independently hand-rolled `<table>` implementations, and it is the direct fix for 3 of the audit's 4 Critical Issues (badges, pagination, responsive overflow) plus the majority of the "component duplication" section.

- **Purpose:** the single table implementation for every list page (orders, products, customers, payments, fulfillments, refunds, returns, shipments, and every other list view in the 76-route inventory).

- **Sizes / density:** two density modes, both meeting the audit's "compact but readable" brief —
  - `compact` (default for admin — row height 40px, `--space-2` `--space-3` cell padding, `--text-sm`) — matches Linear/Shopify Admin's information density, appropriate given merchants routinely scan 50-200+ rows.
  - `comfortable` (row height 48px, `--space-3` `--space-4` cell padding, `--text-base`) — opt-in per table for lower-cardinality, higher-stakes views (e.g. a 5-row payment-methods settings table where scannability matters less than legibility).

- **Anatomy:**
  - Header row: `--color-surface-muted` background (fixes the audit's `--color-surface-muted`-undefined gap directly at its highest-value use site), `--text-sm` `--font-weight-medium` `--color-text-muted` labels, optional sort affordance per column.
  - Body rows: `--color-surface` background, `1px solid var(--color-border)` bottom divider (unchanged from v1's existing table styling — that part was already fine), hover state `background: var(--color-surface-muted)`.
  - Selected row (bulk actions): `background: var(--color-accent-bg)`, `border-left: var(--border-width-thick) solid var(--color-accent)`.
  - Sticky header on vertical scroll for long tables; horizontal scroll container is **mandatory**, not optional (`overflow-x: auto` wrapper always present — fixes audit's finding that 57/63 tables lack this).

- **Filterable:** column-level filter affordances live in the FilterBar component above the table (see below), not inline in headers — keeps the header row purely structural/sortable. DataTable exposes a `filters` slot/prop contract that FilterBar's active-filter state feeds into.

- **Sortable:** clickable column headers where applicable — click toggles ascending/descending/none (3-state, not a 2-state forced-sort, since "no sort / default API order" is a legitimate state to return to), sort direction shown via a small chevron icon (`--icon-size-sm`) that appears on hover for unsorted columns and persistently for the active sort column.

- **Row actions:** kept as the audit's already-consistent pattern (inline text link/IconButton), not converted to a kebab-menu-only model — but a row can now optionally offer both a primary inline action (e.g. "Просмотр") and a Dropdown-menu (§Dropdown) for secondary actions (e.g. "Дублировать", "Архивировать") once there are more than ~2 actions, rather than the current pattern of cramming several inline links into the last column (seen on some detail-adjacent tables in the audit).

- **Bulk actions:** header checkbox (Checkbox's `indeterminate` variant handles "some selected") + per-row checkbox, leading column, only rendered when the table declares `selectable`. Selecting ≥1 row reveals a bulk-action toolbar (replaces the FilterBar region while active) showing selection count ("Выбрано: 12") and available bulk actions as Buttons — this entire interaction is new; no bulk action pattern exists anywhere in the current app per the audit.

- **States:**
  - `loading` — Skeleton rows (§ below), not a spinner overlay, so the table's column structure stays visible while data loads (reduces layout shift vs. the current "empty until resolved" pattern the audit found on `orders/index.vue`/`fulfillments/index.vue`).
  - `empty` — EmptyState (§ below) rendered inside the table body region, replacing the current bare `<p>No X yet.</p>` per page.
  - `error` — Alert (`danger` variant) rendered in place of rows, with a retry action — currently: a failed list fetch has no defined UI at all in the audit's findings.
  - `populated` — normal rows.

- **Typography:** cell text `--text-sm` (compact) / `--text-base` (comfortable); numeric columns (price, quantity) right-aligned with `font-variant-numeric: tabular-nums` so columns of numbers stay visually aligned — a detail the current hand-rolled tables don't apply anywhere.

- **Accessibility:**
  - Semantic `<table>`/`<thead>`/`<tbody>`/`<th scope="col">` structure preserved (not a div-grid reimplementation — native table semantics are what make screen-reader table navigation work at all, and nothing in the audit suggested the current native-table approach itself was the problem, only the lack of shared behavior around it).
  - Sortable headers: `aria-sort="ascending|descending|none"` on the `<th>`, and the sort-trigger is a `<button>` inside the `<th>`, not a click handler on the `<th>` itself (keyboard-reachable).
  - Row selection checkboxes: each gets `aria-label="Выбрать строку: {row label}"` (e.g. order number), header checkbox gets `aria-label="Выбрать все"` and communicates indeterminate state natively.
  - Keyboard navigation: Tab moves between interactive elements in a row (checkbox → primary link → row action menu) in visual order; a future enhancement (not required for v2 launch, noted for Phase 3 scoping) is arrow-key row-to-row navigation matching the grid pattern, but baseline Tab-order accessibility is the v2 requirement.
  - Live region announces bulk-selection count changes (`aria-live="polite"`, e.g. "Выбрано 3 из 20") and sort-change results, matching the Combobox precedent in §Combobox for dynamic result announcements.

### Empty State

- **Purpose:** replaces the audit's ~75 independently-authored `<p>No X yet.</p>`/`<p>No X match.</p>` variants with one component.
- **Sizes:** `default` (full region — used standalone on an empty list page, `--space-12` vertical padding, `--icon-size-lg` icon), `compact` (used inside a Combobox listbox or a small Card, `--space-6` padding, `--icon-size-md` icon).
- **Variants:** `no-data` (nothing exists yet — pairs with a primary-action CTA Button when the user can create the first item, e.g. "Создать заказ"), `no-results` (a filter/search produced zero matches — pairs with a "Сбросить фильтры" secondary action instead of a create CTA, since the fix here is adjusting filters, not creating data).
- **Typography:** title `--text-base` `--font-weight-medium`, supporting line `--text-sm` `--color-text-muted`.
- **Accessibility:** rendered inside the region it replaces (e.g. DataTable's `<tbody>` region or a `role="status"` wrapper for `no-results`, since a live filter change producing zero results benefits from a polite announcement — currently entirely silent per the audit).

### Skeleton

- **Purpose:** loading placeholder — the audit found **zero** skeleton usage anywhere in the app (`grep -ri skeleton` = 0 hits); this is a wholly new pattern.
- **Sizes:** `text` (a single line matching a given `--text-*` height), `block` (arbitrary width/height for card-shaped or avatar-shaped placeholders), `table-row` (preset matching DataTable's compact/comfortable row heights, used by DataTable's loading state specifically).
- **Variants:** shimmer animation (`--duration-slow`-paced gradient sweep, respects `prefers-reduced-motion` per §9 by falling back to a static `--color-surface-muted` block with no animation).
- **Accessibility:** the loading region containing skeletons gets `aria-busy="true"` and `role="status"` with a visually-hidden "Загрузка…" label — skeletons themselves are `aria-hidden="true"` (they're decorative placeholders, not content).

### Spinner

- **Purpose:** small inline loading indicator for a single async action in flight (Button loading state, Switch loading state) — distinct from Skeleton, which is for a whole content region.
- **Sizes:** `sm` (14px, inline in Button/Switch), `md` (20px, standalone e.g. inside a Combobox trigger), `lg` (32px, rare — full-panel loading state fallback when Skeleton isn't a good fit, e.g. a Modal body loading async content).
- **Variants:** `default` (`--color-accent` stroke), `on-accent` (white stroke, for use inside a filled `primary`/`danger` Button).
- **Accessibility:** `role="status"` `aria-label="Загрузка"` when used standalone; when nested inside an already-`aria-busy` Button, no duplicate announcement (the Button's own state communicates it).

### PageHeader

- **Purpose:** already exists (`PageHeader.vue`), correctly sets `h1` per the audit. v2 keeps the contract, extends it with slots the current implementation likely lacks based on the audit's findings (no mention of an actions slot pattern beyond ad hoc per-page buttons).
- **Anatomy:** title (required, `h1`), optional description line below (`--text-sm` `--color-text-muted`), optional `StatusBadge` inline next to title (for detail pages — e.g. order status shown at the page-header level, not just buried in a table row), right-aligned actions slot (primary + secondary Button group), optional Breadcrumb above the title (delegates to the existing `AdminBreadcrumbs`).
- **Spacing:** `--space-6` bottom margin before page content begins, `--space-2` gap between breadcrumb and title.
- **Accessibility:** unchanged core contract (real `h1`), actions slot buttons follow standard Button a11y.

### FilterBar

- **Purpose:** standardizes the filter/search row currently only implemented (well) on `customers/index.vue` — the audit's reference implementation, generalized into a reusable component instead of an outlier.
- **Anatomy:** leading `SearchInput` (see below), followed by a row of filter controls (`Select` for closed-set filters, `Combobox` for open/async filters, a date-range control for date filters), trailing "Сбросить" ghost Button (only rendered when ≥1 filter is active), overflow behavior: filters wrap onto a second line below `--bp-sm` rather than horizontally scrolling (matches the one bit of responsive flex-wrap behavior the audit found already working on `customers/index.vue`).
- **States:** active filters show a removable Badge/chip row below the control row when more than 2 filters are active simultaneously, so the user can see and clear individual filters without opening each control — new pattern, not present today.
- **Spacing:** `--space-3` gap between controls, `--space-4` bottom margin before DataTable begins.
- **Accessibility:** the whole bar is a `role="search"` landmark when it contains a SearchInput (it does, by default) — currently zero `role="search"` landmarks exist in the app.

### SearchInput

- **Purpose:** Input variant specialized for search-as-you-type — leading search icon, trailing clear IconButton once text is entered, debounced input event (300ms default) rather than triggering a fetch per keystroke.
- **Sizes:** matches Input `md`.
- **States:** empty, typing (no distinct visual state, just normal focus), has-value (clear button visible), searching (trailing Spinner replaces the clear button briefly while the debounced request is in flight).
- **Accessibility:** `role="searchbox"`, `aria-label` when not paired with a visible label (e.g. inside FilterBar where the placeholder itself communicates purpose — "Поиск по имени, email..." — but an `aria-label` still backs it up since placeholder text disappears on input).

### DatePicker

- **Purpose:** date/date-range selection — needed for analytics date ranges, promotion start/end dates, report filters; doesn't exist as a component today (the audit didn't find one, and any date input currently in the app would be a bare native `<input type="date">`, which is kept as the underlying value contract but needs a nicer picker UI for range selection specifically).
- **Sizes:** trigger matches Input `md`; calendar popover fixed at 296px per month grid.
- **Variants:** `single` (one date), `range` (start+end, two-month side-by-side calendar on viewports ≥`--bp-lg`, stacked single-month-at-a-time below that), `with-presets` (adds a left-hand column of common ranges — "Сегодня", "Последние 7 дней", "Последние 30 дней", "Этот месяц" — for the analytics use case specifically, where presets cover the majority of real usage).
- **States:** default, focus, range-in-progress (start selected, hovering end — shows a preview highlight across the in-between dates), disabled dates (e.g. can't pick a promotion end date before its start date), disabled (whole field).
- **Spacing/Typography:** calendar cells 36×36px (touch-comfortable, matches Button `sm` height for visual rhythm), `--text-sm` day numbers, `--text-xs` weekday headers.
- **Accessibility:** built on native `<input type="date">`/`type="text"` with `role="grid"`/`role="gridcell"` for the calendar per the APG date-picker pattern, full arrow-key day navigation, PageUp/PageDown for month navigation, `aria-label` on each day cell including the full date (not just the day number, since a screen reader landing mid-grid needs full context) — this is, alongside Combobox and DataTable, one of the three most complex a11y contracts in this spec and should be scoped carefully against a real library (see §15) rather than hand-built from scratch.

---

## 14. Frontend Token Implementation Strategy (Nuxt)

**Principle:** keep the existing `tokens.css` file as the mechanism — it already works, is already the single import in `nuxt.config.ts` (`css: ['~/assets/css/tokens.css']`), and the audit found no reason to replace CSS custom properties with a JS-in-CSS/CSS-in-JS approach for a `ssr:false` admin SPA with no theming requirement beyond light/dark.

1. **Split `tokens.css` into `tokens/primitives.css` + `tokens/semantic.css`** (light values on `:root`, dark values under `:root[data-theme='dark']`), both still registered in `nuxt.config.ts`'s `css` array, load order primitives-then-semantic. Keeps the audit-cited "change the look by editing this file, not hunting through components" property intact — now two files instead of one, but same principle.

2. **New `components/ui/` directory** (doesn't exist today — current `components/` is flat, mixing shell chrome with feature-specific builders per the audit). All 30 specs above live under `app/components/ui/Button.vue`, `app/components/ui/DataTable/` (multi-file — header/row/pagination-integration split, since it's the largest component), etc. Existing shell components (`AdminSidebar`, `PageHeader`, `AdminBreadcrumbs`, `AppIcon`) either move into `ui/` if their contract changes (PageHeader gets new slots) or stay put if unchanged (AdminBreadcrumbs).

3. **Auto-import via Nuxt's built-in `components: true` scanning** (already implicit — no new dependency) — `app/components/ui/**` becomes globally auto-imported like the existing flat `components/` already is, no manual registration needed, no new build tooling.

4. **Composables for cross-cutting behavior**, colocated in `app/composables/`: `useDismissable` (Dropdown/Popover/Modal shared outside-click+Escape logic — replaces the duplicated logic in `StoreSwitcher`/`UserMenu`), `useFocusTrap` (Modal/Drawer), `useColorMode` (new, §1.4), `useToast` (imperative API — `const toast = useToast(); toast.success('Сохранено')` — backing the Toast component's single shared live region).

5. **No new runtime dependency required for most components** — Button/Input/Badge/Card/Alert/Tabs/Pagination/Skeleton/Spinner/PageHeader/FilterBar/SearchInput are all straightforward to hand-build against the token set, matching the app's existing zero-dependency posture (audit noted no UI library is installed and that's a defensible choice given the app's from-scratch CSS architecture).

6. **Two components are legitimate build-vs-buy decisions, flagged for a separate discussion before Phase 3 implementation, not decided here:** Combobox and DatePicker both have substantial, easy-to-get-wrong accessibility contracts (full APG combobox/date-grid patterns). A small headless-behavior dependency (e.g. Floating UI for Popover positioning specifically, and/or a headless combobox/date primitive) would reduce risk versus hand-building — this trades "zero dependencies" for "correct keyboard/AT behavior on the two hardest components," which is very likely the right trade, but should be a deliberate decision made when Phase 3 scoping starts, not an unstated default.

7. **Stylelint enforcement** (new — `eslint.config.mjs` currently has no CSS-property linting per the audit): add `stylelint` with a rule set banning raw hex colors and raw `px`/`rem` spacing values inside any `<style>` block under `app/components/ui/**` and (once migration reaches them) `app/pages/**`, forcing `var(--color-*)`/`var(--space-*)` usage. This is what prevents the token-adoption drift the audit documented (three different hardcoded reds, un-tokenized spacing in the product editor) from recurring.

8. **Storybook or an equivalent isolated component-preview route is out of scope for this document** — worth a follow-up decision once Phase 3 begins, since building 30 components against a token system with zero visual QA surface (beyond eyeballing them wired into a page) is itself a process risk, but adding tooling is an implementation decision, not a design-system spec concern.

---

## 15. Migration Risks

- **Regression risk in the three broken status-binding pages** (fulfillments/refunds/returns, audit §12 pattern 2) — migrating these to `StatusBadge` is a behavior *fix*, not neutral; QA must confirm the newly-visible colors match the intended semantic bucket per status value, since there was no working reference implementation to diff against.
- **Combobox/DatePicker accessibility complexity** (§14.6) — highest risk of shipping a subtly-broken keyboard/AT experience if hand-built under time pressure; recommend resolving the build-vs-buy decision before any page consumes either component, not after.
- **Dark theme contrast validation** — the manually-tuned dark-mode fg/bg pairs in §1.4 need actual automated contrast testing (axe/Lighthouse or a contrast-ratio script) before shipping, not just eyeballing; this document specifies target ratios (AA, 4.5:1) but hasn't run a validator against the exact final hex values.
- **Russian text-length breakage during migration** — several existing pages use fixed-width containers (`products/[id].vue`'s 480px form grid, per the audit) that will need to be re-tested with real Russian copy once migrated to the new Input/Select components, since a component fix doesn't automatically fix a page-level fixed-width wrapper around it — that's page-level work for the later phases, not solved by this spec alone.
- **`window.confirm()` → Modal migration touches 15 files** (per audit) — each needs its confirm copy rewritten to fit Modal's header/body/footer slots rather than a single string, which is more than a mechanical swap and should be scoped per-file, not batched blindly.
- **DataTable is large enough to become a scope-creep magnet** — the spec above intentionally excludes arrow-key row navigation and defers it; implementers should resist adding it (or virtualization, or column-resizing, or column-reordering) into the v2 build unless a specific page's data volume already demands it, per the audit's actual finding (no page currently shows evidence of needing virtualization-scale row counts).
- **No visual regression tooling exists today** — since 30 components are being introduced at once, a manual-only QA pass risks missing token-drift regressions exactly like the ones this spec is trying to eliminate; flagged in §14.8 as a process decision to make before Phase 3 starts in earnest.

---

## 16. Report Summary

**Design tokens:** two-layer system (new primitives + existing semantic names, no breaking renames) covering color (light + new dark theme), typography (+ new line-height scale), spacing (+2 new steps), a new content-area breakpoint grid, radius (unchanged), borders (+1 new token), shadows/elevation (now actually mapped to components), new icon-size scale, new motion scale with `prefers-reduced-motion` handling, generalized focus-ring and disabled-state rules, and the semantic status-bucket map that `StatusBadge` implements against.

**Components:** 30 specified — Button, IconButton, Input, Textarea, Select, Combobox, Checkbox, Radio, Switch, Badge, StatusBadge, Card, Alert, Toast, Tabs, Breadcrumb, Dropdown, Popover, Tooltip, Modal, Drawer, Command Palette, Pagination, Data Table, Empty State, Skeleton, Spinner, PageHeader, FilterBar, SearchInput, DatePicker (31 counting SearchInput as distinct from Input, matches the requested list exactly). Three (Breadcrumb, Command Palette, and largely PageHeader) are "keep the existing correct implementation, restyle to tokens only" — everything else is new.

**Proposed component architecture:** stay dependency-light — CSS custom properties (no CSS-in-JS), new `app/components/ui/` directory with Nuxt's existing auto-import scanning, shared composables for cross-cutting overlay/focus/theme/toast behavior, stylelint gate against raw hex/px to prevent the exact drift the audit documented. Two components (Combobox, DatePicker) flagged as legitimate build-vs-buy candidates for a small headless a11y dependency rather than full hand-builds.

**Migration risks:** three pages have currently-broken status rendering that will visibly change (fix, but needs QA against real semantics); Combobox/DatePicker are the highest a11y-complexity builds; dark-theme contrast needs automated validation, not eyeballing; Russian text length will expose fixed-width page containers that this spec doesn't itself fix; the 15-file `window.confirm()` → Modal migration needs per-file copy rewrites, not a mechanical batch change; DataTable scope must be actively guarded against creep; no visual regression tooling exists yet, which is a process gap given 30 components shipping at once.

**Recommended first components to implement:** (1) token file split + stylelint gate (§14.1, §14.7) since every other component depends on it, (2) **StatusBadge** — highest-impact, fixes a Critical audit issue on first use, low build complexity, (3) **DataTable** (including Pagination, EmptyState, Skeleton as its direct dependencies) — the other Critical audit issues live here, and it's consumed by the largest number of pages, (4) **Button / IconButton / Input / FormField-wrapper** — needed underneath nearly every other component and every future page migration, (5) **Modal** — unblocks the `window.confirm()` replacement. Combobox and DatePicker should come later, after the build-vs-buy decision in §14.6 is made deliberately rather than defaulted.
