@extends('layouts.app')

@section('content')
<div class="rankings-container">
    <a href="{{ route('league.individual.lobby') }}" class="back-button-fixed">
        ← Retour
    </a>
    
    <div class="rankings-header">
        <h1>🏆 CLASSEMENTS LIGUE INDIVIDUEL</h1>
    </div>

    <div class="division-tabs">
        <button class="division-tab active bronze" data-division="bronze">
            Bronze
        </button>
        <button class="division-tab argent" data-division="argent">
            Argent
        </button>
        <button class="division-tab or" data-division="or">
            Or
        </button>
        <button class="division-tab platine" data-division="platine">
            Platine
        </button>
        <button class="division-tab diamant" data-division="diamant">
            Diamant
        </button>
        <button class="division-tab legende" data-division="legende">
            Légende
        </button>
    </div>

    <div class="rankings-content">
        <div class="current-division-info">
            <h2 id="divisionTitle">DIVISION BRONZE</h2>
            <p id="divisionDescription">0-99 points</p>
        </div>

        <div class="my-rank-card" id="myRankCard">
            <div class="rank-badge">
                #<span id="myRank">-</span>
            </div>
            <div class="player-info">
                <h3>{{ Auth::user()->name }}</h3>
                <p>Votre position dans la division</p>
            </div>
            <div class="rank-stats">
                <span class="level">Niv. <span id="myLevel">{{ $myStats->level ?? 1 }}</span></span>
                <span class="points"><span id="myPoints">{{ $myDivision->points ?? 0 }}</span> pts</span>
            </div>
        </div>

        <div class="rankings-list" id="rankingsList">
            <div class="loading">Chargement...</div>
        </div>
    </div>
</div>

<style>
.rankings-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    min-height: 100vh;
    background: #f5f5f5;
}

.back-button-fixed {
    position: fixed;
    top: 10px;
    left: 10px;
    padding: 8px 14px;
    background: rgba(26, 26, 46, 0.9);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    font-size: 0.9em;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    z-index: 100;
    backdrop-filter: blur(5px);
}

.back-button-fixed:hover {
    background: rgba(26, 26, 46, 1);
    color: #fff;
}

.rankings-header {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 30px;
    margin-top: 10px;
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.rankings-header h1 {
    font-size: 2em;
    color: #1a1a1a;
    text-align: center;
    margin: 0;
}

@media (max-width: 768px) {
    .back-button-fixed {
        top: 8px;
        left: 8px;
        padding: 6px 12px;
        font-size: 0.85em;
    }
    
    .rankings-header h1 {
        font-size: 1.4em;
    }
}

.division-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    overflow-x: auto;
    padding: 10px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.division-tab {
    flex: 1;
    padding: 15px 25px;
    border: none;
    border-radius: 10px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    color: white;
    min-width: 120px;
}

.division-tab.bronze { background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%); }
.division-tab.argent { background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%); }
.division-tab.or { background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); }
.division-tab.platine { background: linear-gradient(135deg, #E5E4E2 0%, #71797E 100%); }
.division-tab.diamant { background: linear-gradient(135deg, #B9F2FF 0%, #00CED1 100%); }
.division-tab.legende { background: linear-gradient(135deg, #FF1493 0%, #8B008B 100%); }

.division-tab:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.division-tab.active {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

.rankings-content {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.current-division-info {
    text-align: center;
    margin-bottom: 30px;
}

.current-division-info h2 {
    font-size: 2em;
    color: #1a1a1a;
    margin-bottom: 5px;
}

.current-division-info p {
    color: #666;
    font-size: 1.1em;
}

.my-rank-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border: 2px solid #667eea;
    border-radius: 15px;
    margin-bottom: 30px;
}

.rank-badge {
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50%;
    font-size: 1.5em;
    font-weight: bold;
}

.my-rank-card .player-info {
    flex: 1;
}

.my-rank-card .player-info h3 {
    font-size: 1.5em;
    margin-bottom: 5px;
    color: #1a1a1a;
}

.my-rank-card .player-info p {
    color: #666;
}

.rank-stats {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
}

.rank-stats .level {
    font-size: 1.1em;
    color: #666;
}

.rank-stats .points {
    font-size: 1.3em;
    font-weight: bold;
    color: #667eea;
}

.rankings-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.ranking-item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: #f8f8f8;
    border-radius: 12px;
    transition: all 0.3s;
}

.ranking-item:hover {
    background: #f0f0f0;
    transform: translateX(5px);
}

.ranking-item.current-user {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
    border: 2px solid #667eea;
}

.ranking-position {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3em;
    font-weight: bold;
    color: #667eea;
}

.ranking-position.top3 {
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
    color: white;
    border-radius: 50%;
}

.ranking-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3em;
    font-weight: bold;
}

.ranking-player-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.ranking-player-name {
    font-size: 1.2em;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 5px;
}

.ranking-player-record {
    font-size: 0.95em;
    color: #666;
}

.ranking-stats {
    display: flex;
    gap: 20px;
    align-items: center;
}

.ranking-level {
    font-size: 1em;
    color: #666;
}

.ranking-points {
    font-size: 1.2em;
    font-weight: bold;
    color: #667eea;
}

.loading {
    text-align: center;
    padding: 40px;
    color: #999;
    font-size: 1.2em;
}

.no-rankings {
    text-align: center;
    padding: 60px 20px;
    color: #999;
    font-size: 1.2em;
    font-style: italic;
}

@media (max-width: 768px) {
    .rankings-header h1 {
        font-size: 1.8em;
    }

    .division-tabs {
        flex-wrap: nowrap;
        overflow-x: scroll;
    }

    .division-tab {
        min-width: 100px;
        font-size: 1em;
        padding: 12px 20px;
    }

    .my-rank-card {
        flex-direction: column;
        text-align: center;
    }

    .rank-stats {
        align-items: center;
    }

    .ranking-item {
        flex-wrap: wrap;
    }
}
</style>

<script>
const divisionInfo = {
    bronze: { title: 'DIVISION BRONZE', description: '0-99 points' },
    argent: { title: 'DIVISION ARGENT', description: '100-199 points' },
    or: { title: 'DIVISION OR', description: '200-299 points' },
    platine: { title: 'DIVISION PLATINE', description: '300-399 points' },
    diamant: { title: 'DIVISION DIAMANT', description: '400-499 points' },
    legende: { title: 'DIVISION LÉGENDE', description: '500+ points' }
};

let currentDivision = '{{ $myDivision->division ?? "bronze" }}';

async function loadRankings(division) {
    const listContainer = document.getElementById('rankingsList');
    listContainer.innerHTML = '<div class="loading">Chargement...</div>';

    try {
        const response = await fetch(`/api/league/individual/rankings?division=${division}`, {
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.success && data.rankings.length > 0) {
            displayRankings(data.rankings);
        } else {
            listContainer.innerHTML = '<div class="no-rankings">Aucun joueur dans cette division</div>';
        }
    } catch (error) {
        console.error('Error loading rankings:', error);
        listContainer.innerHTML = '<div class="no-rankings">Erreur de chargement</div>';
    }
}

function displayRankings(rankings) {
    const listContainer = document.getElementById('rankingsList');
    const currentUserId = {{ Auth::id() }};

    listContainer.innerHTML = rankings.map(ranking => {
        const isCurrentUser = ranking.user.id === currentUserId;
        const isTop3 = ranking.rank <= 3;

        return `
            <div class="ranking-item ${isCurrentUser ? 'current-user' : ''}">
                <div class="ranking-position ${isTop3 ? 'top3' : ''}">
                    ${isTop3 ? '🏆' : '#' + ranking.rank}
                </div>
                <div class="ranking-avatar">
                    ${ranking.user.name.charAt(0).toUpperCase()}
                </div>
                <div class="ranking-player-info">
                    <div class="ranking-player-name">${ranking.user.name}</div>
                    <div class="ranking-player-record">
                        ${ranking.stats?.matches_won || 0}V - ${ranking.stats?.matches_lost || 0}D
                    </div>
                </div>
                <div class="ranking-stats">
                    <span class="ranking-level">Niv. ${ranking.level}</span>
                    <span class="ranking-points">${ranking.points} pts</span>
                </div>
            </div>
        `;
    }).join('');
}

document.querySelectorAll('.division-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.division-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const division = this.dataset.division;
        currentDivision = division;

        document.getElementById('divisionTitle').textContent = divisionInfo[division].title;
        document.getElementById('divisionDescription').textContent = divisionInfo[division].description;

        loadRankings(division);
    });
});

loadRankings(currentDivision);
</script>
@endsection
