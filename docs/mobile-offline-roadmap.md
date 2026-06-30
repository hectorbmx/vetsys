# Roadmap de trabajo offline para la aplicacion movil

## Estado

Fase 1 cerrada para web/Android el 2026-06-30.

Queda creada la base local mediante IndexedDB en web y SQLite nativo en movil,
el contrato de stores iniciales, repositorios de lectura y el upsert del
bootstrap movil. El backend ya entrega `notes` y `note_details` en
`/api/v1/mobile/bootstrap`.

Android quedo sincronizado con Capacitor. iOS requiere repetir `cap sync ios`
cuando se resuelva el bloqueo `EPERM` sobre `ios/App/App/capacitor.config.json`
y symlinks de SPM.

## Objetivo

Permitir que el personal complete visitas a clientes sin conexion y sincronice la
informacion cuando la conexion regrese.

El alcance inicial prioriza la creacion de informacion. Se excluyen operaciones que
puedan producir conflictos complejos de edicion, pagos o inventario.

## Caso de uso principal

La aplicacion debe permitir completar offline la siguiente cadena:

```text
Cliente nuevo
  -> Mascota nueva
    -> Nota nueva
      -> Servicios sin inventario
```

Los registros creados offline se relacionaran mediante `client_uuid` hasta que Laravel
les asigne sus identificadores definitivos.

## Alcance offline inicial

### Consultas

- Consultar clientes previamente sincronizados.
- Consultar mascotas previamente sincronizadas.
- Consultar servicios previamente sincronizados.
- Consultar notas guardadas localmente.
- Buscar dentro de la informacion disponible en el dispositivo.

### Creaciones

- Crear clientes.
- Crear mascotas.
- Crear notas.
- Agregar a las notas solamente servicios con `has_inventory = false`.

### Sincronizacion

- Guardar operaciones pendientes en una cola local persistente.
- Sincronizar automaticamente cuando regrese la conexion.
- Resolver dependencias entre clientes, mascotas y notas mediante `client_uuid`.
- Evitar registros duplicados al reintentar operaciones.
- Mostrar operaciones pendientes, sincronizadas y con error.

## Fuera del alcance offline

Estas operaciones seguiran requiriendo conexion:

- Pagos manuales.
- Links de pago y operaciones de Stripe.
- Consulta y distribucion definitiva de saldos.
- Editar registros existentes.
- Desactivar clientes, mascotas o servicios.
- Crear o modificar servicios del catalogo.
- Crear notas con productos o servicios que manejen inventario.
- Respetar `allow_negative_stock` al sincronizar ventas de productos inventariables.
- Videos, radiografias y otros archivos clinicos.
- Compartir expedientes.

## Reglas para notas offline

- El selector offline solo mostrara servicios con `has_inventory = false`.
- La nota se guardara inicialmente como `pending_sync`.
- Se conservara una copia local del nombre, precio e impuestos seleccionados.
- Laravel sera responsable de validar la nota y generar el folio definitivo.
- Si un servicio fue desactivado o cambio de precio antes de sincronizar, la nota
  quedara marcada para revision. No se modificara silenciosamente.
- Una nota pendiente no se considerara confirmada hasta recibir respuesta exitosa
  del servidor.

## Arquitectura propuesta

```text
Pantallas Ionic
    |
Repositorios de dominio
    |
Base local: SQLite nativo / IndexedDB web
    |
Motor de sincronizacion
    |
API Laravel
```

### Tablas locales iniciales

- `customers`
- `animals`
- `catalog_items`
- `notes`
- `note_details`
- `sync_outbox`
- `sync_conflicts`
- `sync_metadata`

Los registros sincronizables deben conservar, según corresponda:

- `server_id`
- `client_uuid`
- `sync_status`
- `server_updated_at`
- `local_created_at`
- `last_sync_error`

## Trabajo necesario en Ionic

- Integrar SQLite para Android/iOS.
- Agregar un adaptador IndexedDB para desarrollo web.
- Crear migraciones de la base local.
- Crear repositorios para evitar llamadas directas desde las pantallas a `ApiService`.
- Implementar `upsert` para el bootstrap inicial e incremental.
- Implementar una cola persistente de creaciones pendientes.
- Detectar recuperacion de conexion y regreso de la aplicacion al primer plano.
- Mostrar estado de conexion y sincronizacion.
- Migrar gradualmente clientes, mascotas, servicios y notas a repositorios locales.

## Trabajo necesario en Laravel

- Mantener `client_uuid` como llave idempotente de creaciones moviles.
- Ampliar el pull incremental para incluir notas y detalles de notas.
- Confirmar que `/sync/push` acepte la cadena completa cliente, mascota y nota.
- Validar que las notas offline solo contengan servicios sin inventario.
- Devolver resultados individuales y errores recuperables por cada operacion.
- Definir una respuesta especifica para notas que requieran revision por cambios
  de precio o estado del servicio.
- Agregar pruebas automatizadas de sincronizacion e idempotencia.

## Fases propuestas

### Fase 1: Fundacion local

- SQLite, IndexedDB y migraciones.
- Repositorios iniciales.
- Bootstrap completo e incremental mediante `upsert`.
- Estado de conexion y sincronizacion.

Estimacion inicial: 2 a 3 dias efectivos.

### Fase 2: Consulta offline

- Clientes, mascotas y servicios leidos desde la base local.
- Busquedas locales.
- Pull incremental al iniciar, recuperar conexion y volver al primer plano.

Estimacion inicial: 1 a 2 dias efectivos.

### Fase 3: Creacion de clientes y mascotas

- Cola local persistente.
- Creacion offline con `client_uuid`.
- Resolucion de relaciones al sincronizar.
- Reintentos e indicadores de error.

Estimacion inicial: 1 a 2 dias efectivos.

### Fase 4: Creacion de notas offline

- Solo servicios sin inventario.
- Notas y detalles almacenados localmente.
- Validacion y folio definitivo asignados por Laravel.
- Flujo de revision cuando cambie un servicio.

Estimacion inicial: 1 a 2 dias efectivos.

### Fase 5: Robustez

- Pruebas de perdida y recuperacion de conexion.
- Pruebas de reintentos e idempotencia.
- Manejo de errores parciales.
- Limpieza de datos locales al cerrar sesion.
- Documentacion operativa.

Estimacion inicial: 1 a 2 dias efectivos.

## Estimacion general

La estimacion inicial para este alcance es de 6 a 10 dias efectivos de desarrollo,
incluyendo pruebas.

La estimacion debe revisarse despues de definir:

- Plataformas objetivo de la primera entrega.
- Cantidad de informacion que debe almacenarse en cada dispositivo.
- Politica exacta ante cambios de precio.
- Experiencia visual para operaciones pendientes o rechazadas.
- Frecuencia y limites del pull incremental.

## Decisiones pendientes antes de implementar

- Definir si la primera entrega offline sera solo Android o tambien iOS y navegador.
- Definir si una nota con precio modificado debe rechazarse o solicitar confirmacion.
- Definir cuanto historial de notas se descargara al dispositivo.
- Definir si varios usuarios compartiran un dispositivo.
- Definir cuanto tiempo conservar operaciones sincronizadas en la cola local.
- Definir si el cierre de sesion elimina inmediatamente toda la base local.
