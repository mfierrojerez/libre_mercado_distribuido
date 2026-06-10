/**
 * SisDist Marketplace - app.js
 *
 * Versión alineada al backend actual:
 * - No accede a base de datos desde el navegador.
 * - No simula carrito ni pedidos en frontend.
 * - Usa formularios HTML reales y mejora progresiva.
 * - Muestra notificaciones con Bootstrap Toast si está disponible.
 */

(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    ready(() => {
        console.log('SisDist Marketplace cargado');

        initFlashMessages();
        initRemoveCartButtons();
        initQuantityForms();
        initCheckoutForm();
        initInventoryFilters();
    });

    /**
     * Lee mensajes flash embebidos en el HTML.
     *
     * Ejemplo esperado:
     * <div hidden data-flash-message="Producto agregado" data-flash-type="success"></div>
     */
    function initFlashMessages() {
        const flashNodes = document.querySelectorAll('[data-flash-message]');

        flashNodes.forEach((node) => {
            const message = (node.dataset.flashMessage || '').trim();
            const type = (node.dataset.flashType || 'info').trim();

            if (message !== '') {
                showNotification(message, type);
            }
        });
    }

    /**
     * Confirmación para botones/enlaces de eliminar item del carrito.
     *
     * Soporta:
     * - data-cart-remove
     * - .remove-btn
     */
    function initRemoveCartButtons() {
        const selectors = document.querySelectorAll('[data-cart-remove], .remove-btn');

        selectors.forEach((element) => {
            const eventName = element.tagName === 'FORM' ? 'submit' : 'click';

            element.addEventListener(eventName, (e) => {
                const confirmed = window.confirm('¿Eliminar este producto del carrito?');
                if (!confirmed) {
                    e.preventDefault();
                }
            });
        });
    }

    /**
     * Auto-submit para formularios de cantidad en carrito.
     *
     * Soporta formularios con:
     * - clase .quantity-form
     * - un <select name="cantidad"> o <input name="cantidad">
     */
    function initQuantityForms() {
        const forms = document.querySelectorAll('.quantity-form');

        forms.forEach((form) => {
            const quantityField =
                form.querySelector('select[name="cantidad"]') ||
                form.querySelector('input[name="cantidad"]');

            if (!quantityField) return;

            const eventName = quantityField.tagName === 'SELECT' ? 'change' : 'change';

            quantityField.addEventListener(eventName, () => {
                if (form.dataset.autosubmit === 'false') return;
                form.submit();
            });
        });
    }

    /**
     * Validaciones ligeras para checkout.
     *
     * No reemplaza validación backend.
     */
    function initCheckoutForm() {
        const checkoutForm = document.getElementById('checkoutForm');
        if (!checkoutForm) return;

        checkoutForm.addEventListener('submit', (e) => {
            const submitButton = checkoutForm.querySelector(
                'button[type="submit"], input[type="submit"]'
            );

            const tipoEntregaField = checkoutForm.querySelector('[name="tipo_entrega"]');
            const direccionField =
                checkoutForm.querySelector('[name="direccion_despacho_id"]') ||
                checkoutForm.querySelector('[name="direccion_envio"]');

            const metodoPagoField = checkoutForm.querySelector('[name="metodo_pago"]');

            if (tipoEntregaField) {
                const tipoEntrega = String(tipoEntregaField.value || '').trim();

                if (
                    tipoEntrega === 'despacho_domicilio' &&
                    direccionField &&
                    String(direccionField.value || '').trim() === ''
                ) {
                    e.preventDefault();
                    showNotification('Debes seleccionar una dirección de despacho.', 'warning');
                    focusField(direccionField);
                    return;
                }
            }

            if (metodoPagoField && String(metodoPagoField.value || '').trim() === '') {
                e.preventDefault();
                showNotification('Debes seleccionar un método de pago.', 'warning');
                focusField(metodoPagoField);
                return;
            }

            if (submitButton) {
                lockSubmitButton(submitButton, 'Procesando...');
            }
        });
    }

    /**
     * Filtro de inventario.
     *
     * Si existe #filterForm, reconstruye la URL actual preservando parámetros existentes.
     */
    function initInventoryFilters() {
        const filterForm = document.getElementById('filterForm');
        if (!filterForm) return;

        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const url = new URL(window.location.href);
            const formData = new FormData(filterForm);

            for (const [key, value] of formData.entries()) {
                const normalized = String(value).trim();

                if (normalized === '') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, normalized);
                }
            }

            window.location.href = url.toString();
        });
    }

    /**
     * Notificaciones:
     * - Usa Bootstrap Toast si bootstrap está disponible.
     * - Si no, usa fallback simple.
     */
    function showNotification(message, type = 'info') {
        const normalizedType = normalizeType(type);

        if (window.bootstrap && typeof window.bootstrap.Toast === 'function') {
            showBootstrapToast(message, normalizedType);
            return;
        }

        showFallbackToast(message, normalizedType);
    }

    function showBootstrapToast(message, type) {
        const container = getToastContainer();

        const colorMap = {
            success: 'text-bg-success',
            error: 'text-bg-danger',
            danger: 'text-bg-danger',
            warning: 'text-bg-warning',
            info: 'text-bg-primary',
        };

        const textColorMap = {
            success: 'btn-close-white',
            error: 'btn-close-white',
            danger: 'btn-close-white',
            warning: '',
            info: 'btn-close-white',
        };

        const toast = document.createElement('div');
        toast.className = `toast align-items-center border-0 ${colorMap[type] || colorMap.info}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${escapeHtml(message)}</div>
                <button
                    type="button"
                    class="btn-close ${textColorMap[type] || ''} me-2 m-auto"
                    data-bs-dismiss="toast"
                    aria-label="Cerrar">
                </button>
            </div>
        `;

        container.appendChild(toast);

        const instance = new window.bootstrap.Toast(toast, {
            autohide: true,
            delay: 3000,
        });

        instance.show();

        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    function showFallbackToast(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;

        const palette = {
            success: { bg: '#198754', color: '#ffffff' },
            error: { bg: '#dc3545', color: '#ffffff' },
            danger: { bg: '#dc3545', color: '#ffffff' },
            warning: { bg: '#ffc107', color: '#000000' },
            info: { bg: '#0d6efd', color: '#ffffff' },
        };

        const theme = palette[type] || palette.info;

        Object.assign(notification.style, {
            position: 'fixed',
            right: '20px',
            bottom: '20px',
            zIndex: '1080',
            maxWidth: '360px',
            padding: '0.875rem 1rem',
            borderRadius: '0.5rem',
            background: theme.bg,
            color: theme.color,
            boxShadow: '0 0.5rem 1rem rgba(0, 0, 0, 0.15)',
            fontWeight: '500',
            lineHeight: '1.4',
        });

        document.body.appendChild(notification);

        window.setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    function getToastContainer() {
        let container = document.getElementById('toast-container');

        if (container) {
            return container;
        }

        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '1080';

        document.body.appendChild(container);

        return container;
    }

    function lockSubmitButton(button, text) {
        if (!button) return;

        if (!button.dataset.originalText) {
            button.dataset.originalText = button.tagName === 'INPUT'
                ? (button.value || '')
                : (button.innerHTML || '');
        }

        button.disabled = true;

        if (button.tagName === 'INPUT') {
            button.value = text;
        } else {
            button.innerHTML = escapeHtml(text);
        }
    }

    function focusField(field) {
        if (!field || typeof field.focus !== 'function') return;

        window.setTimeout(() => {
            field.focus();
        }, 0);
    }

    function normalizeType(type) {
        const normalized = String(type || 'info').toLowerCase().trim();

        if (normalized === 'danger') return 'danger';
        if (normalized === 'error') return 'error';
        if (normalized === 'success') return 'success';
        if (normalized === 'warning') return 'warning';

        return 'info';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }
})();