@extends('layouts.app')

@section('content')
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
</style>

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
</script>
@endsection
