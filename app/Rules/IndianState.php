<?php

namespace App\Rules;

use App\Support\India;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Accepts an Indian state / UT name (case-insensitive, common short forms allowed). Normalise with India::normalizeState(). */
class IndianState implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || India::normalizeState($value) === null) {
            $fail(__('Select a state from the list.'));
        }
    }
}
