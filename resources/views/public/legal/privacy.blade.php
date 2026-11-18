<x-layouts.public :mobile-cta="false" :title="__('Privacy policy')" :description="__('How AaoChaleTaxi collects, uses and protects personal data under the DPDP Act, 2023.')">
    <x-page-hero :title="__('Privacy policy')" :lead="__('Last updated').': 17 November 2026'" :crumbs="[__('Privacy policy') => null]" />
    <section class="section">
        <div class="container"><div class="prose">
            
            <p>{{ config('aaochale.legal_name') }} ("AaoChaleTaxi", "we") operates aaochaletaxi.com. This policy explains how we collect, use and protect personal data in line with the Digital Personal Data Protection Act, 2023 and the Information Technology Act, 2000.</p>
            <h2>1. Data we collect</h2>
            <ul>
                <li><strong>Customers:</strong> name, mobile number, email, pickup/drop locations, travel dates, booking and payment status.</li>
                <li><strong>Operators:</strong> business details, GSTIN/PAN, KYC documents, staff, vehicle and driver records entered by the operator.</li>
                <li><strong>Drivers:</strong> name, mobile number, licence details and trip records maintained by their operator.</li>
                <li><strong>Technical:</strong> IP address, device/browser information and log data for security.</li>
            </ul>
            <p>We do not store card numbers, CVV or UPI PINs. Online payments are processed by RBI-authorised payment gateways.</p>
            <h2>2. How we use data</h2>
            <p>To provide bookings and fleet-management services, verify operators, process payments and invoices, send service notifications, prevent fraud, and comply with law.</p>
            <h2>3. Sharing</h2>
            <p>Booking details are shared with the taxi operator and driver fulfilling your trip. We share data with service providers (hosting, payments, SMS/WhatsApp/email) only as needed to deliver the service, and with authorities when legally required. We do not sell personal data.</p>
            <h2>4. Operator data</h2>
            <p>Each operator's business data is logically isolated and accessible only to that operator's authorised users and our authorised platform staff.</p>
            <h2>5. Retention</h2>
            <p>We keep data while your account is active and as required for tax, accounting and legal purposes. Expired subscriptions do not cause automatic deletion of business records.</p>
            <h2>6. Your rights</h2>
            <p>You may request access, correction or erasure of your personal data, or withdraw consent, by writing to us. Some data may be retained where required by law.</p>
            <h2>7. Security</h2>
            <p>We use HTTPS, hashed passwords, role-based access control and audit logs. No system is perfectly secure; please keep your password confidential.</p>
            <h2>8. Grievance officer</h2>
            @php $grievanceName = \App\Models\PlatformSetting::getValue('platform_legal_name'); @endphp
            <p>@if ($grievanceName){{ $grievanceName }} · @endif Email: <a href="mailto:{{ config('aaochale.grievance_email') }}">{{ config('aaochale.grievance_email') }}</a>. We acknowledge grievances within 72 hours.</p>
            <h2>9. Business details</h2>
            @include('public.partials.business')
        </div></div>
    </section>
</x-layouts.public>
