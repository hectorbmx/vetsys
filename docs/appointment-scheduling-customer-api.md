# API customer de agenda

Fecha del contrato: 2026-06-20.

Base URL: `/api/v1/portal/appointments`.

Todas las rutas requieren token Bearer de Sanctum y pasan por `access.mobile`,
`api.tenant` y `customer.portal`. Tenant y customer se resuelven desde el usuario;
no se aceptan esos IDs en el payload.

## Lecturas

### Bootstrap

`GET /bootstrap`

Devuelve disponibilidad global de la agenda, timezone, politicas publicas,
veterinario, mascotas con `show_appointments` y servicios reservables.

### Servicios

`GET /services`

Devuelve `id`, nombre, descripcion de reserva, duracion y buffer. Solo incluye
servicios activos, no eliminados y marcados como reservables.

### Disponibilidad

`GET /availability?animal_id=1&service_id=2&from=2026-07-01&to=2026-07-07`

- `animal_id`, `service_id` y `from` son obligatorios.
- `to` es opcional y el rango maximo es 31 dias.
- Los slots incluyen timestamps UTC y locales con offset y timezone.
- Limite: 30 consultas por minuto y usuario.

### Lista y detalle

- `GET /?status=confirmed&from=2026-07-01&to=2026-07-31&per_page=20`
- `GET /{appointment}`

La lista es paginada. Las fechas de filtro se interpretan en el timezone del
tenant. El detalle incluye contrapropuestas e historial publico, nunca
`internal_notes` ni metadata interna de eventos.

## Escrituras

Todas requieren el encabezado `Idempotency-Key` con un valor de hasta 100
caracteres. Limite: 10 escrituras por minuto y usuario.

### Solicitar cita

`POST /`

```json
{
  "animal_id": 1,
  "service_id": 2,
  "starts_at": "2026-07-06T09:00:00-06:00",
  "customer_reason": "Revision general"
}
```

Responde `201` con estado `pending_tenant`.

### Responder contrapropuesta

- `POST /{appointment}/proposals/{proposal}/accept`
- `POST /{appointment}/proposals/{proposal}/reject`

El rechazo admite `response_message` opcional de hasta 1000 caracteres.

### Cancelar

`POST /{appointment}/cancel`

```json
{
  "reason": "Ya no necesito la consulta"
}
```

La razon es opcional para customer. El dominio determina si la cancelacion es
tardia y si queda pendiente de revision de cargo.

## Respuestas y errores

Los recursos se entregan bajo `data`. Los listados paginados agregan `links` y
`meta`. Una excepcion de dominio usa:

```json
{
  "message": "El horario seleccionado ya no esta disponible.",
  "code": "APPOINTMENT_SLOT_UNAVAILABLE",
  "errors": {}
}
```

Estados relevantes:

- `401`: token ausente o sesion movil invalida.
- `403`: portal/customer sin acceso vigente o contexto ambiguo.
- `404`: recurso ajeno, no asignado o sin visibilidad de citas.
- `409`: conflicto de estado, slot o idempotencia.
- `422`: payload invalido.
- `429`: limite de frecuencia excedido.

Repetir una escritura con la misma clave y payload devuelve el mismo resultado.
Usar la misma clave con un payload distinto responde con conflicto.
