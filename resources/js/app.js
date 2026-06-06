import Alpine from 'alpinejs';
import './bootstrap';

window.Alpine = Alpine;

// Componentes Alpine
Alpine.data('pagoModal', (customerId) => ({
    open: false,
    amount: '',
    loading: false,
    distribution: [],
    leftover: 0,
    paymentMethodId: '',
    isCard: false,

    fmt(val) {
        return parseFloat(val).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    async fetchPreview() {
        const amt = parseFloat(this.amount);
        if (!amt || amt <= 0) {
            this.distribution = [];
            this.leftover = 0;
            return;
        }

        this.loading = true;

        try {
            const res = await fetch(
                `/client/customers/${customerId}/payments/preview?amount=${amt}`,
                { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const data = await res.json();
            this.distribution = data.distribution ?? [];
            this.leftover     = data.leftover ?? 0;
        } catch (e) {
            this.distribution = [];
            this.leftover = 0;
        } finally {
            this.loading = false;
        }
    }
}));

Alpine.start();
