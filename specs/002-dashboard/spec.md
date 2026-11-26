# Especificación 002 — Paneles por rol, inventario y cierre de caja

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A)
**Estado:** aprobada · **Versión:** 1.1 · **Ámbito:** sólo el QUÉ y el POR QUÉ

> **Relación con la spec 001.** Esta spec **absorbe dos temas que 001 había delegado**: el cierre de caja y arqueo (que 001 §7 y D-3 dejaban en `004-reportes`) y los tableros por rol (que 001 §7 excluía como «reportes gerenciales»). También **introduce el rol Bodeguero**, que 001 sólo nombra como existente en el RBAC pero nunca habilita. Las enmiendas exactas que 001 necesita están en la sección 10 y se aplican en el mismo commit que apruebe esta spec.
>
> **v1.1 (T22):** fija la definición de «anulación» en los indicadores. Hasta la v1.0, «ventas anuladas» significaba cosas distintas en el panel del Administrador (por fecha de venta) y en el del Químico (por fecha de anulación). La decisión D-11 separa los dos conceptos y asigna cada uno a su indicador.

---

## 1. Contexto y objetivo

El POS ya vende, documenta, controla stock y audita. Lo que falta es **punto de entrada y visibilidad**: hoy quien inicia sesión cae siempre en la caja, el administrador no tiene una vista de conjunto, el químico no ve qué autorizó en el día, el bodeguero no existe como usuario del sistema y no hay forma de saber si la caja cuadra al final del turno. Además, el catálogo de productos sólo se puede tocar por base de datos.

Este módulo entrega cuatro paneles de inicio según el rol, un catálogo de productos con ajuste de stock auditado y un corte de caja con diferencia declarada. La regla que lo ordena: **cada rol entra y ve lo que le corresponde hacer, y los números del panel coinciden con los de las pantallas de detalle**.

## 2. Usuarios / actores

| Actor | Rol RBAC | Qué hace en este módulo |
|---|---|---|
| **Administrador** | Admin | Ve el resumen del negocio, reintenta documentos, activa o desactiva convenios, administra el catálogo y consulta los cortes de caja. |
| **Supervisor de turno** | Químico | Ve las ventas y las autorizaciones del día, y entra en lectura a convenios y auditoría. No escribe en este módulo. |
| **Cajero** | Técnico | Abre y cierra su caja, ve el resumen de su turno y entra al POS. |
| **Bodeguero** | Bodeguero | Ve el estado del inventario, ajusta existencias con motivo y consulta movimientos. **No accede al POS.** |

> **Alcance del rol Bodeguero.** Esta spec define **qué puede hacer** el rol, no cómo se crean los usuarios ni cómo se asignan roles: el alta de usuarios sigue fuera de alcance y se resuelve por seed o por base de datos.

## 3. Historias de usuario

- **H1** — Como **administrador** quiero ver al entrar cuánto se vendió hoy y qué quedó pendiente, para detectar problemas sin recorrer cinco pantallas.
- **H2** — Como **administrador** quiero reintentar un documento pendiente desde el panel para no perder el hilo de lo que estaba revisando.
- **H3** — Como **supervisor** quiero ver las autorizaciones que firmé hoy para responder si alguien cuestiona un descuento.
- **H4** — Como **cajero** quiero abrir mi caja con el monto inicial y cerrarla al final del turno sabiendo cuánto debería haber en efectivo.
- **H5** — Como **cajero** quiero ver el resumen de mi turno antes de cerrar, para no cerrar a ciegas.
- **H6** — Como **bodeguero** quiero saber qué productos están bajo el mínimo o sin stock para reponer primero lo urgente.
- **H7** — Como **bodeguero** quiero ajustar la existencia de un producto dejando el motivo, para que el inventario refleje la realidad.
- **H8** — Como **administrador** quiero mantener el catálogo de productos desde la interfaz para no depender de la base de datos.
- **H9** — Como **administrador** quiero comparar el efectivo esperado con el declarado al cierre para detectar diferencias de caja.
- **H10** — Como **cualquier usuario** quiero entrar directo a lo que mi rol necesita, sin ver opciones que me van a rechazar.

## 4. Requisitos funcionales

Notación EARS. «El sistema» es el módulo completo. La numeración continúa la de la spec 001.

### 4.1 Aterrizaje y acceso por rol

- **RF-41 — Aterrizaje por rol.** Cuando un usuario inicia sesión, el sistema deberá llevarlo al panel correspondiente a su rol: Administrador, Químico, Técnico o Bodeguero.
  - *Criterio:* Un usuario con un rol no reconocido o sin rol asignado no deberá llegar a ningún panel y deberá ver un mensaje que indique a quién solicitar acceso.
- **RF-42 — Rol Bodeguero.** El sistema deberá reconocer el rol Bodeguero con acceso al panel de inventario y al catálogo de productos, y sin acceso a la caja ni a las pantallas de venta.
  - *Criterio:* Si un usuario Bodeguero intenta abrir una ruta del POS o de ventas, entonces el sistema deberá negarle el acceso e informarlo.
- **RF-43 — Acceso restringido.** Si un usuario intenta abrir el panel de otro rol, entonces el sistema deberá negarle el acceso sin revelar información de ese panel.
- **RF-44 — Navegación por rol.** La navegación visible deberá ofrecer sólo las secciones habilitadas para el rol de la sesión; ninguna opción visible deberá terminar en un rechazo por permisos.
- **RF-45 — Acceso directo al POS.** El Administrador y el Químico deberán poder entrar al POS desde su panel; el Técnico deberá poder volver a su panel desde el POS.

### 4.2 Panel del Administrador

- **RF-46 — Resumen de ventas del día.** El panel del Administrador deberá mostrar, para el día en curso: cantidad de ventas confirmadas, monto total vendido, cantidad de ventas anuladas y monto anulado, y el desglose por medio de pago.
  - *Criterio:* Los montos deberán excluir las ventas anuladas del total vendido y mostrarlas por separado.
  - *Criterio:* «Ventas anuladas» son las ventas **con fecha de venta de hoy** que están anuladas (D-11), de modo que la cifra cuadre con el total vendido del mismo día.
  - *Criterio:* El panel deberá informar además cuántas **anulaciones se registraron hoy** —eventos de anulación con fecha de hoy, aunque la venta sea de un día anterior— con la misma fuente que el panel del Químico (RF-54).
- **RF-47 — Documentos por resolver.** El panel deberá listar los documentos pendientes o rechazados con su venta, su tipo y su fecha, y ofrecer el reintento de emisión en línea.
  - *Criterio:* Un documento emitido no deberá aparecer en esta lista ni ofrecer reintento.
- **RF-48 — Alertas de inventario.** El panel deberá mostrar la cantidad de productos bajo el stock mínimo y la cantidad de productos sin stock, con acceso a la lista correspondiente.
- **RF-49 — Actividad reciente.** El panel deberá mostrar los últimos movimientos de auditoría con autor, acción, venta y motivo.
- **RF-50 — Estado de convenios.** El panel deberá señalar los convenios activos próximos a vencer y los ya vencidos, y permitir activar o desactivar un convenio en línea.
- **RF-51 — Cortes de caja del día.** El panel deberá listar los turnos de caja abiertos y cerrados del día con su responsable, su monto esperado, su monto declarado y su diferencia.

### 4.3 Panel del Químico

- **RF-52 — Resumen del día.** El panel del Químico deberá mostrar la cantidad de ventas del día, el monto vendido y el desglose por medio de pago.
- **RF-53 — Autorizaciones del día.** El panel deberá listar las autorizaciones otorgadas hoy por el usuario de la sesión —descuento global sobre el tope, descuento de línea y reutilización de receta— con la venta, el motivo y el monto o porcentaje autorizado.
- **RF-54 — Anulaciones registradas hoy.** El panel deberá listar las anulaciones registradas hoy —los eventos de anulación con fecha de hoy, aunque la venta sea de un día anterior— con su motivo y su autor.
  - *Criterio:* La cifra de anulaciones registradas hoy deberá coincidir con la que informa el panel del Administrador (RF-46), porque ambas toman la misma fuente (D-11).
- **RF-55 — Acceso de lectura.** CUANDO el Químico acceda directamente a las rutas de convenios o auditoría, conservará el acceso de solo lectura. El panel y su menú no mostrarán enlaces a esas rutas.
  - El menú del Químico contiene Inicio, Recepciones controladas, Libro de controlados / Autorizaciones y PIN del POS.
- **Pulido del panel del Administrador (v1.1.1).** El menú del Administrador deberá empezar por Inicio (`/panel/admin`), igual que los demás roles. La tabla de convenios deberá mostrar cuántos afiliados (RUT) tiene cada convenio, y el acceso a «Importar afiliados» deberá ser un botón secundario junto a «Nuevo convenio». La auditoría deberá ofrecer un filtro rápido por texto sobre la acción o el autor. Los subtítulos de las pantallas no deberán mostrar códigos internos de requisitos.
- **Bandeja clínica del Químico.** CUANDO el Químico abra su panel, la cabecera deberá mostrar el rol, la fecha local y si tiene configurado el PIN del POS. Debajo, la bandeja deberá mostrar el total de recetas pendientes vigentes y hasta 20 solicitudes, comenzando por las más antiguas. MIENTRAS el panel esté abierto, consultará la bandeja cada ocho segundos por JSON, solo con sesión del Químico, sin recargar la página ni modificar el flujo de aprobación.
- **Consulta clínica.** CUANDO el Químico abra el libro clínico, podrá consultar por fecha las dispensaciones controladas y las revisiones que conservan una aprobación registrada, separadas de la auditoría administrativa y sin permiso de exportación. Las solicitudes vencidas no se muestran como rechazos: el flujo actual no registra rechazos clínicos.

### 4.4 Panel del Técnico

- **RF-56 — Estado de la caja.** El panel del Técnico deberá mostrar si tiene una caja abierta y, si la tiene, desde cuándo, con qué monto inicial y cuánto se vendió en ese turno por medio de pago.
- **RF-57 — Caja cerrada.** Si el usuario no tiene una caja abierta, entonces el panel deberá ofrecer abrirla indicando el monto inicial en efectivo y deberá explicar que sin apertura no puede cobrar.
- **RF-58 — Apertura de caja.** Cuando el cajero abre una caja, el sistema deberá registrar el monto inicial, el responsable y la fecha y hora local, y deberá impedir una segunda caja abierta para el mismo usuario.
- **RF-59 — Cierre de caja.** Cuando el cajero inicia el cierre, la pantalla de conteo no deberá mostrar ni enviar el efectivo esperado ni los totales del turno; deberá pedir cantidades enteras no negativas por denominación de pesos chilenos. Un campo vacío equivale a cero. El servidor calcula el monto contado. Se conserva el campo histórico `declared_amount` como alternativa de entrada para consumidores existentes, sujeto a las mismas reglas de cierre.
  - *Criterio:* El servidor calcula el efectivo esperado —monto inicial más ventas en efectivo menos anulaciones en efectivo— y muestra la diferencia con su signo solo después de cerrar. El panel operativo del turno conserva su resumen (RF-61); el conteo sin referencia aplica a la pantalla de cierre.
  - *Criterio:* Si hay diferencia, el cierre exige una justificación no vacía y la guarda con el arqueo. Si falta, se conserva el conteo ingresado sin revelar el saldo ni cerrar el turno.
  - *Criterio:* Una vez cerrado el turno, su técnico y el administrador pueden descargar un CSV con los valores registrados y el técnico puede abrir un acta imprimible para guardar como PDF desde el navegador. El acta y el CSV no recalculan importes si hay anulaciones posteriores.
- **RF-60 — Requisito de cobro.** Mientras el cajero no tenga una caja abierta, el sistema deberá impedir confirmar un cobro e indicar cómo abrirla.
- **RF-61 — Resumen del turno.** El panel deberá mostrar la cantidad de ventas del turno, el total cobrado, el desglose por medio de pago y las anulaciones del turno.
- **RF-62 — Enlace a la caja.** El panel deberá ofrecer acceso directo al POS.

### 4.5 Panel del Bodeguero

- **RF-63 — Resumen del inventario.** El panel del Bodeguero deberá mostrar la cantidad total de productos activos, la cantidad bajo el stock mínimo, la cantidad sin stock y la cantidad de movimientos de stock del día.
- **RF-64 — Prioridad de reposición.** El panel deberá listar los productos bajo el mínimo ordenados de menor a mayor existencia, con su existencia actual, su mínimo y su unidad de venta.
- **RF-65 — Movimientos recientes.** El panel deberá mostrar los últimos movimientos de stock con producto, tipo de movimiento, cantidad, motivo, autor y fecha.
- **RF-66 — Ajuste desde el panel.** El panel deberá permitir registrar una entrada o salida de stock de un producto indicando cantidad y motivo obligatorio.
- **RF-67 — Accesos del Bodeguero.** El panel deberá ofrecer acceso al catálogo de productos y, en modo lectura, al libro de controlados.

### 4.6 Catálogo e inventario

- **RF-68 — Catálogo de productos.** El Administrador y el Bodeguero deberán poder consultar el catálogo con búsqueda por nombre, principio activo o código de barras, y ver por producto su precio, su categoría de venta, su existencia, su mínimo y si está vendible.
- **RF-69 — Alta de producto.** El Administrador deberá poder crear un producto indicando nombre, principio activo, código de barras, precio, categoría de venta, stock mínimo y existencia inicial.
  - *Criterio:* El sistema deberá exigir nombre y precio, y deberá rechazar un precio menor o igual a cero.
- **RF-70 — Edición de producto.** El Administrador deberá poder editar los datos comerciales de un producto: nombre, principio activo, código de barras, precio, categoría de venta, stock mínimo y si está vendible.
  - *Criterio:* Un cambio de precio no deberá alterar ventas ya confirmadas ni comprobantes ya emitidos.
  - *Criterio:* Quitar la marca de vendible deberá impedir agregarlo a una nueva venta y no deberá afectar el historial.
- **RF-71 — Código de barras único y válido.** Si el código de barras ingresado ya pertenece a otro producto o no cumple el formato EAN-13 con dígito verificador correcto, entonces el sistema deberá rechazar el alta o la edición e informar el motivo.
  - *Criterio:* Un producto podrá quedar sin código de barras.
- **RF-72 — Sin borrado de productos.** El sistema no deberá eliminar productos; un producto fuera de catálogo deberá quedar inactivo y fuera de la búsqueda de la caja.
- **RF-73 — Ajuste de stock.** El Administrador y el Bodeguero deberán poder registrar un movimiento de entrada o salida de stock indicando producto, cantidad entera positiva y motivo obligatorio.
  - *Criterio:* Si una salida dejaría la existencia en negativo, entonces el sistema deberá rechazarla e indicar la existencia disponible.
- **RF-74 — Trazabilidad del inventario.** Todo movimiento de stock deberá registrar producto, tipo, cantidad, existencia resultante, autor, fecha y hora local y motivo, y deberá ser consultable.

### Recepción de mercadería y revisión técnica

- **RF-102 — Recepción por lote.** CUANDO el bodeguero registra una entrega, el sistema deberá guardar producto, lote, vencimiento, cantidades esperada y recibida, responsable y fotografía privada. La diferencia es la cantidad recibida menos la esperada. Para un producto no controlado, la entrada al stock deberá ser por la cantidad recibida, no por la pedida. Por ejemplo, recibir 18 de 20 registra una diferencia de −2 y una entrada de 18.
- **RF-103 — Revisión de controlados.** MIENTRAS una recepción de un medicamento controlado esté pendiente, sus unidades no deberán sumarse al stock disponible. CUANDO un químico distinto de quien registró la recepción la apruebe o rechace, el sistema deberá guardar responsable, motivo y fecha de la decisión. Una aprobación ingresa la cantidad recibida una sola vez; un rechazo no genera entrada. Repetir la decisión no deberá alterar el stock.
  - *Criterios:* Solo bodega registra recepciones; bodega consulta las propias y el químico consulta la bandeja de controlados. Solo sus respectivos usuarios autorizados pueden abrir la evidencia (JPEG, PNG o WebP de hasta 1,5 MiB). No se admiten lotes vencidos y, si vencen mientras esperan, se pueden rechazar pero no aprobar.
- **RF-105 — Existencias por lote.** Las nuevas recepciones ingresadas o aprobadas conservan saldo por lote. No se reconstruyen saldos de recepciones históricas sin trazabilidad: el stock preexistente y las entradas manuales permanecen sin lote. Las salidas manuales sin lote específico descuentan primero unidades sin lote y luego unidades de los lotes por vencimiento, incluidos los vencidos; la venta sigue RF-104 de la spec 001.
  - *Criterio:* La existencia de un producto deberá poder reconstruirse como la existencia inicial más la suma de sus movimientos.

### 4.7 Coherencia de los indicadores

- **RF-75 — Coincidencia con el detalle.** Los totales del panel deberán coincidir con los que arrojan las pantallas de detalle para el mismo período y los mismos filtros.
- **RF-76 — Datos del día en curso.** Los indicadores «de hoy» y «del turno» deberán calcularse en la zona horaria local y actualizarse en cada carga del panel, sin caché que pueda quedar desfasada.
- **RF-77 — Panel sin datos.** Si no hay actividad en el período, entonces el panel deberá mostrar el indicador en cero y un mensaje que explique que aún no hay movimientos, en lugar de una sección vacía.

## 5. Requisitos no funcionales

Numeración continua con la spec 001.

- **RNF-14 — Rendimiento del panel.** El panel deberá responder en menos de 800 ms en el percentil 95 con 20.000 ventas históricas y 5.000 productos.
- **RNF-15 — Sin consultas por fila.** La carga de un panel o de una lista no deberá ejecutar una consulta por cada elemento mostrado.
- **RNF-16 — Idioma y formato.** La interfaz, los mensajes y los estados deberán estar en español de Chile; los montos en pesos chilenos; las fechas en formato dd-mm-aaaa; la zona horaria `America/Santiago`.
  - *Presentación del Administrador (RF-46 a RF-51):* destacar el total vendido del día y ubicar el desglose por pago y las alertas de inventario junto al resumen en escritorio. En pantallas estrechas, apilar los bloques sin desplazar horizontalmente la página. La presentación no modifica los indicadores ni sus permisos.
- **RNF-17 — Accesibilidad.** Todo panel deberá tener foco visible, encabezados de tabla semánticos, etiquetas en los campos y contraste suficiente para uso prolongado.
- **RNF-18 — Operación sin mouse.** La apertura y el cierre de caja y el ajuste de stock deberán poder completarse con teclado.
- **RNF-19 — Permisos.** Cada panel y cada acción deberá estar disponible sólo para el rol que esta spec le atribuye; el rechazo se informa sin exponer datos.
- **RNF-20 — Ausencia de datos analíticos duplicados.** Los indicadores deberán calcularse a partir de los registros operativos existentes; el sistema no deberá mantener tablas de resumen que puedan quedar desincronizadas.
- **RNF-21 — Continuidad.** Un error en un panel no deberá cerrar la sesión ni perder un carrito o un formulario en curso.
- **RNF-22 — Claridad de errores.** Todo mensaje de error deberá indicar qué pasó y qué hacer a continuación, en lenguaje del rol que lo lee.

## 6. Casos límite

1. Usuario sin rol asignado o con un rol no reconocido: no debe llegar a ningún panel.
2. Bodeguero intentando entrar al POS o a una venta.
3. Químico intentando abrir el panel del Administrador.
4. Cierre de caja sin ventas en el turno.
5. Cierre de caja con diferencia a favor y con diferencia en contra.
6. Cierre de caja con anulaciones dentro del turno: el efectivo esperado debe descontarlas.
7. Anulación registrada después del cierre del turno: no debe reabrir el turno ni reescribir su total.
8. Segundo intento de apertura con una caja ya abierta.
9. Cobro intentado sin caja abierta.
10. Panel abierto justo antes y justo después de la medianoche: los indicadores «de hoy» no deben mezclar días.
11. Producto con existencia cero visible en el panel del Bodeguero y en la caja.
12. Alta de producto con código de barras repetido, con dígito verificador inválido o sin código.
13. Edición de precio de un producto que tiene ventas históricas.
14. Marcar como no vendible un producto que está en un carrito abierto en otra caja.
15. Ajuste de stock que dejaría la existencia en negativo.
16. Ajuste de stock sin motivo.
17. Panel con 20.000 ventas y 5.000 productos: debe seguir dentro de RNF-14.
18. Documento rechazado que se reintenta desde el panel y vuelve a fallar.
19. Convenio vencido que el Administrador intenta activar desde el panel.
20. Sesión expirada mientras se completa un cierre de caja: no debe perderse el monto declarado ni quedar un cierre a medias.
21. Venta de un día anterior anulada hoy: no debe sumarse como venta anulada del día en el resumen, pero sí debe aparecer en las anulaciones registradas hoy de ambos paneles (D-11).

## 7. Fuera de alcance

- Gestión de cuentas: el alta operativa y la desactivación se definen en la spec 003 (RF-99 y RF-100). El cambio de contraseña y la edición de roles quedan fuera de esta spec.
- Autenticación y **pantalla de inicio de sesión**: definidas en la spec 003 (`specs/003-auth/`, RF-78 a RF-82).
- Compras a proveedores, órdenes de compra y recepción de mercadería con documento: los ajustes de stock son movimientos manuales con motivo, no recepciones.
- Inventario avanzado: multi-bodega, transferencias entre ubicaciones, lotes, vencimientos y trazabilidad por lote.
- Reportes exportables y tableros analíticos históricos más allá de los indicadores descritos.
- Cierre de caja por turno automático, arqueo por denominaciones de billetes y monedas, y cuadratura obligatoria.
- Devoluciones parciales y anulación de una línea.
- Notificaciones por correo, alertas push o avisos fuera de la aplicación.
- Edición de movimientos de stock ya registrados: se corrigen con un movimiento contrario.
- Modificación de documentos ya emitidos.

## 8. Criterios de finalización

1. Todos los RF de la sección 4 tienen al menos un test automatizado en verde, escrito antes del código.
2. La suite completa pasa sin tests omitidos ni comentados.
3. Los casos límite de la sección 6 están cubiertos por tests o por una verificación manual documentada.
4. Demo manual por rol: iniciar sesión con cada uno de los cuatro roles y comprobar que aterriza en su panel y no ve opciones ajenas.
5. Demo manual del turno completo: abrir caja, vender, anular, cerrar caja con diferencia y ver el corte en el panel del Administrador.
6. Demo manual de inventario: crear producto, ajustar stock con motivo, agotar un producto y verlo aparecer como alerta en los paneles.
7. Los totales del panel coinciden con el historial de ventas para el mismo día y los mismos filtros.
8. La verificación visual a 375 px y en escritorio no muestra desbordes ni errores de consola en los cuatro paneles.
9. Esta spec queda marcada como aprobada y las enmiendas de la sección 10 quedan aplicadas en la spec 001.

## 9. Decisiones cerradas

**Decisiones tomadas por el equipo (v0.1):**

- **D-4 — Cuatro paneles, uno por rol.** Admin, Químico, Técnico y Bodeguero tienen panel propio y navegación derivada del rol; no hay un panel único con secciones ocultas.
- **D-5 — Paneles de lectura con acciones acotadas.** Los paneles informan y ofrecen sólo las acciones ya definidas en su ámbito: reintentar documento, activar o desactivar convenio, ajustar stock, abrir y cerrar caja. No se crean flujos de aprobación asíncrona: la autorización de supervisor sigue ocurriendo en línea, en el momento de la venta.
- **D-6 — El arqueo entra en esta spec.** El cierre de caja y la diferencia declarada se especifican acá y no en `004-reportes`, que queda para reportes históricos y exportaciones. Esto reemplaza la decisión D-3 de la spec 001.
- **D-7 — El cobro exige caja abierta.** Un turno de caja es la unidad del arqueo; sin apertura no hay contra qué comparar el efectivo. Es un cambio de comportamiento del POS y se declara en la sección 10.
- **D-8 — Inventario por movimientos manuales.** El Bodeguero ajusta existencias con entradas y salidas motivadas; no se implementan órdenes de compra ni recepción con documento, coherente con la exclusión de la spec 001.
- **D-9 — Indicadores calculados al vuelo.** No hay tablas de resumen ni procesos de consolidación: todo se deriva de ventas, documentos, movimientos y auditoría, lo que garantiza que el panel y el detalle nunca discrepen.
- **D-10 — Sin borrado de productos ni de movimientos.** Un producto fuera de catálogo se desactiva y un movimiento equivocado se corrige con el movimiento contrario, igual que la regla de inmutabilidad de la spec 001.
- **D-11 — «Anulación» son dos conceptos y se informan por separado.** (v1.1, T22.)
  - **Ventas anuladas del día:** ventas con `sold_at` de hoy cuyo estado es anulado. Responden «¿cuánto de lo vendido hoy se anuló?» y son las que el resumen resta del total vendido (RF-46, RF-52), de modo que el panel cuadre con el detalle por fecha de venta (RF-75).
  - **Anulaciones registradas hoy:** eventos de anulación con fecha de hoy, sin importar cuándo se vendió. Responden «¿qué se anuló hoy?» y son la lista de supervisión del Químico (RF-54) y el indicador del Administrador (RF-46). Ambos paneles toman esta cifra de la misma consulta, así que no pueden discrepar.
  - La palabra «anulación» a secas no se usa en la interfaz: cada indicador dice a cuál de los dos conceptos se refiere.

**Supuestos adoptados (revisables por el equipo):**

- **S-9 — Una caja abierta por usuario.** Cada usuario tiene como máximo un turno de caja abierto; no se modela una caja física compartida entre varios cajeros.
- **S-10 — Monto inicial en efectivo.** La apertura declara sólo el efectivo inicial; los demás medios de pago no requieren monto de apertura.
- **S-11 — Stock mínimo por producto.** El umbral de reposición es un atributo del producto, editable por el Administrador; «bajo el mínimo» significa existencia menor o igual al mínimo, y «sin stock» significa existencia cero.
- **S-12 — Diferencia de caja informativa.** La diferencia se registra y se muestra, pero no bloquea el cierre ni genera una autorización de supervisor en esta versión.
- **S-13 — Alcance temporal de los paneles.** Los indicadores cubren el día en curso y, en el caso del Técnico, el turno abierto; no hay selector de período en los paneles (el período se consulta en el historial).
- **S-14 — Libro de controlados en lectura para Bodeguero.** El Bodeguero consulta el libro pero no lo exporta; la exportación sigue siendo del Administrador.

## 10. Enmiendas requeridas en la spec 001

Se aplican en el mismo commit que apruebe esta spec, para que ninguna spec contradiga a la otra.

1. **§7 «Fuera de alcance» de 001:** quitar «Cierre de caja y arqueo por turno: se especifican aparte en `specs/004-reportes/`» y «Reportes gerenciales y tableros analíticos», y anotar que ambos pasan a la spec 002.
2. **D-3 de 001:** reemplazar por una remisión a D-6 de esta spec, dejando `004-reportes` limitada a reportes históricos y exportaciones.
3. **RF-20 y RF-22 de 001:** añadir la precondición de caja abierta (RF-60 de esta spec), indicando que un cobro sin turno abierto se rechaza.
4. **§2 «Usuarios / actores» de 001:** el mapeo de roles deja de decir que el Bodeguero no tiene acceso a nada; pasa a señalar que su ámbito es inventario y paneles, nunca el POS.
5. **RNF-7 de 001 (trazabilidad):** se mantiene sin cambios; esta spec agrega la trazabilidad de inventario y de turnos de caja en RF-74 y RF-59.
