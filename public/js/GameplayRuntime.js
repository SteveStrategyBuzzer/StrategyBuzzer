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
        INTRO:             { show: true,  msg: null },
        WAITING:           { show: true,  msg: null },
        SYNC:              { show: true,  msg: null },
        ROUND_SCOREBOARD:  { show: true,  msg: null },
        LOBBY:             { show: false },
        QUESTION_ACTIVE:   { show: false },
        ANSWER_SELECTION:  { show: false },
        ANSWER_COLLECTION: { show: false },
        RESULT:            { show: false },
        REVEAL:            { show: false },
        MATCH_END:         { show: false },
        FINISHED:          { show: false },
    };

    function handleBrainForPhase(phase) {
        // Some pages (e.g. duo_question) must never show the brain overlay —
        // it would cover the question content. window.NO_BRAIN_OVERLAY opts out.
        if (window.NO_BRAIN_OVERLAY) {
            hideBrainSpin();
            return;
        }
        var cfg = PHASE_BRAIN[phase] || { show: false };
        if (cfg.show) {
            var labels = window.GR_LABELS || {};
            var msg;
            if (phase === 'INTRO') {
                msg = labels.preparing || 'Préparation...';
            } else if (phase === 'ROUND_SCOREBOARD') {
                msg = labels.roundEnd || 'Fin du round...';
            } else if (phase === 'SYNC') {
                msg = labels.syncing || 'Synchronisation...';
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

    // Socket initialization — read from SB_GAME_CONTEXT first, fallback to legacy window.*

    var CTX = window.SB_GAME_CONTEXT || {};
    var ROOM_ID         = CTX.roomId        || window.ROOM_ID         || null;
    var JWT_TOKEN       = CTX.jwtToken      || window.JWT_TOKEN       || null;
    var LOBBY_CODE      = CTX.lobbyCode     || window.LOBBY_CODE      || null;
    var USER_ID         = (CTX.currentUserId || window.CURRENT_USER_ID) ? String(CTX.currentUserId || window.CURRENT_USER_ID) : null;
    var PLAYER_NAME     = CTX.playerName    || window.PLAYER_NAME     || null;
    var PLAYER_INFO     = CTX.playerInfo    || window.PLAYER_INFO     || {};
    var TOTAL_QUESTIONS = CTX.totalQuestions || window.TOTAL_QUESTIONS || 10;
    var NO_OVERLAY      = !!window.NO_SOCKET_OVERLAY;
    var HIDE_HEADER     = !!window.GR_HIDE_HEADER;

    // ── Bridge UI: restoreState on load ─────────────────────────────────────
    // On reconnect/cold-start, PHP may not have re-hydrated window globals.
    // Pull connection identifiers AND visual state from sessionStorage,
    // then publish them to canonical window.GR_RESTORED_* keys so pages
    // can render immediately before the socket state arrives.
    (function () {
        var ds = window.DuoSocketClient;
        if (!ds || !ds.restoreState) return;
        var saved = ds.restoreState();
        if (!saved) return;
        // Connection identifiers
        if (!ROOM_ID    && saved.room_id)    { ROOM_ID = saved.room_id;       window.ROOM_ID    = ROOM_ID; }
        if (!JWT_TOKEN  && saved.jwt_token)  { JWT_TOKEN = saved.jwt_token;   window.JWT_TOKEN  = JWT_TOKEN; }
        if (!LOBBY_CODE && saved.lobby_code) { LOBBY_CODE = saved.lobby_code; window.LOBBY_CODE = LOBBY_CODE; }
        if (!window.MATCH_ID && saved.match_id) { window.MATCH_ID = saved.match_id; }
        // Visual state — publish for immediate page rendering before socket state arrives
        if (saved.phase)         window.GR_RESTORED_PHASE          = saved.phase;
        if (saved.question_text) window.GR_RESTORED_QUESTION_TEXT  = saved.question_text;
        if (saved.choices)       window.GR_RESTORED_CHOICES        = saved.choices;
        if (saved.player_score  !== undefined) window.GR_RESTORED_PLAYER_SCORE   = saved.player_score;
        if (saved.opponent_score !== undefined) window.GR_RESTORED_OPPONENT_SCORE = saved.opponent_score;
        if (saved.phaseEndsAtMs) window.GR_RESTORED_PHASE_ENDS_AT  = saved.phaseEndsAtMs;
        console.log('[GameplayRuntime] restoreState applied', { phase: saved.phase, page: saved.current_page });
    })();

    // ── Build save payload: base identifiers + page-specific visual state ────
    function buildSavePayload() {
        var base = {
            match_id:     window.MATCH_ID   || '',
            room_id:      window.ROOM_ID    || '',
            lobby_code:   window.LOBBY_CODE || '',
            jwt_token:    window.JWT_TOKEN  || '',
            current_page: window.CURRENT_PAGE || '',
        };
        // Merge page-specific extra (set by each page via window.GR_SAVE_STATE_EXTRA)
        return Object.assign(base, window.GR_SAVE_STATE_EXTRA || {});
    }

    // ── Bridge UI: auto-save before any navigation ───────────────────────────
    // Acts as universal safety net that fires regardless of what triggers navigation.
    window.addEventListener('beforeunload', function () {
        var ds = window.DuoSocketClient;
        if (ds && ds.saveState && (window.MATCH_ID || window.ROOM_ID)) {
            ds.saveState(buildSavePayload());
        }
    });

    // ── Shared navigation helper (explicit save-then-redirect) ───────────────
    // All game-page redirect paths MUST call window.duoNavigate(url) instead of
    // setting window.location.href directly.  This guarantees the full payload
    // (including visual-state fields) is persisted before every page hop.
    window.duoNavigate = function (url) {
        var ds = window.DuoSocketClient;
        if (ds && ds.saveState && (window.MATCH_ID || window.ROOM_ID)) {
            ds.saveState(buildSavePayload());
        }
        window.location.href = url;
    };

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
        setConnectionStatus('', '');

        // Defensive guard — server JoinRoomSchema requires non-empty playerId & playerName.
        // Bail loudly here instead of letting the server reject with VALIDATION_ERROR.
        if (!USER_ID || !PLAYER_NAME) {
            console.error(
                '[GameplayRuntime] Cannot join_room — missing playerId or playerName. ' +
                'Make sure this view @includes partials.game-context with playerName set.',
                { roomId: ROOM_ID, hasUserId: !!USER_ID, hasPlayerName: !!PLAYER_NAME }
            );
            setConnectionStatus('disconnected', (window.GR_LABELS && window.GR_LABELS.configMissing) || 'Configuration manquante');
            return;
        }

        // Join room — pass player metadata (merge window.PLAYER_INFO for extra fields like avatarId)
        var joinPayload = Object.assign({
            playerId: USER_ID,
            playerName: PLAYER_NAME,
            token: JWT_TOKEN
        }, PLAYER_INFO);
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

        // ── Canonical page/phase mismatch reconciliation ─────────────────────
        // On reconnect the server may already be on a different phase than the
        // current Blade page. Navigate to the correct page immediately.
        var _page  = window.CURRENT_PAGE;
        var _phase = data.phase;
        if (_page && _phase && !window.__GR_MISMATCH_NAV) {
            // Map: page → { phase → window URL key }
            var _MAP = {
                question: {
                    // NOTE: answer phases (ANSWER_SELECTION, BUZZ_WINNER_ANSWERING,
                    // ANSWER_COLLECTION) are NOT mapped here because only the buzz winner
                    // should redirect to Answer — the role check requires lockedAnswerPlayerId
                    // from socket state, handled by the question page's own handleGameState.
                    RESULT:           'RESULT_URL',
                    ROUND_SCOREBOARD: 'ROUND_SCOREBOARD_URL',
                    MATCH_END:        'MATCH_RESULT_URL',
                    FINISHED:         'MATCH_RESULT_URL',
                },
                answer: {
                    // QUESTION_ACTIVE intentionally omitted: server allows it on the
                    // answer page (buzz winner navigated here while phase still QUESTION_ACTIVE).
                    // ANSWER_COLLECTION omitted: valid grace-period phase for answer page.
                    INTRO:            'QUESTION_URL',
                    WAITING:          'QUESTION_URL',
                    SYNC:             'QUESTION_URL',
                    RESULT:           'RESULT_URL',
                    ROUND_SCOREBOARD: 'ROUND_SCOREBOARD_URL',
                    MATCH_END:        'MATCH_RESULT_URL',
                    FINISHED:         'MATCH_RESULT_URL',
                },
                result: {
                    INTRO:            'QUESTION_URL',
                    WAITING:          'QUESTION_URL',
                    SYNC:             'QUESTION_URL',
                    QUESTION_ACTIVE:  'QUESTION_URL',
                    // Answer phases: redirect to question; question page handleGameState
                    // will then role-check lockedAnswerPlayerId and redirect buzz winner
                    // to answer (avoids ping-pong for non-buzz-winners).
                    ANSWER_SELECTION:      'QUESTION_URL',
                    BUZZ_WINNER_ANSWERING: 'QUESTION_URL',
                    ANSWER_COLLECTION:     'QUESTION_URL',
                    ROUND_SCOREBOARD: 'ROUND_SCOREBOARD_URL',
                    MATCH_END:        'MATCH_RESULT_URL',
                    FINISHED:         'MATCH_RESULT_URL',
                },
                'round-scoreboard': {
                    SYNC:             'QUESTION_URL',
                    QUESTION_ACTIVE:  'QUESTION_URL',
                    RESULT:           'RESULT_URL',
                    MATCH_END:        'MATCH_RESULT_URL',
                    FINISHED:         'MATCH_RESULT_URL',
                },
            };
            var _pageMap = _MAP[_page];
            if (_pageMap && _pageMap[_phase]) {
                var _targetKey = _pageMap[_phase];
                var _targetUrl = window[_targetKey];
                var _matchId   = window.MATCH_ID;
                if (_targetUrl) {
                    window.__GR_MISMATCH_NAV = true;
                    window.duoNavigate(_targetUrl + (_matchId ? '?match_id=' + encodeURIComponent(_matchId) : ''));
                }
            }
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

    // game_state: flat hydration on join (totalQuestions, currentQuestion, etc.)
    socket.on('game_state', function (data) {
        if (!data) return;

        if (data.players && USER_ID) {
            var gsPE   = data.players[USER_ID];
            var gsOpp  = null;
            Object.keys(data.players).forEach(function (pid) {
                if (pid !== USER_ID) gsOpp = data.players[pid];
            });
            if (gsPE)  updateHeaderScores(gsPE.score, undefined);
            if (gsOpp) updateHeaderScores(undefined, gsOpp.score);
        }
        if (data.questionIndex !== undefined) {
            updateHeaderCounter(data.questionIndex + 1, data.totalQuestions || TOTAL_QUESTIONS);
        }
        if (data.currentRound !== undefined) {
            updateHeaderRound(data.currentRound);
        }
        if (data.phase && !HIDE_HEADER) {
            handleBrainForPhase(data.phase);
        }
    });

    socket.on('score_update', function (data) {
        if (!data || !USER_ID) return;

        // Server format: { scores: { playerId: score }, roundScores: {...} }
        if (data.scores) {
            Object.keys(data.scores).forEach(function (pid) {
                if (String(pid) === USER_ID) {
                    updateHeaderScores(data.scores[pid], undefined);
                } else {
                    updateHeaderScores(undefined, data.scores[pid]);
                }
            });
            return;
        }
        // Legacy fallback: { playerId, score }
        if (data.playerId !== undefined) {
            if (String(data.playerId) === USER_ID) {
                updateHeaderScores(data.score, undefined);
            } else {
                updateHeaderScores(undefined, data.score);
            }
        }
    });

})();
