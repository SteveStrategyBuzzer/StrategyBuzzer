@extends('layouts.app')

@section('content')
@include('partials.game-context', [
    'mode'           => 'solo',
    'page'           => 'question',
    'totalQuestions' => $totalQuestions ?? 10,
    'playerName'     => auth()->user()->name ?? 'Joueur',
])
<style>
    body {
        background-color: #001F3F;
        color: #fff;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .question {
        background-color: rgba(0,0,0,0.4);
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 30px;
        text-align: center;
        max-width: 800px;
        width: 90%;
    }
    .reponses {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        max-width: 800px;
        width: 90%;
    }
    .reponse-btn {
        padding: 15px;
        background-color: #0074D9;
        border: none;
        color: #fff;
        font-size: 1.2rem;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    .reponse-btn:hover {
        background-color: #005fa3;
    }
    .chrono {
        font-size: 1.5rem;
        margin-bottom: 20px;
    }
    .solo-live-stats {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px 18px;
        background: rgba(0,0,0,0.35);
        border: 1px solid rgba(255,215,0,0.25);
        border-radius: 10px;
        padding: 10px 16px;
        margin-bottom: 18px;
        font-size: 0.95rem;
        font-weight: 600;
    }
    .solo-live-stats .sls-item { display: flex; align-items: center; gap: 5px; }
    .solo-live-stats .sls-label { opacity: 0.75; font-size: 0.8rem; }
    .solo-live-stats .sls-value { color: #FFD700; font-weight: 800; }
    .solo-live-stats .sls-eff   { color: #4ECDC4; }
    .solo-live-stats .sls-streak{ color: #FF8E53; }
</style>

<div class="solo-live-stats" aria-label="{{ __('Stats en direct') }}">
    <span class="sls-item"><span class="sls-label">{{ __('Score') }}</span><span class="sls-value" data-stat="score" data-player="self">0</span></span>
    <span class="sls-item"><span class="sls-label">⚡ {{ __('Efficacité') }}</span><span class="sls-value sls-eff" data-stat="efficiencyPercent" data-player="self">0%</span></span>
    <span class="sls-item"><span class="sls-label">🎯 {{ __('Précision') }}</span><span class="sls-value" data-stat="accuracyPercent" data-player="self">0%</span></span>
    <span class="sls-item"><span class="sls-label">🔥 {{ __('Série') }}</span><span class="sls-value sls-streak" data-stat="currentStreak" data-player="self">0</span></span>
    <span class="sls-item"><span class="sls-label">⏱ {{ __('Tps moyen') }}</span><span class="sls-value" data-stat="averageResponseMs" data-player="self">0 ms</span></span>
    <span class="sls-item"><span class="sls-label">✓/✗</span><span class="sls-value" data-stat="correctAnswers" data-player="self">0</span>/<span class="sls-value" data-stat="totalAnswers" data-player="self">0</span></span>
</div>

<div class="question">
    <h2>Question {{ $params['current'] ?? 1 }} / {{ $params['nb_questions'] }}</h2>
    <div class="chrono">
        ⏳ <span id="timer">30</span> sec
    </div>

    @if(isset($params['question_image']))
        <img src="{{ $params['question_image'] }}" alt="Question" class="img-fluid">
    @else
        <p>{{ $params['question_text'] ?? 'Voici votre question...' }}</p>
    @endif
</div>

<form method="POST" action="{{ route('solo.answer') }}">
    @csrf
    <input type="hidden" name="question_id" value="{{ $params['question_id'] }}">
    <div class="reponses">
        @foreach($params['answers'] as $answer)
            <button type="submit" name="answer_id" value="{{ $answer['id'] }}" class="reponse-btn">
                {{ $answer['text'] }}
            </button>
        @endforeach
    </div>
</form>

<script src="{{ asset('js/SoloStatsEngine.js') }}"></script>
<script>
    let timer = 30;
    const timerElement = document.getElementById('timer');
    const interval = setInterval(() => {
        timer--;
        timerElement.textContent = timer;
        if (timer <= 0) {
            clearInterval(interval);
            document.querySelector('form').submit();
        }
    }, 1000);

    (function () {
        var totalAns = {{ (int)($params['current'] ?? 1) - 1 }};
        if (window.SoloStatsEngine) {
            window.SoloStatsEngine.reconcile({ totalAnswers: totalAns });
            window.SoloStatsEngine.markQuestionStart();
        }
        document.addEventListener('DOMContentLoaded', function () {
            var btns = document.querySelectorAll('.reponse-btn');
            btns.forEach(function (b) {
                b.addEventListener('click', function () {
                    if (!window.SoloStatsEngine) return;
                    var elapsed = window.SoloStatsEngine.consumeQuestionElapsedMs();
                    var st = window.SoloStatsEngine.load();
                    st.buzzCount = (st.buzzCount || 0) + 1;
                    window.SoloStatsEngine.save(st);
                    if (typeof elapsed === 'number') {
                        try { sessionStorage.setItem('sb.soloPendingMs.' + (window.MATCH_ID || 'solo'), String(elapsed)); } catch (e) {}
                    }
                }, true);
            });
        });
    })();
</script>
@endsection
