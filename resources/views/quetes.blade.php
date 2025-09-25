@extends('layouts.app')

@section('content')
<div class="container text-center">
    <h1>Quêtes</h1>
    <p>Complétez des quêtes pour gagner des titres, des vies et des styles visuels.</p>

    <ul class="list-group mt-4">
        <li class="list-group-item">Quête 1 : Remporter 5 parties consécutives</li>
        <li class="list-group-item">Quête 2 : Répondre correctement à 50 questions</li>
        <li class="list-group-item">Quête 3 : Débloquer un titre rare</li>
    </ul>

    <a href="{{ route('menu') }}" class="btn btn-primary mt-3">Retour au menu</a>
</div>
@endsection
