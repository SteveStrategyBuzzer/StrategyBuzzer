@extends('layouts.game')

@section('title', __('Rechercher une équipe'))

@section('content')
<div class="game-container team-search-container">
    <div class="game-header">
        <a href="{{ route('league.team.management') }}" class="back-btn">← {{ __('Retour') }}</a>
        <h1>🔍 {{ __('RECHERCHER UNE ÉQUIPE') }}</h1>
    </div>

    <div class="search-content">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="{{ __('Rechercher par nom ou tag...') }}" autofocus>
            <button id="searchBtn" class="btn-search">🔎</button>
        </div>

        <div class="filters-section">
            <label class="filter-checkbox">
                <input type="checkbox" id="recruitingOnly" checked>
                {{ __('Équipes en recrutement uniquement') }}
            </label>
        </div>

        <div id="searchResults" class="search-results">
            @if($teams->isEmpty())
                <div class="no-results">
                    <p>{{ __('Aucune équipe en recrutement pour le moment.') }}</p>
                    <p>{{ __('Soyez le premier à créer une équipe !') }}</p>
                </div>
            @else
                @foreach($teams as $team)
                    <div class="team-search-card" onclick="window.location.href='{{ route('league.team.details', $team->id) }}'">
                        <div class="team-card-header">
                            <div class="team-card-info">
                                <span class="team-tag">[{{ $team->tag }}]</span>
                                <span class="team-name">{{ $team->name }}</span>
                            </div>
                            <div class="team-division {{ strtolower($team->division ?? 'bronze') }}">
                                {{ $team->division ?? 'Bronze' }}
                            </div>
                        </div>
                        <div class="team-card-stats">
                            <div class="stat">
                                <span class="stat-label">{{ __('Membres') }}</span>
                                <span class="stat-value">{{ $team->members_count ?? $team->member_count ?? 0 }}/5</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label">{{ __('ELO') }}</span>
                                <span class="stat-value">{{ $team->elo ?? 1000 }}</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label">V/D</span>
                                <span class="stat-value">{{ $team->total_wins ?? 0 }}/{{ $team->total_losses ?? 0 }}</span>
                            </div>
                        </div>
                        @if($team->is_recruiting)
                            <div class="recruiting-badge">🟢 {{ __('Recrute') }}</div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<style>
.team-search-container {
    min-height: 100vh;
    padding-bottom: 2rem;
}

.search-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 1rem;
}

.search-box {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.search-box input {
    flex: 1;
    padding: 1rem;
    border: 2px solid #0f3460;
    border-radius: 10px;
    background: #16213e;
    color: #fff;
    font-size: 1rem;
}

.search-box input:focus {
    outline: none;
    border-color: #00d4ff;
}

.btn-search {
    padding: 0 1.5rem;
    background: linear-gradient(135deg, #00d4ff 0%, #0094ff 100%);
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 1.5rem;
    transition: all 0.3s ease;
}

.btn-search:hover {
    transform: scale(1.05);
}

.filters-section {
    margin-bottom: 1.5rem;
}

.filter-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #aaa;
    cursor: pointer;
}

.filter-checkbox input {
    width: 18px;
    height: 18px;
}

.search-results {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.no-results {
    text-align: center;
    padding: 3rem;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    color: #aaa;
}

.no-results p:first-child {
    font-size: 1.2rem;
    color: #fff;
    margin-bottom: 0.5rem;
}

.team-search-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.team-search-card:hover {
    transform: translateY(-3px);
    border-color: #00d4ff;
    box-shadow: 0 8px 24px rgba(0, 212, 255, 0.2);
}

.team-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.team-card-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.team-tag {
    color: #ffd700;
    font-weight: bold;
    font-size: 1.1rem;
}

.team-name {
    color: #fff;
    font-size: 1.2rem;
    font-weight: 600;
}

.team-division {
    padding: 6px 16px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.85rem;
}

.team-division.bronze { background: linear-gradient(135deg, #CD7F32, #8B4513); color: #fff; }
.team-division.argent, .team-division.silver { background: linear-gradient(135deg, #C0C0C0, #808080); color: #1a1a2e; }
.team-division.or, .team-division.gold { background: linear-gradient(135deg, #FFD700, #FFA500); color: #1a1a2e; }
.team-division.platine, .team-division.platinum { background: linear-gradient(135deg, #E5E4E2, #B0B0B0); color: #1a1a2e; }
.team-division.diamant, .team-division.diamond { background: linear-gradient(135deg, #B9F2FF, #00CED1); color: #1a1a2e; }
.team-division.legende, .team-division.legend { background: linear-gradient(135deg, #FF00FF, #8B008B); color: #fff; }

.team-card-stats {
    display: flex;
    gap: 2rem;
}

.stat {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-label {
    color: #888;
    font-size: 0.8rem;
    text-transform: uppercase;
}

.stat-value {
    color: #00d4ff;
    font-weight: bold;
    font-size: 1.1rem;
}

.recruiting-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(0, 255, 0, 0.2);
    color: #00ff00;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

@media (max-width: 600px) {
    .team-card-stats {
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .stat {
        min-width: 80px;
    }
    
    .team-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
}
</style>

<script>
const searchInput = document.getElementById('searchInput');
const recruitingOnly = document.getElementById('recruitingOnly');
let searchTimeout;

async function performSearch() {
    const query = searchInput.value.trim();
    const recruiting = recruitingOnly.checked;
    
    try {
        const url = new URL('{{ route("league.team.search.api") }}', window.location.origin);
        if (query) url.searchParams.set('q', query);
        if (recruiting) url.searchParams.set('recruiting', '1');
        
        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        renderResults(data.teams || []);
    } catch (error) {
        console.error('Search error:', error);
    }
}

function renderResults(teams) {
    const container = document.getElementById('searchResults');
    
    if (teams.length === 0) {
        container.innerHTML = `
            <div class="no-results">
                <p>{{ __('Aucune équipe trouvée.') }}</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = teams.map(team => `
        <div class="team-search-card" onclick="window.location.href='/league/team/details/${team.id}'">
            <div class="team-card-header">
                <div class="team-card-info">
                    <span class="team-tag">[${team.tag}]</span>
                    <span class="team-name">${team.name}</span>
                </div>
                <div class="team-division ${(team.division || 'bronze').toLowerCase()}">
                    ${team.division || 'Bronze'}
                </div>
            </div>
            <div class="team-card-stats">
                <div class="stat">
                    <span class="stat-label">{{ __('Membres') }}</span>
                    <span class="stat-value">${team.member_count || 0}/5</span>
                </div>
                <div class="stat">
                    <span class="stat-label">{{ __('ELO') }}</span>
                    <span class="stat-value">${team.elo || 1000}</span>
                </div>
                <div class="stat">
                    <span class="stat-label">V/D</span>
                    <span class="stat-value">${team.total_wins || 0}/${team.total_losses || 0}</span>
                </div>
            </div>
            ${team.is_recruiting ? '<div class="recruiting-badge">🟢 {{ __("Recrute") }}</div>' : ''}
        </div>
    `).join('');
}

searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(performSearch, 300);
});

recruitingOnly.addEventListener('change', performSearch);

document.getElementById('searchBtn').addEventListener('click', performSearch);
</script>
@endsection
