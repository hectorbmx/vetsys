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
