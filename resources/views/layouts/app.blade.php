<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $sbCurrency = \App\Support\Currency::fromSession(request()->session());
        $sbCurrencySymbol = \App\Support\Currency::symbol($sbCurrency);

        $sbUser = auth()->user();
        $sbAdsEnabled = config('ads.enabled', false)
            && config('ads.banner.enabled', false)
            && !($sbUser?->master_purchased ?? false);
        $sbCurrentRoute = request()->route()?->getName() ?? '';
        $sbBannerActive = $sbAdsEnabled && in_array($sbCurrentRoute, config('ads.allowed_banner_routes', []));
    @endphp
    <script>
      window.SB_CURRENCY = @json($sbCurrency);
      window.SB_CURRENCY_SYMBOL = @json($sbCurrencySymbol);
    </script>

    <title>StrategyBuzzer</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.png') }}">
    @stack('head')
</head>
<body>

    <main class="container" @if($sbBannerActive) style="padding-bottom:68px;" @endif>
        @yield('content')
    </main>

    @include('components.ad-banner')

    <!-- Musique gérée par game.blade.php -->

    
<!-- Toast Notification System -->
<div id="toastContainer" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 99999; pointer-events: none;"></div>

<style>
.custom-toast {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
    padding: 16px 28px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 20px rgba(255, 215, 0, 0.2);
    border: 1px solid rgba(255, 215, 0, 0.3);
    font-size: 16px;
    font-weight: 500;
    text-align: center;
    animation: toastSlideIn 0.4s ease-out, toastFadeOut 0.4s ease-in 2.6s forwards;
    pointer-events: auto;
    max-width: 90vw;
}
.custom-toast.success {
    border-color: rgba(76, 217, 100, 0.5);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 20px rgba(76, 217, 100, 0.3);
}
.custom-toast.error {
    border-color: rgba(255, 59, 48, 0.5);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 20px rgba(255, 59, 48, 0.3);
}
.custom-toast.warning {
    border-color: rgba(255, 204, 0, 0.5);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 20px rgba(255, 204, 0, 0.3);
}
@keyframes toastSlideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes toastFadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}
</style>

<script>
window.showToast = function(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = 'custom-toast ' + type;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, duration);
};
</script>

@include('components.custom-dialog')
</body>
</html>
