# Road Map - Servicios y cortes por club

## 1. Objetivo

Crear un flujo contable para clubes donde el veterinario pueda entrar a un club, agregar servicios libres con cantidad y precio editable, imprimir la nota generada y, cuando el tenant trabaje en `monthly_cutoff`, generar cortes mensuales del club.

Para esta primera etapa el club se trata como entidad general. No se requiere seleccionar clientes ni caballos. Los caballos relacionados al club siguen existiendo como contexto operativo, pero no condicionan la captura del cargo.

Ejemplo esperado:

1. El usuario entra a un club.
2. Presiona `Agregar servicios`.
3. Captura `Vacuna` con cantidad `10` y precio editable.
4. Guarda la nota/cargo del club.
5. En modo notas puede imprimir esa nota.
6. En modo `monthly_cutoff`, ese cargo aparece en el corte del club.

---

## 2. Hallazgos verificados

### 2.1 Flujo actual de clientes

El boton `Agregar servicios` en cliente apunta a `client.ventas.create` con `customer_id`. La captura actual depende de:

- `notes.customer_id` obligatorio.
- `note_details.animal_id` opcional en base de datos, pero requerido por el formulario/controlador actual.
- Catalogo compartido en `catalog_items`, con precio editable en la nota mediante `price_at_sale`.
- Corte mensual de cliente basado en `CustomerStatementGenerator`, que consulta `note_details` a traves de `notes.customer_id`.

Archivos revisados:

- `app/Http/Controllers/Client/NoteController.php`
- `app/Models/Note.php`
- `app/Models/NoteDetail.php`
- `app/Services/CustomerStatementGenerator.php`
- `resources/views/client/customers/show.blade.php`
- `resources/views/client/ventas/create.blade.php`

### 2.2 Flujo actual de clubes

El modulo de clubes ya existe con:

- listado de clubes;
- edicion de datos del club;
- administracion de miembros;
- archivos Coggins;
- relacion `Club hasMany Animal`;
- `animals.club_id` para membresia actual.

No existe todavia una cuenta, nota, pago, servicio realizado o corte propio del club.

Archivos revisados:

- `app/Http/Controllers/Client/ClubController.php`
- `app/Models/Club.php`
- `resources/views/client/clubes/index.blade.php`
- `resources/views/client/clubes/edit.blade.php`
- `database/migrations/2026_06_04_040238_create_clubs_table.php`
- `database/migrations/2026_06_04_040324_add_club_id_field_to_animals.php`

### 2.3 Riesgo principal

No conviene forzar esta fase dentro del flujo actual de `notes.customer_id`, porque hoy muchas pantallas, pagos, portal, saldos y cortes asumen que una nota pertenece a un cliente.

La implementacion mas limpia para este requerimiento es crear un flujo contable paralelo para clubes:

- `club_notes`
- `club_note_details`
- `club_payments`, si se requiere abonos para clubes
- `club_statements`, si se requiere cortes mensuales guardados/imprimibles

Esto evita romper:

- saldos de clientes;
- portal de clientes;
- pagos por nota;
- cortes de clientes;
- historial de caballos;
- facturacion futura basada en notas de cliente.

---

## 3. Decision funcional base

Para la fase 1 se adopta esta regla:

**El club es el pagador del cargo.**

Los servicios creados desde el club no pertenecen a ningun cliente y no requieren caballo. La cantidad representa la cantidad operativa atendida, por ejemplo 10 vacunas aplicadas a caballos del club, aunque no se seleccionen los 10 caballos uno por uno.

Los miembros del club pueden mostrarse como referencia en la pantalla del club, pero no forman parte obligatoria del cargo.

---

## 4. Alcance por fases

### Fase 0 - Alinear contrato y nombres

Estado: cerrado.

Objetivo:

Definir nombres, rutas y comportamiento antes de tocar tablas.

Decisiones a cerrar:

- Nombre visible de la seccion: `Servicios`, `Cuenta`, o `Servicios del club`.
- Nombre de documento imprimible en modo notas: `Nota de club` o `Servicio de club`.
- En `monthly_cutoff`, usar `Corte del club` como equivalente a cortes de cliente.
- Si los abonos a club entran en esta primera version o quedan para fase posterior. Decision tomada: quedan fuera de la primera entrega.

Checkpoint:

- Documento actualizado con decisiones cerradas.
- El primer alcance autorizado ya permite cambios de codigo para persistencia y captura inicial.

Definition of done:

- El flujo puede explicarse en una frase sin ambiguedad: "Los servicios de club crean cargos del club sin cliente ni caballo obligatorio".

---

### Fase 1 - Modelo de datos de notas de club

Estado: en progreso.

Objetivo:

Agregar la persistencia minima para registrar servicios libres del club.

Propuesta de tablas:

`club_notes`

- `id`
- `tenant_id`
- `club_id`
- `folio`
- `public_token`
- `total`
- `status`: `PENDIENTE`, `PAGADA`, `CANCELADA`
- `date_at`
- `visible_to_customer` no aplica de inicio; evitar portal hasta decidir alcance.
- timestamps
- soft deletes
- unique `tenant_id + folio`

`club_note_details`

- `id`
- `tenant_id`
- `club_note_id`
- `catalog_item_id`
- `quantity`
- `price_at_sale`
- `tax_at_sale`
- `subtotal`
- timestamps

Modelos:

- `ClubNote`
- `ClubNoteDetail`
- relaciones en `Club`.

Checkpoint:

- Migraciones creadas.
- Modelos y relaciones listos.
- UI inicial ya puede usar estas tablas para guardar notas de club.

Validacion:

- `php artisan migrate` en entorno local.
- `php -l` para modelos/migraciones tocadas.
- Verificar que no se alteran `notes`, `note_details`, `payments` ni `customer_statements`.

Definition of done:

- Se puede crear una `ClubNote` con N detalles desde codigo o prueba sin depender de `customer_id` ni `animal_id`.

---

### Fase 2 - Captura de servicios desde club

Estado: en progreso.

Objetivo:

Agregar boton `Agregar servicios` en `client.clubes.edit` y pantalla de captura para club.

Rutas candidatas:

- `GET /client/clubes/{club}/servicios/create`
- `POST /client/clubes/{club}/servicios`

Controlador candidato:

- Crear `ClubServiceNoteController` o extender `ClubController` solo si el alcance queda pequeno.

UI:

- Reutilizar el buscador de articulos de `NoteController@searchItems` o extraerlo a un endpoint compartido.
- Quitar pasos de cliente y pacientes.
- Mantener fecha de nota.
- Mantener tabla de conceptos con cantidad y precio editable.
- Calcular total como suma directa de renglones, no subtotal por mascota.

Reglas:

- No pedir cliente.
- No pedir caballo.
- Permitir productos/servicios activos del catalogo del tenant.
- Consumir inventario si el item tiene inventario, multiplicando por la cantidad capturada.
- Folio separado de notas de cliente. Recomiendo prefijo `CLUB-00001` o `VC-00001`.

Checkpoint:

- Boton visible en la pantalla de club.
- Captura guarda `club_notes` y `club_note_details`.
- Redirecciona de vuelta al club con mensaje claro.

Validacion:

- `php artisan route:list --name=clubes`
- `php artisan view:cache`
- Guardar una nota de club manualmente desde navegador local.
- Verificar subtotal y total con cantidades mayores a 1.

Definition of done:

- El veterinario puede registrar "10 vacunas" al club sin seleccionar miembros.

---

### Fase 3 - Vista e impresion de nota de club

Estado: en progreso.

Objetivo:

Permitir abrir e imprimir la nota creada desde club en modo notas.

Rutas candidatas:

- `GET /client/clubes/{club}/notas/{clubNote}`
- `GET /client/clubes/{club}/notas/{clubNote}/ticket`

Vistas candidatas:

- `resources/views/client/clubes/notes/show.blade.php`
- `resources/views/client/clubes/notes/ticket.blade.php`

UI en club:

- Nueva pestana `Servicios` o `Notas`.
- Tabla con fecha, folio, cantidad de conceptos, total, estado y acciones.
- Acciones: ver, imprimir, editar/eliminar solo si no hay pagos o cortes asociados.

Checkpoint:

- Nota de club imprimible.
- Historial basico dentro del club.
- Acciones de imprimir y editar disponibles desde la pestana `Servicios`.
- Edicion reutiliza la captura de servicios y revierte/consume inventario nuevamente cuando aplica.

Validacion:

- Ver nota en HTML.
- Abrir ticket/PDF/print view.
- Confirmar que el documento muestra club, fecha, folio, conceptos, cantidad, precio y total.

Definition of done:

- En modo notas, el usuario puede registrar servicios del club e imprimir el documento sin pasar por cliente.

---

### Fase 4 - Cortes mensuales de club

Estado: en progreso.

Objetivo:

Agregar equivalente de cortes para clubes en tenants con `monthly_cutoff`.

Propuesta:

- Crear `club_statements`.
- Crear `ClubStatementGenerator`.
- Reutilizar estructura conceptual de `CustomerStatementGenerator`, pero filtrando por `club_notes.club_id`.

Tabla `club_statements`:

- `id`
- `tenant_id`
- `club_id`
- `period_start`
- `period_end`
- `cutoff_day`
- `previous_balance`
- `period_charges`
- `period_payments`
- `ending_balance`
- `pdf_path`
- `generated_at`
- `status`
- timestamps
- unique `tenant_id + club_id + period_start + period_end`

Preguntas antes de implementar:

- El club tendra dia de corte propio o se usara un default del tenant?
- Se requieren abonos de club en fase 4 o los cortes solo muestran cargos?
- Se necesita publicar cortes de club a algun portal? De inicio, no.

Checkpoint:

- Preview de corte de club.
- Generacion manual de corte por rango.
- PDF o HTML imprimible del corte.

Validacion:

- Crear cargos en un rango.
- Generar corte.
- Verificar que cargos ya cubiertos por otro corte no se dupliquen.
- Verificar que cargos de otros clubes no aparezcan.

Definition of done:

- En `monthly_cutoff`, los servicios de club aparecen en cortes de club separados de cortes de clientes.

---

### Fase 5 - Abonos de club

Estado: pendiente.

Objetivo:

Permitir registrar pagos/abonos contra la cuenta global del club, si el negocio lo requiere.

Propuesta:

- Crear `club_payments`.
- Relacionar con metodo de pago del tenant.
- En modo notas, opcionalmente permitir pago directo a una nota de club.
- En `monthly_cutoff`, usar abonos globales de club, igual que clientes usan abonos globales.

Checkpoint:

- Boton `Registrar abono` en cuenta del club.
- Balance de club: cargos menos abonos.
- Corte mensual incluye abonos del periodo.

Validacion:

- Registrar abono parcial.
- Registrar abono mayor al saldo y definir si se permite credito a favor.
- Confirmar que abonos de cliente no afectan club y viceversa.

Definition of done:

- El club tiene saldo propio y abonos propios sin tocar la cuenta de clientes.

---

### Fase 6 - Edicion, eliminacion y protecciones

Estado: pendiente.

Objetivo:

Agregar reglas de seguridad para evitar inconsistencias contables.

Reglas sugeridas:

- Nota de club sin pagos/cortes: editable y eliminable.
- Nota de club con pagos: bloquear edicion/eliminacion o exigir anulacion controlada.
- Nota de club incluida en corte: bloquear eliminacion o marcar corte como requiere recalculo.
- Revertir inventario si se elimina o edita una nota de club.

Avance 2026-08-25:

- Agregado boton de eliminar nota de club desde la pestana `Servicios`.
- Agregada ruta `client.clubes.services.destroy`.
- La eliminacion revierte inventario, elimina detalles y hace soft delete de la nota.
- Agregado punto de extension `abortIfClubNoteHasFinancialMovements()` para bloquear edicion/eliminacion cuando existan abonos o cortes de club.

Checkpoint:

- Guardas de backend implementadas.
- UI oculta o deshabilita acciones no permitidas.
- Mensajes de error claros.

Validacion:

- Intentar editar/eliminar nota con pagos.
- Intentar editar/eliminar nota incluida en corte.
- Verificar inventario despues de editar/eliminar.

Definition of done:

- No se puede dejar una nota de club, corte o inventario en estado incoherente desde UI ni request directo.

---

### Fase 7 - Reportes y navegacion

Estado: en progreso.

Objetivo:

Hacer visible la actividad de clubes sin mezclarla con clientes.

Superficies candidatas:

- `client.clubes.index`: total de cargos del mes por club.
- `client.clubes.edit`: tarjetas de balance, servicios del mes, ultimo servicio.
- `client.ventas.index`: decidir si se muestran o no notas de club. Recomendacion: no mezclarlas al inicio.
- Dashboard: solo agregar metricas de club si el negocio lo pide.

Checkpoint:

- Historial de servicios de club filtrable.
- KPIs locales del club.

Validacion:

- Buscar por folio, servicio y fecha.
- Confirmar que cliente/caballo no aparecen como obligatorios.

Definition of done:

- El usuario encuentra y audita servicios de club desde el modulo de clubes.

---

## 5. Plan de ejecucion recomendado

Ejecutar en pasos pequenos:

1. Fase 0: cerrar nombres y alcance de abonos.
2. Fase 1: crear tablas/modelos sin UI.
3. Fase 2: boton y captura simple.
4. Fase 3: historial e impresion de nota.
5. Fase 4: cortes mensuales.
6. Fase 5: abonos, solo si el negocio confirma que el club paga saldos.
7. Fase 6: protecciones contables.
8. Fase 7: reportes/KPIs.

No avanzar de una fase a la siguiente sin actualizar este documento con:

- fecha;
- archivos tocados;
- validacion ejecutada;
- pendientes reales.

---

## 6. Checkpoints de avance

### Checkpoint 0 - Planeacion

Estado: cerrado.

Fecha: 2026-08-25.

Hallazgo:

El flujo de clientes no se debe reutilizar directamente porque depende de `customer_id` y de la seleccion de pacientes. El flujo de club necesita cuenta propia para evitar mezclar cargos de clubes con saldos/cortes de clientes.

Pendiente:

- Confirmar si `CLUB-00001` queda como prefijo final o se cambia antes de salir a produccion.

### Checkpoint 1 - Persistencia

Estado: en progreso.

Meta:

Migraciones y modelos para notas/detalles de club.

Avance 2026-08-25:

- Creadas migraciones `club_notes` y `club_note_details`.
- Creados modelos `ClubNote` y `ClubNoteDetail`.
- Agregadas relaciones `Club::notes()` y `Tenant::clubNotes()`.
- Validado con `php -l` en modelos/controlador/rutas.
- Ejecutado `php artisan migrate` localmente.

### Checkpoint 2 - Captura

Estado: en progreso.

Meta:

Boton `Agregar servicios` en club y formulario funcional sin cliente/caballo.

Avance 2026-08-25:

- Agregado boton `Agregar servicios` en `resources/views/client/clubes/edit.blade.php`.
- Agregada pestana `Servicios` con historial basico de notas del club.
- Agregadas rutas `client.clubes.services.create` y `client.clubes.services.store`.
- Agregado `ClubServiceNoteController` para crear notas de club.
- Agregada vista `resources/views/client/clubes/services/create.blade.php`.
- Abonos quedan explicitamente fuera de esta primera captura.
- Validado con `php artisan route:list --name=clubes`.
- Validado con `php artisan view:cache` y luego `php artisan view:clear`.

### Checkpoint 3 - Impresion

Estado: en progreso.

Meta:

Nota de club visible e imprimible.

Avance 2026-08-25:

- Agregadas rutas `client.clubes.services.edit`, `client.clubes.services.update` y `client.clubes.services.ticket`.
- Agregado ticket imprimible en `resources/views/client/clubes/services/ticket.blade.php`.
- Agregados botones por nota para imprimir y editar desde la pestana `Servicios`.
- Eliminado el boton duplicado de `Agregar servicios` dentro del bloque de historial.
- Mensaje de exito del club ahora se oculta automaticamente.

### Checkpoint 4 - Corte mensual

Estado: pendiente.

Meta:

Corte de club por rango con cargos no duplicados.

### Checkpoint 5 - Abonos

Estado: pendiente.

Meta:

Cuenta de club con abonos propios, si se aprueba.

### Checkpoint 6 - Protecciones

Estado: pendiente.

Meta:

Bloqueos y recalculos para notas con pagos/cortes/inventario.

Avance 2026-08-25:

- Eliminacion basica implementada con reversion de inventario.
- Preparado gancho backend para bloquear notas con futuros movimientos financieros.
- Pendiente completar bloqueo real cuando existan `club_payments` o `club_statements`.

---

## 7. Preguntas abiertas

1. Que etiqueta final usaremos: `Servicios del club`, `Cuenta del club` o `Notas del club`.
2. Que prefijo de folio prefieres: `CLUB-00001`, `VC-00001` u otro.
3. Los abonos del club entran desde la primera version o primero solo registramos/imprimimos cargos.
4. El corte de club debe usar un dia de corte propio por club o un dia default del tenant.
5. En reportes generales, los servicios de club deben sumar a ventas totales del tenant desde el inicio o mostrarse aparte.
