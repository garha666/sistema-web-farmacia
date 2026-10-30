# Constitución — Sistema Web de Gestión y Simulación de Farmacia (Grupo A)

1. **Stack fijo y mínimo.** Laravel 11 + Blade/Tailwind + PostgreSQL 16 + Sanctum + Docker. Toda dependencia nueva se justifica en la spec activa; prohibido añadir frameworks JS, ORMs alternos o servicios externos.
2. **La spec manda.** Nada se implementa si no está en la spec activa del módulo. Si el código y la spec difieren, el código es el error: el cambio nace en la spec y se refleja en ella en el mismo commit.
3. **Lógica pura, interfaz tonta.** Precios, descuentos, stock, vencimientos y simulaciones viven en funciones/clases puras (sin `request()`, `Auth`, BD ni vistas). Controladores y Blade solo orquestan y muestran.
4. **Test primero, rojo no avanza.** Cada requisito entra con un test que falla antes del código. Un test en rojo bloquea el avance: no se comenta, no se salta. `php artisan test` debe pasar completo antes de cada commit.
5. **Datos del usuario protegidos.** Fechas en la zona horaria local `America/Santiago` de forma consistente (sin conversiones UTC sueltas); cambios compatibles hacia atrás (nunca borrar/renombrar columnas o campos de API sin capa de compatibilidad); un despliegue o error jamás debe cerrar sesión ni perder carrito o datos ingresados.
6. **Código en inglés, textos en español.** Clases, variables, tablas, rutas, commits y tests en inglés; interfaz, mensajes al usuario, documentación y spec en español. Sin textos de UI fuera de los archivos de traducción.
