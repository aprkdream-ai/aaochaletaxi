{{-- City field with suggestions filtered by the linked state field (any city may still be typed). --}}
@props(['name' => 'city', 'icon' => 'geo-alt', 'stateField' => null, 'label' => null, 'value' => null, 'required' => false, 'help' => null])
<x-input :name="$name" :icon="$icon" :label="$label" :value="$value" :required="$required" :help="$help" :attributes="$attributes->merge(['data-suggest' => 'cities', 'data-suggest-url' => route('suggest.cities'), 'data-suggest-state' => $stateField ? '#f_'.$stateField : null, 'autocomplete' => 'off', 'maxlength' => 100])" />
