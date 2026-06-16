# Roadmap para culminar Customer Portal

## Estado actual

El MVP del portal customer ya tiene la base principal en backend y app Ionic.

Backend `vetsys`:
- Customer puede activarse desde el panel tenant.
- Se crea/vincula un `User` con rol `customer`.
- Customer no puede entrar al panel web tenant.
- Customer puede autenticarse en la app por API.
- Existe API `/api/v1/portal`.
- El portal entrega bootstrap con customer, pacientes, notas, estado contable, estados de cuenta y notificaciones.
- Las mascotas se asignan al customer mediante `final_user_patient_assignments`.
- El acceso granular por mascota/seccion existe mediante `animal_portal_visibility_settings`.
- El customer ve notas/saldos por customer, aunque la nota no tenga mascota ligada.

App `gorozpeApp`:
- Login detecta rol `customer`.
- Customer entra a `/portal`.
- Home customer lista resumen, saldo, mascotas y actividad.
- Navegacion customer: Inicio, Mascotas, Historial, Pagos.
- Historial muestra notas y estatus.
- Pagos muestra notas pendientes y estados de cuenta.
- Detalle de nota carga datos reales.
- Detalle de mascota carga perfil y tabs internos: Perfil, Notas, Historial, Videos, RX, Vacunas.

## Objetivo final

Cerrar un portal customer estable donde el cliente final pueda:
- Activar su cuenta.
- Entrar a la app.
- Ver sus mascotas.
- Ver perfil, historial, notas, videos, RX y vacunas permitidas.
- Ver saldo y estados de cuenta.
- Pagar notas pendientes con Stripe.
- Recibir y gestionar notificaciones.
- Opcionalmente usar portal web customer en una fase posterior.

## Fase 1: Estabilizacion MVP en produccion

### Alcance

- Subir backend y app a ramas remotas.
- Probar migraciones en staging/produccion.
- Validar login customer.
- Validar bloqueo web del customer.
- Validar que customer solo ve sus mascotas asignadas.
- Validar que saldos y notas coinciden con panel tenant.
- Validar URLs temporales de archivos, RX, videos y PDFs.

### Criterios de aceptacion

- Un customer activado no puede entrar a `/client`.
- Un customer activado puede entrar a la app.
- Home muestra saldo real del customer.
- Historial muestra notas pagadas y pendientes.
- Detalle de nota muestra items y pagos aplicados.
- Detalle de mascota muestra tabs disponibles segun configuracion.
- Si una seccion no tiene datos, muestra estado vacio claro.

### Riesgos

- Configuracion de mail/invitaciones en produccion.
- Plan del tenant sin `mobile_access`.
- URLs temporales de storage/R2 mal configuradas.
- Customers existentes con visibilidad vieja apagada.
- Notas antiguas sin `animal_id` en detalles.

## Fase 2: Pagos Stripe customer

### Alcance

- Crear checkout desde detalle de nota pendiente.
- Crear checkout desde pantalla Pagos.
- Definir si `Pagar Ahora` paga una nota especifica o saldo general.
- Agregar return/cancel URLs compatibles con app.
- Refrescar bootstrap despues del pago.
- Mostrar estado de pago confirmado.

### Endpoints esperados

- `POST /api/v1/portal/notes/{note}/checkout`
- Opcional: `POST /api/v1/portal/statements/checkout`
- Opcional: `POST /api/v1/portal/balance/checkout`

### Criterios de aceptacion

- Customer solo puede pagar notas propias.
- No puede pagar notas canceladas o ya pagadas.
- Checkout usa cuenta Stripe del tenant.
- Webhook actualiza pagos y saldo.
- App refleja saldo actualizado despues del pago.
- Se genera notificacion de pago confirmado.

## Fase 3: Detalle avanzado de mascota

### Alcance

- Mejorar tabs internos de mascota.
- Separar componentes si la pantalla crece demasiado.
- Agregar refresh por seccion.
- Mejorar viewer de RX.
- Mejorar viewer de videos.
- Agregar descarga/ver PDF de vacunas.
- Mostrar historial agrupado por fecha/tipo.

### Criterios de aceptacion

- Cada tab carga de forma independiente.
- RX abre imagen temporal segura.
- Videos abren URL temporal segura.
- Vacunas abren PDF o imagen.
- Historial se entiende como linea de tiempo.
- Notas de mascota abren detalle.

## Fase 4: Notificaciones customer

### Alcance

- Crear pantalla de notificaciones.
- Mostrar badge global.
- Marcar una como leida.
- Marcar todas como leidas.
- Definir eventos finales.

### Eventos iniciales

- Nota creada.
- Nota pendiente de pago.
- Servicio realizado.
- RX agregado.
- Video agregado.
- Vacuna/cartilla agregada.
- Estado de cuenta generado.
- Pago confirmado.

### Criterios de aceptacion

- Las notificaciones leidas dejan de acumularse como pendientes.
- Customer solo ve notificaciones propias.
- Tap en notificacion navega al recurso correcto.
- Notificaciones antiguas no bloquean el uso de la app.

## Fase 5: Configuracion tenant

### Alcance

- Ajustar pantalla tenant para portal customer.
- Reenviar invitacion.
- Regenerar link de invitacion.
- Ver estado de activacion del customer.
- Ver ultimo acceso.
- Configurar secciones por mascota.
- Configurar default de secciones para nuevos customers.
- Toggle global de portal customer por tenant.
- Preparar cobro opcional por acceso en fase posterior.

### Criterios de aceptacion

- Tenant sabe si el customer ya activo su cuenta.
- Tenant puede reenviar invitacion sin soporte tecnico.
- Tenant puede suspender acceso.
- Tenant puede reactivar acceso.
- Tenant puede limitar secciones por mascota.

## Fase 6: Cobro opcional por acceso al portal

### Decision pendiente

El tenant podra elegir si cobra o no el acceso al portal/app customer.

Para MVP se recomienda mantener acceso gratis, pero dejar modelo preparado.

### Opciones

1. Cobro por tenant al customer final.
2. Cobro por customer activo.
3. Cobro por paquete de customers.
4. Cobro solo por funcionalidades premium.

### Entidades candidatas

- Mantener `customer_portal_accesses.billing_mode`.
- Agregar estado de suscripcion por customer si se requiere.
- Evitar mezclar esto directamente en `customers` si habra historial de cobros.

### Criterios de aceptacion futuros

- Tenant puede activar/desactivar cobro.
- Customer sabe si su acceso requiere pago.
- Customer puede pagar acceso si aplica.
- Acceso vencido suspende portal sin borrar datos.

## Fase 7: Portal web customer

### Decision pendiente

Hay dos caminos:

1. Usar Ionic responsive como portal web customer.
2. Crear Blade/Laravel separado de `/client`.

Recomendacion actual:
- Reutilizar Ionic responsive primero.
- Nunca meter customer dentro de `/client`.
- Si se hace web Laravel, usar rutas separadas como `/customer` o `/portal-web`.

### Criterios de aceptacion

- Customer web no accede al panel tenant.
- Mismo contrato API que app.
- Layout desktop con sidebar/dashboard.
- Login/redirect claro para customer.

## Fase 8: Seguridad, auditoria y calidad

### Alcance

- Rate limits especificos para portal.
- Auditoria de accesos customer.
- Logs de recursos abiertos.
- Tests de permisos por tenant/customer.
- Tests de que customer no entra a `/client`.
- Tests de notas sin `animal_id`.
- Tests de visibilidad por mascota.

### Criterios de aceptacion

- No hay fuga cross-tenant.
- No hay fuga entre customers del mismo tenant.
- URLs temporales no exponen archivos permanentes.
- Login customer y login tenant no se mezclan.

## Proximo bloque recomendado

Al iniciar la siguiente sesion:

1. Revisar `git status` en `vetsys` y `gorozpeApp`.
2. Preparar commits separados:
   - Backend customer portal MVP.
   - Ionic customer portal MVP.
3. Subir ramas.
4. Probar en staging/produccion.
5. Iniciar Fase 2: Stripe checkout customer.

## Checklist de deploy

Backend:
- `php artisan migrate`
- `php artisan route:list --path=api/v1/portal`
- Revisar `APP_URL`.
- Revisar `SANCTUM_STATEFUL_DOMAINS` si aplica.
- Revisar mailer real o log.
- Revisar storage/R2 temporary URLs.
- Revisar Stripe tenant.

App:
- Confirmar `environment.apiUrl`.
- Build correcto.
- Login customer.
- Refresh bootstrap.
- Navegacion customer.
- Detalle de nota.
- Detalle de mascota.

## Notas de producto

- Para customer, el portal debe sentirse de lectura y pago.
- No debe permitir editar datos clinicos.
- El tenant conserva control sobre activacion, suspension y visibilidad.
- El cobro por acceso debe permanecer opcional por tenant.
- El contrato API debe seguir siendo la fuente comun para app y futuro portal web.
