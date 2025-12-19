@extends('layouts.app')

@section('content')
<div class="league-lobby-container">
    <div class="league-header">
        <button onclick="window.location.href='{{ route('league.team.management') }}'" class="back-button">
            ← Gestion
        </button>
        <h1>LIGUE PAR ÉQUIPE</h1>
        <div class="team-badge">
            <span class="team-name">{{ $team->name }}</span>
            <span class="team-tag">[{{ $team->tag }}]</span>
            <div class="division-badge {{ $team->division }}">
                <span class="division-name">{{ ucfirst($team->division) }}</span>
                <span class="division-points">{{ $team->points }} pts</span>
            </div>
        </div>
    </div>

    <div class="lobby-content">
        <div class="team-section">
            <div class="team-roster-card">
                <h3>👥 VOTRE ÉQUIPE</h3>
                <div class="roster-grid">
                    @foreach($team->teamMembers as $member)
                        <div class="roster-member">
                            <div class="member-avatar-small">
                                @if($member->user->avatar_url)
                                    <img src="{{ $member->user->avatar_url }}" alt="Avatar">
                                @else
                                    <div class="default-avatar-small">{{ substr($member->user->name, 0, 1) }}</div>
                                @endif
                            </div>
                            <div class="member-details">
                                <p class="member-name">{{ $member->user->name }}</p>
                                @if($member->role === 'captain')
                                    <span class="captain-badge">👑</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="matchmaking-card">
                <h3>⚔️ {{ __('MATCHMAKING') }}</h3>
                <p>{{ __('Affrontez une autre équipe de votre division') }}</p>
                
                @if($team->captain_id === Auth::id())
                <div class="level-selection">
                    <h4>🎖️ {{ __('Niveau de compétition') }}</h4>
                    <div class="level-options">
                        @php
                            $divisions = [
                                'bronze' => ['icon' => '🥉', 'coins' => 10, 'label' => 'Bronze', 'index' => 0],
                                'argent' => ['icon' => '🥈', 'coins' => 20, 'label' => 'Argent', 'index' => 1],
                                'or' => ['icon' => '🥇', 'coins' => 40, 'label' => 'Or', 'index' => 2],
                                'platine' => ['icon' => '💎', 'coins' => 80, 'label' => 'Platine', 'index' => 3],
                                'diamant' => ['icon' => '👑', 'coins' => 160, 'label' => 'Diamant', 'index' => 4],
                            ];
                            $divisionMapping = [
                                'silver' => 'argent',
                                'gold' => 'or',
                                'platinum' => 'platine',
                                'diamond' => 'diamant',
                            ];
                            $rawDivision = strtolower($team->division ?? 'bronze');
                            $teamDivision = $divisionMapping[$rawDivision] ?? $rawDivision;
                            if (!isset($divisions[$teamDivision])) {
                                $teamDivision = 'bronze';
                            }
                            $teamIndex = $divisions[$teamDivision]['index'];
                            $userCoins = Auth::user()->competence_coins ?? 0;
                            
                            $availableOptions = [];
                            foreach ($divisions as $divKey => $div) {
                                $levelDiff = $div['index'] - $teamIndex;
                                if ($levelDiff <= 2) {
                                    $isFree = $levelDiff <= 0;
                                    $accessCost = $isFree ? 0 : $div['coins'] * 2;
                                    $canAfford = $isFree || $userCoins >= $accessCost;
                                    $availableOptions[$divKey] = [
                                        'icon' => $div['icon'],
                                        'label' => $div['label'],
                                        'coins' => $div['coins'],
                                        'isFree' => $isFree,
                                        'accessCost' => $accessCost,
                                        'canAfford' => $canAfford,
                                        'isTeamLevel' => $divKey === $teamDivision,
                                    ];
                                }
                            }
                        @endphp
                        @foreach($availableOptions as $divKey => $opt)
                            <label class="level-option {{ $opt['isTeamLevel'] ? 'selected' : '' }} {{ !$opt['canAfford'] ? 'disabled' : '' }}" data-level="{{ $divKey }}" data-cost="{{ $opt['accessCost'] }}">
                                <input type="radio" name="matchLevel" value="{{ $divKey }}" {{ $opt['isTeamLevel'] ? 'checked' : '' }} {{ !$opt['canAfford'] ? 'disabled' : '' }}>
                                <div class="level-card {{ $divKey }}">
                                    <span class="level-name">{{ $opt['icon'] }} {{ $opt['label'] }}</span>
                                    <span class="level-reward">🪙 +{{ $opt['coins'] }}</span>
                                    @if($opt['isFree'])
                                        <span class="level-cost free">{{ __('Gratuit') }}</span>
                                    @else
                                        <span class="level-cost paid {{ !$opt['canAfford'] ? 'insufficient' : '' }}">🪙 {{ $opt['accessCost'] }} (6h)</span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <p class="coins-balance">{{ __('Vos pièces') }}: 🪙 {{ $userCoins }}</p>
                </div>
                @endif

                <button id="startMatchmakingBtn" class="btn-primary btn-large" onclick="findOpponents()">
                    <span class="btn-icon">🎯</span>
                    {{ __('TROUVER UN ADVERSAIRE') }}
                </button>
                
                <div id="opponentChoices" class="opponent-choices" style="display: none;">
                    <h4>🎯 {{ __('Choisissez votre adversaire') }}</h4>
                    <div class="opponents-list" id="opponentsList"></div>
                </div>
                
                <div id="searchingStatus" class="searching-status" style="display: none;">
                    <div class="spinner"></div>
                    <p>{{ __('Recherche d\'une équipe adverse...') }}</p>
                </div>
            </div>

            <div class="stats-summary">
                <h4>Statistiques de l'Équipe</h4>
                <div class="stats-grid">
                    <div class="stat">
                        <span class="stat-value">{{ $team->matches_played }}</span>
                        <span class="stat-label">Matchs joués</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">{{ $team->matches_won }}</span>
                        <span class="stat-label">Victoires</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">{{ $team->points }}</span>
                        <span class="stat-label">Points</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">
                            @if($team->matches_played > 0)
                                {{ number_format(($team->matches_won / $team->matches_played) * 100, 1) }}%
                            @else
                                0%
                            @endif
                        </span>
                        <span class="stat-label">Taux de victoire</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="ranking-section">
            <div class="ranking-header">
                <h3>🏆 CLASSEMENT {{ strtoupper($team->division) }}</h3>
            </div>
            <div class="ranking-list">
                @forelse($rankings as $ranking)
                <div class="ranking-item {{ $ranking['team']->id == $team->id ? 'current-team' : '' }}">
                    <span class="rank">#{{ $ranking['rank'] }}</span>
                    <div class="team-details">
                        <span class="team-name-rank">{{ $ranking['team']->name }}</span>
                        <span class="team-tag-rank">[{{ $ranking['team']->tag }}]</span>
                        <span class="team-record">
                            {{ $ranking['team']->matches_won }}V - {{ $ranking['team']->matches_lost }}D
                        </span>
                    </div>
                    <div class="team-stats-right">
                        <span class="team-points">{{ $ranking['team']->points }} pts</span>
                        <span class="team-winrate">({{ $ranking['win_rate'] }}%)</span>
                    </div>
                </div>
                @empty
                <p class="no-rankings">Aucune équipe dans votre division pour le moment</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<style>
.team-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.team-badge .team-name {
    font-size: 24px;
    font-weight: bold;
    color: #00d4ff;
}

.team-badge .team-tag {
    color: #ffd700;
    font-size: 18px;
    font-weight: bold;
}

.team-section {
    flex: 1;
    max-width: 500px;
}

.team-roster-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.team-roster-card h3 {
    color: #00d4ff;
    margin-bottom: 15px;
    text-align: center;
}

.roster-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}

.roster-member {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #1a1a2e;
    border: 1px solid #0f3460;
    border-radius: 8px;
}

.member-avatar-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.member-avatar-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00d4ff, #0f3460);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: bold;
    color: #fff;
}

.member-details {
    flex: 1;
    min-width: 0;
}

.member-details .member-name {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.captain-badge {
    font-size: 12px;
}

.team-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.team-name-rank {
    font-weight: bold;
    color: #fff;
}

.team-tag-rank {
    color: #ffd700;
    font-size: 14px;
}

.team-record {
    color: #aaa;
    font-size: 13px;
}

.team-stats-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.team-points {
    font-weight: bold;
    color: #00d4ff;
}

.team-winrate {
    color: #aaa;
    font-size: 13px;
}

.current-team {
    background: linear-gradient(135deg, #0f3460, #1a1a2e) !important;
    border: 2px solid #00d4ff !important;
}

.level-selection {
    margin: 1.5rem 0;
    padding: 1rem;
    background: rgba(0,0,0,0.2);
    border-radius: 12px;
}
.level-selection h4 {
    color: #ffd700;
    margin-bottom: 1rem;
    text-align: center;
}
.level-options {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
    justify-content: center;
}
.level-option {
    cursor: pointer;
}
.level-option input {
    display: none;
}
.level-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #333 0%, #222 100%);
    border: 2px solid #444;
    border-radius: 10px;
    transition: all 0.3s ease;
    min-width: 100px;
}
.level-option.selected .level-card,
.level-option:has(input:checked) .level-card {
    border-color: #00d4ff;
    background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
}
.level-card.bronze {
    border-color: #cd7f32;
}
.level-option:has(input[value="bronze"]:checked) .level-card.bronze {
    border-color: #cd7f32;
    box-shadow: 0 0 15px rgba(205, 127, 50, 0.4);
}
.level-card.argent {
    border-color: #c0c0c0;
}
.level-option:has(input[value="argent"]:checked) .level-card.argent {
    border-color: #c0c0c0;
    box-shadow: 0 0 15px rgba(192, 192, 192, 0.4);
}
.level-card.or {
    border-color: #ffd700;
}
.level-option:has(input[value="or"]:checked) .level-card.or {
    border-color: #ffd700;
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.4);
}
.level-card.platine {
    border-color: #e5e4e2;
}
.level-option:has(input[value="platine"]:checked) .level-card.platine {
    border-color: #e5e4e2;
    box-shadow: 0 0 15px rgba(229, 228, 226, 0.4);
}
.level-card.diamant {
    border-color: #00d4ff;
}
.level-option:has(input[value="diamant"]:checked) .level-card.diamant {
    border-color: #00d4ff;
    box-shadow: 0 0 15px rgba(0, 212, 255, 0.4);
}
.level-name {
    font-weight: bold;
    color: #fff;
}
.level-reward {
    color: #4CAF50;
    font-size: 0.85rem;
}
.level-cost {
    font-size: 0.8rem;
}
.level-cost.free {
    color: #4CAF50;
    font-weight: bold;
}
.level-cost.paid {
    color: #ffd700;
}
.level-cost.insufficient {
    color: #ff6b6b;
    text-decoration: line-through;
}
.level-option.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.level-option.disabled .level-card {
    pointer-events: none;
}
.coins-balance {
    text-align: center;
    color: #ffd700;
    font-size: 0.9rem;
    margin-top: 10px;
}

.opponent-choices {
    margin-top: 1.5rem;
    padding: 1rem;
    background: rgba(0,0,0,0.3);
    border-radius: 12px;
}
.opponent-choices h4 {
    color: #00d4ff;
    margin-bottom: 1rem;
    text-align: center;
}
.opponents-list {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}
.opponent-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}
.opponent-card:hover {
    border-color: #00d4ff;
    transform: translateX(5px);
}
.opponent-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.opponent-emblem {
    font-size: 2rem;
}
.opponent-details {
    display: flex;
    flex-direction: column;
}
.opponent-name {
    font-weight: bold;
    color: #fff;
}
.opponent-record {
    color: #aaa;
    font-size: 0.85rem;
}
.opponent-stats {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}
.opponent-points {
    color: #00d4ff;
    font-weight: bold;
}
.opponent-winrate {
    color: #4CAF50;
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .lobby-content {
        flex-direction: column;
    }
    
    .team-section, .ranking-section {
        max-width: 100%;
    }
    
    .roster-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.querySelectorAll('.level-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.level-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
    });
});

async function findOpponents() {
    const btn = document.getElementById('startMatchmakingBtn');
    const status = document.getElementById('searchingStatus');
    const choices = document.getElementById('opponentChoices');
    const list = document.getElementById('opponentsList');
    const levelInput = document.querySelector('input[name="matchLevel"]:checked');
    const level = levelInput ? levelInput.value : 'normal';
    
    btn.style.display = 'none';
    status.style.display = 'block';
    choices.style.display = 'none';

    try {
        const response = await fetch('/api/league/team/find-opponents', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ level: level, team_id: {{ $team->id }} })
        });

        const data = await response.json();
        status.style.display = 'none';

        if (data.success && data.opponents && data.opponents.length > 0) {
            list.innerHTML = data.opponents.map(opp => `
                <div class="opponent-card" onclick="selectOpponent(${opp.id}, '${level}')">
                    <div class="opponent-info">
                        <div class="opponent-emblem">${opp.emblem || '🛡️'}</div>
                        <div class="opponent-details">
                            <span class="opponent-name">${opp.name} [${opp.tag}]</span>
                            <span class="opponent-record">${opp.wins}V - ${opp.losses}D</span>
                        </div>
                    </div>
                    <div class="opponent-stats">
                        <span class="opponent-points">${opp.points} pts</span>
                        <span class="opponent-winrate">${opp.win_rate}%</span>
                    </div>
                </div>
            `).join('');
            choices.style.display = 'block';
        } else {
            showToast(data.message || '{{ __("Aucune équipe disponible") }}', 'info');
            btn.style.display = 'block';
        }
    } catch (error) {
        showToast('{{ __("Erreur de connexion") }}', 'error');
        btn.style.display = 'block';
        status.style.display = 'none';
    }
}

async function selectOpponent(opponentId, level) {
    const status = document.getElementById('searchingStatus');
    const choices = document.getElementById('opponentChoices');
    
    choices.style.display = 'none';
    status.style.display = 'block';
    status.querySelector('p').textContent = '{{ __("Lancement du match...") }}';

    try {
        const response = await fetch('/api/league/team/start-match', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ 
                team_id: {{ $team->id }},
                opponent_id: opponentId,
                level: level
            })
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = `/league/team/game/${data.match_id}`;
        } else {
            showToast(data.error || '{{ __("Erreur lors du lancement") }}', 'error');
            document.getElementById('startMatchmakingBtn').style.display = 'block';
            status.style.display = 'none';
        }
    } catch (error) {
        showToast('{{ __("Erreur de connexion") }}', 'error');
        document.getElementById('startMatchmakingBtn').style.display = 'block';
        status.style.display = 'none';
    }
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:8px;color:#fff;font-weight:600;z-index:9999;';
    toast.style.background = type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#17a2b8';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>
@endsection
