@extends('layouts.app')

@section('content')
<style>
    .header-image {
        width: 100%;
        height: auto;
        display: block;
        margin: 0 auto 30px auto;
    }

    .register-container {
        max-width: 450px;
        margin: 0 auto;
        padding: 30px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    .register-container h1 {
        text-align: center;
        margin-bottom: 25px;
        color: #fff;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #fff;
        font-weight: 600;
    }

    .form-group input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-group input:focus {
        outline: none;
        border-color: #667eea;
        background: rgba(255, 255, 255, 0.15);
    }

    .form-group input::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }

    .btn-submit {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .back-link {
        display: block;
        text-align: center;
        margin-top: 20px;
        color: #fff;
        text-decoration: none;
        opacity: 0.8;
        transition: opacity 0.3s ease;
    }

    .back-link:hover {
        opacity: 1;
        color: #fff;
    }

    .login-link {
        text-align: center;
        margin-top: 15px;
        color: rgba(255, 255, 255, 0.7);
    }

    .login-link a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
    }

    .login-link a:hover {
        text-decoration: underline;
    }

    .alert {
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .alert-danger {
        background-color: rgba(220, 53, 69, 0.2);
        border: 1px solid rgba(220, 53, 69, 0.5);
        color: #ff6b6b;
    }
</style>

<img src="{{ asset('images/strategybuzzer_header.png') }}" alt="StrategyBuzzer" class="header-image">

<div class="register-container">
    <h1>📧 Créer un compte</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('email.register.submit') }}">
        @csrf
        
        <div class="form-group">
            <label for="name">Nom complet</label>
            <input type="text" id="name" name="name" placeholder="Votre nom" required autofocus value="{{ old('name') }}">
        </div>

        <div class="form-group">
            <label for="email">Adresse Email</label>
            <input type="email" id="email" name="email" placeholder="votre@email.com" required value="{{ old('email') }}">
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirmer le mot de passe</label>
            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-submit">Créer mon compte</button>
    </form>

    <div class="login-link">
        Vous avez déjà un compte ? <a href="{{ route('email.login') }}">Se connecter</a>
    </div>

    <a href="{{ route('login') }}" class="back-link">← Retour aux options de connexion</a>
</div>
@endsection
