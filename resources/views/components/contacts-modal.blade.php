@props([
    'mode' => 'duo',
    'maxSelection' => 1,
    'minSelection' => 1
])

@php
$selectionConfig = [
    'duo' => ['min' => 1, 'max' => 1, 'label' => __('INVITER LE JOUEUR')],
    'league' => ['min' => 1, 'max' => 5, 'label' => __('INVITER LES JOUEURS')],
    'master' => ['min' => 2, 'max' => 40, 'label' => __('INVITER LES JOUEURS')],
];
$config = $selectionConfig[$mode] ?? $selectionConfig['duo'];
@endphp

<div id="contactsModal" class="modal-backdrop" style="display: none;">
    <div class="modal-content contacts-modal">
        <div class="modal-header">
            <h2>📒 {{ __('CARNET DE JOUEURS') }}</h2>
            <button class="modal-close" onclick="closeContactsModal()">&times;</button>
        </div>
        
        <div class="contacts-tabs">
            <button class="contacts-tab active" onclick="switchContactsTab('players')">👤 {{ __('Joueurs') }}</button>
            <button class="contacts-tab" onclick="switchContactsTab('groups')">👥 {{ __('Groupes') }}</button>
        </div>
        
        <div id="selectedCount" class="selected-count" style="display: none;">
            <span id="selectedCountText">0</span> / {{ $config['max'] }} {{ __('sélectionnés') }}
        </div>
        
        <button id="inviteSelectedBtn" class="btn-invite-selected" disabled>
            {{ $config['label'] }} (<span id="inviteCount">0</span>)
        </button>
        
        <div id="playersTab" class="contacts-tab-content">
            <div id="contactsList" class="contacts-list">
                <p class="loading-contacts">{{ __('Chargement...') }}</p>
            </div>
        </div>
        
        <div id="groupsTab" class="contacts-tab-content" style="display: none;">
            <div class="group-create-section">
                <input type="text" id="newGroupName" class="group-name-input" placeholder="{{ __('Nom du nouveau groupe...') }}">
                <button class="btn-create-group" onclick="createGroup()">{{ __('Créer') }}</button>
            </div>
            <div id="groupsList" class="groups-list">
                <p class="loading-contacts">{{ __('Chargement...') }}</p>
            </div>
        </div>
    </div>
</div>

<style>
.contacts-tabs {
    display: flex;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin: 0 25px;
}

.contacts-tab {
    flex: 1;
    padding: 12px;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    font-size: 0.95em;
    transition: all 0.3s ease;
}

.contacts-tab.active {
    color: #fff;
    border-bottom: 2px solid #2196F3;
}

.contacts-tab:hover {
    color: #fff;
}

.contacts-tab-content {
    overflow-y: auto;
    max-height: 50vh;
}

.selected-count {
    text-align: center;
    padding: 10px;
    color: #2196F3;
    font-weight: bold;
}

.group-create-section {
    display: flex;
    gap: 10px;
    padding: 15px 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.group-name-input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    font-size: 0.95em;
}

.group-name-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.btn-create-group {
    padding: 10px 20px;
    background: #4CAF50;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.btn-create-group:hover {
    background: #45a049;
}

.groups-list {
    padding: 15px 25px;
}

.group-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.group-card:hover {
    background: rgba(255, 255, 255, 0.1);
}

.group-card.selected {
    background: rgba(33, 150, 243, 0.2);
    border: 2px solid #2196F3;
}

.group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.group-name {
    font-weight: bold;
    font-size: 1.1em;
}

.group-member-count {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9em;
}

.group-members-preview {
    margin-top: 8px;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85em;
}

.group-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.group-action-btn {
    padding: 5px 10px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 5px;
    color: #fff;
    cursor: pointer;
    font-size: 0.85em;
}

.group-action-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.group-action-btn.delete {
    background: rgba(244, 67, 54, 0.3);
}

.group-action-btn.delete:hover {
    background: rgba(244, 67, 54, 0.5);
}

.contact-checkbox.multi-select {
    width: 24px;
    height: 24px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.contact-checkbox.multi-select.selected {
    background: #2196F3;
    border-color: #2196F3;
}
</style>

<script>
const CONTACTS_MODE = '{{ $mode }}';
const MAX_SELECTION = {{ $config['max'] }};
const MIN_SELECTION = {{ $config['min'] }};

let selectedContacts = [];
let selectedGroups = [];
let allGroups = [];

function switchContactsTab(tab) {
    document.querySelectorAll('.contacts-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.contacts-tab-content').forEach(c => c.style.display = 'none');
    
    if (tab === 'players') {
        document.querySelector('.contacts-tab:first-child').classList.add('active');
        document.getElementById('playersTab').style.display = 'block';
    } else {
        document.querySelector('.contacts-tab:last-child').classList.add('active');
        document.getElementById('groupsTab').style.display = 'block';
        loadGroups();
    }
}

function toggleContactSelection(contactId) {
    const index = selectedContacts.indexOf(contactId);
    
    if (index > -1) {
        selectedContacts.splice(index, 1);
    } else {
        if (MAX_SELECTION === 1) {
            selectedContacts = [contactId];
        } else if (selectedContacts.length < MAX_SELECTION) {
            selectedContacts.push(contactId);
        } else {
            if (window.showToast) {
                showToast(`{{ __('Maximum') }} ${MAX_SELECTION} {{ __('joueurs') }}`);
            }
            return;
        }
    }
    
    updateContactsUI();
    updateInviteButton();
}

function toggleGroupSelection(groupId) {
    const group = allGroups.find(g => g.id === groupId);
    if (!group) return;
    
    const index = selectedGroups.indexOf(groupId);
    
    if (index > -1) {
        selectedGroups.splice(index, 1);
        group.members.forEach(m => {
            const idx = selectedContacts.indexOf(m.id);
            if (idx > -1) selectedContacts.splice(idx, 1);
        });
    } else {
        if (selectedContacts.length + group.members.length > MAX_SELECTION) {
            if (window.showToast) {
                showToast(`{{ __('Trop de joueurs dans ce groupe') }}`);
            }
            return;
        }
        selectedGroups.push(groupId);
        group.members.forEach(m => {
            if (!selectedContacts.includes(m.id)) {
                selectedContacts.push(m.id);
            }
        });
    }
    
    updateGroupsUI();
    updateContactsUI();
    updateInviteButton();
}

function updateContactsUI() {
    document.querySelectorAll('.contact-card').forEach(card => {
        const contactId = parseInt(card.dataset.contactId);
        const checkbox = card.querySelector('.contact-checkbox');
        
        if (selectedContacts.includes(contactId)) {
            checkbox.classList.add('selected');
            checkbox.innerHTML = '✓';
        } else {
            checkbox.classList.remove('selected');
            checkbox.innerHTML = '';
        }
    });
    
    const countEl = document.getElementById('selectedCount');
    const countText = document.getElementById('selectedCountText');
    if (MAX_SELECTION > 1) {
        countEl.style.display = 'block';
        countText.textContent = selectedContacts.length;
    }
}

function updateGroupsUI() {
    document.querySelectorAll('.group-card').forEach(card => {
        const groupId = parseInt(card.dataset.groupId);
        if (selectedGroups.includes(groupId)) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    });
}

function updateInviteButton() {
    const btn = document.getElementById('inviteSelectedBtn');
    const count = document.getElementById('inviteCount');
    count.textContent = selectedContacts.length;
    btn.disabled = selectedContacts.length < MIN_SELECTION;
}

function loadGroups() {
    const groupsList = document.getElementById('groupsList');
    groupsList.innerHTML = '<p class="loading-contacts">{{ __('Chargement...') }}</p>';
    
    fetch('/api/contacts/groups', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.groups.length > 0) {
            allGroups = data.groups;
            displayGroups(data.groups);
        } else {
            groupsList.innerHTML = '<p class="no-contacts">{{ __('Aucun groupe') }}<br>{{ __('Créez un groupe pour organiser vos contacts !') }}</p>';
        }
    })
    .catch(error => {
        console.error('Error loading groups:', error);
        groupsList.innerHTML = '<p class="no-contacts">{{ __('Erreur lors du chargement des groupes') }}</p>';
    });
}

function displayGroups(groups) {
    const groupsList = document.getElementById('groupsList');
    
    groupsList.innerHTML = groups.map(group => `
        <div class="group-card ${selectedGroups.includes(group.id) ? 'selected' : ''}" data-group-id="${group.id}" onclick="toggleGroupSelection(${group.id})">
            <div class="group-header">
                <span class="group-name">👥 ${group.name}</span>
                <span class="group-member-count">${group.member_count} {{ __('membre(s)') }}</span>
            </div>
            <div class="group-members-preview">
                ${group.members.slice(0, 3).map(m => m.name).join(', ')}${group.member_count > 3 ? '...' : ''}
            </div>
            <div class="group-actions" onclick="event.stopPropagation();">
                <button class="group-action-btn delete" onclick="deleteGroup(${group.id})">🗑️ {{ __('Supprimer') }}</button>
            </div>
        </div>
    `).join('');
}

function createGroup() {
    const nameInput = document.getElementById('newGroupName');
    const name = nameInput.value.trim();
    
    if (!name) {
        if (window.showToast) showToast('{{ __('Entrez un nom de groupe') }}');
        return;
    }
    
    fetch('/api/contacts/groups', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({ name: name, member_ids: selectedContacts })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            nameInput.value = '';
            loadGroups();
            if (window.showToast) showToast(data.message, 'success');
        } else {
            if (window.showToast) showToast(data.message || '{{ __('Erreur') }}');
        }
    })
    .catch(error => {
        console.error('Error creating group:', error);
        if (window.showToast) showToast('{{ __('Erreur') }}');
    });
}

async function deleteGroup(groupId) {
    if (window.customDialog) {
        const confirmed = await window.customDialog.confirm('{{ __('Supprimer ce groupe ?') }}', { danger: true });
        if (!confirmed) return;
    }
    
    fetch(`/api/contacts/groups/${groupId}`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadGroups();
            if (window.showToast) showToast(data.message, 'success');
        }
    })
    .catch(error => {
        console.error('Error deleting group:', error);
    });
}

function getSelectedContactIds() {
    return selectedContacts;
}
</script>
