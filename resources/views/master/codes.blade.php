@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.codes-container {
    max-width: 720px;
    margin: 0 auto;
    padding: 1rem;
}

.codes-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 0.4rem;
    text-align: center;
    color: #FFD700;
}

.codes-subtitle {
    font-size: 0.95rem;
    text-align: center;
    opacity: 0.85;
    margin-bottom: 1.6rem;
    line-height: 1.4;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #FFD700;
}

.code-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.code-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.8rem;
}

.code-display {
    flex: 1;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 215, 0, 0.5);
    border-radius: 10px;
    padding: 0.9rem;
    font-size: 1.6rem;
    font-weight: 900;
    text-align: center;
    color: #FFD700;
    letter-spacing: 0.2rem;
}

.btn-carnet {
    background: rgba(255, 255, 255, 0.18);
    border: 2px solid rgba(255, 215, 0, 0.5);
    color: #FFD700;
    border-radius: 10px;
    padding: 0.7rem 1rem;
    font-size: 1.4rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-carnet:hover {
    background: rgba(255, 215, 0, 0.25);
    color: #FFF;
}

.btn-carnet small {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.05rem;
}

.specs-grid {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem 1rem;
}

.spec-item {
    font-size: 0.88rem;
    line-height: 1.35;
}

.spec-label {
    opacity: 0.65;
    font-weight: 600;
    margin-right: 0.35rem;
}

.spec-value {
    color: #FFD700;
    font-weight: 700;
}

.btn-start {
    background: linear-gradient(135deg, #00D400, #00A000);
    color: white;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-size: 1.2rem;
    font-weight: 800;
    border: none;
    cursor: pointer;
    transition: all 0.25s ease;
    display: block;
    width: 100%;
    margin-top: 1.2rem;
    text-align: center;
    text-decoration: none;
}

.btn-start:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 212, 0, 0.4);
    color: #fff;
}

.empty-quiz {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 2rem 1rem;
    text-align: center;
    opacity: 0.85;
    margin-bottom: 1.5rem;
}

.empty-quiz a {
    color: #FFD700;
    text-decoration: underline;
    font-weight: 700;
}

.history-list {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.history-card {
    background: rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    padding: 0.9rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    transition: background 0.2s ease;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}

.history-card:hover {
    background: rgba(255, 255, 255, 0.13);
    color: #fff;
    text-decoration: none;
}

.history-info {
    flex: 1;
    min-width: 0;
}

.history-name {
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: #FFD700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.history-meta {
    font-size: 0.8rem;
    opacity: 0.75;
    line-height: 1.35;
}

.history-actions {
    display: flex;
    gap: 0.4rem;
    align-items: center;
}

.history-action-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #fff;
    width: 38px;
    height: 38px;
    border-radius: 8px;
    font-size: 1.1rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    transition: background 0.2s ease;
}

.history-action-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.history-action-btn.action-promote:hover {
    background: rgba(255, 215, 0, 0.45);
    color: #003DA5;
}

.history-action-btn.action-delete:hover {
    background: rgba(255, 80, 80, 0.6);
}

.history-action-form {
    margin: 0;
    display: inline;
}

.empty-history {
    text-align: center;
    opacity: 0.6;
    padding: 1.5rem 0;
    font-size: 0.95rem;
}

.flash-success {
    background: rgba(0, 212, 0, 0.2);
    border: 1px solid rgba(0, 212, 0, 0.5);
    color: #B6F8B6;
    padding: 0.7rem 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    text-align: center;
    font-weight: 600;
}

.header-back {
    position: absolute;
    top: 20px;
    left: 20px;
    background: white;
    color: #003DA5;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.95rem;
}

@media (max-width: 768px) {
    .header-back { top: 10px; left: 10px; padding: 6px 12px; font-size: 0.9rem; }
    .specs-grid { grid-template-columns: 1fr; }
    .codes-title { font-size: 1.5rem; }
}
</style>

@php
    // Helpers d'affichage humain (lecture seule)
    $modeLabels = [
        'face_to_face' => __('Face à face'),
        'one_vs_all' => __('Un contre tous'),
        'podium' => __('Podium'),
        'groups' => __('Groupes'),
    ];
    $domainLabels = [
        'theme' => __('Thème libre'),
        'school' => __('Scolaire'),
        'scolaire' => __('Scolaire'),
    ];
    $questionTypeLabels = [
        'multiple_choice' => __('Choix multiple'),
        'true_false' => __('Vrai / Faux'),
        'image' => __('Image'),
    ];
    $tiebreakerLabels = [
        'bonus' => __('Bonus'),
        'sudden_death' => __('Mort subite'),
        'efficiency' => __('Efficacité'),
        'last_chance' => __('Dernière chance'),
    ];
    $formatTypes = function ($types) use ($questionTypeLabels) {
        if (empty($types)) return '—';
        $arr = is_array($types) ? $types : (array) $types;
        return implode(', ', array_map(fn($t) => $questionTypeLabels[$t] ?? $t, $arr));
    };
    $formatLanguages = function ($langs) {
        if (empty($langs)) return '—';
        $arr = is_array($langs) ? $langs : (array) $langs;
        return implode(', ', array_map('strtoupper', $arr));
    };
@endphp

<a href="{{ route('master.create') }}" class="header-back">{{ __('Retour') }}</a>

<div class="codes-container">
    <h1 class="codes-title">{{ __('Vos Quiz') }}</h1>
    <div class="codes-subtitle">
        {{ __('Sélectionne un quiz enregistré, vérifie ses paramètres, choisis les joueurs, puis démarre la partie.') }}
    </div>

    @if(session('success'))
        <div class="flash-success">{{ session('success') }}</div>
    @endif

    {{-- Bloc Quiz Sélectionné --}}
    <div class="section-title">{{ __('Quiz Sélectionné') }}</div>
    @if($game)
        <div class="code-section">
            <div class="code-row">
                <div class="code-display" title="{{ __('Code du quiz') }}">{{ $game->game_code }}</div>
                <a href="{{ route('master.invite', $game->id) }}"
                   class="btn-carnet"
                   title="{{ __('Carnet — sélectionner les joueurs') }}">
                    📒 <small>{{ __('Carnet') }}</small>
                </a>
            </div>

            <div class="specs-grid">
                <div class="spec-item">
                    <span class="spec-label">{{ __('Nom du quiz') }} :</span>
                    <span class="spec-value">{{ $game->name }}</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">{{ __('Langue du quiz') }} :</span>
                    <span class="spec-value">{{ $formatLanguages($game->languages) }}</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">{{ __('Nombre maximal de joueurs') }} :</span>
                    <span class="spec-value">{{ $game->participants_expected }}</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">{{ __('Nombre de questions') }} :</span>
                    <span class="spec-value">{{ $game->total_questions }}</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">{{ __('Domaine') }} :</span>
                    <span class="spec-value">
                        {{ $domainLabels[$game->domain_type] ?? $game->domain_type }}@if($game->domain_type === 'theme' && $game->theme) — {{ $game->theme }}@endif
                    </span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">{{ __('Types de questions') }} :</span>
                    <span class="spec-value">{{ $formatTypes($game->question_types) }}</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">{{ __('Mode du quiz') }} :</span>
                    <span class="spec-value">{{ $modeLabels[$game->mode] ?? $game->mode }}</span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">{{ __('Avatars Stratégiques') }} :</span>
                    <span class="spec-value">
                        {{ $game->strategic_avatars_enabled ? __('Activés') : __('Désactivés') }}
                    </span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">{{ __('Ambiance visuelle') }} :</span>
                    <span class="spec-value">
                        {{ $game->gameplay_ambiance_enabled ? ($game->ambiance_music_id ?: __('Activée')) : __('Désactivée') }}
                    </span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">{{ __('Ambiance sonore') }} :</span>
                    <span class="spec-value">{{ $game->buzzer_sound_id ?: __('Par défaut') }}</span>
                </div>
                <div class="spec-item" style="grid-column: 1 / -1;">
                    <span class="spec-label">{{ __('Manche Ultime') }} :</span>
                    <span class="spec-value">{{ $tiebreakerLabels[$game->tiebreaker_mode] ?? $game->tiebreaker_mode }}</span>
                </div>
            </div>
        </div>

        <a href="{{ route('master.lobby', $game->id) }}" class="btn-start">
            ▶ {{ __('Démarrer le Quiz') }}
        </a>
    @else
        <div class="empty-quiz">
            {{ __('Aucun quiz sélectionné.') }}<br>
            <a href="{{ route('master.create') }}">{{ __('Créer un nouveau quiz') }}</a>
            {{ __('ou choisis-en un dans l\'historique ci-dessous.') }}
        </div>
    @endif

    {{-- Historique : Quiz enregistrés --}}
    <div class="section-title" style="margin-top: 2rem;">{{ __('Quiz enregistrés') }}</div>
    <div class="code-section">
        @if($history->count() > 0)
            <div class="history-list">
                @foreach($history as $oldGame)
                    <a href="{{ route('master.preview', $oldGame->id) }}" class="history-card" title="{{ __('Cliquez pour voir le détail') }}">
                        <div class="history-info">
                            <div class="history-name">{{ $oldGame->name }}</div>
                            <div class="history-meta">
                                {{ __('Domaine') }} : {{ $domainLabels[$oldGame->domain_type] ?? $oldGame->domain_type }} •
                                {{ $oldGame->total_questions }} {{ __('questions') }} •
                                {{ $formatLanguages($oldGame->languages) }}<br>
                                {{ __('Date d\'enregistrement') }} : {{ optional($oldGame->created_at)->format('d/m/Y H:i') }}
                            </div>
                        </div>
                        <div class="history-actions" onclick="event.stopPropagation();">
                            <form method="POST"
                                  action="{{ route('master.select', $oldGame->id) }}"
                                  class="history-action-form"
                                  onclick="event.stopPropagation();">
                                @csrf
                                <button type="submit"
                                        class="history-action-btn action-promote"
                                        title="{{ __('Pousser ce quiz dans la sélection') }}"
                                        onclick="event.stopPropagation();">
                                    ⏫
                                </button>
                            </form>
                            <form method="POST"
                                  action="{{ route('master.destroy', $oldGame->id) }}"
                                  class="history-action-form"
                                  onclick="event.stopPropagation();"
                                  onsubmit="event.stopPropagation(); return confirm('{{ __('Supprimer ce quiz enregistré ?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="history-action-btn action-delete"
                                        title="{{ __('Supprimer ce quiz') }}"
                                        onclick="event.stopPropagation();">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="empty-history">
                {{ __('Aucun quiz enregistré') }}
            </div>
        @endif
    </div>
</div>
@endsection
