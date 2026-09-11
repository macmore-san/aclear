# Design

<!-- impeccable:design-schema 1 -->

## World

**The permission ledger.** The admin shell reads as rows and columns of database truth, not a friendly SaaS dashboard: hairline rules replace card shadows as the primary separator, tabular data sets in a real monospace, status and structure show as ruled lists rather than pills and cards. It deliberately refuses the soft-card, pastel-icon, rounded-everything look most AI-generated admin panels default to.

Chosen via `impeccable concept-seed --scope direction --mode operate` (seed key `c084df05`), grounded candidate #3 ("permission matrix / ledger"), raised against three declined challengers: the seven-segment display family (an off/unlit state is drawn as deliberately as a lit one — informs the hollow-vs-filled status language), Ikeda's _datamatics_ (tabular-numeral rigor, hairline rule discipline), and Kraftwerk's _Man-Machine_ (one disciplined accent color, reserved for state, never decoration). Full contract: `.impeccable/surfaces/app-shell.md`.

## Palette

Restrained strategy — neutrals plus one accent, both themes a true paper/ink inversion (not a dimmed light theme):

| Token                           | Light                               | Dark                                    |
| ------------------------------- | ----------------------------------- | --------------------------------------- |
| `--background` / `--foreground` | `oklch(1 0 0)` / `oklch(0.145 0 0)` | `oklch(0.145 0 0)` / `oklch(0.985 0 0)` |
| `--primary` (the one accent)    | `oklch(0.47 0.16 257)`              | `oklch(0.75 0.14 257)`                  |
| `--border` / `--input`          | `oklch(0.87 0 0)`                   | `oklch(0.32 0 0)`                       |
| `--sidebar`                     | `oklch(0.99 0 0)`                   | `oklch(0.12 0 0)`                       |
| `--status-on`                   | `oklch(0.6 0.14 155)`               | `oklch(0.72 0.16 155)`                  |

The accent (`--primary`/`--ring`) is the only saturated color in the system and appears only on primary actions, active/focus states, and the sidebar's active item — never as decoration.

## Type

- **Prose / UI labels:** Instrument Sans (`--font-sans`), unchanged from the stock starter kit — a workhorse grotesk, appropriate for an Operate-mode surface.
- **Data:** IBM Plex Mono (`--font-mono`), self-hosted via the same `laravel-vite-plugin/fonts` bunny helper. Used for anything that is a database value rather than a label: emails, permission names (`users.view`), route names, badges (role tags), and table cells generally (`tabular-nums` applied at the `<table>` level so numerals align even in the sans face).

## Shape & structure

- `--radius` reduced from shadcn's default `0.625rem` to `0.375rem` — a ledger doesn't round its corners much.
- Hairline `border` (1px) is the primary separator everywhere; no new drop shadows were added for chrome. Existing `shadow-xs` on inputs/buttons (inherited from shadcn) was left as-is — it's a near-invisible default, not new card chrome.
- The sidebar's top-level rows are divided by `border-b` between every item (`resources/js/components/nav-main.tsx`), not just spaced with a gap — the FIRST VIEWPORT reads as a ruled list on load.
- Tables (`resources/js/components/ui/table.tsx`) are dense: compact row padding, hairline row/header borders, no zebra striping.
- Badges (`resources/js/components/ui/badge.tsx`) are squarer (`rounded` not `rounded-md`) and set in mono — they carry data (role/permission names), not decorative tags.

## Motion

- Buttons (`resources/js/components/ui/button.tsx`): `active:scale-[0.97]` over 160ms ease-out — press feedback on every pressable control, per Emil Kowalski's component-building principles.
- Sidebar group chevron: 200ms ease-out rotation; group content uses Radix Collapsible's native height animation (`animate-collapsible-down/up` from `tw-animate-css`), not a custom keyframe.
- Menu drag rows (`resources/js/pages/admin/menus.tsx`): the lifted row gets `scale(1.02)` + shadow; dnd-kit's own transform/transition handles sibling displacement.
- `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)` and `--ease-in-out: cubic-bezier(0.77, 0, 0.175, 1)` are available as theme tokens for future motion work.

## What this pass covered

Tokens (`resources/css/app.css`, `vite.config.ts`), the authenticated shell (`app-sidebar.tsx`, `nav-main.tsx`), and the primitives every admin page shares: `button`, `badge`, `table`. The four admin pages (users/roles/permissions/menus) inherit the system entirely through those shared components — no page was hand-restyled individually.

**Explicitly out of scope for this pass** (per the "tokens + shell + core primitives" scope the user chose over an exhaustive pass): the public `welcome.tsx` marketing page (a Persuade surface, different mode — would need its own direction round), the auth screens (login/forgot-password/etc.), and the settings pages. They still render correctly against the new tokens (shared CSS variables), just without deliberate ledger-specific treatment of their own components.

## Verification

- `impeccable detect --json` over every changed file: no findings.
- Screenshots captured via a Playwright script (login as Super Admin, `/admin/users` and `/admin/menus`) at desktop light, desktop dark, and mobile light — reviewed inline, not through the finish-reviewer subagent (out of scope for this pass; recommended before this direction ships beyond a starter kit).
- No comp round: no image generation was available in this environment, so the build is code-led per `new-work.md` — ambition is carried by the direction contract above, not an approved reference image.
