<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceLeadOffer;
use App\Services\Marketplace\MarketplaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Marketplace quote requests offered to this operator (first to accept wins). */
class LeadController extends Controller
{
    public function __construct(private MarketplaceService $marketplace) {}

    public function index(): View
    {
        $offers = MarketplaceLeadOffer::query()->with(['lead.category:id,name', 'lead.booking:id,booking_number,tenant_id'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")->latest('id')->paginate(25);

        return view('operator.leads.index', ['offers' => $offers]);
    }

    public function accept(Request $request, MarketplaceLeadOffer $offer): RedirectResponse
    {
        $booking = $this->marketplace->accept($offer, $request->user());

        return redirect()->route('operator.bookings.show', $booking)->with('success', __('Request accepted — booking :no created. Call the customer to confirm.', ['no' => $booking->booking_number]));
    }

    public function decline(MarketplaceLeadOffer $offer): RedirectResponse
    {
        $this->marketplace->decline($offer);

        return back()->with('success', __('Request declined.'));
    }
}
