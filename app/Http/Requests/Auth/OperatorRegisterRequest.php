<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class OperatorRegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')),
            'state' => \App\Support\India::normalizeState($this->input('state')) ?? $this->input('state'),
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'min:3', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100', new \App\Rules\IndianState],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.'];
    }
}
