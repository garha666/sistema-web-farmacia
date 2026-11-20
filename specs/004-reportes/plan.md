# Plan técnico 004 — Reportes y exportaciones

**Spec de referencia:** `specs/004-reportes/spec.md` v1.0 (aprobada)
**Specs relacionadas:** `specs/001-pos/spec.md` v1.1, `specs/002-dashboard/spec.md` v1.1, `specs/003-auth/spec.md` v1.4, `specs/004-pos-async/spec.md` v1.0
**Constitución:** `docs/constitution.md` v1.0
**Stack:** Laravel 11 · Blade + Tailwind · PostgreSQL 16 · guard de sesión `web` · Vite
**Estado:** listo para implementar (TDD), tarea T36

> Nota: la skill `sdd` no está instalada. El plan replica la estructura de `specs/003-auth/plan.md` y las reglas de la constitución, que es la fuente normativa real.

---

## 0. Cumplimiento constitucional (trazabilidad)

| Principio | Cómo lo cumple este plan |
|---|---|
| **P1 Stack fijo** | Sólo Laravel 11, Blade, Tailwind y Vite. Cero frameworks JS, cero ORMs alternos, cero servicios externos. |
| **P2 Spec manda** | Cada ruta, controlador, vista y test declara su RF o RNF de la spec 004-reportes. El código nace de la spec. |
| **P3 Lógica pura, interfaz tonta** | Las agregaciones de ventas, inventario y arqueo viven en servicios/clases puras (o en consultas directas sin lógica de negocio). Los controladores sólo orquestan. |
| **P4 Test primero** | `ReportesTest` se escribe en rojo antes del controlador y la vista; la suite completa queda en verde antes del commit. |
| **P5 Datos protegidos** | Los reportes son de solo lectura; no modifican ventas ni stock. Solo se escribe en `audit_logs` al exportar. |
| **P6 Idioma** | Clases, rutas y tests en inglés; todo texto de interfaz en `lang/es/pos.php` vía `__()`. |

---

## 1. Archivos que se crean o modifican

Todas las rutas son relativas a la raíz del proyecto Laravel.

### 1.1 Se crean

| Archivo | Responsabilidad | RF / RNF |
|---|---|---|
| `app/Http/Controllers/Admin/ReportesController.php` | Genera reportes de ventas, arqueo, descuentos/anulaciones e inventario; responde HTML o CSV. | RF-92 a RF-97, RNF-34 a RNF-39 |
| `app/Services/Reportes/ReportesService.php` | Consulta agregada de ventas, arqueo, descuentos y existencias; fuente de datos de los reportes. | RF-92, RF-94, RF-95, RF-96, RNF-34, RNF-35 |
| `app/Services/Reportes/CsvExporter.php` | Genera CSV UTF-8 con encabezados y filas a partir de un array de datos. | RF-93, RF-97, RNF-38 |
| `resources/views/reportes/ventas.blade.php` | Vista del reporte de ventas con formulario de fechas y tabla de resultados. | RF-92, RF-93, RNF-36, RNF-37 |
| `resources/views/reportes/arqueo.blade.php` | Vista del arqueo de caja por turno. | RF-94, RNF-37 |
| `resources/views/reportes/descuentos-anulaciones.blade.php` | Vista de descuentos y anulaciones por rango de fechas. | RF-95, RNF-37 |
| `resources/views/reportes/inventario.blade.php` | Vista del reporte de inventario para Bodeguero. | RF-96, RNF-37 |
| `tests/Feature/Reportes/ReportesTest.php` | RF-92 a RF-97 y casos límite 1 a 7, escritos primero en rojo. | RF-92 a RF-97 |

### 1.2 Se modifican

| Archivo | Cambio | RF / RNF |
|---|---|---|
| `routes/web.php` | Rutas `reportes.ventas`, `reportes.arqueo`, `reportes.descuentos-anulaciones`, `reportes.inventario` con middleware RBAC por rol. | RF-92 a RF-97, RNF-37 |
| `lang/es/pos.php` | Sección `reportes` con etiquetas de fechas, totales, exportar, errores de validación. | RNF-36 |
| `app/Models/AuditLog.php` | `recordExport()` para registrar exportaciones de reportes (RF-84 de spec 003). | RF-93, RF-97, RNF-39 |
| `specs/002-dashboard/spec.md` §7 | La línea de reportes/exportaciones pasa a remitir a spec 004-reportes (§11). | — |
| `AGENTS.md` | Spec activa y estado del repositorio. | — |
| `MEMORY.md` | Registro de tareas T36 a T40 con conteos reales. | — |

### 1.3 No se toca

- `app/Services/Pos/CartService.php`, `CheckoutService`, `PriceCalculator`: la lógica de ventas ya existe y se reutiliza para los totales de reportes.
- Migraciones y modelos: no hay cambios de esquema.
- `config/auth.php` y middleware `role:*`: se reutilizan sin cambios.

---

## 2. Decisiones de implementación

- **D-23 — Lecturas agregadas, mismo endpoint.** Cada reporte responde HTML o CSV según `Accept` o `?export=1`. No se crea una API paralela.
- **D-25 — Nombre de archivo predecible.** `reporte-{tipo}-{desde}_a_{hasta}.csv`, fechas en español.
- **D-26 — `AuditLog::recordExport()` para auditoría de exportaciones.** Registra usuario, tipo de reporte, rango de fechas y marca de tiempo.
- **D-27 — Consultas directas con Eloquent.** Los reportes usan `DB::table()` o consultas Eloquent con `selectRaw` y `groupBy` para agregaciones; no se crean modelos nuevos salvo los existentes.
- **D-28 — Acceso por rol en el middleware de ruta.** Cada ruta de reporte declara qué roles pueden acceder (admin, quimico, tecnico, bodeguero según corresponda).
- **D-29 — Rango de la auditoría cuando el reporte no lo tiene.** El arqueo (RF-94) no filtra por fechas y la pantalla de descuentos y anulaciones las acepta pero no las exige (T38). La exportación guarda el mismo filtro que la pantalla: si no hay fechas, el registro dice «sin rango» y el archivo es `reporte-{tipo}.csv`. Ventas e inventario siempre llevan rango (caso límite 1).

---

## 3. Contrato de las pantallas (RNF-36, RNF-37)

- `GET /reportes/ventas`:
  - admin → `200` con formulario de fechas y tabla de ventas.
  - otros roles → `403`.
- `GET /reportes/ventas?export=1` o `Accept: text/csv`:
  - admin → `200` con `Content-Type: text/csv` y `Content-Disposition: attachment`.
- `GET /reportes/arqueo`:
  - tecnico → `200` con su propio arqueo.
  - admin → `200` con arqueo de cualquier cajero (filtro opcional por cajero).
  - quimico, bodeguero → `403`.
- `GET /reportes/descuentos-anulaciones`:
  - quimico, admin → `200`.
  - tecnico, bodeguero → `403`.
- `GET /reportes/inventario`:
  - bodeguero, admin → `200`.
  - tecnico, quimico → `403`.
- A 375 px: tablas con scroll horizontal (`overflow-x-auto`), formularios de fecha en una columna, sin desborde.

---

## 4. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Consulta lenta con miles de ventas | Índices existentes en `sold_at` y `user_id`; paginación opcional si excede un umbral (RNF-35). |
| Exportación expone datos sensibles | Cada ruta verifica el rol; el CSV solo contiene lo que la pantalla HTML muestra (RNF-37). |
| Dos exportaciones simultáneas generan conflicto | Cada petición genera su propio stream; no hay estado compartido (caso límite 7). |
| Romper el flujo existente de ventas | Los reportes son solo lectura; no tocan `sales` ni `stock_movements` salvo la auditoría. |

---

## 5. Hitos de implementación (tareas)

| Tarea | RF | Qué entrega |
|---|---|---|
| T36 | RF-92, RF-93 | Reporte de ventas con filtro de fechas y exportación CSV. |
| T37 | RF-94 | Arqueo de caja por turno (técnico ve el suyo, admin ve todos). |
| T38 | RF-95 | Reporte de descuentos y anulaciones (químico y admin). |
| T39 | RF-96 | Reporte de inventario (bodeguero y admin). |
| T40 | RF-97, RF-98, RNF-39 | Auditoría de exportaciones con `AuditLog::recordExport()`; verificación de solo lectura; casos límite. |
