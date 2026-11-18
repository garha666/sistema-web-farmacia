# Tareas 003 — Autenticación y acceso

**Spec:** `specs/003-auth/spec.md` v1.0 (aprobada) · **Plan:** `specs/003-auth/plan.md`
**Metodología:** TDD — test rojo primero, código después, `php artisan test` en verde.
**Estado:** T19 en curso; T20–T23 planificadas.

> Nota: la skill `sdd` no está instalada; el formato sigue lo acordado en `AGENTS.md` y la constitución, igual que `specs/002-dashboard/tasks.md`.

## Reglas de ejecución

- Una tarea a la vez. No se empieza T(n+1) sin T(n) en verde y marcada.
- Cada tarea deja la suite completa en verde, no sólo sus tests, y entrega el conteo real de Tests y Assertions copiado de la corrida.
- Los nombres de tests, clases, rutas y columnas van en inglés; los textos de interfaz en español, siempre desde `lang/es/pos.php`.
- Lógica de negocio en `app/Pos/**` (pura) y `app/Services/**` (orquestación); los controladores sólo validan y orquestan.
- Un commit por tarea: `feat(pos):`, `test(pos):` o `docs(pos):`, mensaje en inglés sin tildes.
- Nada fuera de la spec 003: lo detectado se anota en el reporte de la tarea, no se arregla de paso.
- Validación visual con Chrome DevTools MCP a 375 px antes de cerrar cada tarea con interfaz.
- `AGENTS.md` y `MEMORY.md` se actualizan al cerrar cada tarea.

---

## Tareas (T19–T23)

- [x] **T19. Pantalla de inicio de sesión, autenticación y cierre de sesión.** RF-78, RF-79, RF-80, RF-81, RF-82, RNF-23, RNF-24, RNF-25
  - *Depende de:* —
  - *Alcance:* `LoginController`, `LoginRequest` con el freno por `RateLimiter`, `layouts/guest.blade.php`, `auth/login.blade.php`, las rutas `login`/`logout` y la sección `login` de `lang/es/pos.php`.
  - *Hecho cuando:* `LoginTest` pasa: la pantalla responde 200 al invitado y ofrece correo y contraseña; cada uno de los cuatro roles aterriza en su panel; un rol no reconocido no llega a ningún panel; correo inexistente y contraseña incorrecta dan el mismo mensaje; tras varios intentos fallidos se frena y se indica la espera; un acierto limpia el contador; tras cerrar sesión una ruta protegida vuelve a pedir credenciales; un usuario autenticado que abre `/login` va a su panel; la contraseña no aparece en el HTML.
  - *Cierre:* **cerrada el 2026-11-18.** `LoginTest` (13 tests) escrito primero en rojo (**12 failed, 0 assertions**, `Route [login] not defined`) y después implementación mínima. Objetivo en verde: **13 tests, 83 aserciones**. Suite completa: **373 tests, 1930 aserciones, 0 fallos** (52,67 s); la partida era 360/1847. `LoginController` sólo orquesta; `LoginRequest` valida forma y concentra `Auth::attempt` con el freno por `RateLimiter` (5 intentos por minuto, clave correo + IP, `clear()` al acertar); el destino sale de `LandingPolicy` (spec 002) y un rol no reconocido cae en `dashboard.home`, que responde 403. Rutas `login` (GET/POST) y `logout` (POST); guard `web` ya existente, sin dependencias nuevas. Dentro de la tarea se detectó que **la ruta de logout no tenía ningún botón**: se agregó el formulario de cerrar sesión a la barra lateral y móvil de `layouts/admin` y al encabezado de `layouts/pos`, con su test. También hubo que ajustar `ChemistPanelTest::test_the_panel_only_offers_read_only_links`, que aseveraba «ningún `<form>`» (RF-55): ahora comprueba que todos los formularios del panel del Químico sean el de cerrar sesión, sin relajar el requisito. Verificación con Chrome DevTools MCP a 375×812: pantalla 200 con correo, contraseña y CSRF, `scrollWidth=375`, consola limpia; contraseña incorrecta y correo inexistente devuelven el mismo mensaje; los cuatro roles iniciaron sesión desde el navegador y aterrizaron en `/panel/admin`, `/panel/quimico`, `/panel/tecnico` y `/panel/bodeguero` con `scrollWidth=375` y el botón «Cerrar sesión» visible; la contraseña escrita no vuelve al HTML.

- [ ] **T20. `.env.example` del stack real.** H1 del informe de revisión
  - *Depende de:* T19 (para correr la suite y la demo)
  - *Alcance:* `.env.example` con `APP_TIMEZONE=America/Santiago`, `DB_CONNECTION=pgsql` y credenciales, `APP_LOCALE=es` y `SESSION_DRIVER=database`; test que falle si `config('app.timezone') !== config('pos.timezone')`.
  - *Hecho cuando:* copiar `.env.example` a `.env` deja un entorno equivalente al de la suite y el test de zona horaria pasa.
  - *Cierre:* (pendiente)

- [ ] **T21. Feedback del cierre de caja.** H2 del informe de revisión
  - *Depende de:* —
  - *Alcance:* clave `pos.cash_register.status_closed`, mensaje de cierre con la diferencia en `dashboard/tecnico.blade.php` y test que lo cubra (hoy la pantalla muestra la etiqueta del botón «Cerrar caja» y nunca muestra la diferencia).
  - *Hecho cuando:* tras cerrar, el panel muestra un mensaje de cierre y la diferencia; el test lo comprueba.
  - *Cierre:* (pendiente)

- [ ] **T22. Enmienda: definición de «anulación del día».** H3 del informe de revisión
  - *Depende de:* —
  - *Alcance:* decisión escrita en la spec 002 (fecha de venta o fecha de anulación) y alineación de `SalesDayQuery` y `ChemistPanelService`; test con una venta de ayer anulada hoy.
  - *Hecho cuando:* los paneles del Administrador y del Químico reportan la misma cifra para el mismo período.
  - *Cierre:* (pendiente)

- [ ] **T23. Ajustes menores del informe de revisión.** B1 a B7
  - *Depende de:* T19
  - *Alcance:* anotación `SaleFactory` inexistente (`app/Models/Sale.php`), filtro `active` en `CartService::load()`, control de pertenencia en `PosController::receipt()`, `active` en `AdjustStockRequest`, inyección de `VoidSaleService` en lugar de `app()`, y las nueve etiquetas `RF-nn` faltantes en tests de la spec 002.
  - *Hecho cuando:* cada ajuste entra con su test o con su corrección documentada y la suite sigue en verde.
  - *Cierre:* (pendiente)

---

## Cobertura RF por tarea

| Tarea | RF | RNF |
|---|---|---|
| T19 | 78, 79, 80, 81, 82 | 23, 24, 25 |
| T20 | — | 16 (zona horaria, spec 002) |
| T21 | 59 (spec 002) | 22 (spec 002) |
| T22 | 46, 54, 75 (spec 002) | — |
| T23 | — | — |

---

## Estado actual del flujo SDD

| Fase | Estado |
|---|---|
| Constitución | ✅ v1.0 |
| Spec 001-pos | ✅ v1.1 |
| Spec 002-dashboard | ✅ v1.0 implementada (360 tests, 1847 aserciones) |
| Spec 003-auth | ✅ Aprobada v1.0 |
| Plan 003 | ✅ Completo (`specs/003-auth/plan.md`) |
| T19 | ✅ Cerrada |
| T20–T23 | ⏳ Planificadas (mejoras del informe de revisión) |
| Auditoría RF por RF 002 | ⏳ Pendiente |

---

*Actualizado: 2026-11-18. Suite actual: 373 tests, 1930 aserciones, 0 fallos.*
