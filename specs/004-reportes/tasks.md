# Tareas 004 — Reportes y exportaciones

**Spec:** `specs/004-reportes/spec.md` v1.0 (aprobada) · **Plan:** `specs/004-reportes/plan.md`
**Metodología:** TDD — test rojo primero, código después, `php artisan test` en verde.
**Estado:** T36 a T39 cerradas; T40 pendiente.

---

## Tareas (T36–T40)

- [x] **T36. Reporte de ventas y exportación CSV.** RF-92, RF-93 (spec 004-reportes)
  - *Depende de:* —
  - *Alcance:* `ReportesController::ventas()`, `ReportesService::ventasPorRango()`, `CsvExporter`, vista `reportes.ventas`, rutas con middleware `role:admin`, textos en `lang/es/pos.php`.
  - *Hecho cuando:* el administrador ve un reporte de ventas por rango de fechas con totales y listado; puede exportarlo a CSV; otros roles reciben 403.
  - *Cierre:* **cerrada el 2026-11-19.** Test escrito primero en rojo (`ReportesTest`: **6 failed** de 14 con los casos nuevos), verde en **14 tests**. Correcciones sobre la primera entrega: el CSV exportaba sólo la página 1 (20 filas) y ahora trae todo el rango (`ReportesService::ventasParaExportar`); el total del CSV es numérico sin `$` ni separador de miles (RNF-38); también se exporta con `Accept: text/csv` (D-24); los totales, transacciones y desglose cuentan sólo ventas confirmadas, como el panel del día (D-11), y el listado muestra todas con su estado; el desglose usa la etiqueta en español del medio de pago; sin fechas el reporte abre con hoy y `from > to` se informa como error en el formulario; la paginación conserva el rango; se quitó una ruta `reportes.inventario` duplicada. Suite completa: **459 tests, 3350 aserciones, 0 fallos** (55 s), desde 441/3305. Pint en verde. **Verificación a 375 px pendiente:** la base de desarrollo no tiene usuarios sembrados.

- [x] **T37. Arqueo de caja por turno.** RF-94 (spec 004-reportes)
  - *Depende de:* T36
  - *Alcance:* `ReportesController::arqueo()`, `ReportesService::arqueoPorTurno()`, vista `reportes.arqueo`, middleware `role:tecnico,admin`. Técnico ve el suyo; admin ve el de cualquier cajero.
  - *Hecho cuando:* un cajero ve su arqueo con ventas, medios de pago y diferencia; el admin ve el de cualquier cajero; turno cerrado muestra efectivo esperado, declarado y diferencia.
  - *Cierre:* **cerrada el 2026-11-19.** Test escrito primero en rojo (`ArqueoTest`: **4 failed, 0 passed**), verde en **4 tests, 12 aserciones**. Se corrigió un bug donde `$request->integer('cashier_id')` devolvía 0 en lugar de null para admin sin filtro; se usa `filled()` + cast a int. `SaleStatus` y `PaymentMethod` se comparan como enum (no como string) porque están casteados en el modelo Sale. Suite completa: **441 tests, 3305 aserciones, 0 fallos** (48,30 s), desde 437/3293. Pint en verde.

- [x] **T38. Reporte de descuentos y anulaciones.** RF-95 (spec 004-reportes)
  - *Depende de:* T36
  - *Alcance:* `ReportesController::descuentosAnulaciones()`, `ReportesService::descuentosAnulacionesPorRango()`, vista `reportes.descuentos-anulaciones`, middleware `role:quimico,admin`. Químico ve sus autorizaciones; admin ve todas.
  - *Hecho cuando:* se listan descuentos globales, de línea y reutilización de receta con autorizador, monto y fecha.
  - *Cierre:* **cerrada el 2026-11-19.** Test escrito primero en rojo (`DiscountsVoidsReportTest`: **4 failed, 0 passed**), verde en **4 tests, 11 aserciones**. Suite completa: **441 tests, 3305 aserciones, 0 fallos** (48,30 s), desde 437/3293. Pint en verde.

- [x] **T39. Reporte de inventario.** RF-96 (spec 004-reportes)
  - *Depende de:* T36
  - *Alcance:* `ReportesController::inventario()`, vista `reportes.inventario`, middleware `role:bodeguero,admin`. Productos bajo mínimo, sin stock y movimientos de un rango.
  - *Hecho cuando:* el bodeguero ve el reporte de inventario con valorización y movimientos; solo lectura.
  - *Cierre:* **cerrada el 2026-11-19.** Test escrito primero en rojo (`InventoryReportTest`: **10 failed** de 16 contra la primera entrega), verde en **16 tests**. Correcciones sobre la primera entrega: la vista imprimía claves de traducción sin resolver (faltaba la sección `pos.reportes.inventario` en `lang/es/pos.php`); el reporte listaba todos los productos y una consulta por producto, y ahora muestra sólo el catálogo vigente en listas separadas «bajo el mínimo» (existencia ≤ mínimo, S-11, sin repetir lo agotado) y «sin stock», con los mismos scopes del panel; agrega la valorización (existencia × precio de venta, S-20) y los movimientos del rango en una sola consulta paginada con producto, tipo, cantidad con signo, existencia resultante, motivo y autor; el rango sigue la regla de las demás pantallas (sin fechas, hoy; `from > to` es error del formulario; día final completo, RNF-36) con un único `dateRange()` compartido con el reporte de ventas; se reemplazó `inventarioPorRango()` por `inventario()`. Sólo lectura: sin formularios de edición y sin escrituras (RF-98). **Verificación a 375 px hecha** con Chrome DevTools (sin desborde, sin errores de consola, valorización $94.570 coincide con el cálculo manual; datos de prueba retirados). La exportación CSV del inventario (RNF-38) y su auditoría quedan en T40. Suite completa: **471 tests, 3383 aserciones, 0 fallos**, desde 459/3350. Pint en verde en los archivos tocados.

- [x] **T40. Auditoría de exportaciones y solo lectura.** RF-97, RF-98, RNF-39 (spec 004-reportes)
  - *Depende de:* T36, T37, T38, T39
  - *Alcance:* `AuditLog::recordExport()` para registrar cada exportación; verificar que ningún reporte modifica ventas ni stock; casos límite (rango vacío, from > to, sin datos, exportar vacío).
  - *Hecho cuando:* cada exportación queda en auditoría con usuario, tipo y fechas; ningún reporte escribe en tablas transaccionales.
  - *Cierre:* **cerrada el 2026-11-19.** Tests escritos primero en rojo (los casos nuevos de `ReportesTest` fallaron antes de implementar), verde en **44 tests, 171 aserciones** del archivo. `AuditLog::recordExport()` registra usuario, tipo, rango y marca de tiempo (`created_at`) en `reason`; se escribe después de armar el CSV. Los cuatro reportes se exportan (`?export=1` o `Accept: text/csv`) con el mismo permiso de lectura de su pantalla y con el nombre `reporte-{tipo}-{desde}_a_{hasta}.csv` (D-25, D-29); se agregaron el CSV de arqueo, descuentos/anulaciones e inventario (este con todos los movimientos del rango, sin paginar) y sus botones de exportación. Casos límite 2, 4, 5, 6 y 7 con test; el 1 y el 3 ya estaban cubiertos por T36/T39. Solo lectura comprobada con instantánea de `sales`, `sale_lines`, `stock_movements`, `products` y `cash_registers` antes y después de ver y exportar. Etiqueta «Exportó un reporte» en la bitácora. Suite completa: **501 tests, 3519 aserciones, 0 fallos** (62 s), desde 459/3350. Pint en verde en los archivos tocados. **Verificación a 375 px pendiente.**

---

## Cobertura RF por tarea

| Tarea | RF | RNF |
|---|---|---|
| T36 | 92, 93 | 34, 35, 36, 37, 38 |
| T37 | 94 | 36, 37 |
| T38 | 95 | 36, 37 |
| T39 | 96 | 36, 37, 38 |
| T40 | 97, 98 | 34, 39 |

---

## Estado actual del flujo SDD

| Fase | Estado |
|---|---|
| Constitución | ✅ v1.0 |
| Spec 001-pos | ✅ v1.1 |
| Spec 002-dashboard | ✅ v1.1 |
| Spec 003-auth | ✅ v1.4 |
| Spec 004-pos-async | ✅ v1.0 |
| Spec 004-reportes | ✅ v1.0 (aprobada) |
| Plan 004-reportes | ✅ Completo (`specs/004-reportes/plan.md`) |
| Tasks 004-reportes | ✅ Definidas (T36–T40) |
| T36–T39 | ✅ Cerradas |
| T40 | ⏳ Pendiente |
| Verificación visual 375 px | ⏳ Pendiente (se valida al cerrar cada tarea con interfaz) |

---

