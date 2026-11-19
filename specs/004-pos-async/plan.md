# Plan técnico 004 — Punto de venta asíncrono

**Spec de referencia:** `specs/004-pos-async/spec.md` v1.0 (aprobada)
**Specs relacionadas:** `specs/001-pos/spec.md` v1.1 (POS: busca, carrito, descuentos, cobro), `specs/002-dashboard/spec.md` v1.1, `specs/003-auth/spec.md` v1.4 (guard de sesión, RBAC, bitácora)
**Constitución:** `docs/constitution.md` v1.0
**Stack:** Laravel 11 · Blade + Tailwind · PostgreSQL 16 · axios (ya instalado) · guard de sesión `web` · Vite
**Estado:** listo para implementar (TDD), tarea T31

> Nota: la skill `sdd` no está instalada. El plan replica la estructura de `specs/003-auth/plan.md` y las reglas de la constitución, que es la fuente normativa real.

---

## 0. Cumplimiento constitucional (trazabilidad)

| Principio | Cómo lo cumple este plan |
|---|---|
| **P1 Stack fijo** | Sólo Laravel 11, Blade, Tailwind y axios (ya en `package.json`). Cero frameworks JS nuevos, cero ORM alternos, cero servicios externos. |
| **P2 Spec manda** | Cada ruta, controlador, vista y test declara su RF o RNF de la spec 004. El código nace de la spec; cualquier diferencia es error del código. |
| **P3 Lógica pura, interfaz tonta** | Precios, descuentos, tope y stock ya viven en clases puras (`PriceCalculator`, `DiscountPolicy`, `CartService`, `SalesDayQuery`). Este plan no mueve lógica al cliente: el servidor sigue siendo el único dueño del carrito y el carrito en sesión es la fuente de verdad. |
| **P4 Test primero** | `CartFlowTest`, `CheckoutFlowTest` y `PosConcurrencyTest` se amplían en rojo antes del código; la suite completa queda en verde antes del commit. |
| **P5 Datos protegidos** | La sesión no se toca por las peticiones asíncronas; el carrito en sesión se conserva ante cualquier fallo; no hay cambios de esquema ni de API pública. |
| **P6 Idioma** | Clases, rutas y tests en inglés; todo texto de interfaz en `lang/es/pos.php` vía `__()`, incluidos los avisos de stock y error asíncrono. |

---

## 1. Archivos que se crean o modifican

Todas las rutas son relativas a la raíz del proyecto Laravel.

### 1.1 Se crean

| Archivo | Responsabilidad | RF / RNF |
|---|---|---|
| `tests/Feature/Pos/CartAsyncTest.php` | RF-85, RF-86, RF-87, RF-90, RF-91 y casos límite 1 a 4, 6, 7, escritos primero en rojo. | RF-85 a RF-87, RF-90, RF-91 |
| `tests/Feature/Pos/CheckoutAsyncTest.php` | RF-89 (cobro a salvo) y caso límite 8, escritos primero en rojo. | RF-89 |
| `tests/Feature/Pos/PosStockPollTest.php` | RF-88 (stock vigente, refresco sin recargar), escrito primero en rojo. | RF-88 |
| `resources/js/pos/cart.js` | Lógica de cliente: envío asíncrono de carrito, búsqueda con debounce, descuentos, manejo de errores y avisos `aria-live`. | RF-85 a RF-90, RNF-31, RNF-32 |
| `resources/js/pos/stock.js` | Sondeo del stock de las líneas del carrito y aviso de agotamiento/salida de catálogo. | RF-88, RNF-33 |

### 1.2 Se modifican

| Archivo | Cambio | RF / RNF |
|---|---|---|
| `resources/views/pos/index.blade.php` | El buscador y los botones del carrito envían por AJAX cuando JS está disponible; conservan el formulario como respaldo. Avisos `aria-live` para carrito y errores. | RF-85, RF-86, RF-90, RNF-32 |
| `resources/views/pos/checkout.blade.php` | El cobro verifica stock y catálogo justo antes de confirmar (RF-89). | RF-89 |
| `resources/js/app.js` o `resources/js/bootstrap.js` | Registra `axios` y el token CSRF para peticiones asíncronas del POS. | RNF-30 |
| `app/Http/Controllers/PosController.php` | Los métodos del carrito y búsqueda responden JSON cuando la petición lo pide (content negotiation). | RF-85 a RF-87, RNF-29 |
| `lang/es/pos.php` | Sección `pos.async` con avisos de stock, errores de carrito y descuento. | RNF-25 |
| `specs/001-pos/spec.md` §7 | La línea de interacción asíncrona del POS pasa a remitir a la spec 004 (§11). | — |
| `AGENTS.md` | Spec activa y estado del repositorio. | — |
| `MEMORY.md` | Registro de la tarea T31 con su conteo real de tests. | — |

### 1.3 No se toca

- `app/Services/Pos/CartService.php`, `app/Pos/Calculator/PriceCalculator.php`, `app/Pos/Policy/DiscountPolicy.php`, `app/Pos/Policy/StockPolicy.php`: la lógica de negocio ya es pura y se reutiliza sin cambios.
- `app/Http/Controllers/Cash/` y `app/Http/Controllers/Dashboard/`: fuera de alcance de este módulo.
- Migraciones y modelos: no hay cambios de esquema.
- `config/auth.php` y el middleware `role:*`: se reutilizan sin cambios.

---

## 2. Decisiones de implementación

- **D-17 — Content negotiation, mismo endpoint.** `PosController` detecta si la petición espera JSON (`Accept: application/json` o `X-Requested-With`) y responde con el carrito/totales serializados; de lo contrario devuelve la vista completa como hoy. No se crea una API paralela.
- **D-18 — axios (ya instalado), nada de framework nuevo.** Las peticiones asíncronas del POS usan `axios` con el token CSRF de Laravel (`X-XSRF-TOKEN`). Nada de React, Vue ni Alpine.
- **D-19 — Mejora progresiva.** Los formularios actuales (POST/PATCH/DELETE con recarga de página) siguen funcionando si JavaScript no está disponible o falla. El HTML `form` es el contrato base; JS lo intercepta y lo reemplaza por JSON.
- **D-20 — Sondeo (polling) del stock.** Una consulta ligera cada 10 s mientras el carrito no esté vacío devuelve el stock actual de las líneas; si cambió, se avisa con `aria-live`. No hay WebSockets ni SSE (fuera de alcance, D-20 de la spec).
- **D-21 — El cobro sigue en pantalla propia.** `/pos/checkout` mantiene su flujo de confirmación y recibo; solo añade la verificación final de stock y catálogo.
- **D-22 — El servidor es la fuente de verdad.** El cliente nunca calcula totales ni descuentos: los pide al servidor y pinta lo que este devuelve. El carrito en sesión nunca se sobrescribe desde el cliente.

---

## 3. Contrato de las pantallas (RNF-25, RNF-32)

- `GET /pos`:
  - invitado → `302` a `/login` (guard de sesión).
  - cajero/supervisor/admin → `200` con buscador, catálogo y carrito; cada acción tiene su formulario (`method` y `action`) como respaldo sin JS.
- `POST /pos/cart`, `PATCH /pos/cart/{product}`, `DELETE /pos/cart/{product}`, `POST /pos/cart/{product}/discount`, `DELETE /pos/cart/{product}/discount`, `POST /pos/discount`, `DELETE /pos/discount`:
  - petición HTML → respuesta completa de la página (como hoy).
  - petición JSON → JSON con `cart` (líneas, subtotal, descuentos, IVA, total), `stock` por producto y, en caso de error, `error` con clave de traducción.
- `GET /pos/search?q=...` (o reuso de `GET /pos?q=...`):
  - petición JSON → lista de productos coincidentes (`id`, `name`, `active_ingredient`, `barcode`, `price`, `stock`, `active`).
- `POST /pos/checkout`:
  - verifica stock y catálogo de cada línea antes de crear la venta; si algo cambió, devuelve error sin vaciar el carrito.
- A 375 px: sin desborde horizontal, avisos legibles, foco visible.

---

## 4. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| El carrito del cliente se desincroniza del servidor | El servidor es la fuente de verdad; el cliente pinta lo que el servidor devuelve tras cada acción (D-22). |
| Dos cajeros agotan el último producto al mismo tiempo | Verificación de stock justo antes de cobrar (RF-89) y sondeo periódico (RF-88); el segundo cajero ve el aviso al cobrar. |
| Sesión expirada en una petición asíncrona → carrito perdido | El carrito vive en sesión del servidor; al reautenticar se recupera. El cliente muestra el login sin vaciar nada (caso límite 5). |
| Doble clic rápido duplica una línea | El servidor valida idempotencia por `checkout_token`; el cliente deshabilita el botón mientras la petición va en vuelo. |
| Romper el POS existente sin JS | Mejora progresiva (D-19): los formularios originales siguen intactos y son testeados por los tests existentes. |
| Errores de consola o desbordes a 375 px | Validación con Chrome DevTools MCP en cada cambio de vista; `aria-live` sin romper el layout. |

---

## 5. Hitos de implementación (tareas)

| Tarea | RF | Qué entrega |
|---|---|---|
| T31 | RF-85, RF-86, RF-87, RF-90, RF-91 | Carrito y búsqueda asíncronos con fallback sin JS; tests en rojo y verde; suite en verde. |
| T32 | RF-87 | Descuentos (global y de línea) asíncronos, incluida autorización de supervisor sin recargar. |
| T33 | RF-88 | Sondeo del stock y avisos de agotamiento / salida de catálogo. |
| T34 | RF-89 | Cobro a salvo: verificación final de stock y catálogo antes de confirmar. |
| T35 | Casos límite 1 a 8 | Pruebas de borde: stock insuficiente, producto desactivado, doble clic, respuesta fuera de orden, sesión expirada, 419 CSRF, caída de red, dos cajeros. |
