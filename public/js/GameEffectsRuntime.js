/**
 * STRATEGYBUZZER - GameEffectsRuntime.js
 * Centralized runtime for real-time skill-effect wiring.
 *
 * Manages the lifecycle of visual/gameplay effects triggered by Socket.IO
 * events. Blade pages register pure DOM callbacks; this runtime handles
 * socket listening, idempotent start/stop, reconnect sync, and cleanup.
 *
 * Usage (in a Blade page):
 *   GameEffectsRuntime.registerEffect('shuffle_answers', {
 *       onStart() { ... },
 *       onStop()  { ... }
 *   });
 *   // After DuoSocketClient.connect() resolves:
 *   GameEffectsRuntime.init(DuoSocketClient, PLAYER_ID);
 */
const GameEffectsRuntime = {
    _socket: null,
    _playerId: null,
    _handlers: {},
    _running: {},
    _listeners: [],
    _initialized: false,

    registerEffect(effectId, handlers) {
        if (!handlers || typeof handlers.onStart !== 'function' || typeof handlers.onStop !== 'function') {
            console.warn('[GameEffectsRuntime] registerEffect requires { onStart, onStop }');
            return this;
        }
        this._handlers[effectId] = handlers;
        return this;
    },

    init(socket, playerId) {
        if (this._initialized) return;
        this._initialized = true;
        this._socket = socket;
        this._playerId = String(playerId);

        var self = this;

        var onSkill = function(data) {
            var eff = data.effect || {};
            var target = String(data.targetId || data.targetPlayerId || eff.targetPlayerId || '');
            if (target !== self._playerId) return;
            var effectId = data.skillId || data.effectId || eff.effectId || data.effect;
            if (typeof effectId === 'string') self._startEffect(effectId);
        };
        socket.on('skill_activated', onSkill);
        self._listeners.push(['skill_activated', onSkill]);

        var onGameState = function(data) {
            var state = data.state || data;
            if (state.activeEffects) self._syncFromActiveEffects(state.activeEffects);
        };
        socket.on('game_state', onGameState);
        self._listeners.push(['game_state', onGameState]);

        var onPhase = function(data) {
            if (data.activeEffects) self._syncFromActiveEffects(data.activeEffects);
        };
        socket.on('phase_changed', onPhase);
        self._listeners.push(['phase_changed', onPhase]);

        var onQuestion = function(data) {
            if (data.activeEffects) self._syncFromActiveEffects(data.activeEffects);
        };
        socket.on('question_published', onQuestion);
        self._listeners.push(['question_published', onQuestion]);

        console.log('[GameEffectsRuntime] Initialized for player', this._playerId,
            'with effects:', Object.keys(this._handlers).join(', ') || '(none)');
    },

    _syncFromActiveEffects(activeEffects) {
        var myEffects = {};
        for (var i = 0; i < activeEffects.length; i++) {
            var e = activeEffects[i];
            if (String(e.targetPlayerId) === this._playerId) {
                myEffects[e.effectId] = true;
            }
        }

        var effectId;
        for (effectId in myEffects) {
            if (myEffects.hasOwnProperty(effectId)) {
                this._startEffect(effectId);
            }
        }

        for (effectId in this._running) {
            if (this._running.hasOwnProperty(effectId) && this._running[effectId] && !myEffects[effectId]) {
                this._stopEffect(effectId);
            }
        }
    },

    _startEffect(effectId) {
        if (this._running[effectId]) return;
        var handler = this._handlers[effectId];
        if (!handler) return;
        this._running[effectId] = true;
        console.log('[GameEffectsRuntime] Starting effect:', effectId);
        try { handler.onStart(); } catch (err) {
            console.error('[GameEffectsRuntime] onStart error for', effectId, err);
        }
    },

    _stopEffect(effectId) {
        if (!this._running[effectId]) return;
        var handler = this._handlers[effectId];
        this._running[effectId] = false;
        console.log('[GameEffectsRuntime] Stopping effect:', effectId);
        if (handler) {
            try { handler.onStop(); } catch (err) {
                console.error('[GameEffectsRuntime] onStop error for', effectId, err);
            }
        }
    },

    isEffectRunning(effectId) {
        return !!this._running[effectId];
    },

    dispose() {
        for (var effectId in this._running) {
            if (this._running.hasOwnProperty(effectId)) {
                this._stopEffect(effectId);
            }
        }
        if (this._socket) {
            for (var i = 0; i < this._listeners.length; i++) {
                var pair = this._listeners[i];
                this._socket.off(pair[0], pair[1]);
            }
        }
        this._listeners = [];
        this._running = {};
        this._handlers = {};
        this._initialized = false;
        this._socket = null;
        this._playerId = null;
        console.log('[GameEffectsRuntime] Disposed');
    }
};

if (typeof window !== 'undefined') {
    window.GameEffectsRuntime = GameEffectsRuntime;
}
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { GameEffectsRuntime };
}
