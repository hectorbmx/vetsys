# Roadmap: Push notifications para customer portal

Fecha: 2026-06-16
Estado: planeacion

## Objetivo

Agregar notificaciones push reales para que el customer reciba avisos del sistema aunque la app este cerrada, en segundo plano o el telefono bloqueado.

El sistema actual ya cubre notificaciones in-app:

- Backend guarda eventos en `portal_notifications`.
- Ionic muestra campana, badge y pantalla `/portal/notificaciones`.
- Customer puede marcar una o todas como leidas.

Este roadmap agrega la capa push sin reemplazar `portal_notifications`. La tabla actual sigue siendo la fuente de verdad; push solo sera el canal de entrega al dispositivo.

## Alcance MVP

Eventos customer iniciales:

- Nota creada.
- Nota pendiente de pago.
- Pago confirmado.
- Estado de cuenta generado.
- Video agregado.
- RX agregado.
- Carta/cartilla de vacunacion agregada.

Comportamiento esperado:

- Si la app esta abierta, la notificacion in-app sigue funcionando.
- Si la app esta cerrada o en segundo plano, el sistema envia push al dispositivo.
- Al tocar la push, la app abre la ruta correcta:
  - Nota: `/portal/notas/{id}`
  - Mascota/contenido clinico: `/portal/mascotas/{id}`
  - Pagos/estado de cuenta: `/portal/pagos`
  - Lista fallback: `/portal/notificaciones`
- Al abrir la app, se reconcilia con `GET /api/v1/portal/notifications`.

## Decision tecnica recomendada

Proveedor: Firebase Cloud Messaging.

Razones:

- Compatible con Android.
- Puede enviar a iOS mediante APNs configurado dentro de Firebase.
- Funciona bien con Capacitor.
- Permite usar el mismo backend para enviar push a multiples dispositivos por customer.

Plugin Ionic/Capacitor recomendado:

- `@capacitor/push-notifications`

Backend recomendado:

- Usar Firebase Admin SDK o HTTP v1 API.
- Enviar push desde backend despues de crear `PortalNotification`.
- Encolar el envio con Jobs para no bloquear la accion del tenant.

## Modelo de datos propuesto

Crear tabla `push_devices` o `customer_push_devices`.

Campos sugeridos:

- `id`
- `tenant_id`
- `user_id`
- `customer_id`
- `platform`: `ios`, `android`, `web`, `unknown`
- `token`
- `device_id` nullable
- `app_version` nullable
- `last_seen_at`
- `revoked_at` nullable
- `created_at`
- `updated_at`

Indices:

- unique por `token`
- index `tenant_id`, `user_id`, `revoked_at`
- index `tenant_id`, `customer_id`

Recomendacion:

- No guardar tokens en `users` ni en `customers`.
- Un customer puede tener varios dispositivos.
- Si Firebase responde token invalido, marcar `revoked_at`.

## Contrato API

Endpoints nuevos customer:

### Registrar dispositivo

`POST /api/v1/portal/push-devices`

Payload:

```json
{
  "token": "fcm-device-token",
  "platform": "android",
  "device_id": "optional-device-id",
  "app_version": "1.0.0"
}
```

Respuesta:

```json
{
  "data": {
    "id": 1,
    "platform": "android",
    "last_seen_at": "2026-06-16T18:00:00Z"
  }
}
```

### Revocar dispositivo

`DELETE /api/v1/portal/push-devices/{device}`

Uso:

- Logout.
- Usuario revoca permisos.
- App detecta token reemplazado.

### Opcional: preferencias

`PATCH /api/v1/portal/notification-preferences`

Queda fuera del MVP. Para inicio se envia push de todos los eventos importantes.

## Payload push recomendado

Payload FCM:

```json
{
  "notification": {
    "title": "Nota pendiente de pago",
    "body": "La nota VT-00010 esta disponible en tu portal."
  },
  "data": {
    "notification_id": "123",
    "type": "portal.note.payment_pending",
    "url": "/portal/notas/10",
    "note_id": "10",
    "animal_id": ""
  }
}
```

Reglas:

- `data` debe usar strings por compatibilidad FCM.
- Nunca enviar datos sensibles clinicos completos en la push.
- La push avisa; el detalle real se consulta por API autenticada.

## Fase 0: Preparacion y credenciales

Objetivo: dejar listo Firebase sin tocar aun el flujo funcional.

Tareas:

- Crear proyecto Firebase.
- Registrar app Android.
- Registrar app iOS si aplica.
- Descargar `google-services.json` para Android.
- Configurar APNs en Firebase para iOS.
- Definir variables backend:
  - `FCM_PROJECT_ID`
  - `FCM_CREDENTIALS_PATH` o JSON seguro.
- Confirmar package id de Capacitor.

Criterios de aceptacion:

- Firebase tiene app Android registrada.
- Credenciales no quedan versionadas si contienen secretos.
- Se documenta donde colocar credenciales en local/prod.

## Fase 1: Backend device tokens

Objetivo: que backend pueda guardar y revocar tokens por customer.

Tareas:

- Crear migracion `push_devices`.
- Crear modelo `PushDevice`.
- Crear controller API portal.
- Crear rutas:
  - `POST /api/v1/portal/push-devices`
  - `DELETE /api/v1/portal/push-devices/{device}`
- Validar que el device pertenece al `customer_portal_access` actual.
- Actualizar `last_seen_at` si el token ya existe.

Criterios de aceptacion:

- Customer autenticado puede registrar token.
- Customer no puede revocar tokens de otro usuario/customer.
- Logout puede revocar el token actual.
- Registro repetido no duplica tokens.

## Fase 2: Backend sender service

Objetivo: enviar push cuando se cree una `PortalNotification`.

Tareas:

- Crear `PushNotificationService`.
- Crear `SendPortalPushNotification` Job.
- Conectar envio desde `PortalNotificationService::createOnce`.
- Buscar tokens activos del mismo `tenant_id`, `customer_id`, `user_id`.
- Enviar titulo/body/data desde `PortalNotification`.
- Marcar tokens invalidos como revocados.
- Registrar errores sin romper la creacion de la notificacion in-app.

Criterios de aceptacion:

- Crear una nota genera `portal_notifications` y dispara Job push.
- Si no hay dispositivos, no falla.
- Si FCM falla, la notificacion in-app queda creada.
- Token invalido se marca como revocado.

## Fase 3: Ionic permisos y registro

Objetivo: que la app pida permiso y registre token FCM.

Tareas:

- Instalar/configurar `@capacitor/push-notifications`.
- Al iniciar sesion customer:
  - Solicitar permiso de push.
  - Registrar token.
  - Enviar token a `POST /portal/push-devices`.
- Guardar token local para revocarlo en logout.
- Manejar renovacion de token.
- No pedir permisos si no es rol `customer`.

Criterios de aceptacion:

- En Android fisico/emulador con servicios Google, se obtiene token.
- Backend recibe y guarda token.
- Si el usuario niega permiso, la app sigue funcionando con in-app notifications.
- Logout revoca token si existe.

## Fase 4: Deep links y manejo de tap

Objetivo: abrir la pantalla correcta cuando el customer toca la push.

Tareas:

- Escuchar evento `pushNotificationActionPerformed`.
- Leer `data.url`, `note_id`, `animal_id`, `type`.
- Navegar usando las mismas reglas de `/portal/notificaciones`.
- Si la app abre fria, esperar sesion/bootstrap antes de navegar.
- Fallback a `/portal/notificaciones`.

Criterios de aceptacion:

- Push de nota abre detalle de nota.
- Push de RX/video/vacuna abre detalle de mascota.
- Push de pago/estado abre pagos.
- Si la sesion expiro, app manda a login y despues puede recuperar notificaciones por API.

## Fase 5: QA local/staging

Objetivo: validar con dispositivos reales antes de produccion.

Casos:

- App abierta.
- App en background.
- App cerrada.
- Telefono bloqueado.
- Logout y login de otro customer en el mismo dispositivo.
- Customer con dos dispositivos.
- Token invalido/reinstalacion.
- Tenant crea nota para customer sin acceso activo.
- Customer con acceso suspendido o vencido.

Criterios de aceptacion:

- No llegan pushes a customers suspendidos.
- No hay fuga cross-tenant.
- No hay duplicados graves.
- La notificacion in-app aparece aunque el push falle.

## Fase 6: Produccion y monitoreo

Objetivo: operar push sin afectar el portal.

Tareas:

- Configurar worker de queues.
- Configurar credenciales Firebase en servidor.
- Loggear envios fallidos.
- Agregar metricas basicas:
  - pushes enviados
  - pushes fallidos
  - tokens revocados
- Definir retencion/limpieza de tokens antiguos.

Criterios de aceptacion:

- Queue worker activo.
- Errores FCM visibles en logs.
- Tokens invalidos se limpian gradualmente.

## Riesgos

- iOS requiere configuracion APNs y pruebas en dispositivo real.
- Emuladores sin Google Play Services no reciben FCM.
- Push puede llegar tarde o no llegar segun sistema operativo/bateria.
- No se debe confiar en la push como fuente de verdad.
- El usuario puede negar permisos.
- Si se envia informacion clinica sensible en el body, puede verse en pantalla bloqueada.

## Recomendacion de ejecucion

Orden sugerido:

1. Fase 0: Firebase y credenciales.
2. Fase 1: backend tokens.
3. Fase 2: backend sender con job.
4. Fase 3: Ionic permisos/registro.
5. Fase 4: deep links.
6. Fase 5: QA en dispositivo real.
7. Fase 6: produccion.

## Estimacion

- Backend tokens + sender: 1.5 a 2.5 dias.
- Ionic permisos + deep links: 1 a 2 dias.
- Firebase/iOS/QA real: 1 a 2 dias adicionales.

Total MVP push: 3.5 a 6.5 dias, dependiendo de iOS y credenciales.

## Fuera de alcance MVP

- Preferencias granulares por tipo de notificacion.
- Horarios silenciosos.
- Push para tenant/staff.
- Web push en navegador.
- Live WebSocket/SSE.
- Analitica avanzada de entrega/apertura.
