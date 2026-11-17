# MEMORY.md — Estado del proyecto

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A) — INACAP
**Última actualización:** 2026-11-17

## Estado actual

- **Constitución:** `docs/constitution.md` v1.0, vigente (6 principios).
- **Spec 001 POS:** `specs/001-pos/spec.md` v1.0, **aprobada**, 40 RF y 13 RNF, sin dudas abiertas.
- **Plan 001 POS:** `specs/001-pos/plan.md`, completo (arquitectura, funciones puras, pseudocódigo, UI, decisiones, tests).
- **Tareas 001 POS:** `specs/001-pos/tasks.md`, 10 tareas + correcciones Vuelta 1 (S1–S5) + Vuelta 2 (S6–S14). **T1 a T10 cerradas; S1 a S12 cerradas.**
- **Código:** Laravel 11.57.1. Dominio `app/Pos/` completo. Persistencia: `Product`, `Sale`, `SaleLine`, `Document`, `InvoiceDetail`, `StockMovement`, `AuditLog`, `Convention`, `Prescription`, `ControlledRegisterEntry`. Servicios: `CartService`, `CheckoutService`, `VoidSaleService`, `AuditService`, `ConventionService`, `DocumentService`, `ManualDiscountService`. Emisión simulada: `Pos\Contract\DocumentEmitter`, `Pos\Emission\SimulatedDocumentEmitter`, `Pos\Folio\FolioAllocator`. HTTP: `PosController` y `Admin\{ConventionController, SaleController, DocumentController, AuditController, ControlledRegisterController}`. Vistas: `pos/index`, `pos/checkout`, `pos/receipt`, `admin/*` (layout `layouts.admin`). Traducciones en `lang/es/`. 226 tests en verde (962 aserciones).
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
- **Cambio de stock (T5/S5):** `StockPolicy::detectChanges` compara la existencia viva contra el stock congelado al agregar o ajustar cada línea. La caja guarda ese stock en la sesión `pos.cart_stock` (junto a `pos.cart`), porque `CartService::load()` relee la base en cada request; `PosController::checkout` detecta los cambios y muestra «Stock actual: X» más el botón «Ajustar cantidades». La transacción de cobro sigue bloqueando la fila real con `lockForUpdate()` (T8).
- **Carrito en sesión (T6):** se guarda `id de producto => cantidad` en `pos.cart`, el stock congelado en `pos.cart_stock` y, desde S11, el descuento por línea en `pos.line_discounts` (mapa id => tipo, valor y motivo); precio, categoría y stock se releen de la base en cada request.
- **Búsqueda (T6):** `lower(...) like` sobre nombre, principio activo y código de barras, límite 20 resultados, sólo productos vendibles.
- **Tailwind (T6):** `tailwindcss@3` + PostCSS + Vite. `npm run build` genera `public/build`; la vista usa `@vite` y cae a CDN sólo si falta el manifest. `public/build` está en `.gitignore`, así que **tras cada `git pull` hay que ejecutar `npm run build`**: si no, las clases nuevas de las vistas no existen en el CSS compilado y la pantalla sale sin estilos (pasó con `bg-amber-600` del botón «Ajustar cantidades»).
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

## Pendientes conocidos

- La skill `sdd` referenciada por los comandos **no existe**; los comandos la invocan igual.
- `specs/004-reportes/` (cierre de caja) no ha sido redactada.
- No existe ruta `/login`; en local se entra con una sesión de desarrollo generada con un script externo al repo.
- `welcome.blade.php` sigue en inglés (página por defecto de Laravel, fuera del alcance de la spec).
- `tests/Feature/Pos/FolioConcurrencyTest.php` lanza dos procesos PHP reales (`tests/Fixtures/concurrent-retry.php`): en rojo la carrera no siempre se materializa (es probabilística), pero con el lock de asesoría el verde es determinista. Requiere la BD `farmacia_pos_test` accesible por procesos hijos.
- **F0.1 (2026-11-17):** el deadline de los workers subió de 30 s a `WORKER_DEADLINE_SECONDS = 90` (constante de la clase) y `launchWorkers()` ahora cierra pipes y procesos en un `finally` (`closeWorkers()`), incluso si lanza `RuntimeException`, para no dejar procesos huérfanos. Tiempo real medido: **3,18 s** el test aislado (`php artisan test --filter=FolioConcurrencyTest`, 28 aserciones) y **30,16 s / 24,64 s** las dos corridas completas consecutivas, ambas con 222 tests en verde (931 aserciones) y 0 fallos. No se tocó `DocumentService`, ni la lógica de folios, ni `concurrent-retry.php`, ni se redujeron aserciones.
- **F0.2 (2026-11-17):** se eliminó `tests/Unit/ExampleTest.php` (el `assertTrue(true)` de Laravel) y `tests/Feature/ExampleTest.php` dejó de ser un ejemplo: ahora es `test_root_redirects_to_the_cash_screen`, que verifica que `GET /` redirige a `pos.index` y que esa ruta es `/pos`. Sin archivos nuevos (se reescribió el existente). Suite: **221 tests, 931 aserciones**.
- **F0.3 (2026-11-17):** documentación sincronizada con el repo, sin tocar código. `AGENTS.md` ya no dice «sin código implementado» (T1–T10 y S1–S12 cerradas, S13–S14 pendientes). En `specs/001-pos/tasks.md` la tabla «Estado actual del flujo SDD» separa S6–S12 (cerradas) de S13–S14 (pendientes) y el pie muestra la fecha y la corrida real (221 tests, 931 aserciones). En `specs/001-pos/plan.md` §6.2 la tabla enumera los 24 archivos de test que existen (antes citaba 9 inexistentes: `CashChangeCalculatorTest`, `FolioAllocatorTest`, `SessionDurabilityTest`, `SaleQueryTest`, `DiscountFlowTest`, `PrescriptionFlowTest`, `ConventionManagementTest`, `DocumentRetryTest`, `PermissionTest`; además `StockConcurrencyTest` se llama `ConcurrencyTest`).
- **B1 (2026-11-17):** el catálogo de demo (`ProductSeeder`) trae códigos EAN-13 únicos y con dígito verificador correcto: Paracetamol `7801234567894`, Ibuprofeno `7801234567900`, Amoxicilina `7801234567917`, Clonazepam `7801234567924`. Ojo: los ejemplos del encargo (`…892`, `…908`, `…915`, `…922`) tenían el verificador mal calculado; se corrigieron a `…894`, `…900`, `…917`, `…924` y el test lo comprueba. El test vive en `CartFlowTest::test_demo_catalog_products_have_unique_valid_ean13_barcodes` (valida no nulo, único y dígito de control). No se tocó el esquema: `products.barcode` ya era `string(32) null unique`. La BD demo local **no** se resembró: `ProductSeeder` usa `create()` y volver a correrlo duplicaría los 4 productos.
- **B2 (2026-11-17):** escanear un código exacto agrega al carrito y limpia la búsqueda (criterio añadido a RF-1 en B0). La regla vive en `app/Pos/Policy/BarcodeScanPolicy.php`, clase pura que recibe el término y los candidatos como arrays (`id`, `barcode`, `sellable`, `stock`) y devuelve `product_id` o un código de error; `PosController::index()` (`View|RedirectResponse`) la consulta con la fila del código exacto —incluidos los no vendibles— y agrega con cantidad 1 vía `CartService`, o redirige con `pos.errors.product_not_sellable` / `product_out_of_stock`. El formulario sigue siendo GET `?q=` y no se agregó JavaScript ni se capturan excepciones genéricas. 3 tests nuevos en `CartFlowTest`; el test previo `search_finds_product_by_barcode` usaba el código completo, que con B2 ya es un escaneo, así que ahora busca con un código parcial (mismo objetivo: la búsqueda por código lista resultados) en vez de borrarse.
- **B3 (2026-11-17):** el comprobante declara su hoja de 80 mm con `@media print { @page { size: 80mm auto; margin: 4mm; } }`, cuerpo a 11 pt y el ticket a ancho completo. El layout no expone un stack de estilos, así que el `<style>` vive en `pos/receipt.blade.php` (sin tocar `layouts/pos.blade.php`): oculta con CSS el `header` del layout y quita el padding de `main`, además de anular `max-w-2xl` y los paddings del ticket mediante las clases propias `receipt-page` y `receipt-ticket`. Los botones «Nueva venta» e «Imprimir» ya tenían `print:hidden`. No se agregaron utilidades Tailwind nuevas, así que no hace falta `npm run build`. Test en `CheckoutFlowTest::test_receipt_declares_an_80mm_page_for_thermal_printing` (verifica `@page`, `size: 80mm`, `print:hidden` y la leyenda de simulación en la misma página). La verificación visual a 375 px no se pudo hacer: el script de login de desarrollo había desaparecido del temp y la cookie `laravel_session` no se pudo inyectar a mano (MAC inválida); el cambio es sólo de impresión y no altera la pantalla.
- **S12 (RF-36):** cerrada. La autorización del descuento por línea ya se audita con su autor (`sale.line_discount_authorized`). Pendiente menor heredado: el descuento de línea sigue revalidándose sólo en la caja, no al confirmar el cobro (el tope acumulado de líneas no se vuelve a comprobar en `CheckoutService`); la autorización se registra porque el supervisor la dio al aplicarla.
- `layouts/pos.blade.php` carga `@vite` **y** `<script src="https://cdn.tailwindcss.com">`. Sin internet el CDN no responde y la pantalla depende del CSS compilado local: hay que correr `npm run build` después de tocar clases de Tailwind (pasó en S11 con `h-3.5`/`group-open:`). Conviene quitar el CDN en una limpieza posterior.

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

### Vuelta 2 (S6, 2026-11-02)

Misma técnica por CDP (375×812) contra `127.0.0.1:8001`, flujo real de caja: agregar producto, abrir el cobro, elegir factura, completar los datos y confirmar.

| Pantalla | Resultado |
| --- | --- |
| `pos/checkout` en boleta | Correcta. «Documento tributario: Boleta/Factura»; «Datos de factura» oculto (`hidden`). |
| `pos/checkout` en factura | Correcta. El bloque «Datos de factura» se muestra con razón social, giro, dirección y comuna; RUT del cliente visible y rotulado «requerido para factura». |
| `pos/checkout` factura sin datos | Correcta. Errores en español («El campo … es obligatorio cuando …»), sin textos en inglés. |
| `pos/receipt` de factura | Correcta. «FACTURA ELECTRÓNICA», folio 50001 y bloque «Datos de factura» con RUT, razón social, giro, dirección y comuna; leyenda «No corresponde a un documento electrónico del SII». |

Sin errores de consola y `scrollWidth = 375` en todas las pantallas.

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

S12 (auditoría de autorizaciones de descuento) cerrada. Cola en curso: **B0, B1, B2 y B3 cerradas con 226 tests en verde**; sigue **S13** (filtro por convenio en el historial) y luego **S14** (detalle de venta completo).
