@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.join-container {
    max-width: 500px;
    margin: 0 auto;
    padding: 1rem;
}

.join-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    text-align: center;
    color: #FFD700;
}

.join-subtitle {
    font-size: 1rem;
    text-align: center;
    opacity: 0.8;
    margin-bottom: 2rem;
}

.profile-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    text-align: center;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #FFD700;
    margin-bottom: 1rem;
    background: rgba(255, 255, 255, 0.2);
}

.profile-avatar-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    border: 4px solid #FFD700;
    margin: 0 auto 1rem;
    background: rgba(255, 255, 255, 0.2);
}

.profile-name {
    font-size: 1.4rem;
    font-weight: 700;
    color: #FFD700;
    margin-bottom: 0.5rem;
}

.profile-level {
    font-size: 1rem;
    opacity: 0.9;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.level-badge {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.9rem;
}

.join-form {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.form-input {
    width: 100%;
    padding: 1rem;
    font-size: 1.5rem;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.3rem;
    font-weight: 700;
    border: 3px solid rgba(255, 215, 0, 0.5);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.95);
    color: #003DA5;
    outline: none;
    transition: all 0.3s ease;
}

.form-input:focus {
    border-color: #FFD700;
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.3);
}

.form-input::placeholder {
    color: #aaa;
    letter-spacing: 0.2rem;
}

.btn-join {
    background: linear-gradient(135deg, #00D400, #00A000);
    color: white;
    padding: 1rem 3rem;
    border-radius: 12px;
    font-size: 1.2rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: block;
    width: 100%;
    text-align: center;
}

.btn-join:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 212, 0, 0.4);
}

.btn-join:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.already-joined {
    background: rgba(0, 212, 0, 0.2);
    border: 2px solid #00D400;
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.already-joined-text {
    font-weight: 600;
    color: #00D400;
    margin-bottom: 0.5rem;
}

.btn-lobby {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 0.8rem 2rem;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-block;
    text-decoration: none;
}

.btn-lobby:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
}

.header-back {
    position: absolute;
    top: 20px;
    left: 20px;
    background: white;
    color: #003DA5;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.header-back:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
}

.alert-error {
    background: rgba(255, 82, 82, 0.2);
    border: 2px solid #ff5252;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    text-align: center;
    color: #ff5252;
    font-weight: 600;
}

.alert-success {
    background: rgba(0, 212, 0, 0.2);
    border: 2px solid #00D400;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    text-align: center;
    color: #00D400;
    font-weight: 600;
}

@media (max-width: 768px) {
    .header-back {
        top: 10px;
        left: 10px;
        padding: 6px 12px;
        font-size: 0.9rem;
    }
    
    .join-title {
        font-size: 1.5rem;
        margin-top: 2rem;
    }
    
    .profile-avatar,
    .profile-avatar-placeholder {
        width: 80px;
        height: 80px;
    }
}
</style>

<a href="{{ route('menu') }}" class="header-back">← {{ __('Menu') }}</a>

<div class="join-container">
    <h1 class="join-title">🎮 {{ __('Rejoindre une partie') }}</h1>
    <p class="join-subtitle">{{ __('Entrez le code du jeu pour participer') }}</p>
    
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif
    
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    
    <div class="profile-card">
        @if($user->avatar_url)
            <img src="{{ $user->avatar_url }}" alt="{{ __('Avatar') }}" class="profile-avatar">
        @else
            <div class="profile-avatar-placeholder">👤</div>
        @endif
        
        <div class="profile-name">{{ $user->display_name }}</div>
        
        <div class="profile-level">
            <span>{{ __('Niveau') }}</span>
            <span class="level-badge">{{ $playerLevel }}</span>
        </div>
    </div>
    
    <form action="{{ route('master.join.process') }}" method="POST" class="join-form">
        @csrf
        
        <label for="game_code" class="form-label">{{ __('Code de la partie') }}</label>
        <input 
            type="text" 
            id="game_code" 
            name="game_code" 
            class="form-input" 
            placeholder="XXXXXX"
            maxlength="6"
            pattern="[A-Za-z0-9]{6}"
            required
            autocomplete="off"
        >
        
        <button type="submit" class="btn-join" style="margin-top: 1.5rem;">
            🚀 {{ __('Rejoindre') }}
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('game_code');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    }
});
</script>
@endsection
