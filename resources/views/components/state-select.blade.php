{{-- Searchable Indian state / UT field. Value must be one of the list (snapped on blur, validated server-side). --}}
@props(['name' => 'state', 'icon' => 'map', 'label' => null, 'value' => null, 'required' => false, 'help' => null])
<x-input :name="$name" :icon="$icon" :label="$label" :value="$value" :required="$required" :help="$help" :attributes="$attributes->merge(['data-suggest' => 'states', 'data-options' => json_encode(\App\Support\India::states()), 'autocomplete' => 'off', 'placeholder' => __('Search state'), 'maxlength' => 100])" />
