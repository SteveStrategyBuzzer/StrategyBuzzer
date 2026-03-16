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

        socket.on('skill_activated', (data) => {
            if (String(data.targetPlayerId) !== this._playerId) return;
            var effectId = data.effect || data.effectId || data.skillId;
            if (effectId) this._startEffect(effectId);
        });

        socket.on('game_state', (data) => {
            var state = data.state || data;
            if (state.activeEffects) {
                this._syncFromActiveEffects(state.activeEffects);
            }
        });

        socket.on('phase_changed', (data) => {
            if (data.activeEffects) {
                this._syncFromActiveEffects(data.activeEffects);
            }
        });

        socket.on('question_published', (data) => {
            if (data.activeEffects) {
                this._syncFromActiveEffects(data.activeEffects);
            }
        });

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
