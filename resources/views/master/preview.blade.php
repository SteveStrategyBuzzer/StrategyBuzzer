@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.preview-container {
    max-width: 820px;
    margin: 0 auto;
    padding: 1rem;
}

.preview-title {
    font-size: 1.7rem;
    font-weight: 900;
    margin-bottom: 0.4rem;
    text-align: center;
    color: #FFD700;
}

.preview-subtitle {
    font-size: 0.95rem;
    text-align: center;
    opacity: 0.85;
    margin-bottom: 1.5rem;
    line-height: 1.4;
}

.section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.4rem;
    margin-bottom: 1.4rem;
}

.section-title {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #FFD700;
}

.specs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.55rem 1rem;
}

.spec-item {
    font-size: 0.9rem;
    line-height: 1.4;
}

.spec-label {
    opacity: 0.7;
    font-weight: 600;
    margin-right: 0.35rem;
}

.spec-value {
    color: #FFD700;
    font-weight: 700;
}

.question-card {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 0.8rem;
    border-left: 3px solid #FFD700;
}

.question-card.is-tiebreaker {
    border-left-color: #FF6B6B;
    background: rgba(255, 107, 107, 0.1);
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.4rem;
}

.question-number {
    font-weight: 700;
    color: #FFD700;
    font-size: 0.95rem;
}

.question-tag {
    font-size: 0.72rem;
    background: rgba(255, 215, 0, 0.25);
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.tiebreaker-tag {
    background: rgba(255, 107, 107, 0.35);
    color: #FFE0E0;
}

.question-text {
    font-size: 0.98rem;
    margin-bottom: 0.6rem;
    line-height: 1.4;
}

.question-image {
    max-width: 200px;
    max-height: 130px;
    border-radius: 8px;
    margin: 0.3rem 0 0.6rem;
    display: block;
}

.answers-list {
    list-style: none;
    padding: 0;
    margin: 0.4rem 0 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.35rem 0.8rem;
}

.answer-item {
    font-size: 0.88rem;
    padding: 0.4rem 0.55rem;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 6px;
    line-height: 1.3;
}

.answer-item.is-correct {
    background: rgba(0, 212, 0, 0.25);
    color: #B6F8B6;
    font-weight: 700;
    border-left: 3px solid #00D400;
}

.empty-state {
    text-align: center;
    opacity: 0.6;
    padding: 1.5rem 0;
}

.action-row {
    display: flex;
    gap: 0.7rem;
    flex-wrap: wrap;
    margin-top: 1.4rem;
}

.btn-action {
    flex: 1;
    min-width: 180px;
    padding: 0.9rem 1rem;
    border-radius: 10px;
    font-weight: 800;
    font-size: 1rem;
    border: none;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.btn-promote {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
}

.btn-back-action {
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
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
    .answers-list { grid-template-columns: 1fr; }
    .preview-title { font-size: 1.4rem; }
}
</style>

@php
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

<a href="{{ route('master.codes') }}" class="header-back">{{ __('Retour') }}</a>

<div class="preview-container">
    <h1 class="preview-title">{{ __('Aperçu du Quiz') }}</h1>
    <div class="preview-subtitle">
        {{ __('Lecture seule — détails complets et liste des questions enregistrées.') }}
    </div>

    {{-- Pedigree --}}
    <div class="section">
        <div class="section-title">{{ __('Détails du quiz') }}</div>
        <div class="specs-grid">
            <div class="spec-item">
                <span class="spec-label">{{ __('Nom du quiz') }} :</span>
                <span class="spec-value">{{ $game->name }}</span>
            </div>
            <div class="spec-item">
                <span class="spec-label">{{ __('Code du quiz') }} :</span>
                <span class="spec-value">{{ $game->game_code }}</span>
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
                <span class="spec-value">{{ $game->strategic_avatars_enabled ? __('Activés') : __('Désactivés') }}</span>
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
            <div class="spec-item">
                <span class="spec-label">{{ __('Manche Ultime') }} :</span>
                <span class="spec-value">{{ $tiebreakerLabels[$game->tiebreaker_mode] ?? $game->tiebreaker_mode }}</span>
            </div>
            <div class="spec-item" style="grid-column: 1 / -1;">
                <span class="spec-label">{{ __('Date d\'enregistrement') }} :</span>
                <span class="spec-value">{{ optional($game->created_at)->format('d/m/Y H:i') }}</span>
            </div>
        </div>
    </div>

    {{-- Questions --}}
    <div class="section">
        <div class="section-title">{{ __('Questions enregistrées') }} ({{ $game->questionsOrdered->count() }})</div>
        @if($game->questionsOrdered->count() > 0)
            @foreach($game->questionsOrdered as $q)
                <div class="question-card {{ $q->is_tiebreaker ? 'is-tiebreaker' : '' }}">
                    <div class="question-header">
                        <span class="question-number">{{ __('Question') }} #{{ $q->question_number }}</span>
                        <span class="question-tag {{ $q->is_tiebreaker ? 'tiebreaker-tag' : '' }}">
                            @if($q->is_tiebreaker)
                                {{ __('Manche Ultime') }}
                            @else
                                {{ $questionTypeLabels[$q->type] ?? $q->type }}
                            @endif
                        </span>
                    </div>
                    <div class="question-text">{{ $q->text }}</div>
                    @if($q->media_url)
                        <img src="{{ $q->media_url }}" alt="" class="question-image">
                    @endif
                    @php
                        $choices = is_array($q->choices) ? $q->choices : [];
                        $correct = is_array($q->correct_indexes) ? $q->correct_indexes : [];
                    @endphp
                    @if(count($choices) > 0)
                        <ul class="answers-list">
                            @foreach($choices as $i => $choice)
                                <li class="answer-item {{ in_array($i, $correct, true) ? 'is-correct' : '' }}">
                                    {{ chr(65 + $i) }}. {{ $choice }}
                                    @if(in_array($i, $correct, true)) ✓ @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        @else
            <div class="empty-state">{{ __('Aucune question enregistrée pour ce quiz.') }}</div>
        @endif
    </div>

    {{-- Actions --}}
    <div class="action-row">
        <form method="POST" action="{{ route('master.select', $game->id) }}" style="flex: 1; min-width: 180px; margin: 0;">
            @csrf
            <button type="submit" class="btn-action btn-promote" style="width: 100%;">
                ⏫ {{ __('Pousser ce quiz dans la sélection') }}
            </button>
        </form>
        <a href="{{ route('master.codes') }}" class="btn-action btn-back-action">
            {{ __('Retour à Vos Quiz') }}
        </a>
    </div>
</div>
@endsection
