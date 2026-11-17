<x-layouts.panel :title="__('Dashboard')" panel="operator">
    <x-page-header :title="__('Welcome, :name', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')])" :subtitle="$tenant->name.' · '.now()->format('l, d M Y')" />

    @if ($subscription && $subscription->status->value === 'trialing')
        <div class="alert border-0 d-flex flex-wrap align-items-center gap-3 rounded-4 mb-4" style="background: var(--brand-soft)">
            <span class="icon-tile tile-ink tile-sm"><x-icon name="gift" /></span>
            <div class="flex-grow-1"><div class="fw-bold">{{ __('Free trial: :days days left', ['days' => $subscription->daysLeft()]) }}</div><div class="small">{{ __('You are on the :plan plan trial until :date.', ['plan' => $subscription->plan->name, 'date' => $subscription->ends_at->format('d M Y')]) }}</div></div>
            <a href="{{ route('partner') }}#pricing" class="btn btn-ink btn-sm">{{ __('View plans') }}</a>
        </div>
    @elseif (! $subscription)
        <div class="alert alert-danger d-flex gap-2 rounded-4"><x-icon name="exclamation-triangle-fill" class="mt-1" />{{ __('Your subscription has expired. Please contact support to renew — your data is safe.') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Current plan')" :value="$subscription?->plan->name ?? '—'" icon="award" color="primary" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Valid till')" :value="$subscription?->ends_at->format('d M Y') ?? '—'" icon="calendar-check" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Team members')" :value="$staffCount" icon="people" color="success" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Vehicle limit')" :value="$subscription?->plan->max_vehicles ?? '—'" icon="truck-front" color="secondary" /></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card panel-card h-100">
                <div class="card-header">{{ __('Get started') }}</div>
                <div class="list-group list-group-flush list-modern">
                    @foreach ([
                        ['check-circle-fill', 'text-success', __('Business account created'), __('Your account is verified and active.')],
                        ['circle', 'text-muted-2', __('Add your vehicles & drivers'), __('Fleet management is coming in the next update.')],
                        ['circle', 'text-muted-2', __('Set up your fare rules'), __('Configure per-km rates, allowances and GST.')],
                        ['circle', 'text-muted-2', __('Share your booking page'), __('Let customers book directly with you.')],
                    ] as [$ico, $tone, $t, $d])
                        <div class="list-group-item d-flex gap-3"><x-icon :name="$ico" class="{{ $tone }} mt-1" /><div><div class="fw-semibold">{{ $t }}</div><div class="small text-muted-2">{{ $d }}</div></div></div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card panel-card h-100">
                <div class="card-header">{{ __('Your booking page') }}</div>
                <div class="card-body" x-data="{ copied: false }">
                    <p class="small text-muted-2">{{ __('Customers will be able to book directly with you at:') }}</p>
                    <div class="input-group">
                        <input type="text" class="form-control" readonly value="{{ url('/o/'.$tenant->slug) }}" x-ref="link" aria-label="{{ __('Booking page link') }}">
                        <button class="btn btn-ink" type="button" @click="navigator.clipboard?.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="!copied"><x-icon name="clipboard" /></span><span x-show="copied" x-cloak><x-icon name="check2" /></span>
                        </button>
                    </div>
                    <div class="small text-muted-2 mt-2">{{ __('Branded booking pages go live in an upcoming release.') }}</div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.panel>
