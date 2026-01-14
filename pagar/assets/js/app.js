/**
 * Quibu - Sistema de Pagos
 * JavaScript principal para validación y cálculos
 */

// Validador de RUT chileno
const RutValidator = {
    // Limpia el RUT de puntos y guiones
    clean: function(rut) {
        return rut.replace(/^0+|\\.|-/g, "");
    },

    // Formatea el RUT con guión
    format: function(rut) {
        const cleaned = this.clean(rut);
        if (cleaned.length < 2) return cleaned;
        return cleaned.slice(0, -1) + "-" + cleaned.slice(-1);
    },

    // Valida el dígito verificador
    validate: function(rut) {
        const cleaned = this.clean(rut);
        if (cleaned.length < 2) return false;

        const cuerpo = cleaned.slice(0, -1);
        const dv = cleaned.slice(-1).toUpperCase();

        let suma = 0;
        let multiplo = 2;

        for (let i = cuerpo.length - 1; i >= 0; i--) {
            suma += parseInt(cuerpo.charAt(i)) * multiplo;
            multiplo = multiplo < 7 ? multiplo + 1 : 2;
        }

        let dvEsperado = 11 - (suma % 11);
        dvEsperado = dvEsperado === 11 ? "0" : dvEsperado === 10 ? "K" : dvEsperado.toString();

        return dv === dvEsperado;
    }
};

// Calculadora de fees
const FeeCalculator = {
    // Tabla de fees por tramos
    tramos: [
        { min: 1000, max: 5000, feeBase: 200, feeProg: 0.005 },
        { min: 5100, max: 8000, feeBase: 220, feeProg: 0.006 },
        { min: 8100, max: 10000, feeBase: 230, feeProg: 0.008 },
        { min: 10100, max: 20000, feeBase: 260, feeProg: 0.01 },
        { min: 20100, max: 30000, feeBase: 300, feeProg: 0.01 },
        { min: 30001, max: Infinity, feeBase: 300, feeProg: 0.01 }
    ],

    // Obtiene el tramo correspondiente al monto
    getTramo: function(monto) {
        for (let tramo of this.tramos) {
            if (monto >= tramo.min && monto <= tramo.max) {
                return tramo;
            }
        }
        return this.tramos[this.tramos.length - 1]; // Fallback al último tramo
    },

    // Calcula el fee para una cuota individual
    calcularFeeCuota: function(valorCuota) {
        const tramo = this.getTramo(valorCuota);
        const feeProgresivo = valorCuota * tramo.feeProg;
        const feeTotal = tramo.feeBase + feeProgresivo;
        return Math.round(feeTotal);
    },

    // Calcula el total con fee para múltiples cuotas
    calcularTotal: function(valorCuota, cantidadCuotas) {
        const subtotal = valorCuota * cantidadCuotas;
        const feeTotal = this.calcularFeeCuota(valorCuota) * cantidadCuotas;
        return {
            subtotal: subtotal,
            fee: feeTotal,
            total: subtotal + feeTotal
        };
    }
};

// Formateador de números
const NumberFormatter = {
    toCLP: function(numero) {
        return new Intl.NumberFormat('es-CL').format(Math.round(numero));
    }
};

// Gestor de formulario de RUT
class RutForm {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        if (!this.form) return;

        this.input = this.form.querySelector('input[name="rut"]');
        this.errorDiv = this.form.querySelector('.error-message');

        this.init();
    }

    init() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        this.input.addEventListener('input', () => this.clearError());
    }

    handleSubmit(e) {
        const rutValue = this.input.value.trim();
        const rutFormateado = RutValidator.format(rutValue);

        if (!RutValidator.validate(rutFormateado)) {
            e.preventDefault();
            this.showError('RUT inválido. Verifica el formato (ej: 12345678-9)');
            this.input.classList.add('error');
        } else {
            this.input.value = rutFormateado;
        }
    }

    showError(message) {
        if (this.errorDiv) {
            this.errorDiv.textContent = message;
            this.errorDiv.classList.add('active');
        }
    }

    clearError() {
        if (this.errorDiv) {
            this.errorDiv.classList.remove('active');
        }
        this.input.classList.remove('error');
    }
}

// Gestor de selección de cuotas (checkboxes)
class CuotasCheckboxSelector {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        if (!this.form) return;

        this.checkboxes = this.form.querySelectorAll('.cuota-checkbox');
        this.totalSpan = document.querySelector('#total-pagar');
        this.subtotalSpan = document.querySelector('#subtotal-pagar');
        this.feeSpan = document.querySelector('#fee-pagar');
        this.submitBtn = this.form.querySelector('button[type="submit"]');

        this.init();
    }

    init() {
        this.checkboxes.forEach(cb => {
            cb.addEventListener('change', () => this.updateTotal());
        });
        this.updateTotal();
    }

    updateTotal() {
        let total = 0;
        let subtotal = 0;
        let fee = 0;
        let seleccionadas = 0;

        this.checkboxes.forEach(cb => {
            if (cb.checked) {
                const montoConFee = parseInt(cb.dataset.monto, 10);
                const montoBase = parseInt(cb.dataset.montoBase, 10);
                total += montoConFee;
                subtotal += montoBase;
                fee += (montoConFee - montoBase);
                seleccionadas++;
                cb.closest('.cuota-item').classList.add('selected');
            } else {
                cb.closest('.cuota-item').classList.remove('selected');
            }
        });

        if (this.totalSpan) {
            this.totalSpan.textContent = NumberFormatter.toCLP(total);
        }
        if (this.subtotalSpan) {
            this.subtotalSpan.textContent = NumberFormatter.toCLP(subtotal);
        }
        if (this.feeSpan) {
            this.feeSpan.textContent = NumberFormatter.toCLP(fee);
        }

        if (this.submitBtn) {
            this.submitBtn.disabled = seleccionadas === 0;
        }
    }
}

// Gestor de selector dropdown de cuotas
class CuotasDropdownSelector {
    constructor(selectSelector) {
        this.select = document.querySelector(selectSelector);
        if (!this.select) return;

        this.valorCuota = parseFloat(this.select.dataset.valorCuota);
        this.resultDiv = document.querySelector('#resultado');
        this.totalInput = document.querySelector('#total_a_pagar');

        this.init();
    }

    init() {
        this.select.addEventListener('change', () => this.calcular());
        this.calcular();
    }

    calcular() {
        const cuotasSeleccionadas = parseInt(this.select.value, 10);
        const resultado = FeeCalculator.calcularTotal(this.valorCuota, cuotasSeleccionadas);

        if (this.resultDiv) {
            this.resultDiv.innerHTML = `
                <div class="payment-summary">
                    <div class="summary-row">
                        <span class="summary-label">Subtotal cuotas:</span>
                        <span class="summary-value">$${NumberFormatter.toCLP(resultado.subtotal)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Costo Servicio Automatizado Quibu:</span>
                        <span class="summary-value">$${NumberFormatter.toCLP(resultado.fee)}</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total a pagar:</span>
                        <span>$${NumberFormatter.toCLP(resultado.total)}</span>
                    </div>
                </div>
            `;
        }

        if (this.totalInput) {
            this.totalInput.value = Math.round(resultado.total);
        }
    }
}

// Validación de formulario de registro
class RegistroForm {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        if (!this.form) return;

        this.init();
    }

    init() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }

    handleSubmit(e) {
        const nombre = this.form.querySelector('[name="nombre"]').value.trim();
        const email = this.form.querySelector('[name="email"]').value.trim();
        const telefono = this.form.querySelector('[name="telefono"]').value.trim();

        if (!nombre || !email || !telefono) {
            e.preventDefault();
            alert('Por favor completa todos los campos');
            return false;
        }

        // Validación básica de email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Por favor ingresa un email válido');
            return false;
        }

        // Validación básica de teléfono (números y símbolos comunes)
        const telefonoRegex = /^[\d\s\-\+\(\)]+$/;
        if (!telefonoRegex.test(telefono)) {
            e.preventDefault();
            alert('Por favor ingresa un teléfono válido');
            return false;
        }
    }
}

// Inicialización al cargar el DOM
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar validador de RUT
    new RutForm('#rut-form');

    // Inicializar selector de cuotas (checkboxes)
    new CuotasCheckboxSelector('#form-cuotas');

    // Inicializar selector de cuotas (dropdown)
    new CuotasDropdownSelector('#cuotas_a_pagar');

    // Inicializar formulario de registro
    new RegistroForm('#registro-form');

    // Animación de fade-in
    document.querySelectorAll('.fade-in').forEach(el => {
        el.style.opacity = '0';
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '1';
        }, 100);
    });
});
