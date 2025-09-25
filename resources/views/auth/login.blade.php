@extends('layouts.app')

@section('content')
<style>
    :root{
        --bg:#003DA5;
        --card:#0b2a66;
        --btn:#1E90FF;
        --btn-hover:#339CFF;
        --ink:#ffffff;
        --radius:18px;
        --shadow:0 8px 20px rgba(0,0,0,.25);
    }

    .scene{
        min-height:100vh;
        display:flex;
        align-items:center;
        justify-content:center;
        background:var(--bg);
        color:var(--ink);
        padding:20px;
    }
    .card{
        background:var(--card);
        border-radius:var(--radius);
        padding:32px;
        width:100%;
        max-width:420px;
        box-shadow:var(--shadow);
        text-align:center;
    }
    h1{
        font-size:clamp(1.6rem,3vw,2.2rem);
        margin-bottom:18px;
    }
    .btn{
        display:block;
        width:100%;
        padding:14px;
        border-radius:var(--radius);
        font-weight:700;
        color:#fff;
        background:var(--btn);
        text-decoration:none;
        margin:10px 0;
        transition:.25s;
    }
    .btn:hover{background:var(--btn-hover);}
    .muted{
        margin-top:18px;
        font-size:.9rem;
        color:#cbd5e1;
    }
</style>

<div class="scene">
    <div class="card">
        <h1>Connexion</h1>

        <!-- Connexion Google -->
        <a href="{{ route('auth.google') }}" class="btn">
            🔵 Se connecter avec Google
        </a>

        <!-- Connexion Facebook -->
        <a href="{{ route('auth.facebook') }}" class="btn" style="background:#1877F2">
            📘 Se connecter avec Facebook
        </a>

        <p class="muted">En continuant, vous acceptez nos <a href="{{ url('/privacy-policy') }}" style="color:#fff;text-decoration:underline;">politiques de confidentialité</a>.</p>
    </div>
</div>
@endsection
