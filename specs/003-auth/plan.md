# Plan técnico 003 — Autenticación y acceso

**Spec de referencia:** `specs/003-auth/spec.md` v1.0 (aprobada)
**Specs relacionadas:** `specs/001-pos/spec.md` v1.1 y `specs/002-dashboard/spec.md` v1.0 (el RBAC y el aterrizaje por rol se reutilizan sin cambios)
**Constitución:** `docs/constitution.md` v1.0
**Stack:** Laravel 11 · Blade + Tailwind · PostgreSQL 16 · guard de sesión `web`
**Estado:** listo para implementar (TDD), tarea T19

> Nota: la skill `sdd` no está instalada. El plan replica la estructura de `specs/002-dashboard/plan.md` y las reglas de la constitución, que es la fuente normativa real.

---

## 0. Cumplimiento constitucional (trazabilidad)

| Principio | Cómo lo cumple este plan |
|---|---|
| **P1 Stack fijo** | Sólo Laravel 11 y Blade. Se usa el guard de sesión `web` ya configurado en `config/auth.php`: cero dependencias nuevas (sin Breeze, Fortify ni Jetstream). |
| **P2 Spec manda** | Cada ruta, clase, vista y test declara su RF o RNF. La spec 003 nace antes que el código y enmienda la línea de «fuera de alcance» de 001 §7 y 002 §7. |
| **P3 Lógica pura, interfaz tonta** | El destino por rol ya es una función pura (`LandingPolicy`, sin `Auth` ni BD). El controlador sólo orquesta; la validación y el freno de intentos viven en el FormRequest. |
| **P4 Test primero** | `LoginTest` se escribe en rojo antes del controlador y de la vista; la suite completa queda en verde antes del commit. |
| **P5 Datos protegidos** | La sesión se regenera al entrar y se invalida al salir; la contraseña sólo se compara con `Hash`/`Auth::attempt` y nunca se registra; no hay migraciones ni cambios de datos. |
| **P6 Idioma** | Clases, rutas y tests en inglés; todo texto de interfaz en `lang/es/pos.php` vía `__()`, incluidos los mensajes de validación de `lang/es/validation.php`. |

---

## 1. Archivos que se crean o modifican

Todas las rutas son relativas a la raíz del proyecto Laravel.

### 1.1 Se crean

| Archivo | Responsabilidad | RF |
|---|---|---|
| `app/Http/Controllers/Auth/LoginController.php` | `show` (pantalla, o redirección al panel si ya hay sesión), `authenticate` (orquesta) y `destroy` (cierra sesión). | RF-78, RF-80, RF-81 |
| `app/Http/Requests/Auth/LoginRequest.php` | Validación de forma (correo, contraseña), `authenticate()` con `Auth::attempt` y el freno por `RateLimiter` (pareja correo + IP). | RF-79, RF-82, RNF-23 |
| `resources/views/layouts/guest.blade.php` | Layout mínimo para pantallas sin sesión: marca, tarjeta centrada y franja de acento esmeralda (S-8). | RNF-25 |
| `resources/views/auth/login.blade.php` | Formulario de acceso (correo, contraseña, botón de entrar) con etiquetas, foco visible y avisos de error. | RF-80, RNF-25 |
| `tests/Feature/Auth/LoginTest.php` | Los RF-78 a RF-82 y los casos límite 1 a 10, escritos primero en rojo. | RF-78 a RF-82 |

### 1.2 Se modifican

| Archivo | Cambio | RF |
|---|---|---|
| `routes/web.php` | Rutas `login` (GET/POST) y `logout` (POST). | RF-78, RF-80, RF-81 |
| `lang/es/pos.php` | Sección `login` con todos los textos de la pantalla y de los mensajes de error. | RNF-25 |
| `lang/es/validation.php` | Nombres legibles de `email` y `password`. | RNF-25 |
| `specs/001-pos/spec.md` §7 y `specs/002-dashboard/spec.md` §7 | La línea de autenticación pasa a remitir a la spec 003 (§10). | — |
| `AGENTS.md` | Spec activa y estado del repositorio. | — |
| `MEMORY.md` | Registro de la tarea T19 con su conteo real de tests. | — |

### 1.3 No se toca

- `config/auth.php` (el guard `web` y el proveedor `users` ya están correctos).
- `app/Http/Middleware/RoleMiddleware.php` y `LandingPolicy`: el RBAC y el aterrizaje por rol de la spec 002 se reutilizan tal cual.
- `bootstrap/app.php`: `redirectGuestsTo('/login')` ya apunta a la ruta que ahora existe.
- Migraciones y modelos: no hay cambios de esquema; `users` ya tiene `role`, `email` y `password`.

---

## 2. Decisiones de implementación

- **D11 — Freno en el FormRequest.** El contador de intentos se implementa con `RateLimiter` dentro de `LoginRequest::authenticate()`, que es el patrón canónico de Laravel y mantiene el controlador reducido a orquestar (`show`, `redirect`, `logout`). No se instala ningún paquete.
- **D12 — Destino calculado con `LandingPolicy`.** `authenticate()` no arrastra la URL pedida antes de entrar (evita terminar en un panel ajeno); el destino siempre es `LandingPolicy::panelRoute($role)` y, si el rol no se reconoce, `dashboard.home`, que responde 403 sin mostrar panel.
- **D13 — Limpieza del contador al acertar.** Un `Auth::attempt` correcto ejecuta `RateLimiter::clear($throttleKey)`.
- **D14 — Pantalla sin «recordarme».** No se incluye la casilla, coherente con D-15.

---

## 3. Contrato de la pantalla (RNF-25)

- `GET /login`:
  - invitado → `200` con el formulario (campos `email` y `password`, `@csrf`, etiquetas asociadas por `for`/`id`, foco visible con `focus-visible:ring`).
  - usuario autenticado → `302` a su panel.
- `POST /login`:
  - credenciales válidas → `302` al panel del rol; sesión regenerada.
  - credenciales inválidas → `302` de vuelta con el error en `email`, mismo mensaje para correo inexistente y contraseña incorrecta.
  - demasiados intentos → `302` de vuelta con el error en `email` indicando los segundos de espera.
- `POST /logout` (autenticado) → `302` a `/login`; sesión invalidada y token CSRF regenerado.
- A 375 px: tarjeta a una columna, sin desborde horizontal, sin errores de consola.

---

## 4. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| El error de credenciales revela si el correo existe (enumeración de cuentas) | Un solo mensaje para los dos casos (RF-79, D-12) y test que lo comprueba. |
| Fuerza bruta contra las cuentas de demo | `RateLimiter` por correo + IP con 5 intentos por minuto (RF-82). |
| Fijación de sesión | `session()->regenerate()` al autenticar y `invalidate()` + `regenerateToken()` al salir (RNF-24). |
| La contraseña se filtra a la vista o a la bitácora | Nunca se pasa de vuelta a la respuesta; `User::$hidden` ya oculta `password`; test de que no aparece en el HTML. |
| Romper el RBAC existente | No se toca el middleware ni las rutas de los paneles; el test de aterrizaje por rol de la spec 002 sigue en verde. |
