# Roadmap: visibilidad granular de cards KPI

Fecha de creacion: 2026-07-21
Estado: completado y validado manualmente en navegador.
Ruta de configuracion objetivo: `/client/mi-configuracion?tab=preferencias`

## Objetivo

Permitir que un administrador del tenant decida en que pantallas del panel cliente se muestran u ocultan los cards superiores de KPIs, de forma granular por superficie.

El caso base solicitado es poder ocultar los KPIs de `/client/customers` sin afectar los KPIs de `/client/customers/{id}`. El detalle de cliente conserva ademas su control local existente para ocultar KPIs exclusivamente dentro de esa pantalla.

## Decision de diseno

No usar un boolean global unico como `show_kpi_cards`, porque no permite diferenciar listado de clientes contra detalle de cliente.

Usar una preferencia JSON por tenant, por ejemplo `tenants.kpi_visibility`, normalizada por una clase de soporte. Todas las claves deben tener default `true` para que tenants existentes conserven el comportamiento actual.

Keys iniciales propuestas:

| Key | Pantalla | Ruta principal | Archivo KPI |
| --- | --- | --- | --- |
| `dashboard` | Dashboard | `/client/dashboard` | `resources/views/client/dashboard.blade.php` |
| `customers_index` | Listado de clientes | `/client/customers` | `resources/views/client/customers/index.blade.php` |
| `customer_show` | Detalle de cliente | `/client/customers/{customer}` | `resources/views/client/customers/show.blade.php` |
| `animals_index` | Caballos / pacientes | `/client/caballos` o ruta equivalente | `resources/views/client/animals/index.blade.php` |
| `ventas_index` | Ventas | `/client/ventas` | `resources/views/client/ventas/index.blade.php` |
| `servicios_index` | Servicios / productos | `/client/servicios` | `resources/views/client/servicios/index.blade.php` |
| `clubes_index` | Clubes | `/client/clubes` | `resources/views/client/clubes/index.blade.php` |

## Regla especial: detalle de cliente

`resources/views/client/customers/show.blade.php` ya tiene una funcion local de Alpine para ocultar KPIs solo en esa pagina (`hideCustomerKpis`).

La regla final debe ser:

```text
mostrar KPIs de detalle cliente =
    TenantKpiVisibility::isVisible($tenant, 'customer_show')
    y el toggle local de esa pantalla no esta ocultando los KPIs
```

Si la preferencia global granular `customer_show` esta apagada, el toggle local no debe permitir volver a mostrar esos KPIs.

## Checkpoints de implementacion

### Checkpoint 1: persistencia

- [x] Crear migracion para agregar `kpi_visibility` JSON nullable/default a `tenants`.
- [x] Agregar `kpi_visibility` a `$fillable` en `App\Models\Tenant`.
- [x] Agregar cast `kpi_visibility => array`.
- [x] Garantizar defaults `true` para tenants existentes sin valor.

Definicion de done:

- `php artisan migrate` aplica la columna.
- Un tenant sin `kpi_visibility` se comporta como antes: todos los KPIs visibles.

### Checkpoint 2: soporte centralizado

- [x] Crear `App\Support\TenantKpiVisibility`.
- [x] Incluir metodos `all()`, `keys()`, `normalize()` e `isVisible(?Tenant $tenant, string $key)`.
- [x] Mantener etiquetas y descripciones de UI en esa clase para evitar duplicacion.

Definicion de done:

- Ninguna vista valida claves manualmente.
- Las claves invalidas no entran al JSON guardado.

### Checkpoint 3: rutas y controlador de preferencias

- [x] Agregar ruta `PATCH /client/mi-configuracion/kpis`.
- [x] Nombre sugerido: `client.mi-configuracion.kpis.update`.
- [x] Agregar metodo `updateKpiVisibility()` en `App\Http\Controllers\Client\ConfiguracionController`.
- [x] Restringir guardado a tenant admin, siguiendo el patron de `updateMenuModules()` y `updateBillingMode()`.
- [x] Cargar `$kpiVisibilityOptions` y `$visibleKpiCards` en `index()`.

Definicion de done:

- Usuarios no admin no pueden guardar.
- El redirect vuelve a `mi-configuracion?tab=preferencias`.

### Checkpoint 4: UI en Preferencias

- [x] Agregar card "Cards de KPIs" dentro de la pestana Preferencias.
- [x] Mostrar checkboxes por pantalla con textos claros.
- [x] Mantener el estilo visual de los cards existentes de preferencias.
- [x] Incluir nota: "Esto no cambia permisos ni calculos, solo la visibilidad de los cards superiores."

Definicion de done:

- El usuario puede apagar solo `Clientes` y dejar activo `Detalle de cliente`.
- El estado guardado se refleja al recargar la pagina.

### Checkpoint 5: aplicacion en vistas KPI

- [x] Envolver `dashboard.blade.php` con key `dashboard`.
- [x] Envolver `customers/index.blade.php` con key `customers_index`.
- [x] Envolver `customers/show.blade.php` con key `customer_show` respetando `hideCustomerKpis`.
- [x] Envolver `animals/index.blade.php` con key `animals_index`.
- [x] Envolver `ventas/index.blade.php` con key `ventas_index`.
- [x] Envolver `servicios/index.blade.php` con key `servicios_index`.
- [x] Envolver `clubes/index.blade.php` con key `clubes_index`.

Definicion de done:

- Apagar `customers_index` oculta KPIs en `/client/customers`.
- `customer_show` encendido mantiene KPIs en `/client/customers/{id}`.
- `customer_show` apagado oculta KPIs en detalle aunque el toggle local exista.

### Checkpoint 6: verificacion

- [x] `php -l` en controlador, modelo, support class y migracion.
- [x] `php artisan route:list --path=client/mi-configuracion`.
- [x] `php artisan view:cache`.
- [x] `php artisan view:clear`.
- [x] QA manual en `/client/mi-configuracion?tab=preferencias`.
- [x] QA manual en `/client/customers` y `/client/customers/2015`.

Definicion de done:

- No hay errores de compilacion Blade.
- La preferencia granular sobrevive refresh.
- Los KPIs no se calculan ni se muestran visualmente cuando su bloque esta apagado en Blade.

## Archivos esperados

Archivos nuevos probables:

- `database/migrations/YYYY_MM_DD_HHMMSS_add_kpi_visibility_to_tenants_table.php`
- `app/Support/TenantKpiVisibility.php`

Archivos a modificar:

- `app/Models/Tenant.php`
- `app/Http/Controllers/Client/ConfiguracionController.php`
- `routes/web.php`
- `resources/views/client/mi-configuracion/index.blade.php`
- `resources/views/client/dashboard.blade.php`
- `resources/views/client/customers/index.blade.php`
- `resources/views/client/customers/show.blade.php`
- `resources/views/client/animals/index.blade.php`
- `resources/views/client/ventas/index.blade.php`
- `resources/views/client/servicios/index.blade.php`
- `resources/views/client/clubes/index.blade.php`

## Notas de continuidad

- Antes de implementar, revisar el estado actual del layout porque existe trabajo reciente no commiteado en `resources/views/layouts/client.blade.php` para accesos principales globales.
- No borrar el toggle local de `customers/show.blade.php` sin decision explicita; integrarlo con la preferencia global granular.
- Si se agregan nuevas pantallas con KPIs, registrar una nueva key en `TenantKpiVisibility` y actualizar este roadmap.

## Registro de ejecucion

| Fecha | Fase | Estado | Notas |
| --- | --- | --- | --- |
| 2026-07-21 | Fase 1 - persistencia y contrato | Completado | Se agrego la migracion `kpi_visibility`, fillable/cast en `Tenant`, clase `TenantKpiVisibility` con defaults visibles y carga de opciones en `ConfiguracionController@index`. Sin cambios de UI ni ocultamiento Blade aun. |
| 2026-07-21 | Fase 2 - guardado backend | Completado | Se agrego `PATCH /client/mi-configuracion/kpis`, metodo `updateKpiVisibility()` con validacion granular, restriccion a tenant admin y guardado normalizado donde los checkboxes no enviados quedan como `false`. Sin cambios de UI ni ocultamiento Blade aun. |
| 2026-07-21 | Fase 3 - UI en Preferencias | Completado | Se agrego el card "Cards de KPIs" en la pestana Preferencias, con checkboxes por pantalla, nota de alcance y submit a `client.mi-configuracion.kpis.update`. Aun falta aplicar la visibilidad en las vistas KPI. |
| 2026-07-21 | Fase 4 - aplicacion en vistas KPI | Completado | Se envolvieron los bloques KPI de dashboard, clientes, detalle de cliente, pacientes, ventas, servicios y clubes. En detalle de cliente, si `customer_show` esta apagado globalmente, no se renderiza el bloque ni el boton local de mostrar/ocultar KPIs. |
| 2026-07-21 | Cierre QA | Completado | El usuario confirmo prueba manual exitosa en navegador: la preferencia guarda y la visibilidad granular de KPIs funciona correctamente. |

## Resultado final

- La preferencia granular vive en `tenants.kpi_visibility`.
- El contrato central de keys, labels, defaults y normalizacion vive en `App\Support\TenantKpiVisibility`.
- La UI de Preferencias permite activar/desactivar KPIs por pantalla.
- Las vistas KPI consultan `TenantKpiVisibility::isVisible(...)` antes de renderizar sus cards superiores.
- En detalle de cliente, `customer_show = false` bloquea globalmente los KPIs y oculta el toggle local; `customer_show = true` conserva el toggle local `hideCustomerKpis`.
- Graphify debe refrescarse con `graphify update .` y `graphify-out/` permanece ignorado para no subir artefactos a produccion.
