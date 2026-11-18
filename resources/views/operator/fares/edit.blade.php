<x-layouts.panel :title="__('Edit fare rule')" panel="operator">
    <x-page-header :title="$rule->category->name.' · '.$rule->booking_type->label()"><a href="{{ route('operator.fares.index', ['type' => $rule->booking_type->value]) }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a></x-page-header>
    <form method="POST" action="{{ route('operator.fares.update', $rule) }}" class="card panel-card" style="max-width: 900px" data-loading novalidate>
        @csrf @method('PUT')
        <div class="card-body"><div class="row g-2">
            @if ($rule->booking_type->isRental())
                <div class="col-md-4"><x-input name="base_fare" type="number" step="1" min="0" :label="__('Package price ₹')" :value="(float) $rule->base_fare" /></div>
                <div class="col-md-4"><x-input name="included_hours" type="number" min="0" :label="__('Included hours')" :value="$rule->included_hours" /></div>
                <div class="col-md-4"><x-input name="included_km" type="number" min="0" :label="__('Included km')" :value="$rule->included_km" /></div>
                <div class="col-md-4"><x-input name="extra_km_rate" type="number" step="0.5" min="0" :label="__('Extra km ₹')" :value="(float) $rule->extra_km_rate" /></div>
                <div class="col-md-4"><x-input name="extra_hour_rate" type="number" step="1" min="0" :label="__('Extra hour ₹')" :value="(float) $rule->extra_hour_rate" /></div>
            @else
                <div class="col-md-4"><x-input name="base_fare" type="number" step="1" min="0" :label="__('Base fare ₹')" :value="(float) $rule->base_fare" /></div>
                <div class="col-md-4"><x-input name="per_km" type="number" step="0.5" min="0" :label="__('Per km ₹')" :value="(float) $rule->per_km" /></div>
                <div class="col-md-4"><x-input name="min_km" type="number" min="0" :label="$rule->booking_type === \App\Enums\BookingType::OutstationRound ? __('Minimum km per day') : __('Minimum billable km')" :value="$rule->min_km" /></div>
            @endif
            <div class="col-md-4"><x-input name="driver_allowance" type="number" step="1" min="0" :label="__('Driver allowance / day ₹')" :value="(float) $rule->driver_allowance" /></div>
            <div class="col-md-4"><x-input name="night_charge" type="number" step="1" min="0" :label="__('Night charge ₹')" :value="(float) $rule->night_charge" /></div>
            <div class="col-6 col-md-2"><x-input name="night_start" type="time" :label="__('Night from')" :value="substr($rule->night_start, 0, 5)" required /></div>
            <div class="col-6 col-md-2"><x-input name="night_end" type="time" :label="__('Night to')" :value="substr($rule->night_end, 0, 5)" required /></div>
            <div class="col-md-4"><x-input name="waiting_free_min" type="number" min="0" :label="__('Free waiting (min)')" :value="$rule->waiting_free_min" /></div>
            <div class="col-md-4"><x-input name="waiting_per_min" type="number" step="0.5" min="0" :label="__('Waiting ₹ / min')" :value="(float) $rule->waiting_per_min" /></div>
            <div class="col-md-4"><x-select name="gst_percent" :label="__('GST')" :options="['0' => '0%', '5' => '5% (no ITC)', '12' => '12% (with ITC)', '18' => '18%']" :value="(string) (int) $rule->gst_percent" /></div>
            <div class="col-12"><x-switch name="is_active" :label="__('Active')" :checked="$rule->is_active" /></div>
        </div></div>
        <div class="card-body border-top text-end"><button class="btn btn-primary">{{ __('Save rule') }}</button></div>
    </form>
</x-layouts.panel>
