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

- [x] **T20. `.env.example` del stack real.** H1 del informe de revisión
  - *Depende de:* T19 (para correr la suite y la demo)
  - *Alcance:* `.env.example` con `APP_TIMEZONE=America/Santiago`, `DB_CONNECTION=pgsql` y credenciales, `APP_LOCALE=es` y `SESSION_DRIVER=database`; test que falle si `config('app.timezone') !== config('pos.timezone')`.
  - *Hecho cuando:* copiar `.env.example` a `.env` deja un entorno equivalente al de la suite y el test de zona horaria pasa.
  - *Cierre:* **cerrada el 2026-11-18.** `EnvironmentTest` (4 tests) escrito primero en rojo (**2 failed, 2 passed, 7 assertions**) y después el arreglo: verde en **4 tests, 9 aserciones**. Suite completa: **377 tests, 1939 aserciones, 0 fallos** (64,53 s); la partida era 373/1930. `.env.example` declara `APP_TIMEZONE=America/Santiago`, `DB_CONNECTION=pgsql` con host, puerto, base `farmacia_pos` y usuario, `APP_LOCALE=es` y `SESSION_DRIVER=database`, con un comentario que explica que el huso debe coincidir con `pos.timezone` (si no, las ventas de 21:00 a 23:59 se escriben con fecha del día siguiente y los paneles «de hoy» las buscan en otro huso). El cambio sólo afecta a un clon nuevo: tests y entorno local leen `.env`, no `.env.example`. El test comprueba además que el archivo de ejemplo declare el huso del negocio, PostgreSQL y la sesión en base; se verificó que el guard **no es vacío** (con `APP_TIMEZONE=UTC` el primer test falla con su mensaje explicativo).

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

- [x] **T24. Freno a la autorización de supervisor.** RF-83, RNF-26 (spec 003)
  - *Depende de:* T19
  - *Alcance:* freno por `RateLimiter` al resolver las credenciales de supervisor en `PosController` (las tres vías: cobro, descuento global y descuento de línea) y clave `pos.errors.supervisor_throttled`. El registro del intento fallido pasa a T28.
  - *Hecho cuando:* tras varios intentos fallidos, ni siquiera la contraseña correcta del supervisor autoriza; una autorización correcta limpia el contador; el freno no cambia la sesión ni el rol del cajero; el mensaje indica cuánto esperar.
  - *Cierre:* **cerrada el 2026-11-18.** `SupervisorAuthorizationTest` (5 tests) escrito primero en rojo (**2 failed, 3 passed, 47 assertions**: los dos fallos demostraban el agujero, la sexta vez con la contraseña correcta autorizaba) y después el freno: verde en **5 tests, 50 aserciones**. Suite completa: **382 tests, 1989 aserciones, 0 fallos** (53,88 s); la partida era 377/1939, sin tests existentes rotos. La clave `supervisor-auth|{cajero}|{ip}` se comparte entre las tres vías con cinco intentos por minuto (`SUPERVISOR_MAX_ATTEMPTS`, `SUPERVISOR_DECAY_SECONDS`), `hit()` en cada fallo y `clear()` al acertar; el mensaje de freno (`pos.errors.supervisor_throttled`, con los segundos) es distinto del de credenciales inválidas y no cambia la sesión ni el rol. El registro del intento fallido quedó para T28.

- [x] **T25. Parche de dependencias (Symfony 7.4.23).** S2 del informe de seguridad
  - *Depende de:* —
  - *Alcance:* `composer update` acotado a `symfony/*` y verificación de la suite. Los avisos de `laravel/framework` quedan para una decisión aparte, porque exigen el salto a 12.x.
  - *Hecho cuando:* `composer audit` no reporta avisos de Symfony y la suite sigue en verde.
  - *Cierre:* **cerrada el 2026-11-18.** `composer update "symfony/*" --dry-run` mostró 12 actualizaciones de parche (7.4.22 → 7.4.23), sin altas ni bajas de paquetes; se aplicó y `composer.lock` quedó en 7.4.23. `composer audit` ya no reporta ningún aviso de Symfony: sólo sobreviven **5 avisos de `laravel/framework`** (CRLF en la regla `email`, URL firmadas temporales, XSS en la página de depuración y salida de `Concurrency`), todos sin parche en 11.x y que exigen el salto a 12.x — se registran como decisión aparte. Suite completa: **382 tests, 1989 aserciones, 0 fallos** (52,71 s), sin regresiones.
  - *Incidente de entorno:* `php artisan package:discover` falló con «directory must be present and writable» porque OneDrive repuso el atributo `ReadOnly` sobre `bootstrap/cache` (un *placeholder* de OneDrive, no un junction). PHP interpreta ese atributo como no escribible aunque `file_put_contents()` sí escriba. Se resolvió con `attrib -R bootstrap\cache`; conviene repetirlo si OneDrive vuelve a marcarlo.

- [x] **T26. Cabeceras de seguridad, proxies de confianza y HTTPS.** S4 y S5 · RNF-27
  - *Depende de:* —
  - *Alcance:* middleware de cabeceras (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy), `trusted_proxies` y forzado de HTTPS fuera de local.
  - *Cierre:* **cerrada el 2026-11-18.** `SecurityHeadersTest` (4 tests) escrito primero en rojo (**4 failed, 6 assertions**) y después el middleware: verde en **4 tests, 22 aserciones**. Suite completa: **386 tests, 2011 aserciones, 0 fallos** (48,67 s); la partida era 382/1989, sin tests existentes rotos. `SecurityHeaders` (global, en `bootstrap/app.php`) emite `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()` y una CSP con `default-src 'self'`, `script-src 'self' 'nonce-…'`, `frame-ancestors 'none'`, `base-uri 'self'`, `form-action 'self'` y `object-src 'none'`; el nonce se genera por petición y se comparte con las vistas como `$cspNonce`. Los dos guiones en línea (`pos/checkout.blade.php` y `cash/close.blade.php`) llevan el nonce y el `onclick` del comprobante pasó a un escuchador (`#print-receipt`). `trustProxies` sólo confía en los proxies de `TRUSTED_PROXIES` (ninguno por defecto) y `AppServiceProvider` fuerza HTTPS en producción. Verificado en el navegador a 375 px: cabeceras presentes en `/login`, consola limpia en login, panel, cierre de caja y comprobante, diferencia en vivo ($3.000 en contra) y `scrollWidth = 375`; el turno de prueba se borró y la base dev volvió a 0 turnos.
  - *Nota:* `style-src` mantiene `'unsafe-inline'` porque Vite inyecta estilos en desarrollo; no hay atributos `style` en las vistas.

- [x] **T27. Sacar `role` de `$fillable`.** S3 · RNF-28
  - *Depende de:* —
  - *Alcance:* `User::$fillable` sin `role`; el seeder y los tests asignan el rol de forma explícita.
  - *Cierre:* **cerrada el 2026-11-18.** `MassAssignmentTest` (4 tests) escrito primero en rojo (**3 failed, 1 passed, 7 assertions**); después: `role` fuera de `$fillable`, `User::assignRole()` como única vía de cambio (usa `forceFill`), `UserFactory::newModel()` fija el rol explícitamente para que los 74 llamados existentes (`create(['role' => …])`, `->admin()`, etc.) sigan funcionando, y `UserSeeder` provisiona el rol con `assignRole()`. Verde en **4 tests, 8 aserciones**; suite completa **390 tests, 2019 aserciones, 0 fallos** (50,32 s), desde 386/2011. El caso de prueba demuestra que `User::create([... 'role' => 'admin'])` deja el rol por defecto `tecnico`.

- [ ] **T28. Bitácora de seguridad.** S7
  - *Depende de:* T24
  - *Alcance:* registrar los intentos fallidos de autorización de supervisor y de inicio de sesión, sin credenciales, y traducir sus etiquetas en el panel.
  - *Cierre:* (pendiente)

- [ ] **T29. Textos de usuario fuera de `lang`.** S8
  - *Depende de:* —
  - *Alcance:* mover los siete literales en español de `RoleMiddleware`, `VoidPolicy`, `CheckoutService`, `ConventionService` y `DocumentService` a `lang/es/pos.php`.
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
| T24 | 83 | 26 |
| T25 | — | — |
| T26 | — | 27 |
| T27 | — | 28 |
| T28–T29 | — | — |

---

## Estado actual del flujo SDD

| Fase | Estado |
|---|---|
| Constitución | ✅ v1.0 |
| Spec 001-pos | ✅ v1.1 |
| Spec 002-dashboard | ✅ v1.0 implementada (360 tests, 1847 aserciones) |
| Spec 003-auth | ✅ Aprobada v1.3 (RF-83, RNF-26, RNF-27 y RNF-28) |
| Plan 003 | ✅ Completo (`specs/003-auth/plan.md`) |
| T19 | ✅ Cerrada |
| T20 | ✅ Cerrada |
| T24 | ✅ Cerrada |
| T25 | ✅ Cerrada |
| T26 | ✅ Cerrada |
| T27 | ✅ Cerrada |
| T21–T23, T28–T29 | ⏳ Planificadas (mejoras de los informes de revisión y seguridad) |
| Auditoría RF por RF 002 | ⏳ Pendiente |

---

*Actualizado: 2026-11-18. Suite actual: 390 tests, 2019 aserciones, 0 fallos.*
