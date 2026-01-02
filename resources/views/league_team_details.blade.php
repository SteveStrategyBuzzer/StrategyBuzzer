@extends('layouts.game')

@section('title', $team->name . ' - ' . __('Détails de l\'équipe'))

@section('content')
<div class="game-container team-details-container">
    <div class="game-header">
        @if($isOwnTeam)
            <a href="{{ route('league.team.management') }}" class="back-btn">← {{ __('Retour') }}</a>
        @else
            <a href="{{ route('league.team.search') }}" class="back-btn">← {{ __('Retour') }}</a>
        @endif
        <h1>{{ $team->name }}</h1>
    </div>

    <div class="team-details-content">
        <div class="team-header-card">
            <div class="team-identity">
                <span class="team-tag">[{{ $team->tag }}]</span>
                <h2>{{ $team->name }}</h2>
            </div>
            <div class="team-division {{ strtolower($team->division ?? 'bronze') }}">
                {{ $team->division ?? 'Bronze' }}
            </div>
            <div class="team-stats-row">
                <div class="stat-item">
                    <span class="stat-value">{{ $team->elo ?? 1000 }}</span>
                    <span class="stat-label">ELO</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">{{ $team->total_wins ?? 0 }}</span>
                    <span class="stat-label">{{ __('Victoires') }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">{{ $team->total_losses ?? 0 }}</span>
                    <span class="stat-label">{{ __('Défaites') }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">{{ $team->season_points ?? 0 }}</span>
                    <span class="stat-label">{{ __('Points Saison') }}</span>
                </div>
            </div>
        </div>

        <div class="team-global-strength">
            <h3>💪 {{ __('Forces Globales de l\'Équipe') }}</h3>
            <div class="radar-container">
                <canvas id="teamRadarChart" width="300" height="300"></canvas>
            </div>
            <div class="strengths-summary">
                @foreach($teamStrengths as $theme => $value)
                    <div class="strength-bar">
                        <span class="theme-name">{{ $theme }}</span>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: {{ $value }}%"></div>
                        </div>
                        <span class="theme-value">{{ $value }}%</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="team-members-section">
            <h3>👥 {{ __('Membres de l\'équipe') }} ({{ $team->members->count() }}/5)</h3>
            <div class="members-grid">
                @foreach($team->members as $member)
                    <div class="member-detail-card">
                        <div class="member-header">
                            <div class="member-avatar">
                                @if($member->avatar_url ?? null)
                                    <img src="{{ $member->avatar_url }}" alt="Avatar">
                                @else
                                    <div class="default-avatar">{{ strtoupper(substr($member->name, 0, 1)) }}</div>
                                @endif
                            </div>
                            <div class="member-info">
                                <p class="member-name">
                                    {{ $member->name }}
                                    @if($team->captain_id === $member->id)
                                        <span class="captain-badge">👑</span>
                                    @endif
                                </p>
                                <p class="member-code">{{ $member->player_code ?? '' }}</p>
                            </div>
                        </div>
                        
                        <div class="member-radar-mini">
                            <canvas id="memberRadar{{ $member->id }}" width="150" height="150"></canvas>
                        </div>
                        
                        <div class="member-contribution">
                            <h4>{{ __('Ce que tu apportes') }}</h4>
                            <div class="contribution-tags">
                                @if(isset($memberContributions[$member->id]))
                                    @foreach($memberContributions[$member->id] as $contribution)
                                        <span class="contribution-tag">{{ $contribution }}</span>
                                    @endforeach
                                @else
                                    <span class="contribution-tag default">{{ __('En analyse...') }}</span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="member-stats-mini">
                            <div class="mini-stat">
                                <span class="mini-label">V/D</span>
                                <span class="mini-value">{{ $member->league_wins ?? 0 }}/{{ $member->league_losses ?? 0 }}</span>
                            </div>
                            <div class="mini-stat">
                                <span class="mini-label">ELO</span>
                                <span class="mini-value">{{ $member->league_elo ?? 1000 }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
                
                @for($i = $team->members->count(); $i < 5; $i++)
                    <div class="member-detail-card empty-slot">
                        <div class="empty-slot-icon">👤</div>
                        <p>{{ __('Poste vacant') }}</p>
                    </div>
                @endfor
            </div>
        </div>

        @if(!$isOwnTeam && !$userTeam && $team->is_recruiting && $team->members->count() < 5)
            <div class="join-section">
                @if($hasPendingRequest)
                    <div class="pending-request-info">
                        ⏳ {{ __('Votre demande d\'accès est en attente de validation par le capitaine.') }}
                    </div>
                @else
                    <button id="joinRequestBtn" class="btn-primary btn-large">
                        <span class="btn-icon">✋</span>
                        {{ __('DEMANDER À REJOINDRE') }}
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>

<style>
.team-details-container {
    min-height: 100vh;
    padding-bottom: 2rem;
}

.team-details-content {
    max-width: 1000px;
    margin: 0 auto;
    padding: 1rem;
}

.team-header-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    margin-bottom: 2rem;
}

.team-identity {
    margin-bottom: 1rem;
}

.team-tag {
    color: #ffd700;
    font-size: 1.5rem;
    font-weight: bold;
}

.team-identity h2 {
    color: #00d4ff;
    font-size: 2rem;
    margin: 0.5rem 0;
}

.team-division {
    display: inline-block;
    padding: 8px 24px;
    border-radius: 20px;
    font-weight: bold;
    margin: 1rem 0;
}

.team-division.bronze { background: linear-gradient(135deg, #CD7F32, #8B4513); color: #fff; }
.team-division.argent, .team-division.silver { background: linear-gradient(135deg, #C0C0C0, #808080); color: #1a1a2e; }
.team-division.or, .team-division.gold { background: linear-gradient(135deg, #FFD700, #FFA500); color: #1a1a2e; }
.team-division.platine, .team-division.platinum { background: linear-gradient(135deg, #E5E4E2, #B0B0B0); color: #1a1a2e; }
.team-division.diamant, .team-division.diamond { background: linear-gradient(135deg, #B9F2FF, #00CED1); color: #1a1a2e; }
.team-division.legende, .team-division.legend { background: linear-gradient(135deg, #FF00FF, #8B008B); color: #fff; }

.team-stats-row {
    display: flex;
    justify-content: center;
    gap: 3rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-item .stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #00d4ff;
}

.stat-item .stat-label {
    color: #888;
    font-size: 0.85rem;
    text-transform: uppercase;
}

.team-global-strength {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.team-global-strength h3 {
    color: #00d4ff;
    margin-bottom: 1.5rem;
    text-align: center;
}

.radar-container {
    display: flex;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.strengths-summary {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.strength-bar {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.theme-name {
    width: 120px;
    color: #fff;
    font-size: 0.9rem;
}

.bar-container {
    flex: 1;
    height: 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #00d4ff, #0094ff);
    border-radius: 6px;
    transition: width 0.5s ease;
}

.theme-value {
    width: 50px;
    text-align: right;
    color: #00d4ff;
    font-weight: bold;
}

.team-members-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.team-members-section h3 {
    color: #00d4ff;
    margin-bottom: 1.5rem;
}

.members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.member-detail-card {
    background: rgba(15, 52, 96, 0.5);
    border: 1px solid #0f3460;
    border-radius: 12px;
    padding: 1.5rem;
}

.member-detail-card.empty-slot {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    opacity: 0.5;
    border-style: dashed;
}

.empty-slot-icon {
    font-size: 3rem;
    opacity: 0.5;
}

.member-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.member-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
}

.member-avatar img {
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

.member-name {
    color: #fff;
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
}

.captain-badge {
    margin-left: 0.5rem;
}

.member-code {
    color: #888;
    font-size: 0.85rem;
    margin: 0;
}

.member-radar-mini {
    display: flex;
    justify-content: center;
    margin: 1rem 0;
}

.member-contribution {
    margin: 1rem 0;
}

.member-contribution h4 {
    color: #ffd700;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.contribution-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.contribution-tag {
    background: rgba(0, 212, 255, 0.2);
    color: #00d4ff;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
}

.contribution-tag.default {
    background: rgba(255, 255, 255, 0.1);
    color: #888;
}

.member-stats-mini {
    display: flex;
    justify-content: space-around;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.mini-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.mini-label {
    color: #888;
    font-size: 0.75rem;
}

.mini-value {
    color: #00d4ff;
    font-weight: bold;
}

.join-section {
    text-align: center;
    margin-top: 2rem;
}

.pending-request-info {
    background: rgba(255, 215, 0, 0.2);
    color: #ffd700;
    padding: 1.5rem;
    border-radius: 10px;
    font-size: 1.1rem;
}

.btn-primary.btn-large {
    padding: 1rem 3rem;
    font-size: 1.2rem;
    background: linear-gradient(135deg, #00d4ff 0%, #0094ff 100%);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary.btn-large:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 212, 255, 0.3);
}

@media (max-width: 600px) {
    .team-stats-row {
        gap: 1.5rem;
    }
    
    .stat-item .stat-value {
        font-size: 1.5rem;
    }
    
    .members-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const teamStrengths = @json($teamStrengths);
    const memberStats = @json($memberStats);
    
    const themes = Object.keys(teamStrengths);
    const teamValues = Object.values(teamStrengths);
    
    const teamRadarCtx = document.getElementById('teamRadarChart')?.getContext('2d');
    if (teamRadarCtx) {
        new Chart(teamRadarCtx, {
            type: 'radar',
            data: {
                labels: themes,
                datasets: [{
                    label: '{{ __("Force équipe") }}',
                    data: teamValues,
                    backgroundColor: 'rgba(0, 212, 255, 0.3)',
                    borderColor: '#00d4ff',
                    borderWidth: 2,
                    pointBackgroundColor: '#00d4ff',
                    pointBorderColor: '#fff',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20,
                            color: '#888'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        pointLabels: {
                            color: '#fff',
                            font: { size: 11 }
                        }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
    
    Object.keys(memberStats).forEach(memberId => {
        const stats = memberStats[memberId];
        const canvas = document.getElementById('memberRadar' + memberId);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'radar',
            data: {
                labels: themes,
                datasets: [{
                    label: '{{ __("Force") }}',
                    data: themes.map(t => stats[t] || 50),
                    backgroundColor: 'rgba(255, 215, 0, 0.3)',
                    borderColor: '#ffd700',
                    borderWidth: 2,
                    pointBackgroundColor: '#ffd700',
                    pointRadius: 3
                }]
            },
            options: {
                responsive: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { display: false },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        pointLabels: {
                            color: '#aaa',
                            font: { size: 9 }
                        }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    });
});

document.getElementById('joinRequestBtn')?.addEventListener('click', async function() {
    this.disabled = true;
    this.innerHTML = '<span class="btn-icon">⏳</span> {{ __("Envoi en cours...") }}';
    
    try {
        const response = await fetch('{{ route("league.team.request", $team->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.outerHTML = `<div class="pending-request-info">⏳ {{ __('Votre demande d\'accès est en attente de validation par le capitaine.') }}</div>`;
        } else {
            if (window.customDialog) window.customDialog.alert(data.error || '{{ __("Erreur lors de l\'envoi de la demande") }}');
            this.disabled = false;
            this.innerHTML = '<span class="btn-icon">✋</span> {{ __("DEMANDER À REJOINDRE") }}';
        }
    } catch (error) {
        console.error('Error:', error);
        this.disabled = false;
        this.innerHTML = '<span class="btn-icon">✋</span> {{ __("DEMANDER À REJOINDRE") }}';
    }
});
</script>
@endsection
