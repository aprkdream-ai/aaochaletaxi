@props(['url'])
<tr>
<td class="header">
<table class="header-table" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header-cell">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim(config('app.url'), '/') }}/icon-192.png" class="logo" width="36" height="36" alt="">
<span class="brand-name">AaoChale<span class="brand-accent">Taxi</span></span>
</a>
</td>
<td class="header-tagline">{{ __('Taxi & fleet management') }}</td>
</tr>
</table>
</td>
</tr>
<tr><td class="header-bar">&nbsp;</td></tr>
</table>
</td>
</tr>
