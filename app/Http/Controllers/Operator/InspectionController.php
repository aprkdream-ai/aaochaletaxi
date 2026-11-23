<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\VehicleInspection;
use App\Services\VehicleInspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionController extends Controller
{
    public function resolve(Request $request, VehicleInspection $inspection, VehicleInspectionService $service): RedirectResponse
    {
        $service->resolve($inspection, $request->user());

        return back()->with('success', __('Marked as resolved.'));
    }

    public function photo(VehicleInspection $inspection): StreamedResponse
    {
        abort_unless($inspection->photo_path && Storage::disk(VehicleInspectionService::DISK)->exists($inspection->photo_path), 404);

        return Storage::disk(VehicleInspectionService::DISK)->response($inspection->photo_path, null, ['Cache-Control' => 'private, max-age=600']);
    }
}
