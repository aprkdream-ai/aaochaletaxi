<x-layouts.public :mobile-cta="false" :title="__('Cancellation & refund policy')" :description="__('Taxi booking cancellation charges, refund timelines and subscription refund rules.')">
    <x-page-hero :title="__('Cancellation & refund policy')" :lead="__('Last updated').': 17 November 2026'" :crumbs="[__('Cancellation & refund policy') => null]" />
    <section class="section">
        <div class="container"><div class="prose">
            
            <h2>1. Customer cancellations</h2>
            <ul>
                <li>More than 24 hours before pickup: full refund of advance paid.</li>
                <li>Between 24 and 6 hours before pickup: up to 25% of the advance may be retained.</li>
                <li>Less than 6 hours before pickup or no-show: the advance may be retained in full.</li>
            </ul>
            <p>Operators may publish their own cancellation terms on their booking page; where shown at booking, those terms apply.</p>
            <h2>2. Operator cancellations</h2>
            <p>If an operator cancels or fails to provide the vehicle, the full advance is refunded.</p>
            <h2>3. Refund timelines</h2>
            <p>Approved refunds are initiated within 3 working days to the original payment method. Banks may take 5–7 working days to credit.</p>
            <h2>4. Subscription fees</h2>
            <p>Operator subscription fees are non-refundable once a billing period has started. You may cancel auto-renewal anytime; access continues until the end of the paid period.</p>
            <h2>5. Contact</h2>
            <p>For cancellations and refunds, write to us with your booking number or payment ID.</p>
            @include('public.partials.business')
        </div></div>
    </section>
</x-layouts.public>
