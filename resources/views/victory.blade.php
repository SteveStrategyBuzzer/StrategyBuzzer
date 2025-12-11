@extends('layouts.app')

@section('title', __('Victoire') . ' - StrategyBuzzer')

@section('content')
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .victory-container {
        background: rgba(255, 255, 255, 0.98);
        border-radius: 25px;
        padding: 40px;
        max-width: 600px;
        width: 100%;
        text-align: center;
        box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
    }
    
    .trophy-icon {
        font-size: 5rem;
        margin-bottom: 20px;
        animation: bounce 1s ease infinite;
    }
    
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-20px); }
    }
    
    .victory-title {
        font-size: 3rem;
        font-weight: 900;
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 15px;
    }
    
    .level-up {
        font-size: 1.8rem;
        color: #333;
        margin-bottom: 30px;
        font-weight: 700;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin: 30px 0;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
    }
    
    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 900;
    }
    
    .challenge-section {
        background: #f7f9fc;
        padding: 30px;
        border-radius: 20px;
        margin: 30px 0;
    }
    
    .challenge-title {
        font-size: 1.5rem;
        color: #333;
        margin-bottom: 20px;
        font-weight: 700;
    }
    
    .opponent-name {
        font-size: 2.5rem;
        color: #667eea;
        font-weight: 900;
        margin: 15px 0;
        word-wrap: break-word;
        overflow-wrap: break-word;
        max-width: 100%;
        padding: 0 10px;
    }
    
    .action-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        margin-top: 30px;
    }
    
    .btn {
        padding: 18px 45px;
        font-size: 1.2rem;
        font-weight: 700;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-yes {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
    }
    
    .btn-yes:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 40px rgba(17, 153, 142, 0.4);
    }
    
    .btn-no {
        background: #e0e0e0;
        color: #333;
    }
    
    .btn-no:hover {
        background: #d0d0d0;
        transform: translateY(-2px);
    }
    
    @media (max-width: 600px) {
        .victory-title {
            font-size: 2.2rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
        
        .opponent-name {
            font-size: 1.8rem;
        }
    }
</style>

<div class="victory-container">
    <div class="trophy-icon">🏆</div>
    
    <h1 class="victory-title">{{ __('VICTOIRE') }} !</h1>
    
    <p class="level-up">
        {{ __('Niveau') }} {{ $params['current_level'] }} {{ __('complété') }} !<br>
        <strong>{{ __('Niveau') }} {{ $params['new_level'] }} {{ __('débloqué') }} 🎉</strong>
    </p>
    
    @if(isset($params['coins_earned']) && $params['coins_earned'] > 0)
    <div style="background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%); padding: 20px; border-radius: 15px; margin: 20px 0; box-shadow: 0 5px 20px rgba(247, 183, 51, 0.4);">
        <div style="display: flex; align-items: center; justify-content: center; gap: 15px;">
            <span style="font-size: 2.5rem;">🪙</span>
            <div style="text-align: left;">
                <div style="color: white; font-size: 0.9rem; opacity: 0.9;">{{ __('Pièces d\'intelligence gagnées') }}</div>
                <div style="color: white; font-size: 2rem; font-weight: 900;">
                    +{{ $params['coins_earned'] }}
                    @if($params['has_stratege_bonus'] ?? false)
                    <span style="font-size: 1rem; background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 20px; margin-left: 10px;">
                        {{ __('Stratège') }} +20%
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
    
    @if(!empty($params['round_summaries']))
    <div style="background: rgba(46, 204, 113, 0.1); padding: 20px; border-radius: 15px; margin: 25px 0;">
        <div style="font-size: 1.3rem; font-weight: 700; color: #333; margin-bottom: 15px;">📊 {{ __('Statistiques par Manche') }}</div>
        
        @foreach($params['round_summaries'] as $roundNum => $roundStats)
        <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid #11998e;">
            <div style="font-weight: 700; color: #11998e; margin-bottom: 10px;">🏆 {{ __('Manche') }} {{ $roundNum }}</div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 0.9rem;">
                <div>
                    <span style="color: #666;">✅ {{ __('Réussi') }}:</span>
                    <strong style="color: #2ECC71;">{{ $roundStats['correct'] ?? 0 }}/{{ $roundStats['questions'] ?? 0 }}</strong>
                </div>
                <div>
                    <span style="color: #666;">❌ {{ __('Échec') }}:</span>
                    <strong style="color: #E74C3C;">{{ $roundStats['wrong'] ?? 0 }}/{{ $roundStats['questions'] ?? 0 }}</strong>
                </div>
                <div>
                    <span style="color: #666;">⏭️ {{ __('Sans réponse') }}:</span>
                    <strong style="color: #95a5a6;">{{ $roundStats['unanswered'] ?? 0 }}/{{ $roundStats['questions'] ?? 0 }}</strong>
                </div>
                <div>
                    <span style="color: #666;">📈 {{ __('Efficacité') }}:</span>
                    <strong style="color: #11998e;">{{ number_format($roundStats['efficiency'] ?? 0, 1) }}%</strong>
                </div>
                <div style="grid-column: 1 / -1;">
                    @php
                        $basePoints = $roundStats['points_earned'] ?? 0;
                        $bonusPoints = $roundStats['bonus_points'] ?? 0;
                        $pointsPossible = $roundStats['points_possible'] ?? 20;
                    @endphp
                    <span style="color: #666;">🎯 {{ __('Points Gagnés') }}:</span>
                    <strong style="color: #333;">{{ $basePoints }}</strong>@if($bonusPoints != 0)<strong style="color: {{ $bonusPoints > 0 ? '#2ECC71' : '#E74C3C' }}"> {{ $bonusPoints > 0 ? '+' : '' }}{{ $bonusPoints }}</strong>@endif / {{ $pointsPossible }}
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">{{ __('Réussi') }}</div>
            <div class="stat-value">{{ $params['total_correct'] }}</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">{{ __('Efficacité du Match') }}</div>
            <div class="stat-value">{{ number_format($params['party_efficiency'] ?? $params['global_efficiency'] ?? 0, 1) }}%</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">{{ __('Échec') }}</div>
            <div class="stat-value">{{ $params['total_incorrect'] }}</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">{{ __('Sans réponse') }}</div>
            <div class="stat-value">{{ $params['total_unanswered'] }}</div>
        </div>
    </div>
    
    @php
        $avatar = session('avatar', 'Aucun');
        $bonusResult = session('bonus_question_result', null);
        $cancelErrorUsed = in_array('cancel_error', session('used_skills', []));
    @endphp
    
    @if($avatar === 'Magicienne')
    <div style="background: rgba(155, 89, 182, 0.1); padding: 20px; border-radius: 15px; margin: 20px 0;">
        <div style="font-size: 1.2rem; font-weight: 700; color: #333; margin-bottom: 15px;">✨ {{ __('Skills utilisés') }}</div>
        
        @if($bonusResult)
            <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #666;">💫 {{ __('Question Bonus') }}</span>
                    @if($bonusResult['answered'])
                        <strong style="color: {{ $bonusResult['points'] > 0 ? '#2ECC71' : ($bonusResult['points'] < 0 ? '#E74C3C' : '#95a5a6') }}; font-size: 1.2rem;">
                            {{ $bonusResult['points'] > 0 ? '+' : '' }}{{ $bonusResult['points'] }} {{ __('points') }}
                        </strong>
                    @else
                        <strong style="color: #95a5a6; font-size: 1.2rem;">0 {{ __('point') }}</strong>
                    @endif
                </div>
            </div>
        @endif
        
        @if($cancelErrorUsed)
            <div style="background: white; padding: 15px; border-radius: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #666;">✨ {{ __('Annule erreur') }}</span>
                    <strong style="color: #2ECC71; font-size: 1.2rem;">{{ __('Utilisé') }}</strong>
                </div>
            </div>
        @endif
        
        @if(!$bonusResult && !$cancelErrorUsed)
            <div style="background: white; padding: 15px; border-radius: 10px; text-align: center; color: #95a5a6;">
                {{ __('Aucun') }}
            </div>
        @endif
    </div>
    @endif
    
    @if($params['new_level'] <= 100)
    @php
        $unlocks = [];
        if ($params['new_level'] == 10) {
            $unlocks[] = __('Mode Duo');
        }
    @endphp
    
    @if(count($unlocks) > 0)
    <div class="challenge-section" style="background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%); padding: 20px; border-radius: 15px; margin: 20px 0;">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 10px;">🎉 {{ __('Félicitation') }} !</h2>
        <p style="color: white; font-size: 1.2rem;">
            @foreach($unlocks as $index => $unlock)
                @if($index > 0), @endif
                {{ __('Vous avez débloqué le') }} <strong>{{ $unlock }}</strong>
            @endforeach
        </p>
    </div>
    @endif
    
    <div class="challenge-section">
        <h2 class="challenge-title">{{ __('Prochain adversaire') }}</h2>
        <div class="opponent-name">{{ $params['next_opponent_name'] }}</div>
        <p style="color: #666; font-size: 1.1rem;">{{ __('Niveau') }} {{ $params['new_level'] }}</p>
    </div>
    
    <div class="action-buttons">
        <form action="{{ route('solo.start') }}" method="POST" style="display: inline;">
            @csrf
            <input type="hidden" name="nb_questions" value="{{ session('nb_questions', 30) }}">
            <input type="hidden" name="theme" value="{{ session('theme', 'general') }}">
            <input type="hidden" name="niveau_joueur" value="{{ $params['new_level'] }}">
            <button type="submit" class="btn btn-yes">{{ __('OUI') }} ⚔️</button>
        </form>
        
        <a href="{{ route('solo.index') }}" class="btn btn-no">{{ __('NON') }}</a>
    </div>
    @else
    <div class="challenge-section">
        <h2 class="challenge-title">🎊 {{ __('FÉLICITATIONS') }} ! 🎊</h2>
        <p style="color: #333; font-size: 1.2rem; margin: 20px 0;">
            {{ __('Vous avez atteint le niveau maximum') }} !<br>
            {{ __('Vous êtes un maître absolu de StrategyBuzzer') }} !
        </p>
    </div>
    
    <div class="action-buttons">
        <a href="{{ route('solo.index') }}" class="btn btn-yes">← {{ __('Retour Solo') }}</a>
    </div>
    @endif
</div>

@if($params['duo_full_unlocked'] ?? false)
<div id="duoUnlockPopup" class="duo-unlock-popup">
    <div class="duo-unlock-content">
        <div class="duo-unlock-icon">🎉</div>
        <h2>{{ __('DUO COMPLET DÉBLOQUÉ') }} !</h2>
        <p>{{ __('Matchmaking et statistiques maintenant disponibles') }}</p>
    </div>
</div>

<style>
.duo-unlock-popup {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease-out;
}

.duo-unlock-content {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 40px 60px;
    border-radius: 25px;
    text-align: center;
    color: white;
    box-shadow: 0 25px 70px rgba(102, 126, 234, 0.5);
    animation: scaleIn 0.4s ease-out;
}

.duo-unlock-icon {
    font-size: 5rem;
    margin-bottom: 20px;
    animation: bounce 0.6s ease infinite;
}

.duo-unlock-content h2 {
    font-size: 2rem;
    font-weight: 900;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.duo-unlock-content p {
    font-size: 1.1rem;
    opacity: 0.9;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes scaleIn {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>

<script>
setTimeout(function() {
    const popup = document.getElementById('duoUnlockPopup');
    if (popup) {
        popup.style.animation = 'fadeIn 0.3s ease-out reverse';
        setTimeout(() => popup.remove(), 300);
    }
}, 2000);
</script>
@endif

<audio id="gameplayAmbient" preload="auto" loop>
    <source src="{{ asset('sounds/gameplay_ambient.mp3') }}" type="audio/mpeg">
</audio>

<script>
function isGameplayMusicEnabled() {
    const enabled = localStorage.getItem('gameplay_music_enabled');
    return enabled === null || enabled === 'true';
}

const gameplayAmbient = document.getElementById('gameplayAmbient');
gameplayAmbient.volume = 0.5;

if (isGameplayMusicEnabled()) {
    const savedTime = parseFloat(localStorage.getItem('gameplayMusicTime') || '0');
    gameplayAmbient.addEventListener('loadedmetadata', function() {
        if (savedTime > 0 && savedTime < gameplayAmbient.duration) {
            gameplayAmbient.currentTime = savedTime;
        }
        
        gameplayAmbient.play().catch(e => {
            console.log('Gameplay music autoplay blocked:', e);
            document.addEventListener('click', function playGameplayMusic() {
                gameplayAmbient.play().catch(err => console.log('Audio play failed:', err));
                document.removeEventListener('click', playGameplayMusic);
            }, { once: true });
        });
    });
}

setTimeout(() => {
    localStorage.removeItem('gameplayMusicTime');
}, 5000);
</script>
@endsection
