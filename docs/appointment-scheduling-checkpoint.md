# Checkpoint: agenda privada de citas

Ultima actualizacion: 2026-06-21

## Estado general

- Roadmap principal: `docs/appointment-scheduling-roadmap.md`.
- Ultimo paso trabajado: Paso 10.6, integracion push compartida en Ionic.
- Siguiente paso: Paso 10.7, configuracion Firebase/APNs y QA en dispositivo.

## Ajuste de UI: anticipacion y cancelacion en horas (post Paso 3)

Fecha: 2026-06-19

### Cambio aplicado

Los campos `minimum_notice_minutes` y `customer_cancellation_notice_minutes` se
almacenan en minutos en la base de datos. La interfaz web ahora los muestra y
recibe en horas para mejorar la usabilidad del veterinario.

Estrategia de conversion:

- Mostrar: `valor_bd / 60` (redondeado al entero mas cercano).
- Guardar: `valor_ui * 60` (conversion en `prepareForValidation` del Form Request).
- La base de datos y el motor de disponibilidad siguen trabajando en minutos sin
  cambios de migracion.

### Archivos modificados

- `resources/views/client/mi-configuracion/appointments.blade.php`:
  - Los dos campos salen del loop generico y tienen inputs propios con
    `name="minimum_notice_hours"` y `name="customer_cancellation_notice_hours"`.
  - El `value` muestra el resultado de `$setting->minimum_notice_minutes / 60`.
  - Rango del input: 0 a 168 horas (equivalente a 0 a 7 dias).

- `app/Http/Requests/UpdateAppointmentSettingsRequest.php`:
  - Nuevo metodo `prepareForValidation()` convierte `*_hours` a `*_minutes`
    antes de ejecutar la validacion.
  - Reglas validan `*_hours` (entero, min:0, max:168) y `*_minutes`
    (entero, min:0) por separado.
  - El modelo `AppointmentSetting` ignora `*_hours` automaticamente porque no
    estan en `$fillable`; solo persiste los campos `*_minutes`.

- `tests/Feature/AppointmentConfigurationTest.php`:
  - `settingsPayload()` actualizado para enviar `minimum_notice_hours: 2` y
    `customer_cancellation_notice_hours: 24` en lugar de los valores en minutos.
  - `assertDatabaseHas` sigue verificando `minimum_notice_minutes: 120` en BD.

### Verificacion

- Suite focalizada: 7 pruebas, 32 aserciones. Todas pasan.
- Suite completa revisada el 2026-06-20: 65 pruebas, 246 aserciones. Todas pasan.
- `git diff --check`: sin errores.
- No se requirio migracion ni cambio en `AppointmentSetting` ni en
  `AppointmentConfigurationService`.

## Paso 0: auditoria y normalizacion de roles

Estado: completado en desarrollo local. Pendiente de aplicar la migracion en
otros ambientes durante su despliegue.

### Auditoria realizada

- El panel usa `client-admin` para nuevas invitaciones y opciones de equipo.
- `ConfiguracionController::isTenantAdmin()` acepta temporalmente `admin` y
  `client-admin` para evitar bloqueo antes de migrar todos los ambientes.
- `RoleSeeder` todavia creaba `admin` y fue corregido.
- La base local tenia 3 usuarios con `admin`, 0 con `client-admin`, 1 customer y
  1 super-admin antes de ejecutar la migracion.
- No se encontro otro uso funcional de `client-admin` fuera de configuracion y
  pruebas de tema.
- Un veterinario se identifica por `veterinarian_profiles`, no por rol Spatie.

### Cambios implementados

- `RoleSeeder` ahora crea `client-admin` como administrador canonico.
- Migracion `2026_06_19_000001_normalize_tenant_admin_role.php`:
  - crea `client-admin` si falta;
  - copia asignaciones de usuarios desde `admin`;
  - copia permisos directos del rol legado;
  - es idempotente mediante `insertOrIgnore`;
  - conserva `admin` y sus asignaciones para un despliegue sin bloqueo;
  - limpia el cache de Spatie Permission.
- Se agregaron pruebas de copia de rol/permisos e idempotencia.
- Se agrego una prueba explicita que impide acceso web a customer incluso si el
  plan del tenant permite web.

### Ejecucion local verificada

- Migracion aplicada: `2026_06_19_000001_normalize_tenant_admin_role`.
- Cache de Spatie Permission reiniciado.
- Conteo posterior: 3 usuarios `admin` y los mismos 3 con `client-admin`.
- Administradores legados sin `client-admin`: 0.
- Suite focalizada de normalizacion: 2 pruebas, 5 aserciones.
- Suite de sesiones/acceso: 6 pruebas, 16 aserciones.
- Suite completa: 55 pruebas, 191 aserciones.
- `git diff --check`: sin errores.

### Archivos del Paso 0

- `database/seeders/RoleSeeder.php`
- `database/migrations/2026_06_19_000001_normalize_tenant_admin_role.php`
- `tests/Feature/TenantAdminRoleNormalizationTest.php`
- `tests/Feature/UserAccessSessionManagerTest.php`
- `docs/appointment-scheduling-roadmap.md`
- `docs/appointment-scheduling-checkpoint.md`

### Compatibilidad temporal

No eliminar todavia:

```php
$user->hasAnyRole(['admin', 'client-admin']);
```

Debe mantenerse hasta confirmar que la migracion fue aplicada y auditada en
todos los ambientes. En una limpieza posterior se podra retirar `admin` del
codigo y finalmente de la base.

### Verificacion requerida por ambiente

1. Ejecutar `php artisan migrate`.
2. Ejecutar `php artisan permission:cache-reset`.
3. Comparar cantidad de usuarios `admin` contra usuarios `client-admin`.
4. Confirmar que todo usuario legado puede abrir configuracion del tenant.
5. Confirmar que customer sigue bloqueado del panel tenant.
6. No borrar el rol `admin` durante esta fase.

## Paso 1: contrato funcional y prototipo de estados

Estado: completado en documentacion el 2026-06-19.

Documento fuente:

- `docs/appointment-scheduling-functional-contract.md`

### Decisiones cerradas

- Los servicios viven en `catalog_items`; los estados no son catalogo.
- Estados: `pending_tenant`, `pending_customer`, `confirmed`, `rejected`,
  `cancelled`, `completed` y `no_show`.
- Anticipacion default: 2 horas, configurable.
- Ventana maxima default: 60 dias, configurable.
- Contrapropuesta default: 24 horas, configurable entre 1 y 72 horas.
- La expiracion nunca rebasa fecha propuesta menos anticipacion minima.
- Una propuesta vigente retiene el nuevo horario.
- Reprogramar conserva el horario original mientras customer responde.
- Cancelacion gratuita default: hasta 24 horas antes, configurable.
- MVP soporta `no_penalty` y revision manual de cargo tardio.
- El cargo tardio no se cobra automaticamente; tenant decide aplicar o perdonar.
- Cobro posterior/siguiente consulta puede usar servicio de catalogo y cuenta
  existente.
- Anticipo, retencion de pago y reembolso quedan fuera del primer MVP.
- Se cerraron actores y transiciones por estado.
- Se cerro matriz de eventos para Laravel, Ionic, push y email.
- Se definio contenido permitido/prohibido de correos y notificaciones.
- Se definieron payloads base, errores HTTP e idempotencia.
- Servicio, customer o veterinario desactivado nunca elimina historial.

### Archivos del Paso 1

- `docs/appointment-scheduling-functional-contract.md`
- `docs/appointment-scheduling-roadmap.md`
- `docs/appointment-scheduling-checkpoint.md`

### Verificacion

- Revision cruzada de estados, transiciones y expiracion.
- `git diff --check` sin errores.
- No se crearon migraciones, modelos, servicios, Jobs ni UI en este paso.

## Paso 2: migraciones y modelos

Estado: completado en desarrollo local el 2026-06-19.

### Migraciones implementadas

- `2026_06_19_000002_add_appointment_fields_to_catalog_items.php`
- `2026_06_19_000003_create_appointment_settings_table.php`
- `2026_06_19_000004_create_doctor_schedules_table.php`
- `2026_06_19_000005_create_schedule_blocks_table.php`
- `2026_06_19_000006_create_appointments_table.php`
- `2026_06_19_000007_create_appointment_proposals_table.php`
- `2026_06_19_000008_create_appointment_events_table.php`

Todas quedaron aplicadas localmente en el lote 32.

### Tablas y campos

- `catalog_items` ahora soporta servicio agendable, duracion, buffer y
  descripcion de reserva.
- `appointment_settings` guarda veterinario, zona horaria, reglas de reserva,
  cancelacion tardia y habilitacion customer.
- `doctor_schedules` soporta multiples bloques semanales por dia.
- `schedule_blocks` guarda ausencias en UTC y usa soft delete.
- `appointments` guarda participantes, horario, snapshots, estado, cancelacion
  y revision de cargo tardio.
- `appointment_proposals` conserva estado previo, vigencia y respuesta.
- `appointment_events` es una linea de auditoria sin `updated_at`.

### Enums implementados

- `AppointmentStatus`
- `AppointmentProposalStatus`
- `AppointmentEventType`
- `AppointmentCancellationPolicy`
- `AppointmentCancellationFeeStatus`
- `AppointmentLateFeeType`
- `AppointmentLateFeeCollectionMethod`

Se usaron columnas string con casts a enums PHP para permitir evolucionar
estados sin modificar enums nativos de MySQL.

### Modelos y relaciones

Modelos nuevos:

- `AppointmentSetting`
- `DoctorSchedule`
- `ScheduleBlock`
- `Appointment`
- `AppointmentProposal`
- `AppointmentEvent`

Relaciones agregadas en Tenant, User, Customer, Animal, CatalogItem y
VeterinarianProfile.

### Integridad historica

- Customer, mascota, veterinario y servicio son obligatorios por regla de
  dominio al crear una cita.
- Sus llaves en `appointments` permiten null solo para soportar una eventual
  eliminacion fisica sin destruir el historial.
- La cita guarda snapshots de nombre de servicio, mascota y veterinario.
- Relaciones a Customer, Animal y CatalogItem incluyen registros con soft
  delete mediante `withTrashed()`.
- Propuestas y eventos se eliminan solo si se elimina la cita/tenant completo.

### Factories y pruebas

Factories nuevas:

- `TenantFactory`
- `AppointmentSettingFactory`
- `DoctorScheduleFactory`
- `ScheduleBlockFactory`
- `AppointmentFactory`
- `AppointmentProposalFactory`
- `AppointmentEventFactory`

Pruebas nuevas en `AppointmentDataModelTest`:

- columnas requeridas por contrato;
- casts a enums y metadata;
- relaciones principales;
- lectura historica despues de soft delete.

### Verificacion

- SQL revisado con `php artisan migrate --pretend`.
- Siete migraciones aplicadas correctamente.
- Pruebas de datos: 3 pruebas, 23 aserciones.
- Suite completa: 58 pruebas, 214 aserciones.
- Laravel Pint: correcto en archivos nuevos.
- Sintaxis PHP: correcta.
- `git diff --check`: sin errores.
- Los metodos `down()` quedaron definidos; no se ejecuto rollback destructivo
  sobre la base compartida.

### Fuera de este paso

No se crearon todavia:

- motor de disponibilidad;
- servicios de dominio;
- controladores o rutas;
- Jobs, correos o notificaciones;
- pantallas Laravel o Ionic.

## Paso 3: configuracion tenant

Estado: completado en desarrollo local el 2026-06-19.

### Backend implementado

- Gate `manage-appointment-configuration` para `client-admin`.
- Compatibilidad temporal con `admin` mientras termina el despliegue del Paso 0.
- `AppointmentConfigurationService` centraliza:
  - datos de configuracion para la vista;
  - seleccion y validacion de veterinario activo;
  - reglas generales y politica de cancelacion;
  - readiness de veterinario, horario y servicio;
  - horarios semanales sin solapamientos;
  - ausencias/bloqueos sin solapamientos;
  - conversion de fechas locales a UTC;
  - servicios agendables con duracion y buffer;
  - desactivacion automatica de reservas si la configuracion deja de estar lista.
- `AppointmentConfigurationController` expone seis operaciones web.
- Cuatro Form Requests validan permisos, rangos, pertenencia y payloads.
- Se agregaron seis rutas bajo `client/mi-configuracion/agenda`.

### Interfaz Laravel

Se agrego la pestaña `Agenda` en Configuracion con:

- indicador de configuracion lista/incompleta;
- checklist de veterinario, horario y servicio;
- seleccion de veterinario con perfil profesional activo;
- zona horaria y politicas configurables;
- politica y cargo sugerido de cancelacion tardia;
- interruptor de solicitudes customer, bloqueado hasta completar requisitos;
- multiples bloques semanales por dia;
- ausencias y bloqueos futuros;
- servicios agendables con duracion, margen y descripcion;
- modo de solo lectura para usuarios sin permiso.

### Reglas de seguridad

- Solo `client-admin` y el alias temporal `admin` pueden modificar.
- Veterinarios, servicios, horarios y bloqueos se validan por `tenant_id`.
- Recursos de otro tenant responden 404.
- Solo servicios activos y de tipo `service` pueden habilitarse.
- No se puede activar agenda customer sin veterinario, horario y servicio.
- Eliminar el ultimo horario o deshabilitar el ultimo servicio apaga reservas.
- Lecturas de readiness consultan datos frescos y no dependen del cache de relaciones.

### Archivos principales del Paso 3

- `app/Providers/AuthServiceProvider.php`
- `app/Services/AppointmentConfigurationService.php`
- `app/Http/Controllers/Client/AppointmentConfigurationController.php`
- `app/Http/Requests/UpdateAppointmentSettingsRequest.php`
- `app/Http/Requests/StoreDoctorScheduleRequest.php`
- `app/Http/Requests/StoreScheduleBlockRequest.php`
- `app/Http/Requests/UpdateBookableServiceRequest.php`
- `app/Http/Controllers/Client/ConfiguracionController.php`
- `routes/web.php`
- `resources/views/client/mi-configuracion/index.blade.php`
- `resources/views/client/mi-configuracion/appointments.blade.php`
- `tests/Feature/AppointmentConfigurationTest.php`

### Verificacion

- Seis rutas de configuracion registradas.
- Pruebas focalizadas: 7 pruebas, 32 aserciones.
- Suite completa: 65 pruebas, 246 aserciones.
- Render de la pestaña validado mediante prueba HTTP/Blade.
- Laravel Pint correcto en archivos nuevos.
- Sintaxis PHP correcta.
- `git diff --check` sin errores.
- La inspeccion visual con navegador integrado no pudo iniciarse por una
  restriccion local del proceso; no se detectaron errores de render en Blade.

### Fuera de este paso

- No se agregaron migraciones.
- No se calculan slots disponibles todavia.
- No existen endpoints de citas o disponibilidad.
- No se crean citas, propuestas, Jobs o notificaciones.
- No se modifico Ionic.

## Paso 4: motor de disponibilidad

Estado: completado en desarrollo local el 2026-06-20.

### Implementacion

- `AppointmentAvailabilityService` genera disponibilidad interna por fecha.
- `AppointmentSlot` representa cada resultado con:
  - inicio/fin UTC;
  - inicio/fin en zona local;
  - zona horaria;
  - duracion;
  - buffer;
  - serializacion estable mediante `toArray()`.
- Metodo `slotsForDate()` devuelve slots para servicio y fecha.
- Metodo `slotsForRange()` devuelve un calendario agrupado por fecha.
- Los rangos se limitan a 31 dias para evitar consultas ilimitadas.
- No se agrego cache; se medira antes de introducirlo.

### Reglas aplicadas

- Requiere agenda customer habilitada, veterinario/perfil activo y servicio
  activo/agendable del mismo tenant.
- Usa duracion del servicio y fallback a duracion predeterminada.
- El intervalo de inicio se configura por tenant.
- Duracion y buffer completos deben caber dentro del horario semanal.
- Anticipacion minima y ventana maxima se calculan en zona del tenant.
- Dias sin horario activo devuelven lista vacia.
- Horarios semanales se construyen en hora local y se recorren en UTC.
- Resultados se ordenan y deduplican por inicio UTC.

### Ocupaciones

El motor resta:

- ausencias/bloqueos del veterinario;
- citas `confirmed`;
- horario original de una reprogramacion `pending_customer`;
- propuestas `pending` cuya vigencia no ha terminado;
- buffer posterior de citas y propuestas.

No bloquean:

- solicitudes `pending_tenant`;
- propuestas vencidas;
- citas/propuestas de otro tenant;
- propuestas de otro veterinario dentro del mismo tenant.

El buffer de una cita del dia anterior puede bloquear correctamente el primer
slot del dia siguiente.

### Zona horaria y DST

- Persistencia y comparaciones de ocupacion usan UTC.
- Presentacion conserva offset y zona local.
- Primavera: se omite la hora local inexistente.
- Otono: se conservan ambas horas repetidas con offsets distintos.
- La duracion representa minutos reales transcurridos incluso durante DST.

### Archivos del Paso 4

- `app/Data/AppointmentSlot.php`
- `app/Services/AppointmentAvailabilityService.php`
- `tests/Feature/AppointmentAvailabilityServiceTest.php`

### Verificacion

- Pruebas focalizadas: 16 pruebas, 36 aserciones.
- Suite completa: 81 pruebas, 282 aserciones.
- Laravel Pint: correcto.
- Sintaxis PHP: correcta.
- `git diff --check`: sin errores.

### Fuera de este paso

- No se agregaron migraciones.
- No se expusieron controladores, rutas o endpoints.
- No se crean/modifican citas desde el motor.
- No se implementaron locks de concurrencia ni idempotencia.
- No se agregaron Jobs, notificaciones, correo o cambios Ionic.

## Paso 5: servicio de dominio y concurrencia

Estado: completado en desarrollo local el 2026-06-20.

### Bloque 1: persistencia de dominio

Se agregaron y ejecutaron en el batch 33 estas migraciones:

- `2026_06_20_000001_add_domain_transition_fields_to_appointments.php`: agrega
  `rejected_at`, `rejection_reason` y `no_show_at`.
- `2026_06_20_000002_create_appointment_schedule_locks_table.php`: mutex diario
  unico por tenant, veterinario y fecha.
- `2026_06_20_000003_create_appointment_idempotency_keys_table.php`: conserva
  actor, operacion, clave, hash del payload y resultado de cada operacion.

### Bloque 2: concurrencia e idempotencia

- `AppointmentScheduleLockService` obtiene un registro diario y aplica
  `lockForUpdate` antes de confirmar, proponer o aceptar una contrapropuesta.
- `AppointmentAvailabilityService::intervalIsAvailable()` revalida el intervalo
  dentro de la transaccion y permite excluir la cita o propuesta en proceso.
- Dos solicitudes pendientes pueden pedir el mismo horario, pero solo una puede
  ocuparlo al confirmarse.
- `AppointmentIdempotencyService` devuelve el mismo modelo al repetir una clave
  con el mismo payload y rechaza reutilizarla con datos distintos.
- La idempotencia se guarda dentro de la misma transaccion; un rollback no deja
  una clave consumida ni eventos parciales.

### Bloque 3: servicio de dominio

`AppointmentService` implementa:

- solicitud del customer;
- confirmacion y rechazo del tenant;
- creacion y reemplazo de contrapropuestas;
- aceptacion y rechazo de contrapropuestas;
- cancelacion y evaluacion de cargo tardio;
- finalizacion y marcado como no show;
- expiracion individual y por lote de contrapropuestas.

El servicio valida aislamiento por tenant, actores `client-admin`/vet/customer,
acceso activo al portal, mascota asignada, servicio activo, transiciones de
estado, vencimiento de propuestas y disponibilidad. Al rechazar o vencer una
contrapropuesta restaura el estado anterior cuando corresponde.

### Bloque 4: auditoria y expiracion

- Cada transicion crea un `appointment_event` en su misma transaccion.
- `AppointmentEvent` bloquea actualizaciones y eliminaciones desde Eloquent.
- `ExpireAppointmentProposals` procesa propuestas vencidas de forma idempotente.
- El comando `appointments:expire-proposals --limit=500` permite ejecucion manual.
- El scheduler ejecuta el Job cada cinco minutos con `withoutOverlapping`.

### Bloque 5: archivos principales

- Servicios: `AppointmentService`, `AppointmentScheduleLockService` y
  `AppointmentIdempotencyService`.
- Dominio: `AppointmentDomainException`, `AppointmentScheduleLock` y
  `AppointmentIdempotencyKey`.
- Automatizacion: Job, comando y registro en `app/Console/Kernel.php`.
- Cobertura: `AppointmentServiceTest` y ampliacion de
  `AppointmentDataModelTest`.

### Verificacion

- Pruebas enfocadas del dominio: 16 pruebas, 53 aserciones.
- Suite completa: 97 pruebas, 337 aserciones.
- Cubiertos: doble solicitud/misma franja, lock de confirmacion, idempotencia de
  solicitud y confirmacion, transiciones, autorizacion, rollback y expiracion.
- El comando se ejecuto dos veces sin duplicar eventos.
- `schedule:list` muestra la expiracion cada cinco minutos.
- Pint, sintaxis PHP y `git diff --check` finalizaron correctamente.

No se realizo una prueba de carga multiproceso. La exclusion real descansa en
`lockForUpdate` y la clave unica de MySQL, mientras la carrera logica esta cubierta
por las pruebas del servicio.

### Fuera de este paso

- No se agregaron endpoints, Requests, Resources ni respuestas HTTP.
- No se agregaron notificaciones, correos, push ni cambios Ionic.

## Plan previo del Paso 6

### Estado del siguiente paso

- Siguiente: Paso 6, API para customer.
- Estado: cerrado; se conserva como referencia del plan ejecutado.
- Objetivo: dejar disponible para Ionic todo el flujo customer mediante una API
  autenticada, sin implementar aun sus pantallas.

### Orden de implementacion propuesto

1. Revisar las rutas API y el mecanismo de autenticacion actual de Ionic.
2. Crear un resolver de contexto que obtenga tenant, customer, acceso al portal y
   mascotas permitidas desde el usuario autenticado; no confiar en `tenant_id` o
   `customer_id` enviados por el cliente.
3. Exponer el bootstrap de agenda: configuracion publica, mascotas autorizadas y
   servicios activos que admiten cita.
4. Exponer disponibilidad por servicio, veterinario y fecha utilizando
   `AppointmentAvailabilityService`.
5. Exponer listado paginado y detalle de citas propias.
6. Crear la solicitud de cita con mascota obligatoria y encabezado
   `Idempotency-Key`, delegando la operacion a `AppointmentService`.
7. Exponer aceptar/rechazar contrapropuesta y cancelar cita conforme a la
   politica configurada.
8. Crear Form Requests, API Resources y un mapeo JSON estable para
   `AppointmentDomainException`.
9. Aplicar rate limit a disponibilidad y operaciones de escritura.
10. Agregar pruebas de contrato, autenticacion, autorizacion, aislamiento e
    idempotencia; actualizar nuevamente este checkpoint al terminar.

### Contrato y seguridad obligatorios

- Respetar `show_appointments`, portal activo y vinculacion customer-user.
- Verificar que mascota, servicio, cita y contrapropuesta pertenezcan al contexto
  autenticado.
- Impedir fugas entre tenants y entre customers del mismo tenant, incluso al
  manipular identificadores.
- No incluir `internal_notes`, datos de locks, claves de idempotencia ni otros
  campos internos en Resources.
- Usar codigos HTTP y codigos de error de dominio consistentes.
- Repetir una escritura con la misma clave y payload debe devolver el mismo
  resultado; reutilizar la clave con otro payload debe fallar.

### Entregable esperado del Paso 6

- Rutas y controlador(es) API customer.
- Form Requests y Resources.
- Resolver/politicas de acceso customer.
- Rate limit configurado.
- Pruebas Feature de todos los endpoints y escenarios de seguridad.
- Documentacion del payload y respuestas para que el Paso 8 pueda consumirlos.

### Fuera del Paso 6

- Panel web y API del tenant: Paso 7.
- Pantallas Ionic customer: Paso 8.
- Pantallas Ionic tenant: Paso 9.
- Correos, push y notificaciones Laravel/Ionic: Paso 10.

## Auditoria previa al Paso 6

Fecha: 2026-06-20.

Estado: completada. No se crearon endpoints ni se modifico comportamiento de
produccion durante esta auditoria.

### Autenticacion y contexto existente

- Ionic autentica con Laravel Sanctum mediante token Bearer en `/api/v1`.
- Las rutas customer existentes viven bajo `auth:sanctum`, `access.mobile`,
  `api.tenant` y `customer.portal`; la agenda debe reutilizar este grupo.
- `access.mobile` exige una sesion movil vigente y revoca el token reemplazado.
- `api.tenant` obtiene el tenant desde `user.tenant_id`, valida usuario, tenant,
  plan y vigencia de suscripcion. El cliente no necesita seleccionar veterinaria.
- `customer.portal` exige rol `customer` y adjunta a la request
  `customer_portal_access` y `customer_portal_setting`.
- El portal actual ya resuelve mascotas por `FinalUserPatientAssignment` y evita
  confiar en un `customer_id` recibido del cliente.

Decision: ubicar las rutas de citas dentro de `/api/v1/portal/appointments` y
reutilizar exactamente la cadena de middleware del portal customer.

### Componentes reutilizables

- `AppointmentAvailabilityService` entrega fechas UTC, fechas locales, timezone,
  duracion y buffer; limita rangos a 31 dias.
- `AppointmentService` ya soporta solicitud idempotente, aceptar/rechazar
  contrapropuesta y cancelar.
- `AppointmentDomainException` ya contiene `errorCode`, `httpStatus` y errores,
  pero todavia no tiene representacion HTTP automatica.
- `AppointmentSlot::toArray()` ya define el payload base de disponibilidad.
- El portal usa respuestas con `data` y, en listados paginados, `meta`.

### Hallazgos obligatorios antes de exponer la API

1. `EnsureCustomerPortalAccess` valida estado y fecha final, pero no valida
   `access_starts_at`, `revoked_at`, customer activo ni `CustomerUserLink` no
   revocado. `AppointmentService` cubre parte de esto solo en escrituras. El
   contexto comun debe aplicar todas las condiciones también a lecturas.
2. La base permite que un mismo usuario tenga mas de un acceso customer activo
   dentro del tenant. El middleware usa el primer registro sin una regla de
   seleccion. Para el MVP se debe rechazar el contexto ambiguo y exigir un unico
   customer activo; no aceptar `customer_id` desde Ionic como solucion.
3. `AppointmentService` verifica asignacion de mascota, pero no
   `AnimalPortalVisibilitySetting.show_appointments`. El resolver/API debe exigir
   asignacion activa y `show_appointments = true` para bootstrap, disponibilidad,
   solicitud, listado, detalle y respuestas a contrapropuestas.
4. Una `AppointmentDomainException` llegaria como error generico si el controlador
   no la traduce. Se requiere un envelope JSON estable con `message`, `code` y
   `errors`, conservando su status HTTP.
5. El rate limit global es 60 peticiones por minuto. Se necesitan limiters con
   nombre para disponibilidad y escrituras de agenda, separados por usuario.
6. El `ApiService` de Ionic solo agrega `Accept` y `Authorization`; todavia no
   permite enviar `Idempotency-Key`. No bloquea el backend del Paso 6, pero debe
   ampliarse al integrar las pantallas en el Paso 8.

### Riesgos de exposicion de datos

- No usar serializacion directa de modelos. `Appointment` contiene
  `internal_notes` y datos de cobro que no siempre corresponden al customer.
- El detalle debe construirse con Resources de lista, detalle, propuesta y evento.
- Los eventos deben usar una lista blanca; no se debe devolver metadata interna,
  datos de locks ni claves de idempotencia.
- Toda consulta por cita/propuesta debe filtrar tenant, customer, user, mascota
  asignada y visibilidad. Un ID ajeno debe responder 404 para no revelar existencia.
- Servicios deben filtrar tenant, `type = service`, activos, no eliminados e
  `is_bookable = true`.

### Contrato recomendado para el Paso 6

- `GET /api/v1/portal/appointments/bootstrap`
- `GET /api/v1/portal/appointments/services`
- `GET /api/v1/portal/appointments/availability`
- `GET /api/v1/portal/appointments`
- `GET /api/v1/portal/appointments/{appointment}`
- `POST /api/v1/portal/appointments`
- `POST /api/v1/portal/appointments/{appointment}/proposals/{proposal}/accept`
- `POST /api/v1/portal/appointments/{appointment}/proposals/{proposal}/reject`
- `POST /api/v1/portal/appointments/{appointment}/cancel`

Las escrituras deben recibir `Idempotency-Key`. El backend obtiene tenant,
customer y actor desde el contexto autenticado; el body solo envia mascota,
servicio, fecha/hora y los textos permitidos.

### Orden ajustado de ejecucion

1. Crear un `CustomerPortalContext`/resolver compartido y corregir las validaciones
   de vigencia y ambiguedad.
2. Crear Resources y el renderer JSON de `AppointmentDomainException`.
3. Registrar rate limiters y rutas.
4. Implementar bootstrap, servicios y disponibilidad.
5. Implementar listado y detalle con aislamiento estricto.
6. Implementar solicitud, contrapropuestas y cancelacion idempotentes.
7. Agregar pruebas Feature de contrato, 401/403/404/409/422/429, cross-tenant,
   cross-customer, visibilidad, claves repetidas y campos ocultos.
8. Documentar payloads definitivos y actualizar este checkpoint.

### Impacto estimado

- No se anticipan migraciones para el Paso 6.
- Se esperan principalmente rutas, middleware/resolver, controladores, Requests,
  Resources, rate limiters, manejo de excepciones y pruebas.
- La auditoria no encontro necesidad de cambiar el motor de disponibilidad ni el
  servicio de dominio antes de iniciar, salvo aplicar visibilidad en la capa API.

## Paso 6: API para customer

Estado: completado en desarrollo local el 2026-06-20.

### Bloque 1: contexto y seguridad customer

- Se creo `CustomerAppointmentContext` y
  `CustomerAppointmentContextResolver`.
- El contexto obtiene usuario, tenant y customer desde la sesion y el acceso de
  portal; ningun endpoint acepta `tenant_id` o `customer_id` del cliente.
- Se exige un unico acceso customer activo por usuario dentro del tenant.
- Se validan inicio, fin, revocacion y fin de trial del acceso, customer activo y
  `CustomerUserLink` no revocado.
- Solo se consideran mascotas activas, asignadas al usuario y con
  `show_appointments = true`.
- Los IDs ajenos o no visibles responden 404 para no revelar su existencia.

### Bloque 2: contrato HTTP

Se agregaron nueve endpoints bajo `/api/v1/portal/appointments`:

- bootstrap de configuracion publica, veterinario, mascotas y servicios;
- catalogo de servicios reservables;
- disponibilidad por mascota, servicio y rango de hasta 31 dias;
- listado paginado y detalle de citas propias;
- solicitud de cita;
- aceptar y rechazar contrapropuesta;
- cancelar cita.

Las rutas reutilizan `auth:sanctum`, `access.mobile`, `api.tenant` y
`customer.portal`. Todos los nombres de ruta son unicos.

### Bloque 3: validacion, Resources y errores

- Se crearon cinco Form Requests para disponibilidad, filtros, solicitud,
  respuesta a contrapropuesta y cancelacion.
- Las escrituras requieren `Idempotency-Key` y lo entregan a
  `AppointmentService`.
- Se crearon Resources separados para cita, propuesta, evento y servicio.
- `internal_notes`, metadata de eventos, locks y claves de idempotencia no se
  serializan al customer.
- Los filtros de fecha del listado convierten los limites del dia local a UTC.
- `AppointmentDomainException` responde JSON con `message`, `code` y `errors`,
  conserva el status HTTP y ya no se reporta como error inesperado.
- Se agrego la relacion `Appointment::pendingProposal()` para respuestas
  eficientes.

### Bloque 4: limites de frecuencia

- Disponibilidad: 30 solicitudes por minuto y usuario.
- Escrituras: 10 solicitudes por minuto y usuario.
- Ambos limites se suman al limite API global existente.

### Bloque 5: documentacion para Ionic

El contrato consumible se guardo en
`docs/appointment-scheduling-customer-api.md`. Incluye rutas, payloads,
encabezado idempotente, Resources y codigos 401/403/404/409/422/429.

### Archivos principales del Paso 6

- `app/Data/CustomerAppointmentContext.php`
- `app/Services/CustomerAppointmentContextResolver.php`
- `app/Http/Controllers/Api/V1/CustomerAppointmentController.php`
- `app/Http/Requests/Api/Customer/*`
- `app/Http/Resources/Api/Customer/*`
- `app/Exceptions/Handler.php`
- `app/Providers/RouteServiceProvider.php`
- `routes/api.php`
- `tests/Feature/CustomerAppointmentApiTest.php`
- `docs/appointment-scheduling-customer-api.md`

No se agregaron migraciones en este paso.

### Verificacion

- Pruebas API customer: 10 pruebas, 66 aserciones.
- Pruebas combinadas de agenda: 52 pruebas, 212 aserciones.
- Suite completa: 107 pruebas, 403 aserciones.
- Cubiertos: autenticacion, acceso ambiguo/no vigente, visibilidad por mascota,
  cross-customer, IDs cross-tenant, campos internos, disponibilidad, solicitud,
  idempotencia, contrapropuestas, cancelacion y rate limit.
- Pint y sintaxis PHP finalizaron correctamente.
- La unica advertencia es el schema XML obsoleto de PHPUnit, ya existente.

### Fuera de este paso

- No se crearon pantallas Ionic ni se cambio `ApiService`; el envio del encabezado
  idempotente se integrara con la UI en el Paso 8.
- No se creo el panel ni la API tenant.
- No se agregaron notificaciones, correo o push.

## Plan general del Paso 7

Alcance previsto del Paso 7, API y panel Laravel tenant:

1. Auditar menu, roles `client-admin` y veterinario, patrones del panel y API
   movil tenant.
2. Crear consultas de agenda diaria/semanal con filtros por fecha y estado.
3. Mostrar solicitudes pendientes primero y detalle con historial.
4. Exponer confirmar, rechazar, contrapropuesta, cancelar, completar y no show
   usando exclusivamente `AppointmentService`.
5. Permitir citas manuales para customers existentes.
6. Crear vista Laravel responsive y endpoints API tenant con el mismo contrato de
   dominio.
7. Ocultar notas internas a roles no autorizados y mantener asistentes/cajeros
   bloqueados en el MVP.
8. Agregar pruebas web/API de permisos, aislamiento y operaciones; actualizar este
   checkpoint al cerrar el paso.

## Paso 7, bloque 1: servicios tenant

Estado: completado en desarrollo local el 2026-06-20. El Paso 7 general continua
en progreso.

### Auditoria de roles

- No existe un rol `vet` en `RoleSeeder`.
- Un operador veterinario se identifica por `VeterinarianProfile` activo dentro
  del mismo tenant.
- `client-admin` y el rol legado `admin` pueden operar la agenda.
- `asistente`, `cajero`, customers, usuarios inactivos y usuarios de otro tenant
  quedan bloqueados en el MVP.

### Servicio de autorizacion

Se creo `TenantAppointmentAccessService` como regla compartida para consultas y
operaciones. `AppointmentService` ahora delega en este servicio las autorizaciones
de confirmar, rechazar, proponer, cancelar, completar y no show, evitando dos
definiciones distintas de “operador de agenda”.

### Servicio de consultas

Se creo `TenantAppointmentQueryService` con:

- consulta tenant-scoped por rango local de hasta 62 dias;
- conversion de limites locales a UTC;
- filtros por estados, customer y mascota;
- solicitudes `pending_tenant` primero y despues orden cronologico;
- carga eficiente de customer, mascota, doctor, servicio y propuesta pendiente;
- detalle tenant-scoped con propuestas e historial de eventos;
- opciones para cita manual limitadas a customers, mascotas y servicios activos.

### Creacion manual

Se agrego `AppointmentService::createManual()`:

- disponible para admin y veterinario activo;
- no exige usuario app, link, portal ni asignacion customer-user;
- exige customer, mascota y servicio activos del mismo tenant;
- usa el veterinario configurado en la agenda;
- valida inicio futuro, duracion, horario, bloqueos y solapamientos;
- obtiene lock diario antes de ocupar el intervalo;
- crea la cita directamente como `confirmed`;
- conserva motivo customer y notas internas por separado;
- registra `appointment.created_manually` como evento inmutable;
- soporta idempotencia con la operacion `create-manual`.

### Archivos del bloque

- Nuevo: `app/Services/TenantAppointmentAccessService.php`.
- Nuevo: `app/Services/TenantAppointmentQueryService.php`.
- Modificado: `app/Services/AppointmentService.php`.
- Modificado: `app/Enums/AppointmentEventType.php`.
- Nuevo: `tests/Feature/TenantAppointmentServicesTest.php`.

No se agregaron migraciones, Jobs, colas ni notificaciones.

### Verificacion

- Pruebas nuevas de servicios tenant: 7 pruebas.
- Servicios tenant + dominio + API customer: 33 pruebas, 145 aserciones.
- Suite completa: 114 pruebas, 429 aserciones.
- Cubiertos: admin, veterinario, usuario inactivo/ajeno, asistente, aislamiento,
  fechas locales, filtros, orden de pendientes, detalle, opciones, cita manual,
  idempotencia, conflictos y participantes ajenos.
- Pint finalizo correctamente.
- Permanece solo la advertencia preexistente del schema XML de PHPUnit.

## Plan previo del bloque 2 (cerrado)

Implementar permisos y capa HTTP tenant:

1. Crear Gates `view-appointments` y `operate-appointments` apoyados en
   `TenantAppointmentAccessService`.
2. Crear Form Requests para filtros, cita manual y transiciones.
3. Crear Resources tenant que incluyan notas internas solo para operadores
   autorizados.
4. Crear controlador API tenant y controlador web, ambos delegando en los mismos
   servicios.
5. Registrar rutas web/API con rate limits e idempotencia.
6. Agregar pruebas HTTP de 401/403/404/409/422 y aislamiento antes de iniciar las
   vistas Blade.

## Paso 7, bloque 2: permisos y capa HTTP tenant

Estado: completado en desarrollo local el 2026-06-20. El Paso 7 general continua
en progreso.

### Gates y acceso

- Se agregaron `view-appointments` y `operate-appointments`.
- Ambos Gates delegan en `TenantAppointmentAccessService`.
- Tienen acceso `client-admin`, el legado `admin` y veterinarios activos del
  tenant.
- Asistentes, cajeros, customers, usuarios inactivos y usuarios de otro tenant
  reciben 403.

### Form Requests

Se agregaron Requests para:

- rango, estados, customer, mascota y paginacion;
- disponibilidad tenant;
- cita manual;
- confirmar y guardar nota interna;
- rechazar con motivo visible;
- crear contrapropuesta;
- cancelar con motivo;
- completar y marcar no show.

Las escrituras exigen `Idempotency-Key` en API o `idempotency_key` en formulario
web. Los rangos y duraciones tienen limites explicitos.

### Resources tenant

Se crearon Resources tenant para cita, contrapropuesta y evento. Incluyen:

- customer, mascota, servicio y veterinario;
- timestamps UTC y locales;
- motivo customer y notas internas;
- rechazo, cancelacion y revision de cargo;
- acciones validas para el estado actual;
- propuestas, autor y metadata de auditoria.

No exponen locks ni claves de idempotencia. Estos Resources solo se alcanzan tras
los Gates operativos.

### API tenant

Se registraron 11 rutas bajo `/api/v1/appointments`:

- bootstrap operativo;
- disponibilidad;
- agenda paginada y detalle;
- cita manual;
- confirmar, rechazar y contrapropuesta;
- cancelar, completar y no show.

La API usa los middleware existentes de Sanctum, sesion movil y tenant, mas los
Gates de agenda. Las lecturas tienen limite de 60 por minuto y las escrituras de
20 por minuto, ambos por usuario.

### Acciones web

Se creo `Client\AppointmentController` con siete rutas POST bajo
`/client/agenda`. Las acciones usan los mismos Requests y `AppointmentService`
que la API; responden con redirect, mensaje de exito o errores de dominio en la
sesion. La vista GET se reserva para el siguiente bloque.

### Disponibilidad para tenant

`AppointmentAvailabilityService` ahora acepta `applyCustomerRules`:

- customer conserva habilitacion, anticipacion minima y ventana de reserva;
- tenant conserva doctor, horarios, bloqueos, ocupaciones y tiempo futuro, pero
  puede consultar slots aunque la reserva customer este desactivada.

Esto permite citas manuales sin cambiar la politica publica de la app customer.

### Archivos principales del bloque

- `app/Providers/AuthServiceProvider.php`
- `app/Providers/RouteServiceProvider.php`
- `app/Http/Requests/TenantAppointment*.php` y Requests de transiciones
- `app/Http/Resources/Api/Tenant/*`
- `app/Http/Controllers/Api/V1/TenantAppointmentController.php`
- `app/Http/Controllers/Client/AppointmentController.php`
- `app/Services/AppointmentAvailabilityService.php`
- `app/Exceptions/Handler.php`
- `routes/api.php` y `routes/web.php`
- `tests/Feature/TenantAppointmentHttpTest.php`
- `docs/appointment-scheduling-tenant-http.md`

No se agregaron migraciones, Jobs, colas ni notificaciones.

### Verificacion

- Pruebas HTTP tenant: 8 pruebas, 68 aserciones.
- Regresion enfocada de agenda: 64 pruebas, 280 aserciones antes del ajuste final.
- Suite completa final: 122 pruebas, 497 aserciones.
- Cubiertos: 401, 403, 404, 409, 422, 429, admin, veterinario, customer,
  asistente, aislamiento, notas internas, metadata, disponibilidad tenant, cita
  manual, idempotencia y todas las transiciones tenant.
- Pint y listado de rutas finalizaron correctamente.
- Permanece solo la advertencia preexistente del schema XML de PHPUnit.

## Plan previo del bloque 3 (cerrado)

Implementar el panel Laravel tenant:

1. Crear ruta GET y controlador de presentacion para agenda diaria/semanal.
2. Crear vista responsive con pendientes primero, filtros y navegacion de fechas.
3. Crear detalle con historial, motivo y nota interna.
4. Conectar formularios/modales a las siete acciones web ya disponibles.
5. Crear formulario de cita manual usando bootstrap/disponibilidad tenant.
6. Agregar Agenda al menu tenant siempre y mostrar configuracion pendiente cuando
   readiness sea incompleto.
7. Agregar pruebas Feature de renderizado, filtros, formularios y visibilidad del
   menu; despues cerrar el Paso 7 completo.

## Paso 7, bloque 3: panel Laravel tenant

Estado: completado en desarrollo local el 2026-06-20. Con este bloque queda
cerrado el Paso 7 completo.

### Controlador y rutas de presentacion

`Client\AppointmentController` ahora incluye:

- agenda GET diaria/semanal;
- detalle GET tenant-scoped;
- disponibilidad web JSON para el formulario manual;
- conversion correcta de fechas locales del formulario al timezone tenant.

Las rutas GET se agregaron bajo `/client/agenda` y conservan los siete endpoints
POST creados en el bloque anterior.

### Vista de agenda

Se creo `resources/views/client/appointments/index.blade.php` con:

- cambio entre dia y semana;
- navegacion anterior, hoy y siguiente;
- filtros por fecha, estado y customer;
- KPIs de pendientes, confirmadas, respuesta customer y total;
- solicitudes pendientes mostradas primero por el servicio de consultas;
- columnas responsive por dia y tarjetas enlazadas al detalle;
- estado vacio por fecha;
- aviso y acceso a configuracion cuando readiness es incompleto.

### Cita manual desde web

La agenda incluye modal para:

- seleccionar customer y solo sus mascotas activas;
- seleccionar servicio reservable y fecha;
- consultar slots tenant reales mediante `/client/agenda/disponibilidad`;
- enviar el timestamp ISO seleccionado;
- capturar motivo visible y nota interna por separado;
- generar una clave idempotente por formulario.

La cita se crea confirmada usando `AppointmentService::createManual()`.

### Vista de detalle

Se creo `resources/views/client/appointments/show.blade.php` con:

- estado, fecha local, doctor, customer, mascota y servicio;
- motivo customer y nota interna;
- rechazo, cancelacion tardia y revision de cargo;
- formularios para confirmar, rechazar, proponer, cancelar, completar y no show;
- listado de contrapropuestas;
- linea de tiempo con actor y metadata de auditoria.

Cada formulario contiene clave idempotente y usa las rutas/Requests ya probados.

### Menu y permisos

- Agenda se inserto en el menu principal para admin y veterinario activo.
- El enlace permanece visible aunque la configuracion este incompleta.
- Asistentes y cajeros no ven el enlace y reciben 403 al intentar la ruta.
- El menu queda marcado como activo tanto en agenda como en detalle.
- El navbar usa el icono de calendario para Agenda, validado despues de la prueba
  manual del flujo tenant.

### Archivos principales del bloque

- `app/Http/Controllers/Client/AppointmentController.php`
- `app/Http/Requests/TenantAppointmentIndexRequest.php`
- `routes/web.php`
- `resources/views/layouts/client.blade.php`
- `resources/views/client/appointments/index.blade.php`
- `resources/views/client/appointments/show.blade.php`
- `tests/Feature/TenantAppointmentHttpTest.php`

No se agregaron migraciones, Jobs, colas ni notificaciones.

### Verificacion

- HTTP y renderizado tenant: 12 pruebas, 94 aserciones.
- Servicios + HTTP tenant: 19 pruebas, 120 aserciones.
- Suite completa final: 126 pruebas, 523 aserciones.
- Cubiertos: dia/semana, filtros, menu, readiness incompleto, slots web, modal
  manual, detalle, historial, notas internas, formularios y permisos.
- Las vistas Blade renderizan dentro de las pruebas Feature y el cache de vistas
  se limpio antes de la suite final.
- Pint finalizo correctamente.
- La inspeccion por captura del navegador integrado no pudo ejecutarse: el proceso
  fue bloqueado por permisos del sandbox de Windows en dos intentos.
- Permanece la advertencia preexistente del schema XML de PHPUnit.

## Plan previo del Paso 8 (cerrado)

Alcance previsto para Paso 8, Ionic customer:

1. Auditar rutas y componentes actuales del portal customer Ionic.
2. Extender `ApiService` para encabezados personalizados e `Idempotency-Key`.
3. Crear modelos TypeScript del contrato de agenda customer.
4. Crear proximas citas, historial, nueva solicitud y detalle.
5. Integrar disponibilidad, contrapropuestas y cancelacion.
6. Mostrar estados, timeline y errores de dominio sin exponer campos internos.
7. Agregar pruebas/build Ionic y actualizar este checkpoint.

## Paso 8: Ionic customer

Estado: completado en desarrollo local el 2026-06-20.

### Auditoria Ionic

- El portal customer ya tenia rutas, sesion, bootstrap y navegacion propios.
- El tenant se obtiene de la sesion; no se agrego selector de veterinaria o doctor.
- `PortalPatient.visibility.appointments` ya existia y ahora controla la entrada
  Citas del menu inferior.
- La API customer del Paso 6 se reutiliza sin cambios backend.

### Cliente API y modelos

- `ApiService.post()` acepta encabezados adicionales sin afectar llamadas
  existentes.
- Se agregaron modelos TypeScript estrictos para configuracion, servicio, slot,
  cita, propuesta, evento y respuestas paginadas.
- Se creo `CustomerAppointmentsService` para bootstrap, listado, detalle,
  disponibilidad, solicitud, aceptar/rechazar contrapropuesta y cancelar.
- Todas las escrituras envian `Idempotency-Key`.
- Las pantallas conservan la misma clave tras error/reintento y solo la regeneran
  cuando cambia el payload, evitando solicitudes fantasma por respuesta perdida.

### Navegacion

Se agregaron rutas lazy:

- `/portal/citas`
- `/portal/citas/nueva`
- `/portal/citas/:id`

El menu inferior muestra Citas con icono de calendario solo cuando al menos una
mascota tiene `show_appointments`. Se adapta de cuatro a cinco columnas.

### Proximas citas e historial

La pantalla de citas incluye:

- pull to refresh;
- segmentos Proximas e Historial;
- estado, mascota, servicio, doctor, fecha y hora;
- estados vacios y errores de API;
- acceso a nueva solicitud y detalle.

### Nueva solicitud

El customer puede:

- seleccionar solo mascotas autorizadas por la API;
- seleccionar servicio reservable;
- elegir fecha dentro de la ventana configurada;
- consultar y elegir slots reales del tenant;
- escribir motivo de consulta;
- enviar una solicitud que se muestra como `pending_tenant`.

Si el backend responde `APPOINTMENT_SLOT_UNAVAILABLE`, la pantalla conserva el
mensaje y refresca disponibilidad. Si la agenda no esta habilitada, muestra un
estado informativo y no permite solicitar.

### Detalle y transiciones

La pantalla de detalle muestra:

- estado y horario en timezone del tenant;
- mascota, servicio, veterinario y motivo;
- contrapropuesta destacada con aceptar/rechazar;
- cancelacion con motivo;
- rechazo, cancelacion tardia y aviso de revision de cargo;
- timeline publico sin notas internas ni metadata tenant.

Despues de solicitar, la UI informa que la veterinaria debe confirmar y nunca
presenta la cita como confirmada anticipadamente.

### Deep links

Las pantallas Inicio y Notificaciones ahora reconocen `appointment_id` dentro del
payload de notificacion y navegan a `/portal/citas/:id`. Esto queda listo para los
eventos que se implementaran en el Paso 10.

### Archivos principales

- `gorozpeApp/src/app/core/models/api.models.ts`
- `gorozpeApp/src/app/core/services/api.service.ts`
- `gorozpeApp/src/app/core/services/customer-appointments.service.ts`
- `gorozpeApp/src/app/core/services/customer-appointments.service.spec.ts`
- `gorozpeApp/src/app/app.routes.ts`
- `gorozpeApp/src/app/features/customer-portal/portal-appointments/*`
- `gorozpeApp/src/app/features/customer-portal/portal-appointment-create/*`
- `gorozpeApp/src/app/features/customer-portal/portal-appointment-detail/*`
- `gorozpeApp/src/app/features/customer-portal/shared/portal-appointments.scss`
- `gorozpeApp/src/app/features/customer-portal/shared/portal-bottom-nav.component.*`
- Inicio y notificaciones del portal para deep links.

No se agregaron migraciones, Jobs, colas ni cambios backend.

### Verificacion

- Build Angular development: correcto, con las tres pantallas como chunks lazy.
- Templates estrictos y TypeScript: correctos.
- ESLint dirigido de TypeScript y templates modificados: correcto.
- `git diff --check`: correcto.
- Se agregaron dos pruebas unitarias del servicio para URL y
  `Idempotency-Key`; Karma compilo los bundles pero no pudo ejecutar ChromeHeadless
  porque el proceso GPU fue bloqueado por permisos/sandbox de Windows.
- El lint global conserva un unico error preexistente y ajeno a Agenda en
  `video-upload.service.ts` (`prefer-inject`).

## Plan previo del Paso 9 (cerrado)

El alcance previsto para Ionic tenant se completo el 2026-06-21.

## Paso 9: Ionic tenant

Estado: completado en desarrollo local el 2026-06-21.

### Auditoria e integracion

- La app tenant ya usaba tabs lazy y `staffGuard`; Agenda se integro dentro de
  esa navegacion sin crear una segunda sesion ni seleccionar tenant.
- Se reutilizaron exclusivamente los endpoints `/api/v1/appointments` creados
  en el Paso 7; no hubo cambios backend.
- Laravel conserva la autorizacion final: `client-admin`, `admin` o usuario con
  perfil veterinario activo del mismo tenant.
- El recurso backend `actions` controla que operaciones aparecen en el detalle,
  evitando replicar transiciones de dominio en Ionic.

### Cliente API y modelos

- Se agregaron modelos TypeScript tenant para bootstrap, cita, customer,
  propuesta, evento, acciones, disponibilidad y respuestas paginadas.
- `TenantAppointmentsService` cubre bootstrap, rango, detalle, disponibilidad,
  alta manual, confirmar, rechazar, contrapropuesta, cancelar, completar y no
  show.
- Todas las escrituras envian `Idempotency-Key`.
- Cada accion conserva su clave durante un error/reintento y la renueva solo
  despues de una respuesta exitosa.
- Se agregaron pruebas unitarias de alta manual y no show para verificar URL y
  encabezado de idempotencia.

### Navegacion y agenda

Se agregaron rutas lazy bajo los tabs tenant:

- `/tabs/agenda`
- `/tabs/agenda/nueva`
- `/tabs/agenda/:id`

La barra inferior incluye Agenda con icono de calendario. La pantalla principal
incluye:

- resumen de citas de hoy y solicitudes pendientes dentro del horizonte movil;
- pull to refresh;
- selector Dia/Semana y navegacion entre periodos;
- customer, paciente, servicio, fecha, hora y estado;
- estados vacios, carga y errores de API;
- acceso directo a cita manual, detalle y notificaciones.

### Cita manual

El tenant puede seleccionar customer, uno de sus pacientes, servicio, fecha y
slot calculado por el backend. Tambien puede capturar motivo del customer y notas
internas. La cita manual se registra confirmada, conforme al contrato existente.

Si falta configuracion se muestra el aviso de readiness. Si el slot deja de
estar disponible, se presenta el error de dominio y se refrescan horarios.

### Detalle y operaciones

El detalle muestra horario en timezone del tenant, customer y contacto,
paciente, doctor, motivo, notas internas, razones de rechazo/cancelacion,
contrapropuesta pendiente e historial con actor.

Segun `actions`, permite:

- confirmar con duracion y notas internas;
- rechazar con motivo;
- proponer nueva fecha/hora, duracion y mensaje;
- cancelar con motivo;
- completar una cita pasada;
- marcar no asistencia.

Cada respuesta actualiza el detalle con el estado retornado por Laravel, por lo
que el cambio queda visible tambien en el panel web.

### Notificaciones

La lista tenant reconoce `appointment_id`, usa icono de calendario y abre
`/tabs/agenda/:id`. Se conserva el marcado de lectura mediante el servicio de
notificaciones compartido con Laravel; los tipos y envios de agenda se completan
en el Paso 10.

### Archivos principales

- `gorozpeApp/src/app/core/models/api.models.ts`
- `gorozpeApp/src/app/core/services/tenant-appointments.service.ts`
- `gorozpeApp/src/app/core/services/tenant-appointments.service.spec.ts`
- `gorozpeApp/src/app/tabs/tabs.routes.ts`
- `gorozpeApp/src/app/tabs/tabs.page.ts`
- `gorozpeApp/src/app/tabs/tabs.page.html`
- `gorozpeApp/src/app/features/appointments/*`
- `gorozpeApp/src/app/features/notifications/notifications.page.ts`

No se agregaron migraciones, Jobs, colas ni cambios backend.

### Verificacion

- Build Angular development: correcto; las tres pantallas son chunks lazy.
- Templates estrictos y TypeScript: correctos.
- ESLint dirigido de TypeScript y templates modificados: correcto.
- `git diff --check`: correcto.
- Karma compilo los bundles y descubrio las nuevas pruebas, pero no ejecuto
  ChromeHeadless porque el proceso GPU fue bloqueado por permisos/sandbox de
  Windows, la misma limitacion registrada en el Paso 8.
- No se repitio la suite Laravel porque este paso no modifico backend; la ultima
  ejecucion completa se mantiene en 126 tests y 523 assertions.

## Paso 10: notificaciones, correo, push y sincronizacion

Estado: en progreso. Paso 10.1 completado el 2026-06-21.

El canal persistente sigue siendo la fuente de verdad. Email y push son entregas
derivadas: su fallo nunca elimina la notificacion ni cambia el estado de la cita.
El MVP usara FCM y reconciliacion por API al abrir/reanudar la app. No requiere
WebSockets o SSE; se podran agregar despues sin cambiar el modelo de eventos.

### Plan de ejecucion: 7 pasos

#### Paso 10.1: auditoria tecnica

Estado: completado el 2026-06-21.

Objetivo:

- inventariar infraestructura existente;
- localizar el punto transaccional correcto de Agenda;
- identificar dependencias, credenciales y bloqueos nativos;
- cerrar la arquitectura antes de instalar paquetes.

Resultado detallado:

1. Notificaciones persistentes existentes:

- `tenant_notifications` alimenta Laravel web e Ionic tenant.
- `portal_notifications` alimenta Ionic customer.
- Ambas tablas ya soportan `type`, titulo, cuerpo, URL, JSON, lectura y soft
  delete; la tabla tenant permite `user_id` nulo.
- La API tenant devuelve registros por `tenant_id`, no filtra por `user_id`.
  Agenda debe crear un solo registro tenant compartido por evento para evitar
  duplicados o exposicion cruzada entre operadores.
- Customer requiere un registro por `CustomerPortalAccess` activo y autorizado
  para el paciente; `PortalNotificationService` ya resuelve accesos y visibilidad,
  pero aun no conoce eventos de Agenda.
- Laravel web e Ionic tenant actualizan el mismo `read_at`, cumpliendo la
  reconciliacion entre superficies.

2. Agenda y transacciones:

- Cada transicion ya crea un `AppointmentEvent` dentro de la transaccion
  idempotente de `AppointmentIdempotencyService`.
- Existen tipos para solicitud, alta manual, confirmacion, rechazo,
  contrapropuesta, respuesta, expiracion, cancelacion, cierre, no show y cargos.
- No existe emision de notificacion, correo o push desde Agenda.
- El procesamiento debe comenzar por `appointment_event_id` despues del commit;
  nunca debe enviar canales externos dentro de la transaccion de la cita.
- La cancelacion comparte `appointment.cancelled`; el procesador determinara
  customer/tenant mediante el actor y el estado/contexto del evento.

3. Queue y correo:

- Laravel tiene `failed_jobs`, pero no se encontro migracion para `jobs` ni
  `job_batches`.
- `.env.example` usa `QUEUE_CONNECTION=sync` y todas las conexiones tienen
  `after_commit=false`.
- Solo `ExpireAppointmentProposals` implementa `ShouldQueue`.
- Los Mailables actuales son de activacion/invitacion y se envian de forma
  sincrona; no existen Mailables de Agenda.
- SMTP esta configurado de forma generica. Staging/produccion necesitara
  credenciales reales, worker supervisado y scheduler activo.

4. Firebase/backend:

- No hay SDK Firebase, cliente FCM, configuracion ni variables FCM en Laravel.
- Se adoptara Firebase Cloud Messaging HTTP v1. Antes de instalar se verificara
  una version de `kreait/laravel-firebase` compatible con PHP 8.1 y Laravel 10.
- Las credenciales de service account se guardaran fuera de Git. Solo ruta,
  project ID y flags no secretos iran a `.env.example`/config.

5. Ionic y Android:

- Capacitor 8 y Android 8.4 ya estan instalados.
- Falta `@capacitor/push-notifications` y no hay codigo de registro/listeners.
- Gradle ya incluye `com.google.gms.google-services` y aplica el plugin de forma
  condicional.
- Falta `android/app/google-services.json`; debe obtenerse de Firebase y quedar
  fuera de Git.
- El package Android esperado es `com.hectorbmx.vetsys`.

6. iOS:

- El proyecto iOS existe con deployment target 15.0 y Team ID configurado.
- Faltan `GoogleService-Info.plist`, capability Push Notifications, Background
  Mode `remote-notification` y archivo `.entitlements` con `aps-environment`.
- El Bundle ID Release es `com.hectorbmx.vetsys`, pero Debug contiene
  `-com.hectorbmx.vetsys`; debe corregirse antes de registrar/probar APNs.
- Se requiere cuenta Apple Developer, app registrada, clave APNs `.p8`, Key ID
  y Team ID cargados en Firebase.
- Las pruebas reales requieren iPhone fisico; simulador no sustituye la prueba
  completa APNs/FCM.

7. Sincronizacion actual:

- Ionic tenant consulta notificaciones cada 30 segundos.
- Customer refresca su bootstrap/notificaciones y ambas sesiones ya refrescan al
  volver a primer plano o recuperar red.
- El push agregara aviso inmediato y deep link. Al tocarlo o reanudar, la app
  volvera a consultar la API; el payload nunca sera la fuente de detalle.
- Los deep links de Agenda por `appointment_id` ya existen para ambos roles.

Archivos auditados principalmente:

- `app/Services/AppointmentService.php`
- `app/Services/AppointmentIdempotencyService.php`
- `app/Services/PortalNotificationService.php`
- `app/Models/TenantNotification.php`
- `app/Models/PortalNotification.php`
- `app/Http/Controllers/Api/V1/NotificationController.php`
- `config/queue.php`, `config/mail.php`, `.env.example`, `composer.json`
- `gorozpeApp/package.json`, `capacitor.config.ts`
- `gorozpeApp/android/app/build.gradle`
- `gorozpeApp/ios/App/App/Info.plist`
- `gorozpeApp/ios/App/App/AppDelegate.swift`
- `gorozpeApp/ios/App/App.xcodeproj/project.pbxproj`

No se instalaron dependencias ni se modifico codigo funcional en este paso.

#### Paso 10.2: dispositivos, entregas y queue

Estado: completado en desarrollo local el 2026-06-21.

Objetivo: crear la fundacion durable antes de emitir notificaciones.

Cambios previstos:

- Migracion `push_devices` para todos los usuarios autenticados:
  `tenant_id`, `user_id`, `platform` (`android|ios`), token cifrado,
  `token_hash`, `device_uuid`, nombre, version de app, ultimo uso, `revoked_at`
  y timestamps.
- Restricciones: `token_hash` SHA-256 unico global y dispositivo unico por
  `user_id + platform + device_uuid`; un refresh de FCM actualiza el registro.
- Migracion `appointment_notification_deliveries` para deduplicar por
  `appointment_event_id + channel + recipient_key`, con estado, intentos,
  `delivered_at` y ultimo error sanitizado.
- Migracion de `jobs`/`job_batches` si se confirma queue database.
- Modelo `PushDevice`, relaciones en `User`/`Tenant` y politica tenant/propietario.
- Endpoints autenticados comunes para registrar/actualizar y revocar el
  dispositivo actual. Nunca se aceptara `user_id` o `tenant_id` del cliente.
- Configurar `QUEUE_CONNECTION=database` para entornos desplegados y
  `after_commit=true`, o usar explicitamente `->afterCommit()` en cada Job.
- Pruebas de aislamiento tenant, ownership, upsert, rotacion y revocacion.

Criterio de salida:

- tenant y customer pueden registrar varios dispositivos sin mezclar cuentas;
- logout/revocacion invalida solo el dispositivo indicado;
- una misma entrega de evento/canal/destinatario no puede duplicarse.

Implementacion realizada:

- `push_devices` guarda dispositivos tenant/customer sobre la misma tabla de
  usuarios. El token usa cast cifrado y `token_hash` SHA-256 unico para buscar y
  deduplicar sin devolver secretos.
- La identidad de instalacion es unica por `user_id + platform + device_uuid`.
- El registro transaccional soporta alta, reactivacion, rotacion de token y
  reasignacion del token al usuario autenticado que demuestra poseerlo.
- La API ignora cualquier `user_id`/`tenant_id` enviado; toma ambos de Sanctum.
- Revocar exige coincidencia de tenant y propietario y conserva el registro con
  `revoked_at` para auditoria.
- `appointment_notification_deliveries` registra canal, destinatario, estado,
  intentos, fechas y error. La restriccion unica usa
  `appointment_event_id + channel + recipient_hash`.
- Se agregaron enums para plataforma, canal y estado de entrega, modelos,
  relaciones y factories consistentes con tenant.
- Se crearon `jobs` y `job_batches`; `failed_jobs` ya existia.
- Las conexiones database, beanstalkd, SQS y Redis usan `after_commit=true`.
- `.env.example` propone `QUEUE_CONNECTION=database`; `phpunit.xml` conserva
  `sync` para pruebas deterministas.

API creada:

- `POST /api/v1/push-devices`: alta/upsert del dispositivo actual.
- `DELETE /api/v1/push-devices/{pushDevice}`: revocacion del dispositivo propio.

Payload de alta:

- `platform`: `android` o `ios`;
- `token`: token FCM, maximo 4096;
- `device_uuid`: identificador estable de la instalacion;
- `device_name` y `app_version`: opcionales.

La respuesta incluye solo ID, plataforma, identificador/nombre, version y fechas;
nunca token, hash, `user_id` o `tenant_id`.

Archivos principales:

- `database/migrations/2026_06_21_000001_create_push_devices_table.php`
- `database/migrations/2026_06_21_000002_create_appointment_notification_deliveries_table.php`
- `database/migrations/2026_06_21_000003_create_queue_tables.php`
- `app/Models/PushDevice.php`
- `app/Models/AppointmentNotificationDelivery.php`
- `app/Enums/PushPlatform.php`
- `app/Enums/NotificationDeliveryChannel.php`
- `app/Enums/NotificationDeliveryStatus.php`
- `app/Services/PushDeviceRegistrar.php`
- `app/Http/Requests/StorePushDeviceRequest.php`
- `app/Http/Resources/Api/PushDeviceResource.php`
- `app/Http/Controllers/Api/V1/PushDeviceController.php`
- `database/factories/PushDeviceFactory.php`
- `database/factories/AppointmentNotificationDeliveryFactory.php`
- `tests/Feature/PushNotificationFoundationTest.php`
- relaciones en `User`, `Tenant`, `AppointmentEvent`, rutas y config queue.

Migraciones y verificacion:

- Las tres migraciones se aplicaron correctamente a la base local.
- Rutas POST/DELETE verificadas con `artisan route:list`.
- Sintaxis PHP correcta y Pint aplicado.
- Pruebas nuevas: 6 tests, 38 assertions.
- Suite completa final: 132 tests, 561 assertions.
- Se corrigieron dos assertions antiguas de `AppointmentServiceTest` para contar
  locks/eventos dentro del tenant de prueba, no datos globales de desarrollo.

Decision de plataforma:

- En Windows se continuara con backend, Android y codigo Ionic compartido.
- Configuracion nativa, firma, APNs y pruebas iOS quedan diferidas hasta compilar
  en macOS para App Store. El esquema acepta `ios` para no requerir migraciones
  futuras, pero no se agregaran archivos/capabilities iOS en esta etapa.

#### Paso 10.3: eventos y notificaciones persistentes de Agenda

Estado: completado en desarrollo local el 2026-06-21.

Objetivo: convertir cada `AppointmentEvent` confirmado en notificaciones in-app.

Arquitectura:

- Despachar un procesador por `appointment_event_id` despues del commit.
- Un `AppointmentNotificationService` cargara cita, evento, actor, customer,
  paciente y accesos autorizados.
- Crear un registro compartido en `tenant_notifications` cuando corresponda.
- Crear un registro por usuario customer autorizado en `portal_notifications`.
- Incluir solo `appointment_id`, `animal_id`, tipo y datos minimos de navegacion.
- URLs: `/client/agenda/{id}` para Laravel, `/tabs/agenda/{id}` para Ionic tenant
  y `/portal/citas/{id}` para customer.
- Deduplicar con `appointment_event_id` en datos y en la tabla de entregas.
- Nunca incluir `internal_notes`, metadata clinica o credenciales.

Matriz persistente implementada:

- Cada evento funcional crea actualizacion tenant compartida y customer cuando
  existe un acceso autorizado. Esto incluye solicitud, alta manual,
  confirmacion, rechazo, propuesta, respuesta, expiracion, cancelacion,
  completada, no show y estados de cargo tardio.
- `appointment.cancelled` se publica como
  `appointment.cancelled_by_customer` o `appointment.cancelled_by_tenant`
  usando metadata y, para eventos historicos, el rol del actor.
- Email y push todavia no se despachan; respetaran la matriz selectiva en los
  Pasos 10.4 y 10.5.
- Recordatorios programados se completan en el Paso 11.

Pruebas:

- todos los eventos y destinatarios;
- acceso customer/paciente y aislamiento tenant;
- idempotencia/reintento;
- rollback no genera notificacion;
- payload no contiene notas internas.

Implementacion realizada:

- `AppointmentEvent::created` registra un callback `DB::afterCommit`; un
  rollback no deja Job ni notificacion.
- `ProcessAppointmentEventNotifications` implementa `ShouldQueue` y
  `ShouldBeUnique`, usa cinco intentos, backoff progresivo y la cola `default`.
- El Job recibe solo `appointment_event_id`; al ejecutar vuelve a cargar el
  evento y delega en `AppointmentNotificationService`.
- El procesador crea una entrega `tenant_in_app` con destinatario
  `tenant:{id}` y una `customer_in_app` por `user:{id}`.
- Cada entrega se bloquea y deduplica por evento/canal/hash. Una entrega marcada
  `delivered` se omite en reintentos.
- Tenant recibe un solo `tenant_notifications` con `user_id=null`, visible en
  Laravel web e Ionic sin duplicar por operador.
- Customer recibe `portal_notifications` solo si cumple simultaneamente:
  usuario activo con rol customer, un unico acceso vigente, link no revocado,
  asignacion vigente al paciente y `show_appointments=true`.
- Customers/pacientes inactivos o eliminados y accesos ambiguos no reciben una
  notificacion que despues no puedan abrir.
- Deep links guardados:
  `/client/agenda/{id}`, `/tabs/agenda/{id}` y `/portal/citas/{id}`.
- Payload seguro: IDs de evento/cita/paciente, tipo, fecha, timezone, snapshots
  de paciente/servicio y ruta. No incluye motivo, metadata libre, notas internas,
  expediente, pago, token o credenciales.
- Los titulos y cuerpos se adaptan al evento y audiencia usando el timezone de
  la cita.

Archivos creados/modificados:

- `app/Jobs/ProcessAppointmentEventNotifications.php`
- `app/Services/AppointmentNotificationService.php`
- observer after-commit en `app/Models/AppointmentEvent.php`
- `tests/Feature/AppointmentNotificationServiceTest.php`
- ajuste del test estructural de entregas para usar el canal email reservado.

No se agregaron migraciones, paquetes, endpoints ni cambios Ionic en este paso.

Verificacion:

- Pint y sintaxis PHP: correctos.
- Pruebas dirigidas: 6 tests, 35 assertions.
- Suite Laravel completa: 138 tests, 596 assertions.
- Se verificaron idempotencia, todos los tipos de evento, cancelacion por actor,
  acceso/link/asignacion/visibilidad, acceso ambiguo, Job inexistente y rollback.

#### Paso 10.4: correos de Agenda en queue

Estado: completado en desarrollo local el 2026-06-21.

Objetivo: enviar emails reintentables a partir del mismo evento persistido.

Cambios previstos:

- Mailable/plantilla parametrizada de Agenda con asunto por evento.
- Job separado por destinatario, con `tries`, backoff y timeout definidos.
- Enviar al customer vinculado y, solo en eventos de accion tenant, a operadores
  activos autorizados con email, deduplicando direcciones.
- Contenido: veterinaria, paciente, servicio, estado, fecha/hora y timezone,
  motivo visible cuando corresponda y enlace; sin notas internas.
- Marcar delivery exitosa/fallida sin afectar cita ni notificacion persistente.
- Pruebas con `Mail::fake`, queue fake, reintentos y deduplicacion.

Criterio de salida:

- un fallo SMTP se reintenta y nunca duplica el evento ni revierte la cita.

Matriz email implementada:

- Tenant recibe correo en solicitud nueva, contrapropuesta aceptada/rechazada y
  cancelacion realizada por customer.
- Customer recibe correo de acuse de solicitud, alta manual, confirmacion,
  rechazo, contrapropuesta, expiracion, cancelacion por cualquier actor, cargo
  tardio confirmado y no show.
- Confirmacion/rechazo/propuesta ejecutados por tenant no generan correo tenant
  redundante; respuesta de propuesta ejecutada por customer no genera correo
  customer redundante.
- Completada, cargo pendiente/exentado y eventos fuera de matriz permanecen solo
  como actualizacion persistente.

Implementacion realizada:

- `AppointmentEventMail` usa una plantilla HTML parametrizada y asunto por
  evento/audiencia.
- `SendAppointmentEmail` implementa `ShouldQueue` y `ShouldBeUnique` por delivery,
  cinco intentos, timeout de 60 segundos y backoff 30/120/600/1800.
- El Job recibe solo `delivery_id`, recarga evento/cita/tenant/usuario y vuelve a
  validar autorizacion justo antes de enviar.
- Un operador tenant debe seguir activo y conservar rol admin/client-admin o
  perfil veterinario activo.
- Un customer debe conservar acceso, link, asignacion y visibilidad de Agenda;
  si pierde acceso entre enqueue y envio, la entrega queda `skipped`.
- Destinatarios tenant se deduplican por email normalizado. Asistentes/cajeros no
  reciben correo operativo de Agenda.
- Las claves de entrega son `email:tenant:{user_id}` y
  `email:customer:{user_id}`; no almacenan la direccion en `recipient_key`.
- El Job actualiza `pending/processing/delivered/failed/skipped`, intentos,
  timestamps y ultimo error.
- Errores SMTP se repropagan para que Laravel reintente. `last_error` conserva
  solo clase y mensaje generico; nunca contrasenas o respuesta sensible SMTP.
- El despachador aisla excepciones del canal, especialmente con queue `sync`:
  un fallo SMTP queda registrado/reportado pero no convierte una cita ya
  persistida en respuesta HTTP fallida.
- Reprocesar el evento reutiliza la misma fila email. Una entrega exitosa o
  descartada no se vuelve a encolar.

Contenido del correo:

- veterinaria, destinatario, paciente, servicio, veterinario, fecha/hora,
  timezone y enlace a la cita;
- razon visible de rechazo/cancelacion y mensaje visible de propuesta/respuesta
  cuando aplica;
- no incluye `internal_notes`, motivo privado del customer, metadata libre,
  expediente clinico, pagos completos, tokens o credenciales.

Archivos del Paso 10.4:

Nuevos:

- `app/Mail/AppointmentEventMail.php`
- `app/Jobs/SendAppointmentEmail.php`
- `resources/views/emails/appointments/event.blade.php`
- `tests/Feature/AppointmentEmailTest.php`

Modificados:

- `app/Services/AppointmentNotificationService.php`
- `tests/Feature/AppointmentNotificationServiceTest.php`
- roadmap y checkpoint de Agenda.

No se agregaron migraciones, paquetes o endpoints. La configuracion SMTP ya
existia y `appointment_notification_deliveries` se reutilizo.

Operacion requerida fuera de tests:

- staging/produccion debe usar `QUEUE_CONNECTION=database` (o Redis/SQS);
- mantener un worker, por ejemplo `php artisan queue:work --queue=default`;
- configurar SMTP real y monitorear `failed_jobs`;
- con `QUEUE_CONNECTION=sync`, util para desarrollo, el correo se envia dentro
  del proceso que consume el evento y no obtiene aislamiento operativo real.

Verificacion:

- Pint y sintaxis PHP: correctos.
- Pruebas dirigidas: 6 tests, 42 assertions.
- Suite Laravel completa: 144 tests, 638 assertions.
- Se cubrieron todos los eventos de la matriz, operadores autorizados,
  exclusion de asistente, HTML seguro, acceso revocado, fallo SMTP sanitizado,
  reintento exitoso y deduplicacion.

#### Paso 10.5: Firebase Cloud Messaging en Laravel (completado)

Objetivo: entregar push Android/iOS mediante FCM HTTP v1.

Cambios ejecutados el 2026-06-21:

- Se instalo `kreait/laravel-firebase:5.10.0`, compatible con PHP 8.2 y
  Laravel 10. El paquete aporta su configuracion Firebase.
- Se agrego `config/appointment_push.php`, `FCM_ENABLED=false`,
  `FIREBASE_PROJECT=app` y `FIREBASE_CREDENTIALS=` a `.env.example`.
- Servicio proveedor desacoplado (`PushGateway`) y Job por dispositivo.
- Payload `notification` con titulo/cuerpo y `data` solo string:
  `notification_id`, `appointment_id`, `audience`, `route`, `event_type`.
- TTL y prioridad conservadores; nada de datos clinicos completos.
- Token no registrado/invalido marca dispositivo revocado. Error temporal se
  reintenta con backoff; error permanente no entra en ciclo infinito.
- Modo `FCM_ENABLED=false` para desarrollo/tests sin red.
- Se agregaron pruebas con gateway fake y clasificacion de errores.

Prerequisitos externos:

- proyecto Firebase;
- app Android `com.hectorbmx.vetsys`;
- app iOS con el mismo Bundle ID;
- service account de servidor con permisos FCM;
- APNs configurado dentro de Firebase.

#### Paso 10.6: integracion Ionic compartida

Objetivo: registrar dispositivos y manejar push para tenant y customer.

Cambios previstos:

- Instalar `@capacitor/push-notifications` en version compatible con Capacitor 8
  y ejecutar `npx cap sync`.
- Servicio unico para permiso, registro, refresh de token, errores y listeners.
- Pedir permiso en contexto claro, no antes de que exista sesion autenticada.
- Enviar token/plataforma/device UUID/version a la API y actualizarlo al rotar.
- Revocar el dispositivo en logout cuando haya conectividad.
- Foreground: refrescar stores y mostrar aviso local dentro de la app.
- Tap desde background/cerrada: validar `audience` y navegar por `route`/
  `appointment_id` usando la sesion activa; nunca confiar datos sensibles.
- Al reanudar o recuperar red: consultar notificaciones persistentes y contadores.
- Pruebas unitarias con wrapper del plugin y build Android/iOS sincronizado.

#### Paso 10.7: configuracion nativa y QA real

Android:

- colocar `google-services.json` fuera de Git;
- verificar manifest/permisos de Android 13+, canal, icono y color;
- probar permiso denegado, token refresh, foreground, background y app cerrada;
- generar build firmado de staging y probar en dispositivo con Play Services.

iOS:

- corregir Bundle ID Debug;
- colocar `GoogleService-Info.plist` en el target sin versionar secretos;
- activar Push Notifications y Background Modes/Remote notifications;
- agregar entitlements `aps-environment` por configuracion;
- cargar clave APNs `.p8`, Key ID y Team ID en Firebase;
- ejecutar `npx cap sync ios`, firmar con provisioning correcto y probar en
  iPhone fisico.

QA integral:

- recorrer solicitud, confirmacion, rechazo, contrapropuesta/respuesta,
  cancelacion y expiracion para tenant/customer;
- comprobar persistente, correo y push, deep link y contador;
- probar sin red, token invalido, SMTP/FCM caido, reintento y no duplicacion;
- validar que lectura Laravel/Ionic converge al mismo `read_at`;
- documentar worker/scheduler, secretos, metricas y procedimiento de rollback.

Criterio de cierre del Paso 10:

- tenant recibe persistente en Laravel/Ionic, email y push segun matriz;
- customer recibe persistente Ionic, email y push segun matriz;
- fallos de canales se reintentan y no alteran la cita;
- no hay duplicados ni datos sensibles en email/push;
- Android e iOS quedan probados en dispositivos reales.

## Handoff a macOS para continuar push/iOS

Fecha del handoff: 2026-06-21.

Este bloque permite continuar en otra sesion/maquina sin volver a auditar el
proyecto completo.

### Estado exacto antes del cambio

- Repositorios: `vetsys` (Laravel) y `gorozpeApp` (Ionic/Capacitor).
- Ambos estan en rama `master`.
- Ambos worktrees tienen cambios sin commit y muchos archivos nuevos del feature
  Agenda: aproximadamente 95 entradas en Laravel y 21 en Ionic al auditar.
- Un clone/pull limpio de `master` NO contiene necesariamente este avance ni el
  checkpoint. Antes de cambiar de equipo se debe hacer commit/push intencional o
  copiar el workspace completo incluyendo archivos untracked.
- No ejecutar reset/checkout ni descartar cambios al preparar el traslado.
- Ultima suite Laravel: 144 tests, 638 assertions, todo correcto.
- Ultimo build Ionic development: correcto en Paso 9.
- Karma sigue sin ejecutar ChromeHeadless en Windows por bloqueo GPU; los bundles
  si compilan.

### Versiones y estructura confirmadas

Backend Windows actual:

- PHP 8.2.4; proyecto admite `^8.1`.
- Composer 2.7.7.
- Laravel `^10.0`.
- Queue database, tablas `jobs`, `job_batches` y `failed_jobs` disponibles.

Ionic actual:

- Node usado: v24.14.0.
- Capacitor core/Android/iOS `^8.4.0`.
- App ID: `com.hectorbmx.vetsys`.
- Deployment target iOS: 15.0.
- Team ID presente en Xcode: `44583X3BRM`; verificar que corresponda a la cuenta
  Apple Developer usada en la Mac.
- Integracion iOS usa Swift Package Manager en
  `gorozpeApp/ios/App/CapApp-SPM/Package.swift`.
- No hay `Podfile`; no ejecutar `pod install` salvo que Capacitor cambie la
  integracion explicitamente.

### Backend push ya disponible

- `push_devices` guarda token cifrado y hash unico por dispositivo/usuario.
- `POST /api/v1/push-devices` registra o rota el token autenticado.
- `DELETE /api/v1/push-devices/{id}` revoca solo el dispositivo propio.
- `appointment_notification_deliveries` deduplica canales por evento/destino.
- `AppointmentEvent` genera notificaciones persistentes despues del commit.
- Correo de Agenda ya usa queue, reintentos, deduplicacion y matriz oficial.
- Deep links por `appointment_id` ya navegan a detalle tenant/customer en Ionic.

Al cierre de los Pasos 10.5 y 10.6 ya existen SDK/configuracion deshabilitable
en Laravel, `PushGateway`, Job FCM, entregas `push`, plugin Capacitor y listeners
Ionic. Permanecen pendientes las credenciales reales, el token FCM de iOS y QA
en dispositivos.

### Estado nativo iOS confirmado

- Release usa Bundle ID correcto: `com.hectorbmx.vetsys`.
- Debug tiene un error: `-com.hectorbmx.vetsys`. Corregirlo a
  `com.hectorbmx.vetsys` antes de registrar/probar Firebase/APNs.
- No existe `ios/App/App/GoogleService-Info.plist`.
- No existe `ios/App/App/App.entitlements`.
- No esta configurado `CODE_SIGN_ENTITLEMENTS`.
- No estan activadas capabilities Push Notifications ni Background Modes /
  Remote notifications.
- `Info.plist` no contiene `UIBackgroundModes` para `remote-notification`.
- `AppDelegate.swift` conserva la plantilla Capacitor y no tiene codigo push
  manual; preferir el proxy/plugin oficial salvo necesidad comprobada.
- Los artefactos web sincronizados en `ios/App/App/public` estan ignorados.

### Cuentas y archivos externos necesarios

Firebase:

1. Crear/iniciar sesion en Firebase y crear el proyecto.
2. Registrar app iOS con Bundle ID exacto `com.hectorbmx.vetsys`.
3. Descargar `GoogleService-Info.plist`.
4. Crear una service account para backend/FCM HTTP v1 y descargar JSON.
5. Obtener el Project ID.

Apple Developer:

1. Confirmar membresia activa y acceso al Team `44583X3BRM` o actualizar el Team
   del proyecto al correcto.
2. Registrar/confirmar App ID `com.hectorbmx.vetsys` con Push Notifications.
3. Crear clave APNs `.p8` y guardar Key ID y Team ID.
4. Subir `.p8`, Key ID y Team ID en Firebase > Project settings > Cloud
   Messaging > Apple app configuration.
5. Preparar provisioning/firma para dispositivo fisico y App Store.

Secretos y archivos:

- Nunca versionar service-account JSON ni clave APNs `.p8`.
- El checkpoint actual tambien exige mantener `GoogleService-Info.plist` y
  `google-services.json` fuera de Git; agregar reglas `.gitignore` antes de
  colocarlos porque el `.gitignore` iOS actual no los excluye.
- Guardar credenciales backend fuera del web root y referenciarlas con variables
  de entorno.
- Variables previstas para Paso 10.5: `FCM_ENABLED`, `FCM_PROJECT_ID` y ruta de
  credenciales. No pegar JSON completo dentro de Git o checkpoint.

### Preparacion del workspace en la Mac

Despues de transferir/commitear el estado actual:

Laravel:

```bash
cd vetsys
composer install
cp .env.example .env  # solo si no se transfirio un .env seguro
php artisan key:generate  # solo para un entorno nuevo
php artisan migrate
php artisan test
```

Configurar base de datos, SMTP y queue en `.env`. No reemplazar un `.env`
existente con secretos validos.

Ionic:

```bash
cd gorozpeApp
npm ci
npm run build -- --configuration development
npx cap sync ios
```

El plugin push ya esta instalado y sincronizado. En macOS se debe repetir
`npx cap sync ios` despues de integrar Firebase Messaging en el Paso 10.7.

### Orden recomendado en la nueva sesion

1. Verificar que el checkpoint y archivos nuevos existan en la Mac.
2. Ejecutar `git status --short` en ambos repos; no limpiar cambios existentes.
3. Ejecutar suite Laravel y build Ionic para validar el traslado.
4. Verificar los Pasos 10.5 y 10.6 ya implementados.
5. Crear/configurar Firebase y probar FCM HTTP v1 con credenciales fuera de Git.
6. Ejecutar Paso 10.7: Firebase Messaging/APNs, capabilities y QA real.
7. Corregir Bundle ID Debug y configurar capabilities/plist/APNs en Xcode.
8. Ejecutar `npm run build`, `npx cap sync ios` y abrir
   `ios/App/App.xcodeproj` en Xcode.
9. Probar permiso, token, foreground, background, app cerrada y deep link en un
   iPhone fisico.
10. Solo despues preparar Archive/TestFlight/App Store.

### Criterio de reanudacion sin nueva auditoria

La siguiente sesion puede arrancar directamente en Paso 10.5 si:

- estan presentes las migraciones 2026-06-21 y los servicios/Jobs de Agenda;
- la suite reporta al menos 144 tests correctos;
- existen `push_devices` y `appointment_notification_deliveries`;
- `AppointmentNotificationService` y `SendAppointmentEmail` estan presentes;
- no se descartaron los cambios sin commit durante el traslado.

Si alguno falta, el problema es de transferencia/versionado, no de diseno; usar
este checkpoint para comparar archivos antes de volver a implementar.

## Ejecucion del Paso 10.5: backend FCM

Fecha: 2026-06-21. Equipo: Windows, repo `vetsys`.

### Implementacion terminada

- Dependencia fijada: `kreait/laravel-firebase:5.10.0` en `composer.json` y
  `composer.lock`. Composer tambien actualizo dependencias transitivas
  compatibles al resolver el SDK.
- `PushGateway` desacopla el dominio del SDK. `FirebasePushGateway` construye
  `CloudMessage` con destino token y traduce errores de Kreait a:
  `InvalidPushTokenException`, `PermanentPushException` y
  `TransientPushException`.
- `DisabledPushGateway` se resuelve cuando `FCM_ENABLED=false`; por ello un
  worker local/test arranca sin service account y sin intentar red.
- `SendAppointmentPush` es un Job unico por delivery, con 5 intentos, timeout
  de 60 segundos y backoff de 30, 120, 600 y 1800 segundos.
- Antes de enviar, el Job vuelve a validar tenant, usuario activo, dispositivo
  no revocado y acceso vigente de operador/customer. Una revocacion posterior
  a crear la entrega produce `skipped`.
- Token desconocido/invalido revoca `push_devices.revoked_at` y no reintenta.
  Error permanente queda `skipped`; error temporal queda `failed`, se relanza
  para queue y puede reutilizar la misma entrega.
- `last_error` solo guarda clase/categoria sanitizada; nunca mensaje de SDK,
  token, credencial o respuesta remota.
- `AppointmentNotificationService` crea una entrega canal `push` por
  `appointment_event_id + audience + user_id + push_device_id`, usando el
  indice de deduplicacion existente, y despacha solo para dispositivos activos.
- Payload `data` contiene solo strings: `notification_id`, `appointment_id`,
  `appointment_event_id`, `animal_id`, `event_type`, `starts_at`, `timezone`,
  `animal_name`, `service_name`, `route` y `audience`. No incluye notas
  internas, razones privadas, correo, customer completo ni token.
- La cita y su evento no dependen del resultado del push: el envio sigue
  desacoplado mediante queue y las excepciones de dispatch son reportadas.

### Matriz push implementada

- Tenant (admin/client-admin y veterinario activo): `requested`,
  `proposal_accepted`, `proposal_rejected`, `cancelled_by_customer`.
- Customer con acceso vigente: `requested`, `created_manually`, `confirmed`,
  `rejected`, `proposed`, `proposal_expired`, `cancelled_by_tenant` y
  `late_fee_charged`.
- Sin push: `completed`, `no_show`, `late_fee_pending`, `late_fee_waived` y los
  eventos redundantes para la misma audiencia definidos en la matriz.

### Archivos creados

- `app/Contracts/PushGateway.php`
- `app/Exceptions/InvalidPushTokenException.php`
- `app/Exceptions/PermanentPushException.php`
- `app/Exceptions/TransientPushException.php`
- `app/Jobs/SendAppointmentPush.php`
- `app/Services/DisabledPushGateway.php`
- `app/Services/FirebasePushGateway.php`
- `config/appointment_push.php`
- `tests/Feature/AppointmentPushTest.php`

### Archivos modificados

- `.env.example`, `.gitignore`, `app/Providers/AppServiceProvider.php`
- `app/Services/AppointmentNotificationService.php`
- `composer.json`, `composer.lock`
- `docs/appointment-scheduling-checkpoint.md`

### Verificacion ejecutada

- `php artisan test --filter=AppointmentPushTest`: 6 tests, 33 assertions.
- `php artisan test --filter=Appointment`: 89 tests, 442 assertions.
- `php artisan test`: 150 tests, 671 assertions, todo correcto.
- Pint aplicado y verificacion de estilo correcta en archivos del Paso 10.5.
- `composer audit`: 6 advisories en `laravel/framework` y `symfony/yaml`.
  Registrar una actualizacion mayor de Laravel/Symfony como trabajo separado;
  no se hizo una migracion de framework dentro del feature Agenda.

### Configuracion externa pendiente

- No se agrego ni probo una service account real. Mantener
  `FCM_ENABLED=false` hasta disponer del JSON fuera de Git.
- Para activar: establecer `FIREBASE_PROJECT`, apuntar
  `FIREBASE_CREDENTIALS` a la ruta absoluta segura del JSON, limpiar cache de
  configuracion, reiniciar workers y finalmente usar `FCM_ENABLED=true`.
- No hubo envio real a Firebase/dispositivo en Windows; esa prueba pertenece
  al Paso 10.7 con credenciales y hardware.

## Ejecucion del Paso 10.6: integracion Ionic compartida

Fecha: 2026-06-22. Equipo: Windows, repo `gorozpeApp`.

### Implementacion terminada

- Instalado `@capacitor/push-notifications:8.1.1`, compatible con Capacitor 8,
  en `package.json` y `package-lock.json`.
- Creado `NativePushNotificationsService` como adaptador testeable del plugin
  oficial: plataforma, permisos, registro y cuatro listeners nativos.
- Creado `PushNotificationsService` como coordinador unico para tenant y
  customer. En web no solicita permisos ni invoca APIs nativas.
- El servicio inicia desde `AppComponent`, vuelve a registrar al reanudar/volver
  la red y se activa inmediatamente despues de login normal o biometrico.
- Flujo de permisos: consulta estado, solicita solo cuando esta en `prompt` y
  registra si queda `granted`; permiso denegado no bloquea login ni uso.
- Android registra/rota el token FCM en `POST /api/v1/push-devices` con
  `platform`, UUID estable local y nombre de dispositivo. Guarda el ID de
  backend separado por usuario.
- Logout intenta `DELETE /api/v1/push-devices/{id}` antes de cerrar la sesion;
  un fallo de revocacion no impide limpiar/autenticar el logout existente.
- Foreground y tap reconcilian la fuente persistente: customer refresca portal
  y tenant refresca `TenantNotificationsService`.
- El tap nunca confia una URL arbitraria del push. Valida `appointment_id` y
  construye `/portal/citas/{id}` para customer o `/tabs/agenda/{id}` para
  tenant; sin cita abre la bandeja de notificaciones correspondiente.
- Errores nativos o de sincronizacion se guardan en una signal y nunca rompen
  login, bootstrap, cita o navegacion.
- Ejecutado Capacitor sync Android: Gradle incluye
  `capacitor-push-notifications`.
- Ejecutado Capacitor sync iOS: Swift Package Manager incluye
  `CapacitorPushNotifications`. Las rutas generadas por Windows se corrigieron
  manualmente a `/` para mantener `Package.swift` valido en macOS.

### Limite iOS confirmado

- En Android, el plugin entrega token FCM y ya se envia a Laravel.
- En iOS, `@capacitor/push-notifications` entrega token APNs. El backend del
  Paso 10.5 espera token FCM; enviar el APNs como FCM seria incorrecto.
- Por ello iOS solicita permiso y recibe eventos, pero no publica el token APNs
  a `/push-devices`. El Paso 10.7 debe integrar Firebase Messaging en Xcode,
  obtener su token FCM y pasarlo al mismo flujo de registro.

### Archivos creados

- `src/app/core/services/native-push-notifications.service.ts`
- `src/app/core/services/push-notifications.service.ts`
- `src/app/core/services/push-notifications.service.spec.ts`

### Archivos modificados

- `package.json`, `package-lock.json`
- `src/app/app.component.ts`
- `src/app/core/services/auth.service.ts`
- `android/app/capacitor.build.gradle`
- `android/capacitor.settings.gradle`
- `ios/App/CapApp-SPM/Package.swift`
- `docs/appointment-scheduling-checkpoint.md`

Los assets copiados dentro de `android/app/src/main/assets` e
`ios/App/App/public` permanecen ignorados por Git; Capacitor los regenerara.

### Verificacion ejecutada

- Build development correcto usando salida aislada `.codex-tmp`; TypeScript y
  bundle Angular compilan.
- Pruebas enfocadas push en ChromeHeadless: 3/3 correctas (registro/revocacion
  Android, exclusion del APNs crudo y deep link customer con reconciliacion).
- Suite Karma global: 13 tests, 9 correctos y 4 fallos preexistentes porque
  `app.component.spec.ts`, `tab1.page.spec.ts`, `tab2.page.spec.ts` y
  `tabs.page.spec.ts` no proveen `HttpClient`. Las 3 pruebas push pasan dentro
  de esa misma compilacion.
- Lint global: un error preexistente en `video-upload.service.ts:19` por
  `@angular-eslint/prefer-inject`; los archivos del Paso 10.6 no reportan error.
- `npm install` reporto 31 advisories (2 low, 6 moderate, 23 high). No se uso
  `npm audit fix --force` porque implicaria cambios mayores no auditados.
- `cap sync android` correcto y `cap sync ios` correcto al repetir fuera del
  sandbox por archivos nativos bloqueados.

## Punto de arranque de la siguiente sesion

Ejecutar Paso 10.7 en macOS:

1. Hacer `npm ci` y repetir `npx cap sync ios`.
2. Corregir el Bundle ID Debug `-com.hectorbmx.vetsys`.
3. Registrar app iOS/Firebase, agregar `GoogleService-Info.plist` fuera de Git e
   integrar Firebase Messaging para obtener token FCM, no el APNs crudo.
4. Conectar ese token FCM al metodo de registro ya implementado o exponerlo
   mediante un adaptador nativo especifico.
5. Activar Push Notifications, Background Modes/Remote notifications y
   `aps-environment`; configurar APNs `.p8` en Firebase.
6. Configurar service account del backend, activar `FCM_ENABLED=true`, limpiar
   cache Laravel y reiniciar workers.
7. Probar tenant/customer en iPhone fisico: permiso, token, foreground,
   background, app cerrada, tap, refresh, logout/revocacion y reconciliacion.
8. Resolver los cuatro tests antiguos sin `HttpClient` y el lint preexistente
   antes de exigir suite Ionic global completamente verde.
