# Plan técnico 001 — Punto de Venta (POS)

**Spec de referencia:** `specs/001-pos/spec.md` v1.0 (aprobada)
**Constitución:** `docs/constitution.md` v1.0
**Stack:** Laravel 11 · Blade + Tailwind · PostgreSQL 16 · Sanctum · Docker
**Estado:** listo para implementar por fases (TDD)

> Nota: la skill `sdd` no está instalada en este entorno. El plan sigue la estructura solicitada y las reglas de la constitución, que es la fuente normativa real.

---

## 0. Cumplimiento constitucional (trazabilidad)

| Principio | Cómo lo cumple este plan |
|---|---|
| **P1 Stack fijo** | Sólo Laravel 11, Blade, Tailwind, PostgreSQL 16, Sanctum y Docker. Cero frameworks JS, cero servicios externos. La emisión tributaria es simulada (D-1). |
| **P2 Spec manda** | Cada archivo y cada test declara el RF que implementa. Nada del plan existe sin RF asociado; los RF nuevos entran primero a la spec. |
| **P3 Lógica pura** | Todo cálculo vive en `app/Pos/**`, sin `request()`, `Auth`, Eloquent ni vistas. Los controladores sólo orquestan. |
| **P4 Test primero** | Fase por fase: test rojo → implementación → verde. Los RF se agrupan por test (sección 6). |
| **P5 Datos protegidos** | Fechas con `America/Santiago` desde `config/pos.php`; anulaciones por adición, nunca borrado; carrito persistente en sesión + BD de trabajo; migraciones aditivas. |
| **P6 Idioma** | Clases, métodos, columnas y tests en inglés; textos de interfaz en `lang/es/pos.php`. Sin literales de UI en Blade. |

---

## 1. Archivos que se crean o modifican

Todas las rutas son relativas a la raíz del proyecto Laravel.

### 1.1 Dominio puro — `app/Pos/` (RF-2 a RF-29, RF-32 a RF-36, RF-39, RF-40)

Nada aquí conoce HTTP, sesión, base de datos ni Blade.

| Archivo | Responsabilidad | RF |
|---|---|---|
| `app/Pos/Money.php` | Valor monetario en pesos enteros; suma, resta y comparación sin flotantes. | RF-19 |
| `app/Pos/Value/ProductSnapshot.php` | Copia inmutable de producto para calcular (id, nombre, precio, categoría, stock leído). | RF-2, RF-29 |
| `app/Pos/Value/CartLine.php` | Línea: producto, cantidad, descuento de línea. | RF-2, RF-3 |
| `app/Pos/Value/Cart.php` | Colección inmutable de líneas; agregar, cambiar cantidad, quitar, detectar duplicados. | RF-2, RF-3, RF-5 |
| `app/Pos/Value/Discount.php` | Descuento con origen (manual/convenio), tipo (% o monto), valor y motivo. | RF-13, RF-17 |
| `app/Pos/Value/ConventionSnapshot.php` | Convenio con porcentaje, vigencia y estado. | RF-39, RF-40 |
| `app/Pos/Value/PrescriptionInput.php` | Datos de receta ingresados. | RF-8 |
| `app/Pos/Value/ReceiptTotals.php` | Subtotal, descuentos, neto, IVA y total. | RF-4, RF-12, RF-19 |
| `app/Pos/Value/DiscountDecision.php` | Descuento aplicable, si requiere supervisor y código de motivo. | RF-14 a RF-18 |
| `app/Pos/Value/PrescriptionResult.php` | Válida o lista de errores por campo. | RF-9 |
| `app/Pos/Value/Shortage.php` | Faltante por producto (solicitado vs disponible). | RF-3, RF-27, RF-29 |
| `app/Pos/Value/VoidPlan.php` | Efectos de una anulación: reposición y reversa documental. | RF-31, RF-34 |
| `app/Pos/Enum/SaleCategory.php` | `free`, `prescription`, `controlled`. | RF-7 |
| `app/Pos/Enum/SaleStatus.php` | `confirmed`, `voided`. | RF-33, RF-35 |
| `app/Pos/Enum/DocumentStatus.php` | `pending`, `issued`, `rejected`. | RF-26 |
| `app/Pos/Enum/DocumentType.php` | `receipt`, `invoice`, `credit_note`. | RF-23, RF-34 |
| `app/Pos/Enum/PaymentMethod.php` | `cash`, `debit`, `credit`, `transfer`. | RF-20 |
| `app/Pos/Calculator/PriceCalculator.php` | Subtotal por línea, descuento, neto, IVA y total; redondeo a peso. | RF-4, RF-12, RF-19 |
| `app/Pos/Calculator/CashChangeCalculator.php` | Vuelto y validación de monto recibido. | RF-21 |
| `app/Pos/Policy/DiscountPolicy.php` | Tope general del 10%, descuento por convenio (RF-15), autorización de supervisor y total no negativo. | RF-13 a RF-18, RF-39, RF-40 |
| `app/Pos/Policy/PrescriptionPolicy.php` | Categoría, datos obligatorios, vigencia y receta repetida. | RF-7 a RF-11 |
| `app/Pos/Policy/StockPolicy.php` | Disponibilidad, faltantes y detección de conflicto de stock. | RF-3, RF-27, RF-29 |
| `app/Pos/Policy/VoidPolicy.php` | Si una venta puede anularse y qué efectos produce. | RF-32, RF-35 |
| `app/Pos/Folio/FolioAllocator.php` | Siguiente folio libre dentro del rango, sin repetir ni saltear. | RF-23, RF-24, RF-26 |

### 1.2 Persistencia (RF-22 a RF-40)

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/*_create_conventions_table.php` | Convenios: nombre, porcentaje, vigencia, activo. |
| `database/migrations/*_create_sales_table.php` | Venta: folio lógico, totales, medio de pago, cajero, estado, marca de tiempo local, token de idempotencia. |
| `database/migrations/*_create_sale_items_table.php` | Líneas congeladas: nombre, precio y cantidad al momento de vender. |
| `database/migrations/*_create_prescriptions_table.php` | Receta: número, prescriptor, RUT, fecha de emisión, venta asociada. |
| `database/migrations/*_create_documents_table.php` | Documento: tipo, folio único, estado, venta, documento de reversa. |
| `database/migrations/*_create_stock_movements_table.php` | Movimientos de stock con signo y motivo (venta, anulación). |
| `database/migrations/*_create_audit_logs_table.php` | Autorizaciones y acciones sensibles: actor, acción, motivo, fecha local. |
| `database/migrations/*_add_pos_columns_to_products.php` | Categoría de venta, vendible y stock (si no existen). |
| `app/Models/Sale.php`, `SaleItem.php`, `Prescription.php`, `Document.php`, `StockMovement.php`, `AuditLog.php`, `Convention.php`, `Product.php` | Modelos Eloquent; sin cálculos de negocio. |

### 1.3 Orquestación — `app/Services/Pos/` (RF-6, RF-22 a RF-38)

| Archivo | Responsabilidad | RF |
|---|---|---|
| `CartService.php` | Leer, guardar y limpiar el carrito de la sesión; reconstruirlo tras recarga. | RF-6 |
| `CheckoutService.php` | Transacción de venta: validar, bloquear stock, asignar folio, persistir todo o nada. | RF-22 a RF-31 |
| `VoidSaleService.php` | Anulación: validar rol, reponer stock, crear reversa y auditoría. | RF-31 a RF-36 |
| `DocumentService.php` | Emisión simulada, reintento de pendientes y reimpresión. | RF-23 a RF-26 |
| `ConventionService.php` | CRUD de convenios y validación de vigencia. | RF-39, RF-40 |
| `AuditService.php` | Registrar autorizaciones y acciones sensibles. | RF-16, RF-36 |

### 1.4 HTTP (RF-1 a RF-40)

| Archivo | Responsabilidad |
|---|---|
| `routes/web.php` | Rutas `pos.*` (caja) y `admin.*` (convenios, ventas, documentos). |
| `app/Http/Controllers/Pos/ProductSearchController.php` | Búsqueda por nombre, principio activo o código de barras. |
| `app/Http/Controllers/Pos/CartController.php` | Agregar, cambiar cantidad, quitar, aplicar y quitar descuento. |
| `app/Http/Controllers/Pos/PrescriptionController.php` | Guardar receta en el carrito y validarla. |
| `app/Http/Controllers/Pos/CheckoutController.php` | Mostrar cobro, confirmar venta y volver al POS. |
| `app/Http/Controllers/Pos/ReceiptController.php` | Comprobante e impresión/reimpresión. |
| `app/Http/Controllers/Admin/ConventionController.php` | Alta, edición y desactivación de convenios. |
| `app/Http/Controllers/Admin/SaleController.php` | Historial y detalle de ventas. |
| `app/Http/Controllers/Admin/DocumentController.php` | Estado de documentos y reintento de pendientes. |
| `app/Http/Controllers/Admin/SupervisorAuthorizationController.php` | Captura de credenciales de supervisor. |
| `app/Http/Requests/Pos/{AddToCartRequest,UpdateQuantityRequest,ApplyDiscountRequest,StorePrescriptionRequest,CheckoutRequest}.php` | Validación de forma; sin reglas de negocio. |
| `app/Http/Requests/Admin/{StoreConventionRequest,VoidSaleRequest,AuthorizeRequest}.php` | Validación de administración y autorización. |
| `app/Policies/{SalePolicy,ConventionPolicy}.php` | Permisos por rol. |
| `app/Providers/AppServiceProvider.php` | Registro de policies y de la zona horaria del POS. |

### 1.5 Configuración e infraestructura

| Archivo | Responsabilidad |
|---|---|
| `config/pos.php` | IVA 19%, tope general 10%, vigencia de receta 30 días, rango de folios, datos del emisor, zona horaria. |
| `lang/es/pos.php` | Todo el texto de interfaz y de error. |
| `resources/js/pos.js` | Comportamiento de mostrador: atajos de teclado, foco en búsqueda, confirmaciones. Sin cálculos autoritativos. |
| `resources/css/app.css` | Entrada de Tailwind y estilos de impresión 80 mm / A4. |
| `docker-compose.yml` | App + PostgreSQL 16. |
| `.env.example` | Variables del POS. |
| `phpunit.xml` | Suite `Unit` y `Feature` contra PostgreSQL de pruebas. |

### 1.6 Vistas — `resources/views/` (RF-1 a RF-40)

| Archivo | Responsabilidad |
|---|---|
| `layouts/pos.blade.php` | Layout de caja: cabecera con cajero y estado, atajos, contenedor de mensajes. |
| `pos/index.blade.php` | Pantalla principal: buscador, resultados, carrito, totales y botón cobrar. |
| `pos/partials/search.blade.php` | Campo de búsqueda con foco automático y lectura de código de barras. |
| `pos/partials/cart.blade.php` | Tabla de líneas con cantidad editable y botón quitar. |
| `pos/partials/totals.blade.php` | Subtotal, descuentos, IVA y total; se refresca tras cada cambio. |
| `pos/partials/discount-panel.blade.php` | Descuento por línea o total, motivo y selector de convenio. |
| `pos/partials/prescription-modal.blade.php` | Datos de receta y errores por campo. |
| `pos/checkout.blade.php` | Medio de pago, monto recibido, vuelto y confirmación. |
| `pos/receipt.blade.php` | Comprobante con leyenda de simulación, datos del emisor y del cliente. |
| `admin/conventions/{index,create,edit}.blade.php` | Gestión de convenios. |
| `admin/sales/{index,show}.blade.php` | Historial y detalle, incluida la anulación. |
| `admin/documents/index.blade.php` | Documentos pendientes, emitidos y rechazados. |

### 1.7 Pruebas — `tests/` (sección 6)

`tests/Unit/Pos/…`, `tests/Feature/Pos/…` y `tests/Feature/Admin/…`, más `tests/Factories` implícitas en `database/factories/`.

---

## 2. Funciones puras de lógica

Reglas: sin `now()`, `Carbon::now()`, `Auth`, `request()`, `DB`, ni `view()`. Toda fecha entra como parámetro `DateTimeImmutable $today` (o `$at`) ya convertida a `America/Santiago` por el llamador.

```php
// app/Pos/Calculator/PriceCalculator.php  — RF-4, RF-12, RF-19
public function lineSubtotal(CartLine $line): int;                  // precio * cantidad
public function lineDiscount(CartLine $line): int;                  // monto del descuento de línea
public function totals(Cart $cart, ?Discount $globalDiscount, int $ivaRate): ReceiptTotals;
public function ivaBreakdown(int $grossTotal, int $ivaRate): array;  // ['net' => int, 'iva' => int]
// regla: precios incluyen IVA; net = round(gross / (1 + iva/100)); iva = gross - net

// app/Pos/Policy/DiscountPolicy.php        — RF-13 a RF-18, RF-39, RF-40
public function evaluate(
    int $subtotal,
    Discount $discount,
    ?ConventionSnapshot $convention,
    int $generalCapPercent,
    DateTimeImmutable $today
): DiscountDecision;
// decide: aplicable, monto, requiereSupervisor, motivo de rechazo
// convenio inactivo o fuera de vigencia => rechazo, aunque el supervisor esté presente

// app/Pos/Policy/PrescriptionPolicy.php    — RF-7 a RF-11
public function requiresPrescription(Cart $cart): bool;
public function validate(
    ?PrescriptionInput $input,
    SaleCategory $category,
    DateTimeImmutable $today,
    int $validityDays
): PrescriptionResult;
public function isReused(string $number, array $usedPrescriptionNumbers): bool;

// app/Pos/Policy/StockPolicy.php           — RF-3, RF-27, RF-29
public function canSell(int $available, int $requested): bool;
public function shortages(Cart $cart, array $availableByProductId): array; // Shortage[]

// app/Pos/Calculator/CashChangeCalculator.php — RF-21
public function change(int $total, int $received): int;             // lanza si received < total

// app/Pos/Folio/FolioAllocator.php         — RF-23, RF-24, RF-26
public function next(int $rangeFrom, int $rangeTo, array $usedFolios): ?int; // null si se agotó

// app/Pos/Policy/VoidPolicy.php            — RF-32, RF-35
public function canVoid(SaleStatus $status, ?DocumentStatus $document): bool;
public function plan(SaleSnapshot $sale): VoidPlan;

// app/Pos/Value/Cart.php                   — RF-2, RF-3, RF-5
public function add(ProductSnapshot $product, int $quantity = 1): self;
public function changeQuantity(string $lineId, int $quantity): self;
public function remove(string $lineId): self;
public function isEmpty(): bool;

// app/Pos/Calculator/PriceCalculator.php   — RF-19 (auxiliar)
public function roundToPeso(float $amount): int;                     // redondeo half-up explícito
```

Convención de dinero: **enteros en pesos**, nunca `float` en el dominio. El único punto donde aparece un decimal es el cálculo de IVA y de porcentajes, y se cierra con `roundToPeso()` en la misma función.

---

## 3. Algoritmo del flujo principal de venta

```text
PROCEDIMIENTO vender(cajero, carrito, pago, hoy = ahora_en(America/Santiago))

  1. SI carrito.esta_vacio() ENTONCES
        error("El carrito está vacío")                      // RF-5

  2. PARA CADA línea EN carrito:
        producto = repositorio.buscar(linea.producto_id)
        SI producto es nulo O NO producto.vendible ENTONCES
            error("Producto no disponible")                 // RF-2
        SI linea.cantidad < 1 O linea.cantidad es decimal ENTONCES
            error("Cantidad inválida")                      // RF-3
        linea.producto = producto                           // refresca precio y stock

  3. resumen = PriceCalculator.totals(carrito, descuento_global, IVA=19)   // RF-4, RF-12
     SI resumen.total <= 0 ENTONCES error("Total inválido")               // RF-18

  4. decisión = DiscountPolicy.evaluate(
                    resumen.subtotal, descuento, convenio, tope=10, hoy)  // RF-13..18, RF-39,40
     SI decisión.rechazado ENTONCES error(decisión.motivo)
     SI decisión.requiere_supervisor Y NO supervisor_autorizó ENTONCES
         pedir_credenciales()                                          // RF-16
         registrar_autorización(auditoría)                             // RF-36
     resumen = PriceCalculator.totals(carrito, decisión.descuento, 19)

  5. SI carrito.requiere_receta() ENTONCES
        resultado = PrescriptionPolicy.validate(receta, categoría, hoy, 30) // RF-7..9
        SI NO resultado.válida ENTONCES error(resultado.errores)
        SI receta.ya_usada ENTONCES pedir_supervisor()                  // RF-11

  6. validar_pago(pago, resumen.total)                                  // RF-20
     SI pago.medio == efectivo:
        SI pago.recibido < resumen.total ENTONCES error("Monto insuficiente") // RF-21
        vuelto = CashChangeCalculator.change(resumen.total, pago.recibido)

  7. INICIAR TRANSACCIÓN
       a. PARA CADA línea: leer stock del producto CON BLOQUEO   // RF-28
       b. faltantes = StockPolicy.shortages(carrito, stock_bloqueado)
          SI faltantes NO está vacío ENTONCES
              ROLLBACK
              error("Stock insuficiente", faltantes)             // RF-27, RF-29
       c. PARA CADA línea: actualizar stock = stock - cantidad
          SI filas_afectadas == 0 ENTONCES ROLLBACK + error_conflicto() // RF-28
       d. folio = FolioAllocator.next(rango.desde, rango.hasta, folios_usados)
          SI folio es nulo ENTONCES
              venta queda PENDIENTE de folio, sin consumir folio     // caso 20
       e. insertar venta (cajero, totales, pago, estado=confirmada,
                          token_idempotencia, fecha_hora_local)      // RF-22
       f. insertar líneas congeladas (nombre, precio, cantidad, descuento)
       g. insertar receta si corresponde                              // RF-10
       h. insertar documento simulado (tipo, folio, estado)            // RF-23
       i. insertar movimientos de stock (signo negativo, motivo=venta) // RF-30
       j. insertar auditoría de descuento y autorizaciones            // RF-36
       COMMIT
     SI falla la transacción ENTONCES
       ROLLBACK
       conservar_carrito()                                           // RF-6
       error("No se pudo completar la venta")

  8. SI el documento quedó pendiente O falló su emisión ENTONCES
        marcar_venta_pendiente()                                     // RF-24
        mostrar_comprobante_provisorio()

  9. vaciar_carrito()
     redirigir_a(comprobante(venta))                                 // RF-25
FIN

PROCEDIMIENTO anular(venta, supervisor, motivo, hoy)
  1. SI motivo vacío ENTONCES error("Motivo obligatorio")             // RF-32
  2. SI VOIDPolicy.canVoid(venta.estado, venta.documento?.estado) == false
        ENTONCES error("La venta ya fue anulada")                     // RF-35
  3. INICIAR TRANSACCIÓN
       venta.estado = anulada                                         // RF-33 (nunca se borra)
       registrar anulación (autor, fecha local, motivo, ahorro)       // RF-32, RF-36
       reponer stock por cada línea                                   // RF-31
       insertar movimientos de stock (motivo=anulación)               // RF-30
       SI venta.documento existe ENTONCES crear nota de crédito simulada // RF-34
       COMMIT
FIN
```

**Idempotencia:** cada carrito lleva un `checkout_token` único; la tabla de ventas tiene índice único sobre ese token. Un doble clic o un reenvío no crea dos ventas.

---

## 4. Interfaz (Blade + Tailwind)

### 4.1 Pantalla de caja — `pos/index` (RF-1 a RF-5, RF-13 a RF-19, RF-39, RF-40)

- **Buscador fijo arriba** con foco automático y `Enter` inmediato; el lector de código de barras escribe y confirma como teclado.
- **Resultados** en lista densa: nombre, principio activo, precio, stock y etiqueta de categoría (`Libre` / `Receta` / `Controlado`).
- **Carrito** como tabla: producto, precio unitario, cantidad (input numérico con +/-), descuento, subtotal y botón quitar.
- **Panel de descuento** plegable: origen (manual/convenio), tipo (% o monto), motivo obligatorio en manual; el tope se muestra siempre ("Tope sin autorización: 10%").
- **Totales fijos al pie**: subtotal, descuentos, IVA desglosado y total en tipografía grande, con contraste alto.
- **Aviso de stock** en la línea afectada cuando el stock cambió durante el armado (RF-29).
- **Botón "Cobrar"** deshabilitado y con texto de por qué cuando el carrito está vacío (RF-5).

### 4.2 Cobro y comprobante — `pos/checkout`, `pos/receipt` (RF-20 a RF-26)

- Selección de medio de pago con botones grandes; al elegir efectivo aparecen monto recibido y **vuelto calculado en vivo**, con el vuelto en tamaño destacado.
- Confirmación con resumen no editable de la venta.
- Comprobante imprimible: emisor, folio, fecha en `dd-mm-aaaa`, líneas, descuento y convenio, IVA y total, y **leyenda fija "Documento simulado — sin validez tributaria"** (RNF-13).
- Estilos de impresión en `app.css`: ancho 80 mm y A4.

### 4.3 Autorizaciones y administración (RF-16, RF-32 a RF-40)

- Modal de supervisor reutilizable con usuario, contraseña y motivo; nunca muestra el tope ni la acción sin explicar qué se autoriza.
- `admin/conventions`: tabla con porcentaje, vigencia y estado; formulario con validación en español.
- `admin/sales`: filtros por fecha, cajero, medio de pago, convenio y estado del documento; detalle con líneas, receta, documento, anulación y autorizaciones.
- `admin/documents`: pendientes con botón "Reintentar emisión".

### 4.4 Reglas de interfaz

- Todo texto sale de `lang/es/pos.php`; nada hardcodeado en Blade (P6).
- Los totales se recalculan en el servidor y se repintan con el parcial `pos/partials/totals`; el JS sólo adelanta la vista, nunca decide el precio (esto protege RNF-5).
- `resources/js/pos.js` sólo aporta foco, atajos (`F2` buscar, `F4` cobrar, `Esc` cerrar modal), confirmaciones y bloqueo de doble envío.
- Foco visible, etiquetas asociadas y contraste AA en toda la pantalla de caja (RNF-11).

---

## 5. Decisiones técnicas

| # | Decisión | Alternativa descartada y por qué |
|---|---|---|
| **T1** | **Laravel 11 + Blade server-side**, con parciales que se reemplazan tras cada acción. | **Node.js/Express + SPA**: duplica stack, exige dos despliegues, dos suites de test y un modelo de permisos aparte. Prohibido por P1 y desproporcionado para un equipo de estudiantes. **Livewire/Inertia/Vue**: dependencia adicional no aprobada en la spec. |
| **T2** | **Cálculos en `app/Pos/` como clases puras**, con `$today` inyectado. | **Cálculos en el controlador o en el modelo**: imposible testear sin BD ni HTTP, y las fechas dependen del reloj del servidor. **Cálculos en JS**: el cliente puede alterar el precio; rompe RNF-5. |
| **T3** | **Dinero en enteros de pesos**, sin `float` en el dominio. | **`float`/`double`**: errores de redondeo en IVA y descuentos; el total del comprobante no cuadraría con el registrado. **`decimal` de PostgreSQL expuesto en PHP**: sigue obligando a castear; el entero es más simple y verificable. |
| **T4** | **Bloqueo pesimista de stock (`SELECT … FOR UPDATE`) dentro de la transacción de cobro**, más índice único de idempotencia. | **Verificar stock sólo al agregar al carrito**: permite sobreventa (RF-27/28). **Actualización optimista con reintentos**: más código y más difícil de explicar en un proyecto académico. **Stock negativo con alerta**: descartado por decisión del usuario. |
| **T5** | **Emisión tributaria simulada** con tabla de documentos, folio único y estados. | **Integración con proveedor DTE**: viola P1, exige certificación y costos; decisión D-1. **No emitir documento**: rompe RF-23 a RF-26. |
| **T6** | **Sesión de Laravel + carrito serializado en sesión**, respaldado por el `checkout_token`. | **Carrito sólo en memoria del navegador**: se pierde al recargar (RF-6). **Carrito en tabla temporal por cada clic**: más escrituras y más complejo. |
| **T7** | **Anulación por adición**: venta inmutable + registro de anulación + movimientos de stock. | **Borrado lógico o físico de la venta**: pierde trazabilidad y descuadra reportes (RF-33, RNF-7). |
| **T8** | **Sanctum con sesión web** para autenticación y permisos por rol. | **JWT propio**: reinventa lo que ya trae el stack. **Sin autenticación**: rompe RNF-8 y RF-36. |
| **T9** | **PostgreSQL 16 también en tests**, vía Docker. | **SQLite en memoria**: no reproduce `FOR UPDATE` ni los índices únicos parciales; los tests de concurrencia darían falsos verdes. |
| **T10** | **Configuración del negocio en `config/pos.php`** (IVA, tope 10%, vigencia 30 días, folios, emisor). | **Constantes en el código**: cambiar el tope exigiría un despliegue. **Tabla de parámetros editable en caliente**: alcance no pedido; el administrador ya edita convenios, que es el caso real. |
| **T11** | **Zona horaria `America/Santiago` fijada en la aplicación y pasada explícitamente a las funciones puras.** | **Usar la zona del servidor o UTC por defecto**: fechas de venta y vigencia de receta desfasadas (P5). **Convertir en cada vista**: conversiones sueltas, justo lo que prohíbe la constitución. |
| **T12** | **Texto de interfaz en `lang/es/pos.php`**, código en inglés. | **Español en el código**: rompe P6 y dificulta el mantenimiento entre generaciones de estudiantes. |

---

## 6. Estrategia de tests (PHPUnit, `php artisan test`)

### 6.1 Reglas

1. **TDD estricto:** por cada RF, primero el test que falla, luego la implementación mínima. Un test rojo detiene la fase.
2. **Unit** para todo `app/Pos/`: sin BD, sin HTTP, sin reloj real; `$today` siempre inyectado.
3. **Feature** para controladores, permisos, transacciones y persistencia; una base PostgreSQL de pruebas recreada por test.
4. **Nombres en inglés**, un test por regla de negocio, con el RF en el nombre del método o en el docblock.
5. Prohibido `markTestSkipped`, tests comentados o `withoutExceptionHandling()` para tapar errores.

### 6.2 Cobertura por archivo

| Test | Tipo | RF que cubre |
|---|---|---|
| `tests/Unit/Pos/PriceCalculatorTest.php` | Unit | RF-4, RF-12, RF-19 (IVA incluido, descuentos, redondeo, total = suma de líneas) |
| `tests/Unit/Pos/CartTest.php` | Unit | RF-2, RF-3, RF-5 (duplicados incrementan, cantidad inválida, carrito vacío) |
| `tests/Unit/Pos/DiscountPolicyTest.php` | Unit | RF-13 a RF-18, RF-39, RF-40 (tope 10%, convenio, autorización, total no negativo) |
| `tests/Unit/Pos/PrescriptionPolicyTest.php` | Unit | RF-7, RF-8, RF-9, RF-11 (categorías, datos faltantes, vencimiento a 30 días, reutilización) |
| `tests/Unit/Pos/StockPolicyTest.php` | Unit | RF-3, RF-27, RF-29 (faltantes, cantidad mayor al stock) |
| `tests/Unit/Pos/CashChangeCalculatorTest.php` | Unit | RF-21 (vuelto exacto, monto insuficiente) |
| `tests/Unit/Pos/FolioAllocatorTest.php` | Unit | RF-23, RF-24, RF-26 (folio único, rango agotado, no repetir) |
| `tests/Unit/Pos/VoidPolicyTest.php` | Unit | RF-32, RF-35 (anulación única, motivo obligatorio) |
| `tests/Feature/Pos/CartFlowTest.php` | Feature | RF-1 a RF-6 (búsqueda, agregar, quitar, totales, carrito vacío, persistencia tras recarga) |
| `tests/Feature/Pos/PrescriptionFlowTest.php` | Feature | RF-8 a RF-11 (bloqueo de cobro, registro de controlados, receta repetida) |
| `tests/Feature/Pos/DiscountFlowTest.php` | Feature | RF-13 a RF-19, RF-39, RF-40 (aplicación, autorización, convenios activos e inactivos) |
| `tests/Feature/Pos/CheckoutFlowTest.php` | Feature | RF-20 a RF-26 (medios de pago, vuelto, venta registrada, documento, reimpresión) |
| `tests/Feature/Pos/StockConcurrencyTest.php` | Feature | RF-27 a RF-31 (dos cajas, última unidad, conflicto, descuento único, reposición) |
| `tests/Feature/Pos/VoidSaleTest.php` | Feature | RF-31 a RF-36 (anulación, inmutabilidad, reversa, auditoría) |
| `tests/Feature/Pos/SessionDurabilityTest.php` | Feature | RF-6, RNF-6 (recarga y error no pierden carrito ni sesión) |
| `tests/Feature/Admin/SaleQueryTest.php` | Feature | RF-37, RF-38 (filtros, detalle completo) |
| `tests/Feature/Admin/ConventionManagementTest.php` | Feature | RF-39, RF-40 (alta, desactivación, vigencia) |
| `tests/Feature/Admin/DocumentRetryTest.php` | Feature | RF-24, RF-26 (pendiente, reintento, no duplicar folio) |
| `tests/Feature/Admin/PermissionTest.php` | Feature | RNF-8, RF-16, RF-32, RF-36 (cajero no anula ni autoriza) |

### 6.3 Pruebas de concurrencia (RF-27, RF-28)

Se ejecutan con dos conexiones reales a PostgreSQL: la primera abre la transacción y retiene el bloqueo; la segunda intenta vender la misma unidad y debe recibir el conflicto. No se simula con mocks, porque el objetivo es verificar el bloqueo real.

### 6.4 Orden de implementación

1. **Fase A — Dominio:** `Money`, `Cart`, `PriceCalculator`, `DiscountPolicy`, `PrescriptionPolicy`, `StockPolicy`, `CashChangeCalculator`, `FolioAllocator`, `VoidPolicy` con sus tests unitarios (verde antes de seguir).
2. **Fase B — Persistencia:** migraciones, modelos, factories y seeders de productos y convenios.
3. **Fase C — Caja:** búsqueda, carrito, totales y receta (RF-1 a RF-11).
4. **Fase D — Cobro:** checkout transaccional, documento simulado, comprobante (RF-20 a RF-26).
5. **Fase E — Stock y anulación:** concurrencia, anulación y auditoría (RF-27 a RF-36).
6. **Fase F — Administración:** convenios, historial, detalle y reintento (RF-37 a RF-40).
7. **Fase G — Cierre:** demo manual de los criterios de finalización y revisión de cobertura RF por RF.

### 6.5 Comandos

```bash
docker compose up -d
php artisan migrate --seed
php artisan test --testsuite=Unit
php artisan test
```

---

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Doble venta por doble clic | `checkout_token` único + bloqueo del botón en JS + transacción única. |
| Diferencias de redondeo entre pantalla y comprobante | Un único `PriceCalculator` para ambos; tests de RF-19 con montos límite. |
| Rango de folios agotado | Venta queda pendiente sin consumir folio (caso 20) y alerta al administrador. |
| Pérdida del carrito por despliegue | Carrito en sesión persistente + reconstrucción desde `CartService` (RF-6). |
| Tests de concurrencia inestables | PostgreSQL real en Docker, bloqueo explícito y `RefreshDatabase` por test. |
| Crecimiento del alcance | Todo lo no listado en la spec v1.0 queda fuera; cualquier agregado requiere spec nueva. |
