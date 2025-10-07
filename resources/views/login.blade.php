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

    .auth-buttons {
        max-width: 400px;
        margin: 0 auto;
    }

    .auth-buttons .btn {
        display: block;
        width: 100%;
        margin: 15px 0;
        padding: 15px 24px;
        background-color: #000;
        color: #fff;
        font-size: 1.1rem;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
    }

    .auth-buttons .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .btn-google {
        background-color: #db4437;
    }

    .btn-facebook {
        background-color: #4267B2;
    }

    .btn-apple {
        background-color: #000;
    }

    .btn-phone {
        background-color: #25D366;
    }

    .btn-email {
        background-color: #5865F2;
    }

    .divider {
        margin: 25px 0;
        text-align: center;
        position: relative;
    }

    .divider::before {
        content: "";
        position: absolute;
        left: 0;
        top: 50%;
        width: 40%;
        height: 1px;
        background: rgba(255, 255, 255, 0.3);
    }

    .divider::after {
        content: "";
        position: absolute;
        right: 0;
        top: 50%;
        width: 40%;
        height: 1px;
        background: rgba(255, 255, 255, 0.3);
    }

    .divider span {
        color: rgba(255, 255, 255, 0.6);
        padding: 0 10px;
    }
</style>

<img src="{{ asset('images/strategybuzzer_header.png') }}" alt="StrategyBuzzer" class="header-image">

<div class="content-container">
    <h1>Connexion</h1>

    <p>Veuillez choisir votre méthode de connexion.</p>

    <div class="auth-buttons">
        <a href="{{ url('/auth/email') }}" class="btn btn-email">📧 Connexion avec Email</a>
        
        <div class="divider"><span>OU</span></div>
        
        <a href="{{ url('/auth/google') }}" class="btn btn-google">🔍 Connexion avec Google</a>
        <a href="{{ url('/auth/facebook') }}" class="btn btn-facebook">📘 Connexion avec Facebook</a>
        <a href="{{ url('/auth/apple') }}" class="btn btn-apple">🍎 Connexion avec Apple</a>
        <a href="{{ url('/auth/phone') }}" class="btn btn-phone">📱 Connexion avec Téléphone</a>
    </div>
</div>
@endsection