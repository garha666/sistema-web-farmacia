# Especificación 004 — Punto de venta asíncrono

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A)
**Estado:** aprobada · **Versión:** 1.0 (2026-11-19) · **Ámbito:** sólo el QUÉ y el POR QUÉ

> **Relación con las specs anteriores.** La spec 001 define el POS actual: busca, carrito, totales, descuentos, cobro, comprobante y anulación. Ninguna de sus reglas cambia; lo que se agrega aquí es cómo se **transportan** esas acciones al navegador. La numeración de RF y RNF continúa la de la spec 003 (últimos: RF-84, RNF-28).

## 1. Contexto y objetivo

Hoy cada acción del POS (agregar al carrito, cambiar cantidad, quitar una línea, aplicar o quitar un descuento global o de línea) es un formulario que **recarga toda la página**. El cajero pierde el foco, el carrito se vuelve a pintar completo y el stock mostrado puede haberse desactualizado entre refrescos sin que nadie lo note.

El objetivo de este módulo es que el POS siga haciendo exactamente lo mismo que hace hoy —misma validación, mismos tope y controles, mismas ventas y comprobantes— pero que las operaciones del carrito, la búsqueda y los descuentos **no recarguen la página**, y que el stock mostrado se mantenga vigente sin obligar al cajero a recargar.

La regla que lo ordena: **el cajero opera sin interrupciones de página, con el stock siempre al día, y sin perder nunca el carrito aunque falle una operación.**

## 2. Usuarios / actores

| Actor | Rol RBAC | Qué hace en este módulo |
|---|---|---|
| **Cajero** | Técnico | Opera el POS: busca, agrega al carrito, cambia cantidades, aplica descuentos y cobra. |
| **Supervisor de turno** | Químico | Autoriza descuentos sobre el tope y reutilización de receta desde el POS. |
| **Administrador** | Admin | Opera el POS y supervisa las ventas. |
| **Bodeguero** | Bodeguero | No tiene acceso al POS (RF-11 de la spec 001). |

> **Alcance del POS.** Este módulo cubre las pantallas `/pos`, `/pos/checkout` y `/pos/sales/{sale}/receipt`. El cierre de caja (`/cash-registers/*`) queda fuera.

## 3. Historias de usuario

- **H15** — Como **cajero** quiero agregar, cambiar y quitar líneas del carrito sin que la página recargue, para no perder el foco ni reescribir la búsqueda cada vez.
- **H16** — Como **cajero** quiero buscar productos y ver coincidencias mientras escribo, sin recargar la página.
- **H17** — Como **cajero** quiero que el stock de lo que tengo en el carrito se mantenga al día y que me avisen si algo se agota o sale de catálogo.
- **H18** — Como **supervisor de turno** quiero autorizar un descuento sobre el tope sin que la página recargue, para no interrumpir al cajero.
- **H19** — Como **cajero** quiero que, si falla una operación del carrito, mi carrito se conserve y el sistema me diga por qué falló.

## 4. Requisitos funcionales

Notación EARS. «El sistema» es el módulo del POS. La numeración continúa la de la spec 003.

- **RF-85 — Carrito sin recargar.** CUANDO el cajero agrega una línea, cambia su cantidad o la quita del carrito, ENTONCES el sistema deberá actualizar el carrito, los subtotales y el total sin recargar la página.
  - *Criterio:* La acción responde sin recargar el documento (el `checkout_token`, el carrito en sesión y los totales se mantienen).
  - *Criterio:* El tiempo de respuesta de cada operación del carrito es menor de 500 ms en el percentil 95.
  - *Criterio:* El carrito y sus totales se anuncian a tecnologías de asistencia (`aria-live`).

- **RF-86 — Búsqueda sin recargar.** CUANDO el cajero escribe en el buscador del POS, ENTONCES el sistema deberá listar los productos coincidentes sin recargar la página.
  - *Criterio:* Cada pulsación no genera más de una petición; hay un retardo tras dejar de escribir (debounce) antes de consultar.
  - *Criterio:* La lista muestra nombre, principio activo, código de barras, precio y stock.

- **RF-87 — Descuentos sin recargar.** CUANDO el cajero aplica o quita un descuento global o de línea, ENTONCES el sistema deberá reflejarlo en los totales sin recargar la página.
  - *Criterio:* Una autorización de supervisor pendiente no se pierde entre operaciones del carrito.

- **RF-88 — Stock vigente.** El POS deberá refrescar periódicamente la existencia mostrada de los productos del carrito y avisar al cajero cuando uno se queda sin stock o sale del catálogo.
  - *Criterio:* El refresco no interrumpe al cajero ni recarga la página.
  - *Criterio:* Un producto sin stock no puede agregarse ni aumentarse en el carrito.

- **RF-89 — Cobro a salvo.** CUANDO el cajero confirma la venta, ENTONCES el sistema deberá verificar que el carrito sigue siendo válido (stock y catálogo) justo antes de cobrar; si algo cambió, deberá avisar sin perder el carrito.
  - *Criterio:* La venta no se confirma si una línea supera el stock disponible en ese momento.

- **RF-90 — Carrito a salvo ante fallos.** SI una operación asíncrona del carrito falla (red, servidor, stock, descuento rechazado), ENTONCES el carrito se conserva exactamente como estaba y el sistema muestra el motivo del error sin recargar.

- **RF-91 — Funcionamiento sin JavaScript.** SI el navegador no ejecuta guiones, ENTONCES el POS sigue funcionando con recargas completas de página (mejora progresiva).

## 5. Requisitos no funcionales

Numeración continua con la spec 003.

- **RNF-29 — JSON por el mismo endpoint.** Los endpoints del POS deberán responder JSON cuando la petición sea asíncrona y HTML cuando no, sin duplicar la lógica de negocio en dos sitios.
- **RNF-30 — Mismos controles de seguridad.** Las peticiones asíncronas deben pasar por el mismo guard de sesión, el mismo middleware RBAC y la misma protección CSRF que las de hoy; el JSON no debe exponer datos que la pantalla correspondiente no muestre.
- **RNF-31 — Sin dependencias nuevas.** No se añade ningún framework de JavaScript; se usa `axios`, que ya está instalado (principio 1 de la constitución).
- **RNF-32 — Accesibilidad y tamaño móvil.** El estado del carrito y los avisos de error deben anunciarse (`aria-live`) y la interfaz debe seguir sin desbordes a 375 px (RNF-17 de la spec 003).
- **RNF-33 — Rendimiento.** Cada operación asíncrona del POS debe responder en menos de 500 ms en el percentil 95; el refresco del stock no debe generar más de una petición por intervalo.

## 6. Casos límite

1. Stock insuficiente al agregar una línea: se rechaza y se avisa sin recargar.
2. Producto desactivado (fuera de catálogo) mientras está en el carrito: desaparece del carrito en el próximo refresco y se avisa.
3. Doble clic rápido en «agregar»: el sistema no duplica la línea ni acepta más de una petición por acción.
4. Respuesta del servidor llega fuera de orden (una petición lenta termina después de una rápida): el carrito refleja el estado correcto.
5. Sesión expirada en medio de una operación asíncrona: se redirige al login y el carrito se conserva para cuando el cajero vuelva a entrar.
6. Error 419 (CSRF expirado): se renueva el token y se reintenta la operación sin perder el carrito.
7. Caída de red: se muestra un aviso y el carrito queda exactamente como estaba.
8. Dos cajeros operan el último producto de stock: el segundo no puede completarlo y se le avisa al intentar cobrar.

## 7. Fuera de alcance

- Tiempo real entre cajas (WebSockets, Server-Sent Events o canal compartido).
- Impresión asíncrona del comprobante o impresión térmica directa desde el navegador.
- Modo offline / PWA / service worker.
- Edición de productos, convenios o clientes desde el POS.
- Cierre de caja y arqueo (`/cash-registers/*`).

## 8. Criterios de finalización

1. Todos los RF de la sección 4 tienen al menos un test automatizado en verde, escrito antes del código (test primero, rojo no avanza).
2. La suite completa pasa sin tests omitidos ni comentados.
3. Los casos límite de la sección 6 están cubiertos por tests o por verificación manual documentada.
4. Demo manual: operar el POS desde el navegador a 375 px y en escritorio, verificar que el carrito se actualiza sin recargar, que el stock se refresca y que un fallo no borra el carrito.
5. Verificar con Chrome DevTools MCP que ninguna pantalla del POS desborde a 375 px y que no hay errores de consola.
6. Esta spec queda marcada como aprobada y anotada en `AGENTS.md`.

## 9. Decisiones cerradas

- **D-17 — Content negotiation, mismo endpoint.** Los endpoints del POS responden JSON cuando la petición trae `Accept: application/json` (o `X-Requested-With: XMLHttpRequest`) y HTML cuando no. No se crea una API paralela.
- **D-18 — axios, nada de framework nuevo.** Se usa `axios`, ya instalado; prohibido añadir frameworks JS (principio 1 de la constitución).
- **D-19 — Mejora progresiva.** Los formularios actuales (POST/PATCH/DELETE con recarga) siguen siendo el respaldo cuando JavaScript no está disponible o falla.
- **D-20 — Sondeo (polling) del stock.** El POS consulta la existencia de las líneas del carrito a intervalos fijos; no hay canal push porque no hay infraestructura para ello.
- **D-21 — El cobro sigue en pantalla propia.** `/pos/checkout` mantiene su flujo actual (pantalla de confirmación, recibo impreso).

## 10. Supuestos adoptados (revisables por el equipo)

- **S-17 — Debounce de búsqueda.** 300 ms tras dejar de escribir antes de consultar coincidencias.
- **S-18 — Intervalo de sondeo del stock.** Cada 10 segundos mientras el carrito no esté vacío; se pausa si el cajero está interactuando.
- **S-19 — Sesión y carrito.** El carrito vive en sesión del servidor; el cliente solo refleja lo que el servidor devuelve.

## 11. Enmiendas requeridas en las specs anteriores

1. **Spec 001 — RNF-2 (rendimiento):** se aclara que el tope de 500 ms p95 se aplica también a las operaciones asíncronas del POS (RF-85, RF-33).
2. **Spec 001 §7 («Fuera de alcance»):** la línea «Interacción asíncrona / tiempo real del POS» pasa a remitir a esta spec 004.
