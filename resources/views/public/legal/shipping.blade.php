<x-layouts.public :mobile-cta="false" :title="__('Shipping & delivery policy')" :description="__('How AaoChaleTaxi delivers taxi rides and operator subscriptions — no physical goods are shipped.')">
    <x-page-hero :title="__('Shipping & delivery policy')" :lead="__('Last updated').': 18 November 2026'" :crumbs="[__('Shipping & delivery policy') => null]" />
    <section class="section">
        <div class="container"><div class="prose">
            <p>AaoChaleTaxi provides services only. <strong>We do not sell or ship any physical goods</strong>, so no courier, shipping or delivery charges apply.</p>

            <h2>1. Taxi rides (customers)</h2>
            <ul>
                <li>The service is delivered as a taxi ride by the operator you book with, at the pickup date, time and address confirmed in your booking.</li>
                <li>Booking confirmation, the assigned vehicle and driver details, and the trip OTP are shared by SMS / WhatsApp / e-mail and on your booking link. The vehicle and driver are normally assigned before pickup time.</li>
                <li>The trip starts only after you share the OTP with the driver, and is completed at your drop location. The final fare and GST invoice are available on your booking link after the trip.</li>
                <li>If the operator cannot provide the vehicle, the booking is cancelled and any advance paid is refunded as per our <a href="{{ route('legal.refund') }}">Cancellation &amp; refund policy</a>.</li>
            </ul>

            <h2>2. Operator subscriptions (software)</h2>
            <ul>
                <li>The AaoChaleTaxi dashboard is delivered online at <a href="{{ url('/') }}">{{ parse_url(url('/'), PHP_URL_HOST) }}</a>. Nothing is shipped.</li>
                <li>Paid plans are activated instantly after successful online payment (or within 1 working day for payments recorded manually). If you are on an active paid period, the new period starts when the current one ends.</li>
                <li>The payment receipt and GST invoice are available in <em>Subscription → Invoices</em> and are e-mailed to the account owner.</li>
                <li>If a successful payment does not activate your plan within 24 hours, contact us with the payment ID and we will activate it or refund it.</li>
            </ul>

            <h2>3. Service area</h2>
            <p>Services are available within India only, in the cities and routes covered by the operators on the platform.</p>

            <h2>4. Contact</h2>
            @include('public.partials.business')
        </div></div>
    </section>
</x-layouts.public>
