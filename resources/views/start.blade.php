@extends('layouts.app')

@section('content')
<style>
body {
    margin: 0;
    padding: 0;
    height: 100vh;
    width: 100vw;
    background: url('/images/StrategyBuzzer%20paysage%20et%20ordi%20HD%20Connexion.png') no-repeat center center;
    background-size: cover;
    position: relative;
}

.btn-connexion {
    position: absolute;
    top: 60%; /* ajuste ici la hauteur pour être aligné au texte */
    left: 74%;
    transform: translate(-50%, -50%);
    padding: 12px 24px;
    background-color: #000;
    color: #fff;
    font-size: 1.2rem;
    font-weight: bold;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
}

.btn-connexion:hover {
    opacity: 0.9;
}
</style>

<a href="{{ route('login') }}" class="btn-connexion">Connexion</a>

@endsection
