@extends('layouts.app')

@section('content')
<style>
    body {
        margin: 0;
        padding: 0;
        background-color: #1f4e96;
        font-family: Arial, sans-serif;
    }

    .login-container {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .header-section {
        padding: 20px 0;
        text-align: center;
    }

    .header-logo {
        max-width: 400px;
        height: auto;
        margin: 0 auto;
        display: block;
    }

    .content-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 50px 20px;
    }

    .content-section h1 {
        color: #fff;
        font-size: 2.5rem;
        margin-bottom: 20px;
        font-weight: bold;
    }

    .content-section p {
        color: #fff;
        font-size: 1.2rem;
        margin-bottom: 40px;
    }

    .auth-buttons {
        display: flex;
        gap: 20px;
    }

    .auth-buttons .btn {
        display: inline-block;
        padding: 15px 30px;
        background-color: #000;
        color: #fff;
        font-size: 1.2rem;
        font-weight: bold;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        transition: opacity 0.3s ease;
    }

    .auth-buttons .btn:hover {
        opacity: 0.8;
    }
</style>

<div class="login-container">
    <div class="header-section">
        <img src="{{ asset('images/strategybuzzer-logo.png') }}" alt="StrategyBuzzer" class="header-logo">
    </div>
    
    <div class="content-section">
        <h1>Connexion</h1>
        <p>Veuillez choisir votre méthode de connexion.</p>
        
        <div class="auth-buttons">
            <a href="{{ url('/auth/google') }}" class="btn">Connexion avec Google</a>
            <a href="{{ url('/auth/facebook') }}" class="btn">Connexion avec Facebook</a>
        </div>
    </div>
</div>
@endsection
