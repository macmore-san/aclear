# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 13 + Inertia v3 + React 19, shadcn/ui components, Tailwind CSS v4, Spatie Laravel Permission, TCPDF. Built on the `laravel/react-starter-kit` access-control base (roles, permissions, DB-driven menus) — that layer is inherited, not reinvented; this product's own tables are `employees` and `attendance_punches`.

## Users

**AClear**, a small water refilling station. One primary user in practice: the **owner or manager**, on a laptop, in daylight (shop counter or a back office), working this system in short bursts twice a month at cut-off — not a dashboard they live in all day. Staff at the counter do not touch this system; it has no consumer-facing surface.

## Product Purpose

Turn a biometric time clock's CSV export into payroll-ready Daily Time Records with no fixed company schedule to configure: upload the export, the system pairs raw punches into shifts, computes worked/tardy/undertime/overtime per the rules below, and prints one PDF per employee for payroll. Employee records (position, department, and critically each employee's own `start_time`) are managed by hand since the clock only reports a code and a timestamp.

## Operating Context

- **Semi-monthly cut-offs**: the 1st–15th and 16th–end of month, matching Philippine small-business payroll practice. The owner opens the system at each cut-off to upload the period's export and print DTRs.
- **No fixed schedule.** Every employee just needs to complete a 10-hour shift, from whenever they clock in to whenever they clock out — no company-wide time in/out, no lunch deduction, no holiday calendar.
- **Overnight shifts are normal**, not an edge case: a shift can run past midnight and is still one row on the DTR, keyed to its Time In date.
- **The CSV export is the trigger for most other data**: uploading it is what introduces new employees (from the `Name` column) and is the only source of `attendance_punches`. `start_time` is never in the CSV — it is the one thing the owner enters by hand, and it is what tardy is computed against.
- **A cut-off with no upload yet, or an employee with no `start_time` set, are the two states this UI must surface plainly** — they are silent zeros otherwise (no tardy computed, nothing to print).

## Capabilities and Constraints

- No public registration; accounts stay admin-provisioned via the inherited starter-kit access control (Spatie roles/permissions, DB-driven menu, `Super Admin` bypass).
- CSV import never rejects a file over an ambiguous date format — it makes its best guess (`date_format_assumed`) and imports; the UI must be able to flag that guess, not hide it.
- A CSV re-upload is a no-op for already-imported punches (unique constraint on emp_code + punch_time) — safe to re-drop the same file.
- DTR PDF generation is synchronous per request; fine at this station's headcount.

## Brand Commitments

**AClear** — clean, straightforward, no-nonsense, the way a well-run neighborhood water station reads: real gauges and labels, not a generic SaaS look. Light-first (the owner works this on a laptop in daylight), but dark mode stays fully supported, not an afterthought.

## Evidence on Hand

No logo, no photos, no real address yet — `DTR_COMPANY_NAME`/`DTR_COMPANY_ADDRESS`/`DTR_COMPANY_LOGO` are environment-configured placeholders the owner fills in later. Nothing here should be invented as if real; a station address or headcount is never fabricated content.

## Product Principles

1. **The cut-off is the unit of work, not the day.** Every default (date range, "what's due") should orient around the current semi-monthly period, not today alone.
2. **Missing data is surfaced, never silently zeroed.** No `start_time` → tardy is 0 forever; an incomplete shift → 0 overtime. Both are correct behavior, but a screen that doesn't say so reads as a bug at payroll time.
3. **Print is the finish line.** Every other screen (upload, employee setup) exists to make the DTR PDF correct — the flow should visibly lead there.
4. **Both themes are first-class.** Dark mode is not an afterthought toggle; every surface is designed in both.

## Accessibility & Inclusion

Keyboard-operable everywhere (drag-and-drop file upload doubles as a keyboard-focusable browse control), visible focus states, sufficient contrast in both themes.
