<x-layouts.public :mobile-cta="false" :title="__('Terms & conditions')" :description="__('Terms governing bookings, operator subscriptions and use of the AaoChaleTaxi platform.')">
    <x-page-hero :title="__('Terms & conditions')" :lead="__('Last updated').': 17 November 2026'" :crumbs="[__('Terms & conditions') => null]" />
    <section class="section">
        <div class="container"><div class="prose">
            
            <p>These terms govern use of aaochaletaxi.com and related apps operated by {{ config('aaochale.legal_name') }}. By using the platform you agree to them.</p>
            <h2>1. The platform</h2>
            <p>AaoChaleTaxi provides (a) a booking platform connecting customers with independent taxi operators and (b) subscription software that operators use to manage their business. Rides are fulfilled by the operator named on your booking, who is responsible for the vehicle, driver, permits and insurance.</p>
            <h2>2. Accounts</h2>
            <p>You must provide accurate information and keep your login secure. You are responsible for activity under your account. We may suspend accounts for misuse, fraud or non-payment.</p>
            <h2>3. Bookings & fares</h2>
            <p>Fares are shown with their components (base fare, distance, driver allowance, night charges, tolls, parking, state tax and GST as applicable). Tolls, parking and state entry taxes may be charged at actuals where stated. Extra kilometres or hours are billed as per the fare quoted at booking.</p>
            <h2>4. Operator subscriptions</h2>
            <p>Plans are billed monthly or annually in advance. Plan limits (vehicles, drivers, branches) apply. On expiry a grace period applies, after which access is restricted; business data is retained and restored upon renewal.</p>
            <h2>5. Prohibited use</h2>
            <p>No unlawful use, scraping, reverse engineering, attempts to access other operators' data, or uploading malicious content.</p>
            <h2>6. Liability</h2>
            <p>To the extent permitted by law, AaoChaleTaxi is not liable for acts or omissions of operators or drivers, delays caused by traffic, weather or events beyond reasonable control, or indirect losses.</p>
            <h2>7. Governing law</h2>
            <p>These terms are governed by the laws of India. Courts at the company's registered office location have jurisdiction.</p>
            <h2>8. Contact</h2>
            <p><a href="mailto:{{ config('aaochale.support_email') }}">{{ config('aaochale.support_email') }}</a></p>
        </div></div>
    </section>
</x-layouts.public>
