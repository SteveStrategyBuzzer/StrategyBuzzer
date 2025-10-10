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
    top: 60%;
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

/* Layout mobile avec titre, cerveau, bouton */
.mobile-layout {
    display: none;
}

/* Mobile Portrait */
@media (max-width: 480px) and (orientation: portrait) {
    body {
        background: linear-gradient(180deg, #1a237e 0%, #0d47a1 100%);
        background-image: none;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        padding: 0;
        overflow: hidden;
    }

    .btn-connexion {
        display: none;
    }

    .mobile-layout {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        height: 100vh;
        padding: 30px 20px;
        box-sizing: border-box;
    }

    .mobile-title {
        font-size: 2.5rem;
        font-weight: 900;
        color: #fff;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin: 0;
        text-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
    }

    .mobile-title-small {
        font-size: 1.8rem;
        font-weight: 700;
        color: #fff;
        margin-top: 5px;
    }

    .mobile-brain {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        max-height: 60vh;
    }

    .mobile-brain img {
        width: min(80vw, 350px);
        height: auto;
        filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.4));
        animation: float 3s ease-in-out infinite;
    }

    .mobile-button {
        width: 100%;
        max-width: 300px;
        padding: 18px 24px;
        background-color: #000;
        color: #fff;
        font-size: 1.3rem;
        font-weight: bold;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        transition: transform 0.2s ease;
    }

    .mobile-button:active {
        transform: scale(0.95);
    }
}

/* Animation cerveau (disponible pour tous) */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-15px); }
}

/* Mobile Paysage - garde layout vertical */
@media (max-height: 500px) and (orientation: landscape) {
    body {
        background: linear-gradient(180deg, #1a237e 0%, #0d47a1 100%);
        background-image: none;
    }

    .btn-connexion {
        display: none;
    }

    .mobile-layout {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        height: 100vh;
        padding: 15px 20px;
        box-sizing: border-box;
    }

    .mobile-left {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .mobile-title {
        font-size: 1.8rem;
        font-weight: 900;
        color: #fff;
        text-align: center;
        text-transform: uppercase;
        margin: 0;
        text-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
    }

    .mobile-title-small {
        font-size: 1.3rem;
        font-weight: 700;
        color: #fff;
        margin-top: 3px;
    }

    .mobile-brain {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .mobile-brain img {
        width: min(35vh, 200px);
        height: auto;
        filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.4));
        animation: float 3s ease-in-out infinite;
    }

    .mobile-button {
        width: 100%;
        max-width: 250px;
        padding: 14px 30px;
        background-color: #000;
        color: #fff;
        font-size: 1.2rem;
        font-weight: bold;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    }
}
</style>

<a href="{{ route('login') }}" class="btn-connexion">Connexion</a>

<div class="mobile-layout">
    <div class="mobile-left">
        <div class="mobile-title">STRATEGY</div>
        <div class="mobile-title-small">BUZZER</div>
    </div>
    
    <div class="mobile-brain">
        <img src="/images/brain.png" alt="Brain">
    </div>
    
    <a href="{{ route('login') }}" class="mobile-button">Connexion</a>
</div>

@endsection
