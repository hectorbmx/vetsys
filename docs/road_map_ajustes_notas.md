# Road Map — Ajustes de Notas, Cobranza y Cortes Mensuales por Tenant

## 1. Objetivo general

Buscamos ajustar el comportamiento de cobranza del sistema para que cada tenant pueda decidir cómo quiere operar:

1. **Cobranza fina por nota de venta**  
   El flujo actual se mantiene: el tenant crea notas, cada nota tiene su propio saldo, y los pagos se aplican directamente a una nota específica.

2. **Cobranza mensual por cortes / estados de cuenta**  
   El tenant sigue registrando servicios mediante notas internas, pero visualmente y operativamente trabaja por cortes mensuales del cliente. En este modo, el cliente no paga nota por nota, sino que realiza abonos mensuales contra su saldo global.

La intención principal es soportar ambos modelos sin romper el core actual del sistema.

---

## 2. Contexto funcional actual

Actualmente el sistema funciona alrededor de estas entidades principales:

- Tenant / clínica
- Customer / cliente
- Animal / caballo / paciente
- Servicios / productos
- Notas de venta
- Pagos
- Estados de cuenta

Flujo actual:

1. El tenant crea un cliente.
2. El tenant crea uno o varios animales para ese cliente.
3. El tenant tiene servicios previamente dados de alta.
4. El tenant genera una nota de venta.
5. Una nota puede tener N animales.
6. Cada animal puede tener N servicios dentro de la nota.
7. La nota tiene total, pagado, saldo y estatus.
8. El customer puede pagar una nota específica.
9. El sistema ya puede generar estados de cuenta mensuales con resumen de notas, pagos y saldos.

Este modelo funciona bien para tenants que necesitan control exacto por nota.

---

## 3. Nuevo requerimiento

Uno de los tenants principales no quiere operar por notas individuales.

Su mercado funciona así:

- Durante el mes se hacen servicios a distintos caballos de un cliente.
- Esos servicios deben quedar registrados con detalle.
- Al final del mes se genera un corte / estado de cuenta.
- El cliente realiza uno o varios abonos mensuales.
- No es conveniente llevar el cobro nota por nota.
- El tenant quiere ver el adeudo general del cliente y sus cortes ejecutados.

En el sistema legacy, el flujo era:

1. En el index de clientes se ve el adeudo general del cliente.
2. Al presionar el saldo, se entra al historial de cortes ejecutados.
3. Desde ahí se puede imprimir cada corte.
4. El corte impreso muestra:
   - Cliente
   - Período
   - Servicios realizados
   - Caballos
   - Adeudo anterior
   - Total del período
   - Abonos del período
   - Adeudo total

Queremos replicar ese comportamiento dentro del nuevo sistema, sin eliminar las notas existentes.

entonces aunque las notas se van a crear igual que ahora, nose van a mostrar en la pantalla como notas individuales lo que se va a mostrar en la pestaña cuentas
http://127.0.0.1:8000/client/customers/332 son los cortes generedos a ese cliente de fecha A a fecha B al conboton para imprimir justo como se hace en la pestaña configuracion y este es el link actual http://127.0.0.1:8000/client/customers/332/statements/1/pdf para imprimir

---

## 4. Decisión de arquitectura

No se deben eliminar las notas.

Las notas deben seguir existiendo como motor interno del sistema, porque actualmente son las que guardan:

- Cliente
- Animales
- Servicios
- Cantidades
- Precios
- Subtotales
- Total
- Fecha
- Tenant
- Trazabilidad operativa

La diferencia estará en cómo se presentan y cómo se cobran.

En modo mensual, las notas serán el respaldo interno de los servicios realizados, pero el usuario del tenant verá principalmente cortes mensuales / estados de cuenta.

- (la idea es solo ocualtar de la vista las notas creadas y cuando el tenant cree un corte o un estado de cuenta, traer todas las notas generadas como ya se hace actualmente en estre punto hay que detenernos a detallar bien)

- uno de los cambios clave es que en esta vist http://127.0.0.1:8000/client/animals/1655/edit en la pestaña historial de servicios se mustran las notas creadas  para saber lo que se le hizo a un animal hay que ver los detalles, (preguintar a carlos si podria funcionar como esta hoy) el sistema legacy muestra el historial de servicios realizados (fuera de notas) para el proposito que buscamos repito, no es necesario borrar la funcion de notas solo ocultar el folio y exponer todos los servicios realizados a un animal  libre sin estar agrupado en notas 
---

## 5. Configuración por tenant

Agregar una configuración por tenant para definir el modo de cobranza.

Campo sugerido:

```php
billing_mode
```

Valores sugeridos:

```txt
note_based
monthly_cutoff
```

Significado:

```txt
note_based:
    Modo actual.
    Las notas se muestran y se cobran individualmente.

monthly_cutoff:
    Las notas siguen existiendo internamente.
    El tenant trabaja visualmente por cortes mensuales / estados de cuenta.
    Los pagos se registran como abonos globales del cliente, no contra un corte ni forzosamente contra una nota específica.
```

Valor default para tenants existentes:

```txt
note_based
```

Esto evita romper el comportamiento actual.

---

## 6. Cambio principal de UI: tab dinámico en customer show

Actualmente en el perfil del cliente existe un tab llamado:

```txt
Notas de venta
```

Queremos cambiarlo a un concepto más genérico:

```txt
Cobranza
```

Dentro de ese tab, el contenido dependerá de la configuración del tenant.

### Si el tenant usa `note_based`

El tab `Cobranza` muestra las notas de venta actuales:

- Folio
- Fecha
- Total
- Saldo
- Estatus
- Acciones
- Nueva nota
- Ver nota
- Registrar pago
- Generar link de pago

Título interno sugerido:

```txt
Notas de venta
```

Subtítulo sugerido:

```txt
Ventas y saldos registrados para este cliente.
```

### Si el tenant usa `monthly_cutoff`

El tab `Cobranza` muestra cortes mensuales / estados de cuenta ejecutados:

- Período
- Servicios / notas incluidas
- Total del período
- Pagos / abonos del período
- Saldo del corte
- Fecha de generación
- Acciones

Acciones:

- Ver corte
- Imprimir corte
- Registrar pago / abono
- Ver pagos
- Regresar

Título interno sugerido:

```txt
Cortes / Estados de cuenta
```

Subtítulo sugerido:

```txt
Resumen mensual de servicios, pagos y saldo del cliente.
```

---

## 7. Comportamiento del index de clientes

El index de clientes ya muestra el adeudo general del cliente, igual que el sistema legacy.

Ese comportamiento debe mantenerse.

Al hacer clic en el saldo / adeudo del cliente:

### Si el tenant usa `note_based`

Debe llevar al perfil del cliente, tab `Cobranza`, mostrando notas pendientes o notas de venta.

### Si el tenant usa `monthly_cutoff`

Debe llevar al perfil del cliente, tab `Cobranza`, mostrando los cortes mensuales ejecutados.

Ruta sugerida conceptual:

```txt
/customers/{customer}?tab=billing
```

El tab `Cobranza` decide internamente qué vista mostrar según `tenant.billing_mode`.

---

## 8. Entidad sugerida para cortes mensuales

Crear una entidad para representar los cortes / estados de cuenta mensuales.

Nombre técnico recomendado:

```txt
CustomerStatement
```

Tabla sugerida:

```txt
customer_statements
```

Campos sugeridos:

```php
id
tenant_id
customer_id
period_start
period_end
previous_balance
period_charges
period_payments
ending_balance
generated_at
generated_by
status
created_at
updated_at
```

Status sugeridos:

```txt
draft
generated
sent
paid
cancelled
```

Regla importante:

Debe haber un identificador único por tenant, cliente y período para evitar cortes duplicados del mismo rango.

Ejemplo:

```php
unique(['tenant_id', 'customer_id', 'period_start', 'period_end'])
```

---

## 9. Relación entre cortes y notas

Aunque en modo mensual no se cobren notas individualmente, sí debemos saber qué notas quedaron incluidas en cada corte.

Crear una tabla pivote:

```txt
customer_statement_note_detail
```

Campos sugeridos:

```php
id
customer_statement_id
note_detail_id
quantity_snapshot
price_snapshot
subtotal_snapshot
created_at
updated_at
```

Esto permite:

- Saber qué notas se incluyeron en un corte.
- Evitar duplicar una nota en otro corte.
- Auditar el histórico.
- Imprimir un corte aunque después se creen más notas.
- Mantener trazabilidad entre servicios registrados y corte mensual.

---

## 10. Pagos / abonos mensuales

En modo `monthly_cutoff`, el tenant debe poder registrar pagos como abonos globales del cliente.

No deben estar forzados a una nota específica.

Se recomienda crear o adaptar una tabla de pagos globales del customer.

Nombre sugerido:

```txt
customer_payments
```

Campos sugeridos:

```php
id
tenant_id
customer_id
sale_note_id nullable
amount
payment_date
method
reference
source
notes
created_by
created_at
updated_at
```

Reglas:

```txt
note_based:
    El pago normalmente tendrá sale_note_id.

monthly_cutoff:
    El pago debe tener customer_id.
    No debe tener customer_statement_id.
    sale_note_id puede ser null.
```

Esto permite que el mismo sistema soporte:

- Pagos por nota.
- Abonos mensuales.
- Pagos globales del cliente.

Decision vigente:

```txt
En monthly_cutoff los cortes son informativos.
No se registran pagos directos contra un corte.
Los pagos se registran contra customer_id.
El corte muestra pagos del periodo como parte de la foto, pero no es un objeto de cobro.
```

---

## 11. Cálculo del corte mensual

El corte mensual debe calcular:

```txt
saldo_anterior = total_notas_antes_del_periodo - total_pagos_antes_del_periodo

total_periodo = total_notas_del_periodo

abonos_periodo = total_pagos_del_periodo

adeudo_total = saldo_anterior + total_periodo - abonos_periodo
```

El corte debe mostrar:

- Adeudo anterior
- Total del período en curso
- Abonos del período
- Adeudo total

Este comportamiento debe ser equivalente al sistema legacy.

---

## 12. Vista show del corte

Crear una vista para ver el detalle de un corte.

Debe mostrar:

- Datos del tenant
- Datos del cliente
- Período del corte
- Fecha de generación
- Servicios agrupados por caballo, si aplica
- Notas incluidas, si aplica
- Adeudo anterior
- Total del período
- Abonos del período
- Adeudo total

Acciones:

- Imprimir corte
- Registrar pago
- Ver pagos
- Regresar al cliente

---

## 13. PDF / impresión del corte

El PDF actual del estado de cuenta ya tiene una estructura cercana a lo que se necesita:

- Cliente
- Período
- Notas / servicios del mes
- Pagos registrados
- Saldo anterior
- Total facturado
- Total pagado
- Saldo pendiente

Para modo `monthly_cutoff`, el PDF debe adaptarse al lenguaje de cortes:

```txt
Estado de cuenta / Corte mensual
Adeudo anterior
Total período en curso
Abonos del período
Adeudo total
```

La impresión debe parecerse al sistema legacy, pero usando el diseño moderno del sistema nuevo.

---

## 14. Show de nota en modo mensual

En modo `monthly_cutoff`, la nota individual no debe sentirse como el documento principal de cobranza.

La nota debe mostrarse como respaldo de servicios.

Ocultar o cambiar énfasis de:

- Generar link Stripe para la nota
- Registrar pago manual a esta nota
- Saldo pendiente de la nota como acción principal

Mostrar mensaje sugerido:

```txt
Esta nota forma parte del estado de cuenta mensual del cliente.
Los pagos se registran desde Cobranza / Cortes del cliente.
```

Botones sugeridos:

- Ver cobranza del cliente
- Ver cortes
- Registrar abono

---

## 15. Stripe

### En modo `note_based`

Mantener comportamiento actual:

```txt
Generar link Stripe para una nota específica.
```

### En modo `monthly_cutoff`

Agregar flujo futuro:

```txt
Generar link Stripe para saldo del cliente o corte mensual.
```

Metadata sugerida en Stripe:

```php
tenant_id
customer_id
billing_mode = monthly_cutoff
period_start
period_end
```

El webhook debe registrar el pago como `customer_payment`, no necesariamente como pago de una nota.

---

## 16. Fases de implementación recomendadas

### Fase 1 — Configuración y tab dinámico

Objetivo:

Permitir que el tenant elija el modo de cobranza y que la UI cambie sin romper el sistema actual.

Tareas:

1. Agregar `billing_mode` a tenants.
2. Default `note_based`.
3. Agregar selector en configuración del tenant.
4. Agregar helpers en modelo Tenant:

```php
usesNoteBasedBilling()
usesMonthlyCutoffBilling()
```

5. Renombrar tab `Notas de venta` a `Cobranza`.
6. Si el tenant usa `note_based`, mostrar notas actuales.
7. Si el tenant usa `monthly_cutoff`, mostrar placeholder de cortes.

Resultado esperado:

```txt
Los tenants actuales siguen igual.
El tenant mensual ya ve el espacio de Cobranza orientado a cortes.
```

---

### Fase 2 — Cortes reales

Objetivo:

Permitir generar, listar, ver e imprimir cortes mensuales.

Tareas:

1. Crear tabla `customer_statements`.
2. Crear tabla pivote `customer_statement_sale_note`.
3. Crear modelo `CustomerStatement`.
4. Crear relaciones con Tenant, Customer y SaleNote.
5. Crear controlador para cortes.
6. Crear formulario para generar corte con fecha inicio / fecha fin.
7. Calcular saldo anterior, cargos del período, pagos del período y saldo final.
8. Asociar notas incluidas al corte.
9. Mostrar cortes en tab `Cobranza`.
10. Crear show de corte.
11. Crear impresión/PDF del corte.

Resultado esperado:

```txt
El tenant mensual puede generar cortes, consultarlos e imprimirlos.
```

---

### Fase 3 — Pagos mensuales

Objetivo:

Permitir registrar abonos globales del cliente.

Tareas:

1. Crear o adaptar tabla `customer_payments`.
2. Permitir pagos globales con `customer_id`.
3. No asociar pagos a `customer_statement_id`; los cortes son informativos.
4. Crear formulario de `Registrar abono`.
5. Mostrar pagos en el corte.
6. Mostrar último pago del cliente.
7. Actualizar adeudo general del cliente.
8. Validar que el index de clientes muestre el saldo correcto.

Resultado esperado:

```txt
El tenant mensual puede registrar abonos y ver el saldo global actualizado.
```

---

### Fase 4 — Ajustes de Stripe mensual

Objetivo:

Permitir generar links de pago por corte o por saldo del cliente.

Tareas:

1. Crear flujo para cobrar saldo del corte.
2. Crear flujo para cobrar monto libre al cliente.
3. Agregar metadata de customer, tenant y statement.
4. Ajustar webhook para registrar `customer_payment`.
5. Validar que el pago reduzca el saldo global.

Resultado esperado:

```txt
El tenant mensual puede cobrar por link sin usar una nota individual.
```

---

## 17. Consideraciones importantes

### No romper tenants existentes

Todos los tenants existentes deben quedar en:

```txt
note_based
```

El sistema debe comportarse exactamente como ahora para ellos.

### No borrar notas

Las notas siguen siendo necesarias como respaldo operativo.

### No duplicar cargos

Una nota incluida en un corte no debe aparecer duplicada en otro corte del mismo período.

### Permitir saldos a favor

En el modelo mensual puede pasar que un cliente pague de más. Se recomienda permitir saldo a favor en vez de bloquearlo.

### Cambio de modo

Si un tenant cambia de `note_based` a `monthly_cutoff`, las notas existentes siguen existiendo.

Si un tenant cambia de `monthly_cutoff` a `note_based`, los pagos mensuales históricos deben conservarse, pero puede requerirse una advertencia porque no todos estarán aplicados a notas específicas.

---

## 18. Resultado final esperado

El sistema debe soportar dos formas de operar por tenant:

### Tenant tradicional

```txt
Cliente → Notas de venta → Pagos por nota → Estado de cuenta como resumen
```

### Tenant mensual

```txt
Cliente → Cobranza → Cortes mensuales → Abonos → Estado de cuenta / PDF
```

Con esto el sistema se adapta tanto a clientes que necesitan control fino por nota como a clientes que trabajan por corte mensual.

---

## 19. Decision final de producto

La implementacion no debe borrar ni reemplazar el flujo actual de notas.

El sistema debe soportar dos formas de trabajo por tenant:

```txt
note_based:
    Cobranza actual por nota.
    Cada nota tiene saldo propio.
    Los pagos se aplican a notas especificas.

monthly_cutoff:
    Cobranza tipo tarjeta de credito / cuenta revolvente por customer.
    Las notas siguen existiendo como respaldo interno de servicios.
    El customer acumula cargos y pagos durante un periodo.
    El corte recopila cargos, pagos y saldo para generar un balance.
```

Regla principal:

```txt
Notas = motor operativo / detalle de servicios.
Cobranza = capa de presentacion y cobro segun modo del tenant.
```

En modo `monthly_cutoff`, una nota individual no es el documento principal de cobranza. La nota funciona como cargo interno que alimenta el balance del cliente.

### Ajuste de alcance para `monthly_cutoff`

El nuevo modo debe ser principalmente un ajuste visual y operativo sobre el core existente.

No se debe reescribir la forma base en que se guardan los servicios:

```txt
notes:
    contenedor interno / respaldo tecnico

note_details:
    cargos reales visibles para el tenant mensual

payments / abonos:
    reducen el saldo global del customer, no una nota especifica
```

En modo `monthly_cutoff`, el tenant debe sentir que trabaja una cuenta tipo tarjeta de credito:

```txt
Customer
    -> Cobranza
        -> Cargos libres del periodo
        -> Abonos libres
        -> Balance
        -> Cortes
```

No debe sentir que trabaja asi:

```txt
Customer
    -> Nota VT-00001
    -> Pago a nota VT-00001
```

Por lo tanto:

- Las notas se siguen creando igual que ahora.
- El tenant aplica N servicios cada dia.
- Internamente esos servicios quedan en notas y `note_details`.
- Visualmente, en modo mensual se muestran como cargos/servicios libres.
- El corte de fecha A a fecha B consulta `note_details` del periodo.
- La nota/folio solo se conserva como referencia interna y trazabilidad.
- El cliente paga una cantidad X contra su deuda total.
- El pago mensual no debe obligar a elegir una nota.

Formula conceptual:

```txt
cargos_periodo = suma de note_details dentro del rango A-B
pagos_periodo = suma de abonos del customer dentro del rango A-B
saldo_anterior = cargos antes de A - pagos antes de A
balance_final = saldo_anterior + cargos_periodo - pagos_periodo
```

---

## 20. Checkpoints de ejecucion

Estos checkpoints dividen el trabajo en entregas pequenas y verificables.

### Checkpoint 0 - Flujo actual protegido

Objetivo:

Dejar estable el flujo actual de notas antes de agregar cobranza mensual.

Estado actual:

- [x] Crear nota conserva el flujo existente.
- [x] Crear nota muestra modal de carga.
- [x] Crear nota bloquea guardado accidental con Enter.
- [x] Crear nota pide confirmacion al salir con cambios en progreso.
- [x] Notas sin pagos pueden editarse desde `client/ventas`.
- [x] Notas sin pagos pueden eliminarse desde `client/ventas`.
- [x] Notas sin pagos pueden editarse/eliminarse desde `client/customers/{customer}`, tab `Notas de Venta`.
- [x] Backend bloquea editar/eliminar notas con pagos registrados.
- [x] Generador de folios considera notas eliminadas con soft delete para no repetir folios.

Validacion pendiente:

- [ ] Crear una nota despues de eliminar otra y confirmar que no repite folio.
- [ ] Editar nota sin pagos y validar que el inventario queda correcto.
- [ ] Confirmar que notas con pagos no muestran editar/eliminar en ninguna vista.

### Checkpoint 1 - Configuracion `billing_mode`

Objetivo:

Agregar el modo de cobranza por tenant sin cambiar el comportamiento de tenants existentes.

Tareas:

- [x] Crear migracion para agregar `billing_mode` a `tenants`.
- [x] Default: `note_based`.
- [x] Agregar `billing_mode` a `$fillable` de `Tenant`.
- [x] Agregar helpers en `Tenant`:
  - `usesNoteBasedBilling()`
  - `usesMonthlyCutoffBilling()`
- [x] Agregar selector en `client/mi-configuracion`, preferentemente en la pestaña de preferencias.
- [x] Validar valores permitidos:
  - `note_based`
  - `monthly_cutoff`
- [x] Si el campo esta vacio o viene de datos antiguos, tratarlo como `note_based`.

Resultado esperado:

Todos los tenants actuales siguen trabajando igual. Solo cambia el tenant que explicitamente seleccione modo mensual.

### Checkpoint 2 - Tab `Cobranza` dinamico en cliente

Objetivo:

Cambiar el tab del perfil del cliente para que sea una capa de cobranza adaptable.

Tareas:

- [x] Renombrar tab visible segun modo: `Notas` para `note_based`, `Cuentas` para `monthly_cutoff`.
- [x] Mantener compatibilidad con tab anterior si hay enlaces a `notas`.
- [x] Si el tenant usa `note_based`, mostrar la tabla actual de notas.
- [x] Si el tenant usa `monthly_cutoff`, ocultar la tabla de notas como protagonista.
- [x] Si el tenant usa `monthly_cutoff`, mostrar cortes/estados de cuenta generados, no servicios sueltos.
- [x] En la vista mensual, mostrar por corte:
  - periodo
  - consumo
  - pagos
  - saldo final
  - estado
  - PDF
- [x] Mover el historial/listado de cortes al tab `Cuentas`.
- [x] Dejar `Configuracion` solo para parametros contables del customer.
- [x] En `note_based`, conservar:
  - Nueva nota
  - Ver nota
  - Pagar nota
  - Editar nota sin pagos
  - Eliminar nota sin pagos
- [x] En `monthly_cutoff`, mostrar CTAs iniciales:
  - Generar corte
  - Registrar abono
  - Ver cortes mediante la tabla de cortes generados
- [x] Activar CTA `Generar corte` en Checkpoint 5.
- [x] Activar CTA `Registrar abono` en Checkpoint 7.
- [x] Activar vista `Ver cortes` usando la tabla de estados generados.

Resultado esperado:

El perfil del cliente ya no piensa solo en notas, sino en cobranza. Para tenants actuales se ve igual; para tenant mensual se ven cargos libres y balance, aunque internamente sigan viniendo de notas.

### Checkpoint 3 - Navegacion desde adeudo general

Objetivo:

Replicar el flujo legacy: desde el saldo del cliente se entra al historial de cobranza.

Tareas:

- [ ] Localizar saldo/adeudo en index de clientes.
- [ ] Convertir el saldo en enlace si aun no lo es.
- [ ] Enviar a `client.customers.show` con tab de cobranza.
- [ ] Si `note_based`, enfocar notas/saldos por nota.
- [ ] Si `monthly_cutoff`, enfocar cortes/balance.

Resultado esperado:

El usuario puede hacer clic en el adeudo general del cliente y entrar directo a su cobranza.

### Checkpoint 4 - Modelo base de cortes

Objetivo:

Crear la estructura persistente para cortes mensuales sin modificar aun pagos ni Stripe.

Tareas:

- [ ] Crear tabla `customer_statements`.
- [ ] Crear tabla pivote `customer_statement_sale_note`.
- [ ] Crear modelo `CustomerStatement`.
- [ ] Agregar relaciones:
  - Tenant -> customerStatements
  - Customer -> statements
  - CustomerStatement -> notes
  - Note -> customerStatement, si aplica
- [ ] Agregar unique por:
  - `tenant_id`
  - `customer_id`
  - `period_start`
  - `period_end`
- [ ] Agregar status:
  - `draft`
  - `generated`
  - `sent`
  - `paid`
  - `cancelled`

Resultado esperado:

El sistema puede guardar cortes y asociarlos a notas sin romper la cobranza actual.

### Checkpoint 5 - Generacion de cortes

Objetivo:

Permitir generar cortes reales en modo `monthly_cutoff`.

Estado actual:

- [x] El index `client/customers` ya muestra boton de dinero por cliente en modo `monthly_cutoff`.
- [x] El boton abre modal para seleccionar fecha inicio y fecha fin.
- [x] El modal consulta preview del rango antes de guardar.
- [x] El preview agrupa por caballo/paciente:
  - caballos atendidos
  - servicios realizados
  - total por caballo
  - total general
- [x] El modal permite crear corte manual para el rango seleccionado.
- [x] El corte manual guarda/actualiza `CustomerStatement` y genera PDF.
- [x] El tab `Cuentas` permite recalcular un corte existente.
- [x] Recalcular usa el mismo rango del corte y actualiza el mismo `CustomerStatement`.
- [x] El tab `Cuentas` detecta diferencias contra el barrido actual y muestra `Recalculo disponible`.
- [x] El CTA `Generar corte` del tab `Cuentas` genera el ultimo periodo cerrado usando el dia de corte configurado.
- [x] Se elimino de la cabecera del perfil mensual el acceso duplicado a `Registrar abono` y `Ver Estado`; el rango manual queda en `client/customers` y el corte automatico queda en el tab `Cuentas`.

Tareas:

- [x] Crear controlador para cortes.
- [x] Crear rutas bajo cliente, por ejemplo:
  - `client/customers/{customer}/statements`
  - `client/customer-statements/{statement}`
- [x] Crear formulario con:
  - fecha inicio
  - fecha fin
- [x] Consultar `note_details` del periodo A-B.
- [ ] Usar la relacion con `notes` solo como filtro interno:
  - tenant actual
  - customer actual
  - `notes.date_at` dentro del rango
- [x] No obligar al usuario a seleccionar ni entender folios.
- [x] Evitar duplicar cortes del mismo rango usando `updateOrCreate` por tenant/customer/periodo.
- [x] Detectar diferencias entre snapshot guardado y barrido actual para mostrar `Recalculo disponible`.
- [x] Calcular:
  - saldo anterior
  - cargos del periodo
  - pagos/abonos del periodo
  - saldo final
- [ ] Asociar cargos al corte. Puede ser por `note_details` o por nota como respaldo tecnico, pero la UI debe hablar de cargos/servicios.
- [x] Listar cortes en tab `Cobranza`.

Resultado esperado:

El tenant mensual puede generar un corte por rango de fechas viendo servicios/cargos libres, no folios de notas.

Pendiente de auditoria para la siguiente sesion:

- [ ] Revisar diferencia de $50 detectada en `client/customers/2014` despues de recalcular los 2 cortes existentes.
- Datos observados el 2026-07-09 en local:
  - Customer: Pedro Paramo.
  - Cargos acumulados mostrados: $5,350.00.
  - Abonos registrados: $350.00.
  - Balance mostrado: $5,000.00.
  - Suma de cortes visibles: $3,400.00 + $1,900.00 = $5,300.00.
  - Diferencia contra cargos acumulados: $50.00.
  - No se detecto delta por notas `CANCELADA` en la auditoria inicial.
  - Existen cargos activos fuera de los rangos de cortes visibles con fecha `2026-07-10` por $1,950.00 (`VT-00230`: Consulta $1,500.00 e Inyeccion $450.00), pero estos no explican directamente la diferencia de $50.00 entre $5,350.00 y $5,300.00.
- Hipotesis a validar:
  - Algun `note_detail` activo por $50.00 esta fuera de los rangos de los cortes o queda fuera por hora/limite de fecha.
  - Hay solapamiento de rangos entre cortes (`2026-03-01..2026-07-09` y `2026-06-02..2026-07-01`) que puede hacer que sumar cortes manualmente no sea equivalente al total acumulado.
  - Las tarjetas superiores calculan desde todos los `note_details`, mientras los cortes calculan por periodo y estado; validar que ambos usen exactamente los mismos filtros.
  - Revisar si los pagos deben mostrarse en cortes visibles aunque sean abonos globales y no pagos del corte.
- Siguiente accion tecnica:
  - Crear una auditoria temporal o comando local que liste cada `note_detail` de Pedro Paramo con fecha, folio, status, subtotal y si cae dentro de cada corte.
  - Comparar contra `customer_statements.period_charges`.
  - Decidir si el UI debe mostrar "cargos acumulados no cortados" o advertir rangos solapados/no cubiertos.

### Checkpoint 6 - Show e impresion del corte

Objetivo:

Crear la vista principal del corte mensual.

Tareas:

- [ ] Crear show de corte.
- [ ] Mostrar datos de tenant y customer.
- [ ] Mostrar periodo.
- [ ] Mostrar servicios agrupados por caballo/paciente.
- [ ] Mostrar cargos/servicios del periodo como detalle principal.
- [ ] Mostrar notas/folios solo como referencia secundaria, si se necesita auditoria.
- [ ] Mostrar:
  - adeudo anterior
  - cargos del periodo
  - abonos del periodo
  - balance final
- [ ] Agregar boton imprimir.
- [ ] Adaptar PDF/print del estado de cuenta existente al lenguaje de corte mensual.

Resultado esperado:

El corte mensual puede consultarse e imprimirse como documento principal de cobranza, con lenguaje de cargos, abonos y balance.

### Checkpoint 7 - Abonos mensuales

Objetivo:

Permitir pagos tipo tarjeta de credito: abonos libres contra la deuda total del customer, no contra una nota especifica.

Estado actual:

- [x] El tab `Cuentas` ya habilita `Registrar abono`.
- [x] El abono reutiliza el flujo existente de pagos generales por `customer_id`.
- [x] En modo `monthly_cutoff`, el modal ya usa lenguaje de abono y no muestra folios/distribucion por nota.
- [x] Al registrar abono o generar link, la vista vuelve al tab `Cuentas`.
- [x] Los cortes son informativos y no reciben pagos directos.
- [x] En modo `monthly_cutoff`, los pagos se registran siempre como abonos globales del `customer`.
- [x] El saldo real de la cuenta se calcula como total de servicios acumulados menos total de pagos acumulados.
- [ ] La aplicacion interna del pago sigue usando la distribucion actual sobre notas pendientes para conservar compatibilidad; no debe agregarse `customer_statement_id` a pagos para el modo mensual.

Tareas:

- [x] Revisar modelo actual de pagos antes de crear nueva tabla.
- [x] Definir si se adapta `payments` o se crea `customer_payments`.
- [x] Permitir pago con `customer_id`.
- [x] Descartar pago con `customer_statement_id` para `monthly_cutoff`; el corte no se paga directamente.
- [x] Mantener compatibilidad con pago por nota.
- [x] Crear formulario `Registrar abono`.
- [x] En `monthly_cutoff`, no pedir nota al registrar pago.
- [x] El abono debe reducir el balance global del customer.
- [x] El abono queda asociado al customer, no al corte.
- [x] Mostrar abonos del periodo dentro del corte solo como dato informativo.
- [x] Mostrar ultimo pago del cliente.
- [x] Actualizar adeudo general del cliente.
- [ ] Permitir saldo a favor.

Resultado esperado:

El tenant mensual registra una cantidad X como abono a la deuda total, igual que un pago a tarjeta de credito. Los cortes solo muestran la foto del periodo: servicios, pagos del periodo y saldo, pero no son objetos de cobro.

### Checkpoint 8 - Show de nota en modo mensual

Objetivo:

Reducir protagonismo de la nota individual cuando el tenant trabaja por cortes.

Tareas:

- [ ] Detectar `monthly_cutoff` en `client/ventas/{note}`.
- [ ] Mostrar aviso:

```txt
Esta nota forma parte del estado de cuenta mensual del cliente.
Los pagos se registran desde Cobranza / Cortes del cliente.
```

- [ ] Ocultar o bajar prioridad a:
  - registrar pago manual por nota
  - generar link Stripe por nota
  - saldo pendiente de la nota como accion principal
- [ ] Agregar accesos a:
  - Cobranza del cliente
  - Cortes
  - Registrar abono

Resultado esperado:

La nota queda como respaldo operativo, no como el documento principal de cobro.

### Checkpoint 9 - Stripe mensual

Objetivo:

Permitir cobrar por corte o saldo global del cliente.

Tareas:

- [ ] Crear link Stripe para corte.
- [ ] Crear link Stripe para monto libre del customer.
- [ ] Agregar metadata:
  - `tenant_id`
  - `customer_id`
  - `billing_mode`
  - `period_start`
  - `period_end`
- [ ] Ajustar webhook para registrar abono mensual.
- [ ] Validar que el pago reduzca el balance global.

Resultado esperado:

El tenant mensual puede cobrar por link sin depender de una nota individual.

### Checkpoint 10 - Auditoria final de compatibilidad

Objetivo:

Confirmar que los dos modos conviven sin romper datos.

Tareas:

- [ ] Tenant `note_based` conserva flujo actual completo.
- [ ] Tenant `monthly_cutoff` usa cortes como cobranza principal.
- [ ] Las notas siguen existiendo en ambos modos.
- [ ] Los pagos viejos siguen consultables.
- [ ] Los saldos del index de clientes son correctos en ambos modos.
- [ ] Los PDFs/impresiones muestran el lenguaje correcto segun modo.

Resultado esperado:

El sistema soporta ambas formas de trabajo por tenant sin migraciones destructivas ni perdida de trazabilidad.

### Checkpoint 11 - Ajustes visuales del perfil del cliente

Objetivo:

Hacer que `client/customers/{customer}` se sienta como una vista operativa por contexto, evitando informacion repetida y mostrando indicadores utiles segun el tab activo.

Alcance:

- Vista principal: `client/customers/{customer}`.
- Tabla origen: `client/customers`.
- Modo principal: `monthly_cutoff`, sin romper `note_based`.
- Los cambios son visuales/UX y no deben alterar el core de notas, pagos o cortes.

Estado actual:

- [x] En `client/customers`, el nombre del cliente navega al perfil.
- [x] En `client/customers`, el boton de detalles navega al perfil.
- [x] En `client/customers`, la cantidad de caballos navega al perfil con tab `Caballos`.
- [x] En `client/customers`, el adeudo general navega al perfil con tab `Cuentas`.
- [x] En `client/customers/{customer}`, el tab `Caballos` tiene KPIs propios.
- [x] En `client/customers/{customer}`, el tab `Cuentas` tiene KPIs propios.
- [x] En `client/customers/{customer}`, el tab `Historial de Pagos` tiene KPIs propios.
- [x] En `Caballos`, los botones `Agregar servicios` y `Agregar paciente` quedan juntos en el mismo row.
- [x] En `Cuentas`, el boton `Crear cuenta por rango` reutiliza el mismo modal de corte manual del index.

KPIs por tab:

```txt
Caballos:
    1. Caballos registrados
       - total de caballos
       - inactivos en texto secundario
    2. Ultimo caballo atendido
       - nombre del caballo
       - fecha y servicio aplicado
    3. Servicios del mes
       - cantidad de servicios del mes
       - monto acumulado del mes

Cuentas:
    1. Cargos acumulados
       - total historico de servicios/cargos
       - cantidad de servicios registrados
    2. Cargos del mes
       - monto del mes actual
       - cantidad de servicios del mes
    3. Balance actual
       - cargos acumulados - abonos acumulados

Historial de Pagos:
    1. Total abonado
       - monto total de pagos/abonos
       - cantidad total de pagos
    2. Pagos del mes
       - monto pagado en el mes actual
       - cantidad de pagos del mes
    3. Balance actual
       - mismo balance de Cuentas
```

Pendientes visuales:

- [ ] Agregar control para ocultar/mostrar bloque `Acceso APP/WEB del customer`.
- [ ] Agregar control para ocultar/mostrar bloque de KPIs.
- [ ] Guardar preferencia con `localStorage` para recordar la eleccion por navegador.
- [ ] Definir llaves de `localStorage`, por ejemplo:

```txt
client_customer_show_hide_access
client_customer_show_hide_kpis
```

- [ ] Cuando un bloque este oculto, mostrar un CTA compacto:
  - `Mostrar acceso APP`
  - `Mostrar KPIs`
- [ ] Validar que el layout no brinque visualmente en desktop ni mobile.

Decision de implementacion:

Para estos ocultables, usar primero `localStorage` y Alpine. No requiere migracion ni backend. Si despues se necesita que la preferencia viaje entre computadoras/dispositivos, moverlo a una preferencia por usuario en base de datos.

Resultado esperado:

El perfil del cliente muestra indicadores relevantes segun el tab activo, permite navegar desde el index a la seccion correcta y deja ocultar bloques grandes para trabajar con menos ruido visual.

---

## 19. QA comparativo por modo - hallazgos y checkpoints

Roadmap movil relacionado:

- `vetsys/docs/mobile-monthly-cutoff-sync-roadmap.md`
- `gorozpeApp/docs/mobile-monthly-cutoff-sync-roadmap.md`

Fecha de revision: 2026-07-12

Objetivo:

Comparar el comportamiento actual de `note_based` contra `monthly_cutoff` para detectar donde todavia se mezclan conceptos de cobranza por nota con cobranza por cuenta mensual.

Alcance revisado:

- Rutas `client/*`.
- `CustomerController@show`.
- `NoteController@index/show/storeManualPayment/createStripePaymentLink`.
- `PaymentController@store/createStripePaymentLink`.
- `StatementController`.
- `CustomerStatementGenerator`.
- Vistas:
  - `client/customers/show`
  - `client/customers/index`
  - `client/ventas/index`
  - `client/ventas/show`
  - `client/animals/edit`
  - `client/customers/statement`

Validaciones ejecutadas:

- [x] `php artisan route:list --path=client`
- [x] `php artisan view:cache`
- [x] `php artisan view:clear`
- [x] Revision con `rg` de condiciones `usesMonthlyCutoffBilling`, pagos, links Stripe y cortes.

### Resultado general del QA

El modo `monthly_cutoff` ya esta bien encaminado en:

- `client/customers/{customer}`:
  - tab `Cuentas` como protagonista de cobranza.
  - cortes generados.
  - recalculo de corte.
  - PDF de corte.
  - abonos globales desde customer.
  - KPIs dinamicos por tab.
- `client/ventas`:
  - en modo mensual muestra historial de servicios desde `note_details`.
  - deja de presentar la lista como notas de venta principales.
- `client/animals/{animal}/edit`:
  - historial por servicios sueltos.
  - filtro por mes.
  - paginador.
  - eliminacion de servicio solo para `monthly_cutoff`.
  - conserva el tab `Historial` al cambiar mes/pagina.
- PDF mensual:
  - consulta `note_details` por periodo.
  - oculta visualmente la nota como jerarquia principal.
  - muestra cargos, pagos y balance.

### Hallazgo critico 1 - `client/ventas/{note}` sigue cobrando por nota

Estado:

- [x] Ejecutado en UI/backend base.

Problema:

En `resources/views/client/ventas/show.blade.php`, la nota individual todavia muestra acciones de cobranza por nota sin distinguir modo:

- `Saldo pendiente`.
- `Cobro con Stripe`.
- `Genera un link para que el cliente liquide esta nota con tarjeta`.
- `Pago manual a esta nota`.
- Formulario `client.ventas.manual-payment`.
- Formulario `client.ventas.stripe-payment-link`.

Impacto:

En `note_based` esto es correcto.

En `monthly_cutoff` rompe la separacion conceptual porque la nota vuelve a sentirse como documento principal de cobro.

Checkpoint:

- [x] Pasar `usesMonthlyCutoffBilling` desde `NoteController@show` a `client.ventas.show`.
- [x] En `monthly_cutoff`, cambiar titulo/ayuda de la nota a respaldo interno / detalle operativo.
- [x] En `monthly_cutoff`, ocultar o desactivar:
  - pago manual por nota.
  - link Stripe por nota.
  - saldo pendiente como CTA principal.
- [x] En `monthly_cutoff`, agregar accesos claros a:
  - perfil del cliente tab `Cuentas`.
  - registrar abono global.
  - cortes del cliente.
- [x] En `note_based`, conservar exactamente el flujo actual.

Nota de ejecucion:

- `client/ventas/{note}` en `monthly_cutoff` queda como respaldo interno y enlaza a `Cuentas` / `Abonos`.
- Las acciones de Stripe y pago manual por nota quedan ocultas en `monthly_cutoff`.
- En `note_based` se conserva la UI de cobro por nota.

Resultado esperado:

`client/ventas/{note}` sirve como respaldo tecnico en modo mensual y como documento cobrable en modo notas.

### Hallazgo critico 2 - Endpoints de pago por nota siguen activos en `monthly_cutoff`

Estado:

- [x] Ejecutado.

Problema:

En `NoteController` siguen disponibles para cualquier tenant:

- `createStripePaymentLink(Note $note)`.
- `storeManualPayment(Request $request, Note $note)`.

Impacto:

Aunque la UI mensual oculte los botones, un usuario podria llegar por ruta directa o por link viejo y registrar un pago contra una nota especifica. Eso contradice la regla:

```txt
monthly_cutoff = pagos como abonos globales por customer_id.
```

Checkpoint:

- [x] Si `tenant->usesMonthlyCutoffBilling()` es true, bloquear `client.ventas.manual-payment`.
- [x] Si `tenant->usesMonthlyCutoffBilling()` es true, bloquear `client.ventas.stripe-payment-link`.
- [x] Redirigir al perfil del cliente tab `Cuentas` con mensaje:

```txt
En modo cuentas mensuales, registra abonos desde la cuenta del cliente.
```

- [x] Mantener disponibles esos endpoints en `note_based`.

Resultado esperado:

La separacion no depende solo de la UI; tambien queda protegida en backend.

### Hallazgo medio 3 - Vista HTML propia del corte

Estado:

- [x] Ejecutado.

Problema:

El tab `Cuentas` lista cortes y el boton `Abrir` apunta al PDF. Todavia no existe una vista HTML intermedia tipo:

```txt
client/customers/{customer}/statements/{statement}
```

Impacto:

El PDF funciona, pero el tenant no tiene una pantalla operativa para revisar el corte antes de imprimir.

Checkpoint:

- [x] Crear ruta `client.customers.statements.show`.
- [x] Crear metodo en `StatementController`.
- [x] Crear vista HTML del corte.
- [x] Mostrar:
  - periodo.
  - saldo anterior.
  - cargos del periodo.
  - abonos del periodo.
  - balance final.
  - servicios agrupados por mes/paciente.
  - boton PDF.
  - boton recalcular.
- [x] Cambiar boton `Abrir` del tab `Cuentas` para abrir esta vista y dejar PDF como accion secundaria.

Nota de ejecucion:

- `Abrir` ahora entra a la vista HTML del corte.
- `PDF` queda como accion secundaria en la tabla y como boton dentro de la vista.
- `Recalcular` desde la vista vuelve al mismo corte.

Resultado esperado:

El corte existe como documento operativo en pantalla y no solo como PDF.

### Hallazgo medio 4 - Relacion corte -> cargos incluidos no esta persistida

Estado:

- [ ] Decision pendiente.

Problema:

Actualmente el corte mensual funciona como foto recalculable por rango de fechas consultando `note_details`. Esto permite recalcular, pero no guarda explicitamente que renglones quedaron incluidos en el corte original.

Opciones:

1. Mantener corte recalculable por fecha.
   - Menos tablas.
   - Mas simple para demo y legacy.
   - Si se agregan/eliminan servicios historicos, el corte cambia al recalcular.

2. Crear relacion `customer_statement_note_details`.
   - Mayor trazabilidad.
   - Permite saber que cargos exactos estaban incluidos.
   - Permite comparar corte original vs recalculado.
   - Evita dudas con rangos solapados.

Checkpoint:

- [ ] Decidir si el primer release queda como corte recalculable.
- [ ] Si se requiere trazabilidad fuerte, crear tabla pivote.
- [ ] Si se mantiene recalculable, documentar regla:

```txt
El corte se actualiza al recalcular y representa la foto vigente del rango.
```

Resultado esperado:

La regla de negocio queda explicita y evita interpretaciones contradictorias.

### Hallazgo medio 5 - Auditoria de saldos pendiente

Estado:

- [ ] Pendiente.

Problema:

Se detectaron diferencias historicas en pruebas anteriores. Aunque parecen venir de data vieja o rangos solapados, falta una herramienta rapida para auditar calculos.

Checkpoint:

- [ ] Crear comando o reporte temporal local que liste por customer:
  - `note_detail_id`.
  - fecha de nota.
  - folio interno.
  - paciente.
  - servicio.
  - subtotal.
  - status de nota.
  - si cae dentro de cada corte.
  - pagos del periodo.
  - balance esperado.
- [ ] Usarlo con cliente de prueba monthly.
- [ ] Comparar:
  - KPIs de customer show.
  - tab `Cuentas`.
  - PDF.
  - `client/ventas`.
  - historial por paciente.

Resultado esperado:

Cuando haya diferencias, se puede encontrar exactamente que cargo o pago las causa.

### Hallazgo bajo 6 - Limpieza visual/encoding

Estado:

- [x] Barrido inicial ejecutado.
- [ ] Mantener vigilancia en nuevas vistas.

Problema:

Algunos iconos/emojis se rompieron por encoding y aparecieron como simbolos raros.

Accion tomada:

- [x] Barrido de `resources/views`.
- [x] Reemplazo de iconos rotos por entidades HTML o texto simple.
- [x] `php artisan view:cache` exitoso.

Checkpoint pendiente:

- [ ] Evitar introducir emojis directos en Blade.
- [ ] Preferir SVG, entidades HTML o texto.
- [ ] Si se usan iconos, preferir componentes/SVG estables.

### Plan de ataque propuesto para cerrar separacion por modo

Orden recomendado:

1. **Cerrar `ventas.show` por modo**
   - Ocultar cobros por nota en monthly.
   - Cambiar copy a respaldo interno.
   - Agregar CTA a Cuentas / Abono global.

2. **Blindar backend de pagos por nota**
   - Bloquear manual payment por nota en monthly.
   - Bloquear Stripe por nota en monthly.
   - Redirigir a customer tab `Cuentas`.

3. **Crear vista HTML de corte**
   - Ruta show.
   - Vista con cargos, pagos y balance.
   - PDF como accion secundaria.

4. **Auditoria de saldos**
   - Comando/reporte temporal.
   - Validar cliente monthly de prueba.
   - Comparar KPIs, cortes y PDF.

5. **Decision de trazabilidad**
   - Mantener corte recalculable por fecha para demo.
   - O crear pivote corte -> `note_details` si el cliente pide auditoria fuerte.

6. **QA final por modo**
   - Tenant `note_based`.
   - Tenant `monthly_cutoff`.
   - Flujos:
     - crear servicios.
     - editar/eliminar cargos.
     - generar/recalcular corte.
     - registrar abono.
     - PDF.
     - Stripe si aplica.

Definicion de terminado:

- [ ] `note_based` puede operar como antes sin cambios funcionales.
- [ ] `monthly_cutoff` no muestra ni permite pagos por nota como camino principal.
- [ ] `monthly_cutoff` cobra por cuenta global del cliente.
- [ ] Los cortes se imprimen con lenguaje de cuenta/cargos/abonos.
- [ ] Los saldos coinciden entre KPIs, cortes, PDF e historial.
