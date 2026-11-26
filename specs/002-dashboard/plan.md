# Plan técnico 002 — Paneles por rol, inventario y cierre de caja

**Spec de referencia:** `specs/002-dashboard/spec.md` v1.0 (aprobada)
**Specs relacionadas:** `specs/001-pos/spec.md` v1.1 (las enmiendas de la §10 de la 002 ya están aplicadas: §2, §7, D-3, RF-20 y RF-22)
**Constitución:** `docs/constitution.md` v1.0
**Stack:** Laravel 11 · Blade + Tailwind · PostgreSQL 16 · Docker
**Estado:** listo para implementar por fases (TDD), tareas T11–T18

> Nota: la skill `sdd` no está instalada en este entorno. El plan replica la estructura de `specs/001-pos/plan.md` y las reglas de la constitución, que es la fuente normativa real.

---

## 0. Cumplimiento constitucional (trazabilidad)

| Principio | Cómo lo cumple este plan |
|---|---|
| **P1 Stack fijo** | Sólo Laravel 11, Blade, Tailwind y PostgreSQL 16. Los paneles se renderizan en servidor; no hay framework JS, gráficos de terceros ni servicios externos. |
| **P2 Spec manda** | Cada migración, clase, ruta, vista y test declara el RF o RNF que implementa. La spec 001 ya fue enmendada (v1.1) para que el cobro exija caja abierta: ninguna spec se contradice. |
| **P3 Lógica pura** | Arqueo, validación EAN-13, ajuste de stock y aterrizaje por rol viven en `app/Pos/**` como funciones puras. Consultas, transacciones y HTTP quedan en `app/Services/**` y controladores. |
| **P4 Test primero** | T11–T18 con test rojo visible antes de cada implementación. La cobertura RF por RF está en la sección 6 y en `specs/002-dashboard/tasks.md`. |
| **P5 Datos protegidos** | Fechas en `America/Santiago` desde `config/pos.php`; el turno cerrado es inmutable (snapshot del arqueo); sin borrado de productos ni de movimientos; migraciones aditivas y reversibles. |
| **P6 Idioma** | Clases, columnas, rutas y tests en inglés; todo texto de interfaz en `lang/es/pos.php` vía `__()`. Cero literales en Blade. |

---

## 1. Archivos que se crean o modifican

Todas las rutas son relativas a la raíz del proyecto Laravel.

### 1.1 Dominio puro — `app/Pos/` (RF-41, RF-43, RF-45, RF-59, RF-65, RF-71, RF-73, RF-74)

Nada aquí conoce HTTP, sesión, base de datos ni Blade.

| Archivo | Responsabilidad | RF |
|---|---|---|
| `app/Pos/Enum/StockMovementType.php` | `sale`, `void`, `in`, `out`; signo del movimiento y etiqueta traducida. | RF-65, RF-73, RF-74 |
| `app/Pos/Enum/CashDifference.php` | `balanced`, `over`, `short`: sentido de la diferencia de caja. | RF-59 |
| `app/Pos/Policy/LandingPolicy.php` | Rol → panel; rol desconocido o ausente → `null`; quién puede abrir qué panel. | RF-41, RF-43, RF-45 |
| `app/Pos/Policy/BarcodePolicy.php` | EAN-13: 13 dígitos, dígito verificador correcto, vacío permitido. | RF-71 |
| `app/Pos/Policy/StockAdjustmentPolicy.php` | Cantidad entera positiva, motivo obligatorio, salida que no deje existencia negativa; existencia resultante. | RF-73, RF-74 |
| `app/Pos/Calculator/CashArqueo.php` | Efectivo esperado (apertura + ventas en efectivo − anulaciones en efectivo), diferencia y su sentido. | RF-59 |
| `app/Pos/Value/SalesDaySummary.php` | Ventas confirmadas, monto vendido, anuladas, monto anulado y desglose por medio de pago. | RF-46, RF-52, RF-75 |
| `app/Pos/Value/PaymentBreakdown.php` | Monto y cantidad por medio de pago. | RF-46, RF-52, RF-61 |
| `app/Pos/Value/TurnSummary.php` | Ventas del turno, total cobrado, desglose y anulaciones del turno. | RF-56, RF-61 |
| `app/Pos/Value/InventorySnapshot.php` | Activos, bajo mínimo, sin stock, movimientos del día. | RF-63 |
| `app/Pos/Value/StockAdjustment.php` | Aceptado o rechazado, existencia resultante y código de rechazo. | RF-73 |
| `app/Pos/Value/ArqueoResult.php` | Esperado, declarado, diferencia y sentido. | RF-59 |

### 1.2 Persistencia (RF-56 a RF-59, RF-63 a RF-65, RF-68 a RF-74)

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_11_18_000001_add_inventory_fields_to_products_table.php` | `min_stock` (S-11) y `active` (RF-72); índice por `active`. |
| `database/migrations/2026_11_18_000002_create_cash_registers_table.php` | Turno de caja: responsable, monto inicial, apertura, cierre, snapshot del arqueo y el índice único parcial «una caja abierta por usuario» (S-9). |
| `database/migrations/2026_11_18_000003_extend_stock_movements_table.php` | `reason` → `type`; añade `note`, `user_id` y `resulting_stock` (RF-74), con relleno de las filas históricas. |
| `database/migrations/2026_11_18_000004_add_cash_register_to_sales_table.php` | `sales.cash_register_id` nullable con índice: fija a qué turno pertenece cada venta y congela el conjunto del turno (caso límite 7). |
| `app/Models/CashRegister.php` | Turno abierto/cerrado, relaciones con usuario y ventas, casts de fecha y monto. |
| `app/Models/Product.php` | `min_stock`, `active`; scopes `active()`, `belowMinimum()`, `outOfStock()`. |
| `app/Models/StockMovement.php` | `type`, `note`, `user_id`, `resulting_stock`; relación con autor. |
| `app/Models/Sale.php` | `cash_register_id` y relación `cashRegister()`. |
| `app/Models/User.php` | Constante `ROLE_BODEGUERO`, helper `isBodeguero()` y `panelRoute()`. |
| `database/factories/CashRegisterFactory.php` | Turno abierto y cerrado para tests. |
| `database/seeders/UserSeeder.php` | Un usuario por rol (admin, químico, técnico, bodeguero) para la demo manual del criterio 4; `DatabaseSeeder` lo invoca. |

### 1.3 Orquestación — `app/Services/` (RF-46 a RF-77)

| Archivo | Responsabilidad | RF |
|---|---|---|
| `app/Services/Dashboard/SalesDayQuery.php` | Única consulta agregada del día (ventas, anuladas y desglose por medio de pago). La usan los tres paneles, lo que garantiza RF-75. | RF-46, RF-52, RF-61, RF-75 |
| `app/Services/Dashboard/AdminPanelService.php` | Documentos por resolver, alertas de inventario, auditoría reciente, estado de convenios y cortes del día. | RF-47 a RF-51 |
| `app/Services/Dashboard/ChemistPanelService.php` | Autorizaciones del día del usuario y anulaciones del día. | RF-52 a RF-55 |
| `app/Services/Dashboard/CashierPanelService.php` | Caja abierta, resumen del turno y datos previos al cierre. | RF-56, RF-57, RF-61 |
| `app/Services/Dashboard/WarehousePanelService.php` | Resumen del inventario, prioridad de reposición y movimientos recientes. | RF-63 a RF-65 |
| `app/Services/Cash/CashRegisterService.php` | Apertura y cierre transaccionales; impide la segunda caja abierta; congela el arqueo al cerrar. | RF-58, RF-59, RF-60 |
| `app/Services/Inventory/StockAdjustmentService.php` | Ajuste con bloqueo de la fila del producto, movimiento con autor y existencia resultante. | RF-66, RF-73, RF-74 |
| `app/Services/Inventory/ProductService.php` | Alta y edición del catálogo; valida EAN-13 y unicidad; desactiva en vez de borrar. | RF-68 a RF-72 |
| `app/Services/Pos/CheckoutService.php` | **Modificado:** rechaza el cobro sin turno abierto y sella `cash_register_id` en la venta. | RF-60 |
| `app/Services/Pos/VoidSaleService.php` | **Modificado:** movimiento con `type = void`, autor y existencia resultante. | RF-65, RF-74 |
| `app/Services/Pos/ConventionService.php` | **Modificado:** `activate()` para el interruptor del panel. | RF-50 |

### 1.4 HTTP (RF-41 a RF-77)

| Archivo | Responsabilidad |
|---|---|
| `routes/web.php` | `dashboard.*`, `cash.*`, `inventory.*`; `/` deja de ser un redirect fijo y pasa a resolverse por rol; `role:bodeguero` en catálogo y controlados; Químico y Admin conservan sus grupos. |
| `app/Http/Controllers/Dashboard/DashboardController.php` | Aterrizaje por rol y página de rol no reconocido. |
| `app/Http/Controllers/Dashboard/AdminDashboardController.php` | Panel del Administrador. |
| `app/Http/Controllers/Dashboard/ChemistDashboardController.php` | Panel del Químico. |
| `app/Http/Controllers/Dashboard/CashierDashboardController.php` | Panel del Técnico. |
| `app/Http/Controllers/Dashboard/WarehouseDashboardController.php` | Panel del Bodeguero. |
| `app/Http/Controllers/Cash/CashRegisterController.php` | Formulario de cierre, apertura y cierre. |
| `app/Http/Controllers/Inventory/ProductController.php` | Listado, alta y edición del catálogo. |
| `app/Http/Controllers/Inventory/StockAdjustmentController.php` | Registro de entrada o salida. |
| `app/Http/Controllers/Inventory/StockMovementController.php` | Listado consultable de movimientos. |
| `app/Http/Controllers/Admin/ConventionController.php` | **Modificado:** `toggle()` para activar o desactivar desde el panel. |
| `app/Http/Requests/Cash/{OpenCashRegisterRequest,CloseCashRegisterRequest}.php` | Validación de forma: monto inicial y monto declarado enteros no negativos. |
| `app/Http/Requests/Inventory/{StoreProductRequest,UpdateProductRequest,AdjustStockRequest}.php` | Validación de forma; la regla EAN-13 y la unicidad se delegan en `BarcodePolicy` y `ProductService`. |

**Mapa de rutas**

| Método | URI | Nombre | Middleware `role:` | RF |
|---|---|---|---|---|
| GET | `/` | `dashboard.home` | `auth` | RF-41 |
| GET | `/panel` | `dashboard.index` | `auth` | RF-41 |
| GET | `/panel/admin` | `dashboard.admin` | `admin` | RF-46 a RF-51 |
| GET | `/panel/quimico` | `dashboard.quimico` | `quimico` | RF-52 a RF-55 |
| GET | `/panel/tecnico` | `dashboard.tecnico` | `tecnico` | RF-56, RF-57, RF-61, RF-62 |
| GET | `/panel/bodeguero` | `dashboard.bodeguero` | `bodeguero` | RF-63 a RF-67 |
| POST | `/cash-registers` | `cash.open` | `tecnico` | RF-58 |
| GET | `/cash-registers/close` | `cash.close.show` | `tecnico` | RF-59 |
| PATCH | `/cash-registers/{cashRegister}/close` | `cash.close` | `tecnico` | RF-59 |
| GET | `/inventory/products` | `inventory.products.index` | `admin,bodeguero` | RF-68 |
| GET | `/inventory/products/create` | `inventory.products.create` | `admin` | RF-69 |
| POST | `/inventory/products` | `inventory.products.store` | `admin` | RF-69 |
| GET | `/inventory/products/{product}/edit` | `inventory.products.edit` | `admin` | RF-70 |
| PATCH | `/inventory/products/{product}` | `inventory.products.update` | `admin` | RF-70 |
| POST | `/inventory/adjustments` | `inventory.adjustments.store` | `admin,bodeguero` | RF-66, RF-73 |
| GET | `/inventory/movements` | `inventory.movements.index` | `admin,bodeguero` | RF-65, RF-74 |
| PATCH | `/admin/conventions/{convention}/toggle` | `admin.conventions.toggle` | `admin` | RF-50 |
| POST | `/admin/documents/{document}/retry` | `admin.documents.retry` (existente) | `admin` | RF-47 |
| GET | `/admin/audit` | `admin.audit.index` (existente) | `quimico,admin` | RF-49, RF-55 |
| GET | `/admin/controlled-register` | `admin.controlled-register.index` (existente, se le suma `bodeguero`) | `admin,bodeguero` | RF-67 |

### 1.5 Configuración e infraestructura

| Archivo | Responsabilidad |
|---|---|
| `config/navigation.php` | Cuatro grupos (`admin`, `quimico`, `tecnico`, `bodeguero`) con ruta, patrón activo, etiqueta y trazo de icono. |
| `config/pos.php` | `convention.expiring_days` (días de aviso de vencimiento próximo, RF-50). |
| `lang/es/pos.php` | Claves `panel.*`, `cash.*`, `inventory.*` y los mensajes de error nuevos. |
| `public/build` | Recompilado y versionado tras tocar clases de Tailwind. |

### 1.6 Vistas — `resources/views/`

| Archivo | Responsabilidad |
|---|---|
| `layouts/admin.blade.php` | **Modificado:** recibe `$navGroup` (por defecto `admin`), muestra el grupo del rol y ofrece «Ir al POS» sólo a quien puede entrar. |
| `partials/nav-links.blade.php` | **Modificado:** renderiza `config('navigation.'.$navGroup)`. |
| `dashboard/admin.blade.php` | Panel del Administrador. |
| `dashboard/quimico.blade.php` | Panel del Químico. |
| `dashboard/tecnico.blade.php` | Panel del Técnico, con estado de caja y resumen del turno. |
| `dashboard/bodeguero.blade.php` | Panel del Bodeguero, con prioridad de reposición y ajuste. |
| `dashboard/unsupported.blade.php` | Rol ausente o no reconocido, con el mensaje de a quién solicitar acceso. |
| `dashboard/partials/{kpi,payment-breakdown,empty-state,documents-table,audit-table,conventions-table,cash-cuts-table,inventory-alerts,turn-summary,open-cash-form,close-cash-form,adjust-stock-form,movements-table}.blade.php` | Bloques reutilizados por los cuatro paneles. |
| `inventory/products/{index,create,edit}.blade.php` | Catálogo y formularios. |
| `inventory/movements/index.blade.php` | Movimientos de stock consultables. |

### 1.7 Pruebas — `tests/` (sección 6)

`tests/Unit/Pos/…`, `tests/Feature/Dashboard/…`, `tests/Feature/Cash/…` y `tests/Feature/Inventory/…`, más `database/factories/CashRegisterFactory.php`. `tests/TestCase.php` gana un helper `openCashRegister(User $cashier)` para que los tests de cobro existentes declaren la precondición de RF-60 sin duplicar código.

---

## 2. Funciones puras de lógica

Reglas: sin `now()`, `Auth`, `request()`, `DB` ni `view()`. Toda fecha entra como parámetro `DateTimeImmutable` ya convertida a `America/Santiago` por el llamador.

```php
// app/Pos/Policy/LandingPolicy.php — RF-41, RF-43, RF-45
public function panelRoute(?string $role): ?string;   // 'dashboard.admin' | … | null (caso 1)
public function canOpen(string $role, string $panel): bool;

// app/Pos/Policy/BarcodePolicy.php — RF-71
public function isValid(?string $barcode): bool;      // null o '' => true (producto sin código)
public function checkDigit(string $twelveDigits): int; // dígito verificador EAN-13

// app/Pos/Policy/StockAdjustmentPolicy.php — RF-73, RF-74
public function evaluate(
    int $currentStock,
    int $quantity,
    StockMovementType $type,
    string $reason
): StockAdjustment;
// rechaza: quantity < 1, reason vacío, salida mayor que la existencia
// acepta:   resulting = currentStock + type->sign() * quantity

// app/Pos/Calculator/CashArqueo.php — RF-59
public function expected(int $openingAmount, int $cashSales, int $cashVoids): int;
public function difference(int $expected, int $declared): int; // declarado − esperado
public function sign(int $difference): CashDifference;         // balanced | over | short

// app/Pos/Enum/StockMovementType.php — RF-65, RF-74
public function sign(): int;    // sale −1 · void +1 · in +1 · out −1
public function label(): string; // __() resuelto en la capa de presentación
```

Convención de dinero: **enteros en pesos**, igual que en la 001. La diferencia de caja es el único entero con signo del sistema y se guarda como tal (`declarado − esperado`): positiva es sobrante, negativa es faltante.

---

## 3. Algoritmos

```text
PROCEDIMIENTO aterrizar(usuario)
  1. SI usuario es nulo ENTONCES redirigir_a(login)                 // fuera de alcance
  2. panel = LandingPolicy.panelRoute(usuario.role)
  3. SI panel es nulo ENTONCES responder 403 con vista «sin rol»    // RF-41, caso 1
  4. redirigir_a(panel)                                             // RF-41, RF-44
FIN

PROCEDIMIENTO abrir_caja(cajero, monto_inicial, ahora_local)
  1. SI monto_inicial NO es entero >= 0 ENTONCES error            // RF-58
  2. INICIAR TRANSACCIÓN
       a. INSERTAR turno (user_id, opening_amount, opened_at=ahora_local)
          SI el índice único parcial «una caja abierta por usuario» choca
          ENTONCES ROLLBACK + error «Ya tienes una caja abierta»    // RF-58, caso 8
       b. COMMIT
  3. redirigir_a(panel del Técnico con el turno abierto)            // RF-56
FIN

PROCEDIMIENTO cerrar_caja(cajero, monto_declarado, ahora_local)
  1. turno = turno abierto del cajero
     SI no existe ENTONCES error «No hay una caja abierta»         // caso 4
  2. ventas      = ventas del turno (cash_register_id = turno.id)
     cash_sales  = SUMA(total) WHERE status=confirmed AND payment_method=cash
     cash_voids  = SUMA(total) WHERE status=voided    AND payment_method=cash
     esperado    = CashArqueo.expected(opening, cash_sales, cash_voids)   // RF-59, caso 6
  3. diferencia = CashArqueo.difference(esperado, monto_declarado)       // RF-59
  4. INICIAR TRANSACCIÓN
       turno.closed_at        = ahora_local
       turno.total_sales      = SUMA(total) WHERE status=confirmed
       turno.total_voids      = SUMA(total) WHERE status=voided
       turno.cash_sales       = cash_sales
       turno.cash_voids       = cash_voids
       turno.expected_cash    = esperado
       turno.declared_cash    = monto_declarado
       turno.difference       = diferencia
       COMMIT                                                        // RF-59, caso 5
  5. mostrar corte con la diferencia en pesos y su sentido             // RF-59, RF-51
FIN

PROCEDIMIENTO ajustar_stock(usuario, producto, cantidad, tipo, motivo)
  1. INICIAR TRANSACCIÓN
       fila = producto CON BLOQUEO                                      // RF-73, caso 15
       decisión = StockAdjustmentPolicy.evaluate(
                      fila.stock, cantidad, tipo, motivo)
       SI decisión.rechazada ENTONCES ROLLBACK + error(decisión.motivo) // RF-73, caso 16
       fila.stock = decisión.resultante
       INSERTAR movimiento (producto, tipo, cantidad firmada,
                            resultante, autor, motivo, fecha local)     // RF-74
       COMMIT
FIN

PROCEDIMIENTO cobrar(cajero, carrito, pago, …)                        // RF-60
  1. turno = turno abierto del cajero
     SI no existe ENTONCES error «Abre tu caja para cobrar»            // RF-60, caso 9
  2. … flujo de la 001 …
  3. INSERTAR venta con cash_register_id = turno.id                    // caso 7
FIN
```

**Congelamiento del turno (caso límite 7).** Las ventas del turno se identifican por `cash_register_id`, no por una ventana de fechas. Al cerrar, el arqueo y los totales quedan grabados en la fila del turno: una anulación posterior cambia la venta, pero no reescribe el turno cerrado. No es una tabla de resumen (RNF-20): es el acta del cierre, calculada una sola vez desde los registros operativos.

---

## 4. Interfaz (Blade + Tailwind)

### 4.1 Reglas comunes a los cuatro paneles

- Cabecera con nombre del rol y fecha del día en `dd-mm-aaaa` (RNF-16).
- Indicadores en tarjetas con cifras tabulares (`tabular-nums`), no gráficos.
- Cada lista tiene encabezados semánticos (`<th scope="col">`), estado vacío con mensaje (RF-77) y foco visible (RNF-17).
- Navegación lateral con `config/navigation.php`; en 375 px se convierte en barra horizontal, igual que en B12.
- Todo enlace visible está permitido para el rol (RF-44): nada que termine en 403.

### 4.2 Panel del Administrador — `dashboard/admin` (RF-46 a RF-51)

- Cuatro indicadores: ventas confirmadas, monto vendido, ventas anuladas y monto anulado, más el desglose por medio de pago.
- Documentos por resolver con «Reintentar emisión» en línea.
- Alertas de inventario con enlace a la lista filtrada del catálogo.
- Últimos movimientos de auditoría: autor, acción traducida, venta y motivo.
- Convenios próximos a vencer y vencidos, con interruptor «Activar» / «Desactivar».
- Cortes del día: responsable, esperado, declarado y diferencia con signo y color (esmeralda a favor, ámbar en contra).

### 4.3 Panel del Químico — `dashboard/quimico` (RF-52 a RF-55)

- Ventas del día, monto vendido y desglose por medio de pago.
- Autorizaciones de hoy del usuario en sesión: tipo traducido (descuento global, descuento de línea, receta reutilizada), venta, motivo y monto o porcentaje.
- Anulaciones de hoy con motivo y autor.
- Sin tarjeta ni enlaces de convenios y auditoría; las rutas de lectura conservan sus permisos.

### 4.4 Panel del Técnico — `dashboard/tecnico` (RF-56, RF-57, RF-61, RF-62)

- Si hay caja abierta: desde cuándo, monto inicial, ventas del turno por medio de pago y total cobrado.
- Si no hay caja abierta: formulario de apertura con monto inicial y el aviso de que sin apertura no se puede cobrar.
- Cierre: resumen del turno antes de confirmar, efectivo esperado y campo de monto declarado; la diferencia se muestra en vivo y el cierre se permite con diferencia distinta de cero.
- Botón «Ir al POS».

### 4.5 Panel del Bodeguero — `dashboard/bodeguero` (RF-63 a RF-67)

- Activos, bajo mínimo, sin stock y movimientos del día.
- Prioridad de reposición: existencia ascendente, mínimo y unidad de venta.
- Ajuste desde el panel: producto, entrada/salida, cantidad y motivo obligatorio (formulario accesible por teclado, RNF-18).
- Movimientos recientes con producto, tipo, cantidad, motivo, autor y fecha.
- Accesos a catálogo y libro de controlados en lectura.

### 4.6 Catálogo — `inventory/products` (RF-68 a RF-72)

- Búsqueda por nombre, principio activo o código de barras; columnas precio, categoría, existencia, mínimo y vendible.
- Alta y edición con validación en español; el error de EAN-13 y el de código repetido nombran el motivo (RNF-22).
- «Quitar de catálogo» desactiva, no borra: el producto desaparece de la búsqueda de la caja y conserva su historial.

### 4.7 POS (RF-45, RF-60)

- La cabecera del POS gana «Ir a mi panel» para el Técnico.
- Con caja cerrada, el botón «Cobrar» queda deshabilitado con el texto de cómo abrirla; el rechazo del servidor repite el mismo mensaje si se fuerza el envío.

---

## 5. Decisiones técnicas

| # | Decisión | Alternativa descartada y por qué |
|---|---|---|
| **D1** | **Cuatro controladores de panel y cuatro servicios de consulta**, uno por rol. | **Un panel único con secciones condicionales**: vuelve a esconder con `@if` lo que el RBAC debe negar, y RF-43 exige rechazo, no ocultamiento. **Un `DashboardService` con cuatro métodos**: concentra consultas ajenas y crece sin control. |
| **D2** | **Una sola consulta agregada del día (`SalesDayQuery`)** compartida por Admin, Químico y Técnico. | **Tres consultas parecidas**: se desincronizan y rompen RF-75. **Caché del panel**: RF-76 lo prohíbe y RNF-20 descarta tablas de resumen. |
| **D3** | **Turno de caja como tabla propia con índice único parcial** (`WHERE closed_at IS NULL`). | **Bandera `open` en `users`**: no deja historial ni monto inicial. **Validar sólo en PHP**: dos pestañas abren dos cajas. El índice parcial es la garantía real y exige SQL crudo porque el builder no lo expresa. |
| **D4** | **`sales.cash_register_id`** para fijar la pertenencia al turno. | **Ventana `opened_at … closed_at`**: una venta de las 23:59:59 puede caer en dos turnos y una anulación posterior reescribiría el total (caso 7). |
| **D5** | **Snapshot del arqueo al cerrar** (esperado, declarado, diferencia y totales) en la fila del turno. | **Calcular el cierre al vuelo cada vez que se consulta**: el caso 7 prohíbe que una anulación posterior cambie un turno cerrado. No contradice RNF-20 porque es un acta, no una caché. |
| **D6** | **`stock_movements.reason` → `type`** con valores `sale`, `void`, `in`, `out`. | **Columna nueva `type` al lado de `reason`**: dos fuentes de verdad para el mismo dato y filas históricas ambiguas. Los dos valores existentes ya son válidos, así que la migración no reinterpreta datos. |
| **D7** | **`resulting_stock` obligatorio**, rellenado hacia atrás con una ventana `SUM(quantity) OVER (PARTITION BY product_id ORDER BY id)`. | **Calcularlo al consultar**: cuesta una consulta por fila (RNF-15) y cambia si se corrige un movimiento antiguo. **Dejarlo nullable**: RF-74 lo exige siempre. |
| **D8** | **`products.active` separado de `sellable`**. | **Reutilizar `sellable` para «fuera de catálogo»**: RF-70 dice que quitar vendible no afecta el historial y RF-72 pide además salir de la búsqueda; son dos estados distintos. **Borrado lógico con `deleted_at`**: Eloquent lo escondería también del historial. |
| **D9** | **EAN-13 validado en `BarcodePolicy` (función pura)** y unicidad delegada a la base. | **Sólo `unique` de validación**: no detecta el dígito verificador. **Validar en el modelo**: imposible de testear sin instanciar Eloquent. |
| **D10** | **Los paneles reutilizan `layouts/admin.blade.php`** con un `$navGroup` explícito. | **Renombrar el layout a `layouts/app`**: obliga a tocar las ocho vistas de administración sin cambiar comportamiento ni RF. La deuda del nombre queda anotada en la sección 7. |
| **D11** | **Ajuste de stock con `lockForUpdate()`** sobre la fila del producto. | **Leer y escribir sin bloqueo**: dos bodegueros dejan la existencia negativa (caso 15). **Recalcular la existencia desde la suma de movimientos en cada ajuste**: correcto pero más caro y ajeno a cómo ya opera el POS. |
| **D12** | **RNF-14 se mide a mano** con 20.000 ventas y 5.000 productos y se documenta en `MEMORY.md`. | **Test automatizado de tiempo**: inestable en CI y en el equipo de cada estudiante; daría falsos rojos. RNF-15 sí se automatiza contando consultas con `DB::listen`. |

---

## 6. Estrategia de tests (PHPUnit, `php artisan test`)

### 6.1 Reglas

1. **TDD estricto:** por cada RF, primero el test que falla (mostrado en la conversación), luego la implementación mínima.
2. **Unit** para `app/Pos/**`: sin BD ni HTTP; fechas inyectadas.
3. **Feature** para rutas, permisos, transacciones, consultas y vistas.
4. Nombres de test en inglés, con el RF en el nombre o en el docblock.
5. Prohibido `markTestSkipped`, tests comentados o `withoutExceptionHandling()` para tapar errores.
6. Cualquier test existente que el cambio de comportamiento deje en rojo (RF-60) se corrige declarando la precondición con `openCashRegister()`, nunca relajando la aserción.

### 6.2 Cobertura por archivo

| Test | Tipo | RF / RNF que cubre |
|---|---|---|
| `tests/Unit/Pos/LandingPolicyTest.php` | Unit | RF-41, RF-43, RF-45 (rol → panel, rol desconocido) |
| `tests/Unit/Pos/BarcodePolicyTest.php` | Unit | RF-71 (EAN-13 válido, dígito incorrecto, vacío) |
| `tests/Unit/Pos/StockAdjustmentPolicyTest.php` | Unit | RF-73, RF-74 (cantidad, motivo, salida negativa, resultante) |
| `tests/Unit/Pos/CashArqueoTest.php` | Unit | RF-59 (esperado con anulaciones, diferencia a favor y en contra, cero) |
| `tests/Feature/Dashboard/RoleLandingTest.php` | Feature | RF-41, RF-42, RF-43, RF-45 (cuatro roles, caso 1, caso 2, caso 3) |
| `tests/Feature/Dashboard/NavigationTest.php` | Feature | RF-44, RNF-19 (menú por rol, sin enlaces que terminen en 403) |
| `tests/Feature/Cash/CashRegisterTest.php` | Feature | RF-56, RF-57, RF-58, RF-59, RF-61 (casos 4, 5, 6, 8, 20) |
| `tests/Feature/Cash/CheckoutGateTest.php` | Feature | RF-60 (caso 9; venta sin turno no se crea) |
| `tests/Feature/Dashboard/AdminPanelTest.php` | Feature | RF-46 a RF-51, RF-77 |
| `tests/Feature/Dashboard/ChemistPanelTest.php` | Feature | RF-52 a RF-55 |
| `tests/Feature/Dashboard/ReportConsistencyTest.php` | Feature | RF-75, RF-76 (caso 10: medianoche) |
| `tests/Feature/Dashboard/QueryBudgetTest.php` | Feature | RNF-15 (consultas constantes al crecer la lista) |
| `tests/Feature/Inventory/ProductCatalogTest.php` | Feature | RF-68 a RF-72 (casos 12, 13, 14) |
| `tests/Feature/Inventory/StockAdjustmentTest.php` | Feature | RF-63 a RF-67, RF-73, RF-74 (casos 15, 16, 11) |

### 6.3 Orden de implementación

1. **T11 — Base:** migraciones, rol Bodeguero, `LandingPolicy` y aterrizaje por rol (RF-41 a RF-43, RF-45).
2. **T12 — Turnos:** `CashRegisterService`, arqueo y rutas de apertura y cierre (RF-58, RF-59).
3. **T13 — Panel del Técnico:** estado y resumen del turno, bloqueo del cobro (RF-56, RF-57, RF-60 a RF-62).
4. **T14 — Panel del Administrador** (RF-46 a RF-51).
5. **T15 — Panel del Químico** (RF-52 a RF-55).
6. **T16 — Catálogo** (RF-68 a RF-72).
7. **T17 — Inventario del Bodeguero:** panel, prioridad, movimientos y ajuste (RF-63 a RF-67, RF-73, RF-74).
8. **T18 — Cierre:** navegación por rol, coherencia de indicadores, RNF-14 a RNF-22 y las demos manuales de los criterios 4 a 8.

### 6.4 Comandos

```bash
docker compose up -d
php artisan migrate --seed
php artisan test --testsuite=Unit
php artisan test
npm run build
```

---

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| **Renombrar `stock_movements.reason` → `type` rompe el verde** (toca `CheckoutService`, `VoidSaleService`, el modelo y los tests de venta y anulación) | Se hace en T11 con la suite completa como red: si algo queda rojo, el nombre nuevo no se propaga. La migración conserva los valores (`sale`, `void`), así que no hay datos que reinterpretar. |
| **RF-60 deja en rojo los tests de cobro existentes** | Es un cambio de comportamiento pedido por la spec 001 v1.1 (RF-20 y RF-22 enmendados). Se corrige declarando la precondición con `openCashRegister()`, no relajando aserciones. |
| **Índice único parcial no expresable con el builder** | SQL crudo en la migración (`CREATE UNIQUE INDEX … WHERE closed_at IS NULL`) y un test de dos aperturas concurrentes de la misma sesión que espera el rechazo. |
| **Descuadre entre panel y detalle** | `SalesDayQuery` es la única fuente de los totales del día; `ReportConsistencyTest` compara panel, historial y corte para el mismo día. |
| **Anulación después del cierre reescribe el turno** | El turno se identifica por `cash_register_id` y el arqueo queda grabado al cerrar; test explícito del caso 7. |
| **RNF-14 incumplido con 20.000 ventas** | Índices por `sold_at`, `cash_register_id` y `stock_movements(product_id, created_at)`; agregación en SQL con `GROUP BY`, nunca en PHP; medición manual documentada. |
| **Menú duplicado por rol** | Un solo partial y un solo archivo de configuración; `NavigationTest` recorre los cuatro roles y comprueba que cada enlace visible responde 200 para ese rol. |
| **Nombre del layout (`admin`) para los cuatro paneles** | Deuda declarada: se documenta en el propio layout. Renombrarlo es refactorización sin RF y queda fuera de esta spec. |
| **Crecimiento del alcance** | Todo lo de la §7 de la spec 002 queda fuera; cualquier agregado exige spec nueva. |

---

*Plan escrito el 2026-11-18. Suite de partida: 246 tests, 1131 aserciones, 0 fallos.*
