<?php

namespace App\Http\Requests\Operator;

use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FleetTripRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['requested_phone' => $this->filled('requested_phone') ? Phone::normalize($this->input('requested_phone')) : null]);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $own = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $tenantId);

        return [
            'vehicle_id' => ['required', 'integer', $own('vehicles')->whereNull('deleted_at')],
            'driver_id' => ['required', 'integer', $own('drivers')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', $own('departments')->where('is_active', true)],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'purpose' => ['required', 'string', 'max:200'],
            'requested_by' => ['nullable', 'string', 'max:150'],
            'requested_phone' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'pickup_point' => ['nullable', 'string', 'max:255'],
            'drop_point' => ['nullable', 'string', 'max:255'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['requested_phone.regex' => __('Enter a valid 10-digit Indian mobile number.'), 'end_at.after' => __('Return time must be after the start time.')];
    }
}
