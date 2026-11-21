{{-- Pickup / drop field with live suggestions (operator history → Google Places → Indian cities). Free text always allowed. --}}
@props(['name', 'icon' => 'geo-alt-fill', 'label' => null, 'value' => null, 'required' => false, 'help' => null])
<x-input :name="$name" :icon="$icon" :label="$label" :value="$value" :required="$required" :help="$help" :attributes="$attributes->merge(['data-suggest' => 'places', 'data-suggest-url' => route('suggest.places'), 'autocomplete' => 'off', 'spellcheck' => 'false'])" />
