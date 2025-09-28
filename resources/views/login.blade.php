@extends('layouts.app')

@section('content')
<style>
    .header-image {
        width: 100%;
        height: auto;
        display: block;
        margin: 0 auto 30px auto; /* espace sous l'image */
    }

    .content-container {
        text-align: center;
    }

    .auth-buttons .btn {
        display: inline-block;
        margin: 10px;
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

    .auth-buttons .btn:hover {
        opacity: 0.9;
    }
</style>

<img src="{{ asset('images/strategybuzzer_header.png') }}" alt="StrategyBuzzer" class="header-image">

<div class="content-container">
    <h1>Connexion</h1>

    <p>Veuillez choisir votre méthode de connexion.</p>

    <div class="auth-buttons">
        <a href="{{ url('/auth/google') }}" class="btn">Connexion avec Google</a>
        <a href="{{ url('/auth/facebook') }}" class="btn">Connexion avec Facebook</a>
    </div>
</div>
@endsection