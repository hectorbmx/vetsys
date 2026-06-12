import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

const STORAGE_PREFIX = 'vetsys.contextual-tour';

const tours = {
    dashboard: {
        version: 1,
        steps: [
            {
                element: '[data-tour="dashboard-welcome"]',
                popover: {
                    title: 'Tu panel general',
                    description: 'Aqui tienes una lectura rapida de la operacion diaria de tu clinica.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="main-navigation"]',
                popover: {
                    title: 'Navegacion principal',
                    description: 'Desde este menu puedes administrar clientes, mascotas, ventas, servicios y la configuracion de tu clinica.',
                    side: 'right',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="operational-onboarding"]',
                optional: true,
                popover: {
                    title: 'Onboarding operativo',
                    description: 'Esta tarjeta mide acciones reales de tu clinica. Es independiente de esta guia y avanza cuando completas cada actividad.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="dashboard-metrics"]',
                popover: {
                    title: 'Indicadores principales',
                    description: 'Consulta rapidamente clientes, mascotas, notas e ingresos registrados.',
                    side: 'top',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="recent-sales"]',
                popover: {
                    title: 'Notas recientes',
                    description: 'Revisa los ultimos movimientos de venta y entra al historial completo cuando necesites mas detalle.',
                    side: 'top',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="notifications"]',
                popover: {
                    title: 'Notificaciones',
                    description: 'Los eventos importantes y avisos pendientes aparecen aqui.',
                    side: 'bottom',
                    align: 'end',
                },
            },
            {
                element: '[data-tour-launch]',
                popover: {
                    title: 'Repite la guia cuando quieras',
                    description: 'Usa este boton para volver a iniciar la guia contextual de la pantalla actual.',
                    side: 'bottom',
                    align: 'end',
                },
            },
        ],
    },
};

function storageKey(tourName, version) {
    return `${STORAGE_PREFIX}.${tourName}.v${version}`;
}

function availableSteps(steps) {
    return steps.filter((step) => !step.optional || document.querySelector(step.element));
}

function markAsSeen(tourName, version) {
    try {
        window.localStorage.setItem(storageKey(tourName, version), 'seen');
    } catch {
        // The tour remains usable when browser storage is unavailable.
    }
}

function hasBeenSeen(tourName, version) {
    try {
        return window.localStorage.getItem(storageKey(tourName, version)) === 'seen';
    } catch {
        return false;
    }
}

export function startContextualTour(tourName) {
    const tour = tours[tourName];

    if (!tour) {
        return;
    }

    const tourDriver = driver({
        animate: true,
        allowClose: true,
        overlayClickBehavior: 'close',
        showProgress: true,
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        doneBtnText: 'Finalizar',
        progressText: '{{current}} de {{total}}',
        popoverClass: 'vetsys-tour-popover',
        steps: availableSteps(tour.steps),
        onDestroyed: () => markAsSeen(tourName, tour.version),
    });

    tourDriver.drive();
}

export function initializeContextualTours() {
    const tourName = document.body.dataset.contextualTour;
    const tour = tours[tourName];

    if (!tour) {
        return;
    }

    document.querySelectorAll('[data-tour-launch]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => startContextualTour(tourName));
    });

    if (!hasBeenSeen(tourName, tour.version)) {
        window.setTimeout(() => startContextualTour(tourName), 700);
    }
}
