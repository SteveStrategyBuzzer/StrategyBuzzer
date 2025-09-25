@extends('layout')

@section('content')
<div class="card" style="text-align: center;">
    <h2>Question <span id="question-number">1</span> / 20</h2>

    <div id="player-info" style="margin-bottom: 10px;">
        👤 <strong id="player-name">Steve</strong> | 🧠 Skill: <button id="skill-btn">+ Temps</button> | 🔢 Score: <span id="score">0</span>
    </div>

    <div id="timer">⏱️ Temps restant : <span id="countdown">10</span>s</div>
    <p id="question-text" style="margin-top: 20px;">Quelle est la capitale de la France ?</p>

    <ul id="answers" style="list-style: none; padding: 0;">
        <li><button class="answer">Lyon</button></li>
        <li><button class="answer">Marseille</button></li>
        <li><button class="answer correct">Paris</button></li>
        <li><button class="answer">Nice</button></li>
    </ul>

    <button id="buzzer" class="buzzer-inactive">🔘 BUZZ</button>
    <p><a href="/">⬅️ Retour à l’accueil</a></p>
</div>

<script src="/js/quiz_full.js"></script>
@endsection
