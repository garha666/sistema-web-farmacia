# MEMORY.md — Estado del proyecto

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A) — INACAP
**Última actualización:** 2026-11-18

## Estado actual

- **Constitución:** `docs/constitution.md` v1.0, vigente (6 principios).
- **Spec 001 POS:** `specs/001-pos/spec.md` v1.1, **aprobada**, 40 RF y 13 RNF, sin dudas abiertas. Las cinco enmiendas de v1.1 (mapeo de roles, RF-20 y RF-22 con la caja abierta como precondición, §7 y D-3 apuntando a la spec 002) están aplicadas.
- **Plan 001 POS:** `specs/001-pos/plan.md`, completo (arquitectura, funciones puras, pseudocódigo, UI, decisiones, tests).
- **Tareas 001 POS:** `specs/001-pos/tasks.md`, 10 tareas + correcciones Vuelta 1 (S1–S5) + Vuelta 2 (S6–S14). **T1 a T10 cerradas; S1 a S14 cerradas.**
- **Código:** Laravel 11.57.1. Dominio `app/Pos/` completo. Persistencia: `Product`, `Sale`, `SaleLine`, `Document`, `InvoiceDetail`, `StockMovement`, `AuditLog`, `Convention`, `Prescription`, `ControlledRegisterEntry`. Servicios: `CartService`, `CheckoutService`, `VoidSaleService`, `AuditService`, `ConventionService`, `DocumentService`, `ManualDiscountService`. Emisión simulada: `Pos\Contract\DocumentEmitter`, `Pos\Emission\SimulatedDocumentEmitter`, `Pos\Folio\FolioAllocator`. HTTP: `PosController` y `Admin\{ConventionController, SaleController, DocumentController, AuditController, ControlledRegisterController}`. Vistas: `pos/index`, `pos/checkout`, `pos/receipt`, `admin/*` (layout `layouts.admin`). Traducciones en `lang/es/`. 246 tests en verde (1131 aserciones).
- **Spec 002 paneles/inventario/caja:** `specs/002-dashboard/spec.md` v1.0, **aprobada** (2026-11-17). 37 RF (RF-41 a RF-77), 9 RNF (RNF-14 a RNF-22). Cubre los cuatro paneles por rol, el catálogo de productos con ajuste de stock auditado y el cierre de caja con diferencia declarada. Introduce el rol Bodeguero y absorbe dos temas que 001 delegaba o excluía: el arqueo (antes en `004-reportes`) y los tableros por rol. Las 5 enmiendas que necesitaba la spec 001 ya están aplicadas (v1.1), así que las dos specs no se contradicen. **Plan y tareas definidos:** `specs/002-dashboard/plan.md` y `specs/002-dashboard/tasks.md` (T11–T18, ocho tareas en orden de dependencia). **T11, T12, T13, T14, T15, T16, T17 y T18 cerradas el 2026-11-18. La spec 002 queda implementada.**
- **Spec 003 autenticación:** `specs/003-auth/spec.md` v1.0, **aprobada** (2026-11-18). 5 RF (RF-78 a RF-82) y 3 RNF (RNF-23 a RNF-25): pantalla de inicio de sesión, autenticación real contra las credenciales, mensaje único para credenciales inválidas, freno por fuerza bruta y cierre de sesión. Las specs 001 §7 y 002 §7 remitían la autenticación «a otra spec» que nunca existió, así que la 003 las enmienda. **Plan y tareas:** `specs/003-auth/plan.md` y `specs/003-auth/tasks.md` (T19–T23). **T19 cerrada el 2026-11-18.**
- **Comandos SDD:** instalados en `.opencode/commands/` (9 comandos).

## Entorno (2026-10-30)

- PHP 8.2.12 (XAMPP) con `pdo_pgsql`, `pgsql` y `zip` habilitados en `C:\xampp\php\php.ini` (respaldo en `php.ini.bak-sdd`).
- PostgreSQL **18** local en `127.0.0.1:5432`, autenticación `trust`, bases `farmacia_pos` y `farmacia_pos_test`.
- **Docker no instalado**: desviación aprobada del plan, se usa PG 18 local en lugar de PG 16 en contenedor.
- `composer` instaló con `--no-security-blocking` porque Laravel 11 arrastra avisos de seguridad; revisar antes de producción.
- Zona horaria `America/Santiago`, `SESSION_DRIVER=database` (falta migración de sesiones para RF-6).
- **Skill `frontend-design` (2026-11-03):** instalada desde `anthropics/skills` con `npx skills add anthropics/skills -s frontend-design`. El contenido descargado vive en `.agents/skills/` (ignorado por git) y `skills-lock.json` sí se versiona: cualquier integrante restaura el entorno con `npx skills experimental_install`. Es una guía de diseño para el agente, no una dependencia de la aplicación ni de la suite.

## Decisiones vigentes

- **D-1:** documentos tributarios **simulados** (folio interno, estados pendiente/emitido/rechazado). Sin SII ni servicios externos.
- **D-2:** tope general de descuento **10%** por transacción; convenios de salud con porcentaje propio definido por el administrador.
- **D-3 (reemplazada por D-6 de la spec 002):** el cierre de caja y el arqueo viven en `specs/002-dashboard/`; `specs/004-reportes/` queda para reportes históricos y exportaciones.
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
- **Cambio de stock (T5/S5):** `StockPolicy::detectChanges` compara la existencia viva contra el stock congelado al agregar o ajustar cada línea. La caja guarda ese stock en la sesión `pos.cart_stock` (junto a `pos.cart`), porque `CartService::load()` relee la base en cada request; `PosController::checkout` detecta los cambios y muestra «Stock actual: X» más el botón «Ajustar cantidades». La transacción de cobro sigue bloqueando la fila real con `lockForUpdate()` (T8).
- **Carrito en sesión (T6):** se guarda `id de producto => cantidad` en `pos.cart`, el stock congelado en `pos.cart_stock` y, desde S11, el descuento por línea en `pos.line_discounts` (mapa id => tipo, valor y motivo); precio, categoría y stock se releen de la base en cada request.
- **Búsqueda (T6):** `lower(...) like` sobre nombre, principio activo y código de barras, límite 20 resultados, sólo productos vendibles.
- **Tailwind (T6, B10):** `tailwindcss@3` + PostCSS + Vite. `npm run build` genera `public/build`, que **está versionado desde B10**: un clon funciona sin `npm install` ni internet. Los layouts usan `@vite` directo, sin la rama de respaldo con el CDN. Sigue siendo obligatorio ejecutar `npm run build` después de tocar clases de Tailwind, porque el CSS compilado sólo contiene las utilidades que existían al compilar (pasó con `bg-amber-600` del botón «Ajustar cantidades»).
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
- **Auth del POS (S1):** todo el grupo `/pos` exige `auth`; las peticiones JSON sin sesión reciben 401 y la navegación normal redirige a `/login` (`redirectGuestsTo`, la pantalla de login queda fuera de alcance según spec §7). `Sale::creating` completa `user_id` con `auth()->id()` si el servicio no lo recibe, de modo que ninguna venta quede sin cajero (RF-22).
- **Libro de controlados (S3):** tablas `prescriptions` (número + RUT del prescriptor único, para detectar reutilización) y `controlled_register` (una fila por línea controlada: receta, venta, línea única, medicamento, cantidad, RUT del cliente, folio y `sold_at` congelados). El libro se consulta en `admin.controlled-register.index` y se exporta en CSV (`RUT, Folio, Fecha, Medicamento, Cantidad`) sólo para Admin. Desde S2 el cobro lo escribe por cada línea controlada.
- **Receta en el cobro (S2):** el checkout exige receta cuando el carrito tiene ítems con receta (la categoría más restrictiva manda). Valida número, prescriptor, RUT y fecha con `PrescriptionPolicy` (30 días configurables, sin fechas futuras); la receta se guarda en `prescriptions` y la venta apunta con `sales.prescription_id`. Una receta ya usada exige credenciales de Químico/Admin en el mismo POST (se verifica `Hash::check` y el rol), se reintenta con la receta existente y la autorización queda auditada como `sale.prescription_reuse`. Si el carrito incluye controlados, se escribe una fila por línea en `controlled_register` con folio, medicamento, cantidad, RUT y fecha.
- **Descuento manual (S4):** el cajero lo carga en la caja por porcentaje o monto con motivo obligatorio; se guarda en la sesión `pos.manual_discount` y se re-evalúa con `DiscountPolicy` al confirmar el cobro. Hasta el 10% se aplica directo; sobre el tope la vista abre un modal que exige credenciales de Químico/Admin (mismo `Hash::check` + rol que la receta reutilizada) y sólo entonces se guarda. La venta registra `discount_origin`, `discount_reason` y `discount_authorized_by`, y la autorización sobre el tope se audita como `sale.discount_authorized`. El comprobante muestra motivo y autorizador. El convenio sigue prevaleciendo sobre el descuento manual.
- **Aviso de cambio de stock (S5):** al abrir el cobro, `PosController::checkout` compara el stock vivo con el congelado en `pos.cart_stock` mediante `StockPolicy::detectChanges`. Si cambió, la vista muestra por producto la cantidad pedida, la existencia anterior, «Stock actual: X» y el botón «Ajustar cantidades» (vuelve a la caja); ajustar la cantidad refresca el stock base y apaga el aviso. No bloquea la venta: la validación definitiva sigue en la transacción de cobro.
- **Supuestos S-1 a S-6:** emisor ficticio, factura con RUT/razón social/giro/dirección/comuna, receta válida 30 días, libro de controlados como registro + CSV, nota de crédito simulada, RUT de cliente opcional.
- **Idioma (S8):** la interfaz, los mensajes de validación y los estados viven en `lang/es/pos.php` (+ `lang/es/validation.php`); `config/app.php` fija `locale = 'es'` para no depender de `APP_LOCALE` del `.env`. Las vistas Blade y los controladores usan `__('pos.*')`, y los enums `DocumentStatus`, `SaleStatus`, `PaymentMethod` y `DocumentType` exponen `label()` traducido (Pendiente/Emitida/Rechazada, Confirmada/Anulada). Código sigue en inglés; sólo la interfaz está en español.
- **Factura (S6, RF-23/S-2):** el checkout pregunta «Boleta/Factura» (`document_type`). La factura exige RUT, razón social, giro, dirección y comuna (`required_if`), que se guardan en `invoice_details` (1:1 con `sales`, con el rango `folio_from`/`folio_to` usado). `CheckoutService` emite `DocumentType::invoice` con folio del rango 50.001–100.000, independiente del de boletas; el comprobante muestra «Factura electrónica» y el bloque «Datos de factura». `nextFolio()` ahora usa `match` por tipo (boleta, factura y nota de crédito).
- **Receta en el detalle (S7, RF-38):** `admin.sales.show` muestra la tarjeta «Receta asociada» (número, prescriptor, RUT, emisión y «Vigente hasta») sólo cuando la venta tiene receta con todos sus campos. La vigencia no se guarda: `Prescription::validUntil()` la calcula como `issued_on + pos.prescription.validity_days` (30 días, el día 30 es el último válido, igual que `PrescriptionPolicy`).
- **Emisión después del cobro (S9, RF-24):** `CheckoutService` crea la venta, líneas, stock, movimientos, `invoice_details` y **un** documento original `pending` sin folio en la misma transacción; al confirmar, limpia carrito y redirige al comprobante. Recién entonces `issueDocument()` intenta la emisión simulada. Si el simulador falla (`EmissionFailed`), la venta queda confirmada y el documento pendiente para reintento; ningún otro error se captura (un `QueryException` se propaga y deja ver el fallo real). El flujo normal sigue siendo síncrono: la misma petición emite y asigna folio.
- **Punto de simulación (S9):** interfaz `Pos\Contract\DocumentEmitter` con `SimulatedDocumentEmitter` (sin llamada externa) enlazada en `AppServiceProvider`. Los tests la sustituyen por un doble que lanza `EmissionFailed`; no hay interruptor de «fallar emisión» en la interfaz ni en la demo.
- **Folios (S9):** todo pasa por `DocumentService::allocateFolio()` (boleta, factura y nota de crédito). En PostgreSQL cada tipo toma `pg_advisory_xact_lock(20261102, tipo)` antes de leer `max(folio)`, de modo que dos cajas no calculan el mismo número; el lock es transaccional y un rollback no quema el folio. `FolioAllocator` es la función pura del rango y el índice único `(type, folio)` sigue como última defensa. La anulación usa el mismo camino para la nota de crédito.
- **Un documento original por venta (S9):** índice único parcial `documents_sale_original_unique ON documents (sale_id) WHERE reversal_of_id IS NULL` (migración `2026_11_02_000002`), que no bloquea la nota de crédito porque lleva `reversal_of_id`.
- **Venta anulada con documento pendiente (S9):** el reintento se **bloquea** con error de dominio (`pos.errors.voided_sale_document`) y el botón se oculta en historial y documentos; no se crea nota de crédito porque nunca hubo emisión. Si el documento estaba emitido, la anulación sigue generando la nota como en T9.
- **Comprobante provisorio (S9):** `pos/receipt` muestra «Provisorio», «Pendiente de emisión: sin folio asignado» y la leyenda de simulación cuando el documento está `pending`; nunca imprime un folio inventado. «Reintentar emisión» está en `admin/sales/index` y `admin/documents/index`, sólo para Admin, y desaparece cuando el documento está emitido.
- **Mensaje de concurrencia (S10, RF-28):** cuando el descuento condicional (`where stock >= qty`) no afecta ninguna fila porque otra caja se adelantó, `CheckoutService::shortageMessage()` lanza `InsufficientStock` con «Stock actual: :available unidades del producto ":name"» (`pos.errors.concurrent_stock`), leyendo la existencia real con la fila de producto ya bloqueada dentro de la transacción. Sustituye el viejo «Sin stock suficiente para el producto {id}»; el aviso previo de S5 (`stock_changed_*`) y el rechazo de producto sin stock en `PosController::add` quedan intactos. 2 tests nuevos en `ConcurrencyTest` verifican el mensaje exacto y su llegada a `/pos/checkout` sin persistir venta ni movimientos.
- **Descuento por línea (S11, RF-4/RF-13 a RF-18):** el cajero aplica `%` o `$` por línea con motivo obligatorio; el valor y el motivo viven en la sesión junto al carrito y el cálculo sigue en el dominio (`Cart::applyLineDiscount`, `PriceCalculator::lineDiscount`). `DiscountPolicy::evaluateLine()` rechaza motivo vacío y total cero/negativo, y mide el **acumulado de todas las líneas** contra el tope del 10%; sobre el tope pide credenciales de Químico/Admin (mismo `Hash::check` + rol) y el modal se reabre si las credenciales son inválidas. El tope del descuento global ahora también descuenta las líneas (`evaluate(..., lineDiscountsPesos)` / `PriceCalculator::lineDiscounts`), de modo que 10% de línea + 10% global exige supervisor (RF-14 acumulado). La línea muestra el chip `-10% · motivo` con «Quitar descuento»; el control es un `<details>` nativo (accesible sin JS). El checkout muestra el descuento por renglón y el pie subtotal/descuentos.
- **Auditoría de descuento por línea (S12, RF-36):** la autorización del supervisor se recuerda en `pos.line_discounts[...]['authorized_by']` (misma entrada que el descuento; se conserva al cambiar la cantidad mientras el tipo y el valor no cambien) y `CheckoutService` la registra al confirmar el cobro como `sale.line_discount_authorized`, con el subject en la `SaleLine` real y `discount_type`/`discount_value`. Se eligió el cobro (no la caja) porque la venta aún no existe al autorizar, y el subject polimórfico (no `sale_line_id`) para no duplicar la referencia ni borrar auditoría en cascada. `audit_logs` sólo gana dos columnas nullable (migración `2026_11_03_000000`); el detalle de venta y la bitácora resuelven producto y venta por la línea. La receta repetida y el descuento global ya se auditaban; este cierre no los toca.

## Plan 002 (2026-11-18)

`specs/002-dashboard/plan.md` y `specs/002-dashboard/tasks.md` escritos con el formato de la 001 (§0 a §7 y T11–T18). Decisiones que condicionan la implementación:

- **Cuatro migraciones:** `products.min_stock` + `products.active`, `cash_registers`, `stock_movements` (`reason` → `type`, más `note`, `user_id` y `resulting_stock`) y `sales.cash_register_id`.
- **Turno de caja como tabla propia** con índice único parcial `WHERE closed_at IS NULL` (SQL crudo: el builder no lo expresa). El cierre graba el arqueo y los totales en la fila del turno; una anulación posterior no los reescribe (caso límite 7).
- **La venta pertenece a un turno por `cash_register_id`**, no por ventana de fechas: evita el cruce de medianoche y fija el conjunto de ventas del cierre.
- **`products.active` separado de `sellable`:** «fuera de catálogo» sale de la búsqueda de la caja y conserva historial; «no vendible» sólo impide venderlo de nuevo.
- **`resulting_stock` obligatorio**, rellenado hacia atrás con `SUM(quantity) OVER (PARTITION BY product_id ORDER BY id)`.
- **Un `SalesDayQuery` único** para los tres paneles que muestran totales del día, de modo que RF-75 se cumpla por construcción.
- **Los paneles reutilizan `layouts/admin.blade.php`** con un `$navGroup`; renombrar el layout queda como deuda sin RF.
- **RNF-14 se mide a mano** y se documenta; RNF-15 se automatiza contando consultas con `DB::listen`.
- **RF-60 deja en rojo los tests de cobro existentes**: se corrige declarando la precondición con `openCashRegister()` en `tests/TestCase.php`, nunca relajando aserciones.

## T11 cerrada — base de datos y aterrizaje por rol (2026-11-18)

RF-41, RF-42, RF-43 y RF-45. Tests escritos primero en rojo (11 failed, 1 passed) y luego implementados.

- **Esquema:** las cuatro migraciones del plan aplicadas. `products.min_stock` (umbral de reposición) y `products.active` (fuera de catálogo); `cash_registers` con índice único parcial `cash_registers_open_unique … WHERE closed_at IS NULL`; `stock_movements.reason` renombrado a `type` con `note`, `user_id` y `resulting_stock` obligatorio (rellenado con `SUM(quantity) OVER (PARTITION BY product_id ORDER BY id)`); `sales.cash_register_id` nullable con índice.
- **Rol Bodeguero:** `User::ROLE_BODEGUERO`, `isBodeguero()`, `UserFactory::bodeguero()` y `UserSeeder` idempotente con los cuatro roles (`admin@farmacia.test`, etc., contraseña de desarrollo). `DatabaseSeeder` lo invoca.
- **Aterrizaje:** `App\Pos\Policy\LandingPolicy` (mapa rol → ruta, `panelRoute()`/`canOpen()`, sin Eloquent) y `DashboardController::home()`; `/` y `/panel` redirigen al panel del rol, y un rol ausente o desconocido recibe 403 con la vista `dashboard.unsupported`.
- **Acceso:** `/pos` queda con `role:tecnico,quimico,admin`; cada `/panel/{rol}` exige su rol. Los paneles reutilizan `layouts/admin.blade.php` con `$navGroup` y ocultan «Ir al POS» al Bodeguero.
- **Movimientos de stock:** `CheckoutService::lockProducts()` ahora devuelve la existencia bloqueada por id y ambos servicios (cobro y anulación) escriben `type` (`App\Pos\Enum\StockMovementType`) y `resulting_stock` con autor.
- **Verificación:** suite completa **259 tests, 1179 aserciones, 0 fallos** (30,17 s), desde 246/1131. Base demo: `movimientos=4 nulos=0 inconsistentes=0`, índice parcial presente, columnas nuevas en su sitio. Chrome DevTools a 375×812: `/panel/admin` con grupo «Administración» (5 secciones, 2 enlaces al POS), `/panel/bodeguero` con sólo «Inicio» y 0 enlaces al POS, `scrollWidth=375`, sin errores de consola.

## T12 cerrada — turnos de caja y arqueo (2026-11-18)

RF-58 y RF-59. Tests primero en rojo (22 fallos) y después implementación mínima.

- **Dominio puro:** `CashArqueo` (`expected = apertura + ventas en efectivo − anulaciones en efectivo`, `difference = declarado − esperado`, `sign()`), enum `CashDifference` (`balanced|over|short`), `ArqueoResult` (esperado, declarado, diferencia y sentido) y los valores `PaymentBreakdown` y `TurnSummary`.
- **Servicio:** `App\Services\Cash\CashRegisterService` con `openFor()`, `open()`, `summary()`, `arqueo()` y `close()`. La segunda apertura se detecta en PHP y, ante dos pestañas simultáneas, la garantiza el índice único parcial (`QueryException` SQLSTATE 23505). El resumen se obtiene con **una sola consulta agrupada** por estado y medio de pago; el cierre bloquea la fila del turno (`lockForUpdate`), graba el acta y no vuelve a calcularla (caso límite 7).
- **HTTP:** `POST /cash-registers`, `GET /cash-registers/close` y `PATCH /cash-registers/{cashRegister}/close`, todos con `role:tecnico`; `OpenCashRegisterRequest` y `CloseCashRegisterRequest` validan monto entero no negativo. El controlador traduce las excepciones del dominio a errores de validación y comprueba que sólo el responsable cierre su turno (403).
- **Vista `cash/close`:** muestra apertura, ventas, anulaciones, desglose por medio de pago y efectivo esperado; el campo declarado calcula la diferencia en vivo con un script mínimo (`$3.000 (En contra)`), y el cierre se permite con diferencia distinta de cero.
- **Idioma:** el grupo nuevo es `pos.cash_register.*`, **no** `pos.cash.*`, porque `pos.cash` ya existía con los textos del POS (búsqueda, carrito, descuentos). Reutilizar el nombre sobrescribió ese grupo y dejó 8 tests del POS en rojo hasta corregirlo.
- **Verificación:** objetivo **22 tests, 96 aserciones**; suite completa **281 tests, 1275 aserciones, 0 fallos** (29,46 s), desde 259/1179. Chrome DevTools a 375×812: esperado `$56.000`, desglose Efectivo `$10.000` y Débito `$6.000`, diferencia en vivo correcta, `scrollWidth=375`, sin errores de consola. Los datos de demo usados para la captura se borraron después.

## T13 cerrada — panel del Técnico y bloqueo del cobro (2026-11-18)

RF-56, RF-57, RF-60, RF-61 y RF-62. Tests primero en rojo (10 fallos) y después implementación mínima.

- **Bloqueo del cobro (RF-60):** `CheckoutService` resuelve el turno abierto del cajero dentro de la transacción (`lockForUpdate`) y lanza `CashRegisterNotOpen` con el mensaje `pos.cash_register.error.open_to_charge` si no existe; la venta se crea con `cash_register_id`. La comprobación va después de la idempotencia por token, para que reintentar una venta ya confirmada siga devolviendo la existente. El bloqueo serializa cobro y cierre; una caja cerrada o de otro cajero no autoriza.
- **HTTP:** `PosController::store()` traduce `CashRegisterNotOpen` a un error de validación en `cash`; `checkout()` recibe el turno abierto y, sin él, la vista reemplaza el formulario de pago por el aviso y un enlace de apertura. `PosController` recibe `CashRegisterService` por constructor.
- **Panel del Técnico:** `CashierPanelService::for()` devuelve turno y `TurnSummary` (o nulos); `CashierDashboardController` arma la vista `dashboard/tecnico` con los parciales `open-cash-form` y `turn-summary`. Muestra apertura, ventas por medio de pago, total, anulaciones, enlaces al POS y al cierre. Sin turno muestra aviso y formulario de apertura.
- **POS:** el layout incorpora «Ir a mi panel», resuelto con `LandingPolicy::panelRoute()` para el rol en sesión.
- **Precondición en tests:** helper `openCashRegister(?User $user = null, int $openingAmount = 50000)` en `tests/TestCase.php`; los 16 archivos que cobran lo declaran en `setUp` (o en el helper de sesión de `AdministrationTest`), y las llamadas directas a `CheckoutService` de `ConcurrencyTest` pasan `userId`. Ninguna aserción existente se relajó.
- **Verificación:** objetivo **11 tests, 51 aserciones**; suite completa **292 tests, 1326 aserciones, 0 fallos**, desde 281/1275. Chrome DevTools a 375×812: panel sin caja (aviso, formulario y enlace al POS), panel con caja (`$50.000` inicial, `$16.000` vendidos, Efectivo `$10.000`, Débito `$6.000`, anulaciones `$4.000`), checkout sin caja sin formulario de pago, `scrollWidth=375`, sin errores de consola. Los datos de demo se borraron después.

## T14 cerrada — panel del Administrador (2026-11-18)

RF-46 a RF-51. `AdminPanelTest` escrito primero en rojo (9 fallos de 10) y después implementación mínima.

- **Resumen del día (RF-46, RF-75):** `SalesDayQuery::forDay()` hace una sola consulta agrupada por estado y medio de pago; `SalesDaySummary` es el valor compartido por los paneles. Las ventas anuladas quedan fuera del monto vendido y se informan aparte. `AdminPanelService` sólo compone los bloques con `for()`.
- **Documentos por resolver (RF-47):** pendientes y rechazados con su venta, tipo y fecha, con `POST admin.documents.retry` en línea; un emitido no entra a la lista. El reintento fallido vuelve al panel con `errors['document']` y el documento sigue en `rejected`.
- **Inventario (RF-48):** conteos `Product::active()->belowMinimum()` y `outOfStock()`, sin productos fuera de catálogo. El enlace al catálogo se condiciona con `Route::has('inventory.products.index')` hasta T16.
- **Auditoría (RF-49):** últimos 10 `AuditLog` con autor, acción traducida por `pos.panel.audit_action.*`, venta resuelta desde el subject polimórfico y motivo; si la acción no tiene traducción se muestra el identificador técnico.
- **Convenios (RF-50):** `pos.convention.expiring_days = 30`; el servicio separa activos próximos a vencer y vencidos. `ConventionService::activate()` lanza `DomainException` con `pos.panel.conventions.error.expired` si el convenio ya venció. `ConventionController::toggle()` alterna el estado y traduce el rechazo; ruta nueva `PATCH /admin/conventions/{convention}/toggle`.
- **Cortes (RF-51):** turnos abiertos y cerrados que comenzaron hoy, con responsable, esperado, declarado y diferencia firmada (esmeralda a favor, ámbar en contra); un turno abierto muestra pendientes en lugar de un arqueo inexistente.
- **Panel sin datos (RF-77):** indicadores en cero y un mensaje por bloque en lugar de secciones vacías.
- **Verificación:** objetivo **10 tests, 70 aserciones**; suite completa **302 tests, 1396 aserciones, 0 fallos**, desde 292/1326. Chrome DevTools a 375×812 con datos de demo: `$20.000` vendidos, `$4.000` anulados, Efectivo `$12.000`, Débito `$8.000`, dos reintentos visibles, alertas 2 y 1, «Anulación de venta» con autor y motivo, convenio por vencer con «Desactivar» y vencido sin activar, corte cerrado `$58.000`/`$54.000` con `-$4.000` y turno abierto. Se corrigió el desborde de la tabla dentro de la rejilla con `min-w-0` y se oculta la fecha de documentos bajo `sm` para que «Reintentar emisión» quede visible a 375 px; `scrollWidth=375`, sin errores de consola. Datos de demo borrados después.

## T15 cerrada — panel del Químico (2026-11-18)

RF-52 a RF-55. `ChemistPanelTest` escrito primero en rojo (5 fallos) y después implementación mínima.

- **Resumen del día (RF-52):** `ChemistPanelService::for()` delega en `SalesDayQuery`, igual que el panel del Administrador, así que el mismo día no puede dar cifras distintas. La vista reutiliza `payment-breakdown` con un parámetro `panel` que elige las traducciones (`admin` por defecto).
- **Autorizaciones propias (RF-53):** `AuditLog` del usuario en sesión con acciones `sale.discount_authorized`, `sale.line_discount_authorized` y `sale.prescription_reuse`, acotadas con `whereDate('created_at', ...)`. El monto sale del descuento global de la venta (subject `Sale`) o del `discount_value` de la línea; la receta reutilizada no tiene monto y muestra «—».
- **Anulaciones del día (RF-54):** `AuditLog` con acción `sale.void` de hoy, con su autor y motivo.
- **Salidas de lectura (RF-55):** enlaces a `admin.conventions.index` y `admin.audit.index`, rutas que el Químico comparte con el Admin; el panel no incluye ningún `<form>`. El 403 del Químico en las rutas de gestión sigue cubierto por `AdministrationTest::test_quimico_can_void_and_read_conventions_but_cannot_manage_them`.
- **Verificación:** objetivo **5 tests, 39 aserciones**; suite completa **307 tests, 1435 aserciones, 0 fallos**, desde 302/1396. Chrome DevTools a 375×812 con datos de demo: 3 ventas por `$23.000`, Efectivo `$15.000` y Débito `$8.000`, tres autorizaciones con venta, monto (`$900`, `$500`, «—») y motivo, anulación con motivo y autora, enlaces de lectura y cero formularios; `scrollWidth=375`, sin errores de consola. Se ocultó la fecha bajo `sm` y se acortaron los encabezados a «Acción» y «Monto» para que las cuatro columnas de autorizaciones quepan sin scroll interno a 375 px. Datos de demo borrados después.

## T16 cerrada — catálogo de productos (2026-11-18)

RF-68 a RF-72. `BarcodePolicyTest` y `ProductCatalogTest` escritos primero en rojo (16 fallos) y después implementación mínima.

- **Política pura (`BarcodePolicy`):** `isValid()` acepta trece dígitos con dígito verificador correcto y también `null`, vacío o sólo espacios (producto sin código); `checkDigit()` calcula el verificador de los doce primeros dígitos. Sin dependencias de framework.
- **Servicio (`ProductService`):** única puerta de búsqueda (paginada, por nombre, principio activo o código) y de persistencia. Normaliza el código vacío a `null`, lanza `DomainException` con el motivo cuando el EAN-13 es inválido o el código ya existe, y desactiva en vez de borrar. `search()` ordena los vigentes primero.
- **Controlador y requests:** los FormRequest validan sólo forma (nombre requerido, precio entero ≥ 1, categoría con `Rule::enum(SaleCategory::class)`, mínimo y existencia inicial no negativos); el controlador captura el `DomainException` y devuelve `back()->withInput()->withErrors(['barcode' => ...])`, de modo que el mensaje nombra el motivo (RNF-22) y conserva lo escrito.
- **Rutas y RBAC:** `inventory.products.index` para `admin,bodeguero`; create, store, edit y update sólo para `admin`. El Bodeguero lista el catálogo sin ver los botones de escritura y recibe 403 en las cuatro rutas de escritura.
- **POS (RF-72):** `search()`, `barcodeCandidates()` y `add()` filtran por `active`. Un producto fuera de catálogo desaparece de la búsqueda y del escaneo, y su envío directo a `/pos/cart` falla con `pos.errors.product_not_sellable`.
- **Navegación:** «Productos» se agrega a los grupos `admin` y `bodeguero`, lo que llevó el menú del Administrador de 5 a 6 ítems; se actualizó `AdministrationTest`.
- **Fixture:** se completó `ProductFactory` con `min_stock` y `active` (columnas de T11 que no estaban declaradas) para que un producto recién creado hidrate sus valores; sin cambio de comportamiento porque reproducen los valores por defecto del esquema.
- **Verificación:** objetivo **16 tests, 82 aserciones**; suite completa **323 tests, 1525 aserciones, 0 fallos**, desde 307/1435. Chrome DevTools a 375×812: listado con `scrollWidth=375` y tabla de scroll interno (343→760); búsqueda por principio activo y por código; alta con existencia inicial; precio cero rechazado; verificador incorrecto y código repetido rechazados mostrando el motivo; edición de precio, «Vendible: No» y «Fuera de catálogo»; badge verde «En catálogo» y gris «Fuera de catálogo» con fila atenuada; `POST /pos/cart` del desactivado responde 422 y uno activo sí entra; el Bodeguero lista sin botones y recibe 403. Sin errores de consola. Datos de demo borrados después.

## T17 cerrada — inventario del Bodeguero (2026-11-18)

RF-63 a RF-67, RF-73 y RF-74. `StockAdjustmentPolicyTest` y `StockAdjustmentTest` escritos primero en rojo (22 fallos) y después implementación mínima.

- **Dominio puro:** `StockAdjustmentPolicy::evaluate()` rechaza cantidad no positiva, motivo vacío y salida mayor que la existencia, y acepta con `resulting = currentStock + type->sign() * quantity`. `StockAdjustment` (readonly) expone `accepted()`, `resultingStock()`, `available()` y `rejection()`; sus constructores son `accept()`/`reject()` (los getters ocupan los nombres `accepted`). Un rechazo deja la resultante igual a la disponible.
- **Servicio (`StockAdjustmentService`):** bloquea la fila del producto dentro de una transacción, decide con la política y sólo entonces actualiza la existencia e inserta el movimiento con `quantity` firmada, `resulting_stock`, `note` y `user_id`.
- **Panel (`WarehousePanelService` + `WarehouseDashboardController`):** `InventorySnapshot` (activos, bajo mínimo, sin stock, movimientos de hoy con `whereDate`) más la prioridad de reposición (`active()->belowMinimum()->orderBy('stock')`) y los últimos 10 movimientos con producto, tipo, motivo, autor y fecha. Las cifras se marcan con `id="inventory-*"` para poder leerlas en los tests sin depender de números sueltos.
- **Ajuste (RF-66, RNF-18):** `AdjustStockRequest` valida forma (producto existente, tipo `in|out`, cantidad ≥ 1, motivo obligatorio); el controlador traduce el rechazo del servicio en un error que nombra la existencia disponible. El formulario usa campos nativos con `label for`, así que se completa con teclado.
- **Rutas (RF-67):** `inventory.movements.index` y `inventory.adjustments.store` para `admin,bodeguero`. El libro de controlados pasa a `admin,bodeguero` en lectura (el export y el enlace a la venta quedan para el Administrador).
- **Verificación:** objetivo **22 tests, 96 aserciones**; suite completa **345 tests, 1621 aserciones, 0 fallos**, desde 323/1525. Chrome DevTools a 375×812: panel con activos 8, bajo mínimo 3, sin stock 1 y movimientos de hoy 2; prioridad `0`, `2`, `4` unidades; ajuste completado **sólo con teclado** (Tabs, flechas, texto y Enter → «Ajuste registrado», Amoxicilina 40→33 con movimiento `out` −7 y autor); salida excesiva rechazada con «sólo hay 2 unidades» conservando lo escrito; `/inventory/movements` con 7 filas. `scrollWidth=375`, sin errores de consola. Datos de demo borrados y ajuste de prueba revertido.
- **Hallazgo fuera de alcance:** el libro de controlados (vista de 001) desborda a 375 px porque su filtro de fechas no envuelve; se anota, no se corrige.

## T18 cerrada — navegación, coherencia y cierre (2026-11-18)

RF-44, RF-75 a RF-77 y RNF-14 a RNF-22. `NavigationTest`, `ReportConsistencyTest` y `QueryBudgetTest` escritos primero en rojo y después implementación mínima.

- **Navegación por rol (RF-44, RNF-19):** `config/navigation.php` suma a cada rol sus secciones propias —Químico: convenios y auditoría en lectura; Bodeguero: movimientos y controlados— y estrena `pos.nav.movements`. `NavigationTest` recorre los cuatro roles: toda opción visible responde 200, ninguna sección ajena aparece y cada etiqueta está traducida de verdad. El acceso al POS del layout cubre RF-45 para Admin, Químico y Técnico y se oculta al Bodeguero.
- **Coherencia de indicadores (RF-75, RF-76, RF-77):** `ReportConsistencyTest` comprueba que el panel del Administrador y el historial del mismo día dan los mismos montos, que el Químico reporta la misma cifra, que el panel no mezcla días alrededor de la medianoche (caso 10, con `Carbon::setTestNow`), que se recalcula en cada carga sin caché y que los cuatro paneles sin actividad muestran ceros con mensaje.
- **Reloj simulado:** los tres servicios de panel pasan de `new DateTimeImmutable('now', …)` a `CarbonImmutable::now(config('pos.timezone'))`, de modo que respetan la zona del negocio y el reloj simulado de los tests.
- **Presupuesto de consultas (RNF-15):** `QueryBudgetTest` mide cinco pantallas con listas cortas y largas (ambas con datos, porque Eloquent omite las precargas con listas vacías) y confirma que el número de consultas no crece.
- **RNF-14 medido a mano** con 20.000 ventas y 5.000 productos (medición sobre la request completa, sin servidor): panel del Administrador **p95 29,5 ms** (media 23,4, máx 37,1); panel del Bodeguero **p95 254,9 ms** (media 230,2, máx 257,4), el más costoso porque renderiza la prioridad de reposición completa (unos 2.300 productos bajo el mínimo). Ambos cumplen el objetivo de 800 ms.
- **Demos manuales de los criterios 4 a 8:** (4) los cuatro roles aterrizan en su panel y sólo ven sus secciones; (5) turno completo —apertura 50.000, dos ventas en efectivo, anulación de una por el Administrador, cierre con 54.160 declarados— y corte visible en el panel del Administrador; (6) producto con existencia 3 y mínimo 5 (alerta «bajo mínimo» 0→1), agotado con un ajuste motivado (alerta «sin stock» 0→1 y primera fila de la prioridad del Bodeguero); (7) panel e historial con los mismos montos del día ($4.460 vendido, $990 anulado); (8) los cuatro paneles a 375 px (375/375) y a escritorio (1425/1440, barra lateral visible), sin errores de consola.
- **Verificación:** objetivo **14 tests, 221 aserciones**; suite completa **359 tests, 1842 aserciones, 0 fallos**, desde 345/1621. Datos de demo borrados; la limpieza devolvió a Paracetamol e Ibuprofeno el stock que descontaron las ventas.
- **Corrección posterior (RF-59):** la demo del cierre destapó que `expected_cash = 53.470` cuando el efectivo real era 54.460: `CashRegisterService::summary()` ya dejaba fuera de `cashSales` el efectivo de las ventas anuladas y `CashArqueo::expected()` volvía a restarlo con `cashVoids`, descontando la anulación dos veces. Se corrigió `summary()` para acumular el efectivo **bruto** cobrado en el turno (incluidas las que luego se anulan) y seguir restando `cashVoids`, que es la semántica que ya fijaba `CashArqueoTest`. Se escribió primero el test que lo demuestra (rojo: 2 fallos), se actualizaron dos expectativas antiguas de `CashRegisterTest` que consagraban el cálculo erróneo y la suite quedó en **360 tests, 1847 aserciones, 0 fallos**. El docblock de `summary()` y de `TurnSummary` declara el significado de `cashSales`.
- **Hallazgo abierto (spec 001):** el libro de controlados desborda a 375 px porque su filtro de fechas no envuelve.

## T19 cerrada — autenticación (2026-11-18)

RF-78 a RF-82 y RNF-23 a RNF-25 (spec 003). `LoginTest` escrito primero en rojo y después implementación mínima.

- **El hueco real:** `bootstrap/app.php` ya enviaba al invitado a `/login`, pero **la ruta no existía**: un usuario real abría `/` y terminaba en un 404. Las specs 001 y 002 declaraban la autenticación «fuera de alcance porque se asume existente»; en la práctica nadie podía entrar sin inyectar una sesión con un script. Se redactó la spec 003 con plan y tareas, y se enmendaron las dos líneas de «fuera de alcance» de 001 §7 y 002 §7.
- **Piezas:** `App\Http\Controllers\Auth\LoginController` (`show`, `authenticate`, `destroy`) sólo orquesta; `App\Http\Requests\Auth\LoginRequest` valida la forma y concentra `Auth::attempt` y el freno por `RateLimiter` (5 intentos por minuto, clave correo + IP, `RateLimiter::clear()` al acertar); `LandingPolicy` (spec 002) calcula el destino y un rol no reconocido cae en `dashboard.home`, que responde 403. Rutas `login` (GET/POST) y `logout` (POST). Textos en `pos.login.*` y `validation.attributes`.
- **Sin dependencias nuevas:** se usa el guard de sesión `web` que ya existía en `config/auth.php`; no se instaló Breeze, Fortify ni Jetstream (constitución, principio 1). `bootstrap/app.php`, `RoleMiddleware` y `LandingPolicy` no se tocaron.
- **Hallazgo dentro de la propia tarea:** la ruta de logout existía pero **ningún botón la usaba**, así que RF-81 era inalcanzable desde la interfaz. Se agregó el formulario de cerrar sesión a la barra lateral y a la barra móvil de `layouts/admin` y al encabezado de `layouts/pos`, con su test (`test_a_panel_offers_the_logout_action`).
- **Ajuste necesario en un test de la spec 002:** `ChemistPanelTest::test_the_panel_only_offers_read_only_links` aseveraba «el panel del Químico no tiene ningún `<form>`» (RF-55). El formulario de cerrar sesión es un `<form>` legítimo que no escribe datos, así que la aserción pasó a comprobar que **todos** los formularios del panel sean el de cerrar sesión, conservando el espíritu de RF-55 (sin relajar ningún requisito real).
- **Verificación:** objetivo **13 tests, 83 aserciones**; suite completa **373 tests, 1930 aserciones, 0 fallos** (52,67 s), desde 360/1847. Recorrido real con Chrome DevTools MCP a 375×812 sobre el servidor de desarrollo: la pantalla responde 200 con correo, contraseña y CSRF, `scrollWidth=375` y consola limpia; contraseña incorrecta y correo inexistente devuelven **el mismo** mensaje («El correo o la contraseña no son correctos.»); los cuatro roles iniciaron sesión desde el navegador y aterrizaron en su panel (`/panel/admin`, `/panel/quimico`, `/panel/tecnico`, `/panel/bodeguero`) con `scrollWidth=375`, el botón «Cerrar sesión» visible y sin errores de consola. La contraseña escrita no vuelve al HTML y el correo se conserva como dato previo.
- **Pendiente que destapó:** el `.env.example` seguía siendo el de Laravel (resuelto en T20).

## T20 cerrada — entorno y zona horaria (2026-11-18)

H1 del informe de revisión. `EnvironmentTest` escrito primero en rojo y después el arreglo.

- **El riesgo:** `sold_at` se escribe con `now()` (huso de `config('app.timezone')`) y los paneles filtran «de hoy» con `config('pos.timezone')`. Con `APP_TIMEZONE=UTC` —lo que traía el `.env.example`— las ventas de entre 21:00 y 23:59 de Santiago se escriben con fecha del día siguiente y los indicadores del día se descuadran; además el `.env.example` apuntaba a SQLite y a inglés, así que un clon corría otro sistema. La suite no lo detectaba porque `phpunit.xml` fija pgsql y el `.env` local sí tenía Santiago.
- **El arreglo:** `.env.example` pasa a declarar el stack real —`APP_TIMEZONE=America/Santiago`, `DB_CONNECTION=pgsql` con host, puerto, base `farmacia_pos` y usuario, `APP_LOCALE=es` y `SESSION_DRIVER=database`— con un comentario que explica por qué el huso debe coincidir con `pos.timezone`. Sólo afecta a un clon nuevo: los tests y el entorno local leen `.env`, no `.env.example`.
- **El guard:** `EnvironmentTest` comprueba que `config('app.timezone') === config('pos.timezone')` y que el archivo de ejemplo declare el huso del negocio, PostgreSQL y la sesión en base. Se verificó que **no es vacío**: con `APP_TIMEZONE=UTC` el primer test falla con el mensaje que explica el descuadre.
- **Verificación:** objetivo **4 tests, 9 aserciones** (rojo: 2 failed, 2 passed, 7 assertions); suite completa **377 tests, 1939 aserciones, 0 fallos** (64,53 s), desde 373/1930.

## T24 cerrada — freno a la autorización de supervisor (2026-11-18)

RF-83 y RNF-26 (spec 003 v1.1), S1 del informe de seguridad. `SupervisorAuthorizationTest` escrito primero en rojo y después el freno.

- **El agujero:** `PosController::supervisorId()` comparaba con `Hash::check` las credenciales que llegan del formulario **sin ningún límite de intentos**, en las tres vías que exigen supervisor (confirmar el cobro, descuento global y descuento de línea). Cualquier usuario autenticado —incluido un Técnico— podía probar contraseñas de supervisor indefinidamente hasta dar con una y aplicar descuentos sobre el tope del 10% sin autorización real (RF-16). Con bcrypt a 12 rondas cada intento costaba ~250 ms de CPU, así que además era un sumidero de CPU trivial.
- **El arreglo:** antes de comparar la contraseña se consulta el `RateLimiter` con la clave `supervisor-auth|{cajero}|{ip}` (compartida por las tres vías, D-16): cinco intentos por minuto, `RateLimiter::hit()` en cada fallo y `RateLimiter::clear()` al acertar. El freno da un mensaje propio (`pos.errors.supervisor_throttled`, con los segundos de espera) distinto del de credenciales inválidas, y **no** toca la sesión ni el rol del cajero: la autorización de supervisor no es un inicio de sesión. Las constantes viven en `PosController` (`SUPERVISOR_MAX_ATTEMPTS`, `SUPERVISOR_DECAY_SECONDS`).
- **Scope decidido:** el *registro* del intento fallido se dejó para T28 (bitácora de seguridad). Meterlo aquí habría metido una acción sin traducir en la actividad reciente del panel del Administrador (RF-49).
- **Verificación:** objetivo **5 tests, 50 aserciones** (rojo: 2 failed, 3 passed, 47 assertions — los dos fallos demostraban el agujero: la sexta vez con la contraseña correcta autorizaba). Suite completa: **382 tests, 1989 aserciones, 0 fallos** (53,88 s), desde 377/1939; ningún test existente se rompió, así que el freno no estorba al uso legítimo.

## T25 cerrada — parche de dependencias (2026-11-18)

S2 del informe de seguridad. `composer update "symfony/*"` llevó 12 paquetes de Symfony de 7.4.22 a 7.4.23 (sólo parches, sin altas ni bajas) y `composer.lock` quedó actualizado. `composer audit` ya no reporta avisos de Symfony; sobreviven **5 avisos de `laravel/framework`** (CRLF en la regla `email`, URL firmadas temporales, XSS en la página de depuración y salida de `Concurrency`), todos sin parche en 11.x: exigen el salto a 12.x y quedan como decisión aparte. Suite completa: **382 tests, 1989 aserciones, 0 fallos** (52,71 s), sin regresiones.

**Incidente de entorno:** `php artisan package:discover` falló con «directory must be present and writable» porque OneDrive repuso el atributo `ReadOnly` sobre `bootstrap/cache` (un *placeholder* de OneDrive, no un junction). PHP lo interpreta como no escribible aunque `file_put_contents()` sí escriba. Se resolvió con `attrib -R bootstrap\cache`.

## T26 cerrada — cabeceras de seguridad, proxies y HTTPS (2026-11-18)

RNF-27 (spec 003 v1.2), S4 y S5 del informe de seguridad. `SecurityHeadersTest` escrito primero en rojo (**4 failed, 6 assertions**); después, verde en **4 tests, 22 aserciones**. Suite completa: **386 tests, 2011 aserciones, 0 fallos** (48,67 s), desde 382/1989.

El middleware `SecurityHeaders` (global, registrado en `bootstrap/app.php`) emite `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` y una `Content-Security-Policy` con `default-src 'self'`, `script-src 'self' 'nonce-…'`, `frame-ancestors 'none'`, `base-uri 'self'`, `form-action 'self'` y `object-src 'none'`. El nonce se genera por petición y se comparte con las vistas como `$cspNonce`; los dos guiones en línea llevan el nonce y el `onclick` del comprobante pasó a un escuchador. `trustProxies` sólo confía en `TRUSTED_PROXIES` (ninguno por defecto) y `AppServiceProvider` fuerza HTTPS en producción.

Verificado en el navegador a 375 px: cabeceras presentes en `/login`, consola limpia en login, panel, cierre de caja y comprobante, diferencia del arqueo en vivo ($3.000 en contra) y `scrollWidth = 375`. El turno de prueba se borró; la base dev volvió a 0 turnos.

**Compromiso consciente:** `style-src` mantiene `'unsafe-inline'` porque Vite inyecta estilos en desarrollo; no hay atributos `style` en las vistas.

## T27 cerrada — el rol no se asigna en masa (2026-11-18)

RNF-28 (spec 003 v1.3), S3 del informe de seguridad. `MassAssignmentTest` escrito primero en rojo (**3 failed, 1 passed, 7 assertions**); después, verde en **4 tests, 8 aserciones**. Suite completa: **390 tests, 2019 aserciones, 0 fallos** (50,32 s), desde 386/2011.

`role` sale de `User::$fillable`; la única vía de cambio es `User::assignRole()`, que usa `forceFill`. `UserFactory::newModel()` fija el rol de forma explícita antes de guardar, así los 74 llamados existentes (`create(['role' => …])`, `->admin()`, `->quimico()`, etc.) siguen funcionando sin tocarlos; `UserSeeder` también provisiona con `assignRole()`. El test demuestra que `User::create([... 'role' => 'admin'])` deja el rol por defecto `tecnico`, y que la factory sigue respetando el rol pedido.

## T28 cerrada — bitácora de eventos de seguridad (2026-11-18)

RF-84 (spec 003 v1.4), S7 del informe de seguridad. `SecurityAuditLogTest` escrito primero en rojo; después, verde en **5 tests, 9 aserciones**. Suite completa: **395 tests, 2028 aserciones, 0 fallos** (41,70 s), desde 390/2019.

`AuditLog::recordLoginFailure()` y `recordSupervisorFailure()` guardan la cuenta afectada y la acción (`auth.login_failed`, `auth.supervisor_failed`), nunca la contraseña. Se invocan desde `LoginRequest` (fallo de inicio de sesión) y desde la autorización de supervisor de `PosController`. Las acciones se traducen en el panel del Administrador y en la página de auditoría; dos tests existentes que asertaban el código crudo ahora asertan la etiqueta en español. El test comprueba que la contraseña probada no aparece en ningún atributo del registro.

## T29 cerrada — textos de usuario en `lang` (2026-11-18)

S8 del informe de seguridad, P6 de la constitución. `HardcodedTextTest` escrito primero en rojo (**2 failed**); después, verde en **2 tests, 1060 aserciones**. Suite completa: **397 tests, 3088 aserciones, 0 fallos** (41,20 s), desde 395/2028.

Diez claves nuevas en `pos.errors`: `forbidden`, `sale_already_voided`, `void_reason_required`, `invoice_customer_required`, `convention_missing`, `convention_inactive`, `convention_expired`, `document_not_retryable`, `folios_exhausted`, y se reutiliza `cart_empty`. `VoidPolicy` sigue siendo pura: lanza la clave de traducción y `PosController` la traduce al mostrar el error, así su test unitario sin contenedor sigue pasando. El guardián recorre todo `app/` con una lista explícita de los diez textos. Los mensajes visibles no cambian.

## T21 cerrada — feedback del cierre de caja (2026-11-18)

H2 del informe de revisión, RF-59. El panel del Técnico mostraba la etiqueta del botón «Cerrar caja» como mensaje de éxito y nunca la diferencia. Test primero en `CashierPanelTest`; después, la clave `pos.cash_register.status_closed` («Caja cerrada. Diferencia: :amount (:kind).») y el panel compone el mensaje con el signo de la diferencia y el monto en valor absoluto. Verde en **7 tests, 33 aserciones**; suite completa **398 tests, 3094 aserciones, 0 fallos** (43,14 s), desde 397/3088.

## T22 cerrada — los dos conceptos de «anulación» (2026-11-18)

H3 del informe de revisión. Spec 002 en **v1.1** con la decisión **D-11**: «ventas anuladas del día» se mide por `sold_at` y resta del total vendido; «anulaciones registradas hoy» se mide por la fecha del evento y es la lista de supervisión. Enmienda los RF-46 y RF-54 y agrega el caso límite 21.

`SalesDayQuery::registeredVoidsOn()` es la única fuente: el Administrador cuenta con ella y el Químico lista con ella. El panel del Administrador gana el indicador «Anulaciones registradas hoy» y las etiquetas de «Ventas anuladas» pasan a «… del día». Test nuevo en `AdminPanelTest`, comprobado en rojo al quitar el indicador. Suite completa: **399 tests, 3100 aserciones, 0 fallos** (41,55 s), desde 398/3094. Verificado a 375 px con una venta de ayer anulada hoy: Admin «0 / 1» y Químico lista esa anulación; `scrollWidth = 375` y consola limpia.

## Pendientes conocidos

- La skill `sdd` referenciada por los comandos **no existe**; los comandos la invocan igual.
- `specs/004-reportes/` (reportes históricos y exportaciones) no ha sido redactada; el cierre de caja y el arqueo pasaron a la spec 002 (D-6).
- **Resuelto (T20):** `.env.example` declara `America/Santiago`, PostgreSQL y sesión en base, con un guard (`EnvironmentTest`) que falla si el huso de la aplicación no es el del negocio.
- **Resuelto (T24):** la autorización de supervisor tenía intentos ilimitados, así que el tope del 10% era evadible; ahora hay freno por cajero y dirección (RF-83).
- **Abierto (seguridad):** `composer audit` ya no reporta avisos de Symfony (parcheados en T25); quedan cinco de `laravel/framework` 11.57.1 que exigen 12.x. Cabeceras de seguridad, proxies y HTTPS resueltos en T26; `role` fuera de `$fillable` en T27; bitácora de eventos de seguridad en T28; textos de usuario en `lang` en T29.
- **Resuelto (T21):** tras cerrar la caja, el panel del Técnico muestra «Caja cerrada» con la diferencia declarada, no la etiqueta del botón.
- **Resuelto (T22):** «anulación» son dos conceptos y la spec 002 v1.1 (D-11) asigna cada uno a su indicador; Administrador y Químico comparten `SalesDayQuery::registeredVoidsOn()`, así que no pueden discrepar.
- El libro de controlados desborda a 375 px porque su filtro de fechas no envuelve (spec 001).
- `app/Models/Sale.php` declara una `SaleFactory` que no existe; `Sale::factory()` fallaría (T23).
- `welcome.blade.php` sigue en inglés (página por defecto de Laravel, fuera del alcance de la spec).
- `tests/Feature/Pos/FolioConcurrencyTest.php` lanza dos procesos PHP reales (`tests/Fixtures/concurrent-retry.php`): en rojo la carrera no siempre se materializa (es probabilística), pero con el lock de asesoría el verde es determinista. Requiere la BD `farmacia_pos_test` accesible por procesos hijos.
- **F0.1 (2026-11-17):** el deadline de los workers subió de 30 s a `WORKER_DEADLINE_SECONDS = 90` (constante de la clase) y `launchWorkers()` ahora cierra pipes y procesos en un `finally` (`closeWorkers()`), incluso si lanza `RuntimeException`, para no dejar procesos huérfanos. Tiempo real medido: **3,18 s** el test aislado (`php artisan test --filter=FolioConcurrencyTest`, 28 aserciones) y **30,16 s / 24,64 s** las dos corridas completas consecutivas, ambas con 222 tests en verde (931 aserciones) y 0 fallos. No se tocó `DocumentService`, ni la lógica de folios, ni `concurrent-retry.php`, ni se redujeron aserciones.
- **F0.2 (2026-11-17):** se eliminó `tests/Unit/ExampleTest.php` (el `assertTrue(true)` de Laravel) y `tests/Feature/ExampleTest.php` dejó de ser un ejemplo: ahora es `test_root_redirects_to_the_cash_screen`, que verifica que `GET /` redirige a `pos.index` y que esa ruta es `/pos`. Sin archivos nuevos (se reescribió el existente). Suite: **221 tests, 931 aserciones**.
- **F0.3 (2026-11-17):** documentación sincronizada con el repo, sin tocar código. `AGENTS.md` ya no dice «sin código implementado» (T1–T10 y S1–S12 cerradas, S13–S14 pendientes). En `specs/001-pos/tasks.md` la tabla «Estado actual del flujo SDD» separa S6–S12 (cerradas) de S13–S14 (pendientes) y el pie muestra la fecha y la corrida real (221 tests, 931 aserciones). En `specs/001-pos/plan.md` §6.2 la tabla enumera los 24 archivos de test que existen (antes citaba 9 inexistentes: `CashChangeCalculatorTest`, `FolioAllocatorTest`, `SessionDurabilityTest`, `SaleQueryTest`, `DiscountFlowTest`, `PrescriptionFlowTest`, `ConventionManagementTest`, `DocumentRetryTest`, `PermissionTest`; además `StockConcurrencyTest` se llama `ConcurrencyTest`).
- **B1 (2026-11-17):** el catálogo de demo (`ProductSeeder`) trae códigos EAN-13 únicos y con dígito verificador correcto: Paracetamol `7801234567894`, Ibuprofeno `7801234567900`, Amoxicilina `7801234567917`, Clonazepam `7801234567924`. Ojo: los ejemplos del encargo (`…892`, `…908`, `…915`, `…922`) tenían el verificador mal calculado; se corrigieron a `…894`, `…900`, `…917`, `…924` y el test lo comprueba. El test vive en `CartFlowTest::test_demo_catalog_products_have_unique_valid_ean13_barcodes` (valida no nulo, único y dígito de control). No se tocó el esquema: `products.barcode` ya era `string(32) null unique`. La BD demo local no se resembró en ese momento (ver B4).
- **B4 (2026-11-17):** `ProductSeeder` pasó de `create()` a `updateOrCreate()` con el **nombre** como clave, para que las bases creadas antes de B1 reciban el código de barras sin duplicar filas ni exigir `migrate:fresh`. Se eligió el nombre (y no el `barcode`) porque en esa base los productos existentes tienen `barcode` nulo, así que buscar por código habría insertado duplicados. Efecto secundario aceptado: resembrar reactiva `sellable = true`. Test en `CartFlowTest::test_demo_catalog_seeder_can_run_twice_without_duplicating_products` (dos corridas → 4 productos, 4 nombres distintos); la corrida en rojo fallaba con `products_barcode_unique`. La BD demo local se resembró con `php artisan db:seed --class=ProductSeeder`: 4 productos, 4 códigos.
- **B2 (2026-11-17):** escanear un código exacto agrega al carrito y limpia la búsqueda (criterio añadido a RF-1 en B0). La regla vive en `app/Pos/Policy/BarcodeScanPolicy.php`, clase pura que recibe el término y los candidatos como arrays (`id`, `barcode`, `sellable`, `stock`) y devuelve `product_id` o un código de error; `PosController::index()` (`View|RedirectResponse`) la consulta con la fila del código exacto —incluidos los no vendibles— y agrega con cantidad 1 vía `CartService`, o redirige con `pos.errors.product_not_sellable` / `product_out_of_stock`. El formulario sigue siendo GET `?q=` y no se agregó JavaScript ni se capturan excepciones genéricas. 3 tests nuevos en `CartFlowTest`; el test previo `search_finds_product_by_barcode` usaba el código completo, que con B2 ya es un escaneo, así que ahora busca con un código parcial (mismo objetivo: la búsqueda por código lista resultados) en vez de borrarse.
- **B3 (2026-11-17):** el comprobante declara su hoja de 80 mm con `@media print { @page { size: 80mm auto; margin: 4mm; } }`, cuerpo a 11 pt y el ticket a ancho completo. El layout no expone un stack de estilos, así que el `<style>` vive en `pos/receipt.blade.php` (sin tocar `layouts/pos.blade.php`): oculta con CSS el `header` del layout y quita el padding de `main`, además de anular `max-w-2xl` y los paddings del ticket mediante las clases propias `receipt-page` y `receipt-ticket`. Los botones «Nueva venta» e «Imprimir» ya tenían `print:hidden`. No se agregaron utilidades Tailwind nuevas, así que no hace falta `npm run build`. Test en `CheckoutFlowTest::test_receipt_declares_an_80mm_page_for_thermal_printing` (verifica `@page`, `size: 80mm`, `print:hidden` y la leyenda de simulación en la misma página). La verificación visual a 375 px no se pudo hacer: el script de login de desarrollo había desaparecido del temp y la cookie `laravel_session` no se pudo inyectar a mano (MAC inválida); el cambio es sólo de impresión y no altera la pantalla.
- **S13 (RF-37, 2026-11-17):** el filtro por convenio del historial ya estaba implementado en `SaleController::query()` y en el `<select id="convention_id">` de `admin/sales/index.blade.php`; faltaba la cobertura. `AdministrationTest::test_history_filters_by_convention` cobra una venta con convenio activo y otra sin él, filtra por `convention_id` y verifica que sólo aparece la primera, que la segunda no aparece y que el select conserva la opción. Se comprobó que el test detecta la regresión (al neutralizar el `where` falla con 2 ventas en vez de 1). Sin cambios de código de producción; se quitó el import `PaymentMethod` sin uso que Pint marcaba en ese archivo de test.
- **B5 (2026-11-17):** cerrado el hueco de cobertura de B2. `CartFlowTest::test_scanning_a_non_sellable_barcode_does_not_reach_the_cart` escanea el código exacto de un producto con `sellable = false`: redirige a `/pos`, deja el error `barcode` («El producto no está disponible para la venta.») y no crea carrito. La regla ya estaba en `BarcodeScanPolicy`; verificado que el test falla si se neutraliza esa rama (500 por `ProductNotSellable` lanzada desde `Cart::add`).
- **B6 (RNF-11, 2026-11-17):** identidad visual tomada como referencia del proyecto *Pharmacy-management-system* (MusheAbdulHakim) sin copiar su código ni su stack. `layouts/admin.blade.php` pasa a un armazón con barra lateral fija en escritorio (fondo `slate-900`, ítems con icono SVG inline, estado activo con `aria-current` y `bg-white/10`, botón «Ir al POS» al pie) y, en pantallas angostas, encabezado con la marca más una barra de navegación horizontal desplazable; `layouts/pos.blade.php` estrena encabezado propio (marca, título y chip de caja con punto de estado) más una línea de acento esmeralda que no se imprime. `pos/index` gana buscador con icono, filas con hover, carrito pegado al hacer scroll en escritorio y total en panel oscuro; `pos/checkout` repite ese panel; las cinco tablas de administración usan `[&>tr:hover]:bg-slate-50`; el detalle de venta corrige el desborde a 375 px con `min-w-0` en las columnas del grid y `min-w-[30rem]` en la tabla de líneas. Sólo se tocaron vistas: ni rutas, ni controladores, ni reglas, ni datos. Se agregó el supuesto **S-8** a `specs/001-pos/spec.md`. `npm run build` regeneró `public/build` (ignorado por git) y la suite completa sigue en 232 tests.
- **B7 (RF-14/RF-16/RF-18, 2026-11-17):** el tope acumulado de descuentos de línea se revalida al cobrar.
  `CheckoutService::assertLineDiscountsAuthorized()` recorre las líneas con descuento y llama a `DiscountPolicy::evaluateLine()` con el subtotal final y el
  ahorro de las demás líneas; corre en `checkout()` y en `totalsFor()` (antes de validar el efectivo recibido) y no sólo cuando hay convenio o descuento
  manual, que era el hueco: `resolveDiscount()` retornaba temprano. Una línea con supervisor recordado en `pos.line_discounts[...]['authorized_by']` (S12)
  conserva su autorización; las demás, si el carrito cambió y el ahorro vigente supera el 10 %, lanzan `DiscountRejected('line_discount_supervisor')`.
  La rama `line_value` cubre RF-18: un monto autorizado que, tras bajar la cantidad, iguala la base de la línea. `PosController::store()` pasa
  `lineDiscountAuthors()` a `totalsFor()`; `pos/checkout` muestra «El descuento de línea ya no es válido» justo bajo el total, con el detalle del producto
  y un enlace «Volver» a la caja. 3 tests nuevos en `LineDiscountTest`: rechazo por tope, aceptación con autorización vigente y rechazo por línea en cero.
  Suite: **235 tests, 1024 aserciones**.
- **B8 (RF-17/RF-36, 2026-11-17):** la auditoría del descuento de línea conserva el motivo que escribió el cajero. `CheckoutService` pasaba a
  `AuditService::recordLineDiscountAuthorization()` un texto generado por `lineDiscountReason()` («Descuento de línea (20%) autorizado por supervisor.»),
  que pisaba el motivo real; ahora guarda `$line->discount()->reason` y el método generador se eliminó junto con el import `DiscountType`. El tipo y el
  monto no se pierden: ya viajan en `audit_logs.discount_type`/`discount_value` y las pantallas los muestran en sus propias columnas. Test nuevo
  `AuditTest::test_the_line_discount_audit_keeps_the_reason_typed_by_the_cashier` (registro + bitácora muestran «Cliente frecuente»); se ajustó la
  expectativa de `AdministrationTest::test_the_sale_detail_shows_the_line_discount_authorization_in_spanish`, que verificaba el texto viejo, para que
  compruebe el motivo real. Suite: **236 tests, 1030 aserciones**.
- **B9 (RNF-9, 2026-11-17):** el detalle de venta anteponía `#` al texto del folio ausente (`{{ $type }} #{{ $folio ?? no_folio }}`), así que un documento
  pendiente se titulaba «Boleta #sin folio». La tarjeta «Documento» ahora usa `@if ($sale->document->folio !== null)` / `@else`, el mismo patrón del
  historial (`admin/sales/index.blade.php:130-134`): `#folio` cuando existe y sólo el texto cuando no. Test nuevo
  `AdministrationTest::test_the_sale_detail_does_not_prefix_the_missing_folio_with_a_hash`, que compara en crudo (`assertDontSee('#sin folio', false)`).
  Verificado en navegador a 375×812: la tarjeta muestra «Boleta sin folio», `scrollWidth = 375` y sin mensajes de consola. Suite: **237 tests, 1034
  aserciones**.
- **B10 (RNF-2, 2026-11-17):** los layouts ya no dependen del CDN de Tailwind. `layouts/pos.blade.php` y `layouts/admin.blade.php` tenían un `@if
  (file_exists(public_path('build/manifest.json')))` con `@vite` y, en el `@else`, `<script src="https://cdn.tailwindcss.com">`; sin internet o sin build
  la pantalla quedaba a merced de un servicio externo (principio 1 de la constitución). Ahora ambos usan `@vite` directo y `/public/build` dejó de estar
  en `.gitignore`: el build (3 archivos, 90 KB) se versiona, así que un clon funciona sin `npm install`. Consecuencia deliberada: si alguien borra
  `public/build`, `@vite` lanza «Vite manifest not found» y los tests de vista fallan en vez de degradar en silencio. Tests nuevos: uno en `CartFlowTest` y
  otro en `AdministrationTest` que verifican el HTML renderizado con `/build/assets/` y la **fuente** del layout sin `cdn.tailwindcss.com` (el primer
  intento miraba sólo el HTML y pasaba en verde, porque la rama `@else` no se ejecuta cuando el manifest existe). Suite: **246 tests, 1131 aserciones**.
- **S14 (RF-38, RNF-9, 2026-11-17):** el detalle de venta mostraba sólo las autorizaciones registradas sobre la `Sale`, pero S12 registra el descuento de línea sobre la `SaleLine`; el morph `auditLogs()` no las veía. `Sale::authorizationLogs()` une ambos orígenes con su autor y la vista los lista en «Autorizaciones aplicadas» con etiqueta traducida (Descuento de línea, Descuento global sobre el tope, Reutilización de receta), autor, monto, motivo y fecha; una acción desconocida cae en «Otra autorización» para no mostrar inglés. `SaleController::show()` pasa `$authorizations` (ya no carga `auditLogs.user`). Receta, convenio y estados del documento ya estaban resueltos y quedan con test propio. **Trampa de Blade detectada:** la vista tenía `@php($prescription = $sale->prescription)` inline; al añadir un bloque `@php ... @endphp`, Blade emparejó el bloque desde ese `@php` inline y dejó media vista como texto literal (los tests fallaban con «Expected» mostrando Blade sin compilar). Se convirtió en bloque cerrado. **Hallazgo corregido en B8:** `lineDiscountReason()` descartaba el motivo escrito por el cajero y guardaba el texto generado «Descuento de línea (20%) autorizado por supervisor.».
- **S12 (RF-36):** cerrada. La autorización del descuento por línea ya se audita con su autor (`sale.line_discount_authorized`). El pendiente heredado (revalidar el tope acumulado de las líneas al confirmar el cobro) quedó cerrado en B7.
- ~~`layouts/pos.blade.php` carga `@vite` **y** `<script src="https://cdn.tailwindcss.com">`. Sin internet el CDN no responde y la pantalla depende del CSS compilado local: hay que correr `npm run build` después de tocar clases de Tailwind (pasó en S11 con `h-3.5`/`group-open:`). Conviene quitar el CDN en una limpieza posterior.~~ **Resuelto en B10:** ambos layouts cargan sólo `@vite` y `public/build` está versionado.

## Verificación visual (375 px)

Revisada con Chrome DevTools (`viewport 375x812x2,mobile,touch`), servidor `php artisan serve` en `127.0.0.1:8000` y una sesión autenticada de desarrollo (usuario `admin.demo@example.com`, rol admin):

| Pantalla | Resultado |
| --- | --- |
| `pos/index` | Correcta. `scrollWidth = 375`, sin desbordes. |
| `pos/checkout` con aviso de stock | Correcta. Aviso por producto, «Stock actual: 1» y botón «Ajustar cantidades» visibles. |
| `pos/index` modal de supervisor | Correcta. Modal centrado, campos y botones caben a 375 px. |
| `pos/receipt` | Correcta. Folio, IVA, vuelto y botones «Nueva venta»/«Imprimir» sin desbordes. |

### Vuelta 2 (S8, 2026-11-01)

El MCP `brave-devtools` no conectó (`404` tras el handshake con CDP), así que la revisión se hizo por CDP directo contra Brave headless (`--remote-debugging-port=9222`), mismo motor que usa el MCP: `Emulation.setDeviceMetricsOverride` 375×812, cookie de sesión real y captura de pantalla por página. Servidor en `127.0.0.1:8001` (el 8000 lo ocupa Docker).

| Pantalla | Resultado |
| --- | --- |
| `pos/index` con carrito | Correcta. Línea del carrito reacomodada a tarjeta (nombre + total arriba, cantidad/actualizar/quitar abajo) para que no se solapen a 375 px. |
| `pos/checkout` | Correcta. «Cobro», «Total a pagar», «Medio de pago», «Receta médica», «Confirmar venta»; sin textos en inglés. |
| `pos/index` descuento sin motivo | Correcta. Muestra «El motivo es obligatorio.» en el resumen y junto al campo. |
| `pos/receipt` | Correcta. «Boleta electrónica», «Medio de pago», «Documento simulado — sin validez tributaria». |
| `admin/sales` y `admin/sales/{id}` | Correcta. Estados «Confirmada»/«Emitida»; sin `Pending`/`Issued`. |
| `admin/documents` | Correcta. Filtro y celdas con «Pendiente/Emitida/Rechazada». |

Sin errores de consola ni excepciones en ninguna página; `scrollWidth = 375` (las tablas de administración quedan dentro de su contenedor con scroll horizontal).

### Vuelta final (S13–S14, B0–B5, 2026-11-17)

Login de desarrollo reestablecido: sesión inyectada con el id de la cookie real, `SESSION_HTTP_ONLY=false` para permitir lectura desde JavaScript. Servidor `php artisan serve --host=127.0.0.1 --port=8002`.

| Pantalla | Resultado |
| --- | --- |
| `pos?q=7801234567894` (escaneo exacto) | Redirige a `/pos` sin `?q`, carrito con Paracetamol a $990, búsqueda vacía. Sin errores de consola. |
| `pos?q=7801234567900` (escaneo sin stock) | Redirige a `/pos` sin `?q`, muestra «El producto no tiene stock disponible.», carrito intacto. |
| `pos/sales/5/receipt` (con convenio y descuento de línea) | Banmédica, 20% de descuento, leyenda «Documento simulado» y botón «Imprimir». Sin desbordes. |
| `admin/sales/5` (detalle) | Autorizaciones: «Descuento de línea», «Autorizó: Supervisora Demo», «Monto autorizado: $200», estado «Emitida», convenio «Banmédica» y botón «Ver comprobante». |
| `admin/sales?convention_id=1` (historial filtrado) | 1 fila visible, <select> con «Banmédica» preseleccionado. |

Sin errores de consola en ninguna página; `scrollWidth = 375`.

### B6 — identidad visual (2026-11-17)

Verificado en el mismo servidor con la sesión de desarrollo, a 1366×768 y 375×812. La inyección de sesión requiere la cookie **cifrada** con el formato `Crypt::encrypt(CookieValuePrefix::create('laravel_session', key).$id, false)` y una fila de `sessions` con `last_activity` reciente; sin el prefijo HMAC o con la fila vencida, Laravel descarta la cookie y redirige a `/login` (404).

| Pantalla | Resultado |
| --- | --- |
| `pos` (1366 y 375) | Encabezado con marca y chip «Caja 1», buscador con icono, filas con hover, carrito con total en panel oscuro. Sin desbordes ni errores de consola. |
| `pos/checkout` | «Total a pagar» en panel oscuro, mismo lenguaje visual que la caja. |
| `pos/sales/5/receipt` | Sin cambios en pantalla ni en la regla de impresión; el `header` sigue siendo hijo directo de `body`, así que `@media print` lo oculta. |
| `admin/*` (1366) | Barra lateral con Historial/Documentos/Controlados/Convenios/Auditoría, estado activo correcto en las cinco rutas y «Ir al POS» al pie. Sin desbordes. |
| `admin/*` (375) | Barra lateral oculta, encabezado con marca y navegación horizontal desplazable; `scrollWidth = 375` en todas las pantallas. |
| `admin/sales/5` (375) | El grid ya no desborda: la tarjeta mide 343 px y la tabla de líneas se desplaza dentro de su contenedor. |

`npm run build` es obligatorio después de tocar clases: el layout usa `@vite` cuando existe `public/build/manifest.json` y las utilidades nuevas no están en un CSS ya compilado.

### B7 — tope de línea al cobrar (2026-11-17)

Mismo MCP `chrome-devtools` (375×812×2 mobile/touch) contra `php artisan serve --port=8002` reutilizando la sesión de desarrollo (sólo se actualizó `last_activity` de la fila `sessions`). Flujo real por la interfaz: Paracetamol + Ibuprofeno, cantidad 3 del primero, descuento de línea por $300 (6,7 % del carrito) y después cantidad 1 (12,1 %, sobre el tope del 10 %).

| Pantalla | Resultado |
| --- | --- |
| `pos/checkout` con el tope excedido | El cobro se rechaza: no se crea venta ni se descuenta stock. |
| Tarjeta de aviso | «El descuento de línea ya no es válido» con el detalle del producto («El descuento de Paracetamol 500 mg supera el tope del 10 %…») y enlace «Volver» a `/pos`. Se ubicó al inicio del formulario, bajo el total y antes de «Medio de pago». |
| Medidas | `scrollWidth = 375`, sin desbordes y sin mensajes de consola. |

Misma técnica por CDP (375×812) contra `127.0.0.1:8001`, flujo real de caja: agregar producto, abrir el cobro, elegir factura, completar los datos y confirmar.

| Pantalla | Resultado |
| --- | --- |
| `pos/checkout` en boleta | Correcta. «Documento tributario: Boleta/Factura»; «Datos de factura» oculto (`hidden`). |
| `pos/checkout` en factura | Correcta. El bloque «Datos de factura» se muestra con razón social, giro, dirección y comuna; RUT del cliente visible y rotulado «requerido para factura». |
| `pos/checkout` factura sin datos | Correcta. Errores en español («El campo … es obligatorio cuando …»), sin textos en inglés. |
| `pos/receipt` de factura | Correcta. «FACTURA ELECTRÓNICA», folio 50001 y bloque «Datos de factura» con RUT, razón social, giro, dirección y comuna; leyenda «No corresponde a un documento electrónico del SII». |

Sin errores de consola y `scrollWidth = 375` en todas las pantallas.

### B9 — `#` ante el folio ausente (2026-11-17)

MCP `chrome-devtools` (375×812×2 mobile/touch) contra `php artisan serve --port=8002`, reutilizando la sesión admin de desarrollo. Se puso el documento de la venta 5 en `pending` con `folio = null` (script temporal fuera del repo), se abrió `admin/sales/5` y se restauró el documento (`folio = 3`, `issued`) al terminar; la fila `sessions` de la verificación se eliminó.

| Pantalla | Resultado |
| --- | --- |
| `admin/sales/5`, tarjeta «Documento» | «Boleta sin folio», sin el `#` antepuesto. Antes decía «Boleta #sin folio». |
| Medidas | `scrollWidth = 375`, sin desbordes y sin mensajes de consola. |

### B13 — cifras tabulares, foco visible y «—» (2026-11-18)

MCP `chrome-devtools` (375×812×2 mobile/touch) contra `php artisan serve --port=8002`, repoblando la fila `sessions` como en B11 y B12. Para probar el motivo vacío se insertó un `audit_logs` de demo (`sale.void`, `reason = ''`) sobre la venta 5 y se borró al terminar.

| Comprobación | Resultado |
| --- | --- |
| Foco por teclado en `admin/sales` | Tabulando desde el principio: «Ir al POS» → los cinco enlaces del menú → los campos del filtro → «Filtrar» → «Limpiar» → «Ver». Todos con `:focus-visible` activo. |
| Anillo del enlace «Ver» | `box-shadow: rgb(255,255,255) 0 0 0 2px, rgb(16,185,129) 0 0 0 4px` (offset blanco + anillo esmeralda) sobre `border-radius: 4px`. Se ve en la captura como un contorno esmeralda alrededor de «Ver». |
| `tabular-nums` | `font-variant-numeric: tabular-nums` calculado en las celdas de `admin/audit`; en la captura las fechas y los montos quedan alineados. |
| Motivo vacío en `admin/audit` | La fila `sale.void` muestra «—» en MOTIVO (antes quedaba en blanco) y la fila con motivo real lo conserva. |
| `admin/documents`, `admin/conventions`, `admin/sales/5` | `scrollWidth = 375` y consola limpia en las tres. |

### B12 — navegación en configuración y partial (2026-11-18)

MCP `chrome-devtools` contra `php artisan serve --port=8002`, a 375×812×2 mobile/touch y a 1280×900×1, repoblando la fila `sessions` del navegador igual que en B11.

| Pantalla | Resultado |
| --- | --- |
| `admin/sales` a 375 px | Barra lateral oculta (`display: none`) y barra móvil con los cinco enlaces: Historial, Documentos, Controlados, Convenios, Auditoría. «Historial» activo en verde. `scrollWidth = 375`; la barra mide 496 px de contenido y se desplaza en horizontal, que es el comportamiento previsto (`overflow-x-auto`). |
| `admin/audit` a 1280 px | Barra lateral de 256 px con los cinco enlaces y su icono; «Auditoría» con `aria-current="page"`, icono `rgb(52,211,153)` (emerald-400) y fondo resaltado; el resto en `rgb(100,116,139)` (slate-500). `scrollWidth = 1280`. |

Sin mensajes de consola en ninguna de las dos.

### B11 — paginación propia en español (2026-11-18)

MCP `chrome-devtools` (375×812×2 mobile/touch y 900×900×1) contra `php artisan serve --port=8002`. La sesión httpOnly del navegador había caducado y el servidor no tiene ruta `/login` (el `/login` responde 404), así que la verificación se hizo repoblando la **misma fila `sessions`** que el navegador ya tenía: se decodificó su `payload` (`base64(serialize($atributos))`), se le añadió `login_web_<sha1(SessionGuard::class)>` con el id del admin y se reescribió la fila. Inyectar una cookie cifrada a mano desde JS **no** funciona: `document.cookie` no puede escribir `laravel_session` (es httpOnly) y el valor no sobrevive. Para que hubiera paginación se sembraron 25 ventas de demo con `customer_rut = 'B11DEMO'` (29 ventas en total); al terminar se borraron las 25, las dos filas `sessions` de la prueba y los scripts temporales.

| Pantalla | Resultado |
| --- | --- |
| `admin/sales` a 375 px | 20 filas `[data-sale-id]`, tarjeta de paginación de 343 px: «Mostrando 1 a 20 de 29», «Anterior» deshabilitado en gris, «Página 1 de 2», «Siguiente». Números de página ocultos (`display: none` bajo `sm`). `scrollWidth = 375`. |
| `admin/sales?page=2` | «Mostrando 21 a 29 de 29», «Página 2 de 2», 9 filas y «Anterior» como enlace. `scrollWidth = 375`. |
| `admin/sales?status=confirmed` | El enlace a la página 2 conserva el filtro: `?status=confirmed&page=2`. |
| `admin/sales` a 900 px | Los números aparecen (`display: flex`): «1 2», con «1» en `aria-current="page"`. |
| `admin/documents` a 375 px | 4 documentos en la demo: sin paginador, porque `hasPages()` es falso. Sin errores. |

Sin mensajes de consola en ninguna pantalla.

### B10 — sin CDN de Tailwind (2026-11-17)

MCP `chrome-devtools` (375×812×2 mobile/touch) contra `php artisan serve --port=8002`, con la sesión admin de desarrollo. Se comprobó que la página pide **sólo** `/build/assets/app-*.css` y `/build/assets/app-*.js`, sin ninguna petición a `cdn.tailwindcss.com`.

| Pantalla | Resultado |
| --- | --- |
| `admin/sales` a 375 px | Estilos aplicados desde el CSS compilado: barra lateral `rgb(15,23,42)` (slate-900), `scrollWidth = 375`. Sin errores de consola. |
| `pos` a 375 px | Encabezado con marca, buscador con icono, chips «Receta»/«Controlado» y carrito con total: todo desde el CSS compilado. `scrollWidth = 375`. Sin errores de consola. |

### Vuelta 2 (S7, 2026-11-02)

Esta vez con el MCP `chrome-devtools` (`emulate` 375×812×2 mobile/touch, cookie de sesión admin inyectada, `evaluate_script`, `take_screenshot`, `list_console_messages`), contra `127.0.0.1:8001` y la BD demo.

| Pantalla | Resultado |
| --- | --- |
| `admin/sales/2` | Correcta. Tarjeta «Receta asociada» entre «Estado» y «Convenio»: REC-OK-001, Dra. Maria Soto, RUT 12345678-9, «Emitida: 01-11-2026», «Vigente hasta: 01-12-2026». `scrollWidth = 375` y sin mensajes de consola. |

Observación pendiente (preexistente, no es de S7): en esa pantalla los encabezados de la tabla de líneas («Cant.», «Precio», «Descuento», «Subtotal») quedan pegados a 375 px; se puede revisar junto con S14.

### Vuelta 2 (S9, 2026-11-02)

Mismo MCP `chrome-devtools` (375×812×2 mobile/touch) contra `127.0.0.1:8001` y la BD demo. El documento #2 se dejó `pending` sin folio con un script externo para reproducir el fallo de emisión, y el reintento se disparó desde el botón real de la interfaz.

| Pantalla | Resultado |
| --- | --- |
| `pos/sales/2/receipt` (pendiente) | Correcta. «Pendiente de emisión: sin folio asignado», etiqueta «Provisorio», leyenda «Este comprobante es provisorio…» y «Documento simulado — sin validez tributaria»; no imprime ningún folio. `scrollWidth = 375`. |
| `admin/sales` (pendiente) | Correcta. Fila «Boleta sin folio · Pendiente» y botón «Reintentar emisión». `scrollWidth = 375`. |
| `admin/documents` (pendiente) | Correcta. Fila «Pendiente» con «Reintentar emisión». `scrollWidth = 375`. |
| Reintento desde ambas pantallas | Correcto. Aviso «Documento 2 reemitido.», la fila pasa a «Emitida» con folio 2 y el botón desaparece. |
| `pos/sales/2/receipt` (emitido) | Correcto. «Folio 2», sin «Provisorio» ni aviso de pendiente. `scrollWidth = 375`. |

Sin mensajes de consola ni peticiones fallidas. Se corrigió de paso el «#sin folio» del historial: el `#` ahora aparece sólo cuando hay folio.

### Vuelta 2 (S11, 2026-11-03)

Mismo MCP `chrome-devtools` (375×812×2 mobile/touch) contra `127.0.0.1:8001` y la BD demo, con sesión admin inyectada en la propia sesión del navegador (ver «Pendientes conocidos»: no hay `/login`). Antes se ejecutó `npm run build` porque el CSS compilado estaba añejo y el CDN de Tailwind no carga sin internet.

| Pantalla | Resultado |
| --- | --- |
| `pos/index` carrito a 375 px | Correcta. «Descuento de línea» plegado bajo el producto; `scrollWidth = 375` y sin desbordes. |
| `pos/index` descuento 10% | Correcto. Chip emerald «-10% · Promoción de temporada» con «Quitar descuento»; total de la línea $1.341, «Descuentos -$149» y total $1.341. `scrollWidth = 375`. |
| `pos/index` 20% sin supervisor | Correcto. Modal «Autorización de supervisor» (343×369 px) con el mensaje del tope, correo y contraseña; cabe en pantalla. |
| `pos/checkout` con descuento de línea | Correcto. «Descuento de línea: -$149 (Promoción de temporada)» bajo el renglón, pie subtotal/descuentos y «Total a pagar $1.341». `scrollWidth = 375`. |

Sin mensajes de consola. Pendiente heredado: el aviso global del layout repite el mensaje del tope arriba del modal (igual que en S4).

### Vuelta 2 (S12, 2026-11-03)

Mismo MCP `chrome-devtools` (375×812×2 mobile/touch) contra `127.0.0.1:8001`. El registro de auditoría de línea se sembró con un script externo (venta + línea + `audit_logs`) para poder revisar la vista sin repetir todo el flujo de caja.

| Pantalla | Resultado |
| --- | --- |
| `admin/audit` a 375 px | Correcta. Columnas «Tipo / Producto / Monto / Motivo» con `%`, «Ibuprofeno 400 mg», `-$298` y el motivo; la tabla desborda dentro de su contenedor con scroll horizontal (`scrollWidth` de la página = 375). Sin errores de consola. |

Datos demo usados para la verificación eliminados al terminar (venta, línea y registro de auditoría).

## Próximo paso

**T19, T20, T21, T22 y T24 a T29 cerradas.** T19: pantalla de inicio de sesión, autenticación real, freno por fuerza bruta y cierre de sesión con su botón. T20: `.env.example` con el stack real y un guard de zona horaria. T21: la caja cerrada muestra su diferencia. T22: los dos conceptos de «anulación» con una sola fuente. T24: freno a la autorización de supervisor, que hasta ahora permitía probar contraseñas sin límite y evadir el tope del 10%. T25: parche de Symfony 7.4.22 → 7.4.23. T26: cabeceras de seguridad, proxies de confianza y HTTPS. T27: `role` fuera de `$fillable`. T28: bitácora de eventos de seguridad. T29: textos de usuario en `lang`. Suite actual: **399 tests, 3100 aserciones, 0 fallos**.

Cola de mejora (informes de revisión y de seguridad), en `specs/003-auth/tasks.md`:

- **T21.** Mensaje de cierre de caja con la diferencia declarada (hoy muestra la etiqueta del botón «Cerrar caja»).
- **T22.** ✅ Cerrada: D-11 fija los dos conceptos de «anulación» en la spec 002 v1.1; ambos paneles comparten `SalesDayQuery::registeredVoidsOn()`.
- **T23.** Ajustes menores: `SaleFactory` inexistente, filtro `active` en `CartService::load()`, control de pertenencia en `PosController::receipt()`, `active` en `AdjustStockRequest`, inyección de `VoidSaleService` y las nueve etiquetas `RF-nn` que faltan en los tests de la spec 002.
- **T25.** ✅ Cerrada: `composer update` acotado a `symfony/*` (7.4.22 → 7.4.23). Los cinco avisos de `laravel/framework` 11.57.1 exigen el salto a 12.x y quedan como decisión aparte.
- **T26.** ✅ Cerrada: cabeceras de seguridad, proxies de confianza y HTTPS fuera de local (RNF-27).
- **T27.** ✅ Cerrada: `role` fuera de `User::$fillable`, con `assignRole()` como vía explícita (RNF-28).
- **T28.** ✅ Cerrada: bitácora de intentos fallidos de inicio de sesión y de autorización de supervisor (RF-84).
- **T29.** ✅ Cerrada: los diez textos de usuario que quedaban en `app/` pasan a `lang/es/pos.php`, con guardián en `HardcodedTextTest`.

Pendientes de mayor alcance (fuera de la cola actual): `specs/004-reportes/` sin redactar, desborde a 375 px del libro de controlados (spec 001) y los archivos de despliegue (Docker, Nginx, SSL) que `AGENTS.md` declara como stack y que no están en el repositorio.

Pendientes de mayor alcance (fuera de la cola actual): `specs/004-reportes/` sin redactar, desborde a 375 px del libro de controlados (spec 001) y los archivos de despliegue (Docker, Nginx, SSL) que `AGENTS.md` declara como stack y que no están en el repositorio.
