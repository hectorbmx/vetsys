# Customer Notifications Backend Checkpoint

Fecha: 2026-06-16
Rama: `feature/customer-notifications-mvp`

## Alcance ejecutado

- Se centralizo la generacion de notificaciones del portal customer en `PortalNotificationService`.
- Las notas nuevas ahora generan notificacion para el customer desde:
  - Panel web tenant (`Client\NoteController`)
  - API V1 tenant (`Api\V1\NoteController`)
- Los pagos Stripe ya existentes se mantienen conectados a:
  - Notificacion customer en `portal_notifications`
  - Notificacion tenant en `tenant_notifications`
- Los estados de cuenta ya existentes se mantienen conectados a `portal_notifications`.
- Se agregaron notificaciones customer para contenido clinico publicado:
  - Videos de paciente
  - Estudios RX
  - Imagenes RX agregadas a un estudio
  - Cartas de vacunacion
- Se corrigio la deduplicacion de `portal_notifications` para evitar que campos JSON nulos bloqueen notificaciones distintas.

## Reglas actuales

- Solo se notifica a customers con `customer_portal_accesses.status = active`.
- Si el acceso tiene `access_ends_at`, solo se considera valido mientras no haya vencido.
- Para recursos ligados a mascota, se respeta que la mascota este asignada al usuario final y que la seccion correspondiente este visible:
  - `show_notes`
  - `show_videos`
  - `show_radiology`
  - `show_vaccines`
- Las notas sin `animal_id` siguen pudiendo notificar si el customer tiene al menos una mascota asignada.
- Las notificaciones se consumen desde los endpoints existentes:
  - `GET /api/v1/portal/notifications`
  - `PATCH /api/v1/portal/notifications/{notification}/read`
  - `PATCH /api/v1/portal/notifications/read-all`

## Tenant notifications cubiertas

- Expediente compartido desde telemedicina:
  - Web tenant: `Client\TelemedicineController`
  - API V1: `Api\V1\AnimalClinicalMediaController`
- Pago customer recibido:
  - Pago de estado de cuenta customer con Stripe
  - Pago de nota customer con Stripe

## Pendiente para front

- Mostrar campana/lista de notificaciones en la app customer.
- Marcar notificacion como leida al abrirla o con accion explicita.
- Ocultar/limpiar visualmente notificaciones leidas.
- Definir el mapeo final de `url`/`type` hacia rutas Ionic.
- Decidir si el badge se alimenta desde `bootstrap.meta` o desde `GET /notifications?unread_only=1`.

## Pendiente backend posterior

- Push real (FCM/APNs) queda fuera de este bloque.
- Notificacion por plan/acceso customer vencido queda pendiente hasta definir regla de vencimiento y job programado.
- Si se activan permisos por item individual, agregar hooks de publicacion manual para `visible_to_customer`.
