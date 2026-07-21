# Roadmap - Sincronizacion mobile con modo monthly_cutoff

Fecha de creacion: 2026-07-15

Repos involucrados:

- Backend/web: `vetsys`
- App movil Ionic: `gorozpeApp`

Roadmap relacionado:

- `vetsys/docs/road_map_ajustes_notas.md`

## Objetivo

Sincronizar la app movil con el modo de cobranza configurado en web.

La app movil no debe mostrar selector de modo. El selector vive solo en web, en la configuracion del tenant. La app debe leer el modo activo desde backend y adaptar su experiencia:

- `note_based`: conservar el comportamiento actual por notas.
- `monthly_cutoff`: tratar notas como respaldo operativo y cobrar por cuenta global del cliente mediante abonos.

## Reglas de producto

- La fuente de verdad es `tenants.billing_mode`.
- La app movil no permite cambiar `billing_mode`.
- Si el tenant usa `monthly_cutoff`, la app no debe presentar el pago por nota como camino principal.
- En `monthly_cutoff`, los pagos se registran como abonos globales por `customer_id`.
- En `monthly_cutoff`, las notas siguen existiendo porque alimentan cargos, historial, inventario y trazabilidad.
- Los cortes mensuales son informativos y operativos; no reciben pagos directos.
- Para v1 movil, no se implementa generacion ni recalculo de cortes desde la app.

## Estado inicial detectado

Backend:

- `Tenant` ya tiene `billing_mode`, `usesNoteBasedBilling()` y `usesMonthlyCutoffBilling()`.
- Web ya usa `monthly_cutoff` en perfil de cliente, cortes, PDFs y abonos globales.
- Web ya oculta o bloquea pagos por nota cuando el tenant usa `monthly_cutoff`.
- API movil aun no expone `billing_mode` en `AuthController::serializeUser()`.
- `/mobile/bootstrap` reutiliza `serializeUser()`, por lo que puede recibir el modo sin crear endpoint nuevo.
- API movil de notas todavia permite llamar pagos por nota si no se blinda.

App movil:

- `ApiTenant` no tiene `billing_mode`.
- Pantalla cliente usa lenguaje de notas/pagos.
- Pantalla detalle de nota permite pago manual y link de pago por nota.
- Registro de pago por cliente ya usa `/payments` con `customer_id`, que es compatible con abonos globales.

## Fase 1 - Contrato backend mobile

Objetivo:

Exponer el modo de cobranza del tenant a la app movil sin crear selector ni flujo nuevo.

Tareas:

- [x] Agregar `billing_mode` normalizado a `AuthController::serializeUser()`.
- [x] Confirmar que `/api/v1/auth/login` devuelve `user.tenant.billing_mode`.
- [x] Confirmar que `/api/v1/auth/me` devuelve `user.tenant.billing_mode`.
- [x] Confirmar que `/api/v1/mobile/bootstrap` devuelve `user.tenant.billing_mode`.
- [x] Usar `normalizedBillingMode()` para tolerar datos antiguos como `monthly` o `monthly_based`.
- [x] Documentar que valores validos para mobile son:
  - `note_based`
  - `monthly_cutoff`

Checkpoint 1:

- [x] Backend expone `billing_mode` de forma estable en login, me y bootstrap.
- [x] No hay cambio visual todavia en app movil.
- [x] `note_based` sigue siendo default si el valor esta vacio o invalido.

Validacion sugerida:

- [x] `php artisan route:list --path=api/v1`
- [x] Prueba HTTP o tinker para usuario tenant `note_based`.
- [x] Prueba HTTP o tinker para usuario tenant `monthly_cutoff`.

## Fase 2 - Tipos y helper de modo en gorozpeApp

Objetivo:

Hacer que la app movil pueda consultar el modo activo desde el bootstrap/session sin duplicar reglas.

Tareas:

- [x] Agregar `billing_mode: 'note_based' | 'monthly_cutoff' | string` a `ApiTenant`.
- [x] Crear helper/computed para identificar `usesMonthlyCutoffBilling`.
- [ ] Reutilizar ese helper en pantallas de cliente, notas y pagos.
- [x] Evitar strings sueltos repetidos en cada pantalla.
- [x] Confirmar que el valor sobrevive en `SessionStorageService`.
- [x] Confirmar que el valor se actualiza al refrescar `/mobile/bootstrap`.

Checkpoint 2:

- [x] App compila con el nuevo campo.
- [x] El modo puede consultarse desde componentes sin pedir endpoint extra.
- [x] No hay selector de modo en UI movil.

Validacion sugerida:

- [x] Build Angular con runtime Node bundled: `node node_modules/@angular/cli/bin/ng.js build`
- [ ] Login con tenant `note_based`.
- [ ] Login con tenant `monthly_cutoff`.

## Fase 3 - Pantalla de cliente adaptada por modo

Objetivo:

La pantalla del cliente debe conservar el flujo actual en `note_based` y sentirse como cuenta/abonos en `monthly_cutoff`.

Archivos candidatos:

- `gorozpeApp/src/app/features/customers/customer-detail/customer-detail.page.ts`
- `gorozpeApp/src/app/features/customers/customer-detail/customer-detail.page.html`
- `gorozpeApp/src/app/features/customers/customer-detail/customer-detail.page.scss`

Tareas para `note_based`:

- [x] Conservar copy de notas, pagos y saldos por nota.
- [x] Conservar boton para nueva nota.
- [x] Conservar preview/distribucion del pago sobre notas.
- [x] Conservar navegacion a notas del cliente.

Tareas para `monthly_cutoff`:

- [x] Cambiar labels principales de cobranza a cuenta, balance, cargos y abonos.
- [x] Mantener boton de registrar pago como `Registrar abono`.
- [x] Reutilizar `/payments` con `customer_id` para abono global.
- [x] Cambiar mensaje de exito a `Abono registrado correctamente`.
- [x] Ocultar o degradar la lista de notas como cobranza principal.
- [x] Si se muestran notas, tratarlas como cargos/servicios o respaldo operativo.
- [x] Ocultar preview de distribucion por nota o presentarlo solo como informacion tecnica si se decide conservarlo.
- [x] Mantener saldo global del customer como indicador principal.

Checkpoint 3:

- [x] En `note_based`, la pantalla se ve y funciona igual que antes.
- [x] En `monthly_cutoff`, el usuario no siente que esta pagando una nota especifica.
- [x] El abono global se registra desde la app.
- [x] El balance del cliente se refresca despues del abono.

Validacion sugerida:

- [ ] Crear abono en tenant `monthly_cutoff`.
- [ ] Confirmar que el backend guarda `payment.customer_id`.
- [x] Confirmar que no se obliga a seleccionar nota.
- [x] Confirmar que tenant `note_based` conserva la distribucion por notas.

## Barrido post-Fase 3 - Superficies pendientes de notas en monthly_cutoff

Fecha de barrido: 2026-07-15

Objetivo:

Ocultar o desactivar en `monthly_cutoff` toda superficie movil donde la nota aparezca como documento de cobro o navegacion principal. Las notas no se borran ni se eliminan del modelo; quedan como respaldo operativo y fuente tecnica de cargos/servicios.

Regla de ejecucion:

- En `note_based`, no cambiar comportamiento.
- En `monthly_cutoff`, no mostrar pagos por nota.
- En `monthly_cutoff`, el unico pago permitido desde mobile es abono directo a la cuenta del cliente.
- En `monthly_cutoff`, los historiales deben mostrar servicios/cargos sueltos, no folios de nota como protagonista.

### Hallazgo A - Detalle de nota sigue cobrando directo

Ruta revisada:

- `/tabs/notas/235`

Archivos:

- `gorozpeApp/src/app/features/notes/note-detail/note-detail.page.ts`
- `gorozpeApp/src/app/features/notes/note-detail/note-detail.page.html`
- `gorozpeApp/src/app/features/notes/note-detail/note-detail.page.scss`

Problema:

- Muestra bloque `Cobro`.
- Permite `Pagar` contra `/notes/{id}/manual-payment`.
- Permite `Generar link` contra `/notes/{id}/payment-links`.

Plan de ejecucion:

- [x] Inyectar `BillingModeService` en `NoteDetailPage`.
- [x] En `monthly_cutoff`, ocultar bloque `Cobro`.
- [x] En `monthly_cutoff`, mostrar aviso de respaldo operativo:
  - `Esta nota forma parte de la cuenta mensual del cliente.`
  - `Los pagos se registran como abonos desde la cuenta del cliente.`
- [x] En `monthly_cutoff`, agregar CTA para volver al cliente y registrar abono global.
- [x] En `monthly_cutoff`, mantener detalle de servicios, fecha, cliente y pagos historicos como lectura.
- [x] En `note_based`, conservar pago manual y link de nota.

Checkpoint A:

- [x] `/tabs/notas/:id` no permite pagar nota en `monthly_cutoff`.
- [x] `/tabs/notas/:id` conserva cobro por nota en `note_based`.

### Hallazgo B - Tab global `/tabs/pagos` lista notas

Ruta revisada:

- `/tabs/pagos`

Archivos:

- `gorozpeApp/src/app/features/payments/payments.page.ts`
- `gorozpeApp/src/app/features/payments/payments.page.html`
- `gorozpeApp/src/app/features/payments/payments.page.scss`
- `gorozpeApp/src/app/tabs/tabs.page.html`

Problema:

- El tab inferior dice `Notas`.
- La pantalla lista notas locales.
- Tiene boton `Nueva nota`.
- Cada row navega a `/tabs/notas/:id`.

Plan de ejecucion:

- [x] Inyectar `BillingModeService` en `TabsPage` o exponer estado equivalente para el tabbar.
- [x] En `monthly_cutoff`, ocultar o deshabilitar el tab `Notas` (`/tabs/pagos`) sin borrar ruta ni componente.
- [x] En `monthly_cutoff`, si el usuario entra directo a `/tabs/pagos`, mostrar pantalla bloqueada con mensaje:
  - `En modo cuenta mensual, consulta cargos desde el cliente o registra abonos en la cuenta.`
- [x] En `monthly_cutoff`, ocultar boton `Nueva nota` dentro de `PaymentsPage`.
- [x] En `monthly_cutoff`, evitar navegacion a `/tabs/notas/:id` desde esa pantalla.
- [x] En `note_based`, conservar la pantalla actual.

Checkpoint B:

- [x] Tab `Notas` no aparece o queda no operativo en `monthly_cutoff`.
- [x] `/tabs/pagos` no funciona como listado de notas cobrables en `monthly_cutoff`.
- [x] `note_based` conserva el tab y listado actual.

### Hallazgo C - Historial de mascota navega a notas

Ruta revisada:

- `/tabs/mascotas/3181`, tab `Historial`

Archivos:

- `gorozpeApp/src/app/features/animals/animal-detail/animal-detail.page.ts`
- `gorozpeApp/src/app/features/animals/animal-detail/animal-detail.page.html`
- `gorozpeApp/src/app/features/animals/animal-detail/animal-detail.page.scss`

Problema:

- `serviceHistory()` ya agrupa servicios desde `note.details`, pero cada row sigue abriendo la nota.
- Muestra folio de nota en el subtitulo.

Plan de ejecucion:

- [x] Inyectar `BillingModeService` en `AnimalDetailPage`.
- [x] En `monthly_cutoff`, renderizar filas de historial como articulos no clicables.
- [x] En `monthly_cutoff`, ocultar folio como dato principal; mostrar servicio, fecha e importe.
- [x] En `monthly_cutoff`, quitar icono/flecha de navegacion a nota.
- [x] En `note_based`, conservar click a nota y folio.

Checkpoint C:

- [x] Historial de mascota muestra servicios sueltos en `monthly_cutoff`.
- [x] Historial de mascota no navega a notas en `monthly_cutoff`.
- [x] `note_based` conserva historial actual con links a nota.

### Hallazgo D - Detalle de cliente todavia enlaza notas

Ruta revisada:

- `/tabs/clientes/2015`

Archivos:

- `gorozpeApp/src/app/features/customers/customer-detail/customer-detail.page.ts`
- `gorozpeApp/src/app/features/customers/customer-detail/customer-detail.page.html`

Problema:

- La accion rapida `Nueva nota` sigue visible.
- `Cargos recientes` sigue navegando a `/tabs/notas/:id`.
- `Ver todas` abre `/tabs/clientes/:id/notas`.

Plan de ejecucion:

- [x] En `monthly_cutoff`, ocultar accion rapida `Nueva nota`.
- [x] En `monthly_cutoff`, convertir filas de `Cargos recientes` en filas no clicables o abrir solo lectura si se decide mantener detalle tecnico.
- [x] En `monthly_cutoff`, ocultar `Ver todas` hacia notas.
- [x] En `monthly_cutoff`, mantener `Registrar abono` como accion de cobranza principal.
- [x] En `note_based`, conservar acciones actuales.

Checkpoint D:

- [x] Detalle de cliente no dirige a notas como camino principal en `monthly_cutoff`.
- [x] Detalle de cliente conserva cobro por cuenta/abonos.

### Hallazgo E - Historial de notas por cliente

Ruta revisada:

- `/tabs/clientes/:id/notas`

Archivos:

- `gorozpeApp/src/app/features/customers/customer-notes/customer-notes.page.ts`
- `gorozpeApp/src/app/features/customers/customer-notes/customer-notes.page.html`
- `gorozpeApp/src/app/features/customers/customer-notes/customer-notes.page.scss`

Problema:

- Pantalla completa de notas por cliente.
- Cada row navega a detalle de nota.
- El copy habla de notas.

Plan de ejecucion:

- [x] En `monthly_cutoff`, no usar esta pantalla como listado de notas.
- [x] Opcion recomendada v1: bloquearla con mensaje y CTA a detalle de cliente.
- [ ] Opcion fase posterior: transformarla en historial de cargos sueltos por `note_details`.
- [x] En `note_based`, conservar pantalla actual.

Checkpoint E:

- [x] `/tabs/clientes/:id/notas` no expone notas cobrables en `monthly_cutoff`.
- [x] `note_based` conserva historial de notas por cliente.

### Hallazgo F - Home aun promueve notas

Ruta revisada:

- `/tabs/home`

Archivos:

- `gorozpeApp/src/app/tab1/tab1.page.ts`
- `gorozpeApp/src/app/tab1/tab1.page.html`

Problema:

- Accion rapida `Nueva Nota`.
- `Ver todos` navega a `/tabs/pagos`.
- Ultimos servicios abre `/tabs/notas/:id`.

Plan de ejecucion:

- [x] Inyectar `BillingModeService` en `Tab1Page`.
- [x] En `monthly_cutoff`, ocultar `Nueva Nota`.
- [x] En `monthly_cutoff`, ocultar o deshabilitar `Ver todos` hacia `/tabs/pagos`.
- [x] En `monthly_cutoff`, renderizar ultimos servicios sin click a nota.
- [x] En `monthly_cutoff`, cambiar subtitulo para no mostrar folio como protagonista.
- [x] En `note_based`, conservar acciones actuales.

Checkpoint F:

- [x] Home no promueve nueva nota ni listado de notas en `monthly_cutoff`.
- [x] Home conserva accesos actuales en `note_based`.

### Hallazgo G - Backend API mobile aun permite pago por nota

Archivos:

- `vetsys/app/Http/Controllers/Api/V1/NoteController.php`
- `vetsys/routes/api.php`

Problema:

- `POST /api/v1/notes/{note}/manual-payment` sigue disponible.
- `POST /api/v1/notes/{note}/payment-links` sigue disponible.

Plan de ejecucion:

- [x] En `NoteController::storeManualPayment`, bloquear si `tenant->usesMonthlyCutoffBilling()`.
- [x] En `NoteController::createPaymentLink`, bloquear si `tenant->usesMonthlyCutoffBilling()`.
- [x] Responder con mensaje:
  - `En modo cuentas mensuales, registra abonos desde la cuenta del cliente.`
- [ ] Agregar prueba feature API para ambos endpoints en `monthly_cutoff`.
- [ ] Confirmar que ambos endpoints siguen operando en `note_based`.

Checkpoint G:

- [x] Backend mobile no permite pago por nota en `monthly_cutoff`.
- [x] UI y backend quedan alineados.

## Plan de ejecucion recomendado post-barrido

Orden:

1. **Bloqueo backend API mobile**
   - Ejecutar Hallazgo G primero para proteger datos aunque la UI tenga rutas viejas.
2. **Detalle de nota**
   - Ejecutar Hallazgo A para eliminar el cobro directo visible en la ruta reportada.
3. **Detalle de cliente y home**
   - Ejecutar Hallazgos D y F para quitar accesos principales a notas.
4. **Tab global de notas/pagos**
   - Ejecutar Hallazgo B para desactivar temporalmente `/tabs/pagos` en `monthly_cutoff`.
5. **Historial de mascota**
   - Ejecutar Hallazgo C para dejar servicios sueltos sin link a nota.
6. **Historial de notas por cliente**
   - Ejecutar Hallazgo E como bloqueo v1 o transformacion posterior.
7. **QA visual y funcional**
   - Validar tenant `monthly_cutoff` en:
     - `/tabs/clientes/2015`
     - `/tabs/notas/235`
     - `/tabs/pagos`
     - `/tabs/mascotas/3181`
     - `/tabs/home`
   - Validar tenant `note_based` para confirmar que nada se rompio.

Definition of done post-barrido:

- [ ] En `monthly_cutoff`, ninguna pantalla movil presenta pago por nota.
- [ ] En `monthly_cutoff`, ninguna ruta principal usa notas como documento de cobro.
- [ ] En `monthly_cutoff`, historiales visibles muestran servicios/cargos sueltos.
- [ ] En `monthly_cutoff`, la unica accion de cobranza es abono global desde cuenta del cliente.
- [ ] En `note_based`, notas, pagos por nota y links de nota siguen igual.

## Fase 4 - Detalle de nota adaptado por modo

Objetivo:

Evitar que la nota individual vuelva a ser el documento principal de cobro en `monthly_cutoff`.

Archivos candidatos:

- `gorozpeApp/src/app/features/notes/note-detail/note-detail.page.ts`
- `gorozpeApp/src/app/features/notes/note-detail/note-detail.page.html`
- `gorozpeApp/src/app/features/notes/note-detail/note-detail.page.scss`

Tareas para `note_based`:

- [ ] Conservar pago manual por nota.
- [ ] Conservar link Stripe por nota.
- [ ] Conservar saldo pendiente como accion principal.

Tareas para `monthly_cutoff`:

- [ ] Mostrar aviso: `Esta nota forma parte de la cuenta mensual del cliente.`
- [ ] Ocultar pago manual por nota.
- [ ] Ocultar link Stripe por nota.
- [ ] Ocultar saldo pendiente como CTA principal.
- [ ] Agregar CTA para abrir el cliente y registrar abono global.
- [ ] Mantener detalles de servicios, paciente, fecha, folio y pagos historicos como respaldo.

Checkpoint 4:

- [ ] En `monthly_cutoff`, la nota es respaldo operativo.
- [ ] En `monthly_cutoff`, no hay acciones visibles de pago por nota.
- [ ] En `note_based`, la nota sigue siendo cobrable como antes.

Validacion sugerida:

- [ ] Abrir una nota pendiente en tenant `monthly_cutoff`.
- [ ] Confirmar que no aparecen botones de pago por nota.
- [ ] Abrir una nota pendiente en tenant `note_based`.
- [ ] Confirmar que los botones existentes siguen disponibles.

## Fase 5 - Blindaje API mobile de pagos por nota

Objetivo:

La separacion por modo no debe depender solo de la UI movil.

Archivos candidatos:

- `vetsys/app/Http/Controllers/Api/V1/NoteController.php`

Tareas:

- [ ] En `createPaymentLink(Request $request, Note $note)`, si el tenant usa `monthly_cutoff`, responder 422 o 409 con mensaje de abono global.
- [ ] En `storeManualPayment(Request $request, Note $note)`, si el tenant usa `monthly_cutoff`, responder 422 o 409 con mensaje de abono global.
- [ ] Mantener ambos endpoints funcionales en `note_based`.
- [ ] Alinear el mensaje con web:
  - `En modo cuentas mensuales, registra abonos desde la cuenta del cliente.`
- [ ] Confirmar que la app muestra el mensaje del backend si alguien llega por estado viejo o deep link.

Checkpoint 5:

- [ ] API movil bloquea pagos por nota en `monthly_cutoff`.
- [ ] UI movil tambien los oculta.
- [ ] `note_based` no sufre regresion.

Validacion sugerida:

- [ ] Request directo a `/api/v1/notes/{note}/manual-payment` en tenant `monthly_cutoff`.
- [ ] Request directo a `/api/v1/notes/{note}/payment-links` en tenant `monthly_cutoff`.
- [ ] Repetir ambos en tenant `note_based`.

## Fase 6 - Cortes en app movil v1

Objetivo:

Definir el alcance minimo para no mezclar conceptos en la app movil.

Decision propuesta para v1:

- [ ] No generar cortes desde mobile.
- [ ] No recalcular cortes desde mobile.
- [ ] No pagar cortes directamente desde mobile.
- [ ] Mostrar balance y abonos desde cliente.
- [ ] Mantener cortes como operacion web por ahora.

Opcional fase 2:

- [ ] Crear endpoint tenant-mobile para listar cortes por customer.
- [ ] Mostrar ultimo corte y PDF disponible en detalle de cliente.
- [ ] Agregar pantalla simple de historial de cortes.
- [ ] Mantener acciones administrativas de generar/recalcular solo en web.

Checkpoint 6:

- [ ] Alcance v1 confirmado.
- [ ] Si se decide mostrar cortes, abrir nuevo sub-roadmap antes de implementar.

## Fase 7 - QA comparativo por modo

Objetivo:

Confirmar que los dos modos conviven en mobile sin romper el flujo actual.

Matriz `note_based`:

- [ ] Login.
- [ ] `/mobile/bootstrap` trae `billing_mode = note_based`.
- [ ] Cliente muestra notas y pagos como antes.
- [ ] Nota pendiente permite pago manual.
- [ ] Nota pendiente permite link de pago.
- [ ] Pago por cliente conserva distribucion sobre notas.
- [ ] Crear nota desde mobile conserva comportamiento actual.

Matriz `monthly_cutoff`:

- [ ] Login.
- [ ] `/mobile/bootstrap` trae `billing_mode = monthly_cutoff`.
- [ ] Cliente muestra cuenta/balance/abonos.
- [ ] Registrar abono usa `/payments` con `customer_id`.
- [ ] Nota pendiente no permite pago por nota.
- [ ] Request directo a pago por nota queda bloqueado.
- [ ] Crear nota desde mobile sigue creando cargos/servicios.
- [ ] Balance del cliente refleja cargos menos abonos.

Checkpoint 7:

- [ ] QA manual completado en ambos modos.
- [ ] Pruebas automatizadas relevantes agregadas o actualizadas.
- [ ] Roadmap actualizado con resultados y pendientes reales.

## Definicion de terminado

- [ ] La app movil refleja automaticamente el modo configurado en web.
- [ ] No existe selector de modo en mobile.
- [ ] `note_based` conserva experiencia actual.
- [ ] `monthly_cutoff` cobra por cuenta global del cliente.
- [ ] `monthly_cutoff` no muestra ni permite pago por nota como camino principal.
- [ ] Backend y app tienen el mismo contrato documentado.
- [ ] El avance queda registrado en este archivo al cierre de cada fase.

## Log de avance

| Fecha | Checkpoint | Estado | Notas |
| --- | --- | --- | --- |
| 2026-07-15 | Creacion del roadmap | Completado | Se crea plan sincronizado para backend y app movil sin selector en mobile. |
| 2026-07-15 | Checkpoint 1 - Contrato backend mobile | Completado | `AuthController::serializeUser()` expone `user.tenant.billing_mode` normalizado; se agrego `MobileBillingModeContractTest` para login, me y bootstrap. |
| 2026-07-15 | Checkpoint 2 - Tipos y helper de modo | Completado | `ApiTenant` incluye `billing_mode`; se agrego `BillingModeService` con normalizacion y computed signals para consumir el modo desde la sesion. Build Angular aprobado con Node bundled. |
| 2026-07-15 | Checkpoint 3 - Pantalla de cliente por modo | Completado parcial | `customer-detail` usa `BillingModeService`; `monthly_cutoff` muestra balance/cargos/abonos y omite distribucion por nota. Build Angular aprobado. QA con tenant real queda pendiente. |
| 2026-07-15 | Barrido post-Fase 3 - ocultar notas en monthly | Completado parcial | Se ocultaron cobros por nota en mobile, se desactivo `/tabs/pagos`, se bloquearon historiales de notas y se dejo historial de mascota como servicios sueltos. Build Angular y `php -l` aprobados; faltan pruebas feature API y QA manual. |
| 2026-07-15 | Ajuste Home monthly - acciones rapidas | Completado | En `/tabs/home`, modo `monthly_cutoff` muestra `Registrar Cliente` a la izquierda y `Registrar Caballo` a la derecha; modo `note_based` conserva `Nueva Nota` y `Registrar Paciente`. Build Angular aprobado. |
