# MEMORY.md — Estado del proyecto

**Proyecto:** Sistema Web de Gestión y Simulación de Farmacia Privada Comercial (Grupo A) — INACAP
**Última actualización:** 2026-10-30

## Estado actual

- **Constitución:** `docs/constitution.md` v1.0, vigente (6 principios).
- **Spec 001 POS:** `specs/001-pos/spec.md` v1.0, **aprobada**, 40 RF y 13 RNF, sin dudas abiertas.
- **Plan 001 POS:** `specs/001-pos/plan.md`, completo (arquitectura, funciones puras, pseudocódigo, UI, decisiones, tests).
- **Tareas 001 POS:** `specs/001-pos/tasks.md`, 10 tareas. **T1 a T5 cerradas**; T6 a T10 pendientes.
- **Código:** Laravel 11.57.1. Dominio `app/Pos/`: Money, Cart, CartLine, ProductSnapshot, Discount, ReceiptTotals, PriceCalculator, ConventionSnapshot, DiscountDecision, DiscountPolicy, PrescriptionInput, PrescriptionResult, PrescriptionPolicy, Shortage, StockPolicy; 80 tests en verde (81 con el Example).
- **Comandos SDD:** instalados en `.opencode/commands/` (9 comandos).

## Entorno (2026-10-30)

- PHP 8.2.12 (XAMPP) con `pdo_pgsql`, `pgsql` y `zip` habilitados en `C:\xampp\php\php.ini` (respaldo en `php.ini.bak-sdd`).
- PostgreSQL **18** local en `127.0.0.1:5432`, autenticación `trust`, bases `farmacia_pos` y `farmacia_pos_test`.
- **Docker no instalado**: desviación aprobada del plan, se usa PG 18 local en lugar de PG 16 en contenedor.
- `composer` instaló con `--no-security-blocking` porque Laravel 11 arrastra avisos de seguridad; revisar antes de producción.
- Zona horaria `America/Santiago`, `SESSION_DRIVER=database` (falta migración de sesiones para RF-6).

## Decisiones vigentes

- **D-1:** documentos tributarios **simulados** (folio interno, estados pendiente/emitido/rechazado). Sin SII ni servicios externos.
- **D-2:** tope general de descuento **10%** por transacción; convenios de salud con porcentaje propio definido por el administrador.
- **D-3:** cierre de caja y arqueo quedan en `specs/004-reportes/`, fuera del POS.
- **Roles POS:** Cajero = Técnico, Supervisor = Químico, Administrador = Admin; Bodeguero sin acceso al POS.
- **IVA:** 19% incluido en el precio de lista; el desglose se calcula con `net = round(total * 100 / 119)` y `iva = total - net`.
- **Redondeo:** siempre a peso entero con round half-up (`roundToPeso`), una sola vez por cálculo; el dominio nunca almacena flotantes como dinero.
- **Descuentos:** se aplican primero por línea y luego el global sobre el subtotal ya descontado; el descuento nunca supera su base y el total se acota a 0.
- **Tope 10%:** vigente en `config/pos.php` (`discount.general_cap_percent`). Superarlo devuelve `requires_supervisor`; no se aplica hasta que un Químico autorice.
- **Convenios (T3):** el porcentaje lo define el administrador y no consume el tope del cajero; convenio inactivo o fuera de vigencia se rechaza con `convention_not_applicable`. El porcentaje del convenio prevalece sobre cualquier descuento manual.
- **Rechazos de descuento (T3):** códigos `reason_required`, `total_not_positive`, `convention_not_applicable`, `above_cap`. Ningún descuento deja el total en cero o negativo.
- **Recetas (T4):** vigencia 30 días calendario inclusive (configurable en `config/pos.php`), comparada por día; fecha futura también inválida. Datos obligatorios: número, nombre y RUT del prescriptor y fecha de emisión.
- **Receta reutilizada (T4):** no bloquea la venta, pero devuelve `requires_supervisor` con código `prescription_already_used`.
- **Controlados (T4):** exigen registro en el libro (`requiresControlledRegister`); la categoría más restrictiva del carrito manda sobre toda la venta.
- **Stock (T5):** stock negativo o producto desconocido se tratan como 0; `canSell` exige cantidad ≥ 1 y existencia ≥ cantidad. El faltante es `requested - available` y el carrito se bloquea completo si hay al menos uno.
- **Cambio de stock (T5):** `changedSinceSnapshot` compara la existencia viva contra la congelada en el carrito; la transacción de cobro (T8) es la que bloquea la fila real.
- **Supuestos S-1 a S-6:** emisor ficticio, factura con RUT/razón social/giro/dirección/comuna, receta válida 30 días, libro de controlados como registro + CSV, nota de crédito simulada, RUT de cliente opcional.

## Pendientes conocidos

- La skill `sdd` referenciada por los comandos **no existe**; los comandos la invocan igual.
- `specs/004-reportes/` (cierre de caja) no ha sido redactada.
- Falta inicializar Laravel 11 + PostgreSQL 16 + Docker y ejecutar la Fase A del plan (dominio puro con tests).

## Próximo paso

Generar `specs/001-pos/tasks.md` y comenzar la Fase A del plan bajo TDD.
