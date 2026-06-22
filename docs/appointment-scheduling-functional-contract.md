# Contrato funcional: agenda privada de citas

Fecha: 2026-06-19
Version: 1.0
Estado: cerrado para iniciar modelo de datos

## 1. Proposito

Este documento define el comportamiento funcional del MVP de agenda. Es la
fuente de verdad para migraciones, servicios, API, panel Laravel e Ionic.

En caso de diferencia con el roadmap, este contrato tiene prioridad para las
reglas funcionales del MVP.

## 2. Alcance confirmado

- Agenda privada, nunca publica.
- Solo customers autenticados con acceso activo al portal.
- El tenant y customer se resuelven desde la sesion.
- Una mascota asignada es obligatoria.
- Un servicio agendable es obligatorio.
- Un veterinario agendable se asigna automaticamente.
- Una ubicacion por tenant en el MVP.
- El tenant siempre confirma, rechaza o contrapropone.
- No se permiten citas confirmadas simultaneas para el veterinario.
- Laravel web e Ionic tenant comparten operaciones y notificaciones.
- El customer opera citas solamente desde Ionic.

## 3. Nombres de producto

### 3.1 Entidades visibles

| Nombre interno | Nombre visible |
| --- | --- |
| Appointment | Cita |
| Appointment request | Solicitud de cita |
| Appointment proposal | Propuesta de horario |
| Availability | Horarios disponibles |
| Schedule block | Bloqueo de agenda |
| Doctor schedule | Horario de atencion |
| Late cancellation | Cancelacion tardia |
| No show | No asistio |

### 3.2 Servicios del catalogo

Los servicios describen que se solicita, no el estado de la cita.

Ejemplos:

- Consulta general.
- Vacunacion.
- Revision.
- Cirugia.

Solo `catalog_items` activos, de tipo `service` y marcados como agendables se
presentan al customer.

### 3.3 Estados definitivos

| Estado interno | Etiqueta customer | Etiqueta tenant |
| --- | --- | --- |
| `pending_tenant` | Esperando confirmacion | Solicitud pendiente |
| `pending_customer` | Responde la propuesta | Esperando al cliente |
| `confirmed` | Confirmada | Confirmada |
| `rejected` | No disponible | Rechazada |
| `cancelled` | Cancelada | Cancelada |
| `completed` | Completada | Completada |
| `no_show` | No asististe | No asistio |

Estados terminales: `rejected`, `cancelled`, `completed` y `no_show`.

## 4. Actores y permisos

### Customer

Puede:

- consultar sus servicios y horarios disponibles;
- solicitar una cita para una mascota asignada;
- consultar sus propias citas;
- aceptar o rechazar una contrapropuesta vigente;
- cancelar de acuerdo con la politica configurada.

No puede:

- enviar o elegir tenant, customer o veterinario;
- confirmar su solicitud original;
- leer notas internas;
- cambiar una cita directamente a otra fecha;
- operar una cita de otro customer.

### Administrador de tenant

Es un usuario con rol canonico `client-admin`. Puede realizar todas las
operaciones y configuraciones de agenda.

### Veterinario

Es un usuario activo del tenant con `veterinarian_profile` activo. Puede ver y
operar citas, motivos y notas internas, pero no cambiar politicas globales.

### Otros roles

`asistente` y `cajero` quedan sin acceso a agenda en el MVP.

## 5. Ciclo de vida

### 5.1 Solicitud inicial

1. Customer elige mascota, servicio, fecha, hora y escribe un motivo opcional.
2. Backend resuelve tenant, customer y veterinario.
3. Backend valida servicio, mascota, acceso, configuracion y slot.
4. Se crea la cita en `pending_tenant`.
5. La solicitud no bloquea definitivamente el horario.
6. Tenant recibe aviso y decide confirmar, rechazar o contrapropone.

Puede haber mas de una solicitud pendiente para el mismo horario. La primera
que se confirme obtiene el horario; las demas deben recibir contrapropuesta o
rechazo.

### 5.2 Confirmacion

- Solo administrador o veterinario pueden confirmar.
- Antes de confirmar se revalida el intervalo completo dentro de transaccion.
- Si ya no esta disponible, se responde conflicto y no cambia el estado.
- Al confirmar, `starts_at`, `ends_at` y duracion quedan fijados en la cita.

### 5.3 Rechazo

- Solo administrador o veterinario pueden rechazar.
- El motivo visible para customer es obligatorio.
- Rechazar no genera cargos.
- Una cita rechazada no puede reabrirse; se crea una nueva solicitud.

### 5.4 Contrapropuesta

- Solo administrador o veterinario pueden crearla.
- Incluye fecha, hora, duracion y mensaje visible opcional.
- Solo existe una propuesta activa por cita.
- Crear otra marca la anterior como `superseded`.
- La propuesta conserva `previous_appointment_status` para saber si nacio de
  una solicitud o de una cita ya confirmada.
- La cita pasa a `pending_customer`.
- El horario propuesto queda retenido hasta su expiracion.

Respuesta customer:

- Aceptar revalida disponibilidad y cambia la cita a `confirmed`.
- Rechazar marca la propuesta `rejected` y restaura
  `previous_appointment_status`: `pending_tenant` para una solicitud o
  `confirmed` para una reprogramacion.
- Una propuesta expirada no puede responderse.

### 5.5 Reprogramacion de cita confirmada

- Tenant propone un nuevo horario sin eliminar el horario confirmado original.
- Mientras customer responde, el horario original sigue reservado.
- La nueva propuesta tambien queda retenida temporalmente.
- Si customer acepta, se libera el horario original y se confirma el nuevo.
- Si rechaza o expira, la cita permanece confirmada en el horario original.

Customer no puede proponer unilateralmente otra fecha en el MVP. Puede cancelar
y crear una nueva solicitud, o comunicarse con el tenant.

### 5.6 Finalizacion

- `completed`: administrador o veterinario confirma que la atencion termino.
- `no_show`: administrador o veterinario marca que el customer no asistio.
- No se permite completar o marcar no show antes del inicio, salvo una
  tolerancia operativa futura que queda fuera del MVP.

## 6. Transiciones validas

| Estado origen | Accion | Actor | Estado destino |
| --- | --- | --- | --- |
| `pending_tenant` | Confirmar | Admin/veterinario | `confirmed` |
| `pending_tenant` | Contraproponer | Admin/veterinario | `pending_customer` |
| `pending_tenant` | Rechazar | Admin/veterinario | `rejected` |
| `pending_tenant` | Cancelar | Customer/admin/veterinario | `cancelled` |
| `pending_customer` | Aceptar propuesta | Customer | `confirmed` |
| `pending_customer` | Rechazar propuesta | Customer | Estado previo |
| `pending_customer` | Reemplazar propuesta | Admin/veterinario | `pending_customer` |
| `pending_customer` | Cancelar | Customer/admin/veterinario | `cancelled` |
| `confirmed` | Proponer reprogramacion | Admin/veterinario | `pending_customer` |
| `confirmed` | Cancelar | Customer/admin/veterinario | `cancelled` |
| `confirmed` | Completar | Admin/veterinario | `completed` |
| `confirmed` | No asistio | Admin/veterinario | `no_show` |

Toda otra transicion devuelve `409 APPOINTMENT_INVALID_TRANSITION`.

## 7. Configuracion y valores predeterminados

Todas las duraciones se configuran por tenant.

| Configuracion | Default | Rango MVP |
| --- | --- | --- |
| Zona horaria | `America/Mexico_City` | Zona IANA valida |
| Intervalo de slots | 15 minutos | 5 a 60 |
| Duracion predeterminada | 30 minutos | 5 a 480 |
| Anticipacion minima | 120 minutos | 0 a 10080 |
| Ventana maxima | 60 dias | 1 a 365 |
| Vigencia de propuesta | 24 horas | 1 a 72 |
| Aviso para cancelar gratis | 24 horas | 0 a 168 |
| Recordatorio | 24 horas antes | 1 a 168 |

La duracion del servicio tiene prioridad sobre la duracion predeterminada. El
tenant puede ajustar duracion al confirmar o proponer.

## 8. Vigencia de contrapropuestas

Una contrapropuesta retiene el nuevo horario. Su expiracion es:

```text
min(
  fecha_creacion + vigencia_configurada,
  fecha_propuesta - anticipacion_minima
)
```

Si el resultado no deja tiempo positivo para responder, el backend rechaza la
propuesta con `422 APPOINTMENT_PROPOSAL_EXPIRY_INVALID`.

Al expirar:

- se libera la retencion;
- se marca la propuesta `expired`;
- se registra evento de auditoria;
- se notifica al customer;
- se restaura `previous_appointment_status`: solicitud nueva vuelve a
  `pending_tenant` y reprogramacion vuelve a `confirmed` con el horario original.

La expiracion se ejecuta mediante tarea programada, pero todos los endpoints
tambien consideran vencida una propuesta por su `expires_at` aunque el Job aun
no la haya procesado.

## 9. Politica de cancelacion

### 9.1 MVP

El tenant configura:

- horas de aviso para cancelacion gratuita;
- si registra cancelacion tardia;
- servicio de catalogo opcional para cargo por cancelacion;
- monto fijo o porcentaje sugerido;
- si el cargo se cobra posteriormente o en la siguiente consulta.

Flujo:

1. Customer solicita cancelar y proporciona motivo opcional.
2. Backend determina si esta dentro o fuera del plazo.
3. La cita se cancela en ambos casos.
4. Si es tardia, queda marcada para revision del tenant.
5. Tenant decide `Aplicar cargo` o `Perdonar cargo`.
6. Aplicar cargo crea el movimiento financiero usando los mecanismos de cuenta
   existentes; no se cobra automaticamente al cancelar.

El trato diferenciado queda en manos del tenant y toda decision se audita.

### 9.2 Modos MVP

- `no_penalty`: cancela sin cargo.
- `late_fee_review`: cancelacion tardia pendiente de decision del tenant.

Estados del cargo tardio:

- `not_applicable`
- `pending_review`
- `waived`
- `charged`

### 9.3 Anticipo fuera del MVP

`deposit_required` queda documentado, pero no se implementa en el primer MVP.
Requiere:

- retener horario mientras se paga;
- checkout y webhook idempotentes;
- expiracion de pago;
- reembolsos totales/parciales;
- reglas de devolucion por cancelacion del tenant/customer;
- conciliacion con Stripe y cuenta del customer.

No se agregaran campos de deposito hasta diseñar ese subflujo financiero.

### 9.4 Cancelacion por tenant

- Siempre permitida antes de completar/no show.
- Motivo visible obligatorio.
- Nunca genera cargo al customer.
- Si en el futuro existe anticipo, debe iniciar reembolso segun su contrato.

## 10. Disponibilidad y concurrencia

- `pending_tenant` no bloquea slots.
- `confirmed` bloquea inicio, duracion y buffer.
- Propuesta vigente bloquea temporalmente el intervalo propuesto.
- Una reprogramacion mantiene bloqueado tambien el horario original.
- Bloqueos del veterinario impiden slots.
- Todos los horarios se guardan en UTC y se muestran en zona del tenant.
- Confirmar/aceptar/proponer revalida dentro de transaccion.
- Un conflicto devuelve HTTP 409 y disponibilidad actualizada cuando sea viable.

Solapamiento:

```text
existing.starts_at < candidate.ends_at
AND existing.ends_at > candidate.starts_at
```

## 11. Entidades desactivadas

### Servicio desactivado o no agendable

- Desaparece de nuevas solicitudes y disponibilidad.
- Citas confirmadas conservan nombre, duracion y horario.
- Solicitudes `pending_tenant` no pueden confirmarse con ese servicio.
- Tenant debe cambiar el servicio mediante contrapropuesta o rechazar.
- No se borran citas ni auditoria.

### Customer inactivo

- No puede crear nuevas solicitudes.
- Si conserva acceso portal, no puede aceptar propuestas ni cancelar desde app.
- Citas confirmadas permanecen y requieren decision del tenant.
- Tenant puede confirmar, contrapropone, rechazar, cancelar o completar.
- Puede seguir recibiendo correo operativo sobre cambios de una cita existente.

### Acceso portal suspendido/revocado

- Customer no puede usar endpoints de agenda.
- Citas existentes no se eliminan.
- Tenant mantiene control total.
- Correo puede continuar para cambios operativos, salvo baja de comunicaciones
  exigida por politica futura.

### Veterinario o perfil desactivado

- Deja de generar disponibilidad inmediatamente.
- No permite confirmar nuevas solicitudes.
- Citas futuras no se cancelan automaticamente.
- Se marcan para atencion operativa del administrador.
- Tenant debe reactivar/configurar veterinario o cancelar.
- Como el MVP tiene uno, nuevas solicitudes quedan deshabilitadas hasta resolverlo.

### Servicio/customer/veterinario eliminado

Se usaran llaves, soft delete o snapshots suficientes para que citas historicas
sean legibles. Ninguna eliminacion en cascada debe borrar historial de citas.

## 12. Eventos y notificaciones

### 12.1 Fuentes persistentes

- Tenant: `tenant_notifications`, visible en Laravel web e Ionic tenant.
- Customer: `portal_notifications`, visible en Ionic customer.
- Email y push son canales derivados, no fuentes de verdad.

### 12.2 Matriz

| Evento | Tenant | Customer |
| --- | --- | --- |
| `appointment.requested` | In-app, push, email | In-app, push, email de recepcion |
| `appointment.confirmed` | In-app | In-app, push, email |
| `appointment.rejected` | In-app | In-app, push, email |
| `appointment.proposed` | In-app | In-app, push, email |
| `appointment.proposal_accepted` | In-app, push, email | In-app |
| `appointment.proposal_rejected` | In-app, push, email | In-app |
| `appointment.proposal_expired` | In-app | In-app, push, email |
| `appointment.cancelled_by_customer` | In-app, push, email | In-app, email |
| `appointment.cancelled_by_tenant` | In-app | In-app, push, email |
| `appointment.late_fee_pending` | In-app | In-app |
| `appointment.late_fee_charged` | In-app | In-app, push, email |
| `appointment.reminder` | No por default | In-app, push, email |
| `appointment.completed` | In-app | In-app |
| `appointment.no_show` | In-app | In-app, email |

No se envia correo al actor por cada actualizacion redundante. La tabla indica
el canal funcional; el servicio puede omitir correo al usuario que ejecuto la
accion cuando el cambio ya se confirma en pantalla.

### 12.3 Datos seguros de notificacion

- `appointment_id`
- tipo de evento
- fecha/hora formateada
- nombre de mascota
- nombre del servicio
- URL/deep link

No incluir:

- notas internas;
- expediente clinico;
- datos de pago completos;
- informacion sensible innecesaria en pantalla bloqueada.

## 13. Contrato de correos

### Asuntos base

- `Nueva solicitud de cita para {mascota}`
- `Tu cita fue confirmada`
- `Nueva propuesta de horario para tu cita`
- `Respuesta a propuesta de horario`
- `Tu solicitud no pudo ser confirmada`
- `Cita cancelada`
- `La propuesta de horario vencio`
- `Recordatorio de cita`
- `Cargo por cancelacion registrado`

### Contenido comun

- nombre/logo de veterinaria cuando este disponible;
- mascota;
- servicio;
- veterinario;
- fecha y hora en zona del tenant;
- duracion;
- estado actual;
- mensaje o motivo visible;
- resumen de politica de cancelacion;
- boton/deep link para abrir la cita;
- canal de contacto del tenant.

Nunca se envia `internal_notes`.

## 14. Contrato API comun

### 14.1 Respuesta exitosa

```json
{
  "data": {},
  "meta": {
    "server_time": "2026-06-19T18:00:00Z",
    "timezone": "America/Mexico_City"
  }
}
```

### 14.2 Error

```json
{
  "message": "El horario ya no esta disponible.",
  "code": "APPOINTMENT_SLOT_UNAVAILABLE",
  "errors": {},
  "meta": {
    "server_time": "2026-06-19T18:00:00Z"
  }
}
```

### 14.3 Solicitar cita customer

`POST /api/v1/portal/appointments`

```json
{
  "animal_id": 45,
  "catalog_item_id": 12,
  "starts_at": "2026-06-25T16:00:00Z",
  "customer_reason": "Revision general"
}
```

Campos prohibidos: `tenant_id`, `customer_id`, `doctor_user_id`, `status`,
`ends_at`, `internal_notes`.

Respuesta: HTTP 201 con cita en `pending_tenant`.

### 14.4 Disponibilidad

`GET /api/v1/portal/appointments/availability?catalog_item_id=12&date=2026-06-25`

```json
{
  "data": [
    {
      "starts_at": "2026-06-25T16:00:00Z",
      "ends_at": "2026-06-25T16:30:00Z",
      "local_starts_at": "2026-06-25T10:00:00-06:00"
    }
  ],
  "meta": {
    "timezone": "America/Mexico_City",
    "server_time": "2026-06-19T18:00:00Z"
  }
}
```

### 14.5 Confirmar tenant

`POST /api/v1/appointments/{appointment}/confirm`

```json
{
  "duration_minutes": 30,
  "internal_notes": "Opcional"
}
```

La fecha solicitada se usa si no existe una propuesta activa.

### 14.6 Contrapropuesta tenant

`POST /api/v1/appointments/{appointment}/proposals`

```json
{
  "starts_at": "2026-06-26T17:00:00Z",
  "duration_minutes": 45,
  "message": "Podemos atenderte el viernes a esta hora."
}
```

Respuesta: HTTP 201 con propuesta, `expires_at` y cita `pending_customer`.

### 14.7 Responder propuesta customer

`POST /api/v1/portal/appointments/{appointment}/proposals/{proposal}/accept`

`POST /api/v1/portal/appointments/{appointment}/proposals/{proposal}/reject`

Rechazo opcional:

```json
{
  "message": "No puedo asistir en ese horario."
}
```

### 14.8 Cancelar

Tenant:

`POST /api/v1/appointments/{appointment}/cancel`

Customer:

`POST /api/v1/portal/appointments/{appointment}/cancel`

```json
{
  "reason": "No podre asistir."
}
```

Respuesta incluye:

```json
{
  "data": {
    "status": "cancelled",
    "is_late_cancellation": true,
    "cancellation_fee_status": "pending_review"
  }
}
```

## 15. Codigos de error

| HTTP | Codigo | Uso |
| --- | --- | --- |
| 401 | `AUTHENTICATION_REQUIRED` | Token ausente/invalido |
| 403 | `CUSTOMER_PORTAL_ACCESS_INACTIVE` | Acceso customer suspendido |
| 403 | `APPOINTMENT_FORBIDDEN` | Actor sin permiso |
| 404 | `APPOINTMENT_NOT_FOUND` | No existe o no pertenece al contexto |
| 404 | `APPOINTMENT_ANIMAL_NOT_FOUND` | Mascota no asignada; no revelar otra pertenencia |
| 422 | `APPOINTMENT_BOOKING_DISABLED` | Agenda tenant deshabilitada |
| 422 | `APPOINTMENT_DOCTOR_UNAVAILABLE` | Sin veterinario activo/configurado |
| 422 | `APPOINTMENT_SERVICE_UNAVAILABLE` | Servicio inactivo/no agendable |
| 422 | `APPOINTMENT_OUTSIDE_BOOKING_WINDOW` | Fuera de anticipacion/ventana |
| 422 | `APPOINTMENT_PROPOSAL_EXPIRY_INVALID` | Propuesta sin tiempo de respuesta |
| 422 | `APPOINTMENT_VALIDATION_FAILED` | Campos invalidos |
| 409 | `APPOINTMENT_SLOT_UNAVAILABLE` | Intervalo ocupado al escribir |
| 409 | `APPOINTMENT_INVALID_TRANSITION` | Estado no permite accion |
| 409 | `APPOINTMENT_PROPOSAL_EXPIRED` | Propuesta vencida |
| 409 | `APPOINTMENT_PROPOSAL_SUPERSEDED` | Existe propuesta posterior |
| 409 | `APPOINTMENT_ALREADY_PROCESSED` | Reintento no idempotente |
| 429 | `APPOINTMENT_RATE_LIMITED` | Demasiadas solicitudes |

Para recursos ajenos se prefiere 404 sobre 403 para no revelar existencia.

## 16. Idempotencia

- Crear solicitud acepta una clave `Idempotency-Key` por usuario.
- Confirmar, cancelar y responder propuesta deben tolerar doble tap.
- Repetir exactamente una accion completada devuelve el resultado actual sin
  duplicar evento, correo, push o cargo.
- Reusar una clave con payload diferente devuelve 409.

## 17. Criterios de aceptacion del contrato

- Estados tienen etiqueta, actor, origen y destino.
- Configuraciones tienen default y rango.
- Contrapropuestas tienen retencion y expiracion determinista.
- Cancelacion tardia no cobra automaticamente en el MVP.
- Anticipo queda explicitamente fuera del MVP.
- Entidades desactivadas no borran historial.
- Eventos tienen destinatarios y canales.
- Correos tienen contenido permitido/prohibido.
- API define payloads, seguridad, errores e idempotencia.
- El Paso 2 puede diseñar migraciones sin decisiones funcionales pendientes.
