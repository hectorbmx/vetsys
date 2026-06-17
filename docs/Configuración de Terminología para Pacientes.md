# Tarea: Configuración de Terminología para Pacientes

## Objetivo

Permitir que cada tenant personalice el término utilizado para referirse a los animales registrados en el sistema.

Actualmente el sistema utiliza términos fijos como "Mascotas" o "Pacientes", pero algunos clientes trabajan con nichos específicos (caballos, ganado, animales exóticos, etc.) y desean utilizar una terminología más acorde a su operación.

---

## Ejemplos de uso

| Tipo de clínica      | Término deseado |
| -------------------- | --------------- |
| Veterinaria general  | Mascotas        |
| Hospital veterinario | Pacientes       |
| Clínica equina       | Caballos        |
| Ganadería            | Bovinos         |
| Zoológico            | Ejemplares      |
| Fauna silvestre      | Animales        |

---

## Requerimiento funcional

Agregar una configuración a nivel Tenant:

### Configuración

Menú:

Configuración → Personalización

Campo:

**Nombre para los pacientes**

Tipo: Texto corto

Valor por defecto:

```text
Pacientes
```

Ejemplos válidos:

* Pacientes
* Mascotas
* Caballos
* Bovinos
* Animales
* Ejemplares

---

## Base de datos

Agregar columna a la tabla:

```sql
tenants
```

Nueva columna:

```sql
patient_label VARCHAR(50) DEFAULT 'Pacientes'
```

---

## Backend

Crear helper centralizado:

```php
tenant_patient_label()
```

o

```php
TenantSettingsService::patientLabel()
```

Que obtenga el valor configurado por el tenant actual.

Si no existe valor:

```text
Pacientes
```

---

## Frontend

Reemplazar textos hardcodeados como:

```text
Mascotas
Paciente
Nueva Mascota
Lista de Mascotas
```

por llamadas dinámicas.

Ejemplos:

```php
{{ tenant_patient_label() }}
```

o

```php
__('patient_label')
```

según la arquitectura existente.

---

## Textos derivados

Además del nombre principal, generar automáticamente:

| Singular | Plural    |
| -------- | --------- |
| Paciente | Pacientes |
| Mascota  | Mascotas  |
| Caballo  | Caballos  |
| Bovino   | Bovinos   |

### Opción simple (Fase 1)

Solicitar únicamente el plural.

Ejemplo:

```text
Pacientes
Mascotas
Caballos
```

Y utilizarlo en encabezados y menús.

---

## Alcance inicial

Actualizar:

* Menú lateral
* Dashboard
* Listado de pacientes
* Formulario de alta
* Historial clínico
* Búsquedas globales
* Widgets y KPIs

---

## Beneficios

* Mayor adaptación a diferentes nichos veterinarios.
* Sensación de producto personalizado.
* Reduce resistencia de adopción en clínicas especializadas.
* Evita forzar terminología genérica que no representa el negocio del cliente.

---

## Fase futura

Permitir personalizar múltiples términos:

| Concepto        | Valor        |
| --------------- | ------------ |
| Pacientes       | Caballos     |
| Clientes        | Propietarios |
| Consultas       | Revisiones   |
| Hospitalización | Estabulación |

Convirtiendo la plataforma en un sistema altamente adaptable sin necesidad de desarrollos específicos por industria.
