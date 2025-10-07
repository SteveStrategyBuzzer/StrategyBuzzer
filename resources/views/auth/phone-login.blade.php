@extends('layouts.app')

@section('content')
<style>
    .header-image {
        width: 100%;
        height: auto;
        display: block;
        margin: 0 auto 30px auto;
    }

    .phone-container {
        max-width: 450px;
        margin: 0 auto;
        padding: 30px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        text-align: center;
    }

    .phone-container h1 {
        margin-bottom: 25px;
        color: #fff;
    }

    .info-message {
        padding: 20px;
        background: rgba(102, 126, 234, 0.2);
        border: 2px solid rgba(102, 126, 234, 0.5);
        border-radius: 8px;
        color: #fff;
        margin-bottom: 25px;
    }

    .back-link {
        display: inline-block;
        margin-top: 20px;
        padding: 12px 24px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        color: #fff;
    }
</style>

<img src="{{ asset('images/strategybuzzer_header.png') }}" alt="StrategyBuzzer" class="header-image">

<div class="phone-container">
    <h1>📱 Connexion par Téléphone</h1>

    <div class="info-message">
        <p><strong>Fonctionnalité à venir !</strong></p>
        <p>La connexion par numéro de téléphone avec code SMS sera bientôt disponible.</p>
        <p>En attendant, vous pouvez vous connecter avec Email, Google ou Facebook.</p>
    </div>

    <a href="{{ route('login') }}" class="back-link">← Retour aux options de connexion</a>
</div>
@endsection
