# HTTP tenant de agenda

Fecha del contrato: 2026-06-20.

Acceso permitido: `client-admin`, rol legado `admin` y usuarios con
`VeterinarianProfile` activo en el tenant. Asistentes, cajeros y customers quedan
bloqueados.

## API movil tenant

Base URL: `/api/v1/appointments`.

Lecturas:

- `GET /bootstrap`: configuracion, readiness, doctor, customers, mascotas y servicios.
- `GET /availability`: recibe `service_id`, `from` y `to` opcional.
- `GET /`: agenda paginada; admite `from`, `to`, `statuses[]`, `customer_id` y
  `animal_id`.
- `GET /{appointment}`: detalle, contrapropuestas e historial.

Escrituras:

- `POST /manual`
- `POST /{appointment}/confirm`
- `POST /{appointment}/reject`
- `POST /{appointment}/proposals`
- `POST /{appointment}/cancel`
- `POST /{appointment}/complete`
- `POST /{appointment}/no-show`

Todas las escrituras exigen `Idempotency-Key`. La disponibilidad tiene modo
tenant: respeta veterinario, horarios, bloqueos y ocupaciones, pero no depende de
que las solicitudes customer esten habilitadas ni de su anticipacion minima.

## Acciones web

Base URL: `/client/agenda`.

Se exponen las mismas siete escrituras para formularios Laravel. El token
idempotente se envia como `idempotency_key` en el formulario. Las operaciones
redirigen a la pagina anterior con mensaje de exito o errores de dominio en la
sesion.

El panel Laravel ya expone agenda diaria/semanal, detalle, filtros, formulario
manual con slots y formularios para las siete transiciones tenant.

## Resources y errores

El Resource tenant incluye datos de customer, motivo, notas internas, cargo de
cancelacion, acciones disponibles, propuestas y metadata de auditoria. Nunca se
serializan locks ni claves idempotentes.

Las excepciones API usan `message`, `code` y `errors`. Estados esperados:
`401`, `403`, `404`, `409`, `422` y `429`.

Rate limits adicionales:

- Lecturas: 60 por minuto y usuario.
- Escrituras: 20 por minuto y usuario.
