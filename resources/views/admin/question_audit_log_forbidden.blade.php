<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Forbidden') }}</title>
    <style>
        body { background:#0f1115; color:#e6e8ec; font:14px -apple-system,sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
        .box { text-align:center; padding:32px; border:1px solid #2a2f3a; border-radius:6px; background:#1a1d24; max-width:480px; }
        h1 { margin:0 0 12px; font-size:18px; }
        p { color:#9aa0aa; margin:0; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{{ __('Forbidden') }}</h1>
        <p>{{ __('Provide a valid admin token via Authorization header or ?token= query.') }}</p>
    </div>
</body>
</html>
