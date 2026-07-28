# Roadmap: portal del cliente en modo monthly_cutoff

Fecha de creacion: 2026-07-28

## Objetivo

Adaptar la app del cliente final para que herede correctamente el modo `monthly_cutoff` configurado en web y muestre cuenta mensual, servicios/cargos, cortes, pagos/abonos y balance general sin presentar la nota individual como documento principal de cobro.

Este roadmap es delicado porque afecta lo que ve el cliente final. Aunque ahora se trabaja en pruebas, la implementacion debe mantener intacto el comportamiento `note_based` y alinear los saldos con el panel web.

## Alcance

Rutas y superficies principales:

- `http://localhost:8100/portal/historial`
- `http://localhost:8100/portal/pagos`
- `http://localhost:8100/portal/home`
- Bootstrap portal: `GET /api/v1/portal/bootstrap`
- Endpoints portal relacionados:
  - `GET /api/v1/portal/statements`
  - `GET /api/v1/portal/statements/{statement}/pdf`
  - `GET /api/v1/portal/notes/{note}`

Repos relacionados:

- Backend: `vetsys`
- App Ionic: `gorozpeApp`
- Roadmap movil base: `vetsys/docs/mobile-monthly-cutoff-sync-roadmap.md`
- Roadmap mensual web/base: `vetsys/docs/road_map_ajustes_notas.md`

## Reglas de producto

- `tenants.billing_mode` sigue siendo la fuente de verdad.
- La app del cliente no debe tener selector para cambiar modo.
- En `note_based`, el portal debe conservar notas, pagos por nota y saldos por nota como hasta ahora.
- En `monthly_cutoff`, las notas son respaldo tecnico; el cliente final debe ver servicios/cargos, cortes, abonos y balance general.
- En `monthly_cutoff`, los pagos son abonos globales por `customer_id`, no pagos directos a una nota ni a un corte.
- Los cortes son informativos y operativos; no se pagan directamente.
- El balance mostrado al cliente debe cuadrar con el balance del panel web.

## Estado actual detectado

Backend portal:

- [x] `portal_access.billing_mode` ya llega en `bootstrap` y `me`.
- [x] `statement_summaries` ya llega en `bootstrap`.
- [x] Existen endpoints portal para listar cortes y abrir PDF.
- [ ] `account_summary` todavia se calcula principalmente desde notas y `note->amount_paid`.
- [ ] El bootstrap no expone una lista compacta de pagos/abonos globales del cliente.
- [ ] `note_summaries` sigue representando notas como documentos de cobro.

Ionic portal:

- [x] `/portal/pagos` ya lee `statement_summaries`.
- [x] `/portal/home` ya muestra saldo y ultimo corte disponible.
- [x] `/portal/historial` distingue `note_based` vs `monthly_cutoff`.
- [x] `/portal/historial` monthly ya no depende de `note_summaries` como documento principal.
- [ ] `/portal/pagos` todavia muestra `Notas pendientes` aun cuando el tenant sea mensual.
- [ ] `/portal/home` todavia usa labels tipo `Notas` en modo mensual.

## Contrato esperado por modo

### note_based

El portal conserva el comportamiento actual:

- Historial: notas visibles y acceso a detalle de nota.
- Pagos: notas pendientes, saldo por nota y acciones existentes si aplican.
- Home: metricas de notas, mascotas y saldo pendiente.

### monthly_cutoff

El portal debe presentar:

- Balance general de la cuenta.
- Servicios/cargos visibles como movimientos operativos.
- Cortes/estados de cuenta generados.
- Pagos/abonos globales hechos por el cliente.
- PDF de cortes cuando exista.

No debe presentar:

- Nota individual como documento principal de cobranza.
- Pago por nota.
- Lenguaje de `Notas pendientes` como CTA principal.

## Checkpoint 0 - Auditoria de contrato actual

Objetivo:

Confirmar con datos reales de prueba que el bootstrap del portal trae lo necesario y detectar diferencias de saldo contra web.

Estado 2026-07-28:

- [x] Auditoria local ejecutada sobre acceso portal activo `id=848`.
- [x] Cliente auditado: `customer_id=2015`, `Cliente Perez`.
- [x] Tenant auditado: `tenant_id=219`, `tenants.billing_mode=monthly_cutoff`.
- [ ] Hallazgo: `customer_portal_accesses.billing_mode` viene como `free`, aunque el tenant esta en `monthly_cutoff`; la UI del portal debe leer/normalizar contra la fuente correcta o el backend debe exponer un campo operacional claro.
- [x] Balance actual del portal coincide con regla mensual para este cliente:
  - cargos: `$17,120.00`
  - pagos globales: `$1,750.00`
  - balance: `$15,370.00`
  - delta balance: `$0.00`
- [x] Hay 31 `note_details` visibles para construir historial de servicios/cargos.
- [x] Hay 2 cortes en `statement_summaries`.
- [x] Hay 3 pagos globales en DB.
- [x] Hallazgo resuelto: el bootstrap expone `payment_summaries` para listar abonos.
- [x] Hallazgo resuelto: el bootstrap expone `service_summaries`; `note_summaries` queda intacto para `note_based`.

Tareas:

- [x] Capturar respuesta/datos equivalentes de bootstrap para cliente de prueba `monthly_cutoff`.
- [x] Confirmar valor de modo disponible en portal y tenant.
- [x] Comparar `data.account_summary.outstanding_balance` contra el balance web del cliente.
- [x] Comparar `statement_summaries` contra cortes visibles en web.
- [x] Revisar si `note_summaries` incluye servicios suficientes para construir historial de cargos.
- [x] Identificar si los pagos globales aparecen en algun payload actual del portal.

Criterio de salida:

- [x] Hay evidencia clara de que campos sirven tal cual y que campos requieren cambio backend.

Salida del checkpoint:

- Avanzar a Checkpoint 1 y 2.
- En Checkpoint 1, no asumir `portal_access.billing_mode` como modo operacional final sin resolver el valor `free`.
- En Checkpoint 2, agregar `payment_summaries`.
- En Checkpoint 3, agregar `service_summaries` para evitar que monthly use notas como documento principal.

## Checkpoint 1 - Backend: resumen de cuenta por modo

Objetivo:

Corregir `CustomerPortalController::accountSummary()` para que el balance mensual use la misma regla de cuenta global que web.

Estado 2026-07-28:

- [x] `CustomerPortalController::bootstrap()` expone `data.tenant.billing_mode` con el modo operacional normalizado.
- [x] `CustomerPortalController::bootstrap()` expone `data.portal_access.account_mode` para no depender de `portal_access.billing_mode=free`.
- [x] `CustomerPortalController::me()` tambien expone `tenant.billing_mode` y `portal_access.account_mode`.
- [x] `account_summary` detecta `monthly_cutoff` desde el tenant y usa calculo de cuenta global.
- [x] En `monthly_cutoff`, `account_summary` calcula cargos no cancelados menos pagos globales por `customer_id`.
- [x] En `note_based`, se conserva el calculo previo por notas y se agregan campos compatibles.
- [x] Se agregaron campos:
  - `billing_mode`
  - `total_payments`
  - `total_services`
  - `total_statements`
- [ ] Validar con request autenticado real de `GET /api/v1/portal/bootstrap` en tenant monthly.

Tareas:

- [x] Detectar `monthly_cutoff` desde el acceso/tenant.
- [x] Para `note_based`, conservar calculo actual.
- [x] Para `monthly_cutoff`, calcular:
  - cargos totales desde notas no canceladas del cliente.
  - abonos totales desde `payments` con `customer_id`.
  - balance pendiente como `max(cargos - abonos, 0)`.
  - saldo a favor como `max(abonos - cargos, 0)` o reutilizar `customer.credit_balance` si ese es el contrato vigente.
- [x] Agregar campos explicitos si ayudan a la UI:
  - `billing_mode`
  - `total_charges`
  - `total_payments`
  - `outstanding_balance`
  - `credit_balance`
  - `total_services`
  - `total_statements`
- [ ] Validar que `note_based` no cambie resultados con caso real.

Criterio de salida:

- El balance del portal mensual coincide con web para un cliente de prueba.

## Checkpoint 2 - Backend: payment_summaries / abonos

Objetivo:

Exponer al portal cliente los pagos/abonos globales necesarios para `/portal/historial` y `/portal/pagos`.

Estado 2026-07-28:

- [x] `CustomerPortalController::bootstrap()` expone `data.payment_summaries`.
- [x] `payment_summaries` lista pagos globales por `tenant_id` y `customer_id`.
- [x] Cada pago incluye:
  - `id`
  - `amount`
  - `payment_method_name`
  - `reference`
  - `status`
  - `created_at`
  - `updated_at`
- [x] El contrato Ionic `PortalBootstrapResponse` ya incluye `payment_summaries`.
- [ ] Validar con request autenticado real que `Cliente Perez` recibe 3 abonos.

Tareas:

- [x] Agregar `payment_summaries` al bootstrap del portal.
- [x] Incluir campos minimos:
  - `id`
  - `amount`
  - `payment_method_name`
  - `reference`
  - `status`
  - `created_at`
  - `updated_at`
- [x] En `monthly_cutoff`, tratar estos pagos como `Abonos`.
- [x] En `note_based`, permitir que la UI los use solo si no rompe el flujo actual.
- [x] Respetar visibilidad/tenant/customer del acceso portal.
- [ ] Considerar endpoint paginado futuro `GET /api/v1/portal/payments` si el bootstrap crece demasiado.

Criterio de salida:

- El portal puede listar abonos sin consultar notas ni distribuir pagos por nota.

## Checkpoint 3 - Backend: servicios/cargos visibles

Objetivo:

Definir si `note_summaries` se adapta o si se agrega `service_summaries` para que `/portal/historial` muestre servicios/cargos en monthly.

Opcion recomendada:

- Agregar `service_summaries` para `monthly_cutoff`, derivado de `note_details`, y dejar `note_summaries` intacto para compatibilidad.

Estado 2026-07-28:

- [x] `CustomerPortalController::bootstrap()` expone `data.service_summaries`.
- [x] `service_summaries` se deriva de `note_details`.
- [x] Se conserva `note_summaries` intacto para compatibilidad con `note_based`.
- [x] Los servicios se filtran por animales asignados al usuario final y visibles en `show_history` o `show_notes`.
- [x] No expone notas canceladas.
- [x] Cada servicio incluye referencia al corte si su fecha cae dentro de un `CustomerStatement` del cliente.
- [x] El contrato Ionic `PortalBootstrapResponse` ya incluye `service_summaries`.
- [ ] Validar con request autenticado real que `Cliente Perez` recibe 31 servicios visibles.

Tareas:

- [x] Crear `service_summaries` con campos:
  - `id` de `note_detail`
  - `note_id`
  - `animal_id`
  - `animal_name`
  - `name`
  - `type`
  - `quantity`
  - `subtotal`
  - `date_at`
  - `status`
  - `statement_id` si se puede inferir por rango de corte
- [x] Filtrar por animales visibles para el usuario final.
- [x] No exponer notas canceladas.
- [x] Mantener `note_summaries` para `note_based`.

Criterio de salida:

- `/portal/historial` puede mostrar movimientos de servicios/cargos sin depender del folio como dato principal.

## Checkpoint 4 - Ionic: helper de modo portal

Objetivo:

Evitar condiciones dispersas y adaptar las pantallas del portal usando `portal_access.billing_mode`.

Tareas:

- [x] Crear helper local de modo portal en `/portal/historial`.
- [x] Leer desde `SessionStorageService.portalBootstrap().data.portal_access.account_mode`, con fallback a `tenant.billing_mode` y `account_summary.billing_mode`.
- [x] Normalizar valores legacy si aparecen: `monthly`, `monthly_based` -> `monthly_cutoff`.
- [x] Evitar selector o estado local que contradiga el backend.

Criterio de salida:

- [x] `/portal/historial` renderiza por modo con la fuente backend normalizada.

## Checkpoint 5 - Ionic: /portal/historial por modo

Objetivo:

Hacer que `/portal/historial` sea el historial correcto para ambos modos.

Tareas `note_based`:

- [x] Mantener listado actual de notas.
- [x] Mantener navegacion a detalle de nota.
- [x] Mantener estados `Pagada`, `Pendiente`, `Cancelada`.

Tareas `monthly_cutoff`:

- [x] Mantener encabezado de historial y ajustar empty state a lenguaje mensual.
- [x] Mostrar resumen superior:
  - balance general.
  - total de cargos/servicios.
  - total de abonos.
- [x] Mostrar lista de movimientos:
  - servicios/cargos.
  - cortes generados.
  - abonos.
- [x] No abrir nota como documento principal.
- [x] Abrir detalle de corte si el movimiento es corte.
- [ ] Abrir PDF de corte si el movimiento es corte y existe PDF.
- [x] Mostrar empty state en lenguaje mensual.

Criterio de salida:

- [x] En monthly, el cliente ve servicios, cortes y abonos; no ve notas como cobranza principal.
- [x] Detalle agregado: `GET /api/v1/portal/statements/{statement}` expone resumen, servicios incluidos y abonos del corte para igualar la pantalla web en modo lectura.
- [ ] Pendiente: no se agrega boton PDF al cliente por ahora; el veterinario controla el envio del documento.

## Checkpoint 6 - Ionic: /portal/pagos por modo

Objetivo:

Adaptar la pantalla de pagos para que en monthly sea una cuenta con abonos y cortes.

Tareas `note_based`:

- [x] Mantener `Notas pendientes`.
- [x] Mantener saldo por nota.

Tareas `monthly_cutoff`:

- [x] Cambiar titulo/copy a `Balance`.
- [x] Mostrar `Balance general`.
- [x] Ocultar `Notas pendientes`.
- [x] Mostrar `Abonos realizados`.
- [x] Mostrar `Cortes`.
- [x] Reemplazar `Pagar Ahora` por `Ver Historial` para evitar pago por nota.

Criterio de salida:

- [x] `/portal/pagos` no promueve pago por nota cuando el tenant es mensual.

## Checkpoint 7 - Ionic: /portal/home por modo

Objetivo:

Alinear el primer resumen del portal con el modo mensual.

Tareas:

- [ ] En `note_based`, mantener metricas actuales.
- [x] En `monthly_cutoff`, cambiar:
  - `Notas` -> `Cortes`.
  - `Mascotas` -> `Caballos`.
  - `Pendiente` -> `Balance`.
  - `Pagar Ahora` -> `Ver Historial`.
- [x] Actividad reciente debe priorizar:
  - notificaciones.
  - cortes.
  - servicios/cargos.
  - abonos.
- [x] Cards de resumen navegables a caballos, historial y balance/historial.
- [x] Cards de caballos navegan a su detalle en lugar de expandir notas.

Criterio de salida:

- [x] Home ya no contradice `/portal/historial` en `monthly_cutoff`.
- [ ] Pendiente: revisar `/portal/pagos` para completar la alineacion del modo mensual.

## Checkpoint 8 - QA backend

Casos minimos:

- [ ] Tenant `note_based`: bootstrap conserva forma actual.
- [ ] Tenant `monthly_cutoff`: `account_summary` cuadra con web.
- [ ] Tenant `monthly_cutoff`: `payment_summaries` lista abonos globales.
- [ ] Tenant `monthly_cutoff`: `statement_summaries` lista cortes correctos.
- [ ] Usuario portal sin seccion visible no accede a datos no autorizados.
- [ ] Cliente sin cortes muestra empty state correcto.
- [ ] Cliente con abonos superiores a cargos muestra saldo a favor o balance cero segun contrato.

Comandos sugeridos:

- [ ] `php artisan route:list --path=api/v1/portal`
- [ ] Pruebas feature enfocadas en `CustomerPortalController::bootstrap`
- [ ] `php -l` en controladores tocados

## Checkpoint 9 - QA Ionic

Casos minimos:

- [x] Build Angular exitoso para Checkpoint 5.
- [ ] `/portal/historial` en `note_based`.
- [ ] `/portal/historial` en `monthly_cutoff`.
- [ ] `/portal/pagos` en `note_based`.
- [ ] `/portal/pagos` en `monthly_cutoff`.
- [ ] `/portal/home` en `monthly_cutoff`.
- [ ] Verificar que no haya textos de notas cobrables en monthly.
- [ ] Verificar que no haya botones que lleven a pago por nota en monthly.
- [ ] Verificar mobile viewport real o emulador.

Comando sugerido:

- [ ] `node .\node_modules\@angular\cli\bin\ng.js build`

## Checkpoint 10 - Documentacion y grafo

Tareas:

- [ ] Actualizar este roadmap con resultados reales.
- [ ] Actualizar `vetsys/docs/mobile-monthly-cutoff-sync-roadmap.md`.
- [ ] Si se toca `gorozpeApp`, mantener sincronizado su roadmap equivalente.
- [ ] Ejecutar `graphify update .` en `vetsys`.
- [ ] Mantener `graphify-out/` fuera de commits productivos si esta ignorado/generado.

## Orden recomendado de ejecucion

1. Checkpoint 0 - Auditoria.
2. Checkpoint 1 - Balance backend por modo.
3. Checkpoint 2 - Abonos/pagos globales.
4. Checkpoint 3 - Servicios/cargos visibles.
5. Checkpoint 4 - Helper portal Ionic.
6. Checkpoint 5 - `/portal/historial`.
7. Checkpoint 6 - `/portal/pagos`.
8. Checkpoint 7 - `/portal/home`.
9. Checkpoints 8 y 9 - QA.
10. Checkpoint 10 - Documentacion y grafo.

## Definicion de terminado

- [ ] `note_based` conserva comportamiento actual.
- [ ] `monthly_cutoff` muestra balance general, cargos/servicios, cortes y abonos.
- [ ] `monthly_cutoff` no muestra notas como documento principal de cobranza.
- [ ] Balance del portal coincide con web.
- [ ] Cortes del portal coinciden con cortes web.
- [ ] Abonos del portal coinciden con pagos globales web.
- [ ] Roadmaps quedan actualizados.
- [ ] Graphify queda actualizado.
