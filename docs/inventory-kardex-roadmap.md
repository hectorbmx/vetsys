# Roadmap: inventario y kardex por tenant

## Objetivo

Implementar un control de inventario auditable para productos, con entradas,
salidas, ajustes, devoluciones y ventas automaticas, sin perder el stock actual
ni romper el flujo existente de notas de venta.

Al terminar, `inventories.stock_actual` seguira siendo el saldo rapido para
consultas, pero cada cambio debera estar respaldado por un movimiento inmutable
en el kardex.

## Estado actual

- `catalog_items` distingue entre `product` y `service`.
- Cada producto puede activar `has_inventory`.
- `inventories` guarda:
  - `stock_actual`
  - `stock_minimo`
  - `allow_negative_stock`
- `InventoryService::consumeForSale()` descuenta stock al crear una nota.
- La venta bloquea existencias insuficientes cuando no se permiten negativos.
- Ya existen notificaciones para stock bajo, agotado y negativo.
- `/client/servicios` permite capturar stock inicial y cambiar la politica de
  venta sin existencias.
- El controlador todavia permite modificar `stock_actual` directamente.
- No existe tabla de movimientos, entradas por compra, devoluciones ni ajustes.
- No existe una preferencia general para que el tenant decida si usa inventario.

## Decisiones de producto

### Dos niveles de activacion

1. El tenant decide si desea utilizar el modulo de inventarios.
2. Dentro de un tenant habilitado, cada producto decide si controla existencias.

Propuesta:

- Agregar `inventory_enabled` al tenant.
- Para tenants existentes, habilitarlo automaticamente si ya tienen al menos un
  producto con `has_inventory = true`.
- Para tenants nuevos, iniciar deshabilitado.
- Los servicios nunca controlan inventario.
- Desactivar el modulo no elimina saldos ni movimientos historicos.
- Al reactivar despues de una pausa, solicitar un conteo fisico y registrar un
  ajuste de reconciliacion antes de continuar.

### Stock actual y kardex

- `inventories.stock_actual` sera un saldo materializado para lecturas rapidas.
- `inventory_movements` sera la fuente de auditoria.
- Ningun formulario ordinario podra editar `stock_actual` directamente.
- Todo cambio de saldo pasara por `InventoryService` dentro de una transaccion.
- Los movimientos confirmados no se editan ni eliminan.
- Los errores se corrigen con un movimiento compensatorio.

## Movimientos de la primera version

| Clave | Direccion | Uso |
| --- | --- | --- |
| `initial` | Entrada | Existencia inicial al activar el producto |
| `purchase` | Entrada | Compra o reposicion de mercancia |
| `sale` | Salida | Descuento automatico por nota de venta |
| `return` | Entrada | Devolucion o reversion de una venta |
| `adjustment_in` | Entrada | Correccion positiva por conteo fisico |
| `adjustment_out` | Salida | Merma, dano, caducidad, perdida o correccion |

Reglas:

- Todas las cantidades se reciben positivas.
- La direccion determina si la cantidad suma o resta.
- Merma, dano y caducidad son motivos de `adjustment_out`, no tipos separados.
- La cancelacion o devolucion de una venta genera `return` ligado a la nota.
- Una venta no puede generar dos movimientos para el mismo producto y nota.

## Modelo de datos propuesto

### Preferencia del tenant

Agregar a `tenants`:

```text
inventory_enabled boolean default false
inventory_enabled_at timestamp nullable
inventory_disabled_at timestamp nullable
```

Si ya existe una tabla de configuracion central por tenant al iniciar el trabajo,
usar ese mecanismo en lugar de duplicar la preferencia en `tenants`.

### Tabla `inventory_movements`

Campos propuestos:

```text
id
tenant_id
inventory_id
catalog_item_id
user_id nullable
type string
direction string: in|out
quantity decimal(12,2)
stock_before decimal(12,2)
stock_after decimal(12,2)
reason string nullable
notes text nullable
reference_type string nullable
reference_id unsignedBigInteger nullable
idempotency_key string nullable
occurred_at timestamp
created_at
updated_at
```

Indices y restricciones:

- Indice por `tenant_id`, `catalog_item_id`, `occurred_at`.
- Indice por `reference_type`, `reference_id`.
- `idempotency_key` unica cuando exista.
- Llaves foraneas con aislamiento de tenant validado en aplicacion.
- `quantity > 0` validado en request y servicio.

El `idempotency_key` de una venta puede ser:

```text
sale:{note_id}:product:{catalog_item_id}
```

## Relaciones de modelos

### `Tenant`

- `inventoryMovements()`
- cast boolean para `inventory_enabled`

### `CatalogItem`

- `inventoryMovements()`

### `Inventory`

- `movements()`

### `InventoryMovement`

- `tenant()`
- `inventory()`
- `catalogItem()`
- `user()`

## Servicio de dominio

Centralizar toda mutacion en `InventoryService`.

Metodos sugeridos:

```php
recordMovement(
    Tenant $tenant,
    CatalogItem $item,
    string $type,
    float $quantity,
    array $context = []
): InventoryMovement

consumeForSale(Tenant $tenant, array $items, int $quantityMultiplier, Note $note): void

returnForSale(Note $note, array $items): void
```

`recordMovement()` debe:

1. Confirmar tenant y producto.
2. Confirmar que tenant y producto controlan inventario.
3. Abrir o participar en una transaccion.
4. Bloquear `inventories` con `lockForUpdate()`.
5. Validar cantidad y tipo.
6. Calcular saldo nuevo.
7. Aplicar politica de negativos.
8. Crear el movimiento.
9. Actualizar `stock_actual`.
10. Crear notificacion si cruza minimo, cero o negativo.

La creacion del movimiento y la actualizacion del saldo deben ser atomicas.

## Flujo funcional

### Activar inventario para el tenant

1. Usuario entra a Configuracion.
2. Activa `Manejar inventarios`.
3. El sistema guarda fecha y usuario responsable si se decide auditar settings.
4. En Catalogo aparecen controles de inventario para productos.

### Activar inventario en un producto nuevo

1. Usuario selecciona tipo `Producto`.
2. Activa `Controla inventario`.
3. Captura stock inicial y stock minimo.
4. Se crea `inventories` con saldo cero.
5. Se registra movimiento `initial` por la cantidad capturada.
6. El saldo se actualiza mediante el servicio.

No crear primero el saldo y despues el movimiento con valores independientes.

### Entrada por compra

1. Desde Catalogo, usuario pulsa `Inventario`.
2. Abre el kardex del producto.
3. Pulsa `Registrar movimiento`.
4. Selecciona `Entrada por compra`.
5. Captura cantidad, referencia y notas.
6. El sistema registra saldo anterior y nuevo.
7. La fila nueva aparece al inicio del kardex.

### Salida por venta

1. Usuario crea una nota.
2. Backend agrupa cantidades por producto.
3. `consumeForSale()` registra un movimiento `sale` por producto.
4. El movimiento referencia la nota.
5. Si no se permiten negativos y falta stock, se revierte toda la venta.
6. Si se permiten negativos, se registra y se notifica.

### Ajuste manual

1. Usuario abre `Registrar movimiento`.
2. Elige ajuste positivo o negativo.
3. Captura cantidad y motivo obligatorio.
4. Para salida, selecciona motivo: conteo, merma, dano, caducidad u otro.
5. Se registra usuario, fecha y saldos.

### Cancelacion o devolucion

1. Se identifica el movimiento `sale` original.
2. Se crea `return` por la cantidad realmente devuelta.
3. Se referencia la nota original.
4. No se modifica ni elimina el movimiento de venta.
5. Una devolucion no puede superar la cantidad vendida menos devoluciones previas.

### Desactivar inventario del tenant

1. Mostrar advertencia clara.
2. Confirmar que el historial se conserva.
3. Guardar `inventory_disabled_at`.
4. Dejar de generar movimientos nuevos mientras este deshabilitado.
5. Al reactivar, exigir reconciliacion por conteo fisico.

Esta regla debe cerrarse antes de implementar el toggle. No permitir pausas
silenciosas que generen huecos sin advertencia en el kardex.

## Interfaz web

### `/client/servicios`

Mantener acciones separadas:

- `Cambiar precio`
- `Inventario`
- Estado activo/inactivo

El boton `Inventario` aparece cuando:

- El tenant tiene inventarios habilitados.
- El registro es producto.
- El producto tiene `has_inventory`.

En pantallas pequenas puede existir un menu `Administrar` con ambas acciones.

### Vista o panel de inventario

Encabezado:

- Nombre y SKU.
- Stock actual.
- Stock minimo.
- Estado normal, bajo, agotado o negativo.
- Politica de venta sin stock.
- Boton `Registrar movimiento`.

Kardex:

- Fecha.
- Tipo.
- Entrada.
- Salida.
- Saldo.
- Motivo o referencia.
- Usuario.
- Enlace a nota cuando aplique.

Filtros iniciales:

- Rango de fechas.
- Tipo de movimiento.
- Entrada o salida.

### Formulario de movimiento

Campos:

- Tipo.
- Cantidad.
- Motivo cuando sea ajuste.
- Referencia opcional para compra.
- Notas opcionales.

No incluir `stock resultante` como campo editable. Mostrarlo solo como vista
previa calculada.

## API y app movil

La primera entrega puede concentrar la administracion en web, pero la API debe
mantener contratos consistentes.

Cambios sugeridos:

- Incluir `inventory_enabled` en bootstrap movil.
- Mantener en productos:
  - `has_inventory`
  - `stock_actual`
  - `stock_minimo`
  - `allow_negative_stock`
- Agregar despues:
  - `GET /api/v1/catalog-items/{item}/inventory-movements`
  - `POST /api/v1/catalog-items/{item}/inventory-movements`
- La venta movil usara el mismo `InventoryService`; no duplicar reglas en Ionic.
- Refrescar bootstrap/catalogo despues de una venta o movimiento exitoso.

## Migracion de datos existentes

Al desplegar:

1. Crear tabla y campos nuevos.
2. Marcar `inventory_enabled = true` para tenants con productos inventariables.
3. Por cada `inventory` existente, crear un movimiento `initial` con:
   - `stock_before = 0`
   - `stock_after = stock_actual`
   - nota `Saldo migrado al habilitar kardex`
4. Usar una clave idempotente para que el backfill no se duplique.
5. No recalcular ni alterar el saldo existente durante el backfill.

Si existe stock negativo, el movimiento inicial migrado puede conservarlo como
saldo historico excepcional, aunque las nuevas entradas siempre usen cantidad
positiva y direccion explicita.

## Cambios sobre codigo actual

Archivos principales esperados:

- `app/Services/InventoryService.php`
- `app/Models/Inventory.php`
- `app/Models/CatalogItem.php`
- `app/Models/Tenant.php`
- nuevo `app/Models/InventoryMovement.php`
- `app/Http/Controllers/Client/CatalogItemController.php`
- nuevo controlador de movimientos web
- `app/Http/Controllers/Api/V1/CatalogItemController.php`
- `app/Http/Controllers/Api/V1/NoteController.php` si requiere integracion
- `resources/views/client/servicios/index.blade.php`
- nueva vista o componente de kardex
- `routes/web.php`
- `routes/api.php`
- migraciones nuevas
- pruebas feature nuevas

Cambios obligatorios:

- Retirar `stock_actual` del flujo de edicion ordinaria.
- Conservar `stock_minimo` y `allow_negative_stock` como configuracion.
- Refactorizar el descuento actual para que cree movimiento `sale`.
- Evitar crear notificaciones duplicadas durante el refactor.

## Fases de implementacion

### Fase 1: contrato y migraciones

- [ ] Confirmar ubicacion de `inventory_enabled`.
- [ ] Crear migracion de preferencia del tenant.
- [x] Crear `inventory_movements`.
- [x] Crear modelo y relaciones.
- [x] Crear backfill idempotente de saldos existentes.
- [x] Probar migracion en base local.
- [ ] Probar rollback en base de prueba.

Resultado: datos preparados sin cambiar todavia el comportamiento visible.

### Fase 2: motor transaccional

- [x] Crear `recordMovement()`.
- [x] Validar tipos y direcciones permitidos.
- [x] Aplicar bloqueo pesimista.
- [x] Aplicar politica de negativos.
- [x] Refactorizar `consumeForSale()`.
- [x] Conservar alertas de stock.
- [x] Agregar idempotencia para ventas.

Resultado: toda venta genera kardex y saldo en una sola transaccion.

### Fase 3: administracion web

- [ ] Agregar preferencia en Configuracion.
- [ ] Ocultar controles cuando el tenant no usa inventarios.
- [ ] Agregar boton `Inventario` al catalogo.
- [ ] Crear resumen de existencias.
- [ ] Crear listado de kardex.
- [ ] Crear formulario de entrada y ajuste.
- [ ] Mover politica de negativos al panel de inventario si mejora claridad.
- [ ] Eliminar edicion directa de stock actual.

Resultado: usuario puede reponer y ajustar stock con auditoria.

### Fase 4: devoluciones y reversiones

- [ ] Identificar flujo actual de cancelacion de notas.
- [ ] Definir devolucion total y parcial.
- [ ] Crear movimientos `return`.
- [ ] Evitar devolver mas de lo vendido.
- [ ] Agregar enlaces entre kardex y nota.

Resultado: cancelar o devolver no rompe el historial.

### Fase 5: API y movil

- [ ] Exponer preferencia del tenant.
- [ ] Exponer kardex paginado.
- [ ] Crear movimiento manual desde API solo si entra en alcance.
- [ ] Reconciliar catalogo movil despues de cambios.
- [ ] Confirmar mensajes de stock insuficiente.

Resultado: web y movil usan las mismas reglas.

### Fase 6: cierre y despliegue

- [ ] Ejecutar suite enfocada.
- [ ] Ejecutar suite general.
- [ ] Probar dos ventas concurrentes.
- [ ] Probar tenant sin inventario.
- [ ] Probar aislamiento entre tenants.
- [ ] Probar backfill con stock positivo, cero y negativo.
- [ ] Revisar UI responsive.
- [ ] Documentar operacion para soporte.

## Matriz minima de pruebas

### Servicio

- Entrada incrementa saldo.
- Salida decrementa saldo.
- Cantidad cero o negativa se rechaza.
- Stock insuficiente bloquea cuando corresponde.
- Stock negativo se permite solo con configuracion.
- Movimiento y saldo se revierten juntos ante una excepcion.
- Dos operaciones concurrentes no pierden actualizaciones.
- Idempotencia evita duplicar salida por venta.

### Ventas

- Venta crea movimiento por cada producto inventariable.
- Servicios no crean movimientos.
- Productos sin control de inventario no crean movimientos.
- Cantidades repetidas del mismo producto se agrupan.
- Multiplicador por pacientes conserva el comportamiento actual.
- Venta bloqueada no crea nota, movimiento ni descuento parcial.

### Tenant

- Tenant deshabilitado no muestra administracion de inventario.
- Tenant A no consulta ni modifica movimientos de tenant B.
- Habilitar inventario no afecta otros tenants.
- Desactivar conserva saldo e historial.

### UI

- Boton Inventario solo aparece cuando aplica.
- Kardex ordenado del mas reciente al mas antiguo.
- Formulario muestra saldo resultante estimado.
- Error de validacion conserva datos capturados.
- Estados bajo, agotado y negativo son visibles y accesibles.

## Criterios de aceptacion

- Toda variacion de `stock_actual` tiene un movimiento asociado.
- No existe edicion directa de existencias desde Catalogo.
- Las ventas actuales siguen funcionando.
- Una venta fallida no deja descuentos parciales.
- El usuario puede registrar una compra y ver el nuevo saldo inmediatamente.
- El usuario puede registrar ajustes con motivo y responsable.
- El kardex permite explicar el saldo actual de un producto.
- Los movimientos de ventas enlazan a la nota correspondiente.
- El sistema conserva aislamiento estricto por tenant.
- La suite de inventario y ventas queda aprobada.

## Fuera de alcance inicial

- Proveedores completos.
- Ordenes de compra.
- Recepciones parciales de ordenes.
- Costeo promedio, PEPS o UEPS.
- Lotes y numeros de serie.
- Fechas de caducidad por lote.
- Multiples almacenes o sucursales.
- Transferencias entre almacenes.
- Conteos fisicos masivos.
- Importacion masiva de movimientos.

Estos puntos pueden construirse despues sobre el mismo kardex.

## Riesgos

- Editar saldo por fuera del servicio rompe la auditoria.
- Reintentos de una venta pueden duplicar movimientos sin idempotencia.
- Desactivar inventario puede crear huecos historicos si no se confirma y
  reconcilia al reactivar.
- Una devolucion sin limite puede inflar existencias.
- Backfill incorrecto puede duplicar el saldo inicial.
- Operaciones concurrentes requieren `lockForUpdate()`.
- No mezclar cantidades firmadas con direccion; guardar cantidad positiva.

## Orden recomendado para manana

1. Leer este documento y confirmar las decisiones pendientes.
2. Auditar el flujo de cancelacion/devolucion de notas.
3. Confirmar donde vive la configuracion funcional del tenant.
4. Implementar migraciones, modelo y relaciones.
5. Escribir pruebas de `recordMovement()` antes de modificar ventas.
6. Refactorizar ventas para producir kardex.
7. Ejecutar pruebas antes de iniciar la UI.
8. Construir la vista de kardex y el formulario de movimientos.

## Registro de avance

| Fecha | Fase | Trabajo realizado | Pruebas | Pendientes |
| --- | --- | --- | --- | --- |
| 18/06/2026 | 1 y base de 2 | `inventory_movements`, modelo `InventoryMovement`, relaciones, backfill idempotente, movimiento `initial` al crear producto y movimiento `sale` al vender sin cambiar UI | Migraciones aplicadas; `InventoryServiceTest` aprobado; suite completa aprobada con 46 pruebas; Pint acotado aprobado | Definir `inventory_enabled`, probar rollback, construir administracion web de Kardex y eliminar edicion directa de `stock_actual` |
