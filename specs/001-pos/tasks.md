# Tareas 001 — Punto de Venta (POS)

**Spec:** `specs/001-pos/spec.md` v1.0 (aprobada) · **Plan:** `specs/001-pos/plan.md`
**Metodología:** TDD — test rojo primero, código después, `php artisan test` en verde.
**Estado:** propuesta, pendiente de aprobación.

> Nota: la skill `sdd` no está instalada; el formato sigue lo acordado en `AGENTS.md` y la constitución.

## Reglas de ejecución

- Una tarea a la vez. No se empieza T(n+1) sin T(n) en verde y marcada.
- Cada tarea deja la suite completa en verde, no sólo sus tests.
- Los nombres de tests y código van en inglés; los textos de interfaz en español.
- **Desviación de entorno aprobada (2026-10-30):** no hay Docker; se usa el PostgreSQL 18 local (`farmacia_pos`, `farmacia_pos_test`) en lugar de PostgreSQL 16 en contenedor. El resto del stack se mantiene.

---

## Tareas

- [x] **T1. Esqueleto Laravel 11 + Docker + dominio de dinero y carrito.** RF-2, RF-3, RF-5, RF-19
  - *Depende de:* —
  - *Hecho cuando:* `docker compose up -d` levanta app y PostgreSQL 16; `CartTest` y `MoneyTest` pasan en verde con cantidad inválida, duplicados, carrito vacío y rechazo de `float`.
  - *Cerrada 2026-10-30:* Laravel 11.57.1 + PostgreSQL 18 local (sin Docker, desviación aprobada); `php artisan test` → 22 passed, 33 assertions.

- [x] **T2. Cálculo de precios, IVA incluido y redondeo.** RF-4, RF-12, RF-19
  - *Depende de:* T1
  - *Hecho cuando:* `PriceCalculatorTest` pasa con 5 montos límite y en todos los casos el total es igual a la suma de líneas menos descuentos.
  - *Cerrada 2026-10-30:* 14 tests nuevos; `php artisan test` → 36 passed, 70 assertions.

- [x] **T3. Política de descuentos y convenios de salud.** RF-13, RF-14, RF-15, RF-16, RF-17, RF-18, RF-39, RF-40
  - *Depende de:* T2
  - *Hecho cuando:* `DiscountPolicyTest` pasa: 10% se aplica sin supervisor, 10,01% lo exige, convenio inactivo se rechaza y ningún descuento deja el total en cero o negativo.
  - *Cerrada 2026-10-30:* 16 tests nuevos; `php artisan test` → 52 passed, 99 assertions.

- [x] **T4. Política de recetas y controlados.** RF-7, RF-8, RF-9, RF-10, RF-11
  - *Depende de:* T1
  - *Hecho cuando:* `PrescriptionPolicyTest` pasa con receta vencida a 31 días (fecha inyectada), datos faltantes, producto controlado y receta reutilizada.
  - *Cerrada 2026-10-30:* 15 tests nuevos; `php artisan test` → 67 passed, 131 assertions.

- [x] **T5. Política de stock y faltantes.** RF-3, RF-27, RF-29
  - *Depende de:* T1
  - *Hecho cuando:* `StockPolicyTest` pasa y `shortages()` devuelve la diferencia exacta entre solicitado y disponible para cada producto.
  - *Cerrada 2026-10-30:* 14 tests nuevos; `php artisan test` → 81 passed, 159 assertions.

- [x] **T6. Pantalla de caja: búsqueda, carrito y totales en vivo.** RF-1, RF-2, RF-3, RF-4, RF-5, RF-6
  - *Depende de:* T1, T2
  - *Hecho cuando:* `CartFlowTest` pasa y, en prueba manual, recargar el navegador conserva el carrito con líneas y descuentos.
  - *Cerrada 2026-10-30:* 13 tests nuevos; `php artisan test` → 94 passed, 182 assertions. Assets compilados con Vite + Tailwind.

- [x] **T7. Cobro transaccional, folio simulado y comprobante.** RF-20, RF-21, RF-22, RF-23, RF-24, RF-25, RF-26
  - *Depende de:* T2, T3, T4, T5, T6
  - *Hecho cuando:* `CheckoutFlowTest` pasa: venta confirmada con folio único, stock descontado, comprobante reimprimible y doble envío del mismo carrito no crea dos ventas.
  - *Cerrada 2026-10-30:* 14 tests nuevos; `php artisan test` → 108 passed, 229 assertions.

- [x] **T8. Concurrencia real de stock y movimientos.** RF-27, RF-28, RF-29, RF-30, RF-31
  - *Depende de:* T7
  - *Hecho cuando:* el test de dos conexiones a PostgreSQL deja ganar una sola venta de la última unidad, y la suma de movimientos iguala el stock final.
  - *Cerrada 2026-10-30:* 7 tests nuevos; `php artisan test` → 115 passed, 251 assertions.

- [ ] **T9. Anulación de venta y auditoría.** RF-32, RF-33, RF-34, RF-35, RF-36
  - *Depende de:* T8
  - *Hecho cuando:* `VoidSaleTest` pasa: la anulación repone stock, genera la reversa simulada, registra autor y motivo, impide una segunda anulación y deja intacta la venta original.

- [ ] **T10. Administración: convenios, historial, documentos y permisos.** RF-26, RF-37, RF-38, RF-39, RF-40
  - *Depende de:* T3, T9
  - *Hecho cuando:* `SaleQueryTest`, `ConventionManagementTest`, `DocumentRetryTest` y `PermissionTest` pasan; un usuario Técnico recibe 403 al intentar anular o autorizar.

---

## Cobertura RF por tarea

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

RF-1 a RF-40: **cobertura completa**, sin huecos.

## Aviso de tamaño

Cada tarea agrupa entre 3 y 8 RF. En la práctica, ninguna cabe en 20–30 minutos. Estimación realista: **2 a 4 horas por tarea**. Si se necesita el límite de 30 minutos, hay que dividir cada una en subtareas `Tn.1`, `Tn.2`, `Tn.3` (por ejemplo T3 → T3.1 tope del 10%, T3.2 convenios, T3.3 autorización de supervisor). No propongo dividir la spec, porque los 40 RF forman un flujo único y coherente.
