@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-4">Suppression des données</h1>
    <p>Dernière mise à jour : {{ date('d/m/Y') }}</p>

    <p class="mt-4">
        Vous pouvez supprimer toutes vos données à tout moment en utilisant notre bouton
        <strong>"Supprimer mon compte"</strong> depuis la page de votre profile.
    </p>

    <p class="mt-4">
        Cela effacera définitivement vos informations personnelles, votre progression dans le jeu,
        vos statistiques et toutes les données associées à votre compte.
    </p>
</div>
@endsection
