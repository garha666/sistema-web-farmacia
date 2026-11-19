# Especificación 004 — Reportes y exportaciones

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A)
**Estado:** aprobada · **Versión:** 1.0 (2026-11-19) · **Ámbito:** sólo el QUÉ y el POR QUÉ

> **Relación con las specs anteriores.** La spec 001 define el POS y sus ventas; la spec 002 define los paneles por rol, inventario y cierre de caja; la spec 003 define autenticación y acceso; la spec 004-pos-async define el POS asíncrono. Ninguna cubre reportes históricos ni exportaciones masivas. La numeración de RF y RNF continúa la de la spec 004-pos-async (últimos: RF-91, RNF-33).

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
  - *Criterio:* El archivo debe llevar nombre `ventas-YYYY-MM-DD_a_YYYY-MM-DD.csv`.
  - *Criterio:* La exportación queda registrada en la bitácora de auditoría (RF-84 de la spec 003) con el usuario que la solicitó.

- **RF-94 — Arqueo de caja por turno.** CUANDO un cajero pide el arqueo de su turno, ENTONCES el sistema deberá mostrar ventas, totales por medio de pago, efectivo declarado, diferencia y estado del cierre.
  - *Criterio:* Un cajero solo ve su propio arqueo; el Administrador ve el de cualquier cajero.
  - *Criterio:* Si la caja no está cerrada, el arqueo muestra «abierta» y la diferencia calculada hasta el momento.

- **RF-95 — Reporte de descuentos y anulaciones.** CUANDO un Químico pide descuentos y anulaciones de un rango de fechas, ENTONCES el sistema deberá mostrar quién autorizó cada uno, cuándo y el monto.
  - *Criterio:* Solo incluye acciones del Químico en sesión, a menos que sea el Administrador.
  - *Criterio:* Incluye descuentos globales, de línea y reutilización de receta.

- **RF-96 — Reporte de inventario.** CUANDO el Bodeguero pide el reporte de inventario, ENTONCES el sistema deberá mostrar productos bajo stock mínimo, sin stock y movimientos de stock de un rango de fechas.
  - *Criterio:* Incluye valorización del inventario (stock × precio de costo si existe, o precio de venta).
  - *Criterio:* Solo lectura: no permite crear ni editar productos desde el reporte.

- **RF-97 — Exportación de reportes a CSV.** CUANDO un usuario pide exportar cualquier reporte, ENTONCES el sistema deberá generar un CSV con las columnas del reporte y registrar la exportación en auditoría.
  - *Criterio:* Solo roles con permiso de lectura sobre ese reporte pueden exportarlo.
  - *Criterio:* El nombre del archivo incluye el tipo de reporte y el rango de fechas.

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

## 6. Casos límite

1. Rango de fechas vacío (sin `from` ni `to`): se muestra el reporte de hoy.
2. `from` posterior a `to`: se rechaza con error de validación.
3. Sin datos en el rango: el reporte muestra ceros y lista vacía, sin error.
4. Exportación de un reporte vacío: se genera CSV solo con encabezados.
5. Usuario sin rol o rol no reconocido: no puede acceder a ningún reporte.
6. Caja abierta al pedir arqueo: se muestra estado «abierta» y diferencia provisional.
7. Dos exportaciones simultáneas del mismo reporte: se generan dos archivos independientes, ambas registradas en auditoría.

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
- **D-25 — Nombre de archivo predecible.** `reporte-{tipo}-{desde}_a_{hasta}.csv`, en español para las fechas.
- **D-26 — Auditoría de exportación con `AuditLog::recordExport()`.** Se agrega a la bitácora existente de la spec 003 (RF-84).

## 10. Supuestos adoptados (revisables por el equipo)

- **S-20 — Sin valorización de costo.** Por ahora la valorización de inventario usa el precio de venta, no el costo de compra (no hay campo de costo en `products`).
- **S-21 — Exportación síncrona.** Los CSV se generan y se descargan en la misma petición; no hay colas ni descarga asíncrona.

## 11. Enmiendas requeridas en las specs anteriores

1. **Spec 002 — sección «Fuera de alcance»:** se agrega que los reportes históricos y exportaciones pasan a la spec 004-reportes.
2. **Spec 003 — RF-84:** la bitácora de eventos de seguridad cubre ahora también las exportaciones de reportes (se añade acción `report.exported`).
