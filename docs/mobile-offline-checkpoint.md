# Checkpoint de trabajo offline movil

Fecha: 2026-06-30

## Estado actual

Fase 3 ejecutada para clientes y pacientes.

La aplicacion ya tiene una capa local con IndexedDB en web y SQLite nativo en
Android/iOS mediante `@capacitor-community/sqlite`. El bootstrap movil persiste
por upsert en la base local y las primeras consultas leen desde repositorios
locales. El backend Laravel ya incluye `notes` y `note_details` en
`/api/v1/mobile/bootstrap`.

La cola offline persistente ya permite crear clientes y pacientes sin conexion.
Las operaciones quedan en `sync_outbox` y se sincronizan contra `/api/v1/sync/push`
cuando vuelve la conexion o se refresca el bootstrap.

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

## Pendiente inmediato

- Resolver `cap sync ios`: fallo por `EPERM` al escribir
  `ios/App/App/capacitor.config.json` y symlinks de SPM.
- Validar en dispositivo/emulador Android que SQLite nativo abra y persista.
- Validar en navegador que IndexedDB siga persistiendo bootstrap y consultas.
- Probar manualmente:
  - crear cliente offline
  - crear paciente offline con cliente existente
  - crear paciente offline con cliente pendiente
  - recuperar conexion y verificar `/sync/push`
- Migrar detalles pendientes que aun usan API directa para consulta puntual.

## Decisiones tecnicas vigentes

- IndexedDB queda como adaptador web/desarrollo.
- SQLite nativo queda como adaptador movil.
- Clientes y pacientes ya quedaron cubiertos por Fase 3.
- La creacion offline de notas sigue reservada para Fase 4.
- Notas offline solo podran usar servicios activos sin inventario.

## Archivos principales

- Backend: `app/Http/Controllers/Api/V1/MobileBootstrapController.php`
- Backend docs: `docs/mobile-api-v1.md`, `docs/mobile-offline-roadmap.md`
- Ionic docs sincronizados: `gorozpeApp/docs/mobile-offline-checkpoint.md`

## Proximo arranque recomendado

1. Cerrar permisos/archivos abiertos de iOS y ejecutar `npx cap sync ios`.
2. Probar login/bootstrap en navegador y confirmar IndexedDB poblado.
3. Probar en Android que `@capacitor-community/sqlite` crea la base
   `vetsys_mobile_offline_v2`.
4. Iniciar Fase 4: creacion de notas offline con servicios sin inventario.
