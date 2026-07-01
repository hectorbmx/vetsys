# Plan de pruebas Fase 5 - Offline movil

Fecha: 2026-06-30

## Objetivo

Validar que la cadena offline inicial funcione de punta a punta sin depender de
permisos de iOS o emuladores desde Codex.

## Preparacion

- Iniciar sesion con un usuario tenant valido.
- Ejecutar bootstrap inicial con conexion.
- Confirmar que Home muestre una ultima sincronizacion.
- Tener al menos un servicio activo sin inventario en catalogo.
- Tener al menos un metodo para volver a activar/desactivar internet del equipo o
  dispositivo.

## Pruebas manuales obligatorias

### 1. Lectura local despues del bootstrap

1. Entrar con conexion.
2. Abrir Home, Clientes, Pacientes, Servicios y Notas/Pagos.
3. Desactivar internet.
4. Volver a abrir esas pantallas.

Resultado esperado:

- Las listas siguen mostrando la informacion sincronizada.
- Home conserva actividad reciente y conteos.
- No debe aparecer pantalla vacia por depender solo de la API.

### 2. Cliente offline

1. Desactivar internet.
2. Crear un cliente nuevo.
3. Revisar que aparezca en la lista.
4. Revisar Home.

Resultado esperado:

- El cliente queda visible con estado pendiente.
- Home incrementa el conteo de operaciones pendientes.
- El cliente pendiente no permite abrir acciones que requieran `server_id`
  definitivo.

### 3. Paciente offline con cliente existente

1. Con internet desactivado, elegir un cliente ya sincronizado.
2. Crear un paciente nuevo.
3. Volver al listado de pacientes.

Resultado esperado:

- El paciente queda visible como pendiente.
- La operacion queda en cola y no bloquea el uso de la app.

### 4. Paciente offline con cliente pendiente

1. Desactivar internet.
2. Crear cliente nuevo.
3. Sin reconectar, crear paciente para ese cliente.

Resultado esperado:

- El paciente queda ligado al cliente pendiente.
- Al sincronizar, la app debe enviar `customer_client_uuid` para que Laravel
  resuelva la relacion.

### 5. Nota offline con servicios sin inventario

1. Desactivar internet.
2. Seleccionar cliente y paciente disponibles localmente.
3. Crear una nota de credito.
4. Agregar solo servicios activos sin inventario.
5. Guardar la nota.

Resultado esperado:

- La nota se guarda con folio temporal `Pendiente`.
- La nota queda como operacion pendiente.
- Los detalles conservan nombre, precio e impuesto seleccionados.
- No se registra pago offline.

### 6. Cadena completa offline

1. Desactivar internet.
2. Crear cliente.
3. Crear paciente para ese cliente.
4. Crear nota de credito para ese paciente usando servicios sin inventario.
5. Reactivar internet.
6. Volver a primer plano o refrescar bootstrap.

Resultado esperado:

- Laravel recibe primero cliente, luego paciente, luego nota.
- La sincronizacion resuelve relaciones por `client_uuid`.
- Desaparecen las operaciones pendientes tras respuesta exitosa.
- El bootstrap posterior reemplaza folios/ids temporales por datos definitivos.

### 7. Reintento despues de error

1. Crear una operacion offline.
2. Reactivar internet con backend apagado o inaccesible.
3. Abrir Home o volver a primer plano.
4. Luego encender backend y repetir sincronizacion.

Resultado esperado:

- La operacion queda marcada con error recuperable.
- No se duplica al reintentar.
- Al recuperar backend, la cola se limpia si Laravel confirma exito.

### 8. Rechazo esperado: pago offline

1. Desactivar internet.
2. Intentar crear una nota `contado`.
3. Guardar.

Resultado esperado:

- La app no encola la nota.
- Se muestra que requiere conexion por incluir pago.

### 9. Rechazo esperado: producto o inventario

1. Con una nota que incluya producto o servicio inventariable, cortar conexion.
2. Intentar guardar.

Resultado esperado:

- La app no encola la nota.
- Se muestra que requiere conexion por producto/inventario.

### 10. Cierre de sesion

1. Con datos sincronizados, cerrar sesion.
2. Entrar de nuevo.

Resultado esperado actual:

- La politica final de limpieza local aun esta pendiente.
- Registrar si los datos locales persisten o si se requiere limpieza explicita en
  una fase posterior.

## Pruebas de plataforma

### Web / navegador

- Confirmar que IndexedDB contiene datos despues del bootstrap.
- Confirmar que recargar la pagina no elimina clientes, pacientes, servicios ni
  notas locales.

### Android

- Confirmar que `@capacitor-community/sqlite` crea/abre la base
  `vetsys_mobile_offline_v2`.
- Repetir las pruebas 1 a 9 en dispositivo o emulador.

### iOS

- Pendiente hasta resolver permisos de `cap sync ios`.
- No reintentar desde Codex si vuelve a fallar por `EPERM`; ejecutar manualmente
  cuando Xcode/archivos no esten bloqueando symlinks o `capacitor.config.json`.

## Evidencia a capturar

- Captura de Home con operaciones pendientes.
- Captura de cliente/paciente/nota pendiente.
- Respuesta de `/api/v1/sync/push` cuando sincronice.
- Captura posterior mostrando folio definitivo de nota.
- Cualquier mensaje `last_sync_error` si hay rechazo parcial.

## Criterios de cierre

- La cadena cliente -> paciente -> nota sincroniza sin duplicados.
- Un fallo temporal de red/backend permite reintentar.
- Pagos offline y productos/inventario quedan bloqueados.
- Android valida SQLite nativo.
- iOS queda marcado como pendiente solo si el bloqueo sigue siendo permisos de
  entorno.
