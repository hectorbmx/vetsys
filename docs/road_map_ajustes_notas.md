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
    Los pagos se registran como abonos del cliente o del corte, no forzosamente contra una nota específica.
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
customer_statement_sale_note
```

Campos sugeridos:

```php
id
customer_statement_id
sale_note_id
amount
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

En modo `monthly_cutoff`, el tenant debe poder registrar pagos como abonos del cliente o del corte.

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
customer_statement_id nullable
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
    El pago normalmente tendrá customer_statement_id o solo customer_id.
    sale_note_id puede ser null.
```

Esto permite que el mismo sistema soporte:

- Pagos por nota.
- Abonos mensuales.
- Pagos contra un corte.
- Pagos globales del cliente.

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
customer_statement_id nullable
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

Permitir registrar abonos del cliente o del corte.

Tareas:

1. Crear o adaptar tabla `customer_payments`.
2. Permitir pagos con `sale_note_id` nullable.
3. Permitir pagos con `customer_statement_id` nullable.
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

