@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-4">Politique de confidentialité</h1>
    <p>Dernière mise à jour : {{ date('d/m/Y') }}</p>

    <p class="mt-4">
        Cette application collecte uniquement les données nécessaires pour offrir une expérience de jeu optimale,
        telles que votre nom, votre adresse e-mail et vos préférences de jeu.
        Ces informations ne sont jamais vendues ni partagées avec des tiers.
    </p>

    <p class="mt-4">
        Vous pouvez à tout moment demander la suppression de vos données en visitant
        la page <a href="{{ route('account.delete.show') }}" class="text-blue-600 underline">Supprimer mon compte</a>.
    </p>
</div>
@endsection
