@extends('layouts.app')

@section('content')
<div class="container text-center mt-5">
    <h1 class="display-4">Mode Solo</h1>

    <p class="lead mt-2">
        <strong>Choix de Niveau :</strong>
        {{ $niveau_selectionne ?? 'non sélectionné' }}
    </p>

    <p class="lead">Choisissez vos options puis un thème pour commencer la partie :</p>

    <form action="{{ route('solo.start') }}" method="POST">
        @csrf

        <div class="mb-4">
            <label for="nb_questions" class="form-label">Nombre de questions :</label>
            <select name="nb_questions" id="nb_questions" class="form-select w-auto d-inline-block" required>
                <option value="">-- Choisissez --</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="40">40</option>
            </select>
        </div>

        {{-- Choix de niveau --}}
        <div class="dropdown">
            <span class="fw-bold me-2">Choix de Niveau</span>
            {{ $niveau_selectionne ?? 'non sélectionné' }}
            <button class="btn btn-sm btn-outline-primary dropdown-toggle ms-2" type="button" id="niveauDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                Sélectionner
            </button>
            <ul class="dropdown-menu" aria-labelledby="niveauDropdown">
                @for ($i = 1; $i <= 100; $i++)
                    <li>
                        <button type="submit" name="niveau_joueur" value="{{ $i }}"
                            class="dropdown-item {{ $i > $niveau_selectionne ? 'text-danger disabled' : '' }}">
                            Niveau {{ $i }}
                        </button>
                    </li>
                @endfor
            </ul>
        </div>

        {{-- Avatar stratégique --}}
        <div class="dropdown mt-3">
            <span class="fw-bold">Choix de l’Avatar Stratégique : {{ $avatar_stratégique ?? 'non choisi' }}</span>
            <a href="{{ route('avatar') }}" class="btn btn-sm btn-outline-secondary ms-2">Sélectionner</a>
        </div>

        {{-- Choix des thèmes --}}
        <div class="row mt-4">
            <div class="col-md-6">
                <button type="submit" name="theme" value="general" class="btn btn-primary w-100 mb-2">🧠 Général</button>
                <button type="submit" name="theme" value="histoire" class="btn btn-primary w-100 mb-2">📜 Histoire</button>
                <button type="submit" name="theme" value="cinema" class="btn btn-primary w-100 mb-2">🎬 Cinéma</button>
                <button type="submit" name="theme" value="faune" class="btn btn-primary w-100 mb-2">🦁 Faune</button>
            </div>
            <div class="col-md-6">
                <button type="submit" name="theme" value="geographie" class="btn btn-primary w-100 mb-2">🌐 Géographie</button>
                <button type="submit" name="theme" value="art" class="btn btn-primary w-100 mb-2">🎨 Art</button>
                <button type="submit" name="theme" value="sport" class="btn btn-primary w-100 mb-2">🏅 Sport</button>
                <button type="submit" name="theme" value="cuisine" class="btn btn-primary w-100 mb-2">🍳 Cuisine</button>
            </div>
        </div>
    </form>
</div>
@endsection
