@extends('layouts.app')

@section('content')
<div class="container text-center mt-5">
    <h1 class="display-4">📊 Résultats du Quiz</h1>

    <p class="lead mt-4">
        <strong>Thème :</strong> {{ ucfirst($data['theme']) }}<br>
        <strong>Niveau :</strong> {{ $data['niveau'] }}<br>
        <strong>Avatar :</strong> {{ $data['avatar'] }}
    </p>

    <div class="mt-4">
        <h3>Score final : {{ $data['score'] }}/{{ $data['total'] }}</h3>
        <h4>🎯 Précision : {{ $data['pourcentage'] }}%</h4>
    </div>

    <a href="{{ route('solo') }}" class="btn btn-primary mt-4">🔁 Rejouer</a>
</div>
@endsection
