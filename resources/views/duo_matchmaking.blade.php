@extends('layouts.app')

@section('content')
<div class="matchmaking-container">
    <div class="matchmaking-content">
        <div class="loading-animation">
            <div class="spinner"></div>
            <h2>RECHERCHE D'ADVERSAIRE</h2>
            <p class="division-text">Division {{ $division ?? 'Bronze' }}</p>
        </div>

        <div class="vs-display" id="vsDisplay" style="display: none;">
            <div class="player-side">
                <div class="player-avatar">
                    @if(Auth::user()->avatar_url)
                        <img src="{{ Auth::user()->avatar_url }}" alt="Vous">
                    @else
                        <div class="default-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                    @endif
                </div>
                <h3>{{ Auth::user()->name }}</h3>
                <p class="player-level">Niveau {{ $player_level ?? 1 }}</p>
            </div>

            <div class="vs-text">VS</div>

            <div class="opponent-side">
                <div class="opponent-avatar" id="opponentAvatar">
                    <div class="searching-pulse">?</div>
                </div>
                <h3 id="opponentName">Recherche...</h3>
                <p class="player-level" id="opponentLevel">-</p>
            </div>
        </div>

        <div class="match-info" id="matchInfo" style="display: none;">
            <div class="info-item">
                <span class="label">Mode:</span>
                <span class="value">Best of 3</span>
            </div>
            <div class="info-item">
                <span class="label">Questions par round:</span>
                <span class="value">10</span>
            </div>
            <div class="info-item">
                <span class="label">Thème:</span>
                <span class="value" id="themeValue">Culture Générale</span>
            </div>
        </div>

        <button onclick="cancelMatchmaking()" class="btn-cancel">
            Annuler
        </button>
    </div>
</div>

<style>
.matchmaking-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
}

.matchmaking-content {
    text-align: center;
    color: white;
}

.loading-animation {
    margin-bottom: 40px;
}

.spinner {
    width: 80px;
    height: 80px;
    border: 8px solid rgba(255, 255, 255, 0.3);
    border-top: 8px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 30px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-animation h2 {
    font-size: 2em;
    margin: 0 0 10px 0;
}

.division-text {
    font-size: 1.2em;
    opacity: 0.9;
}

.vs-display {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 40px;
    align-items: center;
    margin-bottom: 40px;
    animation: fadeIn 0.5s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.player-side, .opponent-side {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.player-avatar, .opponent-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: 0 8px 16px rgba(0,0,0,0.3);
}

.player-avatar img, .opponent-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.3);
    font-size: 3em;
    font-weight: bold;
}

.searching-pulse {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.2);
    font-size: 3em;
    font-weight: bold;
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.1); }
}

.vs-text {
    font-size: 3em;
    font-weight: bold;
    text-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

.player-level {
    opacity: 0.9;
    font-size: 1.1em;
}

.match-info {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
    backdrop-filter: blur(10px);
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.3);
}

.info-item:last-child {
    border-bottom: none;
}

.label {
    opacity: 0.9;
}

.value {
    font-weight: bold;
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid white;
    border-radius: 8px;
    padding: 15px 40px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .vs-display {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .vs-text {
        transform: rotate(90deg);
    }
}
</style>

<script>
const matchId = new URLSearchParams(window.location.search).get('match_id');
let pollingInterval;

function checkMatchStatus() {
    if (!matchId) return;

    fetch(`/duo/matches/${matchId}/game-state`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'playing') {
                showOpponentFound(data.opponent);
                setTimeout(() => {
                    window.location.href = '/duo/game/' + matchId;
                }, 2000);
            } else if (data.status === 'waiting') {
                document.getElementById('vsDisplay').style.display = 'grid';
                document.getElementById('matchInfo').style.display = 'block';
            }
        })
        .catch(error => console.error('Error:', error));
}

function showOpponentFound(opponent) {
    clearInterval(pollingInterval);
    
    const spinner = document.querySelector('.spinner');
    if (spinner) spinner.style.display = 'none';
    
    const opponentAvatar = document.getElementById('opponentAvatar');
    const opponentName = document.getElementById('opponentName');
    const opponentLevel = document.getElementById('opponentLevel');
    
    if (opponent.avatar_url) {
        opponentAvatar.innerHTML = `<img src="${opponent.avatar_url}" alt="${opponent.name}">`;
    } else {
        opponentAvatar.innerHTML = `<div class="default-avatar">${opponent.name.charAt(0)}</div>`;
    }
    
    opponentName.textContent = opponent.name;
    opponentLevel.textContent = `Niveau ${opponent.level || 1}`;
    
    document.querySelector('.loading-animation h2').textContent = 'ADVERSAIRE TROUVÉ !';
}

function cancelMatchmaking() {
    if (matchId) {
        fetch(`/duo/matches/${matchId}/cancel`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        }).then(() => {
            window.location.href = '{{ route("duo.lobby") }}';
        });
    } else {
        window.location.href = '{{ route("duo.lobby") }}';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    checkMatchStatus();
    pollingInterval = setInterval(checkMatchStatus, 2000);
});
</script>
@endsection
