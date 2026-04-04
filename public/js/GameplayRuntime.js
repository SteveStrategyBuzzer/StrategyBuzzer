/**
 * GameplayRuntime.js — Central orchestration for all gameplay pages using layouts.game
 *
 * Responsibilities:
 *   • Establish ONE Socket.IO connection per page load via DuoSocketClient
 *   • Join the game room once (from window.ROOM_ID, window.JWT_TOKEN, etc.)
 *   • Show/hide #brainOverlay based on phase (INTRO → show, WAITING → show, others → hide)
 *   • Show #loadingOverlay while connecting; hide on connect
 *   • Update game-header scores in real-time (#ghPlayerScore, #ghOpponentScore, counters)
 *   • Expose helpers: showBrainSpin, hideBrainSpin, showLoading, hideLoading, setConnectionStatus
 *
 * Views MUST NOT call DuoSocketClient.connect() or DuoSocketClient.joinRoom() — this module owns them.
 * Views use DuoSocketClient.on(event, handler) for their own logic (buzz, redirect, etc.).
 *
 * Required window variables (set in @section('game-data') before this script):
 *   window.ROOM_ID          — Socket.IO room identifier
 *   window.JWT_TOKEN        — Authentication token
 *   window.LOBBY_CODE       — Optional lobby code (null if not needed)
 *   window.CURRENT_USER_ID  — Authenticated player's DB id (string)
 *   window.TOTAL_QUESTIONS  — Total questions in the match (default 10)
 *   window.NO_SOCKET_OVERLAY — If true, skip loading overlay (e.g. game_intro, duo_waiting)
 *
 * Guard: window.__GR_INITIALIZED prevents double-init on hot reload scenarios.
 */
(function () {
    'use strict';

    if (window.__GR_INITIALIZED) return;
    window.__GR_INITIALIZED = true;

    // UI helpers

    function showBrainSpin(msg) {
        var overlay = document.getElementById('brainOverlay');
        var msgEl   = document.getElementById('brainMessage');
        if (!overlay) return;
        if (msgEl && msg !== undefined) msgEl.textContent = msg;
        overlay.classList.remove('hidden');
    }

    function hideBrainSpin() {
        var overlay = document.getElementById('brainOverlay');
        if (overlay) overlay.classList.add('hidden');
    }

    function showLoading(msg) {
        var overlay = document.getElementById('loadingOverlay');
        var textEl  = document.getElementById('loadingText');
        if (!overlay) return;
        if (msg && textEl) textEl.textContent = msg;
        overlay.classList.remove('hidden');
    }

    function hideLoading() {
        var overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.classList.add('hidden');
    }

    function setConnectionStatus(state, msg) {
        var el = document.getElementById('connectionStatus');
        if (!el) return;
        el.className = 'connection-status ' + (state || '');
        if (msg) el.textContent = msg;
    }

    // Game header score/counter update

    function updateHeaderScores(playerScore, opponentScore) {
        var pEl = document.getElementById('ghPlayerScore');
        var oEl = document.getElementById('ghOpponentScore');
        if (pEl && playerScore !== undefined && playerScore !== null) pEl.textContent = playerScore;
        if (oEl && opponentScore !== undefined && opponentScore !== null) oEl.textContent = opponentScore;
    }

    function updateHeaderCounter(current, total) {
        var el = document.getElementById('ghQuestionCounter');
        if (!el) return;
        if (total !== undefined) {
            el.textContent = (current || 1) + '/' + total;
        } else {
            el.textContent = current || 1;
        }
    }

    function updateHeaderRound(round) {
        var el = document.getElementById('ghRound');
        if (el && round !== undefined) {
            el.textContent = (window.GR_LABELS && window.GR_LABELS.round ? window.GR_LABELS.round : 'Manche') + ' ' + round;
        }
    }

    // Phase → brain overlay mapping

    var PHASE_BRAIN = {
        INTRO:            { show: true,  msg: null },
        WAITING:          { show: true,  msg: null },
        ROUND_SCOREBOARD: { show: true,  msg: null },
        LOBBY:            { show: false },
        QUESTION_ACTIVE:  { show: false },
        ANSWER_SELECTION: { show: false },
        REVEAL:           { show: false },
        MATCH_END:        { show: false },
        FINISHED:         { show: false },
    };

    function handleBrainForPhase(phase) {
        var cfg = PHASE_BRAIN[phase] || { show: false };
        if (cfg.show) {
            var labels = window.GR_LABELS || {};
            var msg;
            if (phase === 'INTRO') {
                msg = labels.preparing || 'Préparation...';
            } else if (phase === 'ROUND_SCOREBOARD') {
                msg = labels.roundEnd || 'Fin du round...';
            } else {
                msg = labels.nextQuestion || 'Prochaine question...';
            }
            showBrainSpin(msg);
        } else {
            hideBrainSpin();
        }
    }

    // Expose global API for view scripts

    window.showBrainSpin       = showBrainSpin;
    window.hideBrainSpin       = hideBrainSpin;
    window.showLoading         = showLoading;
    window.hideLoading         = hideLoading;
    window.setConnectionStatus = setConnectionStatus;
    window.GRUpdateScores      = updateHeaderScores;
    window.GRUpdateCounter     = updateHeaderCounter;
    window.GRUpdateRound       = updateHeaderRound;

    // Socket initialization — only if ROOM_ID + JWT_TOKEN present

    var ROOM_ID         = window.ROOM_ID || null;
    var JWT_TOKEN       = window.JWT_TOKEN || null;
    var LOBBY_CODE      = window.LOBBY_CODE || null;
    var USER_ID         = window.CURRENT_USER_ID ? String(window.CURRENT_USER_ID) : null;
    var PLAYER_NAME     = window.PLAYER_NAME || null;
    var PLAYER_INFO     = window.PLAYER_INFO || {};
    var TOTAL_QUESTIONS = window.TOTAL_QUESTIONS || 10;
    var NO_OVERLAY      = !!window.NO_SOCKET_OVERLAY;
    var HIDE_HEADER     = !!window.GR_HIDE_HEADER;

    if (HIDE_HEADER) {
        var hdr = document.getElementById('gameHeader');
        if (hdr) hdr.style.display = 'none';
    }

    if (!ROOM_ID || !JWT_TOKEN) {
        // No gameplay session — overlays stay hidden, done.
        return;
    }

    var socket = window.DuoSocketClient;
    if (!socket) {
        console.error('[GameplayRuntime] DuoSocketClient not found');
        return;
    }

    // Show loading overlay while connecting (skip for pages that show their own content)
    if (!NO_OVERLAY) {
        showLoading();
        setConnectionStatus('connecting', window.GR_LABELS && window.GR_LABELS.connecting ? window.GR_LABELS.connecting : 'Connexion...');
    } else {
        setConnectionStatus('connecting', window.GR_LABELS && window.GR_LABELS.connecting ? window.GR_LABELS.connecting : 'Connexion...');
    }

    // Connect — DuoSocketClient.connect() is idempotent (safe to call even if already connected)
    var gameServerUrl = window.GAME_SERVER_URL || window.location.origin;

    socket.connect(gameServerUrl, JWT_TOKEN).then(function () {
        console.log('[GameplayRuntime] Connected to game server');
    }).catch(function (err) {
        console.warn('[GameplayRuntime] Connect error:', err);
        if (!NO_OVERLAY) hideLoading();
        setConnectionStatus('disconnected', 'Déconnecté');
    });

    socket.on('connect', function () {
        if (!NO_OVERLAY) hideLoading();
        setConnectionStatus('connected', window.GR_LABELS && window.GR_LABELS.connected ? window.GR_LABELS.connected : 'Connecté');

        // Join room — pass player metadata (merge window.PLAYER_INFO for extra fields like avatarId)
        var joinPayload = Object.assign({
            playerId: USER_ID || '',
            token: JWT_TOKEN
        }, PLAYER_INFO);
        if (PLAYER_NAME) joinPayload.playerName = PLAYER_NAME;
        socket.joinRoom(ROOM_ID, LOBBY_CODE, joinPayload);
        console.log('[GameplayRuntime] Joined room:', ROOM_ID);
    });

    socket.on('disconnect', function (reason) {
        setConnectionStatus('disconnected', window.GR_LABELS && window.GR_LABELS.disconnected ? window.GR_LABELS.disconnected : 'Déconnecté');
        console.warn('[GameplayRuntime] Disconnected:', reason);
    });

    // state: initial hydration and reconnect (server emits { state: GameState })
    socket.on('state', function (payload) {
        if (!payload) return;
        var data = payload.state || payload;

        // Score from players roster (keyed by player ID)
        if (data.players && USER_ID) {
            var playerEntry   = data.players[USER_ID];
            var opponentEntry = null;
            Object.keys(data.players).forEach(function (pid) {
                if (pid !== USER_ID) opponentEntry = data.players[pid];
            });
            if (playerEntry)   updateHeaderScores(playerEntry.score, undefined);
            if (opponentEntry) updateHeaderScores(undefined, opponentEntry.score);
        }

        // Question counter
        if (data.questionIndex !== undefined) {
            updateHeaderCounter(data.questionIndex + 1, data.totalQuestions || TOTAL_QUESTIONS);
        }

        // Round
        if (data.currentRound !== undefined) {
            updateHeaderRound(data.currentRound);
        }

        // Phase → brain (skip on intro page to avoid covering content on first join)
        if (data.phase && !HIDE_HEADER) {
            handleBrainForPhase(data.phase);
        }
    });

    socket.on('phase_changed', function (data) {
        if (!data || !data.phase) return;
        handleBrainForPhase(data.phase);

        // Update counter from phase data if available
        if (data.questionIndex !== undefined) {
            updateHeaderCounter(data.questionIndex + 1, TOTAL_QUESTIONS);
        }
        if (data.roundNumber !== undefined) {
            updateHeaderRound(data.roundNumber);
        }
    });

    socket.on('score_update', function (data) {
        if (!data || !USER_ID) return;

        // Format: { playerId, score, roundScore, delta }
        if (data.playerId !== undefined) {
            if (String(data.playerId) === USER_ID) {
                updateHeaderScores(data.score, undefined);
            } else {
                updateHeaderScores(undefined, data.score);
            }
        }
    });

})();
