@props(['name', 'label' => null])
{!! \App\Support\Icon::svg($name, $attributes->get('class', ''), $label) !!}
