<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | AaoChaleTaxi</title>
    <style>
        :root{--bg:#14161c;--fg:#f5f5f5;--brand:#f5b400}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;text-align:center;padding:16px}
        h1{font-size:4rem;margin:0;color:var(--brand)} p{opacity:.8;max-width:420px;margin:12px auto 24px}
        a{display:inline-block;background:var(--brand);color:#14161c;padding:10px 22px;border-radius:8px;text-decoration:none;font-weight:600}
    </style>
</head>
<body><main><h1>@yield('code')</h1><p>@yield('message')</p><a href="{{ url('/') }}">Go to home</a></main></body>
</html>
