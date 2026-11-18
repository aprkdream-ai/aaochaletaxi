<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\VehicleCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VehicleCategoryController extends Controller
{
    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'seats' => ['required', 'integer', 'min:1', 'max:60']]);
        $slug = Str::slug($data['name']);
        $exists = VehicleCategory::query()->availableTo($context->id())->where('slug', $slug)->exists();
        if ($exists) {
            return back()->withErrors(['category' => __('This category already exists.')]);
        }
        $c = new VehicleCategory(['name' => $data['name'], 'slug' => $slug, 'seats' => $data['seats'], 'icon' => 'car-front', 'sort_order' => 500]);
        $c->tenant_id = $context->id();
        $c->save();

        return back()->with('success', __('Category :name added.', ['name' => $c->name]));
    }
}
