@extends('layout')
@section('content')
    <h1>{{ __('Menu principal') }}</h1>
    <ul>
        <li><a href="/demo">{{ __('Solo') }}</a></li>
        <li><a href="/duo">{{ __('Duo') }}</a></li>
        <li><a href="/quests">{{ __('Quêtes') }}</a></li>
        <li><a href="/master">{{ __('Maître') }}</a></li>
        <li><a href="/ligue">{{ __('Ligue') }}</a></li>
        <li><a href="/reglements">{{ __('Guide du Joueur') }}</a></li>
        <li><a href="/parametres">{{ __('Paramètres') }}</a></li>
    </ul>
@endsection

