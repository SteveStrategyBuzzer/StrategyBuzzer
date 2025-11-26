@extends('layouts.app')

@section('content')
<div class="connexion-screen">
    <h1>{{ __('Connexion') }}</h1>

    {{-- Messages --}}
    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <p>{{ __('Veuillez choisir votre méthode de connexion') }} :</p>

    <div class="auth-buttons">
        <a href="{{ url('/auth/google') }}" class="btn-google">{{ __('Connexion avec Google') }}</a>
        <a href="{{ url('/auth/facebook') }}" class="btn-facebook">{{ __('Connexion avec Facebook') }}</a>
    </div>
</div>

<style>
body {
    background-color: #003DA5;
    color: #fff;
    text-align: center;
    font-family: Arial, sans-serif;
}

.connexion-screen {
    margin-top: 10vh;
}

h1 {
    font-size: 3rem;
    margin-bottom: 20px;
}

.auth-buttons {
    margin-top: 30px;
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.auth-buttons a {
    display: inline-block;
    padding: 15px 30px;
    border-radius: 8px;
    text-decoration: none;
    color: #fff;
    font-size: 1.2rem;
    font-weight: bold;
    transition: opacity 0.3s ease;
}

.btn-google {
    background-color: #dd4b39;
}

.btn-facebook {
    background-color: #3b5998;
}

.auth-buttons a:hover {
    opacity: 0.9;
}

.alert {
    width: 50%;
    margin: 10px auto;
    padding: 10px;
    border-radius: 4px;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
}
</style>
@endsection
