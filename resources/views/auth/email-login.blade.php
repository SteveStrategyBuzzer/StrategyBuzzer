@extends('layouts.app')

@section('content')
<style>
    .header-image {
        width: 100%;
        height: auto;
        display: block;
        margin: 0 auto 30px auto;
    }

    .login-container {
        max-width: 450px;
        margin: 0 auto;
        padding: 30px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    .login-container h1 {
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

    .password-wrapper {
        position: relative;
    }

    .password-wrapper input {
        padding-right: 45px;
    }

    .toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.3rem;
        user-select: none;
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }

    .toggle-password:hover {
        opacity: 1;
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

    .register-link {
        text-align: center;
        margin-top: 15px;
        color: rgba(255, 255, 255, 0.7);
    }

    .register-link a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
    }

    .register-link a:hover {
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

<div class="login-container">
    <h1>📧 Connexion Email</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('email.login.submit') }}">
        @csrf
        
        <div class="form-group">
            <label for="email">Adresse Email</label>
            <input type="email" id="email" name="email" placeholder="votre@email.com" required autofocus value="{{ old('email') }}">
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" placeholder="••••••••" required>
                <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">👁️</button>
            </div>
        </div>

        <button type="submit" class="btn-submit">Se connecter</button>
    </form>

    <div class="register-link">
        Pas encore de compte ? <a href="{{ route('email.register') }}">Créer un compte</a>
    </div>

    <a href="{{ route('login') }}" class="back-link">← Retour aux options de connexion</a>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.toggle-password');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleButton.textContent = '🙈';
    } else {
        passwordInput.type = 'password';
        toggleButton.textContent = '👁️';
    }
}
</script>
@endsection
