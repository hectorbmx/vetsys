# Roadmap: ajustes visuales y de funcionamiento del panel cliente

Fecha de inicio: 2026-07-08  
Superficie principal: `https://hdoc.vet/client/*`  
Ruta base de configuracion: `https://hdoc.vet/client/mi-configuracion`

## Objetivo

Organizar los ajustes visuales y funcionales del panel cliente para ejecutarlos por fases, evitando cambios dispersos y dejando claro:

- que problema resuelve cada ajuste;
- que usuario lo configura o lo usa;
- que rutas, vistas y datos toca;
- como se verifica antes de subir a produccion.

## Principios de trabajo

- Priorizar primero los ajustes que afectan entrada, navegacion y recuperacion de acceso.
- Mantener la configuracion dentro de `client/mi-configuracion` cuando sea una preferencia del tenant.
- Separar preferencias del tenant de preferencias personales del usuario cuando el comportamiento deba variar por integrante.
- Evitar cambios visuales amplios si el problema es funcional y puntual.
- Cada ajuste debe cerrar con criterio de aceptacion y prueba minima.

## Backlog inicial

### 1. Pantalla de inicio configurable para el tenant

Estado: implementado en backend/UI, pendiente de migrar y validar en entorno  
Prioridad: alta  
Ruta de configuracion: `https://hdoc.vet/client/mi-configuracion`  
Comportamiento actual: al iniciar sesion, el usuario tenant cae siempre en `https://hdoc.vet/client/dashboard`.

#### Problema

No todos los tenants necesitan empezar en el dashboard. Para algunos flujos operativos puede ser mas eficiente iniciar directo en otra pantalla del panel, por ejemplo agenda, clientes, ventas o configuracion.

#### Propuesta funcional

Agregar en `client/mi-configuracion` una opcion para elegir la pantalla inicial despues del login.

La preferencia debe aplicarse cuando:

- un usuario tenant inicia sesion correctamente;
- el plan y el acceso web permiten entrar al panel operativo;
- no existe una restriccion de facturacion que obligue a entrar a `client/profile`.

Si el tenant esta limitado por facturacion, debe conservarse el comportamiento actual de mandar a `client/profile`, porque esa ruta es parte del flujo de pago/renovacion.

#### Alcance sugerido

Agregar una preferencia persistente, probablemente a nivel `tenants`, por ejemplo:

- `default_home_route`
- valores permitidos por whitelist, no URL libre.

Opciones iniciales candidatas:

- Dashboard: `client.dashboard`
- Clientes: `client.customers.index`
- Ventas: `client.ventas.index`
- Agenda: `client.agenda.index`
- Mi configuracion: `client.mi-configuracion.index`

La lista final debe confirmarse contra rutas existentes y permisos reales.

#### Reglas de seguridad

- No permitir URLs externas ni rutas arbitrarias.
- Validar la ruta contra una lista cerrada.
- Si la ruta elegida deja de existir o no esta permitida, hacer fallback a `client.dashboard`.
- Si el usuario no puede entrar por plan/suscripcion, mantener fallback de facturacion actual.

#### Cambios tecnicos probables

- Migracion para guardar la preferencia en `tenants`. Implementado: `2026_07_08_000001_add_default_home_route_to_tenants_table.php`.
- Modelo `Tenant` con fillable/cast si aplica. Implementado: `default_home_route`.
- Controlador `Client\ConfiguracionController` para validar y guardar la preferencia. Implementado: `updateHomeRoute`.
- Vista `resources/views/client/mi-configuracion/index.blade.php` para exponer el selector. Implementado: tab `Preferencias`.
- Flujo de login/redirect en `routes/web.php` o servicio relacionado, donde hoy manda a `client.dashboard`. Implementado con `TenantHomeRouteResolver`.
- Pruebas feature para confirmar redirect configurable y fallback.

#### Criterios de aceptacion

- Desde `client/mi-configuracion`, un admin del tenant puede elegir pantalla inicial.
- Al cerrar sesion e iniciar sesion otra vez, cae en la pantalla elegida.
- Si el tenant tiene acceso limitado por facturacion, sigue cayendo en `client/profile`.
- Si la configuracion esta vacia o invalida, cae en `client.dashboard`.
- La opcion no acepta rutas externas ni texto libre.

#### Verificacion minima

- Prueba feature del redirect post-login con preferencia configurada.
- Prueba feature del fallback a dashboard.
- Prueba feature de tenant limitado por facturacion.
- Revision manual en navegador de la seccion nueva dentro de `client/mi-configuracion`.

## Pendientes por capturar

Agregar aqui los siguientes ajustes visuales/funcionales que se definan durante la planeacion:

- [x] Ajuste 2: modulos visibles en el menu por tenant.
- [ ] Ajuste 3 por definir.
- [ ] Ajuste 4 por definir.

### 2. Modulos visibles en el menu por tenant

Estado: implementado en backend/UI, pendiente de migrar y validar en entorno  
Prioridad: alta  
Ruta de configuracion: `https://hdoc.vet/client/mi-configuracion?tab=preferencias`

#### Problema

Para tenants con operacion simple, ver todos los modulos disponibles en el menu lateral agrega ruido y complejidad. El caso inicial es un cliente fundador/socio que necesita una experiencia mas ligera sin entrar todavia a permisos formales por plan.

#### Propuesta funcional

Agregar una seccion en `Preferencias` para elegir que modulos aparecen en el menu lateral del panel cliente.

Modulos configurables iniciales:

- Clientes
- Pacientes
- Ventas
- Agenda
- Clubes
- Servicios
- Dashboard

`Configuracion` queda siempre visible para poder reactivar modulos.

#### Alcance implementado

- Migracion `visible_menu_modules` en `tenants`.
- Utilidad `TenantMenuModules` con lista cerrada.
- Endpoint `client.mi-configuracion.menu-modules.update`.
- Formulario de checkboxes en tab `Preferencias`.
- Filtro del sidebar en `resources/views/layouts/client.blade.php`.
- El redirect de pantalla inicial evita caer en una ruta cuyo modulo fue ocultado y usa la primera pantalla visible compatible.

#### Fuera de alcance por ahora

- No bloquea acceso por URL directa.
- No modifica planes ni permisos comerciales.
- No aplica middleware/policies por modulo.

#### Criterios de aceptacion

- Un admin del tenant puede activar/desactivar modulos visibles desde `Preferencias`.
- Al guardar, el menu lateral solo muestra los modulos seleccionados.
- `Configuracion` siempre permanece visible.
- Si el modulo de la pantalla inicial queda oculto, el login cae en la primera pantalla visible disponible.
- Tenants sin configuracion previa siguen viendo todos los modulos.

#### Verificacion minima

- Ejecutar migraciones.
- Entrar a `client/mi-configuracion?tab=preferencias`.
- Ocultar Dashboard y Agenda, guardar y verificar el sidebar.
- Confirmar que el login no redirige a una pantalla oculta.

## Orden sugerido de ejecucion

1. [x] Confirmar lista inicial de pantallas permitidas: Dashboard, Clientes, Ventas, Agenda, Mi configuracion.
2. [x] Implementar preferencia de pantalla inicial.
3. [ ] Ejecutar migracion en entorno objetivo.
4. [ ] Probar login, restricciones de facturacion y fallback con feedback del usuario.
5. [ ] Continuar con el siguiente ajuste visual/funcional del backlog.

## Notas de continuidad

- Este documento es el punto de captura para los ajustes del panel cliente.
- Si un ajuste se vuelve grande, crear un roadmap o checkpoint especifico y enlazarlo desde aqui.
- Mantener la ruta exacta y el comportamiento observado en cada item para evitar trabajar sobre pantallas equivocadas.
