# Tareas 004 — Punto de venta asíncrono

**Spec:** `specs/004-pos-async/spec.md` v1.0 (aprobada) · **Plan:** `specs/004-pos-async/plan.md`
**Metodología:** TDD — test rojo primero, código después, `php artisan test` en verde.
**Estado:** T31, T33, T34 y T35 cerradas; T32 con pendiente parcial (API JSON hecha, interfaz de descuentos pendiente).

---

## Tareas (T31–T35)

- [x] **T31. Carrito y búsqueda asíncronos.** RF-85, RF-86, RF-90, RF-91 (spec 004)
  - *Depende de:* —
  - *Alcance:* `resources/js/pos/cart.js`, `resources/views/pos/index.blade.php` (buscador y acciones del carrito con fallback de formulario), content negotiation en `PosController` (JSON vs HTML), textos en `lang/es/pos.php`.
  - *Hecho cuando:* agregar, cambiar cantidad y quitar una línea del carrito no recargan la página; la búsqueda con debounce devuelve coincidencias sin recargar; ante cualquier fallo el carrito se conserva y se muestra el motivo; sin JavaScript el POS sigue funcionando con recargas completas.
  - *Cierre:* **pendiente parcial** (2026-11-19). Hecho: API JSON en `PosController` (`expectsJson()` en `index`, `add`, `update` y `remove`, que devuelve carrito, totales y resultados) y pruebas `CartAsyncTest` en verde (**9 tests, 43 aserciones**; test previo en rojo: 6 failed, 3 passed). Pendiente: `resources/js/pos/cart.js` no existe, así que agregar, cambiar o quitar líneas sigue recargando la página; la búsqueda sin recargar y el aviso con el motivo del fallo no están en la interfaz. **Cierre final (2026-11-19):** interfaz hecha. `resources/js/pos/cart.js` intercepta los formularios con `data-pos-async` (agregar, cambiar cantidad, quitar y búsqueda con debounce de 300 ms); el servidor devuelve los fragmentos Blade `cart` y `results` (`@fragment`) y el cliente sólo los pinta; ante un fallo el carrito no se toca y se muestra el motivo en `#pos-alert` (`role="alert"`); 419 renueva el token y reintenta una vez; 401 lleva al login. Textos en `pos.async` de `lang/es/pos.php`. Contrato nuevo: `scan=0` en la búsqueda al teclear (nunca agrega) y 422 con `errors.barcode` para un código rechazado por JSON. `CartAsyncTest` pasó de 9 a 33 tests. Verificado en Chrome DevTools a 375 px (sin desborde, doble clic, fallo de stock, sin red, 419, sesión vencida).

- [ ] **T32. Descuentos asíncronos.** RF-87 (spec 004)
  - *Depende de:* T31
  - *Alcance:* aplicar y quitar descuento global y de línea por AJAX, incluida la autorización de supervisor (tope de 10% de spec 001, RF-14 a RF-16) sin recargar.
  - *Hecho cuando:* descuentos globales y de línea se reflejan en los totales sin recargar; una autorización de supervisor pendiente no se pierde entre operaciones del carrito.
  - *Cierre:* **pendiente parcial** (2026-11-19). Hecho: `applyDiscount`, `removeDiscount`, `applyLineDiscount` y `removeLineDiscount` responden JSON con `cartPayload()` cuando `expectsJson()`, y sobre el tope (10%) piden credenciales de supervisor en la misma petición (RF-83); pruebas `DiscountAsyncTest` en verde (**8 tests, 27 aserciones**; test previo en rojo: 5 failed, 3 passed). Pendiente: la interfaz todavía aplica descuentos con recarga completa, porque `resources/js/pos/cart.js` no existe; los totales no se actualizan sin recargar.

- [x] **T33. Stock vigente (sondeo).** RF-88 (spec 004)
  - *Depende de:* T31
  - *Alcance:* `resources/js/pos/stock.js`; sondeo ligero cada 10 s del stock de las líneas del carrito; aviso cuando se agota o sale de catálogo.
  - *Hecho cuando:* el stock mostrado se mantiene vigente sin recargar; el cajero recibe un aviso `aria-live` si una línea se agota o se desactiva; un producto sin stock no puede agregarse ni aumentarse.
  - *Cierre:* **pendiente parcial** (2026-11-19). Hecho: endpoint `GET /pos/stock` (`PosController::stock`) que devuelve `id`, `stock` y `active` por producto; productos inexistentes se saltan y una lista vacía no consulta nada; pruebas `PosStockPollTest` en verde (**6 tests, 17 aserciones**; test previo en rojo: 6 failed, 0 passed). Pendiente: `resources/js/pos/stock.js` no existe, así que no hay sondeo cada 10 s en la interfaz ni aviso `aria-live` cuando una línea se agota o sale de catálogo. **Cierre final (2026-11-19):** `resources/js/pos/stock.js` sondea cada 10 s (una petición por intervalo, pausada con la pestaña oculta o mientras el cajero opera), anota cada línea (`max` del campo y nota visible), avisa por `#pos-status` (`aria-live`) y, si el producto sale del catálogo, quita la línea con `DELETE /pos/cart/{id}`. `PosStockPollTest` pasó de 6 a 12 tests.

- [x] **T34. Cobro a salvo.** RF-89 (spec 004)
  - *Depende de:* T31
  - *Alcance:* `POST /pos/checkout` (`store()`) verifica stock y catálogo de cada línea justo antes de crear la venta; si algo cambió, rechaza con `cart` error y conserva el carrito.
  - *Hecho cuando:* una venta no se confirma si una línea supera el stock disponible o el producto salió del catálogo; el carrito se conserva tras un cobro rechazado.
  - *Cierre:* **cerrada el 2026-11-19.** Test escrito primero en rojo (`CheckoutAsyncTest`: **4 failed, 0 passed**), verde en **4 tests, 13 aserciones**. `assertCartStillValid()` se envuelve en try-catch que convierte `InsufficientStock` en `ValidationException` con clave `cart`, usando el mensaje `concurrent_stock` que ya usa `CheckoutService`. Suite completa: **431 tests, 3241 aserciones, 0 fallos** (47,24 s), desde 427/3228. Pint en verde.

- [x] **T35. Casos límite del POS asíncrono.** RF-85 a RF-90, casos límite 1 a 8 (spec 004)
  - *Depende de:* T31, T32, T33, T34
  - *Alcance:* doble clic e idempotencia con `checkout_token` (RF-22); manejo de race condition en `sales.token` (índice único, `QueryException` 23505 → retorna la venta existente).
  - *Hecho cuando:* dos cobros con el mismo carrito y token crean una sola venta; el race condition de token duplicado no produce error 500.
  - *Cierre:* **cerrada el 2026-11-19.** Test escrito primero en rojo (`IdempotencyTest`: **1 failed, 1 passed**), verde en **2 tests, 11 aserciones**. `CheckoutService::checkout()` captura `QueryException` 23505 en el `create()` y retorna la venta existente en lugar de un error 500. Suite completa: **433 tests, 3252 aserciones, 0 fallos** (49,93 s), desde 431/3241. Pint en verde.
  - *Nota:* los casos límite restantes (sesión expirada, 419 CSRF, caída de red, dos cajeros y último stock) quedan para una tarea posterior; el cobro a salvo (T34) ya cubre el caso de dos cajeros con stock insuficiente al confirmar.

---

## Cobertura RF por tarea

| Tarea | RF | RNF |
|---|---|---|
| T31 | 85, 86, 90, 91 | 29, 31, 32 |
| T32 | 87 | 29, 30 |
| T33 | 88 | 32, 33 |
| T34 | 89 | 30 |
| T35 | 85, 86, 87, 88, 89, 90 | — |

---

## Estado actual del flujo SDD

| Fase | Estado |
|---|---|
| Constitución | ✅ v1.0 |
| Spec 001-pos | ✅ v1.1 |
| Spec 002-dashboard | ✅ v1.1 |
| Spec 003-auth | ✅ v1.4 |
| Spec 004-pos-async | ✅ v1.0 (aprobada) |
| Plan 004 | ✅ Completo (`specs/004-pos-async/plan.md`) |
| Tasks 004 | ✅ Definidas (T31–T35) |
| T31, T33 | ✅ Cerradas · interfaz JS (`cart.js`, `stock.js`) y pruebas de contrato |
| T32 | ⏳ Pendiente parcial · API JSON hecha; la interfaz de descuentos sigue con recarga completa |
| T34–T35 | ✅ Cerradas |
| Verificación visual 375 px | ⏳ Pendiente (se valida al cerrar cada tarea con interfaz) |

---

