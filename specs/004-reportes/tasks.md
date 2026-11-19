# Tareas 004 — Reportes y exportaciones

**Spec:** `specs/004-reportes/spec.md` v1.0 (aprobada) · **Plan:** `specs/004-reportes/plan.md`
**Metodología:** TDD — test rojo primero, código después, `php artisan test` en verde.
**Estado:** T36 a T40 definidas, ninguna cerrada.

---

## Tareas (T36–T40)

- [ ] **T36. Reporte de ventas y exportación CSV.** RF-92, RF-93 (spec 004-reportes)
  - *Depende de:* —
  - *Alcance:* `ReportesController::ventas()`, `ReportesService::ventasPorRango()`, `CsvExporter`, vista `reportes.ventas`, rutas con middleware `role:admin`, textos en `lang/es/pos.php`.
  - *Hecho cuando:* el administrador ve un reporte de ventas por rango de fechas con totales y listado; puede exportarlo a CSV; otros roles reciben 403.
  - *Cierre:* test escrito primero en rojo; suite completa en verde; verificación a 375 px sin desbordes.

- [x] **T37. Arqueo de caja por turno.** RF-94 (spec 004-reportes)
  - *Depende de:* T36
  - *Alcance:* `ReportesController::arqueo()`, `ReportesService::arqueoPorTurno()`, vista `reportes.arqueo`, middleware `role:tecnico,admin`. Técnico ve el suyo; admin ve el de cualquier cajero.
  - *Hecho cuando:* un cajero ve su arqueo con ventas, medios de pago y diferencia; el admin ve el de cualquier cajero; turno cerrado muestra efectivo esperado, declarado y diferencia.
  - *Cierre:* **cerrada el 2026-11-19.** Test escrito primero en rojo (`ArqueoTest`: **4 failed, 0 passed**), verde en **4 tests, 12 aserciones**. Se corrigió un bug donde `$request->integer('cashier_id')` devolvía 0 en lugar de null para admin sin filtro; se usa `filled()` + cast a int. `SaleStatus` y `PaymentMethod` se comparan como enum (no como string) porque están casteados en el modelo Sale. Suite completa: **441 tests, 3305 aserciones, 0 fallos** (48,30 s), desde 437/3293. Pint en verde.

- [ ] **T38. Reporte de descuentos y anulaciones.** RF-95 (spec 004-reportes)
  - *Depende de:* T36
  - *Alcance:* `ReportesController::descuentosAnulaciones()`, vista `reportes.descuentos-anulaciones`, middleware `role:quimico,admin`. Químico ve sus autorizaciones; admin ve todas.
  - *Hecho cuando:* se listan descuentos globales, de línea y reutilización de receta con autorizador, monto y fecha.
  - *Cierre:* test escrito primero en rojo; suite completa en verde.

- [ ] **T39. Reporte de inventario.** RF-96 (spec 004-reportes)
  - *Depende de:* T36
  - *Alcance:* `ReportesController::inventario()`, vista `reportes.inventario`, middleware `role:bodeguero,admin`. Productos bajo mínimo, sin stock y movimientos de un rango.
  - *Hecho cuando:* el bodeguero ve el reporte de inventario con valorización y movimientos; solo lectura.
  - *Cierre:* test escrito primero en rojo; suite completa en verde; verificación a 375 px.

- [ ] **T40. Auditoría de exportaciones y solo lectura.** RF-97, RF-98, RNF-39 (spec 004-reportes)
  - *Depende de:* T36, T37, T38, T39
  - *Alcance:* `AuditLog::recordExport()` para registrar cada exportación; verificar que ningún reporte modifica ventas ni stock; casos límite (rango vacío, from > to, sin datos, exportar vacío).
  - *Hecho cuando:* cada exportación queda en auditoría con usuario, tipo y fechas; ningún reporte escribe en tablas transaccionales.
  - *Cierre:* test escrito primero en rojo; suite completa en verde.

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
| T36–T40 | ⏳ Pendientes · nada implementado aún |
| Verificación visual 375 px | ⏳ Pendiente (se valida al cerrar cada tarea con interfaz) |

---

