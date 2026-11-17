import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';

window.bootstrap = bootstrap;
window.Alpine = Alpine;

// Theme (light/dark) persisted per browser.
Alpine.store('theme', {
    value: (() => { try { return localStorage.getItem('theme') || 'light'; } catch { return 'light'; } })(),
    toggle() {
        this.value = this.value === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', this.value);
        try { localStorage.setItem('theme', this.value); } catch { /* storage unavailable */ }
    },
});
document.documentElement.setAttribute('data-bs-theme', Alpine.store('theme').value);

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toast').forEach((el) => new bootstrap.Toast(el, { delay: 5000 }).show());
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Prevent double submits & show loading state.
    document.querySelectorAll('form[data-loading]').forEach((form) => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');
            }
        });
    });

    // Confirm dialogs for destructive actions.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!window.confirm(form.dataset.confirm)) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        }, { capture: true });
    });
});
