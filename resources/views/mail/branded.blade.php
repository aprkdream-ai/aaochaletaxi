<x-mail::message>
# {{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($code)
<div class="code-box">{{ $code }}</div>

@endif
@if ($details)
<x-mail::panel>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($details as $label => $value)
<tr><td style="color:#64748b;font-size:13px;padding:4px 12px 4px 0;white-space:nowrap;vertical-align:top;">{{ $label }}</td><td style="color:#0b1220;font-size:14px;font-weight:bold;padding:4px 0;">{{ $value }}</td></tr>
@endforeach
</table>
</x-mail::panel>

@endif
@if ($actionText && $actionUrl)
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

@endif
@if ($note)
<p class="muted">{{ $note }}</p>

@endif
{{ __('Regards,') }}<br>
**{{ $signOff ?: __('Team AaoChaleTaxi') }}**

@if ($actionText && $actionUrl)
<x-slot:subcopy>
{{ __('If the button does not work, copy this link into your browser:') }} <span class="break-all">[{{ $actionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endif
</x-mail::message>
