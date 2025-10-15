@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 20px;
}

.master-container {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
}

.master-title {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    color: #FFD700;
}

.master-subtitle {
    font-size: 1.2rem;
    opacity: 0.95;
    margin-bottom: 3rem;
}

.btn-create {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 1.2rem 3rem;
    border-radius: 12px;
    font-size: 1.3rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 3rem;
    display: inline-block;
    text-decoration: none;
}

.btn-create:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(255, 215, 0, 0.4);
}

.join-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 2rem;
    margin-top: 2rem;
}

.join-label {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.join-input {
    width: 100%;
    max-width: 300px;
    padding: 0.8rem;
    font-size: 1.1rem;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    text-align: center;
    text-transform: uppercase;
    margin-bottom: 1rem;
}

.join-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.btn-join {
    background: #fff;
    color: #003DA5;
    padding: 0.8rem 2rem;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-join:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
}

.btn-join:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.join-note {
    font-size: 0.9rem;
    opacity: 0.7;
    margin-top: 1rem;
}

.header-menu {
    position: absolute;
    top: 20px;
    right: 20px;
    background: white;
    color: #003DA5;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.header-menu:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
}

/* Responsive Portrait */
@media (max-width: 480px) and (orientation: portrait) {
    .master-title {
        font-size: 2.2rem;
    }
    .master-subtitle {
        font-size: 1rem;
        padding: 0 0.5rem;
    }
    .btn-create {
        padding: 1rem 2rem;
        font-size: 1.1rem;
    }
    .join-section {
        padding: 1.5rem;
    }
}
</style>

<a href="{{ route('menu') }}" class="header-menu">← Menu</a>

<div class="master-container">
    <h1 class="master-title">🎓 MAÎTRE DU JEU</h1>
    <p class="master-subtitle">
        Crée une partie, invite des joueurs et anime ton quiz en direct.
    </p>
    
    <a href="{{ route('master.create') }}" class="btn-create">
        Créer une Partie
    </a>
    
    <div class="join-section">
        <div class="join-label">Rejoindre une partie</div>
        
        @if (session('error'))
            <div style="background: #ff4444; color: white; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem;">
                {{ session('error') }}
            </div>
        @endif
        
        @error('game_code')
            <div style="background: #ff4444; color: white; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem;">
                {{ $message }}
            </div>
        @enderror
        
        <form action="{{ route('master.join') }}" method="POST" id="joinForm">
            @csrf
            <input 
                type="text" 
                name="game_code" 
                id="gameCode"
                class="join-input" 
                placeholder="XXXXXX"
                maxlength="6"
                minlength="6"
                pattern="[A-Z0-9]{6}"
                required
            >
            <br>
            <button type="submit" class="btn-join" id="joinBtn">
                Rejoindre
            </button>
        </form>
        <p class="join-note">Besoin d'un code ? Demande-le au Maître du Jeu.</p>
    </div>
</div>

<script>
// Activer le bouton uniquement si code saisi (6 caractères)
const codeInput = document.getElementById('gameCode');
const joinBtn = document.getElementById('joinBtn');

joinBtn.disabled = true;

codeInput.addEventListener('input', function() {
    // Forcer majuscules
    this.value = this.value.toUpperCase();
    
    // Activer bouton seulement si 6 caractères
    joinBtn.disabled = this.value.length !== 6;
});
</script>
@endsection
