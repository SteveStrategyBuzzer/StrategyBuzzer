@extends('layouts.app')

@section('content')
<div class="container max-w-xl mx-auto py-8">
    <h1 class="text-2xl font-bold mb-4">Supprimer mon compte</h1>

    <p class="mb-4">
        Cette action est <strong>définitive</strong>. Toutes vos données de jeu associées à ce compte seront supprimées.
    </p>

    @if (session('status'))
        <div class="bg-green-100 border border-green-300 text-green-800 p-3 rounded mb-4">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('account.delete.perform') }}">
        @csrf
        <button type="submit"
                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                onclick="return confirm('Confirmer la suppression définitive de votre compte ?');">
            Supprimer mon compte
        </button>
    </form>
</div>
@endsection
