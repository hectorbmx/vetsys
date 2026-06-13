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
    configuration: {
        version: 1,
        steps: [
            {
                element: '[data-tour="configuration-header"]',
                popover: {
                    title: 'Prepara lo indispensable',
                    description: 'Para realizar tu primera venta necesitas un tipo de animal y al menos un metodo de pago activo.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="animal-type-tab"]',
                popover: {
                    title: 'Primero: tipo de animal',
                    description: 'Registra la raza, especie o tipo que usaras para clasificar a tu primera mascota.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="add-animal-type"]',
                popover: {
                    title: 'Agrega un tipo',
                    description: 'Abre el formulario y registra un tipo activo. Con nombre es suficiente para esta ruta inicial.',
                    side: 'left',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="payment-method-tab"]',
                popover: {
                    title: 'Despues: metodo de pago',
                    description: 'Entra a Metodos de Pago y agrega una opcion activa, por ejemplo Efectivo.',
                    side: 'bottom',
                    align: 'start',
                },
            },
        ],
    },
    services: {
        version: 1,
        steps: [
            {
                element: '[data-tour="services-header"]',
                popover: {
                    title: 'Crea tu primer servicio',
                    description: 'La venta necesita al menos un servicio activo con precio vigente.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="add-service"]',
                popover: {
                    title: 'Agregar al catalogo',
                    description: 'Abre el formulario, conserva el tipo Servicio y captura nombre y precio.',
                    side: 'left',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="services-list"]',
                popover: {
                    title: 'Catalogo disponible',
                    description: 'Aqui confirmas que el servicio quedo activo y listo para venderse.',
                    side: 'top',
                    align: 'start',
                },
            },
        ],
    },
    customers: {
        version: 1,
        steps: [
            {
                element: '[data-tour="customers-header"]',
                popover: {
                    title: 'Registra al propietario',
                    description: 'Toda mascota y venta debe estar relacionada con un cliente.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="add-customer"]',
                popover: {
                    title: 'Nuevo cliente',
                    description: 'Abre el formulario y captura los datos obligatorios del primer propietario.',
                    side: 'left',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="customers-list"]',
                popover: {
                    title: 'Clientes registrados',
                    description: 'Desde este listado puedes consultar al cliente y administrar sus mascotas.',
                    side: 'top',
                    align: 'start',
                },
            },
        ],
    },
    animals: {
        version: 1,
        steps: [
            {
                element: '[data-tour="animals-header"]',
                popover: {
                    title: 'Relaciona una mascota',
                    description: 'La primera venta requiere una mascota activa asociada a un cliente.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="add-animal"]',
                popover: {
                    title: 'Nueva mascota',
                    description: 'Selecciona el propietario, asigna el tipo de animal y captura los datos indispensables.',
                    side: 'left',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="animals-list"]',
                popover: {
                    title: 'Pacientes registrados',
                    description: 'Confirma aqui que la mascota aparece activa y vinculada con su propietario.',
                    side: 'top',
                    align: 'start',
                },
            },
        ],
    },
    'patient-record': {
        version: 1,
        steps: [
            {
                element: '[data-tour="patient-record-header"]',
                popover: {
                    title: 'Expediente del paciente',
                    description: 'Esta ficha concentra la informacion clinica y el seguimiento completo del paciente.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="patient-tab-details"]',
                activate: true,
                popover: {
                    title: 'Datos del paciente',
                    description: 'Consulta y actualiza sus datos generales, propietario, tipo, estado y notas clinicas.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="patient-tab-history"]',
                activate: true,
                popover: {
                    title: 'Historial de servicios',
                    description: 'Revisa todos los servicios y productos registrados previamente para este paciente.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="patient-tab-vaccination"]',
                activate: true,
                popover: {
                    title: 'Cartas de vacunacion',
                    description: 'Genera cartas de vacunacion y compartelas con el propietario del paciente.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="patient-tab-videos"]',
                activate: true,
                popover: {
                    title: 'Videos clinicos',
                    description: 'Sube y conserva videos de tratamientos, procedimientos o seguimiento clinico.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="patient-tab-radiology"]',
                activate: true,
                popover: {
                    title: 'Radiologia',
                    description: 'Crea carpetas por estudio y conserva organizadas las imagenes RX realizadas.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="patient-tab-telemedicine"]',
                activate: true,
                popover: {
                    title: 'Telemedicina',
                    description: 'Comparte el expediente en modo lectura con otro profesional veterinario y revoca el acceso cuando sea necesario.',
                    side: 'bottom',
                    align: 'end',
                },
            },
        ],
    },
    'first-sale': {
        version: 1,
        steps: [
            {
                element: '[data-tour="sale-customer"]',
                popover: {
                    title: '1. Selecciona al cliente',
                    description: 'Busca al propietario registrado. Al seleccionarlo apareceran sus mascotas.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="sale-animals"]',
                popover: {
                    title: '2. Selecciona la mascota',
                    description: 'Elige al paciente relacionado con esta venta.',
                    side: 'bottom',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="sale-items"]',
                popover: {
                    title: '3. Agrega el servicio',
                    description: 'Busca el servicio que creaste y agregalo al detalle de la nota.',
                    side: 'top',
                    align: 'start',
                },
            },
            {
                element: '[data-tour="sale-checkout"]',
                popover: {
                    title: '4. Revisa y guarda',
                    description: 'Puedes registrar la venta a credito o de contado. Guardar la nota completa la ruta.',
                    side: 'left',
                    align: 'start',
                },
            },
        ],
    },
};

function storageKey(tourName, version) {
    return `${STORAGE_PREFIX}.${tourName}.v${version}`;
}

function availableSteps(steps) {
    return steps
        .filter((step) => !step.optional || document.querySelector(step.element))
        .map(({ activate, ...step }) => {
            if (!activate) {
                return step;
            }

            return {
                ...step,
                onHighlightStarted: (element) => element?.click(),
            };
        });
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
