# Especificación 001 — Punto de Venta (POS)

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A)
**Estado:** aprobada · **Versión:** 1.0 · **Ámbito:** sólo el QUÉ y el POR QUÉ

> **Alcance tributario:** los documentos de esta iteración son **simulados**. El sistema genera folio, estado de emisión y comprobante reimprimible, pero **no** emite documentos con validez legal ni se conecta al SII ni a certificadores. Todo comprobante lo declara de forma visible.

---

## 1. Contexto y objetivo

La farmacia atiende en mostrador: hoy la venta se anota a mano y el stock se corrige "de memoria", lo que produce quiebres, diferencias de caja y ventas de medicamentos que exigían receta. Este módulo entrega un punto de venta que permite buscar un producto, armar el carrito, controlar la venta de medicamentos con receta y controlados, aplicar descuentos autorizados, cobrar, emitir el documento tributario simulado y descontar el stock en el mismo acto. El objetivo es que cualquier venta quede registrada una sola vez, con su respaldo documental y su efecto exacto sobre el inventario, incluso si dos cajeros venden al mismo tiempo o si el navegador falla a mitad del cobro.

## 2. Usuarios / actores

| Actor | Rol RBAC del sistema | Rol en el POS |
|---|---|---|
| **Cajero** | Técnico | Opera el POS: busca, arma el carrito, cobra, imprime. No puede saltarse controles de receta ni el tope de descuento. |
| **Supervisor de turno** | Químico | Autoriza descuentos sobre el tope, uso de receta repetida y anulaciones de ventas ya confirmadas. |
| **Administrador** | Admin | Define el tope general de descuento, los convenios de salud, los datos del emisor y el rango de folios; consulta ventas y anulaciones. No opera la caja en el flujo normal. |
| **Cliente** | — | Recibe el comprobante. Entrega RUT si pide factura o si se desea dejar registro; entrega receta cuando el producto lo exige. |
| **Emisor tributario simulado** | — | Componente interno del sistema que asigna folios y simula el ciclo de emisión (pendiente, emitido, rechazado). No es un servicio externo ni tiene validez legal. |

> **Mapeo de roles:** el RBAC del sistema define cuatro roles (Químico, Técnico, Bodeguero, Admin). En el POS, Técnico actúa como Cajero, Químico como Supervisor y Admin como Administrador. El rol Bodeguero no tiene acceso a esta funcionalidad.

## 3. Historias de usuario

- **H1** — Como **cajero** quiero buscar un producto por nombre, principio activo o código de barras para agregarlo al carrito sin memorizar códigos.
- **H2** — Como **cajero** quiero ver el total actualizándose con cada cambio para decirle el precio al cliente antes de cobrar.
- **H3** — Como **cajero** quiero que el sistema me exija los datos de la receta cuando el producto lo requiere para no vender medicamentos fuera de norma.
- **H4** — Como **cajero** quiero aplicar un descuento y que el sistema me pida autorización cuando supera el tope para no quedar trabado ni exceder mi facultad.
- **H5** — Como **cajero** quiero cobrar en efectivo y que el sistema calcule el vuelto para no equivocarme en la entrega.
- **H6** — Como **cliente** quiero recibir mi boleta o factura para acreditar la compra.
- **H7** — Como **supervisor** quiero anular una venta equivocada dejando el motivo registrado para corregir la caja sin borrar evidencia.
- **H8** — Como **administrador** quiero ver qué se vendió, quién lo vendió y qué se anuló para detectar diferencias y fraudes.
- **H9** — Como **cajero** quiero aplicar el descuento de un convenio de salud sin tener que calcularlo a mano para atender rápido al afiliado.

## 4. Requisitos funcionales

Notación EARS. "El sistema" es el POS.

### 4.1 Búsqueda y armado del carrito

- **RF-1 — Buscar producto.** Cuando el cajero ingresa texto o escanea un código, el sistema deberá mostrar los productos coincidentes por nombre comercial, principio activo o código de barras.
  - *Criterio:* Cuando la búsqueda no encuentra coincidencias, el sistema deberá mostrar "Sin resultados" y permitir editar el término sin perder el carrito.
- **RF-2 — Agregar al carrito.** Cuando el cajero selecciona un producto vendible, el sistema deberá agregarlo con cantidad 1, o incrementar en 1 la cantidad si ya está en el carrito.
  - *Criterio:* Si el producto está marcado como no vendible o descontinuado, entonces el sistema deberá impedir agregarlo e informar el motivo.
- **RF-3 — Modificar cantidad.** El cajero deberá poder fijar una cantidad entera mayor o igual a 1 y eliminar una línea del carrito.
  - *Criterio:* Si la cantidad solicitada excede la existencia disponible, entonces el sistema deberá rechazarla e indicar la existencia real.
- **RF-4 — Totales en vivo.** Mientras el carrito tenga al menos una línea, el sistema deberá mostrar por línea el precio unitario, la cantidad, el descuento aplicado y el subtotal; y al pie el subtotal, el total de descuentos, el IVA desglosado y el total a pagar.
  - *Criterio:* Cuando cambia cualquier línea o descuento, el sistema deberá recalcular los totales antes de permitir el cobro.
- **RF-5 — Cobro con carrito vacío.** Si el cajero intenta cobrar sin líneas en el carrito, entonces el sistema deberá impedir la operación e informarlo.
- **RF-6 — Carrito persistente.** Si la página se recarga, la sesión se interrumpe o la red falla, entonces el sistema deberá recuperar el carrito con sus líneas y descuentos intactos.

### 4.2 Recetas y productos controlados

- **RF-7 — Producto que requiere receta.** El sistema deberá distinguir tres categorías de venta: libre, bajo receta y controlado.
- **RF-8 — Exigir receta.** Si el carrito contiene un producto bajo receta o controlado, entonces el sistema deberá exigir, antes de cobrar, el número de receta, el nombre y RUT del profesional prescriptor y la fecha de emisión.
- **RF-9 — Validar vigencia.** Si la receta está vencida o le falta algún dato obligatorio, entonces el sistema deberá impedir la venta e indicar qué dato falta.
  - *Criterio:* La vigencia de la receta será de 30 días calendario desde su emisión, configurable por el administrador, y se aplicará igual a receta simple y a receta retenida de controlados.
- **RF-10 — Registro de controlados.** Cuando se vende un producto controlado, el sistema deberá dejar registro de la receta asociada a la venta, consultable y exportable por el administrador.
- **RF-11 — Trazabilidad de la receta.** Una misma receta deberá quedar asociada a las ventas que la usaron, de modo que el administrador pueda detectar su reutilización.
  - *Criterio:* Si se intenta usar una receta ya consumida en otra venta, entonces el sistema deberá advertirlo y exigir confirmación del supervisor.

### 4.3 Precios y descuentos

- **RF-12 — Precio con IVA.** El sistema deberá mostrar los precios al público con IVA incluido y calcular el IVA desglosado que exige el documento tributario.
- **RF-13 — Aplicar descuento.** El cajero deberá poder aplicar un descuento por línea o sobre el total, expresado en porcentaje o en monto, indicando su origen: manual o convenio de salud.
- **RF-14 — Tope general de descuento.** Mientras el descuento acumulado de la transacción no supere el tope general del 10%, el sistema deberá aplicarlo sin intervención adicional.
  - *Criterio:* El tope general será configurable por el administrador y su valor por defecto será 10% sobre el total de la transacción.
- **RF-15 — Descuento por convenio de salud.** Los descuentos por convenio (Isapres, seguros) deberán aplicar el porcentaje configurado para ese convenio, sin que el cajero pueda alterarlo.
  - *Criterio:* Si el porcentaje del convenio deja el total en cero o negativo, entonces el sistema deberá rechazar la aplicación del convenio.
- **RF-16 — Autorización sobre el tope.** Si el descuento supera el tope general, entonces el sistema deberá exigir credenciales de supervisor y registrar quién autorizó, cuánto y por qué.
- **RF-17 — Descuento con motivo.** Cuando se aplica cualquier descuento manual, el sistema deberá registrar su motivo.
- **RF-18 — Límites del descuento.** Si el descuento deja el total en cero o negativo, entonces el sistema deberá rechazarlo.
- **RF-19 — Redondeo.** El sistema deberá expresar todos los montos en pesos chilenos enteros y garantizar que el total sea igual a la suma de las líneas menos los descuentos.

### 4.4 Cobro y documento tributario simulado

- **RF-20 — Medio de pago.** Cuando el cajero confirma el cobro, el sistema deberá exigir la selección de un medio de pago: efectivo, débito, crédito o transferencia.
- **RF-21 — Vuelto.** Si el medio de pago es efectivo, entonces el sistema deberá calcular y mostrar el vuelto a partir del monto recibido.
  - *Criterio:* Si el monto recibido es menor que el total, entonces el sistema deberá impedir confirmar la venta.
- **RF-22 — Confirmar venta.** Cuando el cajero confirma el cobro, el sistema deberá registrar la venta, sus líneas, sus descuentos, su convenio si aplica, su medio de pago, el cajero y la fecha y hora local, y descontar el stock en el mismo acto.
- **RF-23 — Documento tributario simulado.** Cuando se confirma una venta, el sistema deberá generar un documento simulado —boleta, o factura si el cliente entrega RUT y razón social— con folio de un rango interno, estado de emisión y la leyenda visible de que no tiene validez tributaria.
  - *Criterio:* El folio deberá asignarse desde el rango configurado por el administrador, sin repetición y sin huecos injustificados.
- **RF-24 — Emisión simulada no bloqueante.** Si la emisión simulada falla, entonces el sistema deberá conservar la venta como pendiente de emisión, mostrar un comprobante provisorio y permitir reintentar.
  - *Criterio:* El sistema no deberá generar dos documentos para una misma venta ni reutilizar un folio ya asignado.
- **RF-25 — Comprobante.** Cuando la venta queda registrada, el sistema deberá permitir imprimir o reimprimir el comprobante sin alterar la venta.
- **RF-26 — Estado del documento.** El administrador deberá poder consultar qué documentos están emitidos, pendientes o rechazados, y reintentar los pendientes.

### 4.5 Stock y concurrencia

- **RF-27 — Sin sobreventa.** Si la existencia disponible de un producto es menor que la cantidad solicitada, entonces el sistema deberá impedir la confirmación de la venta.
- **RF-28 — Venta simultánea.** Si dos cajas intentan vender la última unidad de un producto al mismo tiempo, entonces el sistema deberá completar una sola venta y avisar a la otra caja que el stock cambió.
- **RF-29 — Cambio de stock durante la venta.** Si la existencia cambia entre el armado del carrito y la confirmación, entonces el sistema deberá informar la nueva existencia y ofrecer ajustar las cantidades antes de continuar.
- **RF-30 — Descuento de stock único.** El sistema deberá descontar el stock exactamente una vez por venta confirmada, y no descontarlo en ventas anuladas o fallidas.
- **RF-31 — Reposición por anulación.** Cuando se anula una venta, el sistema deberá devolver al stock las cantidades vendidas.

### 4.6 Anulación de ventas

- **RF-32 — Anular venta confirmada.** El supervisor deberá poder anular una venta ya confirmada indicando un motivo obligatorio.
- **RF-33 — Inmutabilidad.** Cuando se anula una venta, el sistema deberá conservarla íntegra y registrar la anulación como un hecho separado con autor, fecha, hora y motivo.
- **RF-34 — Reversa documental simulada.** Cuando se anula una venta con documento generado, el sistema deberá generar el documento de reversa simulado correspondiente y reflejar su estado.
- **RF-35 — Anulación única.** Si la venta ya fue anulada, entonces el sistema deberá impedir una segunda anulación.
- **RF-36 — Registro de autorizaciones.** El sistema deberá registrar toda autorización de supervisor —descuento sobre el tope, uso de receta repetida, anulación— con autor, fecha, hora y motivo.

### 4.7 Consulta

- **RF-37 — Historial de ventas.** El administrador deberá poder consultar las ventas por rango de fechas, cajero, medio de pago, convenio y estado del documento.
- **RF-38 — Detalle de venta.** Cuando el administrador abre una venta, el sistema deberá mostrar sus líneas, descuentos, convenio, receta asociada, documento, anulación si existe y autorizaciones aplicadas.

### 4.8 Convenios de salud

- **RF-39 — Catálogo de convenios.** El administrador deberá poder crear y desactivar convenios indicando nombre, porcentaje de descuento y vigencia.
  - *Criterio:* Un convenio desactivado o fuera de vigencia no deberá poder aplicarse en una nueva venta.
- **RF-40 — Aplicación del convenio.** Cuando el cajero selecciona un convenio, el sistema deberá aplicar su porcentaje sobre el total y registrar el convenio, el porcentaje aplicado y el ahorro resultante en la venta y en el comprobante.
  - *Criterio:* Si el convenio está desactivado, fuera de vigencia o deja el total en cero o negativo, entonces el sistema deberá rechazarlo e informar el motivo.

## 5. Requisitos no funcionales

- **RNF-1 — Rendimiento.** La búsqueda de productos deberá responder en menos de 500 ms en el percentil 95 con un catálogo de 5.000 productos; la confirmación de venta, en menos de 2 segundos.
- **RNF-2 — Plataforma.** El POS deberá operar en las dos últimas versiones de Chrome, Edge y Firefox, en pantallas de 1366×768 o mayores.
- **RNF-3 — Operación sin mouse.** Todo el flujo de venta deberá poder completarse con teclado y lector de código de barras.
- **RNF-4 — Idioma y formato.** La interfaz, los mensajes y los comprobantes deberán estar en español de Chile; los montos en pesos chilenos; las fechas en formato dd-mm-aaaa; la zona horaria `America/Santiago`.
- **RNF-5 — Integridad monetaria.** Los cálculos de precio, descuento, IVA y vuelto deberán ser exactos en pesos enteros, sin diferencias de redondeo entre pantalla, comprobante y registro.
- **RNF-6 — Continuidad.** Un error, una recarga o un despliegue no deberá cerrar la sesión del cajero ni perder el carrito en curso.
- **RNF-7 — Trazabilidad.** Toda venta, anulación, autorización y documento deberá ser auditable; nada se borra físicamente.
- **RNF-8 — Permisos.** Cada acción deberá estar disponible sólo para el rol que la spec le atribuye; ningún rol podrá ejecutar acciones de otro sin sus credenciales.
- **RNF-9 — Claridad de errores.** Todo mensaje de error deberá indicar qué pasó y qué hacer a continuación, en lenguaje del cajero.
- **RNF-10 — Impresión.** El comprobante deberá ser legible en impresora térmica de 80 mm y en hoja A4.
- **RNF-11 — Accesibilidad mínima.** La interfaz deberá tener foco visible, etiquetas en los campos y contraste suficiente para uso prolongado en mostrador.
- **RNF-12 — Volumen.** El sistema deberá sostener al menos 5 cajas operando simultáneamente y 20.000 ventas por mes sin degradar los tiempos de RNF-1.
- **RNF-13 — Simulación declarada.** Todo comprobante, pantalla de estado y exportación deberá indicar que el documento es simulado y carece de validez tributaria.

## 6. Casos límite

1. Carrito vacío al presionar cobrar.
2. Producto buscado inexistente, dado de baja o duplicado en el catálogo (mismo nombre, distinto código).
3. Cantidad cero, negativa, decimal o mayor a la existencia.
4. Producto agregado dos veces: debe incrementar cantidad, no crear línea duplicada.
5. Precio modificado por el administrador mientras el carrito está abierto: la venta debe informar el cambio antes de cobrar.
6. Dos cajas vendiendo la última unidad simultáneamente.
7. Receta vencida, incompleta o reutilizada.
8. Producto controlado sin receta.
9. Descuento que supera el 10% sin supervisor disponible.
10. Convenio desactivado, vencido o con porcentaje que deja el total en cero o negativo.
11. Descuento que deja el total en cero o negativo.
12. Monto recibido en efectivo menor al total.
13. Emisión simulada que falla o queda pendiente: la venta no se pierde ni se duplica el folio.
14. Corte de red o recarga del navegador en medio del cobro: no debe quedar una venta a medias ni un stock descontado sin venta.
15. Anulación de una venta ya anulada o de una venta inexistente.
16. Anulación de una venta cuyo documento aún está pendiente.
17. Datos corruptos o incompletos en un producto (sin precio, sin categoría de venta): debe impedir la venta e informarlo.
18. Carrito con muchas líneas (por ejemplo, 200) sin degradar los totales.
19. Producto con existencia cero visible en el catálogo: no debe poder agregarse.
20. Rango de folios agotado: la venta debe registrarse como pendiente sin consumir folio inválido.

## 7. Fuera de alcance

- Compras a proveedores, recepción de mercadería y órdenes de compra.
- Inventario avanzado: multi-bodega, transferencias, lotes, vencimientos y trazabilidad por lote.
- Devoluciones parciales de una venta; en esta iteración sólo existe la anulación completa.
- Pago mixto (parte efectivo, parte tarjeta) y pago en cuotas.
- Integración real con terminal de tarjetas (el POS sólo registra el medio de pago declarado).
- **Emisión tributaria real ante el SII, certificación, firma electrónica y proveedores de facturación.** Sólo existe la simulación descrita en RF-23 a RF-26.
- **Cierre de caja y arqueo por turno:** se especifican aparte en `specs/004-reportes/` y quedan fuera de esta spec.
- Operación sin conexión a internet.
- Tienda en línea, reservas de productos y venta a domicilio.
- Fidelización, puntos y promociones automáticas distintas de los convenios de RF-39 y RF-40.
- Reportes gerenciales y tableros analíticos (sólo consulta de RF-37 y RF-38).
- Gestión de usuarios, roles y autenticación (se asume existente y fuera de esta spec).
- Receta médica electrónica y validación con registros externos de profesionales.
- Multi-sucursal: una sola farmacia con varias cajas.

## 8. Criterios de finalización

1. Todos los RF de la sección 4 tienen al menos un test automatizado en verde, escrito antes del código.
2. La suite completa pasa sin tests omitidos ni comentados.
3. Los casos límite de la sección 6 están cubiertos por tests o por una verificación manual documentada.
4. Demo manual del flujo principal: buscar, armar carrito, aplicar convenio, cobrar, generar documento simulado y ver el stock descontado.
5. Demo manual del flujo de excepción: venta con receta, intento de sobreventa simultánea y anulación con reversa de stock.
6. La consulta de ventas y el detalle muestran lo mismo que el comprobante entregado al cliente.
7. Todo comprobante muestra la leyenda de simulación de RNF-13.
8. Esta spec queda marcada como aprobada; cualquier desviación se corrige en la spec antes que en el código.

## 9. Decisiones cerradas

**Decisiones tomadas por el equipo (v1.0):**

- **D-1 — DTE simulado (RF-23 a RF-26, RF-34).** Se simula el ciclo de emisión dentro del sistema: folio interno, estados pendiente/emitido/rechazado, no duplicación y comprobante reimprimible. No hay servicios externos ni certificadores, en cumplimiento del principio 1 de la constitución.
- **D-2 — Tope de descuento (RF-14 a RF-16).** Tope general del 10% por transacción, configurable. Los convenios de salud tienen porcentaje propio, definido por el administrador, y nunca pueden dejar el total en cero o negativo.
- **D-3 — Cierre de caja (RF-37).** Se especifica en `specs/004-reportes/`, fuera del alcance de esta spec. La consulta de ventas de RF-37 no calcula arqueos.

**Supuestos adoptados para cerrar el resto de las dudas (revisables por el equipo):**

- **S-1 — Emisor.** Farmacia ficticia "Farmacia Grupo A SpA", RUT 76.543.210-K, giro farmacia, dirección en Santiago. Todos los datos son configurables por el administrador y no corresponden a un contribuyente real.
- **S-2 — Datos de factura.** La factura simulada exige RUT, razón social, giro, dirección y comuna del cliente; la boleta no exige datos del cliente.
- **S-3 — Vigencia de receta.** 30 días calendario desde la emisión, configurable; misma regla para receta simple y retenida.
- **S-4 — Libro de controlados.** Se cumple con el registro de RF-10 más una exportación mensual en CSV; no se reproduce un formato legal específico.
- **S-5 — Nota de crédito.** La anulación con documento generado produce una nota de crédito simulada, autorizada por supervisor y registrada en RF-36.
- **S-6 — RUT del cliente.** Es opcional en la boleta; si se ingresa, queda asociado a la venta para futuras consultas.
