# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

LAN Import WMS: a courier/freight-forwarding warehouse system. It is a **CodeIgniter 4 port** of an older plain-PHP "Courier" app: the domain layer (`src/`, namespace `Courier\`) came from it, and the interfaces layer (routing, controllers, filters, views) was rewritten on CI4. Comments like "igual que WebRoutes.php" or "del sistema original" refer to that old app, which is no longer used: **everything lives in this repo** (do not depend on the sibling `../lan` folder). All identifiers, views and messages are in Spanish; keep new code in Spanish too.

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

**Database schema:** plain SQL/PHP files in `database/migrations/NNN_descripcion.(sql|php)`, not CI migrations (`app/Database/Migrations` is empty). `php bin/migrate.php` (or `composer migrate`) applies the pending ones and records each in the `schema_migraciones` table; `--estado` lists pending, `--baseline` marks all as applied without running them (only for a database whose schema is already current). MySQL DDL is not transactional, so a failing migration stops the run. `php bin/seed.php` creates demo users (development only). Production is MariaDB 10.6.

## Configuration: two `.env` systems in one file

- CI4 reads the dotted keys (`app.baseURL`, `database.default.*`, `CI_ENVIRONMENT`, ...).
- The `Courier\` domain code reads the flat keys (`DB_*`, `JWT_*`, `MAIL_*`, `RATE_LIMIT_*`, `APP_TIMEZONE`, `LOG_*`) through `Courier\Shared\Infrastructure\Config` (phpdotenv). The PDO connection that all repositories use comes from `ConnectionFactory`, which uses `DB_*`, not CI's database config.
- Runtime settings edited from the UI (Configuracion > Correo/Empresa/Notificaciones) live in the `configuracion` table (one JSON row per group), read through `Courier\Configuracion\Domain\ConfiguracionRepositoryInterface`. SMTP falls back to `MAIL_*` from `.env` until it is first saved in the UI. The SMTP password is AES-GCM encrypted with a key derived from `JWT_SECRET`, so changing that secret means re-entering the password.

## Architecture

### Domain: `src/<Contexto>/{Domain,Application,Infrastructure}`

These are hexagonal/DDD bounded contexts (`Auth`, `Carga`, `Cliente`, `Lpn`, `Salida`, `Reempaque`, `Factura`, `Notificacion`, `Reporte`, `Aduanero`, `Proveedor`, `Pais`, `Naviera`, `Configuracion`, `Evidencia`, plus `Shared`).
- **Application** has one folder per use case, named `VerboSustantivo/`. Each holds a `*Command`/`*Query` DTO and a `*Handler` (`handle()` implementing `Shared/Application` interfaces), and sometimes a `*Result`.
- **Infrastructure/Persistence** holds `Pdo*Repository` classes: raw PDO with prepared statements, no ORM. CI's Model/Query Builder is not used.
- Writes that touch several repositories go through `UnitOfWork::run(callable)` (a PDO transaction). `RegistrarCargaHandler` is the canonical example.
- Domain errors are `Shared\Domain\Exception\{ValidationException, NotFoundException, DomainException}` plus context-specific subclasses. Controllers catch these.

### Wiring: `bootstrap/courier.php`

This is the single composition root. It builds a lazy-singleton `Courier\Shared\Infrastructure\Container` with every repository and handler binding. CI exposes it as `Services::courierContainer()` (`app/Config/Services.php`), and controllers reach it through `$this->container()->get(XHandler::class)` from `BaseController`. **A new use case must be registered in `bootstrap/courier.php`**, under its context's comment heading.

### Web layer (session-based)

- Routes are explicit in `app/Config/Routes.php`, with no auto-routing. Access control is declared per route through filters: `auth` (session required) and `role:a,b,...`. Filter classes are in `app/Filters`, and their aliases are in `app/Config/Filters.php`.
- Roles have internal values plus user-facing names (`Rol::label()`): `admin` = Owner, `gerente` = Gerencia, `operaciones` = Trafico, `bodega`, `aduanero`, `cliente`.
  - Owner is the only role with Configuracion (usuarios, paises, correo, empresa, notificaciones, etiqueta LPN).
  - Gerencia = everything else, plus Reportes and deleting LPNs.
  - Trafico = all operations, without Reportes or those two actions.
  - Bodega = only recepcion, ingreso, inventario, salidas and reempaque; it has no dashboard or rastreo, and `DashboardController` redirects it to the mobile app at `/bodega`.
  - Menu visibility in `partials/header.php` must match the route filters.
- Auth state uses **native PHP `$_SESSION`** through `Courier\Auth\Infrastructure\Security\SessionManager` (`usuario_id`, `usuario_rol`), not CI's session service. CSRF uses the custom `App\Libraries\Csrf`: forms post a hidden `_csrf` field, and controllers call `Csrf::verify($this->request->getPost('_csrf'))`. CI's global `csrf` filter is off.
- Views are plain PHP templates. They `require __DIR__ . '/../partials/header.php'` and `footer.php` (or `header_cliente.php` for the client portal), and use helpers from `partials/view_helpers.php` (`nav_item`, `badge`, `icon_button`, ...).
- List and search pattern used by the CRUD screens (paises, clientes, aduaneros, proveedores, ubicaciones, usuarios, cargas):
  - `index()` renders the full page.
  - `buscar()` returns JSON `{html, paginacion}` rendered from the `_tabla_filas.php` and `_paginacion.php` partials (`per_page` ∈ 5/10/25).
  - `app.js` binds the search input to that URL.
  - Follow `PaisController` when adding a new one.
  - Cargas (`/cargas/ingreso`) adds more:
    - filters by estado **and** aduanero (`initBusquedaPaginada({filtros: [{id, param}]})`);
    - columns Aduanero, Bultos, Peso and Valor (summed from each carga's lines);
    - "Etiquetas (LPN)": how many of the carga's LPNs are in each state (`LpnRepositoryInterface::conteoPorEstado()`, `badge_conteo_lpn()`), the same badges as Inventario;
    - a totals block (`carga/_resumen.php`, `resumenId`) for **all** filtered cargas, not just the page, from `searchPaginado()['resumen']`.

### API layer (`/api/v1`, JWT)

- Controllers live in `app/Controllers/Api` and extend `BaseApiController`. They return `success()`/`error()` envelopes and map exceptions to HTTP codes in `fromException()`: 422 validation, 404 not found, 409 invalid state transition, 401, 429.
- Filters: `jwt` (`JwtAuthFilter`) verifies the bearer token and puts the claims in `Services::apiAuthContext()`; `apirole:...` checks the role; `ratelimit:key[,max,window]` applies a rate limit.

### App movil de bodega (`/bodega`)

- Warehouse staff use PDAs/tablets with a PWA served by the same app at `/bodega` (`BodegaAppController`, view `bodega/app.php`, static files in `public/assets/bodega/`, no build step). It uses the normal web session, so there is no separate login, no JWT and no CORS.
- Its JSON endpoints are `bodega/api/*` in `Api\BodegaApiController` (recepcion, LPN lookup, salidas). They reuse the same use cases as the web screens `/recepcion` and `/salidas`. POSTs send the CSRF token in the `X-CSRF-Token` header, checked by the `csrfheader` filter.
- `AuthFilter` answers 401 JSON (instead of redirecting to `/login`) when the request sends `Accept: application/json`.
- The service worker is served from `/bodega/sw.js` with `Service-Worker-Allowed: /bodega`, and it only caches `/assets/bodega/*`. Bump `VERSION` in `public/assets/bodega/sw.js` when those files change.
- Scanned codes go through `ConfirmarRecepcionLpnHandler::normalizarCodigo()`, because keyboard-wedge scanners with a Spanish layout type `-` as `'`.

### Datos de entrega (formerly "actas de recepcion")

The paper delivery form (empresa de transporte, chofer, placa, entregado por, cedula, tipo de mercancia) is part of the carga itself: the `Carga\Domain\ValueObject\DatosEntrega` value object is stored in the `entrega_*` columns of `cargas` and filled in the "Datos de entrega" section of the Ingreso/Editar forms (`carga/_datos_entrega.php`). "Verificado por" is the user who registered the carga. `GET /cargas/{id}/comprobante` prints the signable comprobante. The former actas module, its table and the `recepcion` role were removed (migration 053).

### Numero de tracking

- Cargas use `LAN-AAAA-XXXXXX` (`Carga\Domain\ValueObject\TrackingNumero::PREFIJO`).
- The former prefix was `CRX`. Migration 060 (`060_tracking_crx_a_lan.php`) renamed every carga, keeping the rest of the number, and regenerated its barcode image.
- A CRX number from an old email or printed label still works: `TrackingNumero` and `TrackingNumero::normalizarBusqueda()` (used by the carga and inventory searches) translate it to LAN, and `'`/`?`/`_` scanner separators become `-`.
- LPN codes only use the 6-character suffix (`LPN-XXXXXX-N`), so they never changed.

### Estado de la carga sigue a los bultos

- A new carga stays `ingresado` (no longer jumps to `en_bodega` on registration).
- `Carga::sincronizarConBultos()` holds the rule:
  - `ingresado` → `en_bodega` when every LPN has been scanned in reception;
  - `en_bodega` → `en_transito` when every LPN left in confirmed salidas;
  - a partial shipment changes nothing.
- `Carga/Application/SincronizarEstadoCarga` counts bultos through `ConteoBultosCargaInterface` (`PdoConteoBultosCarga`), saves the state plus history, and sends the client the despacho email (`NotificarDespachoCargaHandler`, toggle in Configuracion > Notificaciones).
- It is called by `ConfirmarRecepcionLpnHandler` and `ConfirmarSalidaHandler`.
- Client emails share `Notificacion/Application/PlantillaCorreo`. Placeholder addresses (`sincorreo.N@importado.local`) are never sent to; the notification is recorded as failed with an explanation.
- WhatsApp without API: the carga detail ("Avisos al cliente") has "WhatsApp: ingreso" / "WhatsApp: despacho" buttons.
  - `POST /cargas/{id}/whatsapp/{ingreso|despacho}` → `PrepararAvisoWhatsappHandler` builds the text, records a `notificaciones` row with `canal = 'whatsapp'`, and redirects to `https://wa.me/<number>?text=...`; the user presses Send.
  - Phone numbers are normalized by `Notificacion\Domain\TelefonoWhatsapp` (809/829/849 → +1, 7-8 digits → +507).
  - Despacho data comes from `SalidaOrden::datosParaCliente()` of `SalidaOrdenRepositoryInterface::ultimaConfirmadaConCarga()`, the same data the despacho email uses.
- Reportes (gerente/admin) opens with "Operacion de bodega" (`PdoReporteOperativoRepository::operacionBodega()`):
  - period movement: ingresos, recepciones completas, salidas, bultos despachados;
  - current stock, bultos por escanear and cargas without LPNs;
  - inventory age, top clients, and receptions pending for more than 24 h.

### Aduanero asignado al cliente

`clientes.aduanero_identificador` (migration 056, FK to `aduaneros.identificador`) is the client's usual aduanero. It is edited in the client form, shown in the client list, and pre-selected in Ingreso de Carga when the client is picked (`alSeleccionar` in `app.js`), unless the user already chose another one.

### Salidas: embarque y manifiesto

- "Armar salida" (`GET /salidas`, view `salida/armar.php`): Trafico fills the container data and checks **cargas** (not LPNs).
  - The list comes from `Salida\Domain\CargasParaSalidaInterface` (`PdoCargasParaSalida`). It shows every carga en bodega, ready ones first; the rest are disabled with the reason (sin etiquetas / por escanear / en otra salida).
  - `POST /salidas` takes each checked carga's scanned, free LPNs. A partial carga ships what was scanned, and the rest stays as saldo.
  - **Dividing a carga between containers:** Trafico types "Bultos a subir" (`cantidad[cargaId]`, default all ready). `SalidaController::lpnsDeCargasMarcadas()` takes the first N ready LPNs by consecutive (`CargasParaSalidaInterface::lpnsListosPorCarga()`); the rest stays available for the next DO. "Agregar cargas" works the same way.
  - Bodega takes no decisions on salidas. It views, scans, finalizes and adds photos. Armar, agregar, cancelar and editing the embarque use the route filter `$planificaSalida` (operaciones/gerente/admin), and the buttons are hidden for bodega.
  - `CrearOrdenSalidaHandler` validates the container before creating anything, and stores it with the order.
- More cargas can be added to a **pending** DO (same container): `GET|POST /salidas/{id}/agregar` reuses `salida/armar.php` with `$ordenDestino` (no container form). `AgregarCargasSalidaHandler` reserves the new LPNs, and `SalidaOrden::agregarLineas()` holds the rules: pending only, no repeated LPN, and the DO becomes consolidated (`cliente_id` NULL) if a new client comes in. The repository upserts `salida_lineas`, so scans already done are kept.
- **Unir / sacar (one DO = one container):** on a pending DO, Trafico can unir it to another DO of the same container (`POST /salidas/{id}/unir`), or per carga "Sacar" it, either moving it to another pending DO or a new one (`mover-carga`) or returning it to bodega (`devolver-carga`).
  - `MoverCargasSalidaHandler` moves the lines **with their scan state** (`SalidaOrden::sacarLineas()` / `recibirLineas()`). An origin left empty is cancelled with `salida_ordenes.nota` ("Unida a DO.3 / 2026", migration 062).
  - A **confirmed** DO (finalized before the container was fully loaded) can receive only fully scanned lines. Those LPNs are consumed and their cargas synced, as in `ConfirmarSalidaHandler`.
  - `DevolverCargaSalidaHandler` releases the LPNs back to `available`.
  - The repository's `update()` deletes lines that are no longer in the order.
- A DO can mix clients (consolidated): `salida_ordenes.cliente_id` is NULL then (migration 057), and set only when all LPNs belong to one client.
  - Client names come from `SalidaOrdenRepositoryInterface::clientesPorOrden()`.
  - The scan screens (web and PDA) group by carga via `avancePorCarga()`.

- Each `SalidaOrden` gets a DO consecutive per year (`do_anio` + `do_numero`, shown as "DO.193 / 2026").
  - `PdoSalidaOrdenRepository::insert()` assigns it with `SELECT ... FOR UPDATE` inside the UnitOfWork.
  - The number restarts every year, and a unique key on (`do_anio`, `do_numero`) prevents duplicates.
- Shipment data (contenedor, sello, naviera from the `navieras` catalog, barco, fecha de cargue, ETD, destino) is the `Salida\Domain\ValueObject\DatosEmbarque` value object. It is editable until the order is cancelled.
- `GET /salidas/{id}/manifiesto` prints the manifest with one row per carga.
  - Kilos, m3 and valor come from `carga_lineas` and are prorated when only part of a carga's LPNs ship.
  - Line peso, valor **and `cubicaje_p3`** are line totals. Trafico types the line's total p3 from its sheet, for example 708.62 p3 for 131 bultos. Never multiply p3 by `cantidad`.
  - `LineaCarga::cubicajePies()` falls back to W×H×L×cantidad / 28316.85 when no p3 was typed. The form's auto-calc does the same.
- `GET /salidas/{id}/reporte` is the traffic team's "reporte de salida" sheet (`salida/reporte_imprimir.php`): FECHA (carga ingreso, m/d/Y), COMPAÑÍA, CLIENTE, MARCA, BULTOS, CUBICAJE P3, PESO, VALOR, ADUANERO, DESCRIPCION, with totals. It is built from the same `manifiesto()` rows (prorated), sorted by aduanero and fecha, and can be filtered with `?aduanero=`. `/reporte/excel` downloads the same rows as .xlsx (`EscritorXlsx`).

### Reempaque: trafico ordena, bodega ejecuta

- **Ordering (web):** Trafico (operaciones/gerente/admin) orders it at `GET /reempaques` (`reempaque/ordenar.php`).
  - Always one client. Trafico checks cargas (their scanned, free LPNs), picks a type (Consolidar / Dividir / Cambiar caja, `TipoReempaque::seleccionables()`), and writes an instruction (migration 058).
  - Origin LPNs go to `in_repack`.
- **Execution (PDA):** Bodega runs it in the PDA app (`#/reempaques`, `bodega/api/reempaques/{id}/{abrir-caja|empacar|quitar|cerrar-caja|descartar-caja|terminar}`).
  - The `ReempaqueOrden` aggregate holds the rules: one open box at a time; a bulto in only one box unless Dividir; finish only when everything is in closed boxes.
  - Boxes are new LPNs `LPN-R<orden>-N`, `in_repack` until the order finishes. Each box's contents live in `lpn_genealogia`, and `reempaque_lpns.cerrada_at` marks it closed.
  - Closing a box generates its label (marca, "CONTIENE N BULTOS", "REEMPAQUE"), printed from the web order page. Finishing consumes the origins and makes the boxes `available`.
- **Boxes in salidas:** a box has no `carga_id`. Contents are resolved recursively through the genealogy:
  - `LpnRepositoryInterface::bultosContenidos()` / `cargasContenidas()`;
  - `PdoCargasParaSalida::cajasListas()` (rows in Armar salida);
  - the manifest expands boxes to their original bultos per carga;
  - `PdoConteoBultosCarga` counts a bulto as despachado when its final box shipped;
  - `ConfirmarSalidaHandler` syncs the cargas inside the boxes.

### Fotos de evidencia (recepcion y salida)

- Photos are **per client carga** (all its bultos/pallets from several angles), never per barcode/LPN.
- Table `fotos_evidencia` (migration 059): `tipo` = `recepcion` (`carga_id`) or `salida` (`salida_orden_id` **and** `carga_id`: that carga when loaded into that DO). Files live in `public/uploads/evidencias/AAAA/MM/<uuid>.<ext>` (`Evidencia\Infrastructure\Storage\FotoEvidenciaStorage`).
- One API for PDA and web (`Api\FotoEvidenciaApiController`, multipart field `foto`, CSRF in `X-CSRF-Token`):
  - `GET|POST bodega/api/cargas/{cargaId}/fotos`: recepcion photos.
  - `GET|POST bodega/api/salidas/{ordenId}/fotos?carga={cargaId}`: that carga's photos in that salida. The carga is required and must be in the DO. Reempaque boxes (`caja-*` groups) get no photos.
- `SubirFotoEvidenciaHandler` accepts only real images (getimagesize: JPG/PNG/WEBP, max 10 MB).
- `public/assets/js/fotos-evidencia.js` shrinks each photo on the device (longest side 1600 px, JPEG 0.8) before uploading; both the PDA and the web gallery use it.
- PDA: a "📷 n" button on each carga in Recepcion (`#/fotos/cargas/{cargaId}/{titulo}`) and on each carga of a salida (`#/fotos/salidas/{ordenId}/{cargaId}/{titulo}`), with Tomar foto / Galeria.
- Web: the partial `evidencia/_fotos.php` shows groups.
  - Carga detail: Recepcion, plus one group per salida the carga is in (`SalidaOrdenRepositoryInterface::ordenesConCarga()`).
  - Salida page: one group per carga.
  - Upload: operaciones/bodega/gerente/admin. Delete (`POST fotos-evidencia/{id}/eliminar`, returns to the page it came from): gerente/admin only.

### Carga masiva (Excel/CSV)

- `/cargas/masiva` is restricted to gerente and admin.
- Flow: upload, then preview, then confirm. The validated plan lives in `$_SESSION['carga_masiva']` between preview and confirm.
- `Carga/Application/ImportarCargas/PrepararImportacionCargas` validates and groups the rows by `referencia`, without writing anything.
- `ImportarCargasHandler` registers each carga through `RegistrarCargaHandler`, one by one, so a failure does not stop the rest. It auto-creates missing proveedores (`PROV-####`), and can skip the client email (`RegistrarCargaCommand::$notificarCliente`).
- `.xlsx` files are read and written with ZipArchive and XML (`Carga/Infrastructure/Importacion`); there is no PhpSpreadsheet dependency.

### Label printing

Barcode and label images are generated server-side into `public/` (`PicqerBarcodeGeneratorService`, `LpnBarcodeGeneratorService`).

- The LPN label's serie (bulto X/Y) depends only on the template (Configuracion > Etiqueta LPN, `lpn_etiqueta_config.mostrar_serie`), not on the client's country. `paises.mostrar_serie_etiqueta` is no longer used. Migration 061 regenerated the labels that had come out without it.
- Images keep their file name when regenerated, so views link them with `url_archivo_publico()` (adds `?v=<mtime>`) to avoid stale caches. Uploaded documents are also stored under `public/uploads`. Physical printing goes through a separate local "Agente de Impresion LAN" at `http://localhost:9898`, which `public/assets/js/local-print.js` calls. Its source (PowerShell, no dependencies) is in `herramientas/agente-impresion/`; users download it from the Impresoras page as `public/descargas/AgenteImpresionLAN.zip` and install it with `Instalar.bat`. After changing the agent, rebuild the ZIP with `powershell -ExecutionPolicy Bypass -File herramientas/empaquetar-agente.ps1`.

### Cross-context rules (from the original project)

- Put state-transition invariants on enums/entities (e.g. `CargaStatus::assertTransitionTo`), not in handlers.
- Read config through `Config::get/int/bool`, never `$_ENV` directly.
- `Lpn`, `Salida` and `Reempaque` sit downstream of `Carga`. Use repository interfaces instead of reaching into another context's Infrastructure.
