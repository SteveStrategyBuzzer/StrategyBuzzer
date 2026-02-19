@extends('layouts.app')

@section('content')
<style>
* { box-sizing: border-box; }

body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 12px;
    margin: 0;
}

.create-container {
    max-width: 480px;
    margin: 0 auto;
    padding: 0;
}

.create-title {
    font-size: 1.5rem;
    font-weight: 900;
    margin-bottom: 1rem;
    text-align: center;
    color: #FFD700;
}

.form-grid {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0;
}

.section-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 0.6rem;
    color: #FFD700;
    text-align: center;
}

.form-group {
    margin-bottom: 0.8rem;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.4rem;
    font-size: 0.9rem;
}

.form-input {
    width: 100%;
    padding: 0.7rem;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    font-size: 0.95rem;
}

.form-select {
    width: 100%;
    padding: 0.7rem;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    font-size: 0.95rem;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23ffffff' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.8rem center;
    padding-right: 2rem;
}

.form-select option {
    background: #003DA5;
    color: #fff;
    padding: 0.5rem;
}

.checkbox-group, .radio-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
}

.checkbox-label, .radio-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    font-size: 0.9rem;
}

.checkbox-input, .radio-input {
    width: 18px;
    height: 18px;
}

.buttons {
    display: flex;
    gap: 0.8rem;
    justify-content: center;
    margin-top: 1.2rem;
}

.btn-continue {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 0.9rem 1.5rem;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    flex: 1;
}

.btn-continue:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    padding: 0.9rem 1.5rem;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.3);
}

.header-back {
    position: fixed;
    top: 10px;
    left: 10px;
    background: white;
    color: #003DA5;
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    z-index: 100;
}

.header-back:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
}

.input-with-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.input-number {
    width: 55px;
    padding: 0.4rem;
    border-radius: 6px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    font-size: 0.95rem;
    text-align: center;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 24px;
    flex-shrink: 0;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,0.2);
    border-radius: 24px;
    transition: .3s;
}

.toggle-slider:before {
    content: "";
    position: absolute;
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: #fff;
    border-radius: 50%;
    transition: .3s;
}

.toggle-switch input:checked + .toggle-slider {
    background: #4CAF50;
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(22px);
}

.toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.4rem 0;
    gap: 0.8rem;
    font-size: 0.9rem;
}

.tier-checkbox-group {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.6rem;
}

.tier-checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    padding: 0.4rem 0.6rem;
    border-radius: 8px;
    transition: background 0.2s;
    font-size: 0.9rem;
    background: rgba(255,255,255,0.05);
}

.tier-checkbox-label:hover {
    background: rgba(255,255,255,0.1);
}

.tier-checkbox-label input {
    width: 16px;
    height: 16px;
}

.subsection-hidden {
    display: none;
}

.section-full {
    margin-bottom: 0;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    justify-content: center;
    align-items: center;
    padding: 1rem;
}

.modal-content {
    background: linear-gradient(135deg, #003DA5, #0055CC);
    border: 3px solid #FFD700;
    border-radius: 15px;
    padding: 1.5rem;
    max-width: 400px;
    width: 100%;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

.modal-title {
    font-size: 1.3rem;
    font-weight: 900;
    color: #FFD700;
    margin-bottom: 0.8rem;
}

.modal-text {
    font-size: 0.95rem;
    margin-bottom: 1.2rem;
    line-height: 1.5;
}

.modal-btn {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 0.7rem 1.5rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
}

.sound-divider {
    border: none;
    border-top: 1px solid rgba(255,255,255,0.12);
    margin: 0.8rem 0;
}

/* ========== TABLET (min-width: 600px) ========== */
@media (min-width: 600px) {
    body {
        padding: 20px;
    }

    .create-container {
        max-width: 700px;
        padding: 0.5rem;
    }

    .create-title {
        font-size: 1.8rem;
        margin-bottom: 1.2rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.8rem;
    }

    .section-full {
        grid-column: 1 / -1;
    }

    .section {
        padding: 1.2rem;
    }

    .section-title {
        font-size: 1.05rem;
    }

    .form-label, .checkbox-label, .radio-label, .toggle-row, .tier-checkbox-label {
        font-size: 0.95rem;
    }

    .form-input, .form-select {
        padding: 0.8rem;
        font-size: 1rem;
    }

    .btn-continue {
        padding: 1rem 2rem;
        font-size: 1.1rem;
    }

    .header-back {
        top: 15px;
        left: 15px;
        padding: 8px 14px;
        font-size: 0.9rem;
    }

    .modal-content {
        max-width: 450px;
        padding: 2rem;
    }
}

/* ========== DESKTOP / LARGE TABLET (min-width: 900px) ========== */
@media (min-width: 900px) {
    .create-container {
        max-width: 860px;
        padding: 1rem;
    }

    .create-title {
        font-size: 2rem;
        margin-bottom: 1.5rem;
    }

    .form-grid {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .section {
        padding: 1.4rem;
    }

    .section-title {
        font-size: 1.1rem;
    }

    .header-back {
        top: 20px;
        left: 20px;
        padding: 8px 16px;
        font-size: 0.95rem;
    }

    .tier-checkbox-group {
        flex-direction: row;
        gap: 0.5rem;
    }
}

/* ========== LANDSCAPE on small devices ========== */
@media (orientation: landscape) and (max-height: 500px) {
    body {
        padding: 8px;
    }

    .create-container {
        max-width: 95%;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.6rem;
    }

    .section-full {
        grid-column: 1 / -1;
    }

    .section {
        padding: 0.8rem;
        margin-bottom: 0;
    }

    .section-title {
        font-size: 0.9rem;
        margin-bottom: 0.4rem;
    }

    .create-title {
        font-size: 1.3rem;
        margin-bottom: 0.6rem;
    }
}
</style>

<a href="{{ route('menu') }}" class="header-back">Menu</a>

<div class="create-container">
    <h1 class="create-title">Général</h1>
    
    <form action="{{ route('master.store') }}" method="POST" id="createForm">
        @csrf
        
        <!-- Nom du Quiz (pleine largeur) -->
        <div class="section section-full">
            <div class="section-title">Nom du Quizz</div>
            <input type="text" name="name" class="form-input" placeholder="Ex: Quiz du samedi" required>
        </div>
        
        <div class="form-grid">
        <!-- Langue + Participants -->
        <div class="section">
            <div class="form-group">
                <label class="form-label" style="text-align: center;">Langue</label>
                <select name="language" class="form-select" required style="text-align: center; font-weight: 600;">
                    <option value="FR">Français</option>
                    <option value="EN">English</option>
                    <option value="ES">Español</option>
                    <option value="DE">Deutsch</option>
                </select>
            </div>
            <div class="form-group" style="margin-top: 1rem;">
                <div class="input-with-label" style="justify-content: center;">
                    <label>Participants (2-40)</label>
                    <input type="number" name="participants_expected" class="input-number" value="10" min="2" max="40" required>
                    <span>/40</span>
                </div>
            </div>
        </div>
        
        <!-- Questions -->
        <div class="section">
            <div class="section-title">Questions</div>
            
            <div class="form-group">
                <label class="form-label" style="text-align: center;">Nombre</label>
                <div class="radio-group" style="justify-content: center;">
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="10" class="radio-input">
                        <span>010</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="20" class="radio-input" checked>
                        <span>020</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="30" class="radio-input">
                        <span>030</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="40" class="radio-input">
                        <span>040</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 1rem;">
                <label class="form-label" style="text-align: center;">{{ __('Types') }}</label>
                <div class="checkbox-group" style="justify-content: center;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="true_false" class="checkbox-input type-checkbox">
                        <span>{{ __('Vrai/Faux') }}</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="multiple_choice" class="checkbox-input type-checkbox" checked>
                        <span>{{ __('QCM') }}</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="image" class="checkbox-input type-checkbox" id="imageCheckbox">
                        <span>{{ __('Image') }}</span>
                    </label>
                </div>
            </div>
            
            <div id="aiImagesSection" class="form-group" style="display: none; margin-top: 1rem; padding: 1rem; background: rgba(255, 215, 0, 0.15); border-radius: 8px; border: 2px solid rgba(255, 215, 0, 0.4);">
                <label class="form-label" style="text-align: center; color: #FFD700;">
                    🖼️ {{ __('Images générées par IA') }}
                </label>
                <p style="text-align: center; font-size: 0.85rem; margin-bottom: 0.8rem; opacity: 0.9;">
                    {{ __('Questions mémoire visuelle (environ 0.04$/image)') }}
                </p>
                <div class="radio-group" style="justify-content: center;">
                    <label class="radio-label">
                        <input type="radio" name="ai_images_count" value="0" class="radio-input" checked>
                        <span>0</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="ai_images_count" value="1" class="radio-input">
                        <span>1</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="ai_images_count" value="2" class="radio-input">
                        <span>2</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="ai_images_count" value="3" class="radio-input">
                        <span>3</span>
                    </label>
                </div>
                <p id="aiImagesCost" style="text-align: center; font-size: 0.8rem; margin-top: 0.5rem; color: #FFD700; font-weight: 600;">
                    {{ __('Coût estimé') }}: $0.00
                </p>
            </div>
        </div>
        
        <!-- Mode de Jeu -->
        <div class="section">
            <div class="section-title">{{ __('Mode de Jeu') }}</div>
            <select name="mode" class="form-select" style="text-align: center; font-weight: 600;">
                <option value="one_vs_all" selected>{{ __('1 contre Tous') }}</option>
                <option value="face_to_face">{{ __('Face à Face') }}</option>
                <option value="groups">{{ __('En Groupe') }}</option>
                <option value="podium">{{ __('Podium') }}</option>
            </select>
        </div>
        
        <!-- Manche Ultime (Tiebreaker) -->
        <div class="section">
            <div class="section-title">{{ __('Manche Ultime') }}</div>
            <select name="tiebreaker_mode" class="form-select" style="text-align: center; font-weight: 600;">
                <option value="bonus" selected>{{ __('Bonus') }}</option>
                <option value="efficiency">{{ __('Efficacité') }}</option>
                <option value="sudden_death">{{ __('Mort Subite') }}</option>
            </select>
        </div>
        
        <!-- Domaine -->
        <div class="section">
            <div class="section-title">Domaine</div>
            
            <div class="form-group">
                <div class="radio-group" style="justify-content: center; gap: 2rem;">
                    <label class="radio-label">
                        <input type="radio" name="domain_type" value="theme" class="radio-input domain-radio" checked>
                        <span>Thème</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="domain_type" value="scolaire" class="radio-input domain-radio">
                        <span>Scolaire</span>
                    </label>
                </div>
            </div>
            
            <div id="themeSection" class="form-group" style="margin-top: 1rem;">
                <select name="theme" class="form-select" style="text-align: center; font-weight: 600;">
                    <option value="Géographie">Géographie</option>
                    <option value="Histoire">Histoire</option>
                    <option value="Arts et Culture">Arts et Culture</option>
                    <option value="Sciences et Nature">Sciences et Nature</option>
                    <option value="Sports et Loisirs">Sports et Loisirs</option>
                    <option value="Divertissement">Divertissement</option>
                    <option value="Technologie">Technologie</option>
                    <option value="Société">Société</option>
                    <option value="Général">Général</option>
                </select>
            </div>
            
            <div id="scolaireSection" class="form-group" style="display: none; margin-top: 1rem;">
                <select name="school_country" id="schoolCountry" class="form-select" style="text-align: center; font-weight: 600;">
                    <option value="">-- Pays --</option>
                    <option value="Canada">Canada</option>
                    <option value="France">France</option>
                    <option value="USA">États-Unis</option>
                </select>
                
                <select name="school_level" id="schoolLevel" class="form-select" style="margin-top: 0.8rem; text-align: center;">
                    <option value="">-- Niveau --</option>
                </select>
                
                <select name="school_grade" id="schoolGrade" class="form-select" style="margin-top: 0.8rem; text-align: center;">
                    <option value="">-- Année --</option>
                </select>
                
                <select name="school_subject" id="schoolSubject" class="form-select" style="margin-top: 0.8rem; text-align: center;">
                    <option value="">-- Matière --</option>
                </select>
            </div>
        </div>
        
        <!-- Sons -->
        <div class="section section-full">
            <div class="section-title">{{ __('Sons') }}</div>
            
            <div class="toggle-row">
                <span>{{ __('Ambiance Gameplay') }}</span>
                <label class="toggle-switch">
                    <input type="checkbox" name="gameplay_ambiance_enabled" value="1" checked id="gameplayAmbianceToggle">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            
            <div id="ambianceOptions" style="margin-top: 0.8rem;">
                @php
                    $user = Auth::user();
                    $settings = is_array($user->profile_settings) ? $user->profile_settings : json_decode($user->profile_settings ?? '{}', true);
                    $unlockedMusic = $settings['unlocked']['music'] ?? [['id' => 'strategybuzzer', 'label' => 'StrategyBuzzer']];
                    $unlockedMusicIds = collect($unlockedMusic)->pluck('id')->toArray();
                    $allMusic = [
                        'strategybuzzer' => 'StrategyBuzzer',
                        'fun_01' => 'Fun 01',
                        'chill' => 'Chill',
                        'punchy' => 'Punchy',
                    ];
                @endphp
                
                <div class="form-group">
                    <label class="form-label" style="text-align: center;">{{ __('Musique d\'ambiance') }}</label>
                    <select name="ambiance_music_choice" class="form-select" style="text-align: center; font-weight: 600;" id="ambianceMusicChoiceSelect">
                        <option value="master">{{ __('Choix du Maître') }}</option>
                        <option value="player">{{ __('Choix du Joueur') }}</option>
                    </select>
                </div>
                
                <div class="form-group" id="masterMusicSelect" style="margin-top: 0.6rem;">
                    <select name="ambiance_music_id" class="form-select" style="text-align: center;">
                        @foreach($allMusic as $musicId => $musicLabel)
                            @php $isUnlocked = in_array($musicId, $unlockedMusicIds); @endphp
                            <option value="{{ $musicId }}" {{ $musicId === 'strategybuzzer' ? 'selected' : '' }} {{ !$isUnlocked ? 'disabled' : '' }}>
                                {{ $musicLabel }}{{ !$isUnlocked ? ' 🔒' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <hr class="sound-divider">
            
            <div class="form-group">
                <label class="form-label" style="text-align: center;">{{ __('Sons des Buzzers') }}</label>
                <select name="buzzer_sound_choice" class="form-select" style="text-align: center; font-weight: 600;" id="buzzerSoundChoiceSelect">
                    <option value="master">{{ __('Choix du Maître') }}</option>
                    <option value="player">{{ __('Choix du Joueur') }}</option>
                </select>
            </div>
            
            <div class="form-group" id="masterBuzzerSelect" style="margin-top: 0.6rem;">
                <select name="buzzer_sound_id" class="form-select" style="text-align: center;">
                    <option value="default" selected>Buzzer Classique</option>
                    <option value="buzzer_default_1">Buzzer 1</option>
                    <option value="buzzer_default_2">Buzzer 2</option>
                </select>
            </div>
        </div>
        
        <!-- Avatars Stratégiques -->
        <div class="section section-full">
            <div class="section-title">{{ __('Avatars Stratégiques') }}</div>
            
            <div class="toggle-row">
                <span>{{ __('Activer les avatars stratégiques') }}</span>
                <label class="toggle-switch">
                    <input type="checkbox" name="strategic_avatars_enabled" value="1" id="strategicAvatarsToggle">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            
            <div id="strategicAvatarsOptions" class="subsection-hidden">
                <div class="tier-checkbox-group">
                    <label class="tier-checkbox-label">
                        <input type="checkbox" id="tierAll" class="tier-checkbox">
                        <span style="font-weight:700; color:#FFD700;">{{ __('Tous') }}</span>
                    </label>
                    <label class="tier-checkbox-label">
                        <input type="checkbox" name="strategic_avatars_tiers[]" value="Rare" class="tier-checkbox tier-individual" id="tierRare">
                        <span>🎯 {{ __('Rare') }}</span>
                    </label>
                    <label class="tier-checkbox-label">
                        <input type="checkbox" name="strategic_avatars_tiers[]" value="Épique" class="tier-checkbox tier-individual" id="tierEpic">
                        <span>🔮 {{ __('Épique') }}</span>
                    </label>
                    <label class="tier-checkbox-label">
                        <input type="checkbox" name="strategic_avatars_tiers[]" value="Légendaire" class="tier-checkbox tier-individual" id="tierLegendary">
                        <span>👑 {{ __('Légendaire') }}</span>
                    </label>
                </div>
            </div>
        </div>
        </div>
        
        <div class="section-full" style="margin-top: 1rem;">
            <div class="buttons">
                <button type="submit" name="creation_mode" value="automatique" id="automatiqueBtn" class="btn-continue" style="background: linear-gradient(135deg, #FFD700, #FFA500);">
                    {{ __('Automatique') }}
                </button>
                <button type="button" id="personnaliseBtn" class="btn-continue" style="background: linear-gradient(135deg, #00D4FF, #0099CC);">
                    {{ __('Personnalisé') }}
                </button>
            </div>
        </div>
        <input type="hidden" name="creation_mode" id="creationModeInput" value="automatique">
    </form>
</div>

<!-- Modal d'avertissement -->
<div id="imageWarningModal" class="modal">
    <div class="modal-content">
        <div class="modal-title">⚠️ Attention</div>
        <div class="modal-text">
            En mode Automatique, vous devez uploader les images et remplir les questions manuellement pour chaque question de type Image.
        </div>
        <button class="modal-btn" onclick="closeImageModal()">J'ai compris</button>
    </div>
</div>

<script>
// Systèmes éducatifs par pays
const educationSystems = {
    'Canada': {
        levels: {
            'Primaire': { grades: ['1', '2', '3', '4', '5', '6'] },
            'Secondaire': { grades: ['1', '2', '3', '4', '5'] },
            'Cégep': { grades: ['1', '2'] },
            'Universitaire': { grades: ['1', '2', '3', '4'] }
        },
        subjects: ['Mathématiques', 'Français', 'Anglais', 'Sciences', 'Histoire', 'Géographie', 'Éducation physique', 'Arts']
    },
    'France': {
        levels: {
            'Primaire': { grades: ['CP', 'CE1', 'CE2', 'CM1', 'CM2'] },
            'Collège': { grades: ['6ème', '5ème', '4ème', '3ème'] },
            'Lycée': { grades: ['2nde', '1ère', 'Terminale'] },
            'Université': { grades: ['L1', 'L2', 'L3', 'M1', 'M2'] }
        },
        subjects: ['Mathématiques', 'Français', 'Anglais', 'Histoire-Géographie', 'Sciences', 'Physique-Chimie', 'SVT', 'Philosophie', 'Arts']
    },
    'USA': {
        levels: {
            'Elementary': { grades: ['K', '1', '2', '3', '4', '5'] },
            'Middle School': { grades: ['6', '7', '8'] },
            'High School': { grades: ['9', '10', '11', '12'] },
            'College': { grades: ['Freshman', 'Sophomore', 'Junior', 'Senior'] }
        },
        subjects: ['Mathematics', 'English', 'Science', 'History', 'Geography', 'Physical Education', 'Arts', 'Foreign Language']
    }
};

// Domain type logic
const domainRadios = document.querySelectorAll('.domain-radio');
const themeSection = document.getElementById('themeSection');
const scolaireSection = document.getElementById('scolaireSection');

if (domainRadios.length > 0 && themeSection && scolaireSection) {
    domainRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'theme') {
                themeSection.style.display = 'block';
                scolaireSection.style.display = 'none';
            } else if (this.value === 'scolaire') {
                themeSection.style.display = 'none';
                scolaireSection.style.display = 'block';
            }
        });
    });
}

// Educational system cascading selects
const schoolCountry = document.getElementById('schoolCountry');
const schoolLevel = document.getElementById('schoolLevel');
const schoolGrade = document.getElementById('schoolGrade');
const schoolSubject = document.getElementById('schoolSubject');

if (schoolCountry) {
    schoolCountry.addEventListener('change', function() {
        const country = this.value;
        
        // Reset dependent selects
        schoolLevel.innerHTML = '<option value="">-- Niveau --</option>';
        schoolGrade.innerHTML = '<option value="">-- Année --</option>';
        schoolSubject.innerHTML = '<option value="">-- Matière --</option>';
        
        if (country && educationSystems[country]) {
            // Populate levels
            Object.keys(educationSystems[country].levels).forEach(level => {
                const option = document.createElement('option');
                option.value = level;
                option.textContent = level;
                schoolLevel.appendChild(option);
            });
        }
    });
}

if (schoolLevel) {
    schoolLevel.addEventListener('change', function() {
        const country = schoolCountry.value;
        const level = this.value;
        
        // Reset dependent selects
        schoolGrade.innerHTML = '<option value="">-- Année --</option>';
        schoolSubject.innerHTML = '<option value="">-- Matière --</option>';
        
        if (country && level && educationSystems[country] && educationSystems[country].levels[level]) {
            // Populate grades
            educationSystems[country].levels[level].grades.forEach(grade => {
                const option = document.createElement('option');
                option.value = grade;
                option.textContent = grade;
                schoolGrade.appendChild(option);
            });
        }
    });
}

if (schoolGrade) {
    schoolGrade.addEventListener('change', function() {
        const country = schoolCountry.value;
        
        // Reset subjects
        schoolSubject.innerHTML = '<option value="">-- Matière --</option>';
        
        if (country && educationSystems[country]) {
            // Populate subjects
            educationSystems[country].subjects.forEach(subject => {
                const option = document.createElement('option');
                option.value = subject;
                option.textContent = subject;
                schoolSubject.appendChild(option);
            });
        }
    });
}

// Image checkbox warning (only for Automatique mode)
let imageWarningShown = false;
const imageCheckbox = document.getElementById('imageCheckbox');
const imageWarningModal = document.getElementById('imageWarningModal');
const automatiqueBtn = document.getElementById('automatiqueBtn');
const aiImagesSection = document.getElementById('aiImagesSection');
const aiImagesCost = document.getElementById('aiImagesCost');
const aiImagesRadios = document.querySelectorAll('input[name="ai_images_count"]');

// Show/hide AI images section when Image checkbox is toggled
if (imageCheckbox && aiImagesSection) {
    imageCheckbox.addEventListener('change', function() {
        if (this.checked) {
            aiImagesSection.style.display = 'block';
            // Show warning modal only first time
            if (!imageWarningShown && imageWarningModal) {
                imageWarningModal.style.display = 'flex';
                imageWarningShown = true;
            }
        } else {
            aiImagesSection.style.display = 'none';
            // Reset to 0 when unchecked
            document.querySelector('input[name="ai_images_count"][value="0"]').checked = true;
            updateAiImagesCost();
        }
    });
}

// Update cost estimate when AI images count changes
function updateAiImagesCost() {
    const selected = document.querySelector('input[name="ai_images_count"]:checked');
    if (selected && aiImagesCost) {
        const count = parseInt(selected.value);
        const cost = (count * 0.04).toFixed(2);
        aiImagesCost.textContent = `{{ __('Coût estimé') }}: $${cost}`;
    }
}

aiImagesRadios.forEach(radio => {
    radio.addEventListener('change', updateAiImagesCost);
});

function closeImageModal() {
    if (imageWarningModal) {
        imageWarningModal.style.display = 'none';
    }
}

// Close modal on outside click
window.onclick = function(event) {
    if (event.target == imageWarningModal) {
        closeImageModal();
    }
}

// Bouton Automatique - change texte pendant la création
if (automatiqueBtn) {
    automatiqueBtn.addEventListener('click', function(e) {
        // Change button text to show creation is in progress
        this.innerHTML = '<span class="spinner"></span> {{ __("En Création...") }}';
        this.style.pointerEvents = 'none';
        this.style.opacity = '0.8';
        
        // Add spinner animation
        const style = document.createElement('style');
        style.textContent = `
            .spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid #003DA5;
                border-radius: 50%;
                border-top-color: transparent;
                animation: spin 1s linear infinite;
                margin-right: 8px;
                vertical-align: middle;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    });
}

// Bouton Personnalisé - soumet le formulaire avec mode personnalise
const personnaliseBtn = document.getElementById('personnaliseBtn');
if (personnaliseBtn) {
    personnaliseBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Set creation mode to personnalise
        document.getElementById('creationModeInput').value = 'personnalise';
        
        // Change button text
        this.innerHTML = '{{ __("Chargement...") }}';
        this.style.pointerEvents = 'none';
        this.style.opacity = '0.8';
        
        // Submit the form
        document.getElementById('createForm').submit();
    });
}

// === Sound section logic ===
const gameplayAmbianceToggle = document.getElementById('gameplayAmbianceToggle');
const ambianceOptions = document.getElementById('ambianceOptions');

if (gameplayAmbianceToggle && ambianceOptions) {
    gameplayAmbianceToggle.addEventListener('change', function() {
        ambianceOptions.style.display = this.checked ? 'block' : 'none';
    });
}

// Show/hide master music select based on ambiance choice
const ambianceMusicChoiceSelect = document.getElementById('ambianceMusicChoiceSelect');
const masterMusicSelect = document.getElementById('masterMusicSelect');

if (ambianceMusicChoiceSelect && masterMusicSelect) {
    ambianceMusicChoiceSelect.addEventListener('change', function() {
        masterMusicSelect.style.display = this.value === 'master' ? 'block' : 'none';
    });
}

// Show/hide master buzzer select based on buzzer choice
const buzzerSoundChoiceSelect = document.getElementById('buzzerSoundChoiceSelect');
const masterBuzzerSelect = document.getElementById('masterBuzzerSelect');

if (buzzerSoundChoiceSelect && masterBuzzerSelect) {
    buzzerSoundChoiceSelect.addEventListener('change', function() {
        masterBuzzerSelect.style.display = this.value === 'master' ? 'block' : 'none';
    });
}

// === Strategic Avatars section logic ===
const strategicAvatarsToggle = document.getElementById('strategicAvatarsToggle');
const strategicAvatarsOptions = document.getElementById('strategicAvatarsOptions');

if (strategicAvatarsToggle && strategicAvatarsOptions) {
    strategicAvatarsToggle.addEventListener('change', function() {
        if (this.checked) {
            strategicAvatarsOptions.classList.remove('subsection-hidden');
        } else {
            strategicAvatarsOptions.classList.add('subsection-hidden');
        }
    });
}

// Tier checkbox logic: Tous ↔ individual tiers
const tierAll = document.getElementById('tierAll');
const tierIndividuals = document.querySelectorAll('.tier-individual');

if (tierAll) {
    tierAll.addEventListener('change', function() {
        tierIndividuals.forEach(cb => { cb.checked = this.checked; });
    });
}

tierIndividuals.forEach(cb => {
    cb.addEventListener('change', function() {
        const allChecked = Array.from(tierIndividuals).every(c => c.checked);
        tierAll.checked = allChecked;
    });
});
</script>
@endsection
