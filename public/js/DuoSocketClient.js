/**
 * STRATEGYBUZZER - DuoSocketClient.js
 * Socket.IO client module for Duo mode real-time gameplay
 * Replaces Firebase-based real-time communication
 * 
 * Requires Socket.IO client CDN in HTML:
 * <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
 * 
 * Usage:
 *   duoSocket.connect('wss://game-server.example.com', jwtToken);
 *   duoSocket.onConnect = () => console.log('Connected!');
 *   duoSocket.joinRoom(roomId, lobbyCode);
 */

const DuoSocketClient = {
    socket: null,
    currentRoomId: null,
    latestPing: 0,
    _reconnectAttempts: 0,
    _maxReconnectAttempts: 5,
    _timeSyncInterval: null,
    _clockOffsetMs: 0,
    _handlers: {},

    onConnect: null,
    onDisconnect: null,
    onError: null,

    onPlayerJoined: null,
    onPlayerLeft: null,
    onPlayerReady: null,
    onLobbyState: null,

    onState: null,
    onPhaseChanged: null,
    onScoreUpdate: null,
    onBuzzResult: null,
    onAnswerResult: null,

    onVoiceOffer: null,
    onVoiceAnswer: null,
    onIceCandidate: null,

    onGameStarted: null,
    onQuestionPublished: null,
    onBuzzWinner: null,
    onAnswerRevealed: null,
    onRoundEnded: null,
    onMatchEnded: null,
    onSkillUsed: null,
    onSkillActivated: null,
    onSkillFailed: null,
    onRateLimited: null,
    onWaitingBlock: null,
    onGameState: null,

    _log(message, data = null) {
        if (data) {
            console.log(`[DuoSocket] ${message}`, data);
        } else {
            console.log(`[DuoSocket] ${message}`);
        }
    },

    _dispatch(eventName, payload) {
        const handlers = this._handlers[eventName] || [];
        handlers.forEach((handler) => {
            try {
                handler(payload);
            } catch (error) {
                console.error(`[DuoSocket] Handler error for ${eventName}:`, error);
            }
        });
    },

    _bindSocketEvent(eventName, socketHandler) {
        if (!this.socket) {
            return;
        }

        this.socket.on(eventName, (payload) => {
            socketHandler(payload);
            this._dispatch(eventName, payload);
        });
    },

    connect(url, token = null) {
        if (this.socket && this.socket.connected) {
            this._log('Already connected');
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            try {
                const options = {
                    transports: ['websocket', 'polling'],
                    reconnection: true,
                    reconnectionAttempts: this._maxReconnectAttempts,
                    reconnectionDelay: 1000,
                    reconnectionDelayMax: 5000,
                    timeout: 20000,
                };

                if (token) {
                    options.auth = { token };
                }

                this.socket = io(url, options);

                this.socket.on('connect', () => {
                    this._log('Connected', { id: this.socket.id });
                    this._reconnectAttempts = 0;

                    this.syncTime();
                    if (this._timeSyncInterval) clearInterval(this._timeSyncInterval);
                    this._timeSyncInterval = setInterval(() => this.syncTime(), 30000);

                    if (this.onConnect) this.onConnect();
                    this._dispatch('connect', { id: this.socket.id });

                    resolve();
                });

                this.socket.on('disconnect', (reason) => {
                    this._log('Disconnected', { reason });
                    if (this.onDisconnect) this.onDisconnect(reason);
                    this._dispatch('disconnect', reason);
                });

                this.socket.on('connect_error', (error) => {
                    this._log('Connection error', { message: error.message });
                    this._reconnectAttempts++;
                    const errorPayload = { code: 'CONNECT_ERROR', message: error.message };
                    if (this.onError) this.onError(errorPayload);
                    this._dispatch('error', errorPayload);

                    if (this._reconnectAttempts >= this._maxReconnectAttempts) {
                        reject(error);
                    }
                });

                this._bindSocketEvent('error', (data) => {
                    this._log('Server error', data);
                    if (this.onError) this.onError(data);
                });

                this._bindSocketEvent('state', (data) => {
                    this._log('State received', data);
                    if (this.onState) this.onState(data.state);
                    if (this.onLobbyState) this.onLobbyState(data.state);
                });

                this._bindSocketEvent('phase_changed', (data) => {
                    this._log('Phase changed', data);
                    if (this.onPhaseChanged) this.onPhaseChanged(data);
                });

                this._bindSocketEvent('score_update', (data) => {
                    this._log('Score update', data);
                    if (this.onScoreUpdate) this.onScoreUpdate(data);
                });

                this._bindSocketEvent('event', (data) => {
                    this._log('Game event', data);
                    if (data.event) {
                        const event = data.event;
                        switch (event.type) {
                            case 'PLAYER_JOINED':
                                if (this.onPlayerJoined) this.onPlayerJoined(event);
                                this._dispatch('player_joined', event);
                                break;
                            case 'PLAYER_LEFT':
                                if (this.onPlayerLeft) this.onPlayerLeft(event);
                                this._dispatch('player_left', event);
                                break;
                            case 'BUZZ':
                                if (this.onBuzzResult) this.onBuzzResult(event);
                                this._dispatch('buzz', event);
                                break;
                            case 'PHASE_CHANGED':
                                this._dispatch('phase_changed', event);
                                break;
                            case 'QUESTION_PUBLISHED':
                                this._dispatch('question_published', event);
                                break;
                            case 'ROUND_ENDED':
                                this._dispatch('round_ended', event);
                                break;
                            case 'MATCH_ENDED':
                                this._dispatch('match_ended', event);
                                break;
                            default:
                                this._log('Unhandled event type', event.type);
                        }
                    }
                });

                this._bindSocketEvent('player_ready', (data) => {
                    this._log('Player ready', data);
                    if (this.onPlayerReady) this.onPlayerReady(data);
                });

                this._bindSocketEvent('answer_received', (data) => {
                    this._log('Answer received', data);
                    if (this.onAnswerResult) this.onAnswerResult(data);
                });

                this._bindSocketEvent('voice_offer', (data) => {
                    this._log('Voice offer received', data);
                    if (this.onVoiceOffer) this.onVoiceOffer(data.from, data.offer);
                });

                this._bindSocketEvent('voice_answer', (data) => {
                    this._log('Voice answer received', data);
                    if (this.onVoiceAnswer) this.onVoiceAnswer(data.from, data.answer);
                });

                this._bindSocketEvent('voice_ice_candidate', (data) => {
                    this._log('ICE candidate received', data);
                    if (this.onIceCandidate) this.onIceCandidate(data.from, data.candidate);
                });

                this._bindSocketEvent('pong_check', (data) => {
                    this._log('Pong received', data);
                });

                this._bindSocketEvent('game_started', (data) => {
                    this._log('Game started', data);
                    if (this.onGameStarted) this.onGameStarted(data);
                });

                this._bindSocketEvent('question_published', (data) => {
                    this._log('Question published', data);
                    if (this.onQuestionPublished) this.onQuestionPublished(data);
                });

                this._bindSocketEvent('buzz_winner', (data) => {
                    this._log('Buzz winner', data);
                    if (this.onBuzzWinner) this.onBuzzWinner(data);
                });

                this._bindSocketEvent('answer_revealed', (data) => {
                    this._log('Answer revealed', data);
                    if (this.onAnswerRevealed) this.onAnswerRevealed(data);
                });

                this._bindSocketEvent('round_ended', (data) => {
                    this._log('Round ended', data);
                    if (this.onRoundEnded) this.onRoundEnded(data);
                });

                this._bindSocketEvent('match_ended', (data) => {
                    this._log('Match ended', data);
                    if (this.onMatchEnded) this.onMatchEnded(data);
                });

                this._bindSocketEvent('skill_used', (data) => {
                    this._log('Skill used', data);
                    if (this.onSkillUsed) this.onSkillUsed(data);
                });

                this._bindSocketEvent('skill_activated', (data) => {
                    this._log('Skill activated', data);
                    if (this.onSkillActivated) this.onSkillActivated(data);
                });

                this._bindSocketEvent('skill_failed', (data) => {
                    this._log('Skill failed', data);
                    if (this.onSkillFailed) this.onSkillFailed(data);
                });

                this._bindSocketEvent('rate_limited', (data) => {
                    this._log('Rate limited', data);
                    if (this.onRateLimited) this.onRateLimited(data);
                });

                this._bindSocketEvent('time_sync_pong', (data) => {
                    const clientNowMs = Date.now();
                    const roundtripMs = clientNowMs - data.clientSentAtMs;
                    this._clockOffsetMs = data.serverSentAtMs - data.clientSentAtMs - Math.round(roundtripMs / 2);
                    this._log('Time sync', { offsetMs: this._clockOffsetMs, roundtripMs });
                });

                this._bindSocketEvent('waiting_block', (data) => {
                    this._log('Waiting block', data);
                    if (this.onWaitingBlock) this.onWaitingBlock(data);
                });

                this._bindSocketEvent('game_state', (data) => {
                    this._log('Game state received', data);
                    if (this.onGameState) this.onGameState(data);
                });

            } catch (error) {
                this._log('Failed to create socket', { error: error.message });
                reject(error);
            }
        });
    },

    disconnect() {
        if (this._timeSyncInterval) {
            clearInterval(this._timeSyncInterval);
            this._timeSyncInterval = null;
        }

        if (this.socket) {
            this._log('Disconnecting...');
            this.socket.disconnect();
            this.socket = null;
            this.currentRoomId = null;
        }
    },

    isConnected() {
        return this.socket && this.socket.connected;
    },

    joinRoom(roomId, lobbyCode = null, playerInfo = {}) {
        if (!this.isConnected()) {
            this._log('Cannot join room: not connected');
            return false;
        }

        const payload = {
            roomId: roomId || undefined,
            lobbyCode: lobbyCode || undefined,
            playerId: playerInfo.playerId || '',
            playerName: playerInfo.playerName || '',
            division: playerInfo.division,
            token: playerInfo.token
        };
        if (playerInfo.avatarId && typeof playerInfo.avatarId === 'string') {
            payload.avatarId = playerInfo.avatarId;
        }
        if (playerInfo.strategicAvatarId && typeof playerInfo.strategicAvatarId === 'string') {
            payload.strategicAvatarId = playerInfo.strategicAvatarId;
        }

        this._log('Joining room', payload);
        this.socket.emit('join_room', payload);
        this.currentRoomId = roomId || lobbyCode;
        return true;
    },

    setReady(isReady) {
        if (!this.isConnected() || !this.currentRoomId) {
            this._log('Cannot set ready: not connected or not in room');
            return false;
        }

        this._log('Setting ready status', { isReady });
        this.socket.emit('ready', {
            roomId: this.currentRoomId,
            isReady: isReady
        });
        return true;
    },

    buzz(clientTimeMs) {
        if (!this.isConnected() || !this.currentRoomId) {
            this._log('Cannot buzz: not connected or not in room');
            return false;
        }

        const timestamp = clientTimeMs || Date.now();
        this._log('Buzzing', { clientTimeMs: timestamp });
        this.socket.emit('buzz', {
            roomId: this.currentRoomId,
            clientTimeMs: timestamp
        });
        return true;
    },

    answer(answerValue) {
        if (!this.isConnected() || !this.currentRoomId) {
            this._log('Cannot answer: not connected or not in room');
            return false;
        }

        this._log('Submitting answer', { answer: answerValue });
        this.socket.emit('answer', {
            roomId: this.currentRoomId,
            answer: answerValue
        });
        return true;
    },

    useSkill(skillId, targetPlayerId = null) {
        if (!this.isConnected() || !this.currentRoomId) {
            this._log('Cannot use skill: not connected or not in room');
            return false;
        }

        this._log('Using skill', { skillId, targetPlayerId });
        this.socket.emit('skill', {
            roomId: this.currentRoomId,
            skillId: skillId,
            targetPlayerId: targetPlayerId
        });
        return true;
    },

    activateSkill(skillId, targetPlayerId = null) {
        return this.useSkill(skillId, targetPlayerId);
    },

    playerReady() {
        if (!this.isConnected() || !this.currentRoomId) {
            this._log('Cannot signal ready: not connected or not in room');
            return false;
        }

        this._log('Signaling player ready');
        this.socket.emit('player_ready', {
            roomId: this.currentRoomId
        });
        return true;
    },

    sendVoiceOffer(targetId, offer) {
        if (!this.isConnected() || !this.currentRoomId) {
            this._log('Cannot send voice offer: not connected or not in room');
            return false;
        }

        this._log('Sending voice offer', { targetId });
        this.socket.emit('voice_offer', {
            roomId: this.currentRoomId,
            targetId: targetId,
            offer: offer
        });
        return true;
    },

    sendVoiceAnswer(targetId, answer) {
        if (!this.isConnected() || !this.currentRoomId) {
            this._log('Cannot send voice answer: not connected or not in room');
            return false;
        }

        this._log('Sending voice answer', { targetId });
        this.socket.emit('voice_answer', {
            roomId: this.currentRoomId,
            targetId: targetId,
            answer: answer
        });
        return true;
    },

    sendIceCandidate(targetId, candidate) {
        if (!this.isConnected() || !this.currentRoomId) {
            this._log('Cannot send ICE candidate: not connected or not in room');
            return false;
        }

        this._log('Sending ICE candidate', { targetId });
        this.socket.emit('voice_ice_candidate', {
            roomId: this.currentRoomId,
            targetId: targetId,
            candidate: candidate
        });
        return true;
    },

    measurePing() {
        return new Promise((resolve, reject) => {
            if (!this.isConnected()) {
                reject(new Error('Not connected'));
                return;
            }

            const startTime = Date.now();

            const timeout = setTimeout(() => {
                this.socket.off('pong_check', handler);
                reject(new Error('Ping timeout'));
            }, 5000);

            const handler = (data) => {
                clearTimeout(timeout);
                const endTime = Date.now();
                const ping = endTime - data.clientTime;
                this.latestPing = ping;
                this._log('Ping measured', { ping: ping + 'ms' });
                resolve(ping);
            };

            this.socket.once('pong_check', handler);
            this.socket.emit('ping_check', { clientTime: startTime });
        });
    },

    syncTime() {
        if (!this.isConnected()) return;
        this.socket.emit('time_sync_ping', { clientSentAtMs: Date.now() });
    },

    getServerTime() {
        return Date.now() + this._clockOffsetMs;
    },

    getLatestPing() {
        return this.latestPing;
    },

    on(eventName, callback) {
        if (!this._handlers[eventName]) {
            this._handlers[eventName] = [];
        }

        this._handlers[eventName].push(callback);
        return true;
    },

    off(eventName, callback) {
        if (!this._handlers[eventName]) {
            return false;
        }

        this._handlers[eventName] = this._handlers[eventName].filter((handler) => handler !== callback);
        return true;
    }
};

const duoSocket = DuoSocketClient;

if (typeof window !== 'undefined') {
    window.duoSocket = duoSocket;
    window.DuoSocketClient = DuoSocketClient;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { DuoSocketClient, duoSocket };
}

