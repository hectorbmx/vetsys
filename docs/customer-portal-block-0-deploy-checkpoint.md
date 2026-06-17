# Checkpoint Bloque 0: cierre MVP customer portal

Fecha: 2026-06-16

## Objetivo del bloque

Cerrar y validar el estado actual del MVP customer portal antes de subir cambios y probar en produccion/staging.

## Estado git revisado

Backend `vetsys`:
- Rama actual: `master`
- Estado: limpio
- Ultimo commit: `643f1d3 agregar feature:ccliente final`
- La rama `feature/customer-portal-mvp` existe, pero `master` ya contiene el feature customer actual.

App `gorozpeApp`:
- Rama actual: `master`
- Estado: limpio
- Ultimo commit: `31c5c47 fix: buscador del home`
- La rama `feature/customer-portal-mvp` existe.
- El arbol actual de `master` contiene las pantallas `src/app/features/customer-portal`.

## Validaciones ejecutadas

Backend:
- `php artisan migrate:status`
  - Migraciones customer portal aplicadas:
    - `2026_06_16_000001_create_customer_portal_foundation_tables`
    - `2026_06_16_000002_add_customer_portal_visibility_fields`
- `php artisan route:list --path=api/v1/portal`
  - 15 rutas portal registradas.
- `php -l app/Http/Controllers/Api/V1/CustomerPortalController.php`
- `php -l app/Services/CustomerPortalAccessService.php`
- `php -l app/Services/PortalNotificationService.php`
- `php -l app/Http/Middleware/EnsureCustomerPortalAccess.php`
- `php artisan view:cache`
- `php artisan view:clear`

App:
- `node .\node_modules\@angular\cli\bin\ng.js build --configuration development`
  - Build correcto.
  - Salida generada en `gorozpeApp/www`.

## Funcionalidad actualmente cubierta

Backend:
- Activacion app/web de customer desde panel tenant.
- Invitacion con link/codigo mostrado en pantalla si falla mail.
- Rol `customer`.
- Bloqueo de customer en panel web tenant.
- API `/api/v1/portal`.
- Bootstrap customer con:
  - customer
  - portal access
  - account summary
  - patients
  - note summaries
  - statement summaries
  - notifications
- Notas del customer visibles aunque no tengan `animal_id`.
- Detalle de nota visible por customer.
- Detalle de mascota y secciones:
  - history
  - notes
  - videos
  - radiology
  - vaccines
- Portal notifications basicas.

App:
- Login customer por rol.
- Redireccion a `/portal`.
- Navegacion customer:
  - `/portal`
  - `/portal/mascotas`
  - `/portal/mascotas/:id`
  - `/portal/historial`
  - `/portal/notas/:id`
  - `/portal/pagos`
- Home con saldo real desde `account_summary`.
- Mascotas.
- Detalle de mascota con tabs internos.
- Historial de notas.
- Detalle de nota.
- Pagos pendientes sin Stripe todavia.

## Pendientes antes de deploy productivo

1. Confirmar si los cambios de `gorozpeApp` deben commitearse o ya estan incluidos en el commit correcto.
2. Confirmar remoto/branch destino para backend y app.
3. Subir cambios.
4. En staging/produccion:
   - correr `php artisan migrate`
   - revisar `.env`
   - revisar mailer real
   - revisar `APP_URL`
   - revisar storage/R2 temporary URLs
   - revisar Stripe Connect tenant
   - revisar plan con `mobile_access`
5. Probar un customer real:
   - activar acceso
   - aceptar invitacion
   - login app
   - confirmar bloqueo web `/client`
   - ver saldo
   - ver notas pagada/pendiente
   - ver detalle de mascota
   - ver RX/videos/vacunas si hay datos

## Siguiente bloque recomendado

Despues de subir y probar el MVP actual:

1. Fase 4 del roadmap: notificaciones customer in-app.
2. Luego Stripe checkout customer.

Documento base:
- `docs/customer-portal-completion-roadmap.md`

Documento de notificaciones general:
- `docs/notifications-live-sync-roadmap.md`
