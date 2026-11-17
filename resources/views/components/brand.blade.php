@props(['href' => null, 'dark' => false])
<a href="{{ $href ?? route('home') }}" {{ $attributes->class(['brand', 'brand-dark' => $dark]) }} aria-label="AaoChaleTaxi home">
    <span class="brand-mark"><x-icon name="taxi-front-fill" /></span>
    <span class="text-reset">AaoChale<span>Taxi</span></span>
</a>
