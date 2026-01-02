@extends('layouts.game')

@section('title', __('Gestion du capitaine'))

@section('content')
<div class="game-container captain-container">
    <div class="game-header">
        <a href="{{ route('league.team.management') }}" class="back-btn">← {{ __('Retour') }}</a>
        <h1>👑 {{ __('GESTION DU CAPITAINE') }}</h1>
    </div>

    <div class="captain-content">
        <div class="team-settings-section">
            <h3>⚙️ {{ __('Paramètres de l\'équipe') }}</h3>
            <div class="setting-row">
                <label for="recruitingToggle">
                    <span>🟢 {{ __('Recrutement ouvert') }}</span>
                    <p class="setting-description">{{ __('Permet aux joueurs de demander à rejoindre votre équipe') }}</p>
                </label>
                <label class="toggle-switch">
                    <input type="checkbox" id="recruitingToggle" {{ $team->is_recruiting ? 'checked' : '' }}>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="join-requests-section">
            <h3>📬 {{ __('Demandes d\'accès') }} <span class="request-count">{{ $pendingRequests->count() }}</span></h3>
            
            @if($pendingRequests->isEmpty())
                <div class="no-requests">
                    <p>{{ __('Aucune demande d\'accès en attente.') }}</p>
                </div>
            @else
                <div class="requests-list">
                    @foreach($pendingRequests as $request)
                        <div class="request-card" data-request-id="{{ $request->id }}">
                            <div class="request-player-info">
                                <div class="player-avatar">
                                    @if($request->user->avatar_url ?? null)
                                        <img src="{{ $request->user->avatar_url }}" alt="Avatar">
                                    @else
                                        <div class="default-avatar">{{ strtoupper(substr($request->user->name, 0, 1)) }}</div>
                                    @endif
                                </div>
                                <div class="player-details">
                                    <p class="player-name">{{ $request->user->name }}</p>
                                    <p class="player-code">{{ $request->user->player_code ?? 'SB-XXXX' }}</p>
                                </div>
                            </div>
                            
                            <div class="request-player-stats">
                                <div class="mini-stat">
                                    <span class="mini-label">ELO</span>
                                    <span class="mini-value">{{ $request->user->league_elo ?? 1000 }}</span>
                                </div>
                                <div class="mini-stat">
                                    <span class="mini-label">V/D</span>
                                    <span class="mini-value">{{ $request->user->league_wins ?? 0 }}/{{ $request->user->league_losses ?? 0 }}</span>
                                </div>
                            </div>
                            
                            <div class="request-date">
                                {{ __('Demande le') }} {{ $request->created_at->format('d/m/Y H:i') }}
                            </div>
                            
                            <div class="request-actions">
                                <button class="btn-accept" onclick="handleRequest({{ $request->id }}, 'accept')">
                                    ✅ {{ __('Accepter') }}
                                </button>
                                <button class="btn-decline" onclick="handleRequest({{ $request->id }}, 'reject')">
                                    ❌ {{ __('Refuser') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if($team->members->count() >= 5)
            <div class="team-full-notice">
                ⚠️ {{ __('Votre équipe est complète. Les nouvelles demandes seront automatiquement refusées.') }}
            </div>
        @endif
    </div>
</div>

<style>
.captain-container {
    min-height: 100vh;
    padding-bottom: 2rem;
}

.captain-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 1rem;
}

.team-settings-section, .join-requests-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.team-settings-section h3, .join-requests-section h3 {
    color: #00d4ff;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.request-count {
    background: #ff4444;
    color: #fff;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.9rem;
}

.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: rgba(15, 52, 96, 0.5);
    border-radius: 10px;
}

.setting-row label span {
    color: #fff;
    font-weight: 600;
}

.setting-description {
    color: #888;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.toggle-switch {
    position: relative;
    width: 60px;
    height: 30px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #333;
    transition: 0.3s;
    border-radius: 30px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 4px;
    bottom: 4px;
    background-color: #fff;
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, #00d4ff, #0094ff);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(30px);
}

.no-requests {
    text-align: center;
    padding: 3rem;
    color: #888;
}

.requests-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.request-card {
    background: rgba(15, 52, 96, 0.5);
    border: 1px solid #0f3460;
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.request-card.accepted {
    border-color: #28a745;
    background: rgba(40, 167, 69, 0.2);
}

.request-card.rejected {
    border-color: #dc3545;
    background: rgba(220, 53, 69, 0.2);
}

.request-player-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.player-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
}

.player-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #0f3460, #00d4ff);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.5rem;
    font-weight: bold;
}

.player-name {
    color: #fff;
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
}

.player-code {
    color: #888;
    font-size: 0.85rem;
    margin: 0;
}

.request-player-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 0.75rem;
}

.mini-stat {
    display: flex;
    flex-direction: column;
}

.mini-label {
    color: #888;
    font-size: 0.75rem;
    text-transform: uppercase;
}

.mini-value {
    color: #00d4ff;
    font-weight: bold;
}

.request-date {
    color: #666;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.request-actions {
    display: flex;
    gap: 1rem;
}

.btn-accept, .btn-decline {
    flex: 1;
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-accept {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: #fff;
}

.btn-accept:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.btn-decline {
    background: linear-gradient(135deg, #dc3545, #e74c3c);
    color: #fff;
}

.btn-decline:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.btn-accept:disabled, .btn-decline:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.team-full-notice {
    background: rgba(255, 215, 0, 0.2);
    color: #ffd700;
    padding: 1.5rem;
    border-radius: 10px;
    text-align: center;
}

@media (max-width: 600px) {
    .setting-row {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .request-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.getElementById('recruitingToggle')?.addEventListener('change', async function() {
    const isRecruiting = this.checked;
    
    try {
        const response = await fetch('{{ route("league.team.toggle-recruiting") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ is_recruiting: isRecruiting })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            this.checked = !isRecruiting;
            if (window.customDialog) window.customDialog.alert(data.error || '{{ __("Erreur lors de la mise à jour") }}');
        }
    } catch (error) {
        this.checked = !isRecruiting;
        console.error('Error:', error);
    }
});

async function handleRequest(requestId, action) {
    const card = document.querySelector(`[data-request-id="${requestId}"]`);
    const buttons = card.querySelectorAll('button');
    
    buttons.forEach(btn => btn.disabled = true);
    
    try {
        const url = action === 'accept' 
            ? '{{ url("/league/team/request") }}/' + requestId + '/accept'
            : '{{ url("/league/team/request") }}/' + requestId + '/reject';
        
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            card.classList.add(action === 'accept' ? 'accepted' : 'rejected');
            card.querySelector('.request-actions').innerHTML = `
                <span style="color: ${action === 'accept' ? '#28a745' : '#dc3545'}; font-weight: 600;">
                    ${action === 'accept' ? '{{ __("Accepté") }} ✅' : '{{ __("Refusé") }} ❌'}
                </span>
            `;
            
            const countBadge = document.querySelector('.request-count');
            const currentCount = parseInt(countBadge.textContent);
            countBadge.textContent = Math.max(0, currentCount - 1);
            
            if (action === 'accept') {
                setTimeout(() => window.location.reload(), 1500);
            }
        } else {
            if (window.customDialog) window.customDialog.alert(data.error || '{{ __("Erreur lors du traitement de la demande") }}');
            buttons.forEach(btn => btn.disabled = false);
        }
    } catch (error) {
        console.error('Error:', error);
        buttons.forEach(btn => btn.disabled = false);
    }
}
</script>
@endsection
