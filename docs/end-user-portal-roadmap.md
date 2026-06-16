# Roadmap: portal de usuario final

## Objetivo

Crear un portal para el usuario final del tenant, normalmente el dueno de una
mascota, donde pueda consultar solo los pacientes que el veterinario le haya
asignado, revisar historial clinico y administrativo, ver evidencias y pagar
notas pendientes con tarjeta mediante Stripe.

El alcance debe mantener aislamiento estricto por tenant y por asignacion:

- Un usuario final nunca debe ver pacientes de otro tenant.
- Un usuario final nunca debe ver pacientes no asignados a su cuenta.
- El veterinario/tenant conserva el control de que pacientes, notas, archivos y
  servicios son visibles para el usuario final.

## Definicion de usuario final

Usuario autenticado con rol limitado, distinto al personal del tenant.

Puede representar a:

- Dueno de mascota.
- Responsable autorizado del paciente.
- Cliente externo con acceso de consulta y pago.

No debe tener acceso a funciones administrativas como configuracion del tenant,
inventario, gestion de usuarios, reportes internos, edicion clinica o
facturacion global.

## Decision de modelo: `users` vs `customers`

El acceso al portal debe resolverse desde `users`, no solo desde `customers`.

Razon:

- `users` ya es la tabla de autenticacion.
- `users` ya tiene `tenant_id`, invitaciones, estado activo y roles Spatie.
- Ya existe el rol base `customer`.
- `customers` representa la ficha comercial/cliente del tenant, no
  necesariamente una cuenta que inicia sesion.
- Un mismo `customer` podria tener varios usuarios finales autorizados.
- Un usuario final podria estar relacionado con mas de un paciente.

Modelo recomendado:

- `users`: identidad que inicia sesion.
- Rol `customer`: rol de usuario final.
- `customers`: ficha comercial, datos de contacto, estado de cuenta y relacion
  con pacientes.
- Tabla puente para relacionar usuarios finales con customers.
- Tabla puente para relacionar usuarios finales con pacientes cuando se requiera
  control granular.

No se recomienda resolver todo con un campo simple en `customers`, porque
limitaria escenarios como multiples responsables, accesos revocados, invitaciones
pendientes, permisos por paciente y auditoria.

## Acceso cobrado al portal

Es probable que algunos tenants quieran cobrar un servicio o membresia para que
el usuario final acceda al portal, historial, estudios o estado de cuenta.

Decision inicial:

- El MVP debe arrancar con acceso libre/sin costo para el usuario final.
- El tenant siempre debe poder activar o desactivar el acceso al portal/app movil
  desde su panel web.
- El cobro por acceso debe ser opcional por tenant, no una regla global del
  sistema.
- La arquitectura debe quedar preparada desde el inicio para activar cobro mas
  adelante sin rehacer permisos, usuarios ni relaciones con pacientes.

Decision de producto:

- El toggle en la tabla/listado de clientes significa "este customer tiene
  derecho de login al portal/app".
- Ese toggle no significa que el customer vea toda la informacion.
- Despues de activar el toggle, el tenant controla granularmente que mascotas,
  secciones e items individuales se comparten.
- El acceso de la app/portal inicia libre en MVP, pero controlado por el tenant.

La recomendacion es separar:

- Permiso de login: vive en `users` y roles.
- Relacion comercial: vive en `customers`.
- Habilitacion/cobro del portal: vive en una tabla dedicada o en una extension
  de `customer_account_settings`.

### Configuracion por tenant

Crear configuracion a nivel tenant para definir como se comporta el acceso del
usuario final.

Tabla sugerida:

- `tenant_portal_settings`
  - `tenant_id`
  - `is_portal_enabled`
  - `is_mobile_access_enabled`
  - `access_mode`: `free`, `paid`, `included`, `disabled`
  - `default_access_status`: `active`, `invited`, `disabled`
  - `requires_manual_activation`
  - `monthly_price`
  - `currency`
  - `trial_days`
  - `created_by`
  - timestamps

Valores recomendados para MVP:

- `is_portal_enabled`: `true`
- `is_mobile_access_enabled`: `true`
- `access_mode`: `free`
- `default_access_status`: `active`
- `requires_manual_activation`: `true`
- `monthly_price`: `null`
- `currency`: `MXN`

Interpretacion:

- `free`: el tenant no cobra acceso; solo controla quien entra.
- `paid`: el tenant cobra acceso al portal/app movil.
- `included`: el acceso esta incluido en otro servicio, plan o membresia del
  cliente.
- `disabled`: el tenant no ofrece portal/app movil a sus customers.

Aunque el MVP use `free`, guardar `access_mode` desde el inicio evita una
migracion conceptual despues.

### Opcion recomendada: tabla `customer_portal_accesses`

Crear una tabla dedicada para controlar acceso comercial al portal:

- `customer_portal_accesses`
  - `id`
  - `tenant_id`
  - `customer_id`
  - `user_id`
  - `status`: `invited`, `active`, `suspended`, `expired`, `revoked`
  - `billing_mode`: `free`, `included`, `paid`, `trial`
  - `activated_by`
  - `activated_at`
  - `access_starts_at`
  - `access_ends_at`
  - `trial_ends_at`
  - `last_paid_at`
  - `next_billing_at`
  - `revoked_at`
  - `revoked_by`
  - `notes`
  - timestamps

Ventajas:

- Permite varios usuarios finales por cliente.
- Permite suspender acceso sin borrar el `customer`.
- Permite cobrar, regalar o incluir el portal por cliente.
- Permite activar acceso desde el panel web aunque el tenant no cobre.
- Permite historial de invitacion y revocacion.
- Evita agregar demasiados campos de portal a `customers`.

### Campos minimos si se decide extender `customer_account_settings`

Si el primer MVP necesita algo mas rapido, se puede extender
`customer_account_settings` con:

- `is_portal_enabled`
- `portal_billing_mode`
- `portal_access_ends_at`
- `portal_last_paid_at`

Esta opcion es mas rapida, pero menos flexible. Solo conviene si se acepta que
el acceso sea por customer completo y no por usuario final individual.

### Relacion usuario final con cliente

Tabla sugerida:

- `customer_user_links`
  - `tenant_id`
  - `customer_id`
  - `user_id`
  - `relationship`: `owner`, `guardian`, `payer`, `viewer`
  - `is_primary`
  - `created_by`
  - `revoked_at`

Esta tabla responde la pregunta: "que usuario final representa a que cliente".

### Relacion usuario final con paciente

Para control granular, mantener la asignacion por paciente:

- `final_user_patient_assignments`
  - `tenant_id`
  - `user_id`
  - `customer_id`
  - `animal_id`
  - `assigned_by`
  - `assigned_at`
  - `revoked_at`

Esta tabla responde la pregunta: "que pacientes puede ver este usuario final".

Regla recomendada para MVP:

- El tenant activa el acceso desde su panel web.
- Si el tenant tiene `access_mode = free`, el acceso activo no requiere pago.
- Si el tenant tiene `access_mode = paid`, el acceso activo puede depender de
  una suscripcion, pago vigente o fecha `access_ends_at`.
- Si el usuario tiene acceso activo al portal/app movil del `customer`, puede
  ver los pacientes asignados explicitamente.
- Si no tiene acceso activo al portal, no puede entrar aunque tenga pacientes
  asignados.
- El tenant puede suspender acceso por falta de pago del servicio del portal sin
  afectar el historial interno del customer.

Flujo recomendado al activar toggle del customer:

1. Validar que el customer tenga email o dato suficiente para crear invitacion.
2. Crear o vincular un `user` con rol `customer`.
3. Crear o actualizar `customer_user_links`.
4. Crear o actualizar `customer_portal_accesses` con estado `active`.
5. Mostrar al tenant la configuracion de mascotas compartidas.
6. Permitir que el tenant asigne mascotas y configure secciones visibles.
7. Enviar invitacion o acceso inicial si aplica.

## Alcance funcional

### Pacientes asignados

El usuario final podra ver una lista de pacientes/mascotas asignados por el
veterinario.

Informacion inicial sugerida:

- Nombre del paciente.
- Especie/tipo.
- Raza, sexo, edad o fecha de nacimiento si existe.
- Estado del paciente.
- Fotografia si existe.
- Ultima visita o ultima nota visible.
- Indicador de saldo/notas pendientes si aplica.

Reglas:

- La asignacion debe ser explicita.
- La baja de una asignacion debe revocar el acceso inmediatamente.
- El paciente puede pertenecer a un cliente administrativo distinto, pero el
  usuario final solo vera lo que tenga asignado.

### Historial del paciente

El usuario final podra consultar el historial visible de cada paciente.

Contenido posible:

- Consultas.
- Diagnosticos.
- Tratamientos.
- Procedimientos.
- Vacunas.
- Servicios realizados.
- Productos aplicados o vendidos.
- Archivos asociados.
- Videos.
- Estudios de radiologia.
- Indicaciones post-consulta.
- Citas pasadas y futuras si el tenant decide exponerlas.

Reglas:

- No todo el historial interno tiene que ser publico.
- Cada nota, archivo o evento debe poder marcarse como visible/no visible para
  el usuario final.
- Datos sensibles internos, costos internos, comentarios del equipo o bitacoras
  administrativas no deben exponerse por defecto.

### Granularidad de visibilidad por paciente

La visibilidad del portal debe manejarse en dos niveles desde el MVP:

1. Nivel general por mascota y seccion.
2. Nivel individual por nota, archivo, video, RX o recurso sensible.

#### Nivel 1: mascota y tabs/secciones

Despues de activar acceso al customer, el tenant define que mascotas puede ver.

Ejemplo:

- Luna: visible.
- Max: visible.
- Rocky: no visible.

Para cada mascota visible, el tenant puede controlar secciones:

- Datos generales.
- Historial.
- Notas.
- Servicios.
- Productos.
- Archivos.
- Videos.
- RX/radiologia.
- Estado de cuenta.
- Vacunas.
- Citas.

Tabla sugerida:

- `animal_portal_visibility_settings`
  - `tenant_id`
  - `customer_id`
  - `user_id`
  - `animal_id`
  - `show_profile`
  - `show_history`
  - `show_notes`
  - `show_services`
  - `show_products`
  - `show_files`
  - `show_videos`
  - `show_radiology`
  - `show_statement`
  - `show_vaccines`
  - `show_appointments`
  - `updated_by`
  - timestamps

Regla:

- Si una seccion esta apagada, el endpoint correspondiente no devuelve datos,
  aunque existan items publicados dentro de esa seccion.

#### Nivel 2: items individuales

Para notas, archivos, videos, RX/radiologia y otros recursos sensibles, agregar
publicacion individual.

Campos sugeridos en cada recurso o mediante tabla de publicaciones:

- `visible_to_customer`
- `published_at`
- `published_by`

Reglas:

- Si `show_notes = true`, el customer solo ve notas con
  `visible_to_customer = true`.
- Si `show_files = true`, el customer solo ve archivos publicados.
- Si `show_radiology = true`, el customer solo ve RX/radiologia publicada.
- Los recursos no publicados deben responder 404 o quedar ocultos, aunque el
  customer conozca el ID.

### Notas y estado de pago

El usuario final podra ver notas generadas por el veterinario cuando esten
marcadas como visibles.

Estados sugeridos:

- Borrador: no visible para usuario final.
- Pendiente de pago: visible y pagable.
- Pagada: visible con recibo o confirmacion.
- Cancelada: oculta por defecto o visible solo si se requiere historial.
- Parcialmente pagada: visible si el sistema soporta abonos.

Detalle visible de nota:

- Folio o identificador.
- Fecha.
- Paciente asociado.
- Servicios y productos incluidos.
- Subtotal, impuestos, descuentos y total.
- Pagos aplicados.
- Saldo pendiente.
- Estado.

### Estado de cuenta

El estado de cuenta del customer debe heredar la configuracion existente que el
tenant ya manipula para cada cliente.

Fuente de verdad:

- `CustomerAccountSetting`
- `cutoff_day`
- `credit_days`
- `is_statement_enabled`
- servicios actuales de generacion de estados de cuenta

Reglas:

- El portal/app no debe inventar otra logica de corte.
- Si el tenant define que el corte del customer es el dia 15, el resumen de
  movimientos se genera segun esa configuracion.
- Si `is_statement_enabled = true`, el sistema puede generar automaticamente el
  estado de cuenta en el dia de corte configurado.
- Si el tenant decide no generar automatico, el customer solo vera estados de
  cuenta generados/publicados manualmente.
- El customer solo consulta estados de cuenta ya generados y visibles.
- Cuando se genere un nuevo estado de cuenta visible, se crea una notificacion
  para el customer.

Endpoints sugeridos:

- `GET /api/v1/portal/statements`
- `GET /api/v1/portal/statements/{statement}`
- `GET /api/v1/portal/statements/{statement}/pdf`

Notificacion:

- `portal.statement.generated`

Condiciones para notificar:

- Portal/app habilitado para el tenant.
- Customer con acceso activo.
- Estado de cuenta generado.
- Estado de cuenta visible/publicado para el customer.

### Pago con tarjeta via Stripe

El usuario final podra pagar notas pendientes con tarjeta de credito/debito.

Flujo recomendado:

1. Usuario final abre una nota pendiente.
2. Backend valida tenant, asignacion del paciente y visibilidad de la nota.
3. Backend crea una sesion de pago o PaymentIntent en Stripe.
4. Usuario completa el pago con Stripe.
5. Stripe notifica al backend por webhook.
6. Backend registra el pago, actualiza la nota y conserva la referencia Stripe.
7. Portal muestra confirmacion y estado actualizado.

Decisiones tecnicas a cerrar:

- Usar Stripe Checkout o Payment Element.
- Definir si cada tenant usara la cuenta Stripe de la plataforma o Stripe
  Connect.
- Definir comisiones, moneda, impuestos y recibos.
- Definir soporte para pagos parciales o solo pago total de nota.
- Definir si se permiten reintentos y expiracion de links de pago.

Datos minimos a guardar:

- `stripe_payment_intent_id` o `stripe_checkout_session_id`.
- `stripe_charge_id` si aplica.
- Monto, moneda y estado.
- Nota pagada.
- Tenant.
- Usuario final que inicio el pago.
- Fecha de confirmacion por webhook.
- Payload resumido o referencia auditada del evento.

### Archivos, videos y radiologia

El usuario final podra ver archivos que el tenant haya publicado para el
paciente o la nota.

Tipos:

- Imagenes.
- Videos.
- PDFs.
- Radiografias o estudios.
- Documentos clinicos.
- Resultados de laboratorio si existen.

Reglas:

- Acceso siempre autorizado por tenant, paciente asignado y visibilidad.
- Evitar URLs publicas permanentes cuando el archivo sea sensible.
- Preferir URLs temporales para R2/S3.
- Registrar descargas o visualizaciones solo si se necesita auditoria.

### Servicios y productos

El usuario final podra ver el detalle de servicios o productos asociados a una
nota, historial o tratamiento cuando el tenant los exponga.

Informacion sugerida:

- Nombre.
- Cantidad.
- Precio publico si forma parte de una nota.
- Descripcion o indicaciones.
- Fecha de aplicacion/entrega.
- Profesional responsable si aplica.

No se deben exponer:

- Costo interno.
- Margenes.
- Proveedor.
- Existencias internas.
- Comentarios administrativos.

### Notificaciones para usuario final

El portal debe tener notificaciones propias para eventos que afectan al usuario
final. No deben mezclarse sin filtro con las notificaciones internas del tenant.

Eventos iniciales:

- Nota creada/publicada.
- Nota pendiente de pago.
- Pago confirmado.
- Pago fallido.
- Servicio realizado.
- RX/radiologia publicada.
- Video o archivo clinico publicado.
- Estado de cuenta generado.
- Recordatorio de saldo pendiente.
- Paciente asignado al portal.
- Acceso al portal activado, suspendido o proximo a vencer.

Reglas:

- Solo notificar si el usuario final tiene acceso activo al portal.
- Solo notificar recursos visibles para ese usuario.
- Las notificaciones deben apuntar a rutas del portal, no del panel interno.
- Notificaciones de dinero deben incluir monto, moneda y folio cuando aplique.
- Notificaciones clinicas no deben revelar detalles sensibles en email/push si
  no es necesario; el detalle se consulta dentro del portal autenticado.
- Las notificaciones deben poder marcarse como leidas para que desaparezcan del
  contador y no se acumulen como pendientes.
- Puede existir historial de notificaciones leidas, pero el contador debe usar
  solo `read_at = null`.

Canales sugeridos:

- In-app dentro del portal.
- Email para eventos importantes.
- Push movil en una fase posterior.

Modelo recomendado:

- Reutilizar `tenant_notifications` solo si se agrega un `audience` claro:
  `tenant_staff` o `final_user`.
- Alternativa mas limpia: crear `portal_notifications`.

Tabla sugerida si se crea independiente:

- `portal_notifications`
  - `tenant_id`
  - `customer_id`
  - `user_id`
  - `animal_id` nullable
  - `type`
  - `title`
  - `body`
  - `url`
  - `data`
  - `read_at`
  - timestamps

Tipos sugeridos:

- `portal.note.created`
- `portal.note.payment_pending`
- `portal.payment.succeeded`
- `portal.payment.failed`
- `portal.service.completed`
- `portal.radiology.published`
- `portal.media.published`
- `portal.statement.generated`
- `portal.balance.due`
- `portal.access.activated`
- `portal.access.suspended`

## Alcance no funcional

### Seguridad

- Autenticacion obligatoria.
- Aislamiento por tenant en todas las queries.
- Politicas de autorizacion para paciente, nota, pago y archivo.
- Validacion de visibilidad por recurso.
- Proteccion contra manipulacion de IDs en URLs.
- Webhooks Stripe con firma verificada.
- Idempotencia en procesamiento de pagos.

### Auditoria

Registrar eventos importantes:

- Usuario final creado/invitado.
- Paciente asignado o desasignado.
- Acceso comercial al portal activado, suspendido, expirado o revocado.
- Cobro del servicio de portal generado o pagado.
- Nota publicada para usuario final.
- Notificacion creada para usuario final.
- Intento de pago iniciado.
- Pago confirmado, fallido o cancelado.
- Archivo publicado u ocultado.

### Experiencia de usuario

El portal debe ser simple y orientado a consulta:

- Pantalla inicial con pacientes asignados.
- Ficha de paciente con historial.
- Vista de notas pendientes.
- Accion clara para pagar.
- Estado de pago visible despues de regresar de Stripe.
- Soporte movil desde el inicio.

## Modelo de permisos propuesto

Crear o reutilizar permisos especificos:

- `final_user.portal.access`
- `final_user.patients.view`
- `final_user.patient_history.view`
- `final_user.notes.view`
- `final_user.notes.pay`
- `final_user.files.view`
- `final_user.notifications.view`
- `final_user.statements.view`

Para el panel interno del tenant, agregar permisos administrativos:

- `portal_access.manage`
- `portal_access.billing.manage`
- `portal_settings.manage`
- `portal_publications.manage`
- `portal_notifications.manage`

Entidades o relaciones candidatas:

- `tenant_portal_settings`
  - `tenant_id`
  - `is_portal_enabled`
  - `is_mobile_access_enabled`
  - `access_mode`
  - `default_access_status`
  - `requires_manual_activation`
  - `monthly_price`
  - `currency`
  - `trial_days`

- `customer_user_links`
  - `tenant_id`
  - `customer_id`
  - `user_id`
  - `relationship`
  - `is_primary`
  - `created_by`
  - `revoked_at`

- `customer_portal_accesses`
  - `tenant_id`
  - `customer_id`
  - `user_id`
  - `status`
  - `billing_mode`
  - `access_starts_at`
  - `access_ends_at`
  - `last_paid_at`
  - `next_billing_at`

- `final_user_patient_assignments`
  - `tenant_id`
  - `user_id`
  - `customer_id`
  - `animal_id`
  - `assigned_by`
  - `assigned_at`
  - `revoked_at`

- Campos de visibilidad en recursos existentes:
  - `visible_to_final_user`
  - `published_at`
  - `published_by`

Si se requiere control mas granular, usar una tabla de publicaciones por recurso:

- `portal_publications`
  - `tenant_id`
  - `user_id` opcional
  - `animal_id`
  - `publishable_type`
  - `publishable_id`
  - `visible`
  - `published_by`
  - `published_at`
  - `revoked_at`

## API sugerida

Base path sugerido: `/api/v1/portal`

Endpoints iniciales:

- `GET /me`
- `GET /patients`
- `GET /patients/{patient}`
- `GET /patients/{patient}/history`
- `GET /patients/{patient}/notes`
- `GET /notes/{note}`
- `POST /notes/{note}/payments/stripe-session`
- `GET /files/{file}/temporary-url`
- `GET /notifications`
- `PATCH /notifications/{notification}/read`
- `PATCH /notifications/read-all`
- `GET /statements`
- `GET /statements/{statement}`

Webhooks:

- `POST /webhooks/stripe`

Respuesta de paciente sugerida:

```json
{
  "id": 123,
  "name": "Maya",
  "animal_type": "Canino",
  "status": "active",
  "photo_url": null,
  "last_activity_at": "2026-06-15T18:00:00Z",
  "pending_balance": 1250.00
}
```

Respuesta de nota sugerida:

```json
{
  "id": 456,
  "folio": "N-000456",
  "status": "pending_payment",
  "patient": {
    "id": 123,
    "name": "Maya"
  },
  "items": [
    {
      "type": "service",
      "name": "Consulta general",
      "quantity": 1,
      "unit_price": 650.00,
      "total": 650.00
    }
  ],
  "subtotal": 650.00,
  "tax": 0.00,
  "discount": 0.00,
  "total": 650.00,
  "paid": 0.00,
  "balance": 650.00,
  "currency": "MXN"
}
```

## Roadmap por fases

### Fase 1: auditoria y definicion de alcance

Objetivo: entender las entidades actuales y cerrar el contrato funcional.

Tareas:

- Identificar modelos actuales de usuarios, roles y tenants.
- Identificar modelos de pacientes/mascotas.
- Identificar modelo actual de notas, pagos, servicios y productos.
- Identificar como se almacenan archivos, videos y radiologia.
- Definir que historial puede publicarse al usuario final.
- Definir si el acceso sera web, app movil o ambos.
- Definir si el usuario final se invita por email, se crea manualmente o se
  autogestiona.

Entregable:

- Documento de contrato funcional y matriz de permisos.

### Fase 2: identidad y asignacion de pacientes

Objetivo: permitir que el tenant asigne pacientes a usuarios finales.

Tareas:

- Reutilizar o formalizar rol `customer` para usuario final.
- Crear permisos del portal.
- Crear configuracion `tenant_portal_settings`.
- Crear relacion `customer_user_links`.
- Crear control de acceso comercial `customer_portal_accesses`.
- Crear tabla de asignacion usuario-paciente.
- Crear configuracion de visibilidad por mascota/seccion:
  `animal_portal_visibility_settings`.
- Crear UI interna para configurar si el portal/app movil esta habilitado.
- Crear UI interna para elegir modo de acceso: libre, incluido, pagado o
  deshabilitado.
- Crear UI interna para asignar/desasignar pacientes.
- Crear UI interna para activar/suspender acceso al portal.
- Agregar policies/scopes para validar acceso por tenant y asignacion.
- Agregar pruebas de aislamiento.

Entregable:

- Usuario final puede autenticarse si tiene rol `customer`, acceso activo al
  portal/app movil y pacientes asignados. En MVP, el acceso activo sera libre si
  el tenant usa `access_mode = free`.

### Fase 3: portal de pacientes e historial

Objetivo: construir la experiencia base de consulta.

Tareas:

- Crear listado de pacientes asignados.
- Crear ficha de paciente.
- Crear timeline de historial visible.
- Agregar control de visibilidad por tabs/secciones de mascota.
- Agregar control de visibilidad en notas/eventos/archivos/RX/videos.
- Ocultar datos internos por defecto.

Entregable:

- Portal consultivo funcional sin pagos.

### Fase 4: notas visibles y saldos

Objetivo: mostrar notas publicadas y estado de pago.

Tareas:

- Definir estados de nota visibles para portal.
- Exponer detalle de servicios/productos cobrados.
- Calcular saldo pendiente.
- Mostrar notas pendientes, pagadas y parciales si aplica.
- Proteger notas no publicadas o no asociadas a pacientes asignados.

Entregable:

- Usuario final puede consultar notas y saldos de sus pacientes.

### Fase 5: integracion Stripe

Objetivo: permitir pago seguro de notas pendientes.

Tareas:

- Definir estrategia Stripe: Checkout, Payment Element o Connect.
- Crear configuracion Stripe por plataforma/tenant.
- Crear endpoint para iniciar pago.
- Crear webhook Stripe con verificacion de firma.
- Registrar pagos de forma idempotente.
- Actualizar estado de nota despues del webhook.
- Mostrar confirmacion de pago en portal.
- Agregar pruebas para pagos exitosos, fallidos y eventos duplicados.

Entregable:

- Usuario final puede pagar una nota pendiente con tarjeta.

### Fase 6: archivos, videos y radiologia

Objetivo: exponer evidencias clinicas publicadas por el tenant.

Tareas:

- Inventariar tipos de archivo actuales.
- Crear permisos y policies por archivo.
- Generar URLs temporales para archivos privados.
- Crear visor de imagenes, PDFs y videos.
- Definir visor o descarga para radiologia segun formato disponible.
- Agregar controles internos para publicar/ocultar archivos.

Entregable:

- Usuario final puede ver evidencias publicadas sin acceso a archivos privados.

### Fase 7: notificaciones y comunicacion

Objetivo: avisar al usuario final cuando tenga contenido nuevo o pagos
pendientes.

Tareas:

- Decidir si se extiende `tenant_notifications` con `audience` o se crea
  `portal_notifications`.
- Crear servicio central `PortalNotificationService`.
- Notificar nueva nota pendiente.
- Notificar pago confirmado.
- Notificar pago fallido.
- Notificar servicio realizado.
- Notificar RX/radiologia publicada.
- Notificar archivo/historial publicado.
- Notificar estado de cuenta generado.
- Notificar acceso al portal activado/suspendido/proximo a vencer.
- Integrar email y/o notificaciones in-app.
- Permitir marcar una notificacion como leida.
- Permitir marcar todas como leidas.
- Asegurar que el contador solo incluya notificaciones no leidas.
- Reutilizar roadmap de notificaciones live si aplica.

Entregable:

- Usuario final recibe avisos accionables.

### Fase 8: hardening y lanzamiento

Objetivo: preparar la funcionalidad para uso real.

Tareas:

- Pruebas de autorizacion e IDOR.
- Pruebas de webhook Stripe.
- Pruebas de carga para historial y archivos.
- Revision de privacidad de datos.
- Logs y auditoria.
- Documentacion operativa para tenants.

Entregable:

- Portal listo para piloto con uno o mas tenants.

## MVP recomendado

Para una primera version, limitar alcance a:

1. Usuario final autenticado.
2. Rol `customer` en `users`.
3. Configuracion `tenant_portal_settings` con `access_mode = free` por defecto.
4. Tenant puede activar/desactivar acceso al portal/app movil desde panel web.
5. Relacion `customer_user_links`.
6. Acceso activo por `customer_portal_accesses`, sin cobro en MVP.
7. Pacientes asignados manualmente por el tenant.
8. Ficha de paciente con historial publicado.
9. Notas publicadas con estado pendiente/pagada.
10. Pago total de nota mediante Stripe Checkout para notas veterinarias.
11. Webhook Stripe idempotente.
12. Archivos visibles con URL temporal.
13. Notificaciones in-app basicas: nota pendiente, pago confirmado, RX/archivo
    publicado y estado de cuenta.

Dejar para fases posteriores:

- Pagos parciales.
- Stripe Connect por tenant.
- Notificaciones push.
- Cobro del acceso al portal/app movil por tenant.
- Cobro recurrente automatico del servicio de portal/app movil.
- Chat con veterinario.
- Firma digital.
- Radiologia avanzada con visor DICOM.
- Autogestion de cuentas por usuario final.

## Ejecucion del MVP por pasos

El MVP se puede ejecutar en 10 pasos. La idea es avanzar de base de datos y
permisos hacia experiencia de usuario, pagos y notificaciones, dejando siempre
una version verificable al final de cada paso.

### Paso 1: contrato final y nombres de dominio

Objetivo: cerrar el lenguaje del feature antes de programar.

Decisiones:

- Confirmar que el rol `customer` representa al usuario final.
- Confirmar que el acceso inicial sera libre con `access_mode = free`.
- Confirmar que el tenant activara acceso manualmente desde panel web.
- Confirmar si el primer canal sera app movil, portal web o ambos.
- Confirmar que el toggle en clientes solo da derecho de login, no visibilidad
  total.
- Confirmar que la visibilidad se controla por mascota, seccion e item
  individual.
- Confirmar que las notificaciones deben marcarse como leidas.

Entregable:

- Matriz final de permisos, estados y reglas de acceso.

### Detalle del contrato final

El contrato final es el documento corto que define como debe comportarse el MVP
antes de escribir migraciones, controladores o pantallas. Debe responder de forma
concreta: quien entra, que ve, quien lo activa, que se cobra y que eventos se
notifican.

#### 1. Actores del sistema

Definir los actores con nombres estables:

- Tenant: veterinaria/clinica que usa el sistema.
- Staff del tenant: admin, asistente, cajero u otro usuario interno.
- Customer: ficha comercial del cliente dentro del tenant.
- Usuario final: `user` con rol `customer` que entra al portal/app movil.
- Paciente: mascota/animal asociado al customer.

Regla base:

- El usuario final no administra el sistema; solo consulta informacion publicada
  y paga notas visibles.

#### 2. Estados del portal por tenant

Definir como se interpreta `tenant_portal_settings`.

Estados sugeridos:

- Portal habilitado: el tenant permite acceso al portal/app.
- Portal deshabilitado: ningun usuario final del tenant puede entrar.
- Acceso movil habilitado: la app movil puede consumir API del portal.
- Acceso movil deshabilitado: solo portal web o ningun acceso movil.

Modo de acceso:

- `free`: el tenant no cobra acceso.
- `paid`: el tenant cobra acceso.
- `included`: acceso incluido en otro plan o servicio.
- `disabled`: el tenant no ofrece acceso.

Decision MVP:

- `access_mode = free`.
- El tenant activa manualmente el acceso desde panel web.
- El cobro por acceso queda preparado, pero no se implementa en MVP.

#### 3. Estados de acceso por customer/usuario

Definir como se interpreta `customer_portal_accesses`.

Estados sugeridos:

- `invited`: usuario invitado, aun no activo.
- `active`: puede entrar si el tenant tambien tiene portal habilitado.
- `suspended`: acceso detenido temporalmente.
- `expired`: acceso vencido, util para futuro modo pagado.
- `revoked`: acceso retirado definitivamente.

Reglas:

- Si el tenant deshabilita el portal, nadie entra aunque tenga acceso `active`.
- Si el usuario no tiene acceso `active`, no ve pacientes, notas ni archivos.
- Si el usuario esta activo pero no tiene pacientes asignados, ve pantalla vacia.

#### 4. Relacion entre customer, usuario final y paciente

Definir las relaciones minimas:

- `customer_user_links`: que usuario final representa a que customer.
- `final_user_patient_assignments`: que pacientes puede ver ese usuario.
- `animal_portal_visibility_settings`: que tabs/secciones ve por mascota.

Reglas:

- Un customer puede tener varios usuarios finales.
- Un usuario final puede ver uno o varios pacientes asignados.
- Un paciente puede asignarse a uno o varios usuarios finales si el tenant lo
  permite.
- El acceso por URL directa siempre valida tenant, acceso activo y asignacion.
- El toggle del customer solo habilita login; la visibilidad real se decide
  despues por mascota, seccion e item publicado.

#### 5. Visibilidad de informacion

Definir que recursos se publican al usuario final.

Recursos MVP:

- Pacientes asignados.
- Historial publicado.
- Notas publicadas.
- Archivos/videos/radiologia publicados.
- Estado de cuenta publicado.
- Notificaciones de portal.

Reglas:

- Nada es visible por defecto si es sensible.
- El tenant decide que nota, archivo o evento se publica.
- El tenant decide primero que mascota se comparte.
- El tenant decide despues que tabs/secciones de esa mascota se comparten.
- En recursos sensibles, el tenant decide tambien que item individual se
  publica.
- No se exponen costos internos, margenes, inventario ni comentarios internos.

#### 6. Notas y pagos

Definir la diferencia entre dos tipos de cobro:

- Cobro de nota veterinaria: pago que hace el usuario final por servicios,
  productos o saldo pendiente.
- Cobro de acceso al portal/app: posible cobro futuro que el tenant podria
  activar para permitir acceso.

Decision MVP:

- Si se implementa Stripe, sera para pagar notas veterinarias pendientes.
- El acceso a la app/portal no se cobra en MVP.
- La arquitectura queda lista para que en el futuro el tenant active cobro por
  acceso.

Estados de nota visibles:

- `pending_payment`: visible y pagable.
- `paid`: visible como pagada.
- `partial`: visible si existe saldo parcial.
- `cancelled`: oculta por defecto.
- `draft`: no visible.

#### 7. Notificaciones

Definir que eventos generan notificacion para el usuario final.

Eventos MVP:

- Nota publicada o pendiente de pago.
- Pago confirmado.
- RX/radiologia publicada.
- Archivo/video publicado.
- Estado de cuenta generado.

Reglas:

- Solo se notifica a usuarios con acceso activo.
- Solo se notifica si el recurso esta publicado para ese usuario.
- La notificacion debe abrir una ruta del portal/app, no del panel interno.
- La notificacion desaparece del contador cuando el customer la marca como
  leida.
- El sistema debe soportar marcar una notificacion individual o todas como
  leidas.

#### 8. Rutas y API del contrato

Definir el namespace de API:

- `/api/v1/portal/*`

Definir que todas las respuestas deben estar filtradas por:

- tenant del usuario autenticado.
- acceso activo al portal.
- customer vinculado.
- paciente asignado.
- recurso publicado.

#### 9. Criterios de aceptacion del contrato

El contrato final se considera cerrado cuando estas frases son verdaderas:

- Sabemos quien puede activar el portal.
- Sabemos si el MVP cobra o no el acceso.
- Sabemos que rol usa el usuario final.
- Sabemos como se enlaza usuario final con customer.
- Sabemos como se asignan pacientes.
- Sabemos que recursos se publican.
- Sabemos que eventos notifican.
- Sabemos que endpoints usara la app/portal.
- Sabemos que casos deben bloquearse por seguridad.

#### 10. Ejemplo concreto del flujo MVP

Flujo esperado:

1. El tenant habilita portal/app movil en configuracion.
2. El tenant abre la ficha de un customer.
3. El tenant activa acceso al portal para ese customer.
4. El sistema crea o vincula un `user` con rol `customer`.
5. El tenant asigna uno o mas pacientes a ese usuario final.
6. El usuario final inicia sesion.
7. El usuario final ve solo sus pacientes asignados.
8. El usuario final abre historial, notas y archivos publicados.
9. Si hay nota pendiente publicada, puede pagarla con Stripe.
10. El usuario final recibe notificaciones por contenido publicado o pago
    confirmado.

### Paso 2: migraciones base del portal

Objetivo: crear la estructura persistente del feature.

Tablas/cambios:

- `tenant_portal_settings`
- `customer_user_links`
- `customer_portal_accesses`
- `final_user_patient_assignments`
- `animal_portal_visibility_settings`
- Campos de visibilidad/publicacion en notas, archivos o recursos necesarios.

Entregable:

- Base de datos lista para configurar tenant, enlazar usuario final, activar
  acceso y asignar pacientes.

### Paso 3: roles, permisos y policies

Objetivo: bloquear el acceso correctamente desde backend.

Tareas:

- Formalizar permisos del rol `customer`.
- Crear policies/scopes para portal.
- Validar `tenant_id` en todas las consultas.
- Validar acceso activo en `customer_portal_accesses`.
- Validar paciente asignado en `final_user_patient_assignments`.

Entregable:

- Un usuario final no puede acceder a recursos por URL directa si no tiene
  permiso, acceso activo y asignacion.

### Paso 4: configuracion del tenant en panel web

Objetivo: permitir que el tenant controle si ofrece portal/app movil.

Tareas:

- Pantalla o seccion de configuracion del portal.
- Activar/desactivar portal.
- Activar/desactivar acceso movil.
- Elegir modo inicial: libre, incluido, pagado o deshabilitado.
- Guardar defaults para nuevos customers.

Entregable:

- El tenant puede habilitar o apagar el feature desde su panel.

### Paso 5: gestion de acceso por customer

Objetivo: que el tenant active usuarios finales reales.

Tareas:

- Desde la ficha del customer, activar acceso al portal/app movil.
- Crear o vincular un `user` con rol `customer`.
- Crear `customer_user_links`.
- Crear `customer_portal_accesses` con `billing_mode = free`.
- Enviar invitacion o preparar flujo de acceso.
- Suspender/reactivar acceso.

Entregable:

- Un customer puede tener acceso activo al portal/app movil sin cobro.

### Paso 6: asignacion de pacientes

Objetivo: definir que pacientes ve cada usuario final.

Tareas:

- UI para asignar/desasignar pacientes desde panel web.
- Crear registros en `final_user_patient_assignments`.
- Crear o actualizar checks generales por seccion en
  `animal_portal_visibility_settings`.
- Revocar acceso al desasignar.
- Mostrar indicador interno de pacientes compartidos con usuario final.

Entregable:

- El usuario final solo puede ver pacientes que el tenant le asigno y solo las
  secciones habilitadas para cada mascota.

### Paso 7: API de portal/app movil

Objetivo: exponer los datos necesarios para la app/portal.

Endpoints MVP:

- `GET /api/v1/portal/me`
- `GET /api/v1/portal/patients`
- `GET /api/v1/portal/patients/{patient}`
- `GET /api/v1/portal/patients/{patient}/history`
- `GET /api/v1/portal/patients/{patient}/notes`
- `GET /api/v1/portal/notes/{note}`
- `GET /api/v1/portal/files/{file}/temporary-url`
- `GET /api/v1/portal/notifications`
- `PATCH /api/v1/portal/notifications/{notification}/read`
- `PATCH /api/v1/portal/notifications/read-all`

Entregable:

- La app/portal puede cargar sesion, pacientes, historial, notas, archivos y
  notificaciones basicas.
- Cada endpoint respeta acceso activo, mascota asignada, seccion habilitada e
  item publicado.

### Paso 8: experiencia del usuario final

Objetivo: construir la interfaz minima usable.

Pantallas MVP:

- Login/invitacion.
- Lista de pacientes.
- Ficha de paciente.
- Historial visible.
- Notas y saldos.
- Archivos/videos/radiologia publicados.
- Notificaciones.

Entregable:

- Usuario final puede entrar, ver sus pacientes y consultar informacion
  publicada por el tenant.

### Paso 9: pagos de notas veterinarias con Stripe

Objetivo: permitir pagar notas pendientes. Este pago es distinto al posible
cobro futuro por acceso a la app.

Tareas:

- Endpoint para crear Stripe Checkout/PaymentIntent de una nota.
- Validar acceso activo, paciente asignado y nota visible.
- Webhook Stripe con firma verificada.
- Registrar pago de forma idempotente.
- Actualizar saldo/estado de nota.
- Mostrar confirmacion en app/portal.

Entregable:

- Usuario final puede pagar una nota veterinaria pendiente con tarjeta.

### Paso 10: notificaciones MVP y QA de seguridad

Objetivo: cerrar el MVP con avisos utiles y pruebas de aislamiento.

Notificaciones MVP:

- Nota publicada o pendiente de pago.
- Pago confirmado.
- RX/radiologia publicada.
- Archivo/video publicado.
- Estado de cuenta generado.
- Notificacion marcada como leida para limpiar contador.

QA obligatorio:

- Usuario sin acceso activo no entra.
- Usuario con acceso activo pero sin paciente asignado no ve datos.
- Usuario no puede ver pacientes de otro tenant.
- Usuario no puede abrir nota/archivo no publicado.
- Usuario no puede abrir una seccion deshabilitada de una mascota asignada.
- Webhook Stripe duplicado no duplica pagos.
- Portal deshabilitado por tenant bloquea acceso.
- Notificacion leida desaparece del contador.

Entregable:

- MVP listo para piloto controlado.

### Orden sugerido de implementacion

1. Pasos 1 a 3: base tecnica y seguridad.
2. Pasos 4 a 6: operacion del tenant.
3. Pasos 7 y 8: experiencia del usuario final.
4. Paso 9: pagos de notas.
5. Paso 10: notificaciones y validacion final.

## Riesgos y decisiones pendientes

- Confirmar si "cliente" actual del sistema equivale al usuario final o si se
  requiere una entidad separada.
- Confirmar si el rol existente `customer` se usara como usuario final o si se
  renombrara conceptualmente en UI.
- Confirmar si el acceso al portal se cobra por customer, por usuario final, por
  paciente o por tenant.
- Confirmar si el acceso libre del MVP sera el valor por defecto para todos los
  tenants nuevos.
- Definir si el tenant puede activar automaticamente el acceso a todos sus
  customers o solo manualmente uno por uno.
- Confirmar si un paciente puede estar asignado a multiples usuarios finales.
- Confirmar si una nota puede incluir varios pacientes y como se mostrara.
- Definir si se exponen precios siempre o solo cuando hay nota pendiente.
- Definir moneda e impuestos por tenant.
- Definir si Stripe sera por cuenta plataforma o Stripe Connect.
- Definir si el cobro del portal sera un producto interno del tenant o una
  suscripcion recurrente gestionada por Stripe.
- Definir que archivos de radiologia existen hoy: imagen, PDF, video, DICOM u
  otro formato.
- Definir si el historial visible se controla por nota completa o por items
  individuales.
- Definir politicas de retencion y privacidad de archivos clinicos.
- Definir si las notificaciones del usuario final viven en `tenant_notifications`
  con `audience` o en una tabla separada.

## Criterios de aceptacion iniciales

- Un tenant puede habilitar o deshabilitar el portal/app movil desde su panel.
- Un tenant con `access_mode = free` puede activar acceso a un customer sin
  cobrarle.
- Un tenant con portal/app movil deshabilitado bloquea el acceso de sus usuarios
  finales aunque existan relaciones previas.
- Un usuario con rol `customer` pero sin acceso activo al portal no puede entrar
  al portal.
- Un usuario con acceso activo al portal pero sin pacientes asignados ve una
  pantalla vacia.
- Un usuario final sin asignaciones ve una pantalla vacia y no puede acceder por
  URL directa a ningun paciente.
- Un usuario final con un paciente asignado ve solo ese paciente.
- Al desasignar un paciente, el acceso se revoca.
- Al suspender el acceso comercial del portal, el usuario no puede ver pacientes,
  notas ni archivos aunque conserve su cuenta.
- Una nota no publicada no aparece en portal.
- Una nota pendiente publicada permite iniciar pago.
- Un pago confirmado por webhook cambia la nota a pagada o reduce el saldo.
- Un webhook duplicado de Stripe no duplica pagos.
- Un archivo no publicado no puede verse aunque se conozca su ID.
- Las URLs temporales expiran y no exponen archivos de otros tenants.
- Una notificacion de portal solo aparece para usuarios con acceso al recurso
  relacionado.
