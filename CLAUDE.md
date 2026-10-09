# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

LAN Import WMS: a courier/freight-forwarding warehouse system. This repo is a **CodeIgniter 4 port** of the original plain-PHP "Courier" app in the sibling folder `../lan`. The domain layer (`src/`, namespace `Courier\`) was copied over unchanged; only the interfaces layer (routing, controllers, filters, views) was rewritten on CI4. Many comments say "igual que WebRoutes.php" or "equivalente a ... del sistema original". Those point to the `../lan/interfaces/` code this port must match. All identifiers, views and messages are in Spanish; keep new code in Spanish too.

## Commands

```bash
composer install
composer dump-autoload -o          # required after adding any new class (optimize-autoloader is on)
composer test                      # = vendor/bin/phpunit (config: phpunit.dist.xml, suite in tests/)
vendor/bin/phpunit tests/unit/HealthTest.php
vendor/bin/phpunit --filter testMethodName
php spark routes                   # list registered routes with their filters
```

The app is served by Laragon with DocumentRoot at `public/`. There is no JS/CSS build step. Views use Tailwind classes directly, and front-end behavior lives in `public/assets/js/app.js`.

**Database schema is not managed here.** `app/Database/Migrations` is empty. The schema and seeders come from `../lan/database/migrations` (`php ../lan/bin/migrate.php`, `php ../lan/bin/seed.php`).

## Configuration: two `.env` systems in one file

- CI4 reads the dotted keys (`app.baseURL`, `database.default.*`, `CI_ENVIRONMENT`, ...).
- The `Courier\` domain code reads the flat keys (`DB_*`, `JWT_*`, `MAIL_*`, `RATE_LIMIT_*`, `APP_TIMEZONE`, `LOG_*`) through `Courier\Shared\Infrastructure\Config` (phpdotenv). The PDO connection that all repositories use comes from `ConnectionFactory`, which uses `DB_*`, not CI's database config.
- Runtime settings edited from the UI (Configuracion > Correo/Empresa/Notificaciones) live in the `configuracion` table (one JSON row per group), read through `Courier\Configuracion\Domain\ConfiguracionRepositoryInterface`. SMTP falls back to `MAIL_*` from `.env` until it is first saved in the UI. The SMTP password is AES-GCM encrypted with a key derived from `JWT_SECRET`, so changing that secret means re-entering the password.

## Architecture

### Domain: `src/<Contexto>/{Domain,Application,Infrastructure}`

These are hexagonal/DDD bounded contexts (`Auth`, `Carga`, `Cliente`, `Lpn`, `Salida`, `Reempaque`, `Factura`, `Notificacion`, `Reporte`, `Aduanero`, `Proveedor`, `Pais`, plus `Shared`).
- **Application** has one folder per use case, named `VerboSustantivo/`. Each holds a `*Command`/`*Query` DTO and a `*Handler` (`handle()` implementing `Shared/Application` interfaces), and sometimes a `*Result`.
- **Infrastructure/Persistence** holds `Pdo*Repository` classes: raw PDO with prepared statements, no ORM. CI's Model/Query Builder is not used.
- Writes that touch several repositories go through `UnitOfWork::run(callable)` (a PDO transaction). `RegistrarCargaHandler` is the canonical example.
- Domain errors are `Shared\Domain\Exception\{ValidationException, NotFoundException, DomainException}` plus context-specific subclasses. Controllers catch these.

### Wiring: `bootstrap/courier.php`

This is the single composition root, a copy of `../lan/bootstrap/app.php`. It builds a lazy-singleton `Courier\Shared\Infrastructure\Container` with every repository and handler binding. CI exposes it as `Services::courierContainer()` (`app/Config/Services.php`), and controllers reach it through `$this->container()->get(XHandler::class)` from `BaseController`. **A new use case must be registered in `bootstrap/courier.php`**, under its context's comment heading.

### Web layer (session-based)

- Routes are explicit in `app/Config/Routes.php`, with no auto-routing. Access control is declared per route through filters: `auth` (session required) and `role:a,b,...`. The role groups are `gerente,admin` (management), `operaciones,gerente,admin`, and the "bodega" group `operaciones,bodega,gerente,admin`. Filter classes are in `app/Filters`, and their aliases are in `app/Config/Filters.php`.
- Auth state uses **native PHP `$_SESSION`** through `Courier\Auth\Infrastructure\Security\SessionManager` (`usuario_id`, `usuario_rol`), not CI's session service. CSRF uses the custom `App\Libraries\Csrf`: forms post a hidden `_csrf` field, and controllers call `Csrf::verify($this->request->getPost('_csrf'))`. CI's global `csrf` filter is off.
- Views are plain PHP templates. They `require __DIR__ . '/../partials/header.php'` and `footer.php` (or `header_cliente.php` for the client portal), and use helpers from `partials/view_helpers.php` (`nav_item`, `badge`, `icon_button`, ...).
- List and search pattern used by the CRUD screens (paises, clientes, aduaneros, proveedores, ubicaciones, usuarios, cargas):
  - `index()` renders the full page.
  - `buscar()` returns JSON `{html, paginacion}` rendered from the `_tabla_filas.php` and `_paginacion.php` partials (`per_page` ∈ 5/10/25).
  - `app.js` binds the search input to that URL.
  - Follow `PaisController` when adding a new one.

### API layer (`/api/v1`, JWT)

- Controllers live in `app/Controllers/Api` and extend `BaseApiController`. They return `success()`/`error()` envelopes and map exceptions to HTTP codes in `fromException()`: 422 validation, 404 not found, 409 invalid state transition, 401, 429.
- Filters: `jwt` (`JwtAuthFilter`) verifies the bearer token and puts the claims in `Services::apiAuthContext()`; `apirole:...` checks the role; `ratelimit:key[,max,window]` applies a rate limit.

### Label printing

Barcode and label images are generated server-side into `public/` (`PicqerBarcodeGeneratorService`, `LpnBarcodeGeneratorService`). Uploaded documents are also stored under `public/uploads`. Physical printing goes through a separate local "Agente de Impresion LAN" at `http://localhost:9898`, which `public/assets/js/local-print.js` calls (source in `../lan/herramientas/`).

### Cross-context rules (from the original project)

- Put state-transition invariants on enums/entities (e.g. `CargaStatus::assertTransitionTo`), not in handlers.
- Read config through `Config::get/int/bool`, never `$_ENV` directly.
- `Lpn`, `Salida` and `Reempaque` sit downstream of `Carga`. Use repository interfaces instead of reaching into another context's Infrastructure.
