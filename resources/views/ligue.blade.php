@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    text-align: center;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    padding: 20px;
}
.header-menu {
    position: absolute;
    top: 20px;
    right: 20px;
}
.ligue-container {
    max-width: 900px;
    width: 100%;
    margin-top: 60px;
}
.ligue-title {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 1rem;
    text-transform: uppercase;
}
.ligue-subtitle {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}
.ligue-modes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

@media (max-width: 768px) {
    html, body {
        overflow-x: hidden;
        max-width: 100vw;
    }
    body {
        padding: 10px;
    }
    .ligue-container {
        padding: 0.5rem;
        max-width: 100%;
        width: 100%;
        margin-top: 50px;
        box-sizing: border-box;
    }
    .ligue-title {
        font-size: 1.8rem;
        margin-bottom: 0.5rem;
    }
    .ligue-subtitle {
        font-size: 0.95rem;
        margin-bottom: 1rem;
    }
    .ligue-modes {
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-top: 1rem;
        width: 100%;
    }
    .ligue-mode-card {
        padding: 1.2rem;
        width: 100%;
        box-sizing: border-box;
    }
    .mode-icon {
        font-size: 2.5rem;
    }
    .mode-title {
        font-size: 1.3rem;
    }
    .mode-description {
        font-size: 0.85rem;
    }
    .header-menu {
        padding: 8px 14px !important;
        font-size: 0.85rem !important;
        right: 10px !important;
        top: 10px !important;
    }
    .team-section {
        padding: 1rem;
        width: 100%;
        box-sizing: border-box;
    }
    .team-section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    .team-section-title {
        font-size: 1.2rem;
    }
    .team-action-buttons {
        width: 100%;
        justify-content: flex-start;
    }
    .team-card {
        padding: 0.8rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    .team-info {
        width: 100%;
    }
    .team-emblem {
        font-size: 2rem;
        width: 40px;
        height: 40px;
    }
    .team-name {
        font-size: 1rem;
        flex-wrap: wrap;
    }
    .team-actions {
        width: 100%;
        justify-content: flex-end;
    }
    .pending-invitation-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    .invitation-info {
        width: 100%;
        flex-wrap: wrap;
    }
    .invitation-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
.ligue-mode-card {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 16px;
    padding: 2rem;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}
.ligue-mode-card:hover {
    transform: translateY(-8px);
    background: rgba(255, 255, 255, 0.2);
    border-color: white;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}
.mode-icon {
    font-size: 4rem;
}
.mode-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
}
.mode-description {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0;
}
.mode-badge {
    background: rgba(255, 215, 0, 0.2);
    border: 1px solid #FFD700;
    color: #FFD700;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-top: 0.5rem;
}
.team-section {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: left;
}
.team-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.team-section-title {
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.team-action-buttons {
    display: flex;
    gap: 0.5rem;
}
.btn-create-team, .btn-join-team {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}
.btn-create-team {
    background: #FFD700;
    color: #003DA5;
}
.btn-create-team:hover {
    background: #FFC000;
    transform: translateY(-2px);
}
.btn-join-team {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.5);
}
.btn-join-team:hover {
    background: rgba(255,255,255,0.3);
}
.team-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.team-card {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}
.team-card:hover {
    background: rgba(255, 255, 255, 0.25);
}
.team-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.team-emblem {
    font-size: 2.5rem;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.2);
    border-radius: 8px;
}
.team-details {
    text-align: left;
}
.team-name {
    font-weight: 700;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.team-tag {
    background: rgba(255,255,255,0.2);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}
.team-meta {
    font-size: 0.85rem;
    opacity: 0.8;
    margin-top: 0.2rem;
}
.captain-badge {
    background: #FFD700;
    color: #003DA5;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
}
.team-actions {
    display: flex;
    gap: 0.5rem;
}
.btn-team-action {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}
.btn-select {
    background: #4CAF50;
    color: white;
}
.btn-select:hover {
    background: #45a049;
}
.btn-manage {
    background: rgba(255,255,255,0.2);
    color: white;
}
.btn-manage:hover {
    background: rgba(255,255,255,0.3);
}
.empty-state {
    text-align: center;
    padding: 2rem;
    opacity: 0.8;
}
.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 0.5rem;
}
.pending-invitations {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255,255,255,0.2);
}
.pending-invitation-card {
    background: rgba(255, 215, 0, 0.15);
    border: 1px solid rgba(255, 215, 0, 0.4);
    border-radius: 10px;
    padding: 0.8rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.invitation-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.invitation-actions {
    display: flex;
    gap: 0.5rem;
}
.btn-accept {
    background: #4CAF50;
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
}
.btn-decline {
    background: #dc3545;
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
}
</style>

<a href="javascript:history.back()" class="header-menu" style="
  background: white;
  color: #003DA5;
  padding: 10px 20px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 700;
  font-size: 1rem;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(255,255,255,0.3)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
  ← {{ __('Retour') }}
</a>

<div class="ligue-container">
    <h1 class="ligue-title">{{ __('LIGUE') }}</h1>
    <p class="ligue-subtitle">{{ __('Choisissez votre mode de compétition') }}</p>
    
    <div class="ligue-modes">
        <a href="{{ route('league.individual.lobby') }}" class="ligue-mode-card">
            <div class="mode-icon">👤</div>
            <h2 class="mode-title">{{ __('INDIVIDUEL') }}</h2>
            <p class="mode-description">{{ __('Affrontez des adversaires en 1v1 et grimpez dans les divisions') }}</p>
            <div class="mode-badge">{{ __('Carrière Solo') }}</div>
        </a>

        <div class="team-section">
            <div class="team-section-header">
                <div class="team-section-title">
                    👥 {{ __('ÉQUIPE') }}
                </div>
                <div class="team-action-buttons">
                    <a href="{{ route('league.team.create') }}" class="btn-create-team">+ {{ __('Créer') }}</a>
                    <a href="{{ route('league.team.search') }}" class="btn-join-team">{{ __('Rejoindre') }}</a>
                </div>
            </div>
            <p style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 1rem; text-align: left;">
                {{ __('Choisissez l\'équipe avec laquelle vous souhaitez participer aux matchs 5v5.') }}
            </p>

            @if($userTeams->count() > 0)
                <div class="team-list">
                    @foreach($userTeams as $team)
                        <div class="team-card">
                            <div class="team-info">
                                <div class="team-emblem">{{ $team->emblem ?? '🛡️' }}</div>
                                <div class="team-details">
                                    <div class="team-name">
                                        {{ $team->name }}
                                        <span class="team-tag">[{{ $team->tag }}]</span>
                                        @if($team->captain_id === $user->id)
                                            <span class="captain-badge">{{ __('Capitaine') }}</span>
                                        @endif
                                    </div>
                                    <div class="team-meta">
                                        {{ $team->members->count() }}/5 {{ __('joueurs') }} • {{ __('ELO') }}: {{ $team->elo ?? 1000 }}
                                    </div>
                                </div>
                            </div>
                            <div class="team-actions">
                                <a href="{{ route('league.team.management', ['teamId' => $team->id]) }}" class="btn-team-action btn-select">{{ __('Choisir') }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <p>{{ __('Vous n\'appartenez à aucune équipe.') }}</p>
                    <p style="font-size: 0.9rem;">{{ __('Créez votre propre équipe ou rejoignez-en une existante !') }}</p>
                </div>
            @endif

            @if($pendingInvitations->count() > 0)
                <div class="pending-invitations">
                    <p style="font-weight: 600; margin-bottom: 0.5rem;">📩 {{ __('Invitations en attente') }}</p>
                    @foreach($pendingInvitations as $invitation)
                        <div class="pending-invitation-card">
                            <div class="invitation-info">
                                <span>{{ $invitation->team->emblem ?? '🛡️' }}</span>
                                <span><strong>{{ $invitation->team->name }}</strong> ({{ __('par') }} {{ $invitation->team->captain->name ?? 'Inconnu' }})</span>
                            </div>
                            <div class="invitation-actions">
                                <form action="{{ route('league.team.invitation.accept', $invitation->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-accept">{{ __('Accepter') }}</button>
                                </form>
                                <form action="{{ route('league.team.invitation.decline', $invitation->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-decline">{{ __('Refuser') }}</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div style="margin-top: 2rem; opacity: 0.8; font-size: 0.9rem;">
        <p>📊 {{ __('Système de divisions') }}: {{ __('Bronze') }} → {{ __('Argent') }} → {{ __('Or') }} → {{ __('Platine') }} → {{ __('Diamant') }} → {{ __('Légende') }}</p>
    </div>
</div>
@endsection
