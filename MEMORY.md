# MEMORY.md — Estado del proyecto

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A) — INACAP
**Última actualización:** 2026-10-30

## Estado actual

- **Constitución:** `docs/constitution.md` v1.0, vigente (6 principios).
- **Spec 001 POS:** `specs/001-pos/spec.md` v1.0, **aprobada**, 40 RF y 13 RNF, sin dudas abiertas.
- **Plan 001 POS:** `specs/001-pos/plan.md`, completo (arquitectura, funciones puras, pseudocódigo, UI, decisiones, tests).
- **Tareas 001 POS:** `specs/001-pos/tasks.md`, 10 tareas. **T1 a T10 cerradas: módulo 001-pos completo**.
- **Código:** Laravel 11.57.1. Dominio `app/Pos/` completo. Persistencia: `Product`, `Sale`, `SaleLine`, `Document`, `StockMovement`, `AuditLog`, `Convention`. Servicios: `CartService`, `CheckoutService`, `VoidSaleService`, `AuditService`, `ConventionService`, `DocumentService`. HTTP: `PosController` y `Admin\{ConventionController, SaleController, DocumentController, AuditController}`. Vistas: `pos/index`, `pos/checkout`, `pos/receipt`, `admin/*` (layout `layouts.admin`). 151 tests en verde.
- **Comandos SDD:** instalados en `.opencode/commands/` (9 comandos).

## Entorno (2026-10-30)

- PHP 8.2.12 (XAMPP) con `pdo_pgsql`, `pgsql` y `zip` habilitados en `C:\xampp\php\php.ini` (respaldo en `php.ini.bak-sdd`).
- PostgreSQL **18** local en `127.0.0.1:5432`, autenticación `trust`, bases `farmacia_pos` y `farmacia_pos_test`.
- **Docker no instalado**: desviación aprobada del plan, se usa PG 18 local en lugar de PG 16 en contenedor.
- `composer` instaló con `--no-security-blocking` porque Laravel 11 arrastra avisos de seguridad; revisar antes de producción.
- Zona horaria `America/Santiago`, `SESSION_DRIVER=database` (falta migración de sesiones para RF-6).

## Decisiones vigentes

- **D-1:** documentos tributarios **simulados** (folio interno, estados pendiente/emitido/rechazado). Sin SII ni servicios externos.
- **D-2:** tope general de descuento **10%** por transacción; convenios de salud con porcentaje propio definido por el administrador.
- **D-3:** cierre de caja y arqueo quedan en `specs/004-reportes/`, fuera del POS.
- **Roles POS:** Cajero = Técnico, Supervisor = Químico, Administrador = Admin; Bodeguero sin acceso al POS.
- **IVA:** 19% incluido en el precio de lista; el desglose se calcula con `net = round(total * 100 / 119)` y `iva = total - net`.
- **Redondeo:** siempre a peso entero con round half-up (`roundToPeso`), una sola vez por cálculo; el dominio nunca almacena flotantes como dinero.
- **Descuentos:** se aplican primero por línea y luego el global sobre el subtotal ya descontado; el descuento nunca supera su base y el total se acota a 0.
- **Tope 10%:** vigente en `config/pos.php` (`discount.general_cap_percent`). Superarlo devuelve `requires_supervisor`; no se aplica hasta que un Químico autorice.
- **Convenios (T3):** el porcentaje lo define el administrador y no consume el tope del cajero; convenio inactivo o fuera de vigencia se rechaza con `convention_not_applicable`. El porcentaje del convenio prevalece sobre cualquier descuento manual.
- **Rechazos de descuento (T3):** códigos `reason_required`, `total_not_positive`, `convention_not_applicable`, `above_cap`. Ningún descuento deja el total en cero o negativo.
- **Recetas (T4):** vigencia 30 días calendario inclusive (configurable en `config/pos.php`), comparada por día; fecha futura también inválida. Datos obligatorios: número, nombre y RUT del prescriptor y fecha de emisión.
- **Receta reutilizada (T4):** no bloquea la venta, pero devuelve `requires_supervisor` con código `prescription_already_used`.
- **Controlados (T4):** exigen registro en el libro (`requiresControlledRegister`); la categoría más restrictiva del carrito manda sobre toda la venta.
- **Stock (T5):** stock negativo o producto desconocido se tratan como 0; `canSell` exige cantidad ≥ 1 y existencia ≥ cantidad. El faltante es `requested - available` y el carrito se bloquea completo si hay al menos uno.
- **Cambio de stock (T5):** `changedSinceSnapshot` compara la existencia viva contra la congelada en el carrito; la transacción de cobro bloquea la fila real con `lockForUpdate()` (T8).
- **Carrito en sesión (T6):** se guarda sólo `id de producto => cantidad`; precio, categoría y stock se releen de la base en cada request. La clave es `pos.cart`.
- **Búsqueda (T6):** `lower(...) like` sobre nombre, principio activo y código de barras, límite 20 resultados, sólo productos vendibles.
- **Tailwind (T6):** `tailwindcss@3` + PostCSS + Vite. `npm run build` genera `public/build`; la vista usa `@vite` y cae a CDN sólo si falta el manifest.
- **Sesiones:** Laravel 11 crea la tabla `sessions` en la migración de `users`; no se agrega una migración propia.
- **Cobro (T7):** `CheckoutService` hace todo dentro de `DB::transaction`: descuenta stock con `where('stock','>=',qty)`, crea venta, líneas congeladas, documento y movimiento negativo. Si algo falla, rollback y el carrito queda intacto.
- **Bloqueo pesimista (T8):** antes de validar y descontar, `CheckoutService::lockProducts()` ejecuta `SELECT ... FOR UPDATE` (`lockForUpdate()`) sobre los productos del carrito ordenados por `id`. El descuento condicional `where('stock','>=',qty)` queda como segunda barrera contra la sobreventa (RF-27, RF-30).
- **Aislamiento (T8):** PostgreSQL en su nivel por defecto **READ COMMITTED**; no se sube a `SERIALIZABLE` porque el bloqueo de fila ya serializa la última unidad y evita reintentos masivos por conflictos de serialización.
- **Deadlocks (T8):** el orden estable por `id` (cobro y anulación usan el mismo) evita bloqueos cruzados; `DB::transaction($cb, 3)` reintenta hasta 3 veces si PostgreSQL reporta deadlock (SQLSTATE 40P01).
- **Anulación (T9):** `VoidSaleService::void()` es atómica: bloquea la venta con `lockForUpdate`, valida con `VoidPolicy` (motivo obligatorio, sin doble anulación → `DomainException`), repone stock con **un movimiento `void` por línea**, registra auditoría (`sale.void`: autor, motivo, timestamp en `America/Santiago`) y genera la nota de crédito simulada si el documento estaba emitido. La venta original no se borra ni se edita; sólo su estado pasa a `voided`.
- **Nota de crédito (T9):** rango de folios propio (`credit_note_from` 100001–150000); `reversal_of_id` apunta al documento original. Si el documento estaba `pending`, no se crea nota.
- **Permisos (T10):** columna `users.role` (`tecnico` por defecto, `quimico`, `admin`). El middleware `role:...` (`App\Http\Middleware\RoleMiddleware`, alias `role`) devuelve 403 si el rol no está en la lista. Anulación (`pos.sales.void`) exige `auth` + `role:quimico,admin`; historial y documentos son sólo Admin; convenios y auditoría se leen con Químico o Admin; crear/editar/desactivar convenios es sólo Admin. Técnico queda restringido al POS.
- **Convenios (T10):** tabla `conventions` (nombre único, `percent` decimal 5,2, `valid_from`/`valid_until` por día, `active`) + `Convention` y `ConventionFactory`. `ConventionService::findApplicable()` valida al confirmar el cobro (no al armar el carrito) y lanza `DomainException` si no existe, está inactivo o fuera de vigencia. La baja es lógica (`active = false`) para no romper el historial.
- **Convenio en la venta (T10):** `sales.convention_id` (FK `nullOnDelete`), `convention_name` y `convention_percent` congelados; el ahorro queda en `global_discount`. El porcentaje se aplica sobre el total y no consume el tope del 10%; si deja el total en cero o negativo se rechaza. La caja muestra sólo convenios aplicables hoy y el comprobante registra convenio, porcentaje y ahorro.
- **Historial (T10):** `admin.sales.index` filtra por `from`/`to` (por día de `sold_at`), `payment_method`, `status`, `user_id`, `convention_id` y `document_status`; paginación de 20 con query string. `admin.sales.show` muestra líneas, descuentos, convenio, documento, nota de crédito, anulación y auditoría (RF-37, RF-38).
- **Reintento de documentos (T10):** `DocumentService::retry()` sólo acepta `pending` o `rejected`; un documento con folio lo conserva, uno sin folio recibe el siguiente del rango de su tipo. Actualiza estado a `issued` y `issued_at` dentro de transacción; el índice único `(type, folio)` impide duplicados. `admin.documents.index` y la lista de ventas ofrecen el botón «Reintentar».
- **Auditoría (T10):** `admin.audit.index` lista `audit_logs` con autor, acción, venta y motivo; visible para Químico y Admin (403 para Técnico).
- **Consistencia (T8):** el stock final siempre se puede reconstruir como `stock inicial + suma(stock_movements)`; el test de concurrencia lo verifica tras ventas y anulaciones.
- **Idempotencia (T7):** `sales.token` es UUID único tomado de la sesión (`pos.checkout_token`); un doble envío devuelve la venta existente en vez de crear otra.
- **Folio (T7):** simulado y por tipo (`receipt` 1–50.000, `invoice` 50.001–100.000, `credit_note` 100.001–150.000); siguiente = máximo + 1 con índice único `(type, folio)`. Si el rango se agota, el documento queda `pending` sin folio.
- **Comprobante (T7):** siempre muestra "Documento simulado — sin validez tributaria" y el emisor ficticio de `config/pos.php`.
- **Pendiente de integración:** el cobro aún no exige receta (RF-8) aunque `PrescriptionPolicy` existe; los convenios ya se aplican desde la caja (T10).
- **Supuestos S-1 a S-6:** emisor ficticio, factura con RUT/razón social/giro/dirección/comuna, receta válida 30 días, libro de controlados como registro + CSV, nota de crédito simulada, RUT de cliente opcional.

## Pendientes conocidos

- La skill `sdd` referenciada por los comandos **no existe**; los comandos la invocan igual.
- `specs/004-reportes/` (cierre de caja) no ha sido redactada.
- El cobro todavía no exige receta (RF-8): la política existe pero no está conectada al `PosController`.

## Próximo paso

Módulo `001-pos` completo (T1–T10). Siguiente: redactar la spec del módulo siguiente (p. ej. `specs/002-inventario/` o `specs/004-reportes/`) antes de escribir código.
