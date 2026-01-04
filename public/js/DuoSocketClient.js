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

    _log(message, data = null) {
        if (data) {
            console.log(`[DuoSocket] ${message}`, data);
        } else {
            console.log(`[DuoSocket] ${message}`);
        }
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
                    if (this.onConnect) this.onConnect();
                    resolve();
                });

                this.socket.on('disconnect', (reason) => {
                    this._log('Disconnected', { reason });
                    if (this.onDisconnect) this.onDisconnect(reason);
                });

                this.socket.on('connect_error', (error) => {
                    this._log('Connection error', { message: error.message });
                    this._reconnectAttempts++;
                    if (this.onError) this.onError({ code: 'CONNECT_ERROR', message: error.message });
                    if (this._reconnectAttempts >= this._maxReconnectAttempts) {
                        reject(error);
                    }
                });

                this.socket.on('error', (data) => {
                    this._log('Server error', data);
                    if (this.onError) this.onError(data);
                });

                this.socket.on('state', (data) => {
                    this._log('State received', data);
                    if (this.onState) this.onState(data.state);
                    if (this.onLobbyState) this.onLobbyState(data.state);
                });

                this.socket.on('phase_changed', (data) => {
                    this._log('Phase changed', data);
                    if (this.onPhaseChanged) this.onPhaseChanged(data);
                });

                this.socket.on('score_update', (data) => {
                    this._log('Score update', data);
                    if (this.onScoreUpdate) this.onScoreUpdate(data);
                });

                this.socket.on('event', (data) => {
                    this._log('Game event', data);
                    if (data.event) {
                        const event = data.event;
                        switch (event.type) {
                            case 'PLAYER_JOINED':
                                if (this.onPlayerJoined) this.onPlayerJoined(event);
                                break;
                            case 'PLAYER_LEFT':
                                if (this.onPlayerLeft) this.onPlayerLeft(event);
                                break;
                            case 'BUZZ':
                                if (this.onBuzzResult) this.onBuzzResult(event);
                                break;
                            default:
                                this._log('Unhandled event type', event.type);
                        }
                    }
                });

                this.socket.on('player_ready', (data) => {
                    this._log('Player ready', data);
                    if (this.onPlayerReady) this.onPlayerReady(data);
                });

                this.socket.on('answer_received', (data) => {
                    this._log('Answer received', data);
                    if (this.onAnswerResult) this.onAnswerResult(data);
                });

                this.socket.on('voice_offer', (data) => {
                    this._log('Voice offer received', data);
                    if (this.onVoiceOffer) this.onVoiceOffer(data.from, data.offer);
                });

                this.socket.on('voice_answer', (data) => {
                    this._log('Voice answer received', data);
                    if (this.onVoiceAnswer) this.onVoiceAnswer(data.from, data.answer);
                });

                this.socket.on('voice_ice_candidate', (data) => {
                    this._log('ICE candidate received', data);
                    if (this.onIceCandidate) this.onIceCandidate(data.from, data.candidate);
                });

                this.socket.on('pong_check', (data) => {
                    this._log('Pong received', data);
                });

                this.socket.on('game_started', (data) => {
                    this._log('Game started', data);
                    if (this.onGameStarted) this.onGameStarted(data);
                });

                this.socket.on('question_published', (data) => {
                    this._log('Question published', data);
                    if (this.onQuestionPublished) this.onQuestionPublished(data);
                });

                this.socket.on('buzz_winner', (data) => {
                    this._log('Buzz winner', data);
                    if (this.onBuzzWinner) this.onBuzzWinner(data);
                });

                this.socket.on('answer_revealed', (data) => {
                    this._log('Answer revealed', data);
                    if (this.onAnswerRevealed) this.onAnswerRevealed(data);
                });

                this.socket.on('round_ended', (data) => {
                    this._log('Round ended', data);
                    if (this.onRoundEnded) this.onRoundEnded(data);
                });

                this.socket.on('match_ended', (data) => {
                    this._log('Match ended', data);
                    if (this.onMatchEnded) this.onMatchEnded(data);
                });

                this.socket.on('skill_used', (data) => {
                    this._log('Skill used', data);
                    if (this.onSkillUsed) this.onSkillUsed(data);
                });

            } catch (error) {
                this._log('Failed to create socket', { error: error.message });
                reject(error);
            }
        });
    },

    disconnect() {
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
            avatarId: playerInfo.avatarId,
            strategicAvatarId: playerInfo.strategicAvatarId,
            division: playerInfo.division,
            token: playerInfo.token
        };

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

    getLatestPing() {
        return this.latestPing;
    },

    on(eventName, callback) {
        if (this.socket) {
            this.socket.on(eventName, callback);
        }
    },

    off(eventName, callback) {
        if (this.socket) {
            this.socket.off(eventName, callback);
        }
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
