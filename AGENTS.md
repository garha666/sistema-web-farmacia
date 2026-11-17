# AGENTS.md — Sistema Web de Farmacia (Grupo A)

Proyecto académico INACAP, mantenido por estudiantes. Norma superior: `docs/constitution.md`.

## Contexto
- Spec activa: `specs/001-pos/spec.md` (POS, aprobada v1.0). Plan: `specs/001-pos/plan.md`. Tareas: `specs/001-pos/tasks.md`.
- Estado: T1–T10 cerradas y correcciones Vuelta 1–2 (S1–S14) cerradas, junto con las mejoras B0–B7. El POS está implementado en `app/` y la suite se ejecuta con `php artisan test`.
- Toda decisión nueva entra primero a la spec y después al código.

## Stack y estructura
- Backend: Laravel 11 (PHP 8.2+), MVC con controladores RESTful.
- Frontend: Blade + Tailwind CSS, responsive para cajas POS y tablets.
- Base de datos: PostgreSQL 16 en Tercera Forma Normal (3FN).
- Autenticación: Laravel Auth SessionGuard + middlewares RBAC (Químico, Técnico, Bodeguero, Admin).
- Almacenamiento: `storage/app/private/` (fuera del alcance del POS).
- Entorno: Docker / Docker Compose (Laravel + PostgreSQL + Nginx + SSL).

## Convenciones
- Código en inglés; interfaz y documentación en español.
- Requisitos funcionales en EARS (CUANDO..., SI..., MIENTRAS...).
- Nada se implementa si no está en la spec activa (`specs/NNN-*/spec.md`).
- Lógica de negocio en funciones puras; controladores sólo orquestan y validan.
- Roles POS: Cajero = Técnico, Supervisor = Químico, Administrador = Admin; Bodeguero sin acceso al POS.
- Pruebas con PHPUnit: test primero y validación RF por RF.

## Límites
- ✅ Siempre: respetar las reglas SDD; actualizar `MEMORY.md` al terminar cada tarea.
- ⚠ Pregunta antes: crear archivos nuevos, cambiar formato de datos, nuevas dependencias.
- 🚫 Nunca: tocar código fuera de la spec aprobada; agregar lógica sin test.

## Verificación
- `php artisan test` completo y sin tests en rojo antes de cerrar una tarea.
- Validar interfaces con el MCP de Chrome DevTools (vista móvil 375 px).
