@php
    $user = auth()->user();
    $rewardedEnabled = config('ads.enabled', false)
        && config('ads.rewarded.enabled', false)
        && !($user?->master_purchased ?? false)
        && $user !== null;
@endphp

@if($rewardedEnabled)
<div class="ad-reward-wrapper" style="margin:12px 0;">
    <button
        id="sb-ad-reward-btn"
        class="btn btn-sm"
        style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.7);border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer;"
        onclick="sbWatchRewardedAd()"
    >
        📺 {{ __('Voir une pub (+:amount pièces)', ['amount' => config('ads.rewarded.reward.amount', 10)]) }}
    </button>
    <div id="sb-ad-reward-msg" style="font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;"></div>
</div>

<script>
async function sbWatchRewardedAd() {
    const btn = document.getElementById('sb-ad-reward-btn');
    const msg = document.getElementById('sb-ad-reward-msg');
    if (!btn || btn.disabled) return;

    btn.disabled = true;
    btn.style.opacity = '0.5';
    msg.textContent = '{{ __("Chargement...") }}';

    try {
        const res = await fetch('/ads/reward', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
            }
        });
        const data = await res.json();

        if (data.success) {
            msg.style.color = '#34d399';
            msg.textContent = '✓ {{ __("+:amount pièces de compétence reçues !", ["amount" => config("ads.rewarded.reward.amount", 10)]) }}';
            if (data.remaining > 0) {
                btn.style.display = '';
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.textContent = '📺 {{ __("Voir une pub") }} (+{{ config("ads.rewarded.reward.amount", 10) }} — ' + data.remaining + ' restante' + (data.remaining > 1 ? 's' : '') + ')';
            } else {
                btn.style.display = 'none';
            }
        } else if (data.reason === 'limit_reached') {
            msg.style.color = 'rgba(255,120,80,0.8)';
            msg.textContent = '{{ __("Limite atteinte pour aujourd\'hui.") }}';
            btn.style.display = 'none';
        } else {
            msg.style.color = 'rgba(255,120,80,0.8)';
            msg.textContent = '{{ __("Indisponible.") }}';
            btn.style.display = 'none';
        }
    } catch(e) {
        btn.disabled = false;
        btn.style.opacity = '1';
        msg.textContent = '{{ __("Erreur, réessayez.") }}';
    }
}
</script>
@endif
