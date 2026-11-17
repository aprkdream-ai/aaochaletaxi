<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | AaoChaleTaxi</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;background:radial-gradient(700px 400px at 85% 0%,rgba(255,183,3,.25),transparent 60%),#0b1220;color:#e7ecf5;font-family:"Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;text-align:center}
        .code{font-size:clamp(5rem,18vw,9rem);font-weight:800;line-height:1;letter-spacing:-.05em;background:linear-gradient(92deg,#ffd166,#ffb703 45%,#ff8a00);-webkit-background-clip:text;background-clip:text;color:transparent}
        h1{font-size:1.5rem;margin:.5rem 0}
        p{color:#9aa6bd;max-width:440px;margin:0 auto 1.75rem;line-height:1.6}
        .actions{display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap}
        a{display:inline-block;padding:.75rem 1.4rem;border-radius:.75rem;text-decoration:none;font-weight:700}
        .primary{background:#ffb703;color:#0b1220}.ghost{border:1px solid rgba(255,255,255,.2);color:#fff}
        .brand{position:fixed;top:20px;left:0;right:0;font-weight:800;color:#fff;letter-spacing:-.02em}.brand span{color:#ffb703}
    </style>
</head>
<body>
<div class="brand">AaoChale<span>Taxi</span></div>
<main>
    <div class="code">@yield('code')</div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="actions"><a class="primary" href="{{ url('/') }}">Go to home</a><a class="ghost" href="{{ url('/contact') }}">Contact support</a></div>
</main>
</body>
</html>
