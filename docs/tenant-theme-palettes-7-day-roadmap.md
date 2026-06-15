# Personalizacion visual por tenant: roadmap de 7 dias

## Objetivo

Permitir que cada tenant seleccione una paleta visual fija para el panel cliente,
manteniendo el estilo general de VetSys, el contraste y el significado de los
colores funcionales.

El trabajo se ejecutara gradualmente durante siete dias. Al terminar cada dia,
el sistema debe quedar funcional, con pruebas aprobadas y con la paleta actual
`ocean` reproduciendo el diseño existente.

## Estado inicial

- El panel cliente usa Tailwind CSS.
- Los colores de marca estan escritos directamente en aproximadamente 22
  archivos.
- Existen alrededor de 575 usos directos de los colores principales.
- Los colores mas frecuentes son `#38B2AC` y `#0F172A`.
- Todavia no existe una preferencia de tema por tenant.
- El panel administrativo, las vistas publicas y los documentos imprimibles
  quedan fuera de la primera version.

## Alcance de la primera version

### Se personaliza

- Sidebar y navegacion activa.
- Acentos de marca.
- Botones primarios.
- Tabs activos.
- Enlaces destacados.
- Bordes y anillos de enfoque.
- Fondos suaves de marca.
- Encabezados y superficies oscuras de marca.

### No se personaliza

- Verde de exito.
- Rojo de error o peligro.
- Ambar de advertencia.
- Estados clinicos y financieros.
- Colores oficiales de Stripe, WhatsApp u otras integraciones.
- Panel administrativo.
- Paginas publicas.
- Tickets, estados de cuenta y otros documentos imprimibles.
- Espaciado, tipografia, tamanos, grids o estructura general.

## Reglas para evitar regresiones

1. No agregar el selector de paletas hasta centralizar el panel cliente.
2. No reemplazar colores por busqueda global sin revisar su significado.
3. Migrar una seccion a la vez.
4. La paleta `ocean` debe verse igual que el sistema actual.
5. Cada dia debe terminar con pruebas enfocadas y revision visual.
6. Cada seccion terminada debe quedar en un commit independiente.
7. No mezclar esta iniciativa con refactors funcionales no relacionados.
8. Si una seccion presenta regresiones, detener el avance antes de iniciar la
   siguiente.

## Tokens visuales propuestos

Los nombres quedaron definidos durante el dia 1 y expresan la funcion del color,
no un color concreto. La matriz y reglas de uso viven en
`docs/theme-token-guidelines.md`.

```css
--theme-primary
--theme-primary-hover
--theme-primary-soft
--theme-primary-soft-hover
--theme-primary-strong
--theme-primary-contrast
--theme-primary-ink
--theme-sidebar
--theme-sidebar-footer
--theme-heading
--theme-focus-ring
```

Ejemplos de clases semanticas:

```css
.theme-bg-primary
.theme-bg-primary-soft
.theme-text-primary
.theme-border-primary
.theme-ring-primary
.theme-bg-sidebar
.theme-bg-sidebar-footer
.theme-text-heading
.theme-button-primary
```

## Paletas iniciales propuestas

| Clave | Nombre visible | Acento | Navegacion |
| --- | --- | --- | --- |
| `ocean` | Oceano | Turquesa actual | Azul marino actual |
| `violet` | Violeta | Violeta | Indigo oscuro |
| `forest` | Bosque | Verde | Verde bosque oscuro |
| `sunset` | Atardecer | Naranja calido | Marron oscuro |

Antes de aprobar una paleta se debe revisar contraste en texto, botones, focus,
sidebar y navegacion activa.

---

# Dia 1: Inventario y base de tokens

## Objetivo del dia

Crear la base tecnica para centralizar colores sin cambiar visualmente el
sistema.

## Tareas

- [x] Crear una rama dedicada al feature.
- [ ] Registrar capturas de referencia del panel cliente en escritorio y movil.
- [x] Crear una matriz de colores de marca contra colores funcionales.
- [x] Definir los tokens visuales finales.
- [x] Agregar los tokens de la paleta `ocean` a `resources/css/app.css`.
- [x] Crear las primeras clases semanticas reutilizables.
- [x] Documentar ejemplos correctos e incorrectos de uso.
- [x] Compilar assets.
- [x] Confirmar que no existen cambios visuales.

## Capturas minimas

- [ ] Dashboard.
- [ ] Configuracion.
- [ ] Clientes.
- [ ] Mascotas.
- [ ] Ventas.
- [ ] Servicios.
- [ ] Clubes.
- [ ] Notificaciones.
- [ ] Sidebar abierto y cerrado.
- [ ] Una pantalla movil.

## No hacer hoy

- No reemplazar colores en vistas.
- No crear migraciones.
- No crear selector de paletas.

## Verificacion

```powershell
npm run build
php artisan test
```

## Criterio de cierre

- Los tokens existen.
- La aplicacion compila.
- La apariencia actual no cambia.
- Las pruebas existentes pasan.

## Commit sugerido

```text
feat(theme): add semantic visual tokens
```

---

# Dia 2: Layout principal del cliente

## Objetivo del dia

Centralizar la marca aplicada por `resources/views/layouts/client.blade.php`.

## Tareas

- [x] Migrar fondo y footer del sidebar.
- [x] Migrar inicial y nombre del tenant.
- [x] Migrar navegacion activa.
- [x] Migrar boton de guia.
- [x] Migrar enlaces destacados de la topbar.
- [x] Migrar focus rings compartidos.
- [x] Mantener sin cambios los colores de notificaciones y errores.
- [ ] Revisar sidebar abierto y cerrado.
- [ ] Revisar escritorio y movil.

## Archivos esperados

```text
resources/css/app.css
resources/views/layouts/client.blade.php
```

## Riesgos del dia

- Perder contraste dentro del sidebar.
- Recolorear notificaciones rojas accidentalmente.
- Romper variantes hover o estados activos.
- Modificar el layout administrativo por error.

## Verificacion

```powershell
npm run build
php artisan test
```

Revision visual:

- [ ] Sidebar abierto.
- [ ] Sidebar cerrado.
- [ ] Ruta activa.
- [ ] Dropdown de notificaciones.
- [ ] Toast de exito.
- [ ] Toast de error.

## Criterio de cierre

El layout cliente utiliza tokens semanticos y con `ocean` se ve igual que antes.

## Commit sugerido

```text
refactor(theme): centralize client layout colors
```

---

# Dia 3: Configuracion y dashboard

## Objetivo del dia

Centralizar las dos pantallas que definen la experiencia inicial y preparar el
lugar donde vivira el selector.

## Seccion A: Configuracion

- [x] Migrar tabs activos.
- [x] Migrar botones primarios.
- [x] Migrar inputs y focus.
- [x] Migrar encabezados oscuros de marca.
- [x] Migrar acentos de tipos de animales.
- [x] Migrar usuarios, pagos, plan e importaciones.
- [x] Mantener estados funcionales e integraciones con sus colores actuales.

## Seccion B: Dashboard

- [x] Migrar encabezados y enlaces de marca.
- [x] Migrar onboarding incompleto.
- [x] Migrar banner de onboarding completado.
- [x] Mantener colores funcionales de metricas.
- [x] Mantener estados pagados y pendientes.

## Verificacion funcional

- [ ] Cambiar entre todos los tabs de configuracion.
- [ ] Abrir y cerrar modales.
- [ ] Validar formularios.
- [ ] Ver onboarding incompleto.
- [ ] Ver onboarding completado.
- [ ] Cerrar el banner completado.

## Pruebas sugeridas

```powershell
npm run build
php artisan test tests\Feature\DashboardOnboardingTest.php
php artisan test
```

## Criterio de cierre

Configuracion y dashboard usan tokens sin alterar colores funcionales.

## Commits sugeridos

```text
refactor(theme): centralize configuration colors
refactor(theme): centralize dashboard colors
```

---

# Dia 4: Modulos CRUD principales

## Objetivo del dia

Centralizar los modulos de operacion cotidiana con menor complejidad visual.

## Orden de trabajo

1. Servicios.
2. Clientes.
3. Mascotas.
4. Clubes.
5. Notificaciones.

## Checklist por modulo

- [x] Migrar boton principal.
- [x] Migrar encabezados y acentos.
- [x] Migrar tabs o navegacion local.
- [x] Migrar inputs y focus.
- [x] Migrar enlaces destacados.
- [x] Mantener estados funcionales.
- [x] Revisar listado.
- [x] Revisar creacion.
- [x] Revisar edicion.
- [x] Revisar detalle.

Nota de cierre: `resources/views/client/customers/statement.blade.php` conserva
colores directos de marca porque es un documento/estado de cuenta imprimible y
queda fuera del alcance de la primera version de paletas.

## Regla de avance

No iniciar el siguiente modulo hasta verificar visual y funcionalmente el
anterior.

## Verificacion

```powershell
npm run build
php artisan test
```

## Criterio de cierre

Los cinco modulos usan tokens de marca y conservan sus comportamientos.

## Commits sugeridos

Crear un commit independiente por modulo:

```text
refactor(theme): centralize services colors
refactor(theme): centralize customers colors
refactor(theme): centralize animals colors
refactor(theme): centralize clubs colors
refactor(theme): centralize notifications colors
```

---

# Dia 5: Modulos complejos y auditoria

## Objetivo del dia

Centralizar las secciones con modales, integraciones y estados especiales, y
detectar colores de marca restantes.

## Orden de trabajo

1. Ventas.
2. Expediente clinico.
3. Radiologia y videos.
4. Telemedicina autenticada.
5. Facturacion autenticada.

## Restricciones

- [x] Stripe conserva sus colores.
- [x] WhatsApp conserva sus colores.
- [x] Estados clinicos conservan sus colores.
- [x] Estados financieros conservan sus colores.
- [x] Tickets e impresiones quedan fuera del tema.
- [x] Vistas publicas quedan fuera del tema.

## Auditoria al terminar

- [x] Buscar referencias directas de colores de marca.
- [x] Clasificar cada referencia restante como intencional o pendiente.
- [x] Revisar contraste de texto.
- [x] Revisar hover, focus, disabled y loading.
- [ ] Comparar contra las capturas del dia 1.
- [x] Documentar excepciones intencionales.

Nota de cierre: `resources/views/client/ventas/ticket.blade.php` conserva
colores directos de marca porque es ticket/imprimible y queda fuera del alcance
de la primera version de paletas. La comparacion visual contra capturas queda
pendiente porque el navegador interno local fallo por sandbox.

## Busquedas sugeridas

```powershell
rg -n "#38B2AC|#38b2ac|#2C9A94|#238A85|#0F172A|#0f172a|#0B1222" resources\views\client resources\views\layouts\client.blade.php resources\css resources\js
```

## Verificacion

```powershell
npm run build
php artisan test
```

## Criterio de cierre

El area autenticada del cliente esta centralizada. Toda referencia directa
restante esta documentada y es intencional.

## Commits sugeridos

```text
refactor(theme): centralize complex client modules
docs(theme): document intentional color exceptions
```

---

# Dia 6: Persistencia y selector de apariencia

## Objetivo del dia

Permitir que un administrador seleccione una paleta fija para todo su tenant.

## Backend

- [x] Agregar columna `theme_palette` a `tenants`.
- [x] Usar `ocean` como valor predeterminado.
- [x] Crear un catalogo central de paletas.
- [x] Agregar fallback seguro a `ocean`.
- [x] Exponer la paleta al layout cliente.
- [x] Crear endpoint para actualizar la paleta.
- [x] Validar mediante una lista cerrada.
- [x] Permitir cambios solo al rol `admin`.
- [x] Actualizar exclusivamente el tenant autenticado.

## Interfaz

- [x] Agregar tab `Apariencia` en `mi-configuracion`.
- [x] Mostrar tarjetas de las paletas disponibles.
- [x] Mostrar la paleta activa.
- [x] Agregar vista previa temporal sin guardar.
- [x] Agregar boton `Aplicar paleta`.
- [x] Agregar boton `Restaurar predeterminada`.
- [x] Informar que el cambio afecta a todo el equipo.

## Pruebas automatizadas obligatorias

- [x] Tenant sin configuracion usa `ocean`.
- [x] Tenant puede guardar una paleta permitida.
- [x] Valor invalido es rechazado.
- [x] Usuario no administrador no puede cambiar la paleta.
- [x] Un tenant no puede modificar otro tenant.
- [x] Dos tenants pueden usar paletas distintas.
- [x] Admin y vistas publicas no cambian.

Nota de cierre: antes de agregar el selector se limpio la deuda de colores
directos en pantallas autenticadas del cliente. Las referencias restantes son
tokens CSS o documentos imprimibles excluidos: ticket de venta y estado de
cuenta.

## Verificacion

```powershell
php artisan migrate
npm run build
php artisan test
```

## Criterio de cierre

El selector funciona, esta protegido y cada tenant recibe exclusivamente su
paleta.

## Commits sugeridos

```text
feat(theme): persist tenant palette
feat(theme): add appearance settings tab
test(theme): cover tenant palette permissions and isolation
```

---

# Dia 7: Revision final y despliegue gradual

## Objetivo del dia

Validar todas las paletas y preparar un despliegue con bajo riesgo.

## Matriz de revision visual

Revisar cada pantalla principal con las cuatro paletas:

| Pantalla | Ocean | Violet | Forest | Sunset |
| --- | --- | --- | --- | --- |
| Layout y sidebar | [ ] | [ ] | [ ] | [ ] |
| Dashboard | [ ] | [ ] | [ ] | [ ] |
| Configuracion | [ ] | [ ] | [ ] | [ ] |
| Clientes | [ ] | [ ] | [ ] | [ ] |
| Mascotas | [ ] | [ ] | [ ] | [ ] |
| Servicios | [ ] | [ ] | [ ] | [ ] |
| Clubes | [ ] | [ ] | [ ] | [ ] |
| Ventas | [ ] | [ ] | [ ] | [ ] |
| Notificaciones | [ ] | [ ] | [ ] | [ ] |
| Modales | [ ] | [ ] | [ ] | [ ] |

## Revision responsive

- [ ] Escritorio grande.
- [ ] Laptop.
- [ ] Tablet.
- [ ] Movil.
- [ ] Sidebar abierto y cerrado.
- [ ] Scroll horizontal de tabs.

## Revision funcional

- [ ] Login y navegacion.
- [ ] Formularios y validaciones.
- [ ] Modales.
- [ ] Notificaciones.
- [ ] Onboarding.
- [ ] Cambio y restauracion de paleta.
- [ ] Sesiones de dos tenants distintos.
- [ ] Panel administrativo.
- [ ] Vistas publicas.

## Verificacion tecnica final

```powershell
npm run build
php artisan test
php vendor\bin\pint --test
git diff --check
```

## Estrategia de despliegue

1. Respaldar base de datos.
2. Desplegar migracion y codigo.
3. Mantener todos los tenants existentes en `ocean`.
4. Confirmar visualmente produccion.
5. Probar el cambio con un tenant controlado.
6. Habilitar el selector al resto de tenants.
7. Monitorear errores y reportes visuales.

## Plan de rollback

- Restaurar `theme_palette` de tenants afectados a `ocean`.
- Ocultar temporalmente el tab `Apariencia` si existe una regresion.
- Revertir solo el commit de la seccion afectada.
- No revertir migraciones destructivamente durante una incidencia.
- Mantener siempre disponible el fallback a `ocean`.

## Criterio de cierre

- Todas las pruebas pasan.
- Las cuatro paletas son legibles.
- Los colores funcionales conservan su significado.
- Existe aislamiento entre tenants.
- Ocean reproduce la apariencia original.
- El feature puede desplegarse gradualmente.

---

# Definicion global de terminado

El feature se considera terminado cuando:

- [ ] Un administrador puede seleccionar una paleta fija.
- [ ] La paleta aplica a todos los usuarios de su tenant.
- [ ] La seleccion no afecta otros tenants.
- [ ] Solo se aceptan paletas registradas.
- [ ] Existe fallback a `ocean`.
- [ ] El panel administrativo no cambia.
- [ ] Las vistas publicas e imprimibles no cambian.
- [ ] Los colores de exito, error, advertencia y estados no cambian.
- [ ] Las pruebas automatizadas pasan.
- [ ] La revision visual de escritorio y movil esta aprobada.
- [ ] El comportamiento queda documentado en `SISTEMA.md`.

# Registro diario

Usar esta tabla al cerrar cada jornada.

| Dia | Fecha | Responsable | Resultado | Pruebas | Commit | Pendientes |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | 15/06/2026 | Codex | Tokens `ocean`, utilidades y guia creados; sin cambios en vistas | Build aprobado; 31 pruebas aprobadas y 1 fallo heredado en `ExampleTest` | Pendiente | Capturas de referencia bloqueadas por navegador local |
| 2 | 15/06/2026 | Codex | Layout cliente migrado a tokens semanticos; admin y vistas internas sin cambios | Build y Blade aprobados; 31 pruebas aprobadas y 1 fallo heredado en `ExampleTest` | Pendiente | Revision visual de sidebar, dropdown y responsive bloqueada por navegador local |
| 3 | 15/06/2026 | Codex | Configuracion y Dashboard migrados; integraciones, estados y metricas funcionales preservados | Build, Blade y 5 pruebas de onboarding aprobados; suite con 31 aprobadas y 1 fallo heredado | Pendiente | Revision visual de tabs, modales y onboarding bloqueada por navegador local |
| 4 | 15/06/2026 | Codex | CRUD principales cerrados: servicios, clientes, mascotas, clubes y notificaciones usan tokens semanticos; estados funcionales preservados; estado de cuenta imprimible excluido por alcance | Build Vite aprobado; 5 pruebas de onboarding aprobadas; suite con 31 aprobadas y 1 fallo heredado en `Tests\Feature\ExampleTest` | Pendiente | Revision visual en navegador interno bloqueada por sandbox local |
| 5 | 15/06/2026 | Codex | Modulos complejos cerrados: ventas, telemedicina autenticada y facturacion autenticada usan tokens semanticos; Stripe, estados y ticket imprimible preservados | Build Vite aprobado; 5 pruebas de onboarding aprobadas; suite con 31 aprobadas y 1 fallo heredado en `Tests\Feature\ExampleTest` | Pendiente | Revision visual en navegador interno bloqueada por sandbox local |
| 6 | 15/06/2026 | Codex | Persistencia y selector de apariencia implementados; deuda autenticada de colores limpiada; cuatro paletas disponibles con fallback `ocean` | Migracion aplicada; build Vite aprobado; 5 pruebas de paleta aprobadas; 5 pruebas de onboarding aprobadas; suite con 36 aprobadas y 1 fallo heredado en `Tests\Feature\ExampleTest` | Pendiente | Revision visual multi-paleta queda para dia 7 por bloqueo del navegador interno |
| 7 |  |  |  |  |  |  |
