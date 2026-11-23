import Collapse from 'bootstrap/js/src/collapse.js';
import Dropdown from 'bootstrap/js/src/dropdown.js';
import Modal from 'bootstrap/js/src/modal.js';
import Offcanvas from 'bootstrap/js/src/offcanvas.js';
import Toast from 'bootstrap/js/src/toast.js';
import Alpine from 'alpinejs';
import './suggest.js';

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

// Booking form: live fare preview + Google distance.
Alpine.data('bookingForm', (previewUrl, distanceUrl, initialType, initialStops) => ({
    type: initialType,
    stops: initialStops || [],
    fare: null,
    error: '',
    busy: false,
    distanceNote: '',
    seq: 0,
    init() { this.$nextTick(() => this.refresh()); },
    inr(v) { const n = Number(v); const d = Number.isInteger(n) ? 0 : 2; return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: d, maximumFractionDigits: d }); },
    headers() { return { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }; },
    async refresh() {
        const data = Object.fromEntries(new FormData(this.$refs.form).entries());
        delete data._token; delete data['stops[]'];
        data.type = this.type; // Alpine state is the source of truth (DOM may not be synced on first run)
        if (!data.vehicle_category_id || !data.pickup_at) return;
        const mine = ++this.seq;
        this.busy = true;
        try {
            const res = await fetch(previewUrl, { method: 'POST', headers: this.headers(), body: JSON.stringify(data) });
            const json = await res.json();
            if (mine !== this.seq) return;
            if (res.ok) { this.fare = json; this.error = ''; } else { this.fare = null; this.error = Object.values(json.errors || {})[0]?.[0] || json.message || ''; }
        } catch { if (mine === this.seq) this.error = 'Could not calculate fare.'; } finally { if (mine === this.seq) this.busy = false; }
    },
    // After picking both pickup and drop from suggestions, calculate the road distance automatically.
    autoDistance(e) {
        if (e.target !== this.$refs.from && e.target !== this.$refs.to) return;
        if (this.$refs.from?.value && this.$refs.to?.value && this.$refs.to.offsetParent !== null) this.fetchDistance();
    },
    async fetchDistance() {
        const from = this.$refs.from?.value, to = this.$refs.to?.value;
        if (!from || !to) { this.distanceNote = 'Enter pickup and drop first.'; return; }
        this.busy = true;
        try {
            const res = await fetch(distanceUrl, { method: 'POST', headers: this.headers(), body: JSON.stringify({ from, to, round: this.type === 'outstation_round' }) });
            const json = await res.json();
            if (!res.ok) { this.distanceNote = json.message; return; }
            this.$refs.km.value = json.km; this.$refs.duration.value = json.minutes;
            this.distanceNote = json.km + ' km · ~' + Math.floor(json.minutes / 60) + 'h ' + (json.minutes % 60) + 'm (' + json.from + ' → ' + json.to + ')';
            this.refresh();
        } finally { this.busy = false; }
    },
}));

// Public booking page: server-side fare estimate + mobile OTP verification before submit.
Alpine.data('micrositeForm', (urls, verified) => ({
    type: urls.type || 'outstation_oneway',
    fare: null, fareMsg: '', busy: false,
    verifiedPhone: verified || '', otpSent: false, otp: '', otpMsg: '', otpErr: '',
    seq: 0,
    headers() { return { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }; },
    inr(v) { return '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 }); },
    data() { const d = Object.fromEntries(new FormData(this.$refs.form).entries()); delete d._token; d.type = this.type; return d; },
    phone() { return (this.$refs.form.customer_phone.value || '').replace(/\D/g, '').slice(-10); },
    async estimate() {
        const d = this.data();
        if (!d.vehicle_category_id || !d.pickup_address || !d.pickup_at) return;
        const mine = ++this.seq; this.busy = true;
        try {
            const res = await fetch(urls.preview, { method: 'POST', headers: this.headers(), body: JSON.stringify(d) });
            const json = await res.json();
            if (mine !== this.seq) return;
            if (res.ok) { this.fare = json; this.fareMsg = ''; } else { this.fare = null; this.fareMsg = Object.values(json.errors || {})[0]?.[0] || json.message || ''; }
        } catch { this.fareMsg = ''; } finally { if (mine === this.seq) this.busy = false; }
    },
    async submit(e) {
        if (this.verifiedPhone && this.verifiedPhone === this.phone()) { this.$refs.form.submit(); return; }
        this.otpErr = '';
        const res = await fetch(urls.otp, { method: 'POST', headers: this.headers(), body: JSON.stringify({ phone: this.phone(), email: this.$refs.form.customer_email.value }) });
        const json = await res.json();
        if (!res.ok) { this.otpErr = Object.values(json.errors || {})[0]?.[0] || json.message; return; }
        this.otpSent = true; this.otpMsg = json.message;
        this.$nextTick(() => this.$refs.otpInput?.focus());
    },
    async verify() {
        this.otpErr = '';
        const res = await fetch(urls.verify, { method: 'POST', headers: this.headers(), body: JSON.stringify({ phone: this.phone(), otp: this.otp, name: this.$refs.form.customer_name.value, email: this.$refs.form.customer_email.value }) });
        const json = await res.json();
        if (!res.ok) { this.otpErr = Object.values(json.errors || {})[0]?.[0] || json.message; return; }
        // Session was regenerated on login: refresh the CSRF token before submitting.
        const fresh = await fetch(location.href, { headers: { Accept: 'text/html' } }).then((r) => r.text());
        const m = fresh.match(/name="csrf-token" content="([^"]+)"/);
        if (m) { this.$refs.form._token.value = m[1]; document.querySelector('meta[name="csrf-token"]').content = m[1]; }
        this.verifiedPhone = this.phone();
        this.$refs.form.submit();
    },
}));

// Marketplace quote request: verify the mobile by OTP (once per session), then submit the form.
Alpine.data('quoteForm', (urls, verified) => ({
    verifiedPhone: verified || '', otpSent: false, otp: '', otpMsg: '', otpErr: '', busy: false,
    headers() { return { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }; },
    phone() { return (this.$refs.form.customer_phone.value || '').replace(/\D/g, '').slice(-10); },
    async submit() {
        if (!this.$refs.form.reportValidity()) return;
        if (this.verifiedPhone && this.verifiedPhone === this.phone()) { this.$refs.form.submit(); return; }
        this.otpErr = ''; this.busy = true;
        try {
            const res = await fetch(urls.otp, { method: 'POST', headers: this.headers(), body: JSON.stringify({ phone: this.phone() }) });
            const json = await res.json();
            if (!res.ok) { this.otpErr = Object.values(json.errors || {})[0]?.[0] || json.message; return; }
            this.otpSent = true; this.otpMsg = json.message;
            this.$nextTick(() => this.$refs.otpInput?.focus());
        } finally { this.busy = false; }
    },
    async verify() {
        this.otpErr = '';
        const res = await fetch(urls.verify, { method: 'POST', headers: this.headers(), body: JSON.stringify({ phone: this.phone(), otp: this.otp, name: this.$refs.form.customer_name.value }) });
        const json = await res.json();
        if (!res.ok) { this.otpErr = Object.values(json.errors || {})[0]?.[0] || json.message; return; }
        const fresh = await fetch(location.href, { headers: { Accept: 'text/html' } }).then((r) => r.text());
        const m = fresh.match(/name="csrf-token" content="([^"]+)"/);
        if (m) { this.$refs.form._token.value = m[1]; document.querySelector('meta[name="csrf-token"]').content = m[1]; }
        this.verifiedPhone = this.phone();
        this.$refs.form.submit();
    },
}));

// One-click Razorpay payment for an amount fixed by the server (booking advance).
Alpine.data('payNow', (url) => ({
    busy: false, error: '',
    async pay() {
        this.busy = true; this.error = '';
        try {
            if (!window.Razorpay) await new Promise((ok, fail) => { const s = document.createElement('script'); s.src = 'https://checkout.razorpay.com/v1/checkout.js'; s.onload = ok; s.onerror = () => fail(new Error('Could not load payment window.')); document.head.appendChild(s); });
            const res = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Payment could not be started.');
            const form = this.$refs.verify; form.action = data.verify_url;
            const rzp = new window.Razorpay({ ...data, handler: (r) => { form.razorpay_order_id.value = r.razorpay_order_id; form.razorpay_payment_id.value = r.razorpay_payment_id; form.razorpay_signature.value = r.razorpay_signature; form.submit(); }, modal: { ondismiss: () => { this.busy = false; } } });
            rzp.on('payment.failed', (r) => { this.error = r.error?.description || 'Payment failed.'; this.busy = false; });
            rzp.open();
        } catch (e) { this.error = e.message; this.busy = false; }
    },
}));

// Razorpay Checkout: create order on the server, open the modal, post the signed result back.
Alpine.data('razorpayCheckout', (orderUrl) => ({
    cycle: 'monthly',
    busy: null,
    error: '',
    loadScript() {
        if (window.Razorpay) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://checkout.razorpay.com/v1/checkout.js';
            s.onload = resolve;
            s.onerror = () => reject(new Error('Could not load payment window. Check your internet connection.'));
            document.head.appendChild(s);
        });
    },
    async pay(planId) {
        this.error = '';
        this.busy = planId;
        try {
            await this.loadScript();
            const res = await fetch(orderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ plan_id: planId, billing_cycle: this.cycle }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Payment could not be started.');
            const form = this.$refs.verify;
            form.action = data.verify_url;
            const rzp = new window.Razorpay({
                ...data,
                handler: (r) => {
                    form.razorpay_order_id.value = r.razorpay_order_id;
                    form.razorpay_payment_id.value = r.razorpay_payment_id;
                    form.razorpay_signature.value = r.razorpay_signature;
                    form.submit();
                },
                modal: { ondismiss: () => { this.busy = null; } },
            });
            rzp.on('payment.failed', (r) => { this.error = r.error?.description || 'Payment failed. Please try again.'; this.busy = null; });
            rzp.open();
        } catch (e) {
            this.error = e.message;
            this.busy = null;
        }
    },
}));


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

    // Branded confirm dialog for <form data-confirm="…"> (replaces the browser's native confirm()).
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
        if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        const submitter = e.submitter || form.querySelector('[type="submit"]');
        const danger = form.dataset.confirmTone === 'danger' || (form.dataset.confirmTone !== 'primary' && !!(submitter && /btn-(outline-)?danger/.test(submitter.className)));
        uiConfirm({
            message: form.dataset.confirm,
            title: form.dataset.confirmTitle,
            ok: form.dataset.confirmOk || (submitter ? submitter.textContent.trim() : ''),
            danger,
        }).then((yes) => {
            if (!yes) return;
            form.dataset.confirmed = '1';
            if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined); else form.submit();
        });
    }, { capture: true });

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

    // Panel tables stack into cards on phones: copy each header into its cells' data-label.
    document.querySelectorAll('.panel-body table.table-modern:not(.table-keep)').forEach((table) => {
        const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        if (!heads.length) return;
        table.classList.add('table-stack');
        table.querySelectorAll('tbody tr, tfoot tr').forEach((tr) => {
            let col = 0;
            [...tr.children].forEach((cell) => {
                const span = Number(cell.getAttribute('colspan') || 1);
                if (span === 1 && heads[col] && !cell.hasAttribute('data-label')) cell.setAttribute('data-label', heads[col]);
                col += span;
            });
        });
    });

    // Mobile sticky CTA hides while the quote form (or footer) is on screen, so it never covers inputs.
    const cta = document.querySelector('.mobile-cta');
    const targets = [document.getElementById('quote'), document.querySelector('footer')].filter(Boolean);
    if (cta && targets.length && 'IntersectionObserver' in window) {
        const seen = new Set();
        const io2 = new IntersectionObserver((entries) => {
            entries.forEach((en) => (en.isIntersecting ? seen.add(en.target) : seen.delete(en.target)));
            cta.classList.toggle('is-hidden', seen.size > 0);
        });
        targets.forEach((t) => io2.observe(t));
    }
});

// ---------- Instant navigation fallback (browsers without Speculation Rules, e.g. Firefox) ----------
(() => {
    if (window.HTMLScriptElement?.supports?.('speculationrules')) return;
    const skip = /^\/(logout|locale\/|driver\/|b\/|webhooks\/)|\/(pdf|file)$|\/export|\/download|invoice/;
    const done = new Set();
    let timer;
    const prefetch = (a) => {
        if (!a || a.target === '_blank' || a.hasAttribute('download') || a.closest('[data-no-prefetch]')) return;
        const url = new URL(a.href, location.href);
        if (url.origin !== location.origin || url.hash && url.pathname === location.pathname || skip.test(url.pathname) || done.has(url.href)) return;
        done.add(url.href);
        const l = document.createElement('link');
        l.rel = 'prefetch'; l.href = url.href;
        document.head.appendChild(l);
    };
    document.addEventListener('mouseover', (e) => { const a = e.target.closest?.('a[href]'); clearTimeout(timer); if (a) timer = setTimeout(() => prefetch(a), 80); }, { passive: true });
    document.addEventListener('touchstart', (e) => prefetch(e.target.closest?.('a[href]')), { passive: true });
})();

// ---------- Confirm dialog ----------
function uiConfirm({ message, title, ok, danger }) {
    const t = window.__ui || {};
    return new Promise((resolve) => {
        const wrap = document.createElement('div');
        wrap.className = 'ui-confirm';
        wrap.innerHTML = `<div class="ui-confirm-backdrop"></div>
            <div class="ui-confirm-card ${danger ? 'is-danger' : ''}" role="alertdialog" aria-modal="true" aria-labelledby="uic-title" aria-describedby="uic-msg">
                <div class="ui-confirm-ico" aria-hidden="true">${danger
                    ? '<svg viewBox="0 0 16 16" fill="currentColor"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>'
                    : '<svg viewBox="0 0 16 16" fill="currentColor"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>'}</div>
                <h2 class="ui-confirm-title" id="uic-title"></h2>
                <p class="ui-confirm-msg" id="uic-msg"></p>
                <div class="ui-confirm-actions">
                    <button type="button" class="btn btn-light border" data-act="no"></button>
                    <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-act="yes"></button>
                </div>
            </div>`;
        wrap.querySelector('.ui-confirm-title').textContent = title || (danger ? (t.confirmDangerTitle || 'Are you sure?') : (t.confirmTitle || 'Please confirm'));
        wrap.querySelector('.ui-confirm-msg').textContent = message;
        wrap.querySelector('[data-act="no"]').textContent = t.cancel || 'Cancel';
        wrap.querySelector('[data-act="yes"]').textContent = (ok && ok.length <= 32 ? ok : '') || t.confirm || 'Yes, continue';
        const prev = document.activeElement;
        const close = (val) => {
            wrap.classList.remove('show');
            document.removeEventListener('keydown', onKey, true);
            setTimeout(() => wrap.remove(), 180);
            if (prev && prev.focus) prev.focus();
            resolve(val);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); close(false); }
            if (e.key === 'Tab') { // keep focus inside
                const btns = [...wrap.querySelectorAll('button')];
                const i = btns.indexOf(document.activeElement);
                e.preventDefault();
                btns[(i + (e.shiftKey ? -1 : 1) + btns.length) % btns.length].focus();
            }
        };
        wrap.addEventListener('click', (e) => {
            const act = e.target.closest('[data-act]')?.dataset.act;
            if (act) close(act === 'yes');
            else if (e.target.classList.contains('ui-confirm-backdrop')) close(false);
        });
        document.addEventListener('keydown', onKey, true);
        document.body.appendChild(wrap);
        requestAnimationFrame(() => { wrap.classList.add('show'); wrap.querySelector(danger ? '[data-act="no"]' : '[data-act="yes"]').focus(); });
    });
}
window.uiConfirm = uiConfirm;

// ---------- Driver PWA ----------
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const postJson = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body), credentials: 'same-origin' });

Alpine.data('driverApp', (cfg) => ({
    offline: !navigator.onLine, installable: false, fresh: 0, prompt: null, timer: null,
    get freshText() {
        const t = window.__driverI18n || {};
        return this.fresh > 1 ? (t.many || ':n new trips').replace(':n', this.fresh) : (t.new || 'New trip assigned!');
    },
    init() {
        window.addEventListener('online', () => { this.offline = false; this.poll(); });
        window.addEventListener('offline', () => { this.offline = true; });
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.prompt = e; this.installable = true; });
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(cfg.sw, { scope: '/driver/' }).then((reg) => this.setupPush(reg)).catch(() => {});
        }
        this.timer = setInterval(() => { if (!document.hidden) this.poll(); }, 30000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) this.poll(); });
    },
    async poll() {
        if (this.offline) return;
        try {
            const r = await fetch(cfg.poll, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (r.status === 401 || r.status === 419) { location.reload(); return; }
            if (!r.ok) return;
            const d = await r.json();
            if (d.new > this.fresh && d.new > 0 && navigator.vibrate) navigator.vibrate([200, 100, 200]);
            this.fresh = d.new;
        } catch { /* network blip */ }
    },
    async install() {
        if (!this.prompt) return;
        this.prompt.prompt();
        await this.prompt.userChoice;
        this.prompt = null; this.installable = false;
    },
    async setupPush(reg) {
        if (!cfg.push || !('Notification' in window) || !('PushManager' in window)) return;
        if (Notification.permission === 'default') {
            // Ask after the first user interaction (browsers block prompts without a gesture).
            document.addEventListener('click', () => Notification.requestPermission().then((p) => p === 'granted' && this.setupPush(reg)), { once: true });
            return;
        }
        if (Notification.permission !== 'granted') return;
        try {
            const v = '10.12.2';
            const { initializeApp } = await import(/* @vite-ignore */ `https://www.gstatic.com/firebasejs/${v}/firebase-app.js`);
            const { getMessaging, getToken } = await import(/* @vite-ignore */ `https://www.gstatic.com/firebasejs/${v}/firebase-messaging.js`);
            const token = await getToken(getMessaging(initializeApp(cfg.push.config)), { vapidKey: cfg.push.vapidKey, serviceWorkerRegistration: reg });
            if (token) {
                let saved = null;
                try { saved = localStorage.getItem('fcm_token'); } catch { /* storage unavailable */ }
                if (saved !== token && (await postJson(cfg.token, { token })).ok) {
                    try { localStorage.setItem('fcm_token', token); } catch { /* storage unavailable */ }
                }
            }
        } catch { /* push optional — polling still works */ }
    },
}));

// Shares only the latest position while a trip is in progress (every ~60 s).
Alpine.data('tripTracker', (url) => ({
    status: '', last: 0, watch: null,
    start() {
        if (!('geolocation' in navigator)) return;
        this.watch = navigator.geolocation.watchPosition((pos) => {
            if (Date.now() - this.last < 60000) return;
            this.last = Date.now();
            postJson(url, { lat: pos.coords.latitude, lng: pos.coords.longitude }).catch(() => {});
        }, () => {}, { enableHighAccuracy: true, maximumAge: 30000, timeout: 20000 });
    },
    destroy() { if (this.watch !== null) navigator.geolocation.clearWatch(this.watch); },
}));

Alpine.start();
