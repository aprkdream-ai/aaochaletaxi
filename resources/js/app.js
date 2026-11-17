import Collapse from 'bootstrap/js/src/collapse.js';
import Dropdown from 'bootstrap/js/src/dropdown.js';
import Modal from 'bootstrap/js/src/modal.js';
import Offcanvas from 'bootstrap/js/src/offcanvas.js';
import Toast from 'bootstrap/js/src/toast.js';
import Alpine from 'alpinejs';

window.bootstrap = { Collapse, Dropdown, Modal, Offcanvas, Toast };

// Theme store (initial value is applied by an inline script in <head> to avoid flashes).
Alpine.store('theme', {
    value: document.documentElement.getAttribute('data-bs-theme') || 'light',
    toggle() {
        this.value = this.value === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', this.value);
        try { localStorage.setItem('theme', this.value); } catch { /* storage unavailable */ }
    },
});

// Quote form: trip-type tabs + round-trip toggle.
Alpine.data('quoteForm', (initial = 'outstation_oneway') => ({
    trip: initial,
    get group() {
        if (this.trip.startsWith('outstation')) return 'outstation';
        if (['hourly', 'full_day'].includes(this.trip)) return 'rental';
        if (['airport', 'railway'].includes(this.trip)) return 'transfer';
        return 'local';
    },
    pick(group) {
        this.trip = { outstation: 'outstation_oneway', local: 'local', transfer: 'airport', rental: 'hourly' }[group];
    },
}));

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toast').forEach((el) => new Toast(el, { delay: 5000 }).show());

    // Loading state + double-submit protection.
    document.querySelectorAll('form[data-loading]').forEach((form) => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('[type="submit"]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>');
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

    // Reveal-on-scroll (content is visible without JS; .js class added in <head>).
    const items = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => entries.forEach((en) => {
            if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
        }), { rootMargin: '0px 0px -8% 0px' });
        items.forEach((el) => io.observe(el));
    } else {
        items.forEach((el) => el.classList.add('in'));
    }
});
