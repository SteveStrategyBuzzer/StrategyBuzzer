@php
    $user = auth()->user();
    $adsEnabled = config('ads.enabled', false)
        && config('ads.banner.enabled', false)
        && !($user?->master_purchased ?? false);

    $currentRoute = request()->route()?->getName() ?? '';
    $allowedRoutes = config('ads.allowed_banner_routes', []);
    $routeAllowed = in_array($currentRoute, $allowedRoutes);
@endphp

@if($adsEnabled && $routeAllowed)
<div id="sb-ad-banner" style="
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 999;
    background: rgba(10,10,20,0.97);
    border-top: 1px solid rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60px;
    padding: 8px 16px;
    text-align: center;
">
    {{-- Zone pub bannière : remplacer le contenu ci-dessous par le script réel du provider --}}
    <div style="color:rgba(255,255,255,0.3);font-size:11px;letter-spacing:1px;">
        {{ __('Publicité') }}
    </div>
    <button onclick="document.getElementById('sb-ad-banner').style.display='none'"
            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.3);cursor:pointer;font-size:16px;line-height:1;"
            aria-label="Fermer">✕</button>
</div>
@endif
