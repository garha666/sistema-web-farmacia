# Tareas 001 — Punto de Venta (POS)

**Spec:** \specs/001-pos/spec.md\ v1.0 (aprobada) · **Plan:** \specs/001-pos/plan.md\
**Metodología:** TDD — test rojo primero, código después, \php artisan test\ en verde.
**Estado:** T1–T10 cerradas; correcciones Vuelta 1 (S1–S5) cerradas; correcciones Vuelta 2 (S6–S11) cerradas, S12–S14 pendientes.

> Nota: la skill \sdd\ no está instalada; el formato sigue lo acordado en \AGENTS.md\ y la constitución.

## Reglas de ejecución

- Una tarea a la vez. No se empieza T(n+1) sin T(n) en verde y marcada.
- Cada tarea deja la suite completa en verde, no sólo sus tests.
- Los nombres de tests y código van en inglés; los textos de interfaz en español.

---

## Tareas originales (T1–T10)

- [x] **T1. Esqueleto Laravel 11 + Docker + dominio de dinero y carrito.** RF-2, RF-3, RF-5, RF-19
  - *Depende de:* —
  - *Hecho cuando:* \docker compose up -d\ levanta app y PostgreSQL 16; \CartTest\ y \MoneyTest\ pasan en verde con cantidad inválida, duplicados, carrito vacío y rechazo de \loat\.

- [x] **T2. Cálculo de precios, IVA incluido y redondeo.** RF-4, RF-12, RF-19
  - *Depende de:* T1
  - *Hecho cuando:* \PriceCalculatorTest\ pasa con 5 montos límite y en todos los casos el total es igual a la suma de líneas menos descuentos.

- [x] **T3. Política de descuentos y convenios de salud.** RF-13, RF-14, RF-15, RF-16, RF-17, RF-18, RF-39, RF-40
  - *Depende de:* T2
  - *Hecho cuando:* \DiscountPolicyTest\ pasa: 10% se aplica sin supervisor, 10,01% lo exige, convenio inactivo se rechaza y ningún descuento deja el total en cero o negativo.

- [x] **T4. Política de recetas y controlados.** RF-7, RF-8, RF-9, RF-10, RF-11
  - *Depende de:* T1
  - *Hecho cuando:* \PrescriptionPolicyTest\ pasa con receta vencida a 31 días (fecha inyectada), datos faltantes, producto controlado y receta reutilizada.

- [x] **T5. Política de stock y faltantes.** RF-3, RF-27, RF-29
  - *Depende de:* T1
  - *Hecho cuando:* \StockPolicyTest\ pasa y \shortages()\ devuelve la diferencia exacta entre solicitado y disponible para cada producto.

- [x] **T6. Pantalla de caja: búsqueda, carrito y totales en vivo.** RF-1, RF-2, RF-3, RF-4, RF-5, RF-6
  - *Depende de:* T1, T2
  - *Hecho cuando:* \CartFlowTest\ pasa y, en prueba manual, recargar el navegador conserva el carrito con líneas y descuentos.

- [x] **T7. Cobro transaccional, folio simulado y comprobante.** RF-20, RF-21, RF-22, RF-23, RF-24, RF-25, RF-26
  - *Depende de:* T2, T3, T4, T5, T6
  - *Hecho cuando:* \CheckoutFlowTest\ pasa: venta confirmada con folio único, stock descontado, comprobante reimprimible y doble envío del mismo carrito no crea dos ventas.

- [x] **T8. Concurrencia real de stock y movimientos.** RF-27, RF-28, RF-29, RF-30, RF-31
  - *Depende de:* T7
  - *Hecho cuando:* el test de dos conexiones a PostgreSQL deja ganar una sola venta de la última unidad, y la suma de movimientos iguala el stock final.

- [x] **T9. Anulación de venta y auditoría.** RF-32, RF-33, RF-34, RF-35, RF-36
  - *Depende de:* T8
  - *Hecho cuando:* \VoidSaleTest\ pasa: la anulación repone stock, genera la reversa simulada, registra autor y motivo, impide una segunda anulación y deja intacta la venta original.

- [x] **T10. Administración: convenios, historial, documentos y permisos.** RF-26, RF-37, RF-38, RF-39, RF-40
  - *Depende de:* T3, T9
  - *Hecho cuando:* \SaleQueryTest\, \ConventionManagementTest\, \DocumentRetryTest\ y \PermissionTest\ pasan; un usuario Técnico recibe 403 al intentar anular o autorizar.

---

## Correcciones Vuelta 1 (S1–S5)

> Origen: veredicto \CAMBIOS NECESARIOS\ de la validación RF por RF (151 tests en verde, 24 RF completos, 14 parciales y 2 sin cobertura).
> Orden recomendado de ejecución: **S1 → S3 → S2 → S4 → S5** (S3 aporta el esquema que S2 consume; el resto conserva la numeración).

- [x] **S1. Auth en rutas /pos y user_id en venta.** RF-22
  - *Depende de:* T7
  - *Hecho cuando:* las rutas \/pos/*\ tienen middleware \uth\; \Sale\ guarda el \user_id\ del cajero autenticado; y un test verifica que una venta anónima se rechaza con 401/403.

- [x] **S2. Flujo de receta en checkout.** RF-8, RF-9, RF-11
  - *Depende de:* T4, T7, S3
  - *Hecho cuando:* \pos/checkout.blade.php\ muestra los campos de receta (número, prescriptor, RUT y fecha); \CheckoutService\ valida vigencia de 30 días y el flag \eused\; la venta guarda \prescription_id\; y un test verifica que una venta controlada sin receta es rechazada.

- [x] **S3. Registro de controlados (modelo, tabla, CSV).** RF-10
  - *Depende de:* T4
  - *Hecho cuando:* existen las migraciones \prescriptions\ y \controlled_register\, el modelo \Prescription\ y \ControlledRegisterController\ con exportación CSV; y un test verifica que el CSV exporta las columnas RUT, folio, fecha, medicamento y cantidad.

- [x] **S4. Descuento manual con tope, supervisor y motivo.** RF-13, RF-16, RF-17
  - *Depende de:* T3, T6
  - *Hecho cuando:* \pos/index.blade.php\ tiene input de descuento manual (% o monto) con botón; un descuento sobre el 10% abre un modal de supervisor con credenciales y motivo obligatorio; el descuento queda guardado en la venta y en el comprobante; y un test verifica el tope, la autorización de supervisor y el motivo obligatorio.

- [x] **S5. Aviso de cambio de stock en checkout.** RF-29
  - *Depende de:* T5, T8
  - *Hecho cuando:* \PosController::checkout\ invoca \StockPolicy::detectChanges\; si hay cambios, la vista muestra «Stock actual: X» y el botón «Ajustar cantidades»; y un test verifica el mensaje y el botón.

---

## Cobertura RF por tarea (actualizada tras Vuelta 1)

| Tarea | RF |
|---|---|
| T1 | 2, 3, 5, 19 |
| T2 | 4, 12, 19 |
| T3 | 13, 14, 15, 16, 17, 18, 39, 40 |
| T4 | 7, 8, 9, 10, 11 |
| T5 | 3, 27, 29 |
| T6 | 1, 2, 3, 4, 5, 6 |
| T7 | 20, 21, 22, 23, 24, 25, 26 |
| T8 | 27, 28, 29, 30, 31 |
| T9 | 32, 33, 34, 35, 36 |
| T10 | 26, 37, 38, 39, 40 |
| S1 | 22 |
| S2 | 8, 9, 11 |
| S3 | 10 |
| S4 | 13, 16, 17 |
| S5 | 29 |

RF-1 a RF-40: **cobertura nominal completa**; huecos reales que corrigen S1–S5.

---

## Correcciones Vuelta 2 (S6–S14)

> Origen: veredicto \CAMBIOS NECESARIOS\ de la validación RF por RF (172 tests en verde, 24 RF completos, 14 parciales, 2 sin cobertura).
> Prioridad: **Alta** (bloquean aprobación) → **Media** (cobertura parcial) → **Baja** (pulido).
> Orden recomendado: **S8 → S6 → S7 → S9 → S10 → S11 → S12 → S13 → S14**.
> S8 (i18n) desbloquea mensajes en español para todo lo demás.

- [x] **S6. Factura (RF-23) — implementar tipo factura con campos S-2.**
  - *Depende de:* T7, S1
  - *Hecho cuando:* CheckoutService crea DocumentType::invoice con RUT, razón social, giro, dirección, comuna; pos/checkout.blade.php tiene selector Boleta/Factura + campos condicionales; vista de comprobante muestra campos de factura; test cubre factura completa con campos S-2.
  - *Cierre (2026-11-02):* migración `invoice_details` (1:1 con `sales` + rango `folio_from`/`folio_to`), modelo `InvoiceDetail`, valor puro `InvoiceData`, `CheckoutService` emite `DocumentType::invoice` con folio 50.001–100.000 y el checkout exige RUT/razón social/giro/dirección/comuna (`required_if`). 5 tests en `InvoiceTest` (183 en total).

- [x] **S7. Detalle de venta muestra receta asociada (RF-38).**
  - *Depende de:* S2
  - *Hecho cuando:* admin/sales/show.blade.php muestra sección "Receta asociada" con número, prescriptor, RUT, fecha, vigencia; test verifica que venta #2 (con receta REC-OK-001) la muestra.
  - *Cierre (2026-11-02):* `Prescription::validUntil()` (emisión + `pos.prescription.validity_days`, día final inclusive), `SaleController::show()` carga `prescription` y la vista muestra la tarjeta «Receta asociada» sólo si la receta existe y sus campos están completos. 2 tests nuevos en `AdministrationTest` (185 en total). Sólo lectura: no se tocó el cobro ni el comprobante.

- [x] **S8. Internacionalización (RNF-4, RNF-9, P6) — lang/es/pos.php y mensajes en español.**
  - *Depende de:* T6, T10
  - *Hecho cuando:* existe lang/es/pos.php con todos los textos de validación y UI; config/app.php locale=es; mensajes de validación y estados (Pending/Issued/Rejected) traducidos; no hay texto hardcodeado en vistas; test verifica que /pos/checkout muestra "El motivo es obligatorio" en español.
  - *Cierre (2026-11-01):* `lang/es/pos.php` + `lang/es/validation.php`, `locale = 'es'`, `label()` traducido en `DocumentStatus`, `SaleStatus`, `PaymentMethod` y `DocumentType`; vistas `pos/*`, `layouts/*` y `admin/*` migradas a `__()`; 6 tests nuevos en `I18nTest` (178 en total).

- [x] **S9. Flujo de emisión fallida + comprobante provisorio (RF-24).**
  - *Depende de:* T7
  - *Hecho cuando:* CheckoutService simula fallo de emisión (mock DocumentService::emit fails); venta queda en estado pending + Document::pending; pos/checkout.blade.php muestra comprobante provisorio con leyenda "Provisorio"; botón "Reintentar emisión" en admin/sales/index; test cubre fallo → provisorio → reintento → issued sin duplicar folio.
  - *Cierre (2026-11-02):* el cobro crea el documento original **pendiente y sin folio dentro de la transacción** y sólo emite después del commit (`CheckoutService::issueDocument()`), así que un fallo del simulador no puede deshacer la venta, el stock ni los movimientos. Punto de simulación sustituible por inyección (`DocumentEmitter` → `SimulatedDocumentEmitter`); sólo se captura `EmissionFailed`, un error de base de datos se propaga. `DocumentService` centraliza `emit()`, `retry()` y `allocateFolio()` (boleta, factura y nota de crédito) con `pg_advisory_xact_lock` por tipo, conservando el folio del documento rechazado; índice único parcial `documents_sale_original_unique` (`sale_id` donde `reversal_of_id IS NULL`). `pos/receipt` muestra «Provisorio», «Pendiente de emisión: sin folio asignado» y nunca inventa folio; historial y documentos ofrecen «Reintentar emisión» y lo ocultan si la venta está anulada (reintentarla responde error de dominio). 11 tests en `DocumentEmissionTest` + 1 de concurrencia real con dos procesos en `FolioConcurrencyTest` (197 en total, 817 aserciones).

- [x] **S10. Mensaje de concurrencia específico (RF-28).**
  - *Depende de:* T8
  - *Hecho cuando:* error de concurrencia muestra "Stock actual: X unidades del producto [nombre]" en lugar de "Sin stock suficiente para el producto 3"; test verifica mensaje exacto.
  - *Cierre (2026-11-02):* `CheckoutService::shortageMessage()` arma el mensaje con `lang/es/pos.php` `errors.concurrent_stock` («Stock actual: :available unidades del producto ":name"»), leyendo la existencia real con la fila ya bloqueada. Sólo el fallo del descuento condicional (`where stock >= qty`) lo emite; el aviso previo de S5 y el rechazo de producto sin stock en `PosController::add` siguen intactos. 2 tests nuevos en `ConcurrencyTest` (mensaje exacto del servicio + mensaje visible en `/pos/checkout` con 0 ventas y 0 movimientos persistidos). 199 tests (828 aserciones).

- [x] **S11. Columna descuento por línea en carrito (RF-4).**
  - *Depende de:* S4, T6
  - *Hecho cuando:* pos/index.blade.php tabla del carrito tiene columna "Descuento" por línea con input %/monto; subtotal de línea = precio × cantidad − descuento; totales recalculan en vivo; test verifica columna, cálculo y recálculo.
  - *Cierre (2026-11-03):* `CartService` persiste el descuento por línea en sesión (mapa `pos.line_discounts`) junto al carrito; rutas `pos.cart.discount` (POST) y `pos.cart.discount.remove` (DELETE); `DiscountPolicy::evaluateLine()` valida motivo, total positivo y tope acumulado del 10% con supervisor; `PriceCalculator::lineDiscounts()` y `evaluate()` descuentan las líneas antes del descuento global (RF-14 acumulado). Vista: chip emerald con `-10% · motivo` y control plegable `<details>` accesible (375 px, sin overflow), modal de supervisor reutilizado con credenciales inválidas reabriendo el modal. Checkout muestra el descuento de línea por renglón y el pie de descuentos. 15 tests en `LineDiscountTest` (214 en total). Verificado en Chrome DevTools a 375 px (carrito, chip, modal, checkout) con `scrollWidth = 375`.

- [ ] **S12. Auditoría de autorizaciones de descuento y receta repetida (RF-36).**
  - *Depende de:* S4, S2
  - *Hecho cuando:* AuditService registra discount.authorization y prescription.reuse con autor, motivo, timestamp; admin/audit/index muestra estos eventos; test verifica registro en audit_logs.

- [ ] **S13. Test de filtro por convenio en historial (RF-37).**
  - *Depende de:* T10
  - *Hecho cuando:* AdministrationTest::test_history_filters_by_convention pasa; admin/sales/index filtra por convenio correctamente.

- [ ] **S14. Detalle de venta completo (RF-38 complemento + RNF-9).**
  - *Depende de:* S7, S8
  - *Hecho cuando:* admin/sales/show.blade.php muestra: receta asociada, autorizaciones (descuento/receta), convenio, estado traducido (Emitida/Pendiente/Rechazada/Anulada); estados no en inglés; test verifica campos presentes.

---

## Estado actual del flujo SDD

| Fase | Estado |
|---|---|
| Constitución | ✅ v1.0 |
| Spec 001-pos | ✅ Aprobada |
| Clarificación | ✅ Cerrada |
| Plan | ✅ Completo |
| Tasks | ✅ Aprobada |
| T1–T10 | ✅ Cerradas |
| S1–S5 (Vuelta 1) | ✅ Cerradas |
| **S6–S14 (Vuelta 2)** | ⬜ **Pendientes de aprobación** |

---

*Actualizado: 2026-11-01 tras veredicto de validación Fase 7 (172 tests, 523 assertions).*
