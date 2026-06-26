# Roadmap: alta de tenants, trials, suscripciones y acceso

Estado: planeacion

Fecha: 2026-06-25

## Objetivo

Ordenar el flujo de creacion de tenants y manejo de suscripciones para que un
tenant no pueda usar el sistema solo por tener `plan_id`.

El acceso debe depender de un contrato verificable:

- plan asignado;
- suscripcion creada;
- pago confirmado, checkout pendiente o trial vigente;
- fechas de inicio y fin claras;
- estado del tenant compatible con acceso.

## Problema actual

Hoy el sistema mezcla tres conceptos distintos:

1. `tenants.plan_id` se usa como "plan actual".
2. `tenant_subscriptions` representa la suscripcion real, pero puede no existir.
3. `tenant_payments` representa cobros, pero puede no existir aunque el tenant
   tenga plan asignado.

Caso observado:

- tenant `3828`;
- `status = active`;
- `is_active = 1`;
- `plan_id = 1`;
- sin `trial_ends_at`;
- sin `subscription_ends_at`;
- sin filas en `tenant_subscriptions`;
- sin filas en `tenant_payments`.

Con ese estado, el tenant parece tener plan contratado aunque no existe
suscripcion ni pago que lo respalde.

## Principio de dominio

`tenants.plan_id` no debe ser suficiente para habilitar acceso operativo.

Debe interpretarse como el plan seleccionado o plan vigente cacheado, pero la
fuente de verdad para acceso debe ser una suscripcion vigente respaldada por:

- pago `paid`;
- trial vigente con pago de monto `0`;
- plan gratuito, si se define explicitamente como gratuito.

## Estados funcionales

### Sin plan

No hay `plan_id` ni suscripcion pendiente.

Resultado:

- no acceso operativo;
- master debe asignar un plan antes de continuar.

### Plan asignado pendiente de pago

Hay plan y existe una suscripcion/pago en estado `pending`.

Resultado:

- no acceso operativo;
- puede existir modo de facturacion limitada para que el tenant pague;
- master puede reenviar link o confirmar pago manual.

### Trial activo

Hay suscripcion activa con `trial_ends_at` vigente y pago `paid` de monto `0`.

Resultado:

- acceso operativo permitido hasta fin del trial;
- el sistema tiene evidencia de por que el acceso es valido;
- al vencer, se bloquea si no existe pago real posterior.

### Suscripcion activa pagada

Hay suscripcion activa vigente y pago `paid` asociado al periodo.

Resultado:

- acceso operativo permitido.

### Vencido

La suscripcion o trial termino y no hay otro periodo activo.

Resultado:

- no acceso operativo;
- mostrar pago/renovacion;
- API movil responde bloqueo de pago.

### Bloqueado administrativamente

Tenant suspendido, cancelado o inactivo.

Resultado:

- no acceso operativo;
- no basta con pagar, el master debe reactivar el tenant.

## Flujo de creacion de tenant

### Paso 1: datos base

El master registra:

- nombre;
- slug;
- razon social;
- email corporativo;
- telefono;
- plan seleccionado.

El plan seleccionado no debe activar acceso por si solo.

### Paso 2: decision de facturacion inicial

Agregar una seccion obligatoria:

**Como se manejara el acceso inicial?**

Opciones:

1. Trial.
2. Pago con Stripe.
3. Transferencia pendiente.
4. Pago confirmado por transferencia.
5. Pago confirmado en efectivo.
6. Paga despues.

## Flujo: trial

Campos:

- checkbox `Otorgar trial`;
- plan de prueba;
- fecha inicio;
- fecha fin trial;
- notas opcionales.

Al guardar:

1. Crear `tenant_subscriptions`:
   - `tenant_id`;
   - `plan_id`;
   - `provider = manual`;
   - `status = active`;
   - `starts_at = fecha inicio`;
   - `trial_ends_at = fecha fin trial`;
   - `ends_at = fecha fin trial`;
   - `created_by = admin`;
   - `notes = Trial otorgado desde master`.

2. Crear `tenant_payments`:
   - `tenant_id`;
   - `tenant_subscription_id`;
   - `plan_id`;
   - `provider = manual`;
   - `amount = 0`;
   - `currency = MXN`;
   - `status = paid`;
   - `payment_method = trial`;
   - `paid_at = now()`;
   - `period_starts_at = fecha inicio`;
   - `period_ends_at = fecha fin trial`;
   - `created_by = admin`;
   - `notes = Trial sin cargo`.

3. Actualizar `tenants`:
   - `plan_id = plan trial`;
   - `trial_ends_at = fecha fin trial`;
   - `subscription_ends_at = fecha fin trial`;
   - `status` e `is_active` segun activacion de cuenta.

Regla:

- si la cuenta ya fue activada por el usuario, queda `active`;
- si todavia no completo activacion, queda `inactive` hasta generar/usar acceso.

## Flujo: pago con Stripe

Usar el servicio existente `StripeTenantCheckoutService`.

Al guardar:

1. Crear checkout Stripe.
2. Crear `tenant_subscriptions` en `pending`.
3. Crear `tenant_payments` en `pending`.
4. Guardar URL en `payment_reference`.
5. Mostrar link en master para copiar/enviar.
6. Mostrar pago pendiente al tenant en perfil/facturacion.

El tenant no obtiene acceso operativo hasta que Stripe confirme pago por webhook.

## Flujo: transferencia pendiente

Al guardar:

1. Crear `tenant_subscriptions` en `pending`.
2. Crear `tenant_payments` en `pending`.
3. `payment_method = transfer`.
4. Guardar referencia, banco o notas si se capturan.

El master debe tener accion posterior para confirmar pago.

Al confirmar:

- marcar pago como `paid`;
- marcar suscripcion como `active`;
- actualizar `subscription_ends_at`;
- habilitar acceso si el tenant esta activado.

## Flujo: pago confirmado manual

Aplica para:

- efectivo;
- transferencia ya confirmada;
- otro pago manual confirmado.

Al guardar:

1. Crear `tenant_subscriptions` activa.
2. Crear `tenant_payments` pagado.
3. Registrar metodo, referencia y notas.
4. Actualizar tenant con plan y vencimiento.

Este flujo puede reutilizar la logica actual de `assignPlan`, pero debe quedar
separado de los estados pendientes/trial.

## Flujo: paga despues

Al guardar:

1. Crear suscripcion pendiente.
2. Crear pago pendiente, si se conoce el monto.
3. No activar acceso operativo.
4. Mostrar en master como `pendiente de pago`.
5. Mostrar al tenant una vista limitada para pagar o contactar administracion.

## Modo facturacion limitada

Se recomienda permitir que un tenant con pago pendiente inicie sesion solo para
resolver su facturacion.

Rutas permitidas:

- perfil/facturacion;
- continuar checkout Stripe;
- ver estado de pago;
- cerrar sesion.

Rutas bloqueadas:

- dashboard operativo;
- clientes;
- pacientes;
- notas;
- servicios;
- agenda;
- API movil operativa.

Si se decide no permitir modo limitado, entonces el pago debe resolverse solo
desde links enviados por el master.

## Reglas del guard

El guard debe evaluar acceso completo con una regla central.

Acceso completo permitido si:

1. usuario activo;
2. tenant existe;
3. tenant no esta suspendido/cancelado;
4. plan existe y esta activo;
5. existe una suscripcion activa vigente;
6. existe evidencia financiera valida:
   - pago `paid` del periodo;
   - pago `paid` monto `0` con `payment_method = trial` y trial vigente;
   - plan gratuito explicitamente definido.

Acceso completo denegado si:

- no hay suscripcion;
- no hay pago;
- pago esta pendiente;
- trial vencio;
- suscripcion vencio;
- plan esta inactivo;
- tenant esta bloqueado.

## Reutilizacion de piezas existentes

No crear un servicio nuevo al inicio.

Usar y ajustar:

- `App\Services\Auth\TenantSessionGuard`
  - convertirlo en la regla canonica de acceso;
  - devolver estados mas expresivos, no solo `allowed/message`.

- `App\Services\StripeTenantCheckoutService`
  - seguir usandolo para crear checkout, pago y suscripcion pendiente.

- `Admin\TenantsController`
  - separar intencion de plan, trial, pago pendiente y pago confirmado.

- `Client\ProfileController`
  - mostrar estado real de suscripcion/pago.

- `CheckTenantSubscription`
  - delegar en la regla canonica.

- `EnsureApiTenantAccess`
  - delegar en la regla canonica para API movil.

## Cambios de UI master

### Crear tenant

Agregar bloque "Acceso inicial".

Campos:

- plan;
- tipo de acceso inicial;
- fecha inicio;
- fecha fin;
- metodo de pago;
- referencia;
- notas.

Comportamiento:

- si elige trial, monto forzado a `0`;
- si elige Stripe, generar link;
- si elige transferencia pendiente, no activar acceso;
- si elige pago confirmado, activar periodo.

### Detalle del tenant

Separar visualmente:

- plan seleccionado;
- suscripcion activa;
- pago pendiente;
- trial vigente;
- vencimiento;
- estado administrativo.

Evitar mostrar "Plan actual" como si fuera contratado cuando solo existe
`plan_id`.

### Historial de pagos

Mostrar tambien pagos de trial:

- monto `$0.00`;
- metodo `trial`;
- status `paid`;
- periodo cubierto.

## Cambios de UI cliente

### Perfil/facturacion

Cambiar texto segun estado:

- sin suscripcion: `Plan asignado sin activar`;
- checkout pendiente: `Continuar pago`;
- trial: `Trial activo hasta fecha`;
- vencido: `Renovar plan`;
- activo pagado: `Suscripcion activa`.

El boton no debe decir `Renovar con Stripe` si nunca hubo pago previo.

Para primer pago debe decir:

- `Pagar plan`;
- `Continuar pago`;
- `Ver instrucciones de transferencia`.

## Cambios de API movil

La API debe bloquear acceso operativo cuando no haya suscripcion/pago valido.

Respuesta sugerida:

HTTP `402` para falta de pago o plan vencido.

Payload:

```json
{
  "message": "Tu plan esta pendiente de pago.",
  "code": "tenant_payment_required",
  "billing_status": "pending_payment"
}
```

Estados sugeridos:

- `tenant_no_plan`;
- `tenant_payment_required`;
- `tenant_trial_expired`;
- `tenant_subscription_expired`;
- `tenant_suspended`.

## Migracion de datos existentes

Antes de endurecer el guard hay que auditar datos actuales.

Consultas necesarias:

- tenants activos sin suscripcion;
- tenants activos sin pago;
- tenants con plan pero sin vencimiento;
- pagos pendientes antiguos;
- suscripciones pending/cancelled inconsistentes;
- tenants con `subscription_ends_at` pero sin `tenant_subscriptions`.

Decision de migracion:

1. Tenants que ya pagan manualmente pero no tienen filas:
   - crear suscripcion y pago manual historico si hay evidencia.

2. Tenants que son prueba:
   - crear trial con pago `$0`.

3. Tenants sin pago ni trial aprobado:
   - dejar pendiente y bloquear acceso operativo.

## Plan de ejecucion

### Fase 1: auditoria y contrato

- [x] Listar estados reales de tenants, suscripciones y pagos.
- [x] Confirmar estados finales permitidos.
- [x] Confirmar si se habilita modo facturacion limitada.
- [x] Confirmar texto de UI para trial, primer pago y renovacion.

Ejecucion inicial:

- Comando agregado: `php artisan tenants:audit-subscriptions`.
- Archivo: `app/Console/Commands/AuditTenantSubscriptions.php`.
- Fecha de ejecucion local: 2026-06-26.
- Resultado inicial:
  - total tenants auditados: 6;
  - requieren atencion: 6;
  - `needs_review`: 5;
  - `pending_payment`: 1.
- Tenant `3828`:
  - `status = active`;
  - `is_active = true`;
  - `plan_id = 1`;
  - sin suscripciones;
  - sin pagos;
  - `billing_status = needs_review`;
  - issues: `sin_suscripciones`, `sin_pagos`,
    `sin_suscripcion_activa_o_pendiente`,
    `sin_pago_pagado_o_pendiente`.
- Tenant `2`:
  - `billing_status = pending_payment`;
  - tiene suscripciones/pagos, pero no pago `paid` vigente.

Matriz MVP de decision:

| Estado auditoria | Condicion detectada | Acceso operativo | Facturacion limitada | Accion previa al guard |
| --- | --- | --- | --- | --- |
| `needs_review` con tenant activo | Tiene `plan_id`, pero no tiene suscripcion ni pago | No | Si | Master decide: trial `$0`, pago manual historico o pago pendiente |
| `needs_review` con tenant inactivo | Tiene `plan_id`, pero no tiene suscripcion/pago y no esta activo | No | No hasta activar cuenta | Mantener bloqueado; si procede, crear trial/pago antes o durante activacion |
| `pending_payment` | Tiene suscripcion o pago pendiente sin pago `paid` vigente | No | Si | Permitir pagar/continuar checkout o confirmar pago manual |
| `trial_active` | Suscripcion activa con pago `$0 paid` y trial vigente | Si | No | Sin accion |
| `paid_active` | Suscripcion activa con pago `paid` vigente | Si | No | Sin accion |
| `trial_expired` | Trial vencido sin pago posterior vigente | No | Si | Pedir pago o registrar renovacion |
| `subscription_expired` | Suscripcion/pago vencido sin renovacion vigente | No | Si | Pedir pago o registrar renovacion |
| `no_plan` | No tiene plan asignado | No | No | Master asigna plan y decide trial/pago/pendiente |

Decisiones MVP cerradas para avanzar:

- El modo de facturacion limitada si se implementara para tenants con
  `pending_payment`, `trial_expired`, `subscription_expired` y `needs_review`
  activos.
- El trial siempre creara un pago `$0`, `paid`, `payment_method = trial`.
- En trial, `trial_ends_at` y `subscription_ends_at` seran iguales para este
  MVP.
- No se implementa plan gratuito formal en esta fase; acceso sin cobro se
  representa como trial `$0`.
- No se agregan dias de gracia en esta fase; al vencer el periodo, se bloquea
  acceso operativo.

Matriz de textos UI aprobada:

| Estado | Texto cliente | Accion cliente |
| --- | --- | --- |
| `needs_review` activo | Plan asignado pendiente de activacion | Pagar plan / Contactar administracion |
| `pending_payment` Stripe | Pago pendiente | Continuar pago |
| `pending_payment` transferencia | Transferencia pendiente de confirmacion | Ver instrucciones |
| `trial_active` | Trial activo hasta `{fecha}` | Sin accion de pago obligatoria |
| `paid_active` | Suscripcion activa hasta `{fecha}` | Renovar plan |
| `trial_expired` | Tu trial ha vencido | Pagar plan |
| `subscription_expired` | Tu suscripcion ha vencido | Renovar plan |
| `no_plan` | Sin plan asignado | Contactar administracion |

| Estado master | Etiqueta master | Accion master |
| --- | --- | --- |
| sin subs/pagos | Sin contrato registrado | Crear trial / Registrar pago / Generar checkout |
| pendiente Stripe | Link Stripe pendiente | Copiar link / Reenviar |
| pendiente transferencia | Pago por confirmar | Confirmar pago |
| trial activo | Trial vigente | Ver vencimiento |
| vencido | Vencido | Renovar / Registrar pago |

Criterio de salida:

- matriz de estados aprobada;
- queries de auditoria ejecutadas;
- tenants inconsistentes identificados.

### Fase 2: regla central de acceso

- [x] Extender `TenantSessionGuard` para devolver estado de billing.
- [x] Incluir validacion de suscripcion activa.
- [x] Incluir validacion de pago `paid`.
- [x] Incluir trial con pago `$0`.
- [x] Cubrir vencimiento de trial y suscripcion.

Ejecucion:

- `TenantSessionGuard::canLogin()` ahora valida acceso operativo completo.
- `TenantSessionGuard::billingStatus()` clasifica estados de billing.
- `TenantSessionGuard::canEnterBillingArea()` permite entrada limitada a
  facturacion para `needs_review`, `pending_payment`, `trial_expired` y
  `subscription_expired`.
- `CheckTenantSubscription` delega en `TenantSessionGuard`.
- `EnsureApiTenantAccess` delega en `TenantSessionGuard` y devuelve
  `code`/`billing_status` en respuestas API.
- Login API devuelve `code`/`billing_status` cuando falla por billing.
- Verificacion local:
  - tenant `2`: acceso completo bloqueado como `pending_payment`, facturacion
    limitada permitida;
  - tenant `36`: acceso completo bloqueado como `needs_review`, facturacion
    limitada permitida;
  - tenant `3828`: acceso completo bloqueado como `needs_review`, facturacion
    limitada permitida.

Criterio de salida:

- login web y API usan la misma interpretacion;
- `plan_id` solo ya no habilita acceso completo.

### Fase 3: rutas de facturacion limitada

- [x] Separar rutas de perfil/facturacion del grupo operativo completo.
- [x] Permitir acceso limitado si hay pago pendiente.
- [x] Bloquear modulos operativos.
- [x] Redirigir login de pago pendiente hacia perfil/facturacion.

Ejecucion:

- `/client/profile` queda en grupo con `auth`, `access.web`, `tenant.plan`,
  sin `check.tenant.subscription`.
- `client.mi-configuracion.plan.stripe-checkout` queda disponible en modo de
  facturacion limitada.
- El grupo operativo completo conserva `check.tenant.subscription`.
- Login web redirige usuarios con `billing_limited` a `client.profile.index`.

Criterio de salida:

- tenant pendiente puede pagar;
- tenant pendiente no puede operar el sistema.

### Fase 4: creacion/asignacion de plan

- [x] Agregar decision de acceso inicial en crear tenant.
- [x] Agregar trial con fecha fin.
- [x] Crear pago `$0` para trial.
- [x] Crear pending para Stripe/transferencia.
- [x] Mantener pago confirmado manual.

Ejecucion:

- `Admin\TenantsController@store` ahora exige `billing_action`,
  `starts_at` y `ends_at`, y crea siempre suscripcion + pago/intencion al
  crear tenant.
- `Admin\TenantsController@assignPlan` reutiliza la misma regla manual para
  trial, pago confirmado y pago pendiente.
- `createManualBillingRecord()` centraliza la creacion manual de:
  - trial: suscripcion `active`, pago `paid`, amount `0`, method `trial`;
  - pago confirmado: suscripcion `active`/`scheduled`, pago `paid`;
  - pago pendiente: suscripcion `pending`, pago `pending`.
- El flujo Stripe existente conserva `StripeTenantCheckoutService`, que ya
  crea suscripcion y pago pendiente.
- Prueba local con rollback:
  - `trial`: `subs=1`, `payments=1`, `sub_status=active`,
    `pay_status=paid`, `amount=0.00`;
  - `paid`: `subs=1`, `payments=1`, `sub_status=active`,
    `pay_status=paid`;
  - `pending`: `subs=1`, `payments=1`, `sub_status=pending`,
    `pay_status=pending`.

Criterio de salida:

- ningun tenant nuevo queda solo con `plan_id`;
- siempre queda suscripcion y pago/intencion relacionada.

### Fase 5: UI master

- [x] Reordenar modal `Agregar Plan`.
- [x] Separar plan seleccionado de suscripcion activa.
- [x] Mostrar acciones segun estado.
- [x] Mostrar trial en historial.
- [x] Mostrar links Stripe pendientes.

Ejecucion:

- El detalle admin ahora recibe `billingSummary` desde `TenantsController`.
- La vista separa `Plan asignado` de `Suscripcion`.
- El card de suscripcion muestra estados de negocio: trial vigente, pago
  pendiente, suscripcion activa, vencido, sin contrato o bloqueo
  administrativo.
- El historial de pagos traduce metodos y estados para trial, Stripe checkout,
  transferencia, efectivo, pagado, pendiente y cancelado.
- El modal `Agregar Plan` incluye ayuda sobre trial `$0` y pago pendiente sin
  acceso operativo.

Criterio de salida:

- el master entiende si el tenant esta activo, en trial, pendiente o vencido.

### Fase 6: UI cliente

- [x] Ajustar perfil/facturacion.
- [x] Cambiar `Renovar con Stripe` por `Pagar plan` cuando aplique.
- [x] Mostrar trial vigente.
- [x] Mostrar pago pendiente.
- [x] Mostrar vencimiento real.

Ejecucion:

- `Client\ProfileController` ahora calcula `billingSummary` para la vista.
- `/client/profile` muestra `Plan asignado` y estado real de billing.
- La seccion de facturacion usa `Pagar plan`, `Continuar pago`, `Renovar
  plan`, `Trial activo` o `Suscripcion vencida` segun estado.
- La tabla de pagos traduce metodos y estados para cliente.
- El checkout Stripe iniciado desde cliente vuelve a `/client/profile`, ruta
  permitida en facturacion limitada.
- Render local de `ProfileController@index` validado correctamente.

Criterio de salida:

- primer login de tenant nuevo muestra una accion clara para pagar o ver trial.

### Fase 7: migracion y limpieza de datos

- [x] Crear comando o script de auditoria.
- [x] Crear registros faltantes para tenants existentes segun decision.
- [x] Corregir tenants activos sin contrato.
- [x] Documentar excepciones.

Ejecucion:

- Auditoria disponible: `php artisan tenants:audit-subscriptions`.
- Normalizacion trial historico disponible:
  `php artisan tenants:normalize-trial {tenant_id} --starts=YYYY-MM-DD
  --ends=YYYY-MM-DD --created-by=ID --apply`.
- El comando `tenants:normalize-trial` corre en dry-run por defecto.
- Documento operativo agregado:
  `docs/tenant-subscription-production-normalization.md`.
- Produccion fue normalizada manualmente antes del deploy:
  - tenant `1`: trial historico `$0`;
  - tenant `2`: cancelado con `is_active = false`;
  - tenant `5`: queda en facturacion limitada por pago pendiente;
  - check final de activos sin respaldo: `0`.

Criterio de salida:

- no quedan tenants activos sin suscripcion/pago/trial valido, salvo excepciones
  documentadas.

### Fase 8: pruebas

- [x] Feature test: tenant sin plan no entra.
- [x] Feature test: tenant con plan sin pago no entra a modulos.
- [x] Feature test: tenant con checkout pendiente solo ve facturacion.
- [x] Feature test: trial activo entra.
- [x] Feature test: trial vencido no entra.
- [x] Feature test: pago manual confirmado entra.
- [ ] Feature test: Stripe paid activa acceso.
- [x] API test: bloqueo devuelve `402`.

Ejecucion:

- `tests/Feature/TenantSessionGuardTest.php` cubre:
  - plan sin suscripcion/pago queda en facturacion limitada;
  - pago pendiente queda en facturacion limitada;
  - trial activo entra;
  - trial vencido bloquea;
  - pago confirmado vigente entra;
  - tenant inactivo no entra ni a facturacion.
- `tests/Feature/TenantBillingAccessHttpTest.php` cubre:
  - ruta operativa responde `402` con pantalla restringida para tenant
    billing-limited;
  - `/client/profile` sigue disponible para tenant billing-limited.
- Comando ejecutado:
  `php artisan test --filter='TenantSessionGuardTest|TenantBillingAccessHttpTest'`.
- Resultado: 8 tests, 21 assertions, passed.
- Pendiente especifico: prueba end-to-end de webhook Stripe `paid`; queda fuera
  de esta pasada porque requiere simular payload Stripe/webhook con IDs de
  proveedor.

Criterio de salida:

- reglas cubiertas para web y API;
- no hay regresion para super-admin.

## Riesgos

- Bloquear tenants existentes que hoy operan sin registros completos.
- Duplicar suscripciones si no se limpia pending antes de crear nuevas.
- Confundir trial con descuento real si no queda `payment_method = trial`.
- Mostrar plan contratado cuando solo hay plan asignado.
- Dejar perfil protegido por el mismo middleware que bloquea pago pendiente.

## Decisiones pendientes

1. Confirmar si al crear tenant con pago pendiente se permite activar usuario
   inmediatamente en modo facturacion limitada o si queda inactivo hasta que
   el master genere acceso.
2. Definir la accion de normalizacion para cada tenant existente detectado en
   auditoria:
   - crear trial `$0`;
   - crear pago manual historico;
   - crear pago pendiente;
   - mantener bloqueado.

Decisiones cerradas para MVP:

- Se implementara modo facturacion limitada.
- El trial siempre crea pago `$0 paid`.
- En trial, `trial_ends_at` y `subscription_ends_at` seran iguales.
- No habra plan gratuito formal en esta fase.
- No habra dias de gracia en esta fase.

## Orden recomendado

1. Auditoria de datos actuales.
2. Definir matriz final de estados.
3. Implementar regla central.
4. Separar rutas de facturacion limitada.
5. Ajustar creacion/asignacion de tenant.
6. Ajustar UI master.
7. Ajustar UI cliente.
8. Migrar/normalizar tenants existentes.
9. Endurecer guard en produccion.
