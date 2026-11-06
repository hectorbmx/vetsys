# Roadmap - Presupuestos en app movil del vet

Fecha de creacion: 2026-10-06

Repos involucrados:

- Backend/web: `vetsys`
- App movil Ionic: `gorozpeApp`

Modulo web de referencia:

- URL web: `/client/presupuestos`
- Controlador: `vetsys/app/Http/Controllers/Client/BudgetController.php`
- Modelos: `Budget`, `BudgetAnimal`, `BudgetItem`
- Vistas: `resources/views/client/budgets/*`
- Tablas: `budgets`, `budget_animals`, `budget_items`

## Objetivo

Llevar el flujo de presupuestos del panel Laravel a la app movil del veterinario, empezando con una version online y funcional, sin comprometer el modo offline actual de clientes, caballos, servicios, notas y pagos.

La app movil debe permitir al vet:

- Ver presupuestos del tenant.
- Crear un presupuesto para un cliente.
- Seleccionar uno o varios caballos del cliente.
- Agregar servicios/productos del catalogo activo por caballo.
- Ajustar precios del presupuesto sin cambiar el precio base del catalogo.
- Consultar el detalle, total, vigencia y estatus.
- Abrir el PDF generado por Laravel.

## Estado inicial detectado

Backend Laravel:

- El modulo existe solo como rutas web bajo `client/presupuestos`.
- No existe `Api/V1/BudgetController` para la app movil.
- No hay serializadores API para `Budget`, `BudgetAnimal` ni `BudgetItem`.
- No hay presupuestos en `/api/v1/mobile/bootstrap`.
- El PDF se genera desde `BudgetController::pdf()` con `client.budgets.pdf`.
- La creacion actual usa validaciones tenant-safe: cliente del tenant, caballos del cliente y servicios activos del tenant.
- El folio se genera como `PRES-00001`, usando `Budget::withTrashed()` y bloqueo transaccional.
- Los totales se recalculan desde partidas guardadas; no hay descuento ni impuesto aplicado aunque existen columnas.

App movil:

- No hay rutas, pantallas, repositorios ni modelos locales para presupuestos.
- No hay outbox para presupuestos.
- La app ya tiene datos base necesarios: clientes, caballos y catalogo de servicios/productos.
- La app ya tiene patrones de UI reutilizables para listas, detalle, formularios, loader global y estilo de cabecera azul.

## Reglas de producto para v1

- La fuente de verdad de presupuestos sera Laravel.
- Para v1, presupuestos funcionaran online primero.
- No se debe tocar el precio del catalogo cuando se ajusta `price_at_budget`.
- El presupuesto se guarda inicialmente como `draft`, igual que web.
- La app debe respetar tenant, usuario autenticado y middleware mobile existentes.
- El PDF debe salir del backend; la app solo lo abre o descarga.
- No se implementa aceptacion/rechazo por cliente en esta primera fase si no existe flujo de negocio definido.
- No se elimina ni reemplaza el modulo web; mobile consume una API equivalente.

## Fase 0 - Confirmar alcance funcional

Objetivo:

Cerrar exactamente que entra en la primera version movil antes de tocar codigo.

Tareas:

- [ ] Confirmar si la pantalla se llamara `Presupuestos` o `Cotizaciones`.
- [ ] Confirmar si debe vivir como tab principal, boton en cliente o ambas.
- [ ] Confirmar si la creacion sera solo online en v1.
- [ ] Confirmar si la app puede eliminar presupuestos o solo crearlos/verlos.
- [ ] Confirmar si la app puede editar notas generales del presupuesto.
- [ ] Confirmar si la app debe agregar partidas despues de creado el presupuesto.
- [ ] Confirmar si hay que cambiar estatus: `draft`, `sent`, `accepted`, `rejected`, `expired`.
- [ ] Confirmar si se necesita compartir PDF por WhatsApp desde la app.

Checkpoint 0:

- [ ] Alcance v1 aprobado.
- [ ] No hay dudas de navegacion principal.
- [ ] Se sabe que acciones seran solo web y cuales seran movil.

## Fase 1 - API backend de lectura

Objetivo:

Exponer presupuestos a la app movil sin crear ni modificar datos todavia.

Rutas candidatas:

- `GET /api/v1/budgets`
- `GET /api/v1/budgets/{budget}`
- `GET /api/v1/budgets/{budget}/pdf`

Tareas:

- [ ] Crear `App\Http\Controllers\Api\V1\BudgetController`.
- [ ] Registrar rutas dentro del grupo `auth:sanctum`, `access.mobile`, `api.tenant`.
- [ ] Implementar `index` con filtros:
  - [ ] `q` por folio o cliente.
  - [ ] `status` opcional.
  - [ ] `customer_id` opcional.
  - [ ] `per_page` controlado.
- [ ] Implementar `show` cargando `customer`, `animals.animal`, `animals.items.catalogItem`.
- [ ] Crear serializador privado para presupuesto resumen.
- [ ] Crear serializador privado para detalle.
- [ ] Asegurar `abort_unless($budget->tenant_id === $request->user()->tenant_id, 404)`.
- [ ] Reutilizar la vista PDF actual o extraer servicio si conviene.
- [ ] Definir si `GET /pdf` devuelve blob PDF o URL temporal.

Checkpoint 1:

- [ ] La app puede listar presupuestos desde API.
- [ ] La app puede abrir un presupuesto existente.
- [ ] La app puede abrir PDF existente/generado.
- [ ] No se modifico el flujo web Blade.

Validacion sugerida:

- [ ] `php artisan route:list --path=api/v1/budgets`
- [ ] Feature test de listado por tenant.
- [ ] Feature test de 404 entre tenants.
- [ ] Feature test de detalle con animales y partidas.
- [ ] Feature test de PDF con header `application/pdf`.

## Fase 2 - API backend de creacion

Objetivo:

Permitir crear presupuestos desde la app movil con la misma regla de negocio que web.

Ruta candidata:

- `POST /api/v1/budgets`

Payload candidato:

```json
{
  "customer_id": 2017,
  "budget_date": "2026-10-06",
  "valid_until": "2026-10-21",
  "notes": "Observaciones generales",
  "animals": [
    {
      "animal_id": 3184,
      "notes": null,
      "items": [
        {
          "catalog_item_id": 499,
          "quantity": 1,
          "price_at_budget": 1500
        }
      ]
    }
  ]
}
```

Tareas:

- [ ] Extraer logica compartida de creacion de `Client\BudgetController::store()` a un servicio, por ejemplo `BudgetService`.
- [ ] Mantener generacion de folio transaccional.
- [ ] Validar que el cliente pertenezca al tenant.
- [ ] Validar que cada caballo pertenezca al cliente.
- [ ] Validar que cada item pertenezca al catalogo activo del tenant.
- [ ] Sanitizar `notes` con la misma regla que web o una version plain-text para mobile.
- [ ] Guardar `Budget`, `BudgetAnimal` y `BudgetItem` en una transaccion.
- [ ] Recalcular subtotal y total desde partidas.
- [ ] Devolver presupuesto completo serializado.

Checkpoint 2:

- [ ] Mobile puede crear presupuesto como `draft`.
- [ ] Los precios quedan congelados en `price_at_budget`.
- [ ] El precio base queda guardado en `base_price`.
- [ ] El catalogo no cambia.
- [ ] Web sigue pudiendo abrir el presupuesto creado desde mobile.

Validacion sugerida:

- [ ] Feature test de creacion exitosa.
- [ ] Feature test de caballo que no pertenece al cliente.
- [ ] Feature test de servicio inactivo o de otro tenant.
- [ ] Feature test de folio unico por tenant.
- [ ] Feature test de totales por caballo y total general.

## Fase 3 - API backend de edicion puntual

Objetivo:

Cubrir las ediciones pequenas que ya existen en la vista web de detalle.

Rutas candidatas:

- `PATCH /api/v1/budgets/{budget}` para notas generales.
- `POST /api/v1/budgets/{budget}/animals/{budgetAnimal}/items` para agregar partida.
- `PATCH /api/v1/budgets/{budget}/items/{budgetItem}` para cambiar precio.
- `DELETE /api/v1/budgets/{budget}/items/{budgetItem}` para eliminar partida.
- `DELETE /api/v1/budgets/{budget}` solo si se aprueba eliminar desde mobile.

Tareas:

- [ ] Extraer `recalculateBudget()` a servicio compartido.
- [ ] Reutilizar autorizacion de budget animal/item por tenant y presupuesto.
- [ ] Agregar partida con cantidad default `1.00` como web.
- [ ] Permitir cambiar `price_at_budget`.
- [ ] Recalcular subtotal de partida, subtotal del caballo y total del presupuesto.
- [ ] Decidir si mobile puede eliminar partidas.
- [ ] Decidir si mobile puede eliminar presupuesto completo.

Checkpoint 3:

- [ ] Mobile puede ajustar un presupuesto despues de crearlo.
- [ ] Web refleja cambios hechos desde mobile.
- [ ] Las respuestas devuelven totales actualizados.

Validacion sugerida:

- [ ] Feature test de agregar partida.
- [ ] Feature test de actualizar precio.
- [ ] Feature test de eliminar partida.
- [ ] Feature test de recalculo tras cada operacion.

## Fase 4 - Contrato app movil y modelos TypeScript

Objetivo:

Definir tipos y servicios HTTP antes de construir pantallas.

Archivos candidatos:

- `gorozpeApp/src/app/core/models/api.models.ts`
- `gorozpeApp/src/app/core/services/api.service.ts`
- Nuevo servicio: `budget-api.service.ts` o uso directo de `ApiService`.

Tareas:

- [ ] Crear interfaces `MobileBudget`, `MobileBudgetAnimal`, `MobileBudgetItem`.
- [ ] Crear interface de payload `CreateBudgetPayload`.
- [ ] Crear helper de status labels:
  - `draft` -> `Borrador`
  - `sent` -> `Enviado`
  - `accepted` -> `Aceptado`
  - `rejected` -> `Rechazado`
  - `expired` -> `Expirado`
- [ ] Crear helper de formato de dinero o reutilizar uno existente.
- [ ] Crear metodos HTTP para index/show/create/pdf.
- [ ] Mantener errores API legibles para UI.

Checkpoint 4:

- [ ] App compila con tipos nuevos.
- [ ] No hay pantalla visible todavia.
- [ ] Servicios HTTP quedan listos para UI.

Validacion sugerida:

- [ ] `npm run build`

## Fase 5 - Navegacion movil

Objetivo:

Agregar entrada a presupuestos sin saturar el footer.

Opciones:

- Opcion A: agregar una card en Home o Servicios.
- Opcion B: agregar boton dentro del detalle de cliente.
- Opcion C: agregar ruta accesible desde Servicios y detalle de cliente, sin nueva pestaña.
- Opcion D: convertirlo en tab si el usuario lo necesita todos los dias.

Recomendacion v1:

- Agregar acceso desde detalle de cliente: `Nuevo presupuesto` y `Presupuestos`.
- Agregar acceso desde Home o Servicios como acceso secundario.
- No agregar tab nuevo hasta confirmar uso diario.

Rutas candidatas Ionic:

- `/tabs/presupuestos`
- `/tabs/presupuestos/nuevo`
- `/tabs/presupuestos/:id`
- `/tabs/clientes/:customerId/presupuestos/nuevo`

Tareas:

- [ ] Definir rutas en `tabs.routes.ts`.
- [ ] Crear pagina lista.
- [ ] Crear pagina crear.
- [ ] Crear pagina detalle.
- [ ] Agregar boton desde detalle de cliente si se aprueba.

Checkpoint 5:

- [ ] Se puede navegar a lista, alta y detalle.
- [ ] No se rompe footer ni rutas existentes.

Validacion sugerida:

- [ ] `npm run build`
- [ ] Prueba manual de navegacion en Chrome mobile viewport.

## Fase 6 - Pantalla lista de presupuestos

Objetivo:

Ver presupuestos existentes con filtros simples.

Tareas:

- [ ] Header azul consistente con catalogos.
- [ ] KPI compacto: total mes, abiertos, por vencer.
- [ ] Buscador por folio/cliente.
- [ ] Lista con folio, cliente, fecha, vigencia, total y status.
- [ ] Boton para crear presupuesto.
- [ ] Estado vacio.
- [ ] Estado cargando.
- [ ] Pull to refresh.
- [ ] Paginacion o cargar mas si hay muchos.

Checkpoint 6:

- [ ] Lista usable desde mobile.
- [ ] Filtro no dispara demasiadas llamadas.
- [ ] UI respeta tema actual.

Validacion sugerida:

- [ ] Cargar con tenant sin presupuestos.
- [ ] Cargar con tenant con varios presupuestos.
- [ ] Buscar por folio.
- [ ] Buscar por nombre de cliente.

## Fase 7 - Alta de presupuesto movil

Objetivo:

Crear presupuesto en pasos pequenos para que sea usable en telefono.

Flujo sugerido:

1. Seleccionar cliente.
2. Seleccionar caballos del cliente.
3. Agregar servicios por caballo.
4. Ajustar cantidades/precios.
5. Revisar resumen y guardar.

Tareas:

- [ ] Paso cliente: buscador con resultados.
- [ ] Paso caballos: lista de caballos activos del cliente.
- [ ] Paso servicios: buscador de catalogo activo.
- [ ] Permitir agregar multiples servicios por caballo.
- [ ] Permitir editar cantidad.
- [ ] Permitir editar precio presupuesto.
- [ ] Mostrar subtotal por caballo.
- [ ] Mostrar total general sticky o al final.
- [ ] Campos fecha presupuesto y vigencia.
- [ ] Campo notas generales simple.
- [ ] Validar antes de guardar:
  - [ ] cliente seleccionado.
  - [ ] al menos un caballo.
  - [ ] cada caballo seleccionado con al menos un servicio.
  - [ ] cantidades mayores a cero.
- [ ] Enviar `POST /api/v1/budgets`.
- [ ] Navegar al detalle al guardar.

Checkpoint 7:

- [ ] Vet puede crear presupuesto completo desde la app.
- [ ] El presupuesto creado abre en web.
- [ ] El presupuesto creado abre en app.
- [ ] Total coincide con web.

Validacion sugerida:

- [ ] Crear presupuesto de un caballo y un servicio.
- [ ] Crear presupuesto de varios caballos.
- [ ] Crear presupuesto con precio modificado.
- [ ] Intentar guardar sin servicios.

## Fase 8 - Detalle de presupuesto movil

Objetivo:

Permitir revisar y ajustar un presupuesto ya guardado.

Tareas:

- [ ] Header azul con folio y cliente.
- [ ] Card de estado, fecha, vigencia y total.
- [ ] Lista por caballo.
- [ ] Partidas por caballo con cantidad, precio y subtotal.
- [ ] Accion abrir PDF.
- [ ] Accion compartir PDF si se aprueba.
- [ ] Editar notas generales si se aprueba.
- [ ] Agregar servicio a caballo si se aprueba.
- [ ] Editar precio por partida si se aprueba.
- [ ] Eliminar partida si se aprueba.
- [ ] Refrescar detalle despues de cada cambio.

Checkpoint 8:

- [ ] Detalle movil muestra la misma informacion central que web.
- [ ] PDF abre desde app.
- [ ] Cambios hechos en mobile se reflejan en web.

Validacion sugerida:

- [ ] Abrir presupuesto viejo.
- [ ] Abrir presupuesto creado desde mobile.
- [ ] Abrir PDF.
- [ ] Editar partida y revisar total.

## Fase 9 - Integracion con cliente y caballo

Objetivo:

Conectar presupuestos con los flujos existentes de cliente y paciente.

Tareas:

- [ ] En detalle de cliente, mostrar seccion `Presupuestos` con ultimos presupuestos.
- [ ] En detalle de cliente, agregar boton `Nuevo presupuesto` preseleccionando cliente.
- [ ] En detalle de caballo, evaluar boton `Presupuesto` preseleccionando caballo y cliente.
- [ ] En Home, evaluar card de acceso rapido si se aprueba.
- [ ] Evitar duplicar logica de creacion; usar la misma pantalla con parametros iniciales.

Checkpoint 9:

- [ ] Presupuestos se sienten parte del flujo del vet.
- [ ] No obliga a buscar cliente cuando ya viene desde detalle.
- [ ] No agrega ruido innecesario al footer.

Validacion sugerida:

- [ ] Crear presupuesto desde cliente.
- [ ] Crear presupuesto desde caballo si se implementa.
- [ ] Volver correctamente al contexto anterior.

## Fase 10 - Offline y sincronizacion futura

Objetivo:

Decidir si presupuestos deben existir offline despues de validar la version online.

No implementar en v1 salvo aprobacion explicita.

Opciones:

- Solo lectura online: mas simple y seguro.
- Cache local de lista/detalle: util para consulta, sin crear offline.
- Creacion offline con outbox: mas costoso; requiere resolver folios temporales, conflictos y recalculos.

Tareas si se aprueba offline:

- [ ] Agregar tablas locales `budgets`, `budget_animals`, `budget_items`.
- [ ] Agregar presupuestos a `/mobile/bootstrap`.
- [ ] Agregar reconciliacion incremental por `updated_at` y `deleted_at` si existe soft delete.
- [ ] Crear outbox `budget_create`.
- [ ] Usar `client_uuid` o identificador local para presupuestos pendientes.
- [ ] Resolver folio temporal hasta que backend confirme.
- [ ] Manejar cambios de catalogo/precio mientras el presupuesto esta pendiente.

Checkpoint 10:

- [ ] Decision offline tomada con evidencia de uso real.
- [ ] Si queda online-only, queda documentado.

## Riesgos y puntos de cuidado

- Folios: no generar folio final en frontend; debe hacerlo backend.
- Tenant isolation: todo endpoint debe validar `tenant_id`.
- Caballos: no aceptar caballos que no sean del cliente seleccionado.
- Catalogo: no aceptar servicios inactivos ni de otro tenant.
- PDF: evitar duplicar generacion en app; mantenerlo en Laravel.
- Offline: crear presupuestos offline puede duplicar folios o usar precios desactualizados si no se diseña bien.
- UI: el flujo de alta puede crecer mucho; conviene wizard y no una sola pantalla larga.
- Eliminacion: borrar presupuestos desde mobile puede ser riesgoso; conviene dejarlo fuera de v1 salvo aprobacion.

## Orden recomendado de implementacion

1. Fase 0: cerrar alcance.
2. Fase 1: API lectura.
3. Fase 2: API creacion.
4. Fase 4: tipos y servicios en app.
5. Fase 5: rutas.
6. Fase 6: lista.
7. Fase 7: alta.
8. Fase 8: detalle/PDF.
9. Fase 9: integraciones con cliente/caballo.
10. Fase 10: offline solo si se aprueba despues.

## Primer bloque ejecutable sugerido

Alcance pequeno para empezar:

- Backend:
  - `GET /api/v1/budgets`
  - `GET /api/v1/budgets/{budget}`
  - `GET /api/v1/budgets/{budget}/pdf`
- App:
  - Tipos TypeScript.
  - Ruta `/tabs/presupuestos`.
  - Lista simple online.
  - Detalle solo lectura con boton PDF.

Checkpoint inicial:

- [ ] Vet puede consultar presupuestos desde app movil.
- [ ] Vet puede abrir PDF.
- [ ] No se puede crear todavia.
- [ ] No hay offline todavia.
- [ ] Web queda intacto.
