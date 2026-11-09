# Idea futura: simplificar temas a Ocean, Dark y Light

## Objetivo

Evaluar si conviene reemplazar el set actual de paletas por tres opciones mas
simples:

- Ocean: paleta original y predeterminada.
- Dark: modo oscuro para el panel cliente.
- Light: modo claro neutro para el panel cliente.

## Alcance minimo

Si Dark y Light se manejan como paletas de identidad, el cambio seria pequeno:

- Reducir las opciones disponibles en `TenantThemePalettes`.
- Ajustar variables CSS en `resources/css/app.css`.
- Actualizar el tab de Apariencia si cambia el texto de opciones.
- Actualizar pruebas enfocadas de paleta.

Archivos estimados: 4 a 6.

## Riesgo principal

Un Dark theme completo no es solo cambiar colores primarios. Para que se sienta
realmente oscuro habria que tokenizar superficies y textos base que hoy siguen
en clases fijas como `bg-white`, `bg-slate-50`, `border-slate-200` y similares.

En ese caso el alcance subiria a varias vistas del panel cliente, especialmente:

- Tablas y listados.
- Cards.
- Modales.
- Formularios.
- Estados vacios.
- Cabeceras internas.

Archivos estimados para dark completo: 15 a 25 vistas.

## Recomendacion

Mantener esta idea en backlog. Si se ejecuta, decidir primero si Dark sera:

1. Solo una paleta de identidad: rapido y de bajo riesgo.
2. Un modo oscuro completo: requiere fase propia de UI y revision visual amplia.

## Estado

Pendiente secundaria. No iniciar hasta cerrar el flujo prioritario de captura de
servicios desde una nota de venta.

## Hallazgos confirmados

- El selector actual vive en `resources/views/client/mi-configuracion/index.blade.php`.
- Las opciones se definen en `app/Support/TenantThemePalettes.php`.
- La seleccion se guarda en `tenants.theme_palette`.
- El layout aplica la seleccion mediante `data-theme-palette` en
  `resources/views/layouts/client.blade.php`.
- Las variables visuales estan centralizadas en `resources/css/app.css`.
- La implementacion actual cambia acento, sidebar y contraste, pero no define un
  modo claro/oscuro completo.
- `restoreThemePalette()` actualmente restaura `ocean`.
- Si se eliminan las claves actuales, hay que conservar compatibilidad con
  tenants que ya tengan `ocean`, `violet`, `forest` o `sunset` guardado.

## Checkpoints cuando se retome

1. Decidir si `Dark` sera solo una paleta de identidad o un modo oscuro completo.
2. Definir la estrategia de compatibilidad para valores existentes en
   `theme_palette`.
3. Actualizar `TenantThemePalettes` y el selector visual.
4. Ajustar `restoreThemePalette()` y la vista previa.
5. Para Dark completo, tokenizar fondos, tarjetas, tablas, inputs, bordes,
   textos y modales que hoy usan clases fijas claras.
6. Revisar visualmente todas las vistas del panel cliente y validar contraste.
7. Probar persistencia, recarga, cambio de tenant y fallback de valores inválidos.
