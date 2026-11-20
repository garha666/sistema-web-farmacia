# Especificación 004 — Reportes y exportaciones

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A)
**Estado:** aprobada · **Versión:** 1.1 (2026-11-19) · **Ámbito:** sólo el QUÉ y el POR QUÉ

> **Relación con las specs anteriores.** La spec 001 define el POS y sus ventas; la spec 002 define los paneles por rol, inventario y cierre de caja; la spec 003 define autenticación y acceso; la spec 004-pos-async define el POS asíncrono. Ninguna cubre reportes históricos ni exportaciones masivas. La numeración de RF y RNF continúa la de la spec 004-pos-async (últimos: RF-91, RNF-33). Esta spec agrega RF-92 a RF-98 y RNF-34 a RNF-40.

## 1. Contexto y objetivo

Hoy el sistema permite consultar ventas, inventario y auditoría desde pantallas individuales, pero no hay forma de obtener **reportes históricos** (ventas de un mes, stock por categoría, arqueo de caja por turno, controlados por rango de fechas) ni de **exportar** esos datos a CSV para llevarlos a Excel o entregarlos a contabilidad.

El objetivo de este módulo es ofrecer reportes configurables por rango de fechas y rol, con exportación a CSV, sin duplicar la lógica de negocio ya existente en `app/Services/` y `app/Pos/`.

La regla que lo ordena: **los reportes leen datos agregados sin modificar el estado del sistema, y toda exportación queda registrada en la auditoría.**

## 2. Usuarios / actores

| Actor | Rol RBAC | Qué hace en este módulo |
|---|---|---|
| **Administrador** | Admin | Genera cualquier reporte y exporta cualquier dato del sistema. |
| **Supervisor de turno** | Químico | Genera reportes de ventas y controlados de su turno; no exporta datos sensibles de otros cajeros. |
| **Cajero** | Técnico | Genera reportes de sus propias ventas y su arqueo de caja; no ve reportes de otros cajeros. |
| **Bodeguero** | Bodeguero | Genera reportes de inventario y movimientos de stock; no ve reportes de ventas. |

## 3. Historias de usuario

- **H20** — Como **administrador** quiero un reporte de ventas por rango de fechas para saber cuánto se vendió en un período.
- **H21** — Como **administrador** quiero exportar a CSV el reporte de ventas para llevarlo a contabilidad.
- **H22** — Como **cajero** quiero ver el arqueo de mi turno con ventas, efectivo, tarjetas y diferencia para cerrar mi caja.
- **H23** — Como **químico** quiero ver las anulaciones y descuentos autorizados por mí en un rango de fechas para supervisar mi turno.
- **H24** — Como **bodeguero** quiero ver el reporte de inventario (stock mínimo, sin stock, movimientos) para planificar compras.
- **H25** — Como **usuario de cualquier rol** quiero que cada exportación quede registrada con mi usuario y fecha para rendir cuentas.

## 4. Requisitos funcionales

Notación EARS. «El sistema» es el módulo de reportes. La numeración continúa la de la spec 004-pos-async.

- **RF-92 — Reporte de ventas por rango de fechas.** CUANDO el administrador pide ventas entre dos fechas, ENTONCES el sistema deberá mostrar el total vendido, la cantidad de transacciones, el desglose por medio de pago y el listado de ventas con cajero y total.
  - *Criterio:* Solo el Administrador puede ver ventas de todos los cajeros.
  - *Criterio:* El reporte debe incluir anuladas y no anuladas, diferenciadas por estado.
  - *Criterio:* Las fechas usan la zona horaria local `America/Santiago` (RNF-5 de la spec 001).

- **RF-93 — Exportación de ventas a CSV.** CUANDO el administrador pide exportar el reporte de ventas, ENTONCES el sistema deberá generar un CSV con fecha, cajero, medio de pago, estado y total de cada venta.
  - *Criterio:* El archivo debe llevar nombre `ventas-YYYY-MM-DD_a_YYYY-MM-DD.csv` (fechas `desde` y `hasta` del reporte, en ISO 8601; ver D-25).
  - *Criterio:* El contenido textual del CSV cumple RNF-40 (protección contra inyección de fórmulas).
  - *Criterio:* La exportación queda registrada en la bitácora de auditoría (RF-84 de la spec 003) con el usuario que la solicitó.

- **RF-94 — Arqueo de caja por turno.** CUANDO un cajero pide el arqueo de su turno, ENTONCES el sistema deberá mostrar ventas, totales por medio de pago, efectivo declarado, diferencia y estado del cierre.
  - *Criterio:* Un cajero solo ve su propio arqueo; el Administrador ve el de cualquier cajero.
  - *Criterio:* Si la caja no está cerrada, el arqueo muestra «abierta» y la diferencia calculada hasta el momento.
  - *Criterio:* El arqueo **no tiene filtro de fechas**: se identifica por turno de caja, no por rango (D-29).

- **RF-95 — Reporte de descuentos y anulaciones.** CUANDO un Químico pide descuentos y anulaciones de un rango de fechas, ENTONCES el sistema deberá mostrar quién autorizó cada uno, cuándo y el monto.
  - *Criterio:* Solo incluye acciones del Químico en sesión, a menos que sea el Administrador.
  - *Criterio:* Incluye descuentos globales, de línea y reutilización de receta.
  - *Criterio:* Si no se indican fechas, el reporte usa «hoy» (`America/Santiago`) como `desde` y `hasta` (D-29).

- **RF-96 — Reporte de inventario.** CUANDO el Bodeguero pide el reporte de inventario, ENTONCES el sistema deberá mostrar productos bajo stock mínimo, sin stock y movimientos de stock de un rango de fechas.
  - *Criterio:* Incluye valorización del inventario (stock × precio de costo si existe, o precio de venta).
  - *Criterio:* Solo lectura: no permite crear ni editar productos desde el reporte.

- **RF-97 — Exportación de reportes a CSV.** CUANDO un usuario pide exportar cualquier reporte, ENTONCES el sistema deberá generar un CSV con las columnas del reporte y registrar la exportación en auditoría.
  - *Criterio:* Solo roles con permiso de lectura sobre ese reporte pueden exportarlo.
  - *Criterio:* El nombre del archivo sigue D-25: incluye el tipo de reporte y, salvo el arqueo, el rango de fechas.
  - *Criterio:* El contenido textual de cada CSV cumple RNF-40.

- **RF-98 — Reportes sin modificar el estado.** CUANDO se genera o exporta un reporte, ENTONCES el sistema no debe crear ventas, movimientos de stock ni modificar ningún dato transaccional.
  - *Criterio:* Solo se escribe en la tabla de auditoría (registro de la exportación o consulta).

## 5. Requisitos no funcionales

Numeración continua con la spec 004-pos-async.

- **RNF-34 — Consultas de solo lectura.** Los reportes deben usar consultas de lectura sin transacciones de escritura, excepto el registro de auditoría de la exportación.
- **RNF-35 — Rendimiento.** Un reporte de ventas de un mes con hasta 5.000 transacciones debe responder en menos de 2 segundos (RNF-1 de la spec 001).
- **RNF-36 — Zona horaria.** Todas las fechas de los reportes usan `America/Santiago` de forma consistente (constitución, principio 5).
- **RNF-37 — Acceso por rol.** Cada reporte verifica el rol del usuario antes de mostrar datos; un cajero no ve ventas ajenas.
- **RNF-38 — Formato CSV.** Las exportaciones usan coma como separador, punto como separador decimal y codificación UTF-8.
- **RNF-39 — Auditoría de exportaciones.** Cada exportación registra usuario, tipo de reporte, rango de fechas y marca de tiempo.
  - *Criterio:* El rango registrado es el efectivo, es decir, el que usó el reporte tras aplicar «hoy» por defecto (ventas, descuentos y anulaciones, inventario).
  - *Criterio:* El arqueo no tiene rango (D-29): su registro de auditoría dice «sin rango» y conserva usuario, tipo y marca de tiempo.
- **RNF-40 — Protección contra inyección de fórmulas en CSV.** Todo valor de texto que el sistema escribe en un CSV y que un usuario pudo introducir (nombre de cajero o de usuario, nombre de producto, motivo, observación, etc.) no debe poder ser interpretado como fórmula por una planilla (Excel, LibreOffice, Google Sheets).
  - *Criterio:* Si un valor de texto empieza con `=`, `+`, `-`, `@`, tabulador (`\t`) o retorno de carro (`\r`), el sistema antepone una comilla simple (`'`) antes de escribirlo; el resto del valor no se altera.
  - *Criterio:* La neutralización se aplica solo a valores de texto; las cifras (totales, cantidades, diferencias de arqueo, incluidas las negativas) se escriben como números sin prefijo, respetando RNF-38.
  - *Criterio:* Se aplica a todos los CSV de la spec (RF-93 y RF-97) a través de un único punto de generación, sin excepciones por reporte.
  - *Criterio de verificación:* un test crea un registro con el texto `=HYPERLINK("http://x","clic")` (y otros con `+1`, `-1`, `@SUM(A1)`, `\t=1` y `\r=1`) en cada campo de texto exportado, exporta el reporte y comprueba que la celda correspondiente del CSV empieza con `'` y que ninguna celda de texto empieza con un carácter de la lista. Un segundo test comprueba que un total negativo se exporta como `-1500` sin comilla.

## 6. Casos límite

1. Rango de fechas vacío (sin `from` ni `to`): el reporte de ventas, el de descuentos y anulaciones y el de inventario muestran «hoy» (`America/Santiago`). El arqueo no tiene filtro de fechas (D-29), por lo que no aplica este caso: cualquier fecha enviada al arqueo se ignora.
2. `from` posterior a `to`: se rechaza con error de validación.
3. Sin datos en el rango: el reporte muestra ceros y lista vacía, sin error.
4. Exportación de un reporte vacío: se genera CSV solo con encabezados.
5. Usuario sin rol o rol no reconocido: no puede acceder a ningún reporte.
6. Caja abierta al pedir arqueo: se muestra estado «abierta» y diferencia provisional.
7. Dos exportaciones simultáneas del mismo reporte: se generan dos archivos independientes, ambas registradas en auditoría.
8. Texto con prefijo de fórmula (por ejemplo, un producto llamado `=cmd|...` o un cajero llamado `@usuario`): el CSV lo escribe neutralizado según RNF-40 y la pantalla HTML lo muestra sin alterar.

## 7. Fuera de alcance

- Programación automática de reportes (cron jobs, envío por correo).
- Dashboards gráficos o gráficos de tendencia (solo tablas y CSV).
- Reportes contables oficiales (balances, libros IVA, impuestos).
- Edición de datos desde una pantalla de reporte.

## 8. Criterios de finalización

1. Todos los RF de la sección 4 tienen al menos un test automatizado en verde, escrito antes del código.
2. La suite completa pasa sin tests omitidos ni comentados.
3. Los casos límite de la sección 6 están cubiertos por tests o por verificación manual documentada.
4. Demo manual: generar un reporte de ventas y exportarlo a CSV desde el navegador, verificando que el archivo se descarga y aparece en auditoría.
5. Verificar con Chrome DevTools MCP que ninguna pantalla de reportes desborde a 375 px y que no hay errores de consola.
6. Esta spec queda marcada como aprobada y anotada en `AGENTS.md`.

## 9. Decisiones cerradas

- **D-23 — Reportes como lecturas agregadas.** Los reportes leen directamente de las tablas transaccionales con `SELECT` y agregaciones; no se crean tablas resumen ni se denormaliza.
- **D-24 — Exportación por el mismo endpoint.** Un reporte responde HTML cuando se pide normalmente y CSV cuando el cliente pide `Accept: text/csv` (o un parámetro `?export=1`), sin duplicar la lógica de consulta.
- **D-25 — Nombre de archivo predecible.** Fechas siempre en ISO 8601 (`YYYY-MM-DD`) y tipo de reporte en español.
  - Ventas: `ventas-YYYY-MM-DD_a_YYYY-MM-DD.csv` (RF-93).
  - Descuentos y anulaciones e inventario: `reporte-{tipo}-YYYY-MM-DD_a_YYYY-MM-DD.csv`.
  - Arqueo (sin rango, D-29): `reporte-arqueo.csv`.
- **D-26 — Auditoría de exportación con `AuditLog::recordExport()`.** Se agrega a la bitácora existente de la spec 003 (RF-84).
- **D-29 — Rango de fechas por reporte.** El arqueo (RF-94) no tiene filtro de fechas: se identifica por turno. El reporte de descuentos y anulaciones (RF-95) usa «hoy» por defecto cuando no se indican fechas, igual que ventas e inventario. Consecuencia en auditoría (RNF-39): ventas, descuentos y anulaciones e inventario siempre registran un rango; solo el arqueo registra «sin rango». (D-27 y D-28 viven en `plan.md`.)

## 10. Supuestos adoptados (revisables por el equipo)

- **S-20 — Sin valorización de costo.** Por ahora la valorización de inventario usa el precio de venta, no el costo de compra (no hay campo de costo en `products`).
- **S-21 — Exportación síncrona.** Los CSV se generan y se descargan en la misma petición; no hay colas ni descarga asíncrona.

## 11. Enmiendas requeridas en las specs anteriores

1. **Spec 002 — sección «Fuera de alcance»:** se agrega que los reportes históricos y exportaciones pasan a la spec 004-reportes.
2. **Spec 003 — RF-84:** la bitácora de eventos de seguridad cubre ahora también las exportaciones de reportes (se añade acción `report.exported`).

## 12. Historial de versiones

- **v1.1 (2026-11-19)** — Decisiones aplicadas en este orden:
  1. **D-29** (nueva): el arqueo no tiene filtro de fechas y descuentos y anulaciones usa «hoy» por defecto. Actualizados RF-94, RF-95, RNF-39 y caso límite 1.
  2. **D-25** y **RF-93**: el CSV de ventas se llama `ventas-YYYY-MM-DD_a_YYYY-MM-DD.csv`; los demás reportes siguen `reporte-{tipo}-…`; el arqueo, `reporte-arqueo.csv`. Se corrige además «en español para las fechas» (las fechas son ISO). Ajustado el criterio de nombre de RF-97.
  3. **RNF-40** (nuevo) y caso límite 8: protección contra inyección de fórmulas en CSV, con criterio verificable; referenciado desde RF-93 y RF-97.
  - **Impacto pendiente en código y plan (no aplicado):** `plan.md` repite D-29 con otra redacción (descuentos «sin rango») y D-25 con el nombre único `reporte-{tipo}-…`; T38/T40 exportan hoy con ese nombre y sin neutralizar fórmulas en `CsvExporter`. Hay que alinear plan, tareas y código con esta versión antes de cerrar la spec.
- **v1.0 (2026-11-19)** — Versión aprobada inicial.
