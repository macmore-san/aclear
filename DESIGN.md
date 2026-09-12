---
name: AClear DTR
description: An operate-mode payroll DTR dashboard for a water refilling station, on an ocean-blue/sea-green accent system
colors:
    background: '#f4f7f9'
    foreground: '#1e293b'
    card: '#ffffff'
    primary: '#0088cc'
    primary-foreground: '#ffffff'
    secondary-accent: '#20b2aa'
    secondary: '#e9eef2'
    muted-foreground: '#64748b'
    border: '#d9e1e7'
    destructive: '#dc2626'
    status-on: '#20b2aa'
    status-off: '#f59e0b'
    sidebar: '#1a2530'
    sidebar-foreground: '#e7ecef'
typography:
    display:
        fontFamily: 'Author, ui-sans-serif, system-ui, sans-serif'
        fontSize: '1.25rem'
        fontWeight: 700
        lineHeight: 1.2
        letterSpacing: '-0.01em'
    body:
        fontFamily: 'Plus Jakarta Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '0.875rem'
        fontWeight: 400
        lineHeight: 1.5
    label:
        fontFamily: 'Plus Jakarta Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '0.6875rem'
        fontWeight: 500
        letterSpacing: '0.14em'
    data:
        fontFamily: 'Plus Jakarta Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '0.75rem'
        fontWeight: 500
rounded:
    lg: '0.5rem'
    md: 'calc(0.5rem - 2px)'
    sm: 'calc(0.5rem - 4px)'
spacing:
    sm: '0.5rem'
    md: '1rem'
    lg: '1.5rem'
components:
    button-primary:
        backgroundColor: '{colors.primary}'
        textColor: '{colors.primary-foreground}'
        rounded: '{rounded.md}'
        padding: '0.5rem 1rem'
    button-secondary:
        backgroundColor: '{colors.secondary}'
        textColor: '{colors.foreground}'
        rounded: '{rounded.md}'
        padding: '0.5rem 1rem'
    register-header:
        backgroundColor: '{colors.card}'
        textColor: '{colors.foreground}'
        typography: '{typography.display}'
        rounded: '{rounded.sm}'
        padding: '1rem 1.25rem'
    sidebar:
        backgroundColor: '{colors.sidebar}'
        textColor: '{colors.sidebar-foreground}'
---

# Design System: AClear DTR

## Overview

**Client-specified brand system**, superseding the earlier "Sanitary Permit Register" world (cream ledger paper, one seal-teal accent, serif record titles) that shipped first. That world is retired outright, not blended: this is a client-provided palette and typography spec — Ocean Blue primary, Light Sea Green secondary accent, ice-white canvas, a fixed dark-navy sidebar, Author display type over Plus Jakarta Sans body — applied as the system's new visual identity while every structural pattern the product already relied on (the document-header masthead, ruled tables, the status-seal glyph) is preserved unchanged underneath it, since the request was to restyle the surface, not to re-architect it.

No finish-review pass has run (no browser tooling available in this environment) — this file documents the shipped tokens directly; treat visual QA as an open item.

**Key Characteristics:**

- Ice-white canvas (`#f4f7f9`) with pure-white cards and one Ocean Blue primary accent
- Light Sea Green as the second, cooler accent for charts, metrics, and highlight items — never competing with primary for the same role
- A fixed dark-navy sidebar (`#1a2530`) in both light and dark theme — a stable structural panel, not theme-inverted
- Author (700) for headings, Plus Jakarta Sans for everything else, including data values
- Flat throughout — no drop shadows, no gradients (inherited, unchanged)

## Colors

Full-palette strategy: two named accents with clearly split roles, plus a light neutral canvas.

### Primary

- **Ocean Blue** (`#0088cc`, dark: `#29a3e0`): buttons, active tabs, selected states, links, focus rings. `--primary` / `--ring` / `--sidebar-primary`.

### Secondary

- **Light Sea Green** (`#20b2aa`, dark: `#2dd4cb`): graphs, metrics, highlight items, and the "on-file / complete" status-seal state (`--status-on`, `--chart-2`). Never used for buttons — that role stays Ocean Blue's alone.

### Neutral

- **Dashboard Background** (`#f4f7f9`, `--background`): the main canvas.
- **Card / Container** (`#ffffff`, `--card`): every widget panel, masthead, and table container — a crisp step up from the canvas.
- **Primary Text** (`#1e293b`, `--foreground`): headings, table text, core numbers.
- **Sidebar** (`#1a2530`, `--sidebar`): fixed dark navy, same value in both themes.
- **Status Off** (`#f59e0b`): dashed status-seal state and warning banners — pending / inactive / missing data, amber, not destructive red.
- **Destructive** (`#dc2626`): delete actions and hard errors only.

### Named Rules

**The Two-Accent Split.** Ocean Blue owns every interactive/selection role (buttons, active tabs, links, focus). Light Sea Green owns every data/measurement role (chart series, metric highlights, the "on" status seal). A component never uses both accents for the same purpose.

**The Fixed-Sidebar Rule.** The sidebar is dark navy (`--sidebar` / `--sidebar-foreground` etc.) in both light and dark mode — it does not invert with the theme toggle, unlike every other surface.

## Typography

**Display Font:** Author, 700 (with system-ui fallback), loaded from Fontshare — not in the Google Fonts catalog, so it ships via a direct `<link>` in `app.blade.php` rather than the project's usual self-hosted `@fonts` pipeline.
**Body Font:** Plus Jakarta Sans (with system-ui fallback), self-hosted via the existing bunny-fonts Vite plugin.

### Hierarchy

- **Display** (700, `text-xl`/`text-2xl`, tight tracking): `font-serif` (the CSS variable name kept from the prior world; it now resolves to Author) — record titles inside `RegisterHeader` and page/dialog headings.
- **Label** (500, 11px, tracked uppercase): masthead meta lines and stat-cell labels.
- **Body** (400, 14px): default UI text, descriptions, table prose cells.
- **Data** (500, 12px, `tabular-nums`): every emp_code, time, hours figure, and count — same face as body copy now (no separate mono face), weight bumped to 500 so figures still stand slightly apart from prose in the same table.

## Layout, Elevation, Shapes, Components

Unchanged from the prior world except where noted below — this was a token-level restyle, not a structural rebuild:

- Single-column page shells with a `RegisterHeader` masthead first on every page; the DTR screen keeps its two-column filter-rail + preview layout.
- Flat depth model: blocks are set apart by a 1px `border` + `bg-card`, never a `box-shadow`.
- `--radius` raised from `0.25rem` to `0.5rem` — the client's ocean-blue/sea-green system reads as a standard dashboard product, not a printed form, so corners soften accordingly. `rounded-lg`/`md`/`sm` derive from it as before.
- `StatusSeal` (`resources/js/components/status-seal.tsx`) is unchanged in structure (filled ring = on-file, dashed ring = pending) and now draws in Light Sea Green / amber instead of the old green/amber pair.
- `RegisterHeader`, ruled tables, and `nav-main.tsx`'s divided sidebar rows are unchanged in markup; they inherit the new tokens automatically.

## Do's and Don'ts

### Do:

- **Do** use Ocean Blue only for buttons, active tabs, selected states, links, and focus rings.
- **Do** use Light Sea Green only for graphs, metrics, and highlight/status-on items.
- **Do** keep the sidebar dark navy in both themes.
- **Do** set headings in Author (700) and everything else in Plus Jakarta Sans.

### Don't:

- **Don't** reintroduce a second display face or reach for the old Zilla Slab / Roboto Mono stack — both are fully retired.
- **Don't** let the sidebar invert with the light/dark toggle — it is a fixed navy panel by design.
- **Don't** add drop shadows or gradients — the flat depth model carries over from the prior world unchanged.

## Not canonized

This redesign covers color tokens, typography, and the sidebar/radius adjustments called out above. It does not touch copy, information architecture, or the underlying structural components (masthead, ruled tables, status seal) — those were preserved deliberately, per the request, rather than re-evaluated. No finish-reviewer pass has run (no browser tooling in this environment); treat visual QA as an open item.
