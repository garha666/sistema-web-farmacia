# Tareas 002 — Paneles por rol, inventario y cierre de caja

**Spec:** `specs/002-dashboard/spec.md` v1.0 (aprobada) · **Plan:** `specs/002-dashboard/plan.md`
**Metodología:** TDD — test rojo primero, código después, `php artisan test` en verde.
**Estado:** T11–T18 pendientes.

> Nota: la skill `sdd` no está instalada; el formato sigue lo acordado en `AGENTS.md` y la constitución. La spec 001 v1.1 ya absorbió las enmiendas de la §10 de esta spec.

## Reglas de ejecución

- Una tarea a la vez. No se empieza T(n+1) sin T(n) en verde y marcada.
- Cada tarea deja la suite completa en verde, no sólo sus tests, y entrega el conteo real de Tests y Assertions copiado de la corrida.
- Los nombres de tests, clases, rutas y columnas van en inglés; los textos de interfaz en español, siempre desde `lang/es/pos.php`.
- Lógica de negocio en `app/Pos/**` (pura) y `app/Services/**` (orquestación); los controladores sólo validan y orquestan.
- Un commit por tarea: `feat(pos):`, `test(pos):` o `docs(pos):`, mensaje en inglés sin tildes.
- Nada fuera de la spec 002: lo detectado se anota en el reporte de la tarea, no se arregla de paso.
- Validación visual con Chrome DevTools MCP a 375 px antes de cerrar cada tarea con interfaz.
- `AGENTS.md` y `MEMORY.md` se actualizan al cerrar cada tarea.

---

## Tareas (T11–T18)

- [x] **T11. Base de datos, rol Bodeguero y aterrizaje por rol.** RF-41, RF-42, RF-43, RF-45
  - *Depende de:* —
  - *Alcance:* las cuatro migraciones del plan (§1.2), `CashRegister`, `StockMovement` con `type`/`note`/`user_id`/`resulting_stock`, `Product` con `min_stock`/`active`, `User::ROLE_BODEGUERO`, `CashRegisterFactory`, `UserSeeder` con los cuatro roles, `LandingPolicy` y `DashboardController`.
  - *Hecho cuando:* `LandingPolicyTest` y `RoleLandingTest` pasan; cada uno de los cuatro roles aterriza en su panel, un rol desconocido recibe 403 con el mensaje de acceso, un Bodeguero recibe 403 en `/pos` y en el historial, y un Químico recibe 403 en `/panel/admin`; `migrate:fresh --seed` deja cuatro usuarios y cuatro movimientos históricos con `resulting_stock` correcto.
  - *Cierre:* **cerrada el 2026-11-18.** `LandingPolicyTest` (5 tests) y `RoleLandingTest` (7 tests) escritos en rojo (11 failed, 1 passed) y luego en verde. Suite completa: **259 tests, 1179 aserciones, 0 fallos** (30,17 s); la partida era 246/1131. Migración aplicada sobre la base demo: `movimientos=4 nulos=0 inconsistentes=0`, índice `cash_registers_open_unique` presente, `sales.cash_register_id` y `products.active` en su sitio; `db:seed` deja los cuatro roles. Verificación visual con Chrome DevTools MCP a 375×812: `/panel/admin` muestra el grupo «Administración» con sus 5 secciones y 2 enlaces al POS, `/panel/bodeguero` muestra sólo «Inicio», 0 enlaces al POS, `scrollWidth=375` y sin errores de consola.

- [x] **T12. Turnos de caja: apertura, cierre y arqueo.** RF-58, RF-59
  - *Depende de:* T11
  - *Alcance:* `CashArqueo`, `CashRegisterService`, `CashRegisterController` y sus FormRequest.
  - *Hecho cuando:* `CashArqueoTest` pasa con esperado y diferencia a favor, en contra y en cero; `CashRegisterTest` pasa: una segunda apertura del mismo usuario se rechaza, el cierre sin ventas da diferencia cero, el cierre con anulaciones descuenta el efectivo, el cierre con diferencia distinta de cero se registra, y una anulación posterior al cierre no reescribe el turno.
  - *Cierre:* **cerrada el 2026-11-18.** Tests en rojo primero (22 failed: 6 unitarios y 16 de feature) y luego en verde: **22 tests, 96 aserciones** en el objetivo. Suite completa: **281 tests, 1275 aserciones, 0 fallos** (29,46 s), desde 259/1179. Cubre apertura con monto, responsable y hora local; segunda apertura rechazada por el índice único parcial; cierre sin ventas con diferencia cero; cierre con ventas en efectivo, débito y una anulación en efectivo (esperado 56.000); faltante y sobrante registrados y permitidos; cierre negado a otro cajero (403), a una caja ya cerrada y a un invitado; y anulación posterior al cierre que no reescribe el acta. Verificación visual con Chrome DevTools MCP a 375×812: efectivo esperado `$56.000`, desglose Efectivo `$10.000` y Débito `$6.000`, diferencia en vivo `$3.000 (En contra)` al declarar 53.000, `scrollWidth=375` y sin errores de consola.

- [ ] **T13. Panel del Técnico y bloqueo del cobro.** RF-56, RF-57, RF-60, RF-61, RF-62
  - *Depende de:* T12
  - *Alcance:* `CashierPanelService`, `CashierDashboardController`, `dashboard/tecnico.blade.php`, sus parciales, el filtro de caja abierta en `CheckoutService` y el enlace «Ir a mi panel» del POS.
  - *Hecho cuando:* el panel muestra apertura, ventas del turno y enlace al POS; sin caja abierta muestra el formulario y el aviso; `CheckoutGateTest` pasa: un cobro sin turno no crea venta, no descuenta stock y muestra cómo abrirla; los tests de cobro existentes quedan en verde declarando la precondición con `openCashRegister()`.
  - *Cierre:* pendiente.

- [ ] **T14. Panel del Administrador.** RF-46, RF-47, RF-48, RF-49, RF-50, RF-51
  - *Depende de:* T13
  - *Alcance:* `SalesDayQuery`, `AdminPanelService`, `AdminDashboardController`, `dashboard/admin.blade.php` con sus parciales, y `ConventionController::toggle()` con `ConventionService::activate()`.
  - *Hecho cuando:* `AdminPanelTest` pasa con ventas confirmadas y anuladas del día, desglose por medio de pago, documentos pendientes y rechazados con reintento en línea, alertas de inventario, auditoría reciente, convenios próximos a vencer y vencidos con interruptor, y cortes del día con diferencia; un documento rechazado que se reintenta y vuelve a fallar se informa sin salir del panel; un convenio vencido no se puede activar.
  - *Cierre:* pendiente.

- [ ] **T15. Panel del Químico.** RF-52, RF-53, RF-54, RF-55
  - *Depende de:* T14
  - *Alcance:* `ChemistPanelService`, `ChemistDashboardController`, `dashboard/quimico.blade.php`, enlaces de lectura a convenios y auditoría.
  - *Hecho cuando:* el panel muestra ventas y monto del día con el mismo desglose que el panel del Administrador, lista las autorizaciones de hoy del usuario en sesión (descuento global, descuento de línea y receta reutilizada) con venta, motivo y monto, y las anulaciones de hoy con motivo y autor; no ofrece ningún botón de escritura; un Químico sigue recibiendo 403 en las rutas de edición de convenios.
  - *Cierre:* pendiente.

- [ ] **T16. Catálogo de productos.** RF-68, RF-69, RF-70, RF-71, RF-72
  - *Depende de:* T11
  - *Alcance:* `BarcodePolicy`, `ProductService`, `ProductController` y sus FormRequest, `inventory/products/{index,create,edit}.blade.php`.
  - *Hecho cuando:* `BarcodePolicyTest` pasa (EAN-13 válido, verificador incorrecto, vacío permitido); `ProductCatalogTest` pasa: búsqueda por nombre, principio activo y código; alta con precio cero rechazada; código repetido y código con verificador inválido rechazados con el motivo; cambio de precio sin alterar ventas históricas; quitar vendible impide agregarlo a una venta nueva sin tocar el historial; quitar de catálogo desactiva y lo saca de la búsqueda de la caja; el Bodeguero consulta pero recibe 403 al crear o editar.
  - *Cierre:* pendiente.

- [ ] **T17. Inventario del Bodeguero: panel, prioridad, movimientos y ajuste.** RF-63, RF-64, RF-65, RF-66, RF-67, RF-73, RF-74
  - *Depende de:* T16
  - *Alcance:* `StockAdjustmentPolicy`, `StockAdjustmentService`, `WarehousePanelService`, `WarehouseDashboardController`, `StockAdjustmentController`, `StockMovementController`, `dashboard/bodeguero.blade.php` e `inventory/movements/index.blade.php`.
  - *Hecho cuando:* `StockAdjustmentPolicyTest` pasa; `StockAdjustmentTest` pasa: resumen del inventario, prioridad de reposición ordenada de menor a mayor existencia, movimientos con tipo, motivo, autor y fecha, ajuste de entrada y salida con existencia resultante, salida que dejaría negativo rechazada indicando la existencia disponible, ajuste sin motivo rechazado, y la existencia se reconstruye como inicial más la suma de movimientos; el ajuste se completa sólo con teclado.
  - *Cierre:* pendiente.

- [ ] **T18. Navegación por rol, coherencia de indicadores y cierre.** RF-44, RF-75, RF-76, RF-77, RNF-14 a RNF-22
  - *Depende de:* T15, T17
  - *Alcance:* grupos de `config/navigation.php` por rol, `$navGroup` en `layouts/admin.blade.php` y `partials/nav-links.blade.php`, `ReportConsistencyTest`, `QueryBudgetTest` y la documentación de la medición de RNF-14.
  - *Hecho cuando:* `NavigationTest` pasa en los cuatro roles (cada enlace visible responde 200 y no aparece ninguna sección ajena); `ReportConsistencyTest` confirma que panel, historial y corte coinciden para el mismo día y que el panel de antes y después de medianoche no mezcla días; `QueryBudgetTest` demuestra consultas constantes al crecer las listas; el panel sin datos muestra ceros y mensaje; RNF-14 se mide con 20.000 ventas y 5.000 productos y queda documentado en `MEMORY.md`; las demos manuales de los criterios 4 a 8 de la spec se registran con su resultado; la suite completa queda en verde.
  - *Cierre:* pendiente.

---

## Cobertura RF por tarea

| Tarea | RF |
|---|---|
| T11 | 41, 42, 43, 45 |
| T12 | 58, 59 |
| T13 | 56, 57, 60, 61, 62 |
| T14 | 46, 47, 48, 49, 50, 51 |
| T15 | 52, 53, 54, 55 |
| T16 | 68, 69, 70, 71, 72 |
| T17 | 63, 64, 65, 66, 67, 73, 74 |
| T18 | 44, 75, 76, 77 |

RF-41 a RF-77: **cobertura nominal completa, sin repeticiones y sin huecos.**

| RNF | Dónde se verifica |
|---|---|
| RNF-14 | T18 — medición manual documentada (decisión D12 del plan) |
| RNF-15 | T18 — `QueryBudgetTest` con `DB::listen` |
| RNF-16 | Todas las tareas con interfaz; `I18nTest` y revisión visual |
| RNF-17 | Todas las tareas con interfaz; revisión visual a 375 px |
| RNF-18 | T12 y T17 — recorrido completo sólo con teclado |
| RNF-19 | T11 (base) y T18 (recorrido completo por rol) |
| RNF-20 | T14 y T18 — indicadores desde registros operativos, sin tablas de resumen |
| RNF-21 | T12 (caso 20) y T13 (carrito intacto tras rechazo de cobro) |
| RNF-22 | T14, T16 y T17 — mensajes de error con qué pasó y qué hacer |

## Casos límite y dónde se cubren

| # | Caso | Tarea |
|---|---|---|
| 1 | Usuario sin rol o rol no reconocido | T11 |
| 2 | Bodeguero intentando entrar al POS o a una venta | T11 |
| 3 | Químico intentando abrir el panel del Administrador | T11 |
| 4 | Cierre de caja sin ventas en el turno | T12 |
| 5 | Diferencia a favor y en contra | T12 |
| 6 | Cierre con anulaciones en el turno | T12 |
| 7 | Anulación posterior al cierre | T12 |
| 8 | Segunda apertura con caja abierta | T12 |
| 9 | Cobro sin caja abierta | T13 |
| 10 | Panel antes y después de medianoche | T18 |
| 11 | Producto con existencia cero | T17 |
| 12 | Código repetido, verificador inválido o sin código | T16 |
| 13 | Edición de precio con ventas históricas | T16 |
| 14 | Marcar no vendible un producto en un carrito abierto | T16 |
| 15 | Ajuste que dejaría existencia negativa | T17 |
| 16 | Ajuste sin motivo | T17 |
| 17 | Panel con 20.000 ventas y 5.000 productos | T18 |
| 18 | Documento rechazado que se reintenta y vuelve a fallar | T14 |
| 19 | Convenio vencido que se intenta activar | T14 |
| 20 | Sesión expirada durante el cierre de caja | T12 |

---

## Estado actual del flujo SDD

| Fase | Estado |
|---|---|
| Constitución | ✅ v1.0 |
| Spec 001-pos | ✅ v1.1 con las enmiendas de la 002 aplicadas |
| Spec 002-dashboard | ✅ Aprobada v1.0 |
| Plan 002 | ✅ Completo (`specs/002-dashboard/plan.md`) |
| Tasks 002 | ✅ Definidas (T11–T18) |
| T11–T18 | 🔄 T11 y T12 cerradas · T13–T18 pendientes |
| Verificación visual 375 px | 🔄 T11 y T12 verificadas; el resto se valida al cerrar cada tarea con interfaz |
| Auditoría RF por RF 002 | ⏳ Pendiente |

---

*Actualizado: 2026-11-18. Suite actual: 281 tests, 1275 aserciones, 0 fallos.*
