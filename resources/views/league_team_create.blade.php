@extends('layouts.app')

@section('title', __('Créer une Équipe'))

@section('content')
<div class="league-lobby-container">
    <div class="league-header">
        <button onclick="window.location.href='{{ route('league.entry') }}'" class="back-button">
            ← {{ __('Retour') }}
        </button>
        <h1>🛡️ {{ __('Créer une Équipe') }}</h1>
    </div>

    <div class="create-team-content">
        <p class="create-description">{{ __('Formez une équipe de 5 joueurs pour participer à la Ligue par Équipe') }}</p>
        
        <div class="create-team-form">
            <div class="form-group">
                <label>{{ __('Nom de l\'équipe') }} <span class="char-limit">({{ __('max 10 caractères') }})</span></label>
                <input type="text" id="teamName" placeholder="{{ __('ex: CHAMPIONS') }}" maxlength="10">
                <div class="char-counter"><span id="nameCharCount">0</span>/10</div>
            </div>
            
            <div class="form-group">
                <label>{{ __('Emblème de l\'équipe') }}</label>
                
                <div class="emblem-selector">
                    <div class="emblem-preview" id="emblemPreview">
                        <div class="emblem-placeholder">🛡️</div>
                    </div>
                    
                    <div class="emblem-tabs">
                        <button type="button" class="emblem-tab active" data-tab="categories">{{ __('Choisir') }}</button>
                        <button type="button" class="emblem-tab" data-tab="upload">{{ __('Importer') }}</button>
                    </div>
                    
                    <div class="emblem-tab-content" id="categoriesTab">
                        <div class="emblem-categories">
                            @php
                            $categories = [
                                'animals' => ['name' => 'Animaux', 'icon' => '🦁'],
                                'warriors' => ['name' => 'Guerriers', 'icon' => '⚔️'],
                                'sports' => ['name' => 'Sport', 'icon' => '🏆'],
                                'symbols' => ['name' => 'Symboles', 'icon' => '🌟'],
                                'elements' => ['name' => 'Éléments', 'icon' => '🔥'],
                                'gaming' => ['name' => 'Gaming', 'icon' => '🎮'],
                                'royalty' => ['name' => 'Royauté', 'icon' => '👑'],
                                'flags' => ['name' => 'Drapeaux', 'icon' => '🌍'],
                                'masks' => ['name' => 'Masques', 'icon' => '🎭'],
                                'gems' => ['name' => 'Gemmes', 'icon' => '💎'],
                            ];
                            @endphp
                            @foreach($categories as $key => $cat)
                                <button type="button" class="category-btn" data-category="{{ $key }}">
                                    <span class="cat-icon">{{ $cat['icon'] }}</span>
                                    <span class="cat-name">{{ __($cat['name']) }}</span>
                                </button>
                            @endforeach
                        </div>
                        
                        <div class="emblem-grid" id="emblemGrid" style="display: none;">
                            <button type="button" class="back-to-categories" id="backToCategories">← {{ __('Retour') }}</button>
                            <div class="emblems-container" id="emblemsContainer"></div>
                        </div>
                    </div>
                    
                    <div class="emblem-tab-content" id="uploadTab" style="display: none;">
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" id="emblemUpload" accept="image/png,image/jpeg,image/gif,image/webp" style="display: none;">
                            <div class="upload-placeholder">
                                <span class="upload-icon">📁</span>
                                <p>{{ __('Cliquez ou déposez une image') }}</p>
                                <small>PNG, JPG, GIF, WEBP (max 2MB)</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="emblemCategory" value="animals">
                <input type="hidden" id="emblemIndex" value="1">
                <input type="hidden" id="customEmblem" value="">
            </div>
            
            <button id="createTeamBtn" class="btn-primary btn-large">
                <span class="btn-icon">⚔️</span>
                {{ __('CRÉER L\'ÉQUIPE') }}
            </button>
        </div>
        
        <div id="createError" class="error-message" style="display: none;"></div>
        
        <div id="successSection" class="success-section" style="display: none;">
            <div class="success-card">
                <div class="success-icon">✅</div>
                <h2>{{ __('Équipe créée avec succès !') }}</h2>
                <div class="team-created-info">
                    <div class="created-emblem" id="createdEmblem"></div>
                    <div class="created-details">
                        <h3 id="createdTeamName"></h3>
                        <p class="team-id-display">{{ __('ID') }}: <span id="createdTeamId"></span></p>
                    </div>
                </div>
                <div class="success-actions">
                    <button onclick="window.location.href='{{ route('league.team.management') }}'" class="btn-primary">
                        {{ __('Gérer mon équipe') }}
                    </button>
                    <button onclick="resetForm()" class="btn-secondary">
                        {{ __('Créer une autre équipe') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.create-team-content {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
}

.create-description {
    text-align: center;
    color: #aaa;
    margin-bottom: 30px;
}

.create-team-form {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 30px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    color: #00d4ff;
    margin-bottom: 10px;
    font-weight: bold;
}

.char-limit {
    color: #888;
    font-weight: normal;
    font-size: 0.85rem;
}

.form-group input[type="text"] {
    width: 100%;
    padding: 15px;
    background: #0a0a15;
    border: 2px solid #0f3460;
    border-radius: 10px;
    color: #fff;
    font-size: 1.1rem;
    box-sizing: border-box;
}

.form-group input[type="text"]:focus {
    outline: none;
    border-color: #00d4ff;
}

.char-counter {
    text-align: right;
    color: #888;
    font-size: 0.85rem;
    margin-top: 5px;
}

.emblem-selector {
    background: #0a0a15;
    border-radius: 10px;
    padding: 20px;
}

.emblem-preview {
    width: 100px;
    height: 100px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
    border: 4px solid #00d4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    overflow: hidden;
}

.emblem-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.emblem-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    justify-content: center;
}

.emblem-tab {
    padding: 10px 25px;
    border: 2px solid #0f3460;
    background: transparent;
    border-radius: 25px;
    cursor: pointer;
    color: #fff;
    transition: all 0.3s ease;
}

.emblem-tab.active {
    background: linear-gradient(135deg, #00d4ff 0%, #0f3460 100%);
    border-color: #00d4ff;
}

.emblem-categories {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
}

@media (max-width: 600px) {
    .emblem-categories {
        grid-template-columns: repeat(2, 1fr);
    }
}

.category-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px 10px;
    background: #1a1a2e;
    border: 2px solid #0f3460;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.category-btn:hover {
    border-color: #00d4ff;
    transform: translateY(-3px);
}

.category-btn .cat-icon {
    font-size: 1.8rem;
    margin-bottom: 5px;
}

.category-btn .cat-name {
    font-size: 0.75rem;
    color: #aaa;
}

.emblem-grid {
    max-height: 300px;
    overflow-y: auto;
}

.back-to-categories {
    background: none;
    border: none;
    color: #00d4ff;
    cursor: pointer;
    margin-bottom: 15px;
    font-size: 0.9rem;
    padding: 5px 10px;
}

.emblems-container {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 8px;
}

@media (max-width: 600px) {
    .emblems-container {
        grid-template-columns: repeat(5, 1fr);
    }
}

.emblem-item {
    width: 40px;
    height: 40px;
    border: 2px solid #0f3460;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: #1a1a2e;
    transition: all 0.2s ease;
}

.emblem-item:hover {
    border-color: #00d4ff;
    transform: scale(1.1);
}

.emblem-item.selected {
    border-color: #ffd700;
    background: rgba(255, 215, 0, 0.2);
}

.upload-zone {
    border: 2px dashed #0f3460;
    border-radius: 10px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.upload-zone:hover, .upload-zone.dragover {
    border-color: #00d4ff;
    background: rgba(0, 212, 255, 0.05);
}

.upload-placeholder .upload-icon {
    font-size: 3rem;
    display: block;
    margin-bottom: 10px;
}

.upload-placeholder p {
    color: #fff;
    margin: 0 0 5px 0;
}

.upload-placeholder small {
    color: #888;
}

.btn-primary {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #00d4ff 0%, #0f3460 100%);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0, 212, 255, 0.3);
}

.btn-secondary {
    width: 100%;
    padding: 15px;
    background: transparent;
    border: 2px solid #0f3460;
    border-radius: 10px;
    color: #aaa;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    border-color: #00d4ff;
    color: #00d4ff;
}

.error-message {
    background: rgba(220, 53, 69, 0.2);
    border: 1px solid #dc3545;
    border-radius: 10px;
    padding: 15px;
    color: #ff6b6b;
    text-align: center;
    margin-top: 20px;
}

.success-section {
    margin-top: 30px;
}

.success-card {
    background: linear-gradient(135deg, #1a2e1a 0%, #162e16 100%);
    border: 2px solid #28a745;
    border-radius: 15px;
    padding: 30px;
    text-align: center;
}

.success-icon {
    font-size: 4rem;
    margin-bottom: 15px;
}

.success-card h2 {
    color: #28a745;
    margin: 0 0 20px 0;
}

.team-created-info {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-bottom: 25px;
}

.created-emblem {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
    border: 3px solid #00d4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    overflow: hidden;
}

.created-emblem img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.created-details h3 {
    color: #fff;
    margin: 0 0 5px 0;
}

.team-id-display {
    color: #aaa;
    margin: 0;
    font-family: monospace;
    font-size: 1.1rem;
}

.team-id-display span {
    color: #00d4ff;
    font-weight: bold;
}

.success-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
</style>

<script>
const emblems = {
    animals: ['🦁', '🐯', '🐻', '🦊', '🐺', '🦅', '🦈', '🐍', '🦎', '🐊', '🦂', '🦀', '🐙', '🦑', '🐋', '🐬', '🦭', '🐘', '🦏', '🦛', '🐪', '🦒', '🦘', '🦬', '🐃', '🦌', '🦙', '🐎', '🦓', '🐗', '🐺', '🦇', '🐀', '🐉', '🦎', '🦖', '🦕', '🐢', '🐸', '🐊', '🦜', '🦩', '🦚', '🦢', '🦤', '🕊️', '🐝', '🦋', '🐞', '🦗'],
    warriors: ['⚔️', '🗡️', '🛡️', '🏹', '🪓', '🔱', '⚒️', '🪃', '💣', '🧨', '💥', '🎯', '🥷', '👺', '👹', '💀', '☠️', '👻', '🤖', '👾', '🦾', '🦿', '🧠', '👁️', '🫀', '🪖', '🎖️', '🏅', '🥇', '⭐', '🌟', '✨', '💫', '🔥', '❄️', '⚡', '💨', '🌪️', '🌊', '🌋', '☄️', '🌙', '☀️', '🌈', '🎭', '👑', '💎', '🔮', '🧿', '⚱️'],
    sports: ['🏆', '🥇', '🥈', '🥉', '🏅', '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🥊', '🥋', '⛳', '⛸️', '🎿', '🛷', '🏂', '🏋️', '🤸', '🚴', '🏊', '🤽', '🚣', '🧗', '🤺', '🏄', '🎳', '♟️', '🎯', '🏹', '🥏', '🪀', '🛹', '🛼', '⛹️', '🤾', '🤿', '🪂', '🏇', '🚵', '🧘', '🎽', '🥅', '🪁', '🎣'],
    symbols: ['🌟', '⭐', '✨', '💫', '🔥', '💧', '💎', '❤️', '💜', '💙', '💚', '💛', '🧡', '🖤', '🤍', '❤️‍🔥', '💝', '💖', '💗', '💓', '💕', '💞', '💘', '💌', '🎀', '🎁', '🎊', '🎉', '🎈', '🎆', '🎇', '✳️', '❇️', '💠', '🔷', '🔶', '🔹', '🔸', '🟠', '🟡', '🟢', '🔵', '🟣', '🟤', '⚫', '⚪', '🔴', '🟥', '🟧', '🟨'],
    elements: ['🔥', '💧', '🌊', '💨', '🌪️', '⚡', '❄️', '☃️', '🌙', '☀️', '🌈', '⭐', '🌟', '✨', '💫', '☄️', '🌋', '🏔️', '⛰️', '🌍', '🌎', '🌏', '🪐', '💎', '🔮', '🧊', '🌡️', '🌀', '🌁', '🌫️', '🌤️', '⛅', '🌥️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '☔', '💦', '💥', '🏝️', '🏜️', '🌵', '🌴', '🌲', '🌳', '🌾', '🍀', '🍁'],
    gaming: ['🎮', '🕹️', '👾', '🤖', '🎯', '🎲', '♟️', '🃏', '🀄', '🎰', '🎪', '🎭', '🎬', '🎥', '📺', '📱', '💻', '🖥️', '⌨️', '🖱️', '💾', '💿', '📀', '🔌', '🔋', '💡', '🔦', '🏮', '📡', '🛸', '🚀', '🛰️', '✈️', '🚁', '🎪', '🎢', '🎡', '🎠', '⚙️', '🔧', '🔩', '⛓️', '🔗', '📌', '📍', '🗺️', '🧭', '🎴', '🎨', '🖼️'],
    royalty: ['👑', '💎', '💍', '🏰', '🏯', '👸', '🤴', '🦁', '🦅', '🐉', '🗡️', '⚔️', '🛡️', '🔱', '⚜️', '🎖️', '🏅', '🥇', '🏆', '✨', '🌟', '⭐', '💫', '👼', '😇', '🙏', '🪔', '🕯️', '🔮', '🧿', '📿', '📜', '🖋️', '✒️', '🪶', '📖', '📚', '🎓', '🧙', '🧝', '🧚', '🧞', '🧜', '🧛', '🦸', '🦹', '🥷', '🤺', '♔', '♕'],
    flags: ['🏴', '🏳️', '🚩', '🎌', '🏁', '🇫🇷', '🇬🇧', '🇺🇸', '🇩🇪', '🇮🇹', '🇪🇸', '🇵🇹', '🇧🇷', '🇯🇵', '🇰🇷', '🇨🇳', '🇮🇳', '🇷🇺', '🇦🇺', '🇨🇦', '🇲🇽', '🇦🇷', '🇨🇱', '🇨🇴', '🇵🇪', '🇻🇪', '🇪🇨', '🇧🇴', '🇵🇾', '🇺🇾', '🇳🇱', '🇧🇪', '🇨🇭', '🇦🇹', '🇵🇱', '🇬🇷', '🇹🇷', '🇪🇬', '🇿🇦', '🇳🇬', '🇰🇪', '🇲🇦', '🇹🇳', '🇩🇿', '🇸🇦', '🇦🇪', '🇮🇱', '🇮🇪', '🇸🇪', '🇳🇴'],
    masks: ['🎭', '👺', '👹', '👻', '💀', '☠️', '👽', '👾', '🤖', '🤡', '😈', '👿', '🙀', '😱', '😰', '🥶', '🥵', '🤯', '😎', '🥸', '🤓', '🧐', '🤠', '😷', '🤒', '🤕', '🤑', '🤥', '🤫', '🤭', '🥳', '🥴', '😵', '🤐', '😮', '😯', '😲', '😳', '🤪', '😜', '😝', '😛', '🤑', '😏', '😒', '🙄', '😬', '😮‍💨', '🥱', '😴'],
    gems: ['💎', '💍', '👑', '🔮', '🧿', '📿', '💠', '🔷', '🔶', '🔹', '🔸', '❄️', '💧', '🩵', '🩷', '🩶', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💝', '💖', '💗', '💓', '💕', '⭐', '🌟', '✨', '💫', '🪙', '💰', '💳', '🏆', '🎖️', '🏅', '🥇', '🥈', '🥉', '✳️', '❇️', '🔆', '🔅', '💡', '🌸', '🌺']
};

document.getElementById('teamName')?.addEventListener('input', function() {
    document.getElementById('nameCharCount').textContent = this.value.length;
});

document.querySelectorAll('.emblem-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.emblem-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const tabName = this.dataset.tab;
        document.getElementById('categoriesTab').style.display = tabName === 'categories' ? 'block' : 'none';
        document.getElementById('uploadTab').style.display = tabName === 'upload' ? 'block' : 'none';
    });
});

document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const category = this.dataset.category;
        const container = document.getElementById('emblemsContainer');
        const categoryEmblems = emblems[category] || [];
        
        container.innerHTML = categoryEmblems.map((emb, idx) => 
            `<div class="emblem-item" data-category="${category}" data-index="${idx + 1}">${emb}</div>`
        ).join('');
        
        document.querySelector('.emblem-categories').style.display = 'none';
        document.getElementById('emblemGrid').style.display = 'block';
        
        container.querySelectorAll('.emblem-item').forEach(item => {
            item.addEventListener('click', function() {
                selectEmblem(this.dataset.category, this.dataset.index, this.textContent);
            });
        });
    });
});

document.getElementById('backToCategories')?.addEventListener('click', function() {
    document.querySelector('.emblem-categories').style.display = 'grid';
    document.getElementById('emblemGrid').style.display = 'none';
});

function selectEmblem(category, index, emoji) {
    document.getElementById('emblemCategory').value = category;
    document.getElementById('emblemIndex').value = index;
    document.getElementById('customEmblem').value = '';
    document.getElementById('emblemPreview').innerHTML = `<span style="font-size: 3rem;">${emoji}</span>`;
    
    document.querySelectorAll('.emblem-item').forEach(item => item.classList.remove('selected'));
    document.querySelector(`.emblem-item[data-category="${category}"][data-index="${index}"]`)?.classList.add('selected');
    
    document.querySelector('.emblem-categories').style.display = 'grid';
    document.getElementById('emblemGrid').style.display = 'none';
}

const uploadZone = document.getElementById('uploadZone');
const uploadInput = document.getElementById('emblemUpload');

uploadZone?.addEventListener('click', () => uploadInput?.click());
uploadZone?.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});
uploadZone?.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone?.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    const file = e.dataTransfer?.files[0];
    if (file) handleImageUpload(file);
});

uploadInput?.addEventListener('change', function() {
    if (this.files[0]) handleImageUpload(this.files[0]);
});

function handleImageUpload(file) {
    if (file.size > 2 * 1024 * 1024) {
        alert('{{ __("L\'image ne doit pas dépasser 2MB") }}');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('customEmblem').value = e.target.result;
        document.getElementById('emblemPreview').innerHTML = `<img src="${e.target.result}" alt="Custom emblem">`;
    };
    reader.readAsDataURL(file);
}

document.getElementById('createTeamBtn')?.addEventListener('click', async function() {
    const name = document.getElementById('teamName').value.trim();
    const emblemCategory = document.getElementById('emblemCategory').value;
    const emblemIndex = document.getElementById('emblemIndex').value;
    const customEmblem = document.getElementById('customEmblem').value;
    
    if (!name) {
        showError('{{ __("Veuillez entrer un nom d\'équipe") }}');
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<span class="spinner"></span> {{ __("Création...") }}';
    
    try {
        const response = await fetch('{{ route("league.team.create.submit") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                name: name,
                emblem_category: emblemCategory,
                emblem_index: parseInt(emblemIndex),
                custom_emblem: customEmblem || null
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.team);
        } else {
            showError(data.error || '{{ __("Erreur lors de la création") }}');
            this.disabled = false;
            this.innerHTML = '<span class="btn-icon">⚔️</span> {{ __("CRÉER L\'ÉQUIPE") }}';
        }
    } catch (error) {
        showError('{{ __("Erreur de connexion") }}');
        this.disabled = false;
        this.innerHTML = '<span class="btn-icon">⚔️</span> {{ __("CRÉER L\'ÉQUIPE") }}';
    }
});

function showError(message) {
    const errorDiv = document.getElementById('createError');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

function showSuccess(team) {
    document.querySelector('.create-team-form').style.display = 'none';
    document.getElementById('createError').style.display = 'none';
    
    const emblemPreview = document.getElementById('emblemPreview').innerHTML;
    document.getElementById('createdEmblem').innerHTML = emblemPreview;
    document.getElementById('createdTeamName').textContent = team.name;
    document.getElementById('createdTeamId').textContent = '#' + String(team.id).padStart(6, '0');
    
    document.getElementById('successSection').style.display = 'block';
}

function resetForm() {
    document.querySelector('.create-team-form').style.display = 'block';
    document.getElementById('successSection').style.display = 'none';
    document.getElementById('teamName').value = '';
    document.getElementById('nameCharCount').textContent = '0';
    document.getElementById('emblemPreview').innerHTML = '<div class="emblem-placeholder">🛡️</div>';
    document.getElementById('emblemCategory').value = 'animals';
    document.getElementById('emblemIndex').value = '1';
    document.getElementById('customEmblem').value = '';
    document.getElementById('createTeamBtn').disabled = false;
    document.getElementById('createTeamBtn').innerHTML = '<span class="btn-icon">⚔️</span> {{ __("CRÉER L\'ÉQUIPE") }}';
}
</script>
@endsection
