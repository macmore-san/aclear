# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 13 + Inertia v3 + React 19, shadcn/ui components, Tailwind CSS v4, Spatie Laravel Permission. (Pre-existing starter kit; not a greenfield stack choice.)

## Users

Two audiences, in sequence:

1. **Developers** who clone this starter kit as the base for a new client or internal admin system. They use it once, at the start of a project, to see access control (users, roles, permissions, menus) already solved so they can focus on domain features.
2. **Admins and staff** who then operate whatever product gets built on top of the kit — creating accounts (registration is admin-only, no public sign-up), assigning roles, and managing the sidebar's menu structure. This is a daily-use back-office tool, not a consumer-facing surface.

## Product Purpose

Ship admin systems fast: role & permission management (Spatie), a dynamic sidebar built from a DB-driven, permission-filtered menu tree, drag-and-drop menu management (2 levels, reorder), and route-level protection via a `CheckMenuPermission` middleware — all solved on day one so a new project's first commit is its actual domain logic, not access control scaffolding.

## Positioning

Most Laravel starter kits stop at authentication. This one ships the layer after auth: who can see what, and where it lives in the sidebar — configurable by a non-developer admin without touching code, because the menu tree and permissions are database rows, not a hardcoded nav array.

## Operating Context

- Long admin sessions: users work through tables of accounts, roles, and menu items rather than dashboards.
- Menu management is inherently structural (drag lists, nesting) — an editing tool, not a reading surface.
- The app has two structurally different areas: authentication screens (login, password reset) and the authenticated admin shell (sidebar + content).

## Capabilities and Constraints

- Roles/permissions: Spatie Laravel Permission. A `Super Admin` role bypasses all checks (`Gate::before`).
- Menus nest at most 2 levels (group → items). Reordering is drag-and-drop (@dnd-kit); moving an item to a different parent is a form field, not a drag target.
- Permissions follow a `{menu}.{view|create|edit|delete}` convention, generated automatically per menu item.
- No public registration route; accounts are admin-provisioned only.

## Brand Commitments

No brand yet — this ships as a starter kit, not a branded product. Light and dark mode (already present via an appearance toggle) must both stay fully supported; neither is more "default" than the other from a brand standpoint.

## Evidence on Hand

None. No logo, no existing testimonials or customer references, no name beyond "Laravel" from `config/app.name`. Nothing here should be invented as if real.

## Product Principles

1. **Boring is a feature.** This is infrastructure a hundred different products will sit on top of — the visual identity must not fight whatever brand gets layered on later.
2. **Density over whitespace.** Admins work through tables and lists for long stretches; every extra pixel of padding is pixels they scroll past all day.
3. **The database is the source of truth, visibly.** Menus, roles, and permissions are editable data, not config files — the UI should make that editability legible (inline actions, drag handles, visible structure).
4. **Both themes are first-class.** Dark mode is not an afterthought toggle; every surface is designed in both.

## Accessibility & Inclusion

No product-specific requirement beyond baseline: keyboard-operable drag-and-drop (dnd-kit's keyboard sensor), visible focus states, and sufficient contrast in both themes.
