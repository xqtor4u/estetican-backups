# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Session Protocol

**Before writing code:** Read `BITACORA.md` (root) and identify the current sprint. Do not work on items not listed there.

**After changes:** Update `BITACORA.md` with touched files and pending items. Move "good ideas" to `docs/architecture/IDEAS_FUTURO.md`, not a new file.

## Technical Documentation

| File | Purpose |
|---|---|
| `docs/tecnico/NOTAS_TECNICAS.md` | Bugs, root causes and solutions (NT-XXX). Read before touching image upload, Alpine, or CSP. |
| `docs/tecnico/ESTRATEGIA_DESARROLLO.md` | Dev workflow, commit conventions, deploy checklist, dependency rules. |
| `docs/tecnico/image-upload-system.md` | Full reference for the `x-image-upload` component — props, patterns, backend managers. |

**Critical rules from notes:**
- `cropperjs` is pinned to `1.6.2` (exact). v2 has incompatible API. Do NOT upgrade without reading NT-001.
- Always clear compiled Blade views after `git pull` in production (see NT-005 and deploy checklist).
- Alpine.js requires `unsafe-eval` in CSP (see NT-006).

## Development Environment

This project runs on **WSL 2 + Docker (Laravel Sail)**. All commands assume you are inside WSL.

```bash
# Start environment (from repo root)
./levantar_backoffice.sh

# Stop environment + auto-backup DB
./apagar_backoffice.sh

# Manual Sail commands (from apps/backoffice-laravel/)
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan tinker
./vendor/bin/sail artisan route:list
```

The startup script handles: Docker health check, migrations, `storage:link`, cache rebuild, and Vite asset compilation.

**App URL:** `http://localhost:8000` (port defined by `APP_PORT` in `.env`)

### Frontend Assets

```bash
# From apps/backoffice-laravel/ — hot reload (set BACKOFFICE_USE_VITE_HOT=true in env first)
./vendor/bin/sail npm run dev

# Production build
./vendor/bin/sail npm run build
```

Assets compile via Vite. If npm is resolved to Windows binaries from WSL, compilation runs inside the Docker container automatically.

## Testing & Linting

```bash
# From apps/backoffice-laravel/
./vendor/bin/sail artisan test                    # all tests
./vendor/bin/sail artisan test --filter TestName  # single test
./vendor/bin/sail php vendor/bin/phpunit          # direct PHPUnit

# Code style (Laravel Pint)
./vendor/bin/sail php vendor/bin/pint
./vendor/bin/sail php vendor/bin/pint --test      # dry run
```

Tests use an in-memory SQLite database (`DB_DATABASE=testing`).

## Architecture

### Stack
- **Backend:** Laravel 13 / PHP 8.3+
- **Database:** MySQL 8.4 (Docker volume)
- **Frontend:** Blade + Alpine.js 3.x + Bootstrap 5.3 + Tailwind CSS 4
- **Assets:** Vite via `laravel-vite-plugin`
- **Auth/Permissions:** `spatie/laravel-permission` (roles: `admin`, `super-admin`, plus operator roles)
- **Media:** `spatie/laravel-medialibrary`

### Domain Layer (`app/Domain/`)

Business logic is organized into domain modules, each with `Contracts/`, `Repositories/`, and `Services/`:

| Domain | Responsibility |
|---|---|
| `Planning` | `BookingService` — SPA appointment lifecycle |
| `Execution` | `ExecutedServiceService` — recording completed services |
| `Commercial` | `QuoteService` — multi-version quotes → work orders |
| `Resources` | `ResourceAllocationService` — cage/room scheduling, collision prevention |
| `Catalog` | Service catalog, navigation caching |
| `Core` | Shared core entities |

Interfaces are bound to implementations in `AppServiceProvider`. Always inject interfaces, not concrete classes.

### Key Models

- **`SpaBooking`** — appointment with states: `scheduled` → `work_order` → `completed` (also `cancelled`, `no_show`).
- **`HotelReservation`** — date-range stay with cage allocation via `ResourceAllocation`.
- **`Quote` / `QuoteItem`** — multi-version budget per booking; accepting one freezes services into a work order.
- **`Payment`** — records advances and settlements. `destination` field: `caja` (cash) or `banco` (bank/card).
- **`CashLedger` / `BankLedger`** — separated accounting tables for audits.
- **`Pet`** — `profile_photo_path` column holds identity photo. `PetPhoto` records hold the chronological gallery (categories: `ingreso`, `incidencia`, `resultado`, `perfil`). Marking a gallery photo as `perfil` updates `pets.profile_photo_path`.
- **`SystemSetting`** — all business configuration (branding, SMTP, fiscal data, guarantee rules) lives here; no config in code.

### Support Layer (`app/Support/`)

- `*ImageManager` classes — handle upload, crop, and storage for each entity (Pet, Operator, Resource).
- `SystemSettings/` — typed accessors for system settings.
- `Pages/` — page-builder helpers.
- `Navigation/` — cached nav menu resolution.

### Views & Components

Blade views are organized per module under `resources/views/`. Reusable UI components live in `resources/views/components/`:

- **`x-image-upload`** — Alpine.js component for file selection, interactive crop (circular for profiles, rectangular for galleries), and optional auto-submit. Requires explicit `autoSubmitFormId` to trigger auto-save; omit it on complex forms to prevent accidental submissions.
- `x-list-table`, `x-list-filters`, `x-page-header`, `x-main-navigation`, etc.

The **Agenda detail view** (`agenda/show.blade.php`) is context-driven: it renders different partials (`_quote_manager`, `_work_order`, `_billing_summary`) based on the booking's current state.

### Mobile App (`mob_apps/operador/`)

Separate React 19 + Vite + Tailwind app (not yet connected to Laravel API). Uses Node.js v20 native in WSL (via `nvm`) to avoid Windows path conflicts.

```bash
# From mob_apps/operador/
npm run dev
```

Requires `GEMINI_API_KEY` in `.env.local`.

## Important Patterns

- **Routes** are all in `routes/web.php`. Admin-only routes are wrapped in `middleware('role:admin|super-admin')`.
- **Financial flows:** Payments always require selecting `destination` (caja/banco). Never bypass this when implementing payment features.
- **Advance/guarantee rules** are configured per service in `services.requires_advance` + `services.advance_percentage`, not hardcoded.
- **`ResourceAllocation`** is polymorphic (`source` morph) — used by both `SpaBooking` and `HotelReservation`.
