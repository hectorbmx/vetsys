# Guia de tokens visuales del panel cliente

## Objetivo

Centralizar los colores de marca del panel cliente sin cambiar su apariencia
actual. La primera paleta se llama `ocean` y reproduce los colores existentes.

Durante la fase 1 los tokens y clases existen, pero no se aplican todavia en las
vistas. La migracion de vistas se realiza gradualmente en fases posteriores.

## Inventario inicial

Auditoria realizada sobre `resources/views/client`,
`resources/views/layouts/client.blade.php` y `resources/css/app.css`.

| Color | Usos aproximados | Clasificacion | Decision |
| --- | ---: | --- | --- |
| `#38B2AC` | 357 | Marca primaria | Convertir gradualmente a tokens de tema |
| `#0F172A` | 465 | Marca oscura y encabezados | Convertir gradualmente a tokens de tema |
| `#2C9A94` | 16 | Hover de marca | Convertir a `--theme-primary-hover` |
| `#238A85` | 4 | Texto fuerte de marca | Convertir a `--theme-primary-strong` |
| `#0B1222` | 1 | Footer del sidebar | Convertir a `--theme-sidebar-footer` |
| `#635BFF` | 10 | Stripe | Mantener fijo |
| `#25D366` | 7 | WhatsApp | Mantener fijo |

Los verdes de exito, rojos de error, ambar de advertencia y colores que expresan
estados clinicos o financieros son funcionales. No deben depender de la paleta
del tenant.

## Tokens definitivos de la fase 1

| Token | Valor `ocean` | Uso |
| --- | --- | --- |
| `--theme-primary` | `#38B2AC` | Acento principal |
| `--theme-primary-hover` | `#2C9A94` | Hover de controles primarios |
| `--theme-primary-soft` | `rgb(56 178 172 / 10%)` | Fondos suaves |
| `--theme-primary-soft-hover` | `rgb(56 178 172 / 20%)` | Hover de fondos suaves |
| `--theme-primary-border` | `rgb(56 178 172 / 30%)` | Bordes suaves |
| `--theme-primary-strong` | `#238A85` | Texto de marca con mayor contraste |
| `--theme-primary-contrast` | `#FFFFFF` | Texto sobre botones primarios |
| `--theme-primary-ink` | `#0F172A` | Texto oscuro sobre acentos de marca |
| `--theme-sidebar` | `#0F172A` | Fondo principal del sidebar |
| `--theme-sidebar-hover` | `#1E293B` | Hover del encabezado del sidebar |
| `--theme-sidebar-footer` | `#0B1222` | Fondo del pie del sidebar |
| `--theme-heading` | `#0F172A` | Encabezados de marca |
| `--theme-focus-ring` | `rgb(56 178 172 / 10%)` | Anillos de enfoque |

## Clases semanticas iniciales

```text
theme-bg-primary
theme-bg-primary-soft
theme-text-primary
theme-text-primary-strong
theme-text-primary-ink
theme-border-primary
theme-border-primary-soft
theme-ring-primary
theme-bg-sidebar
theme-bg-sidebar-footer
theme-text-heading
theme-link-primary
theme-button-primary
theme-button-dark
theme-surface-dark
theme-tab-active
theme-input
theme-file-input
theme-overlay
theme-border-top-primary
theme-progress-primary
theme-nav-active
theme-shadow-primary
theme-focus-primary
theme-hover-border-primary-soft
theme-hover-bg-primary
theme-hover-text-heading
theme-hover-text-primary
theme-hover-text-primary-strong
theme-group-hover-text-primary
theme-peer-checked-bg-primary
theme-peer-focus-ring-primary
theme-focus-ring-primary
```

## Uso correcto

```html
<h1 class="theme-text-heading">Clientes</h1>
<button class="theme-button-primary">Guardar</button>
<a class="theme-text-primary">Ver detalles</a>
```

Use tokens cuando el color represente identidad visual y deba cambiar junto con
la paleta del tenant.

## Uso incorrecto

```html
<p class="theme-text-primary">Error al guardar</p>
<span class="theme-bg-primary">Pago vencido</span>
<button class="theme-button-primary">Pagar con Stripe</button>
```

Errores, advertencias, exitos, estados financieros y marcas externas deben
conservar sus colores funcionales u oficiales.

## Reglas de migracion

1. Migrar una seccion a la vez.
2. Comparar cada seccion contra una referencia visual.
3. No hacer reemplazos globales por codigo hexadecimal.
4. No aplicar tokens al panel administrativo, vistas publicas o documentos.
5. Mantener `ocean` como fallback para cualquier paleta ausente o invalida.
6. Conservar colores propios de metricas cuando expresan categorias o estados,
   aunque coincidan actualmente con un color de marca.
