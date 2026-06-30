# Checkpoint de trabajo offline movil

Fecha: 2026-06-30

## Estado actual

Fase 1 cerrada para web/Android.

La aplicacion ya tiene una capa local con IndexedDB en web y SQLite nativo en
Android/iOS mediante `@capacitor-community/sqlite`. El bootstrap movil persiste
por upsert en la base local y las primeras consultas leen desde repositorios
locales. El backend Laravel ya incluye `notes` y `note_details` en
`/api/v1/mobile/bootstrap`.

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

## Pendiente inmediato

- Resolver `cap sync ios`: fallo por `EPERM` al escribir
  `ios/App/App/capacitor.config.json` y symlinks de SPM.
- Validar en dispositivo/emulador Android que SQLite nativo abra y persista.
- Validar en navegador que IndexedDB siga persistiendo bootstrap y consultas.
- Migrar detalles pendientes que aun usan API directa para consulta puntual.

## Decisiones tecnicas vigentes

- IndexedDB queda como adaptador web/desarrollo.
- SQLite nativo queda como adaptador movil.
- La creacion offline de clientes, pacientes y notas no forma parte de Fase 1.1;
  sigue reservada para Fase 3 y Fase 4.
- Notas offline solo podran usar servicios activos sin inventario.

## Archivos principales

- Backend: `app/Http/Controllers/Api/V1/MobileBootstrapController.php`
- Backend docs: `docs/mobile-api-v1.md`, `docs/mobile-offline-roadmap.md`
- Ionic docs sincronizados: `gorozpeApp/docs/mobile-offline-checkpoint.md`

## Proximo arranque recomendado

1. Cerrar permisos/archivos abiertos de iOS y ejecutar `npx cap sync ios`.
2. Probar login/bootstrap en navegador y confirmar IndexedDB poblado.
3. Probar en Android que `@capacitor-community/sqlite` crea la base
   `vetsys_mobile_offline`.
4. Iniciar Fase 3: cola persistente para crear clientes y pacientes offline.
