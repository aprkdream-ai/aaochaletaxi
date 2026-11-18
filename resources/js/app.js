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
    inr(v) { return '₹' + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); },
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
    type: 'outstation_oneway',
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
