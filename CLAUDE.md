# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Protocolo de sesión

### Al iniciar — leer siempre

| Archivo | Qué buscar |
|---|---|
| `BITACORA.md` | Última sesión: qué se hizo, qué quedó pendiente |
| `docs/tecnico/BACKLOG.md` | Sprint activo: ítems priorizados por ID |

No trabajar en ítems que no estén en el backlog activo.

### Al iniciar — leer según contexto

| Archivo | Cuándo |
|---|---|
| `docs/tecnico/MODELO_BD.md` | Antes de crear migraciones, tocar modelos o diseñar cualquier feature |
| `docs/tecnico/NOTAS_TECNICAS.md` | Antes de tocar image upload, Alpine.js o CSP |
| `docs/tecnico/image-upload-system.md` | Antes de tocar `x-image-upload` |
| `docs/tecnico/ESTRATEGIA_DESARROLLO.md` | Antes de hacer deploy o cambiar dependencias |
| `docs/OPI_PRODUCCION.md` | Antes de trabajar en producción (OPi) |

### Al cerrar — actualizar siempre

| Archivo | Qué registrar |
|---|---|
| `BITACORA.md` | Resumen de la sesión: logros, archivos tocados, pendientes |
| `docs/tecnico/BACKLOG.md` | Mover ítems completados, agregar nuevos si corresponde |

### Al cerrar — actualizar si aplica

| Archivo | Cuándo |
|---|---|
| `docs/tecnico/MODELO_BD.md` | Si se creó o modificó una tabla o columna |
| `docs/architecture/IDEAS_FUTURO.md` | Si surgió una idea que no entra en el sprint actual |
| `docs/tecnico/NOTAS_TECNICAS.md` | Si se resolvió un bug con causa raíz no obvia |

## Documentación técnica de referencia

| Archivo | Propósito |
|---|---|
| `docs/tecnico/MODELO_BD.md` | Inventario completo de tablas y campos — **actualizar al crear/modificar tablas** |
| `docs/tecnico/NOTAS_TECNICAS.md` | Bugs, causas raíz y soluciones (NT-XXX) |
| `docs/tecnico/ESTRATEGIA_DESARROLLO.md` | Workflow de sesión, convenciones de commit, checklist de deploy, reglas de dependencias |
| `docs/tecnico/image-upload-system.md` | Referencia completa del componente `x-image-upload` — props, patrones, ImageManagers |
| `docs/OPI_PRODUCCION.md` | Guía de operación del servidor de producción (Orange Pi 5 Plus) |
| `docs/architecture/IDEAS_FUTURO.md` | Ideas y funcionalidades para sprints futuros |

**Reglas críticas:**
- `cropperjs` está fijado en `1.6.2` (exacto). v2 tiene API incompatible. NO actualizar sin leer NT-001.
- Siempre borrar vistas compiladas Blade después de `git pull` en producción (ver NT-005 y checklist de deploy).
- Alpine.js requiere `unsafe-eval` en CSP (ver NT-006).

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

Tests use a MySQL database named `testing` (`DB_DATABASE=testing`, same `DB_CONNECTION=mysql` as production) — **not SQLite** despite what this doc said until 16/07/2026. Confirmed real incompatibility: migration `2026_03_20_000003_cleanup_phones_table` (`DROP COLUMN` on a polymorphic column) fails on SQLite because Laravel emulates `DROP COLUMN` by recreating the table, which trips on a related index — MySQL supports native `DROP COLUMN` without this issue. Don't attempt to run this suite against SQLite.

## Architecture

### Stack
- **Backend:** Laravel 13 / PHP 8.5 (imagen `estetican/app:prod`; se documentaba como "8.3+" hasta el 16/07/2026 — quedó desactualizado desde que se actualizó la imagen sin tocar este archivo, ver NT-005 en Zeus-Estetican)
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

React 19 + Vite + Tailwind app connected to the Laravel API via Bearer token (Laravel Sanctum custom tokens in `api_tokens` table). Auth, pets, clients, agenda, bookings, checkin and payments are all wired. Uses Node.js v20 native in WSL (via `nvm`).

```bash
# From mob_apps/operador/ — dev server (exposes on LAN port 3000)
nohup npm run dev > /tmp/mobile-dev.log 2>&1 &
```

API controllers live in `app/Http/Controllers/Api/`. Routes in `routes/api.php`.

## Important Patterns

- **Routes** are all in `routes/web.php`. Admin-only routes are wrapped in `middleware('role:admin|super-admin')`.
- **Financial flows:** Payments always require selecting `destination` (caja/banco). Never bypass this when implementing payment features.
- **Advance/guarantee rules** are configured per service in `services.requires_advance` + `services.advance_percentage`, not hardcoded.
- **`ResourceAllocation`** is polymorphic (`source` morph) — used by both `SpaBooking` and `HotelReservation`.
- **Togglable business modules** (Clínica, Tienda, Hotel — everything except the core Spa/Estética flow) follow one repeated pattern: a `SystemSettings` boolean named `{code}_module_enabled` in its own section, an `Ensure{Name}ModuleEnabled` middleware (alias `{code}.module` in `bootstrap/app.php`) wrapping that module's routes, and navigation classes checking the same flag before rendering their items. See `EnsureClinicalModuleEnabled`/`EnsureStoreModuleEnabled`/`EnsureHotelModuleEnabled` for the three real implementations. This convention is also the contract Zeus-Estetican's portal relies on to push module state to a tenant's `SystemSettings` — a new module's toggle key must follow it.
