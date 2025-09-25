{{-- Champs affichés en lecture seule --}}
<div class="space-y-6">
    <div>
        <label class="block text-sm font-medium mb-2">Afficher mon avatar dans la ligue :</label>
        <div class="w-full rounded-xl bg-gray-100 p-3">{{ $showInLeague }}</div>
    </div>

    <div>
        <label class="block text-sm font-medium mb-2">Langue du jeu :</label>
        <div class="w-full rounded-xl bg-gray-100 p-3">{{ $language }}</div>
    </div>

    <div>
        <label class="block text-sm font-medium mb-2">Pays :</label>
        <div class="w-full rounded-xl bg-gray-100 p-3">{{ $country ?: '—' }}</div>
    </div>

    <div>
        <span class="block text-sm font-medium mb-2">Options sonores :</span>
        <ul class="list-disc pl-6">
            <li>Ambiance : {{ !empty($options['ambiance']) ? 'Oui' : 'Non' }}</li>
            <li>Buzzer : {{ !empty($options['buzzer']) ? 'Oui' : 'Non' }}</li>
            <li>Résultats : {{ !empty($options['results']) ? 'Oui' : 'Non' }}</li>
        </ul>
    </div>

    <div>
        @if(Route::has('boutique'))
            <a href="{{ route('boutique') }}" class="text-[#0A2C66] underline">Accéder à la Boutique</a>
        @else
            <span class="opacity-70">Accéder à la Boutique (bientôt)</span>
        @endif
    </div>
</div>
