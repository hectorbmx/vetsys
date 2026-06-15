# Roadmap: notificaciones live y sincronizacion con app movil

## Objetivo

Convertir el sistema actual de notificaciones en una experiencia sincronizada
entre panel web y app movil, con actualizacion casi en tiempo real, manteniendo
`tenant_notifications` como fuente de verdad.

## Estado actual

- Ya existe tabla `tenant_notifications`.
- El panel cliente muestra ultimas notificaciones y contador sin leer desde
  `AppServiceProvider`.
- El panel cliente permite listar, abrir, marcar como leida y eliminar
  notificaciones.
- La API movil ya expone:
  - `GET /api/v1/notifications`
  - `PATCH /api/v1/notifications/{notification}/read`
- La app movil ya tiene autenticacion Sanctum y middleware de tenant.
- El contrato movil actual es principalmente pull/polling:
  - `GET /api/v1/mobile/bootstrap`
  - `GET /api/v1/notifications`
  - `POST /api/v1/sync/push`
- Laravel broadcasting existe en configuracion base, pero no esta activado para
  notificaciones del tenant.

## Aclaracion sobre webhook vs live

Para una app movil, un "webhook" puro no resuelve recibir eventos live, porque
el telefono no expone una URL publica estable para que el servidor le pegue.

La arquitectura recomendada es:

- Webhook: para recibir eventos externos en backend, por ejemplo pagos,
  integraciones o sistemas de terceros.
- WebSocket o SSE: para actualizar en vivo mientras la app esta abierta.
- Push notification: para avisar cuando la app esta en segundo plano o cerrada.
- Pull incremental: para reconciliar datos al abrir app o recuperar conexion.

## Requerimientos funcionales

1. Crear notificaciones desde eventos relevantes del sistema.
2. Entregar una notificacion live al panel web y a la app movil cuando el usuario
   este conectado.
3. Mantener contador de no leidas sincronizado.
4. Marcar como leida desde web o movil y reflejarlo en el otro cliente.
5. Permitir que la app recupere notificaciones perdidas al reconectar.
6. Evitar duplicados entre eventos live y pull incremental.
7. Respetar aislamiento por tenant.
8. Soportar notificaciones dirigidas a todo el tenant o a un usuario especifico.

## Requerimientos tecnicos

### Backend

- Mantener `tenant_notifications` como tabla principal.
- Agregar un servicio central para crear notificaciones, por ejemplo
  `TenantNotificationService`.
- Emitir eventos al crear o actualizar notificaciones:
  - `TenantNotificationCreated`
  - `TenantNotificationRead`
  - `TenantNotificationDeleted`
- Crear canales privados:
  - `private-tenant.{tenantId}.notifications`
  - opcional: `private-user.{userId}.notifications`
- Autorizar canales por `tenant_id` y usuario autenticado.
- Definir payload estable para web y movil.
- Agregar endpoints de reconciliacion:
  - `GET /api/v1/notifications?since=<iso-date>`
  - `PATCH /api/v1/notifications/read-all`
  - opcional: `DELETE /api/v1/notifications/{notification}`

### App movil

- Conectarse al canal live cuando la sesion este activa.
- Procesar eventos idempotentemente usando `notification.id`.
- Actualizar contador local sin recargar toda la lista.
- Al reconectar, ejecutar pull incremental con `since`.
- En segundo plano, depender de push notification si se implementa.

### Web

- Conectar Echo/Reverb/Pusher en layout cliente.
- Actualizar dropdown y contador al recibir eventos.
- Refrescar estado de leida cuando una notificacion se marque desde otro
  dispositivo.

## Fase 1: auditoria y contrato

Objetivo: cerrar el contrato antes de tocar UI.

Tareas:

- Inventariar todos los puntos que crean `TenantNotification::create`.
- Definir tipos oficiales de notificacion.
- Definir payload unico:

```json
{
  "id": 123,
  "tenant_id": 10,
  "user_id": null,
  "type": "inventory.low",
  "title": "Inventario bajo",
  "body": "Producto con pocas unidades.",
  "url": "/client/servicios",
  "data": {},
  "read_at": null,
  "created_at": "2026-06-15T18:00:00Z"
}
```

Archivos estimados: 1 a 3.

## Fase 2: servicio central de notificaciones

Objetivo: dejar de crear notificaciones a mano en varios lugares.

Tareas:

- Crear `TenantNotificationService`.
- Mover la creacion de notificaciones al servicio.
- Mantener compatibilidad con los registros existentes.
- Agregar pruebas para:
  - tenant correcto
  - usuario especifico opcional
  - payload `data`
  - contador sin leer

Archivos estimados: 4 a 8.

## Fase 3: API movil incremental

Objetivo: que la app pueda reconciliar sin depender del live.

Tareas:

- Agregar `since` a `GET /api/v1/notifications`.
- Devolver `server_time`.
- Agregar paginacion/cursor si hace falta.
- Agregar endpoint `read-all`.
- Confirmar que `markRead` mantiene aislamiento por tenant.

Archivos estimados: 3 a 5.

## Fase 4: broadcasting live

Objetivo: entregar eventos en vivo cuando web/app estan abiertos.

Tareas:

- Elegir driver:
  - Laravel Reverb si se quiere controlar infraestructura.
  - Pusher/Ably si se quiere servicio administrado.
- Configurar variables de entorno.
- Crear eventos broadcast:
  - `TenantNotificationCreated`
  - `TenantNotificationRead`
  - `TenantNotificationDeleted`
- Registrar canales privados en `routes/channels.php`.
- Probar autorizacion por tenant.

Archivos estimados: 6 a 10.

## Fase 5: cliente web live

Objetivo: que el panel web actualice dropdown y contador sin refresh.

Tareas:

- Activar Laravel Echo en `resources/js/bootstrap.js`.
- Crear modulo JS de notificaciones.
- Escuchar canal del tenant.
- Insertar nuevas notificaciones en el dropdown.
- Actualizar contador sin leer.
- Marcar como leida de forma optimista y reconciliar si falla.

Archivos estimados: 3 a 6.

## Fase 6: app movil live

Objetivo: que la app movil quede sincronizada mientras esta abierta.

Tareas:

- Agregar cliente WebSocket compatible con el proveedor elegido.
- Autenticar canal privado con token Sanctum.
- Mantener store local de notificaciones.
- Deduplicar por `id`.
- Reconciliar con `GET /notifications?since=` al reconectar.

Archivos estimados en backend: 1 a 3.
Archivos estimados en app movil: depende de la arquitectura de la app.

## Fase 7: push notifications

Objetivo: avisar cuando la app esta cerrada o en segundo plano.

Tareas:

- Elegir proveedor:
  - Firebase Cloud Messaging.
  - Expo Push Notifications si la app usa Expo.
  - APNs/FCM directo si es nativo.
- Crear tabla de dispositivos/tokens push.
- Agregar endpoints:
  - `POST /api/v1/devices`
  - `DELETE /api/v1/devices/{device}`
- Enviar push al crear notificacion.
- Respetar preferencias futuras de usuario.

Archivos estimados backend: 6 a 10.

## Riesgos y decisiones pendientes

- Definir si "live" sera WebSocket, SSE o polling corto.
- Confirmar stack de la app movil para elegir libreria compatible.
- Confirmar si se necesita push en segundo plano desde la primera version.
- Evitar exponer notificaciones entre tenants en canales privados.
- Evitar duplicados cuando la app recibe evento live y luego hace pull.
- Decidir retencion de notificaciones antiguas.

## Recomendacion de MVP

MVP recomendado en orden:

1. Fase 1: contrato.
2. Fase 2: servicio central.
3. Fase 3: API incremental.
4. Fase 4: broadcasting.
5. Fase 5: web live.
6. Fase 6: app live.

Dejar push notifications como segunda iteracion si el objetivo inicial es ver
cambios live con la app abierta.

## Estimacion general

- MVP live con app abierta: 3 a 5 dias de trabajo.
- Live + push en segundo plano: 5 a 8 dias.
- Sistema completo con preferencias, auditoria y retencion: 8 a 12 dias.
