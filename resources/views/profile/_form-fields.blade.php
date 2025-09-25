{{-- Champs en mode FORM avec POST possible --}}
<div class="space-y-6">
    <div>
        <label class="block text-sm font-medium mb-2">Afficher mon avatar dans la ligue :</label>
        <select name="show_in_league" class="w-full rounded-xl border-gray-300">
            <option value="Oui"  {{ ($showInLeague === 'Oui')  ? 'selected' : '' }}>Oui</option>
            <option value="Non"  {{ ($showInLeague === 'Non')  ? 'selected' : '' }}>Non</option>
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-2">Langue du jeu :</label>
        <select name="language" class="w-full rounded-xl border-gray-300">
            <option value="Français" {{ ($language === 'Français') ? 'selected' : '' }}>Français</option>
            <option value="Anglais"  {{ ($language === 'Anglais')  ? 'selected' : '' }}>Anglais</option>
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-2">Pays :</label>
        <select name="country" class="w-full rounded-xl border-gray-300">
            <option value="">-- Sélectionnez --</option>
            <option value="CA" {{ $country==='CA' ? 'selected' : '' }}>Canada</option>
            <option value="FR" {{ $country==='FR' ? 'selected' : '' }}>France</option>
            <option value="US" {{ $country==='US' ? 'selected' : '' }}>États-Unis</option>
        </select>
    </div>

    <div>
        <span class="block text-sm font-medium mb-2">Options sonores :</span>
        <label class="flex items-center gap-2 mb-2">
            <input type="checkbox" name="options[ambiance]" value="1" {{ !empty($options['ambiance']) ? 'checked' : '' }}>
            <span>Ambiance</span>
        </label>
        <label class="flex items-center gap-2 mb-2">
            <input type="checkbox" name="options[buzzer]" value="1" {{ !empty($options['buzzer']) ? 'checked' : '' }}>
            <span>Buzzer</span>
        </label>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="options[results]" value="1" {{ !empty($options['results']) ? 'checked' : '' }}>
            <span>Résultats</span>
        </label>
    </div>

    <div>
        @if(Route::has('boutique'))
            <a href="{{ route('boutique') }}" class="text-[#0A2C66] underline">Accéder à la Boutique</a>
        @else
            <span class="opacity-70">Accéder à la Boutique (bientôt)</span>
        @endif
    </div>
</div>
