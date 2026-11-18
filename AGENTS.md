# AGENTS.md — Sistema Web de Farmacia (Grupo A)

Proyecto académico INACAP, mantenido por estudiantes. Norma superior: `docs/constitution.md`.

## Contexto
- Spec activa: `specs/001-pos/spec.md` (POS, aprobada v1.1), `specs/002-dashboard/spec.md` (paneles por rol, inventario y cierre de caja, aprobada v1.0) y `specs/003-auth/spec.md` (autenticación y acceso, aprobada v1.0). Planes: `specs/001-pos/plan.md`, `specs/002-dashboard/plan.md` y `specs/003-auth/plan.md`. Tareas: `specs/001-pos/tasks.md`, `specs/002-dashboard/tasks.md` y `specs/003-auth/tasks.md`.
- Estado: T1–T10 cerradas y correcciones Vuelta 1–2 (S1–S14) cerradas, junto con las mejoras B0–B14. El POS está implementado en `app/` y la suite se ejecuta con `php artisan test`. La spec 002 tiene plan y tareas definidas (T11–T18) y **T11 a T18 cerradas** (migraciones, rol Bodeguero, aterrizaje por rol, turnos de caja, panel del Técnico con bloqueo del cobro, panel del Administrador, panel del Químico, catálogo de productos, inventario del Bodeguero y cierre de navegación y coherencia); la spec queda implementada. La spec 003 tiene plan y tareas (T19–T29), con la spec en **v1.1** (agrega RF-83 y RNF-26, el freno a la autorización de supervisor) y **T19, T20 y T24 cerradas** (pantalla de inicio de sesión, autenticación, freno por fuerza bruta, cierre de sesión, `.env.example` con la zona horaria del negocio y PostgreSQL, y freno a las credenciales de supervisor). Suite en verde: **382 tests y 1.989 aserciones**.
- **Seguridad (auditoría con la skill `laravel-security`):** base sólida (sin `$guarded = []`, CSRF completo, cero `{!! !!}`, sesión regenerada, contraseñas con `hashed`). Abierto y planificado en `specs/003-auth/tasks.md`: avisos de dependencias (T25), cabeceras de seguridad y proxies de confianza (T26), `role` en `User::$fillable` (T27), bitácora de seguridad (T28) y textos de usuario fuera de `lang` (T29).
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
