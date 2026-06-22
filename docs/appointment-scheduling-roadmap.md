# Roadmap: agenda privada de citas veterinarias

Fecha: 2026-06-19
Estado: planeacion

Checkpoint de ejecucion: `docs/appointment-scheduling-checkpoint.md`.

Contrato funcional: `docs/appointment-scheduling-functional-contract.md`.

## Objetivo

Construir una agenda privada por tenant para que customers autenticados en Ionic
soliciten citas para una mascota asignada, y para que el tenant las confirme,
rechace o responda con una contrapropuesta de fecha y hora.

El MVP parte de estas condiciones:

- Un customer pertenece a un solo tenant.
- Un tenant opera inicialmente con un veterinario agendable y una ubicacion.
- No existe agenda publica ni seleccion de veterinaria.
- Toda cita requiere customer, mascota, veterinario y horario.
- No se permiten citas simultaneas para el mismo veterinario.
- El tenant siempre debe confirmar la solicitud.
- El customer debe aceptar una contrapropuesta del tenant.
- Tenant y customer reciben notificaciones persistentes, correo y entrega movil
  segun la matriz de canales definida en este documento.

## Decisiones cerradas

### Identidad y aislamiento

El customer no enviara ni seleccionara `tenant_id` o `customer_id`.

El backend resolvera el contexto mediante:

```text
User autenticado
  -> users.tenant_id
  -> customer_user_links
  -> customer_portal_accesses activo
  -> Customer
  -> final_user_patient_assignments activo
  -> Animal
```

La mascota enviada en una solicitud debe:

- pertenecer al mismo tenant;
- pertenecer al customer resuelto por el acceso actual;
- tener una asignacion no revocada para el usuario;
- estar activa.

### Confirmacion y contrapropuesta

- Una solicitud del customer nunca se confirma automaticamente.
- El tenant puede confirmar, rechazar o proponer otro horario.
- Una contrapropuesta requiere respuesta explicita del customer.
- Al aceptar la contrapropuesta, la cita queda confirmada sin una segunda
  confirmacion del tenant.
- Rechazar una contrapropuesta no elimina la auditoria de la cita.

### Servicio y duracion

Se reutilizara `catalog_items` con `type = service`. Un servicio agendable debe
tener duracion y margen configurables. El tenant puede ajustar la duracion al
confirmar o proponer un horario, conservando en la cita una copia de la duracion
aplicada para evitar que cambios futuros del catalogo alteren citas existentes.

### Veterinario y ubicacion

En el MVP no se pedira al customer seleccionar veterinario ni sucursal. El
veterinario agendable se configura en el tenant y se asigna automaticamente.

No se construira un modulo completo de sucursales en el MVP. La configuracion
de agenda sera tenant/veterinario y debera poder migrarse a sucursales en una
fase posterior.

### Privacidad

- `customer_reason`: visible para customer, administrador y veterinario.
- `customer_message`: mensajes de propuesta/cancelacion visibles para ambas partes.
- `internal_notes`: solo administrador y veterinario.
- Las notas clinicas seguiran en el expediente; no se guardaran como notas de cita.
- Correos y push no incluiran informacion clinica sensible completa.

## Estado actual reutilizable

Backend Laravel:

- Autenticacion Sanctum para Ionic.
- Aislamiento por `tenant_id`.
- Rol `customer` y middleware `customer.portal`.
- `customer_user_links` para vincular User y Customer.
- `customer_portal_accesses` para acceso activo.
- `final_user_patient_assignments` para mascotas autorizadas.
- `animal_portal_visibility_settings.show_appointments` ya existe.
- `tenant_notifications` alimenta notificaciones del tenant.
- `portal_notifications` alimenta notificaciones del customer.
- `PortalNotificationService` centraliza eventos customer existentes.
- Ionic ya consume notificaciones tenant y customer.
- El panel Laravel ya presenta notificaciones del tenant.
- `catalog_items` ya distingue productos y servicios.
- `veterinarian_profiles` identifica usuarios con perfil profesional.

Brechas actuales:

- No existen citas, propuestas, horarios o bloqueos.
- No existe duracion de agenda en servicios.
- No existe configuracion de zona horaria por tenant.
- No existe una seleccion explicita del veterinario agendable.
- No existe un rol `veterinario`.
- `RoleSeeder` crea `admin`, mientras el panel administra `client-admin`.
- No existe un servicio central equivalente para todas las notificaciones tenant.
- El envio de email para eventos de agenda no existe.

## Roles y autorizacion

### Paso previo obligatorio: normalizar administrador

Antes de implementar permisos de agenda:

- Auditar usuarios con rol `admin` y `client-admin`.
- Definir `client-admin` como nombre canonico del administrador de tenant.
- Actualizar `RoleSeeder` para crear `client-admin`.
- Preparar migracion o comando idempotente para trasladar usuarios `admin` a
  `client-admin` sin afectar `super-admin`.
- Buscar validaciones que aun dependan de `admin`.
- Agregar pruebas de acceso despues de la normalizacion.
- No borrar el rol antiguo hasta confirmar que ningun usuario lo usa.

### Matriz MVP

| Accion | client-admin | veterinario con perfil | asistente | cajero | customer |
| --- | --- | --- | --- | --- | --- |
| Ver agenda tenant | Si | Si | No | No | No |
| Configurar agenda | Si | No | No | No | No |
| Confirmar/rechazar | Si | Si | No | No | No |
| Crear contrapropuesta | Si | Si | No | No | No |
| Ver motivo customer | Si | Si | No | No | Propio |
| Ver/editar nota interna | Si | Si | No | No | No |
| Completar/no show | Si | Si | No | No | No |
| Solicitar cita | No | No | No | No | Si |
| Responder contrapropuesta | No | No | No | No | Propia |
| Cancelar | Si | Si | No | No | Propia, segun politica |

Para el MVP, "veterinario" significa un User activo del tenant con
`veterinarian_profile`; no requiere crear inmediatamente un rol Spatie nuevo.
Esta decision debera reevaluarse al soportar varios veterinarios.

## Estados y transiciones

Estados canonicos de `appointments`:

- `pending_tenant`: solicitud nueva esperando respuesta del tenant.
- `pending_customer`: contrapropuesta activa esperando al customer.
- `confirmed`: horario aceptado por ambas partes.
- `rejected`: tenant rechazo la solicitud.
- `cancelled`: una de las partes cancelo una cita o solicitud.
- `completed`: atencion terminada.
- `no_show`: customer no asistio.

No se guardara `tenant_proposed` como segundo estado simultaneo. La existencia
de una propuesta activa y `appointments.status = pending_customer` expresa esa
situacion sin duplicar fuentes de verdad.

Transiciones permitidas:

```text
pending_tenant -> confirmed
pending_tenant -> pending_customer
pending_tenant -> rejected
pending_tenant -> cancelled

pending_customer -> confirmed
pending_customer -> pending_customer  (nueva contrapropuesta del tenant)
pending_customer -> pending_tenant     (rechazo/expiracion de solicitud)
pending_customer -> confirmed          (rechazo/expiracion de reprogramacion)
pending_customer -> cancelled

confirmed -> pending_customer         (reprogramacion propuesta)
confirmed -> cancelled
confirmed -> completed
confirmed -> no_show
```

Reglas:

- Toda transicion se valida en backend.
- Toda transicion genera un evento de auditoria.
- Estados terminales no se editan; una nueva atencion crea otra cita.
- Solo puede existir una contrapropuesta activa por cita.
- La propuesta conserva el estado anterior para restaurar `pending_tenant` o
  `confirmed` al ser rechazada o expirar.
- Reprogramar una cita confirmada conserva el horario anterior en auditoria.

## Modelo de datos propuesto

### `appointment_settings`

Una fila por tenant en el MVP:

- `id`
- `tenant_id` unique
- `doctor_user_id`
- `timezone`, inicialmente `America/Mexico_City`
- `slot_interval_minutes`, por ejemplo 15
- `default_duration_minutes`, por ejemplo 30
- `minimum_notice_minutes`
- `booking_window_days`
- `customer_cancellation_notice_minutes`
- `proposal_hold_minutes`
- `cancellation_policy`: `no_penalty` o `late_fee_review`
- `late_fee_type`: `fixed` o `percentage`, nullable
- `late_fee_value` nullable
- `late_fee_catalog_item_id` nullable
- `is_customer_booking_enabled`
- `created_by`
- timestamps

Validaciones:

- `doctor_user_id` pertenece al tenant, esta activo y tiene perfil veterinario.
- Minutos y dias usan rangos razonables y no valores negativos.
- La zona horaria debe ser un identificador IANA valido.

### `doctor_schedules`

Horario semanal recurrente:

- `id`
- `tenant_id`
- `doctor_user_id`
- `weekday`, 1 a 7
- `starts_at`, hora local
- `ends_at`, hora local
- `is_active`
- timestamps

Debe permitir mas de un bloque por dia para descansos, por ejemplo
09:00-14:00 y 16:00-19:00.

### `schedule_blocks`

Ausencias y bloqueos no recurrentes:

- `id`
- `tenant_id`
- `doctor_user_id`
- `starts_at` UTC
- `ends_at` UTC
- `reason` nullable
- `created_by`
- timestamps

Ejemplos: vacaciones, comida extraordinaria, cirugia interna o cierre.

### Cambios en `catalog_items`

- `is_bookable` boolean default false
- `appointment_duration_minutes` nullable
- `appointment_buffer_minutes` default 0
- `booking_description` nullable

Solo servicios activos con `is_bookable = true` aparecen en Ionic.

### `appointments`

- `id`
- `tenant_id`
- `customer_id`
- `animal_id`
- `doctor_user_id`
- `catalog_item_id`
- `service_name_snapshot`
- `animal_name_snapshot`
- `doctor_name_snapshot`
- `starts_at` UTC
- `ends_at` UTC
- `timezone`
- `duration_minutes`
- `buffer_minutes`
- `status`
- `customer_reason`
- `internal_notes` nullable
- `requested_at`
- `confirmed_at` nullable
- `completed_at` nullable
- `cancelled_at` nullable
- `cancelled_by` nullable
- `cancellation_reason` nullable
- `is_late_cancellation`
- `cancellation_fee_status`: `not_applicable`, `pending_review`, `waived` o `charged`
- `cancellation_fee_amount` nullable
- `created_by_user_id`
- timestamps

Indices minimos:

- `tenant_id`, `status`, `starts_at`
- `tenant_id`, `doctor_user_id`, `starts_at`
- `tenant_id`, `customer_id`, `starts_at`
- `tenant_id`, `animal_id`, `starts_at`

La cita conserva `customer_id`, aunque la mascota ya lo tenga, para consultas,
auditoria y seguridad explicitas.

### `appointment_proposals`

- `id`
- `tenant_id`
- `appointment_id`
- `proposed_by_user_id`
- `starts_at` UTC
- `ends_at` UTC
- `duration_minutes`
- `previous_appointment_status`
- `message` nullable
- `status`: `pending`, `accepted`, `rejected`, `expired`, `superseded`
- `expires_at`
- `responded_at` nullable
- timestamps

### `appointment_events`

Auditoria inmutable:

- `id`
- `tenant_id`
- `appointment_id`
- `actor_user_id` nullable para procesos del sistema
- `event_type`
- `previous_status` nullable
- `new_status` nullable
- `metadata` JSON nullable
- `created_at`

Eventos iniciales:

- `appointment.requested`
- `appointment.confirmed`
- `appointment.rejected`
- `appointment.proposed`
- `appointment.proposal_accepted`
- `appointment.proposal_rejected`
- `appointment.proposal_expired`
- `appointment.cancelled`
- `appointment.completed`
- `appointment.no_show`

## Calculo de disponibilidad

La disponibilidad se calcula bajo demanda:

```text
horario semanal del veterinario
- bloqueos/ausencias
- citas confirmadas
- contrapropuestas vigentes retenidas
- margen antes/despues de servicios
= slots disponibles
```

Reglas:

- El API recibe fecha o rango corto, nunca un rango ilimitado.
- El customer no puede consultar fuera de `booking_window_days`.
- Se respeta `minimum_notice_minutes`.
- Los inicios se alinean a `slot_interval_minutes`.
- El servicio completo, incluyendo buffer, debe caber en el horario.
- `pending_tenant` no bloquea definitivamente un horario: pueden existir varias
  solicitudes pendientes, pero solo una puede confirmarse.
- `confirmed` siempre bloquea el horario.
- Una contrapropuesta pendiente bloquea temporalmente hasta `expires_at`.
- Horarios se almacenan en UTC y se presentan en la zona horaria del tenant.

### Concurrencia obligatoria

Consultar disponibilidad no reserva el horario. Confirmar, aceptar una propuesta
o crear una retencion debe ejecutar nuevamente la validacion dentro de una
transaccion.

La implementacion debera:

- bloquear las filas relevantes o usar un mecanismo de bloqueo por
  tenant/veterinario/intervalo;
- comprobar solapamiento usando `starts_at < nuevo_end` y
  `ends_at > nuevo_start`;
- considerar citas confirmadas y propuestas vigentes;
- responder `409 Conflict` si el horario dejo de estar disponible;
- incluir una prueba de dos confirmaciones concurrentes.

No basta un indice unique sobre `starts_at`, porque citas de distinta duracion
pueden solaparse aunque comiencen a horas diferentes.

## Notificaciones y correo

### Fuentes de verdad

- Tenant: `tenant_notifications`.
- Customer: `portal_notifications`.
- Email: canal adicional generado a partir del mismo evento de dominio.
- Push: canal de entrega al movil, nunca fuente de verdad.

No se crearan notificaciones separadas para Laravel web y tenant Ionic. Ambos
clientes consumiran el mismo registro de `tenant_notifications`, por lo que leer
en uno debe reflejarse en el otro.

### Matriz de canales

| Destinatario | Laravel web | Ionic in-app | Push movil | Email |
| --- | --- | --- | --- | --- |
| Tenant | Si | Si | Si | Si |
| Customer | No en MVP | Si | Si | Si |

Comportamiento esperado:

- Tenant recibe la solicitud en campana/lista del panel Laravel.
- La misma notificacion aparece en la sesion tenant de Ionic.
- Tenant recibe push si tiene dispositivo registrado y permisos concedidos.
- Tenant recibe email operativo.
- Customer recibe notificacion persistente en su portal Ionic.
- Customer recibe push si tiene dispositivo registrado y permisos concedidos.
- Customer recibe email.

### Eventos y destinatarios

| Evento | Tenant | Customer |
| --- | --- | --- |
| Solicitud creada | In-app, push, email | Confirmacion de recepcion in-app, push, email |
| Cita confirmada | Actualizacion in-app | In-app, push, email |
| Solicitud rechazada | Actualizacion in-app | In-app, push, email |
| Contrapropuesta creada | Actualizacion in-app | In-app, push, email |
| Contrapropuesta aceptada | In-app, push, email | Actualizacion in-app |
| Contrapropuesta rechazada | In-app, push, email | Actualizacion in-app |
| Cita cancelada por tenant | Actualizacion in-app | In-app, push, email |
| Cita cancelada por customer | In-app, push, email | Actualizacion in-app, email |
| Recordatorio futuro | Opcional | In-app, push, email |

"Actualizacion in-app" significa que el evento queda registrado, aunque se
pueda evitar un segundo correo redundante al actor que acaba de realizar la accion.

### Implementacion

- Crear un servicio de dominio para publicar eventos de agenda.
- Extender/usar `PortalNotificationService` para customer.
- Centralizar notificaciones tenant en `TenantNotificationService`.
- Crear Mailables por plantilla o una plantilla parametrizable de agenda.
- Despachar emails y push mediante queues despues del commit.
- Usar claves idempotentes para evitar duplicados por reintentos.
- Incluir `appointment_id`, `type` y `url` en `data`.
- No incluir `internal_notes` en email, push ni payload customer.
- Deep links tenant abren la cita en agenda.
- Deep links customer abren el detalle de su cita en `/portal`.

## API propuesta

Todas las rutas requieren `auth:sanctum`, acceso movil y aislamiento de tenant.

### Customer portal

- `GET /api/v1/portal/appointments/bootstrap`
- `GET /api/v1/portal/appointments/services`
- `GET /api/v1/portal/appointments/availability`
- `GET /api/v1/portal/appointments`
- `GET /api/v1/portal/appointments/{appointment}`
- `POST /api/v1/portal/appointments`
- `POST /api/v1/portal/appointments/{appointment}/proposals/{proposal}/accept`
- `POST /api/v1/portal/appointments/{appointment}/proposals/{proposal}/reject`
- `POST /api/v1/portal/appointments/{appointment}/cancel`

Reglas:

- El customer solo ve citas de su acceso actual.
- `animal_id` se valida contra asignaciones activas.
- `catalog_item_id` debe ser servicio agendable del tenant.
- Nunca se aceptan `tenant_id`, `customer_id` o `doctor_user_id` del customer.
- La cancelacion respeta la politica de anticipacion.

### Tenant movil

- `GET /api/v1/appointments/bootstrap`
- `GET /api/v1/appointments`
- `GET /api/v1/appointments/{appointment}`
- `POST /api/v1/appointments`
- `POST /api/v1/appointments/{appointment}/confirm`
- `POST /api/v1/appointments/{appointment}/reject`
- `POST /api/v1/appointments/{appointment}/proposals`
- `POST /api/v1/appointments/{appointment}/cancel`
- `POST /api/v1/appointments/{appointment}/complete`
- `POST /api/v1/appointments/{appointment}/no-show`

### Panel Laravel tenant

Rutas web equivalentes para:

- agenda diaria/semanal;
- detalle de cita;
- confirmar, rechazar y proponer;
- crear cita manual;
- cancelar, completar y marcar no show;
- configurar servicios, horario, bloqueos y politicas.

La logica no debe duplicarse entre controladores web y API. Ambos deben usar
servicios de dominio compartidos.

## Roadmap de implementacion

## Paso 0: auditoria y normalizacion de roles

Objetivo: tener autorizacion consistente antes de exponer agenda.

Estado: completado en desarrollo local el 2026-06-19. La migracion quedo
aplicada y la suite completa paso. Debe aplicarse y verificarse en los demas
ambientes durante su despliegue. Consultar
`docs/appointment-scheduling-checkpoint.md`.

Tareas:

- Corregir `RoleSeeder` para usar `client-admin`.
- Auditar referencias a `admin` y `client-admin`.
- Diseñar migracion/comando idempotente de roles existentes.
- Confirmar acceso de `super-admin`, tenant y customer.
- Crear policies o capacidades especificas de agenda.
- Probar que cajero/asistente/customer no entran a administracion de agenda.

Criterios de aceptacion:

- No se pierde acceso de administradores existentes.
- `client-admin` es canonico en codigo, seeds y pruebas.
- Customer continua bloqueado del panel tenant.

## Paso 1: contrato funcional y prototipo de estados

Objetivo: congelar reglas antes de crear tablas/UI.

Estado: completado el 2026-06-19. Contrato funcional version 1.0 registrado en
`docs/appointment-scheduling-functional-contract.md`.

Tareas:

- Confirmar nombres finales mostrados al usuario.
- Confirmar politica de cancelacion.
- Confirmar expiracion de contrapropuestas.
- Confirmar anticipacion minima y ventana maxima iniciales.
- Definir mensajes y plantillas de correo.
- Documentar payloads API y codigos de error.
- Definir comportamiento al desactivar servicio, customer o veterinario.

Criterios de aceptacion:

- Todas las transiciones tienen actor, permiso y efecto definidos.
- No quedan estados ambiguos para reprogramacion.

## Paso 2: migraciones y modelos

Objetivo: crear la base persistente de agenda.

Estado: completado en desarrollo local el 2026-06-19. Las siete migraciones de
agenda quedaron aplicadas en el lote 32 y la suite completa paso. Consultar
`docs/appointment-scheduling-checkpoint.md`.

Tareas:

- Crear migraciones de configuracion, horarios, bloqueos, citas, propuestas y eventos.
- Extender `catalog_items` con configuracion agendable.
- Crear modelos, casts y relaciones.
- Agregar relaciones a Tenant, User, Customer, Animal y CatalogItem.
- Agregar factories para pruebas.
- Definir enums PHP o constantes centralizadas para estados/eventos.

Criterios de aceptacion:

- Migraciones suben y bajan en una base limpia.
- Relaciones respetan tenant y llaves foraneas.
- Citas existentes no dependen de cambios posteriores en duracion del servicio.

## Paso 3: configuracion tenant

Objetivo: permitir que `client-admin` prepare la agenda.

Estado: completado en desarrollo local el 2026-06-19. La configuracion backend,
la pestaña Laravel y las pruebas quedaron implementadas. Consultar
`docs/appointment-scheduling-checkpoint.md`.

Tareas:

- Seleccionar veterinario agendable con perfil profesional.
- Configurar zona horaria y politicas.
- Configurar horario semanal con multiples bloques por dia.
- Crear/editar/eliminar bloqueos.
- Marcar servicios agendables y definir duracion/buffer.
- Mostrar validaciones de solapamiento en horarios y bloqueos.
- Crear estado vacio/onboarding cuando la agenda no este lista.

Criterios de aceptacion:

- No se habilita reserva customer sin veterinario, horario y servicio.
- Solo administrador cambia configuracion.
- Un servicio no agendable nunca aparece en Ionic customer.

## Paso 4: motor de disponibilidad

Objetivo: devolver slots correctos y consistentes.

Estado: completado en desarrollo local el 2026-06-20. El motor interno, DTO y
pruebas quedaron implementados sin exponer endpoints. Consultar
`docs/appointment-scheduling-checkpoint.md`.

Tareas:

- Crear `AppointmentAvailabilityService`.
- Generar slots por servicio, fecha y zona horaria.
- Restar bloqueos, citas confirmadas y propuestas retenidas.
- Aplicar margen, anticipacion y ventana maxima.
- Manejar dias sin horario y cambios de horario estacional.
- Limitar rangos y paginar/calendario si procede.
- Agregar cache corta solo si mediciones lo justifican.

Criterios de aceptacion:

- No se ofrecen slots que no contienen la duracion completa.
- Horarios UTC/local se convierten correctamente.
- Dos servicios con distinta duracion producen slots correctos.

## Paso 5: servicio de dominio y concurrencia

Objetivo: centralizar operaciones y evitar doble reservacion.

Estado: completado en desarrollo local el 2026-06-20. El servicio de dominio,
los locks, la idempotencia, la expiracion y sus pruebas quedaron implementados.
Consultar `docs/appointment-scheduling-checkpoint.md` para el detalle verificable.

Tareas:

- Crear `AppointmentService` para solicitar, confirmar, proponer, responder,
  cancelar, completar y marcar no show.
- Validar transiciones y permisos.
- Revalidar disponibilidad dentro de transacciones.
- Implementar retencion y expiracion de propuestas.
- Registrar `appointment_events` en cada cambio.
- Crear comando/job para expirar propuestas vencidas.
- Hacer operaciones idempotentes ante doble tap o reintentos.

Criterios de aceptacion:

- Dos confirmaciones concurrentes no pueden solapar al veterinario.
- Aceptar dos veces una propuesta no duplica eventos ni notificaciones.
- Una propuesta expirada no puede aceptarse.

## Paso 6: API customer

Objetivo: habilitar el flujo completo desde Ionic customer.

Estado: completado en desarrollo local el 2026-06-20. Se implementaron nueve
endpoints, contexto seguro, Resources, idempotencia HTTP, rate limits y pruebas.
Consultar `docs/appointment-scheduling-checkpoint.md` y
`docs/appointment-scheduling-customer-api.md`.

Tareas:

- Crear endpoints de servicios, disponibilidad, lista y detalle.
- Crear solicitud con mascota obligatoria.
- Aceptar/rechazar contrapropuesta.
- Cancelar segun politica.
- Respetar `show_appointments` y acceso portal activo.
- Crear Resources/serializadores sin notas internas.
- Agregar rate limit a solicitudes y consultas de disponibilidad.

Criterios de aceptacion:

- No existe fuga cross-tenant o entre customers del mismo tenant.
- Manipular IDs no permite usar otra mascota, servicio o cita.
- Customer nunca recibe `internal_notes`.

## Paso 7: API y panel Laravel tenant

Objetivo: operar la agenda desde web y desde Ionic tenant.

Estado: completado en desarrollo local el 2026-06-20. Servicios, permisos, API,
acciones web y panel Laravel diario/semanal quedaron implementados y probados.

Tareas:

- Crear endpoints API tenant.
- Crear vista diaria y semanal en panel Laravel.
- Agregar filtros por estado y fecha.
- Mostrar solicitudes pendientes primero.
- Crear detalle con historial de eventos.
- Implementar confirmar, rechazar y contrapropuesta.
- Permitir cita manual para customers existentes.
- Implementar cancelacion, completar y no show.
- Agregar agenda al menu tenant siempre; mostrar configuracion pendiente si no
  esta habilitada.

Criterios de aceptacion:

- Web e Ionic ejecutan la misma logica de dominio.
- Administrador y veterinario ven motivo; solo ellos ven nota interna.
- Asistente y cajero quedan bloqueados en MVP.

## Paso 8: Ionic customer

Objetivo: completar la solicitud y respuesta desde el portal customer.

Estado: completado en desarrollo local el 2026-06-20. El portal Ionic incluye
lista, historial, solicitud, disponibilidad, detalle, contrapropuestas,
cancelacion, idempotencia y deep links preparados.

Pantallas:

- Proximas citas y solicitudes.
- Historial de citas.
- Nueva solicitud: mascota, servicio, fecha, slot y motivo.
- Detalle de cita con estado y linea de tiempo.
- Contrapropuesta con aceptar/rechazar.
- Cancelacion con motivo y confirmacion.

Tareas:

- Omitir selector de veterinaria, tenant y doctor.
- Cargar solo mascotas asignadas.
- Manejar slot ocupado con respuesta 409 y refrescar disponibilidad.
- Mostrar horarios en zona del tenant.
- Integrar deep links desde notificaciones.
- Diseñar estados offline/error sin crear solicitudes fantasma.

Criterios de aceptacion:

- Customer completa una solicitud sin introducir datos del tenant.
- Una contrapropuesta se entiende claramente antes de aceptarla.
- La UI nunca presenta una solicitud como confirmada antes del tenant.

## Paso 9: Ionic tenant

Estado: completado en desarrollo local el 2026-06-21. La app tenant incluye
agenda diaria/semanal, resumen operativo, alta manual, detalle, transiciones y
deep links desde notificaciones sobre la API existente.

Objetivo: operar solicitudes desde la app movil del tenant.

Pantallas:

- Resumen de hoy.
- Solicitudes pendientes.
- Agenda diaria/semanal simplificada.
- Detalle y acciones.
- Formulario de contrapropuesta.

Tareas:

- Reutilizar endpoints y estados del panel web.
- Integrar notificaciones tenant existentes.
- Actualizar badge/contador al responder.
- Integrar deep links de solicitud/cambio.

Criterios de aceptacion:

- Una accion en Ionic se refleja en Laravel web.
- Leer notificacion en una superficie se reconcilia en la otra.

## Paso 10: notificaciones, correo, push y live sync

Estado: en progreso. Auditoria, fundacion, notificaciones persistentes y correo
en queue completados el 2026-06-21. Continua FCM backend para Android.

Objetivo: entregar cada evento por los canales acordados.

Tareas:

- Crear tipos oficiales de notificacion de agenda.
- Implementar notificaciones persistentes tenant y customer.
- Crear plantillas de correo y Jobs en queue.
- Integrar push customer con el roadmap FCM existente.
- Ampliar push a usuarios tenant y sus dispositivos.
- Agregar deep links por rol.
- Emitir despues del commit de la transaccion.
- Deduplicar por evento/destinatario/canal.
- Reconciliar mediante pull aunque live o push fallen.
- Implementar WebSocket/SSE solo si se adopta para sincronizacion live; polling
  incremental sigue siendo fallback obligatorio.

Criterios de aceptacion:

- Tenant ve el evento en Laravel web e Ionic y recibe email.
- Customer ve el evento en Ionic y recibe email.
- Push fallido no elimina la notificacion persistente.
- Email fallido se reintenta sin duplicar eventos.
- No se filtran notas internas o datos clinicos sensibles.

## Paso 11: recordatorios y tareas programadas

Objetivo: gestionar expiraciones y recordatorios de manera confiable.

Tareas:

- Expirar contrapropuestas vencidas.
- Enviar recordatorio configurable, inicialmente 24 horas antes.
- Evitar recordatorios de citas canceladas/no confirmadas.
- Definir comportamiento al cambiar horario despues de programar recordatorio.
- Ejecutar Scheduler y queues en staging/produccion.

Criterios de aceptacion:

- Cada recordatorio se envia una sola vez por canal.
- Expiraciones se ejecutan aunque ningun usuario abra la app.

## Paso 12: QA de seguridad y negocio

Objetivo: validar aislamiento, concurrencia y reglas completas.

Casos obligatorios:

- Customer sin portal activo no usa agenda.
- Customer no ve citas de otro customer.
- Customer no agenda mascota no asignada.
- Customer no selecciona servicio inactivo/no agendable.
- Customer no suplanta tenant, customer o doctor en payload.
- Usuario tenant de otro tenant no accede a la cita.
- Cajero/asistente no ven motivo o nota interna.
- Horario bloqueado no aparece.
- Cita confirmada bloquea el intervalo completo y buffer.
- Dos confirmaciones concurrentes producen una sola reserva valida.
- Contrapropuesta expirada no se acepta.
- Cambio de duracion del catalogo no cambia cita existente.
- Cancelacion fuera de politica se rechaza al customer.
- Correos y notificaciones se generan una sola vez.
- Push fallido no rompe la operacion.
- Zona horaria y cambio de fecha se muestran correctamente.

Pruebas recomendadas:

- Unitarias para estados y slots.
- Feature para APIs customer/tenant.
- Integracion para transacciones y solapamientos.
- Browser/E2E para panel Laravel.
- E2E Ionic para ambos roles.
- Pruebas manuales push/email en dispositivos reales.

## Paso 13: piloto y despliegue gradual

Objetivo: liberar con control operativo.

Tareas:

- Agregar feature flag por tenant.
- Activar primero en un tenant de prueba.
- Verificar zona horaria, horarios, correo, queues y push.
- Monitorear conflictos 409, emails fallidos y Jobs fallidos.
- Crear metricas: solicitudes, confirmadas, rechazadas, contrapropuestas,
  cancelaciones y no show.
- Preparar rollback funcional desactivando reservas sin borrar citas.
- Documentar soporte para corregir una cita sin alterar auditoria.

Criterios de aceptacion:

- Desactivar agenda impide nuevas solicitudes pero conserva historial.
- No hay citas duplicadas ni fuga de datos durante el piloto.
- Tenant puede operar web y movil con los mismos resultados.

## Orden recomendado de entrega

### Bloque A: cimientos

1. Paso 0: roles.
2. Paso 1: contrato.
3. Paso 2: datos.
4. Paso 3: configuracion.

### Bloque B: motor

1. Paso 4: disponibilidad.
2. Paso 5: dominio y concurrencia.
3. Paso 6: API customer.
4. Paso 7: API/panel tenant.

### Bloque C: experiencias

1. Paso 8: Ionic customer.
2. Paso 9: Ionic tenant.
3. Paso 10: canales de notificacion.
4. Paso 11: recordatorios.

### Bloque D: salida

1. Paso 12: QA.
2. Paso 13: piloto y despliegue.

No se debe iniciar UI completa antes de cerrar pasos 0 a 5.

## Fuera de alcance MVP

- Agenda publica para personas sin cuenta.
- Portal web customer separado de Ionic.
- Multiples sucursales.
- Seleccion de varios veterinarios.
- Citas simultaneas por consultorio/recurso.
- Reservas recurrentes.
- Lista de espera.
- Pagos o anticipos para reservar.
- Videoconsulta asociada a la cita.
- Sincronizacion con Google Calendar/Outlook.
- Preferencias granulares de notificaciones.

## Evolucion posterior

### Multiples veterinarios

- Convertir configuracion unica en disponibilidad por veterinario.
- Permitir seleccionar doctor o "cualquiera disponible".
- Crear permisos clinicos explicitos si el perfil ya no basta.

### Sucursales

- Crear `branches` y `branch_schedules`.
- Agregar `branch_id` a configuracion, citas, horarios y bloqueos.
- Calcular disponibilidad como interseccion sucursal/veterinario.
- Permitir al customer seleccionar sucursal antes del servicio.

### Recursos

- Consultorios, equipos o cupos simultaneos.
- Reglas de capacidad adicionales al veterinario.

## Definicion de terminado del MVP

El MVP se considera terminado cuando:

- Roles de administrador estan normalizados.
- Tenant configura veterinario, horario, bloqueos, politicas y servicios.
- Customer autenticado solicita cita para una mascota asignada.
- Tenant confirma, rechaza o contrapropone desde Laravel e Ionic.
- Customer acepta/rechaza contrapropuesta desde Ionic.
- No existen solapamientos confirmados para el veterinario.
- Tenant recibe notificacion en Laravel, Ionic, push y email.
- Customer recibe notificacion en Ionic, push y email.
- Estados, eventos y comunicaciones son auditables e idempotentes.
- Seguridad cross-tenant/customer esta cubierta por pruebas.
- Agenda puede deshabilitarse sin perder historial.
