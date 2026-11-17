@props(['items'])
{{-- $items: [question => answer]. Native <details> (no JS) + FAQPage schema. --}}
<div class="faq">
    @foreach ($items as $q => $a)
        <details @if ($loop->first) open @endif>
            <summary>{{ $q }}<span class="plus"><x-icon name="plus-lg" /></span></summary>
            <div class="answer">{{ $a }}</div>
        </details>
    @endforeach
</div>
<x-json-ld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => collect($items)->map(fn ($a, $q) => ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]])->values()->all(),
]" />
