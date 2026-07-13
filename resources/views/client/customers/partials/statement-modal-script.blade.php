<script>
    window.statementModalState = function () {
        return {
            statementModal: false,
            statementLoading: false,
            statementError: '',
            statementCustomer: {
                id: null,
                name: '',
                previewUrl: '',
                storeUrl: '',
            },
            statementForm: {
                date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
                date_to: new Date().toISOString().slice(0, 10),
            },
            statementPreview: null,

            openStatementModal(customer) {
                this.statementCustomer = customer;
                this.statementPreview = null;
                this.statementError = '';
                this.statementModal = true;
            },

            closeStatementModal() {
                if (this.statementLoading) {
                    return;
                }

                this.statementModal = false;
            },

            async fetchStatementPreview() {
                this.statementError = '';
                this.statementPreview = null;

                if (!this.statementForm.date_from || !this.statementForm.date_to) {
                    this.statementError = 'Selecciona fecha inicio y fecha fin.';
                    return;
                }

                this.statementLoading = true;

                try {
                    const params = new URLSearchParams(this.statementForm);
                    const response = await fetch(`${this.statementCustomer.previewUrl}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo consultar el corte.');
                    }

                    this.statementPreview = await response.json();
                } catch (error) {
                    this.statementError = error.message || 'No se pudo consultar el corte.';
                } finally {
                    this.statementLoading = false;
                }
            },

            fmt(value) {
                return Number(value || 0).toLocaleString('es-MX', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            },
        };
    };
</script>
