# Especificación 003 — Autenticación y acceso

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A)
**Estado:** aprobada · **Versión:** 1.2 (2026-11-18: agrega RNF-27, cabeceras de seguridad, proxies de confianza y HTTPS, tras la auditoría de seguridad) · **Ámbito:** sólo el QUÉ y el POR QUÉ

> **Relación con las specs anteriores.** La spec 001 (§7) y la 002 (§7) declararon la autenticación **fuera de alcance** «porque se asume existente». Las dos specs siguen vigentes en todo lo demás; esta spec 003 **agrega** la pantalla de inicio de sesión y el cierre de sesión, que hasta ahora sólo existían como precondición (el RBAC de `role:*` y el aterrizaje por rol de RF-41 a RF-45 ya funcionaban, pero no había forma de iniciar sesión desde el navegador: un invitado terminaba en error al ser enviado a `/login`).

---

## 1. Contexto y objetivo

El sistema está protegido por sesión y por roles, pero **la puerta de entrada nunca se construyó**: `bootstrap/app.php` envía al invitado a `/login`, y esa ruta no existe. En la práctica, nadie puede usar el sistema sin una sesión inyectada a mano. Este módulo entrega la pantalla de inicio de sesión, la autenticación real contra las credenciales almacenadas y el cierre de sesión, integrados con el RBAC (spec 001 §2) y con el aterrizaje por rol (spec 002, RF-41).

La regla que lo ordena: **un usuario entra con su cuenta y llega al panel de su rol, y no hay forma de entrar sin credenciales válidas.**

## 2. Usuarios / actores

| Actor | Rol RBAC | Qué hace en este módulo |
|---|---|---|
| **Administrador** | Admin | Inicia sesión con su cuenta y aterriza en el panel del Administrador. |
| **Supervisor de turno** | Químico | Inicia sesión y aterriza en el panel del Químico. |
| **Cajero** | Técnico | Inicia sesión y aterriza en el panel del Técnico. |
| **Bodeguero** | Bodeguero | Inicia sesión y aterriza en el panel del Bodeguero. |
| **Invitado** | — | Ve la pantalla de inicio de sesión y ningún panel. |

> **Alcance del alta de usuarios.** Esta spec define **cómo se entra** al sistema, no cómo se crean las cuentas: el alta, la asignación de roles y el cambio de contraseña siguen fuera de alcance (spec 002 §7) y se provisionan por seed o por base de datos.

## 3. Historias de usuario

- **H11** — Como **usuario** quiero entrar con mi correo y mi contraseña para llegar a mi panel y empezar a trabajar.
- **H12** — Como **usuario** quiero equivocarme con la contraseña sin que el sistema me diga si existe mi correo, para que nadie descubra qué cuentas hay.
- **H13** — Como **usuario** quiero cerrar sesión al terminar el turno para que nadie siga usando mi cuenta.
- **H14** — Como **responsable de la farmacia** quiero que un atacante no pueda probar contraseñas indefinidamente, para proteger las ventas y los controlados.

## 4. Requisitos funcionales

Notación EARS. «El sistema» es el módulo de acceso. La numeración continúa la de la spec 002.

- **RF-78 — Inicio de sesión.** Cuando un usuario envía su correo y su contraseña, el sistema deberá autenticarlo si las credenciales son válidas y llevarlo al panel que le corresponde según su rol (spec 002, RF-41).
  - *Criterio:* Con credenciales válidas de un rol reconocido, el usuario deberá terminar en el panel de ese rol.
  - *Criterio:* Con un rol no reconocido o sin rol, el usuario autenticado no deberá llegar a ningún panel (spec 002, caso límite 1).

- **RF-79 — Credenciales inválidas.** Si el correo o la contraseña no son correctos, entonces el sistema deberá rechazar el acceso e informarlo con **un mismo mensaje**, sin revelar si el correo existe.
  - *Criterio:* Un correo inexistente y una contraseña incorrecta deberán producir exactamente el mismo mensaje y el mismo resultado (ninguna sesión).

- **RF-80 — Pantalla de entrada del invitado.** Cuando un invitado pida la raíz o cualquier ruta protegida, el sistema deberá llevarlo a la pantalla de inicio de sesión, nunca a una página de error.
  - *Criterio:* La pantalla deberá pedir correo y contraseña y ofrecer el botón de entrar.
  - *Criterio:* Un usuario ya autenticado que abra la pantalla de inicio de sesión deberá ser llevado a su panel, sin volver a pedirle credenciales.

- **RF-81 — Cierre de sesión.** Cuando un usuario cierre su sesión, el sistema deberá invalidarla por completo y volver a la pantalla de inicio de sesión.
  - *Criterio:* Después de cerrar sesión, volver a pedir una ruta protegida deberá exigir credenciales de nuevo.

- **RF-82 — Protección contra fuerza bruta.** Si un mismo correo desde una misma dirección acumula intentos fallidos, entonces el sistema deberá frenar nuevos intentos e indicar cuánto esperar antes de reintentar.
  - *Criterio:* Un inicio de sesión correcto deberá limpiar el contador de intentos fallidos de ese correo.

- **RF-83 — Freno a la autorización de supervisor.** Si un usuario acumula intentos fallidos de autorización de supervisor —descuento sobre el tope o reutilización de receta—, entonces el sistema deberá frenar nuevos intentos e indicar cuánto esperar antes de reintentar.
  - *Criterio:* Una autorización correcta deberá limpiar el contador de intentos fallidos de ese usuario.
  - *Criterio:* El freno deberá cubrir las tres vías de autorización en línea: el cobro, el descuento global y el descuento de línea (spec 001, RF-16 y RF-11).
  - *Criterio:* La autorización de supervisor **no** es un inicio de sesión: no deberá cambiar la sesión ni el rol de quien la pide.

## 5. Requisitos no funcionales

Numeración continua con la spec 002.

- **RNF-23 — Almacenamiento de contraseñas.** Las contraseñas deberán guardarse sólo como hash y nunca mostrarse, registrarse en bitácora ni viajar de vuelta al navegador.
- **RNF-24 — Higiene de sesión.** El identificador de sesión deberá regenerarse al autenticar, y al cerrar sesión se deberá invalidar la sesión y regenerar el token CSRF.
- **RNF-25 — Idioma, formato y accesibilidad.** La pantalla deberá estar en español de Chile (RNF-16), con etiquetas en los campos, foco visible y utilizable a 375 px (RNF-17).
- **RNF-26 — Credenciales de supervisor.** La contraseña de supervisor deberá compararse sólo contra su hash y nunca deberá registrarse, mostrarse ni devolverse al navegador; el límite de intentos es el de RF-83.
- **RNF-27 — Cabeceras de seguridad, proxies y HTTPS.** SI el sistema atiende una petición, ENTONCES deberá responder con `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` restrictiva y una `Content-Security-Policy` que sólo permita recursos del propio sitio y guiones autorizados por nonce; SI el entorno es producción, ENTONCES deberá forzar HTTPS en las URLs generadas y confiar sólo en los proxies declarados en la configuración.
  - *Criterio:* Ningún guion en línea deberá ejecutarse sin el nonce de la petición; los manejadores de eventos en línea (`onclick` y similares) no se admiten y deberán migrarse a escuchadores.
  - *Criterio:* La política no deberá romper la interfaz: los estilos compilados por Vite, las fuentes y las imágenes del propio sitio deberán cargarse sin errores de consola.
  - *Criterio:* Ninguna cabecera de seguridad deberá depender de que el proxy o el servidor web las añada por su cuenta.

## 6. Casos límite

1. Credenciales válidas de cada uno de los cuatro roles: cada uno aterriza en su panel.
2. Contraseña incorrecta con un correo que existe.
3. Correo que no existe: mismo mensaje que el caso 2.
4. Campos vacíos o con formato inválido.
5. Muchos intentos fallidos seguidos: se frena y se indica cuándo reintentar.
6. Inicio de sesión correcto después de varios fallidos: se limpia el contador.
7. Invitado pidiendo la raíz, el POS o el panel del Administrador: termina en la pantalla de inicio de sesión.
8. Usuario autenticado que abre la pantalla de inicio de sesión: va a su panel.
9. Usuario con un rol no reconocido que inicia sesión: no llega a ningún panel.
10. Cierre de sesión y botón «atrás»: no debe volver a mostrarse una pantalla autenticada.
11. Muchos intentos fallidos de autorización de supervisor seguidos: se frena y se indica cuándo reintentar.
12. Autorización de supervisor correcta después de varios intentos fallidos: se limpia el contador.

## 7. Fuera de alcance

- Alta de usuarios, asignación y edición de roles, y desactivación de cuentas.
- Cambio de contraseña, recuperación por correo, verificación de correo y segundo factor.
- «Recordarme» y sesiones recordadas de larga duración.
- Inicio de sesión con proveedores externos (SSO, OAuth, directorio corporativo).
- Auditoría de inicios de sesión más allá del freno por fuerza bruta.

## 8. Criterios de finalización

1. Todos los RF de la sección 4 tienen al menos un test automatizado en verde, escrito antes del código.
2. La suite completa pasa sin tests omitidos ni comentados.
3. Los casos límite de la sección 6 están cubiertos por tests o por una verificación manual documentada.
4. Demo manual: iniciar sesión con cada uno de los cuatro roles desde el navegador y comprobar que cada uno aterriza en su panel; cerrar sesión y comprobar que una ruta protegida vuelve a pedir credenciales.
5. La pantalla de inicio de sesión se ve sin desbordes ni errores de consola a 375 px y en escritorio.
6. Se comprueba que, tras varios intentos fallidos de autorización de supervisor, el sistema frena e indica cuándo reintentar (RF-83).
7. Esta spec queda marcada como aprobada y anotada en `AGENTS.md`.

## 9. Decisiones cerradas

**Decisiones tomadas por el equipo (v0.1):**

- **D-11 — Autenticación por sesión con guard `web`.** Se usa el guard de sesión de Laravel y el RBAC por middleware ya existente; no se introduce Sanctum ni ningún paquete nuevo, en cumplimiento del principio 1 de la constitución.
- **D-12 — Mensaje único de credenciales.** Un solo mensaje para correo inexistente y contraseña incorrecta: no se filtra qué cuentas existen.
- **D-13 — Freno por correo y dirección.** El contador de intentos se lleva por la pareja correo + dirección de origen, con ventana de un minuto y cinco intentos; un acierto limpia el contador.
- **D-14 — Aterrizaje siempre en el panel del rol.** Tras autenticar, el destino se calcula con `LandingPolicy`; el sistema no arrastra una pantalla pedida antes de entrar, para que nadie termine en un panel ajeno.
- **D-15 — Sesión sin «recordarme».** No se ofrece la casilla «mantener la sesión» en esta versión.
- **D-16 — Freno por actor y dirección.** El contador de intentos de autorización de supervisor se lleva por el usuario en sesión y su dirección de origen, con la misma ventana que el inicio de sesión (cinco intentos por minuto); un acierto limpia el contador. La autorización de supervisor no es un inicio de sesión: no cambia la sesión ni el rol del cajero, sólo deja registrado quién autorizó.

**Supuestos adoptados (revisables por el equipo):**

- **S-15 — Credenciales por seed.** Las cuentas de la demo se siguen provisionando con `UserSeeder`; su contraseña es de desarrollo y debe cambiarse por base de datos antes de un uso real (el cambio de contraseña está fuera de alcance).
- **S-16 — Sin verificación de correo.** El modelo conserva `email_verified_at`, pero no se exige verificar el correo para entrar.

## 10. Enmiendas requeridas en las specs anteriores

Se aplican en el mismo commit que apruebe esta spec, para que ninguna spec se contradiga.

1. **Spec 001 §7 y spec 002 §7 («Fuera de alcance»):** la línea «Autenticación y pantalla de inicio de sesión» pasa a remitir a esta spec 003, igual que el cierre de caja remite a la 002.
2. **RF-16 y RF-11 de la spec 001:** se complementan con el freno de RF-83. Ninguna cambia su regla —quien autoriza sobre el tope sigue siendo el supervisor y la reutilización de receta sigue exigiendo confirmación—; lo que se agrega es el límite de intentos de autorización, que hasta ahora era ilimitado y permitía al cajero probar contraseñas de supervisor sin freno.
