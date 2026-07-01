# Checkpoint de trabajo offline movil

Fecha: 2026-06-30

## Estado actual

Fase 5 preparada para validacion manual.

La aplicacion ya tiene una capa local con IndexedDB en web y SQLite nativo en
Android/iOS mediante `@capacitor-community/sqlite`. El bootstrap movil persiste
por upsert en la base local y las primeras consultas leen desde repositorios
locales. El backend Laravel ya incluye `notes` y `note_details` en
`/api/v1/mobile/bootstrap`.

La cola offline persistente ya permite crear clientes, pacientes y notas de
credito con servicios sin inventario. Las operaciones quedan en `sync_outbox` y
se sincronizan contra `/api/v1/sync/push` cuando vuelve la conexion o se
refresca el bootstrap.

## Alcance ejecutado

### Backend Laravel

- `MobileBootstrapController` ahora devuelve:
  - `notes`
  - `note_details`
- El contrato `docs/mobile-api-v1.md` fue actualizado para documentar esos dos
  arreglos dentro del bootstrap/pull incremental.

### Ionic

- Se agregaron tipos moviles para:
  - clientes
  - pacientes
  - servicios/catalogo
  - notas
  - detalles de notas
- Se agrego `LocalDatabaseService` con IndexedDB y stores:
  - `customers`
  - `animals`
  - `catalog_items`
  - `notes`
  - `note_details`
  - `sync_outbox`
  - `sync_conflicts`
  - `sync_metadata`
- `BootstrapService.loadInitial()` y `refreshIncremental()` ahora aplican el
  bootstrap a la base local antes de guardar la sesion.
- `LocalDatabaseService` usa SQLite nativo cuando `Capacitor.isNativePlatform()`
  es verdadero y mantiene IndexedDB para web/desarrollo.
- Se agregaron repositorios iniciales:
  - `CustomerRepository`
  - `AnimalRepository`
  - `CatalogItemRepository`
  - `NoteRepository`
- Se migraron a lectura local:
  - busqueda global de Home
  - contadores operativos de Home
  - actividad reciente de Home
  - listado de pacientes
  - listado de servicios
  - listado de notas/pagos
  - historial de notas por cliente
- Se agrego estado minimo de sincronizacion:
  - sincronizando
  - ultima sincronizacion
  - error de sincronizacion local
- `cap sync` enlazo Android y detecto `@capacitor-community/sqlite`.

### Fase 3

- Se agrego `OfflineOutboxService` en Ionic.
- `sync_outbox` persiste operaciones:
  - `create_customer`
  - `create_animal`
- Si la creacion online de cliente falla, el cliente queda local como
  `pending_create`.
- Si la creacion online de paciente falla, el paciente queda local como
  `pending_create`.
- Si un paciente se crea contra un cliente pendiente, la sincronizacion usa
  `customer_client_uuid` para que Laravel resuelva la relacion en `/sync/push`.
- La cola se intenta sincronizar al recuperar conexion/app activa.
- Home muestra el conteo de operaciones pendientes.
- Las listas bloquean abrir/desactivar registros pendientes porque aun no tienen
  `server_id` definitivo.

### Fase 4

- Ionic agrego `create_note` en `OfflineOutboxService`.
- Si la creacion online de nota falla, la app guarda localmente la nota y sus
  detalles cuando la operacion es de credito y todos los conceptos son servicios
  activos sin inventario.
- La nota local queda como `pending_create`, con folio temporal `Pendiente`,
  totales congelados y detalles con nombre, precio e impuesto seleccionados.
- La sincronizacion manda las notas pendientes en `/api/v1/sync/push`.
- Si la nota usa cliente o pacientes creados offline, la sincronizacion resuelve
  `customer_client_uuid` y `animal_client_uuids`.
- El formulario de nueva nota usa busqueda local como respaldo para clientes,
  pacientes y servicios sin inventario cuando la API no responde.
- Los pagos offline siguen fuera de alcance: un intento de nota `contado` o con
  productos/inventario requiere conexion.

### Fase 5

- Se agrego `docs/mobile-offline-phase5-test-plan.md` con la lista de acciones
  manuales para validar lectura local, creaciones offline, sincronizacion,
  reintentos, rechazos esperados y plataformas.
- No se forzaron pruebas con iOS/emulador desde Codex para evitar bloqueos de
  permisos. Si `cap sync ios` vuelve a fallar por `EPERM`, se mantiene como
  pendiente de entorno.

## Pendiente inmediato

- Resolver `cap sync ios`: fallo por `EPERM` al escribir
  `ios/App/App/capacitor.config.json` y symlinks de SPM.
- Validar en dispositivo/emulador Android que SQLite nativo abra y persista.
- Validar en navegador que IndexedDB siga persistiendo bootstrap y consultas.
- Ejecutar el checklist de `docs/mobile-offline-phase5-test-plan.md`.
- Migrar detalles pendientes que aun usan API directa para consulta puntual.

## Decisiones tecnicas vigentes

- IndexedDB queda como adaptador web/desarrollo.
- SQLite nativo queda como adaptador movil.
- Clientes y pacientes ya quedaron cubiertos por Fase 3.
- Notas offline basicas ya quedaron cubiertas por Fase 4.
- Notas offline solo usan servicios activos sin inventario y siempre se guardan
  como credito.
- Pagos offline, productos con inventario y revision formal por cambio de precio
  quedan fuera de esta fase.
- Fase 5 queda documentada como pruebas manuales; no requiere nuevas
  instalaciones desde Codex.

## Archivos principales

- Backend: `app/Http/Controllers/Api/V1/MobileBootstrapController.php`
- Backend docs: `docs/mobile-api-v1.md`, `docs/mobile-offline-roadmap.md`
- Backend docs de prueba: `docs/mobile-offline-phase5-test-plan.md`
- Ionic docs sincronizados: `gorozpeApp/docs/mobile-offline-checkpoint.md`
- Ionic: `src/app/core/offline/offline-outbox.service.ts`,
  `src/app/features/notes/note-create/note-create.page.ts`

## Proximo arranque recomendado

1. Cerrar permisos/archivos abiertos de iOS y ejecutar `npx cap sync ios`.
2. Probar login/bootstrap en navegador y confirmar IndexedDB poblado.
3. Probar en Android que `@capacitor-community/sqlite` crea la base
   `vetsys_mobile_offline_v2`.
4. Ejecutar el checklist de Fase 5 en navegador y Android.
5. Registrar resultados/fallos puntuales en el checkpoint antes de cerrar la
   fase como validada.

## Verificacion

- `ng build` ejecutado correctamente en `gorozpeApp` el 2026-06-30 con el Node
  runtime bundled: `node node_modules/@angular/cli/bin/ng.js build`.
- Correccion posterior 2026-06-30 en Ionic:
  - Se agrego store local `animal_types` y `AnimalTypeRepository`.
  - Se agrego store local `payment_methods` y `PaymentMethodRepository`.
  - El bootstrap movil ahora persiste `catalogs.animal_types`.
  - El bootstrap movil ahora persiste `catalogs.payment_methods`.
  - Alta rapida de pacientes usa tipos locales como fallback si `/animal-types`
    no responde.
  - Nueva nota usa metodos de pago locales como fallback si `/payment-methods`
    no responde.
  - `OfflineOutboxService` actualiza directamente clientes/pacientes/notas
    locales cuando `/sync/push` devuelve `synced`, borrando el registro
    temporal por `client_uuid` y guardando el registro definitivo por `server_id`.
  - `ng build` volvio a pasar despues de esta correccion.

## Nota para el siguiente agente

Si un dispositivo ya tenia la base `vetsys_mobile_offline_v2`, debe abrir la app
con conexion y completar un bootstrap/refresh antes de crear pacientes offline.
Ese refresh crea/puebla las stores locales `animal_types` y `payment_methods`.
Sin ese paso, los selectores de tipo de paciente o metodo de pago no tendran
datos locales.
