/**
 * STRATEGYBUZZER - MultiplayerFirestoreProvider.js
 * Provider Firebase (Modular SDK v10) pour la synchronisation multijoueur
 * 
 * Utilisé par: Duo, Ligue Individuelle, Ligue Équipe, Maître du Jeu
 * 
 * Synchronisation:
 * - SYNC: Début de chaque question (tous voient la question au même moment)
 * - ASYNC: Pendant le jeu (buzz/réponse à son rythme)
 * - RE-SYNC: Page d'attente (attend que tous soient prêts)
 */

const MultiplayerFirestoreProvider = {
    db: null,
    sessionId: null,
    playerId: null,
    isHost: false,
    mode: 'duo',
    firestoreDoc: null,
    firestoreOnSnapshot: null,
    firestoreUpdateDoc: null,
    firestoreSetDoc: null,
    firestoreServerTimestamp: null,
    firestoreArrayUnion: null,
    unsubscribeQuestion: null,
    unsubscribeBuzz: null,
    unsubscribeReady: null,
    lastQuestionPublishedAt: 0,
    
    onQuestionReceived: null,
    onBuzzReceived: null,
    onOpponentReady: null,
    onAllPlayersReady: null,
    
    csrfToken: '',
    routes: {
        fetchQuestion: '/game/{mode}/fetch-question'
    },

    /**
     * Initialise le provider avec instances Firebase modulaires
     * @param {Object} config - Configuration
     * @param {Object} config.db - Firestore instance (from getFirestore)
     * @param {Function} config.doc - doc function from firebase/firestore
     * @param {Function} config.onSnapshot - onSnapshot function
     * @param {Function} config.updateDoc - updateDoc function
     * @param {Function} config.serverTimestamp - serverTimestamp function
     * @param {Function} config.arrayUnion - arrayUnion function
     */
    async init(config = {}) {
        this.sessionId = config.sessionId;
        this.playerId = String(config.playerId);
        this.isHost = config.isHost || false;
        this.mode = config.mode || 'duo';
        this.csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        this.db = config.db;
        this.firestoreDoc = config.doc;
        this.firestoreOnSnapshot = config.onSnapshot;
        this.firestoreUpdateDoc = config.updateDoc;
        this.firestoreSetDoc = config.setDoc;
        this.firestoreServerTimestamp = config.serverTimestamp;
        this.firestoreArrayUnion = config.arrayUnion;
        this.firestoreGetDoc = config.getDoc;
        
        if (config.routes) {
            this.routes = { ...this.routes, ...config.routes };
        }

        if (!this.sessionId) {
            console.error('[MultiplayerFirestoreProvider] No sessionId provided');
            return false;
        }

        if (!this.db || !this.firestoreDoc || !this.firestoreOnSnapshot) {
            console.error('[MultiplayerFirestoreProvider] Firebase instances not provided');
            return false;
        }

        console.log('[MultiplayerFirestoreProvider] Initialized', { 
            sessionId: this.sessionId, 
            isHost: this.isHost,
            mode: this.mode,
            playerId: this.playerId
        });
        
        return true;
    },

    /**
     * Obtient la référence du document de session
     */
    getSessionRef() {
        const firestoreGameId = `${this.mode}-match-${this.sessionId}`;
        return this.firestoreDoc(this.db, 'games', firestoreGameId);
    },

    /**
     * Écoute les questions publiées par l'hôte
     */
    listenForQuestions(callback) {
        if (!this.db || !this.sessionId) return;

        this.onQuestionReceived = callback;
        const sessionRef = this.getSessionRef();

        this.unsubscribeQuestion = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
            if (!snapshot.exists()) return;
            
            const data = snapshot.data();
            
            const questionPublishedAt = data.questionPublishedAt?.toMillis?.() || data.questionPublishedAt || 0;
            if (data.currentQuestionData && questionPublishedAt > this.lastQuestionPublishedAt) {
                this.lastQuestionPublishedAt = questionPublishedAt;
                const questionData = data.currentQuestionData;
                const questionNumber = data.currentQuestion || questionData.question_number || 1;
                
                console.log('[MultiplayerFirestoreProvider] Question received:', questionNumber, 'at', questionPublishedAt);
                
                if (this.onQuestionReceived) {
                    this.onQuestionReceived(questionData, questionNumber);
                }
            }
        }, (error) => {
            console.error('[MultiplayerFirestoreProvider] Question listener error:', error);
        });

        console.log('[MultiplayerFirestoreProvider] Listening for questions on:', this.getSessionRef().path);
    },

    /**
     * Publie une question (hôte uniquement)
     * Utilise setDoc avec merge:true pour créer le document s'il n'existe pas
     */
    async publishQuestion(questionData, questionNumber) {
        if (!this.isHost || !this.db || !this.sessionId) {
            console.warn('[MultiplayerFirestoreProvider] Not host or not initialized');
            return false;
        }

        try {
            const sessionRef = this.getSessionRef();
            const updateData = {
                currentQuestionData: questionData,
                questionPublishedAt: this.firestoreServerTimestamp(),
                currentQuestion: questionNumber,
                phase: 'question',
                buzzedPlayerId: null,
                player1Buzzed: false,
                player2Buzzed: false,
                buzzTime: null,
                playersReady: [],
                player1Id: this.playerId,
                hostId: this.playerId,
                mode: this.mode,
                sessionId: this.sessionId
            };
            
            if (this.firestoreSetDoc) {
                await this.firestoreSetDoc(sessionRef, updateData, { merge: true });
            } else {
                await this.firestoreUpdateDoc(sessionRef, updateData);
            }

            console.log('[MultiplayerFirestoreProvider] Question published:', questionNumber);
            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Publish question error:', err);
            return false;
        }
    },

    /**
     * Récupère et publie une question (hôte uniquement)
     */
    async fetchAndPublishQuestion(questionNumber) {
        if (!this.isHost) {
            console.log('[MultiplayerFirestoreProvider] Not host, waiting for question via listener');
            return null;
        }

        try {
            const route = this.routes.fetchQuestion.replace('{mode}', this.mode);
            console.log('[MultiplayerFirestoreProvider] Fetching question', questionNumber, 'from', route);
            
            const response = await fetch(route, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ question_number: questionNumber })
            });

            const data = await response.json();
            
            if (data.success && data.question) {
                const correctIndex = data.question.answers.findIndex(a => a.is_correct === true);
                
                const questionData = {
                    question_number: data.question_number,
                    total_questions: data.total_questions,
                    question_text: data.question.question_text,
                    answers: data.question.answers.map(a => ({
                        text: a.text || a,
                        is_correct: a.is_correct || false
                    })),
                    correct_index: correctIndex,
                    theme: data.question.theme,
                    sub_theme: data.question.sub_theme,
                    chrono_time: data.chrono_time || 8
                };

                await this.publishQuestion(questionData, data.question_number);
                return { success: true, question: data.question, questionData };
            } else if (data.redirect_url) {
                return data;
            }

            console.error('[MultiplayerFirestoreProvider] Fetch failed:', data);
            return null;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Fetch question error:', err);
            return null;
        }
    },

    /**
     * Publie un buzz
     */
    async publishBuzz(buzzTime) {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.getSessionRef();
            
            const snapshot = await this.firestoreGetDoc(sessionRef);
            if (!snapshot.exists()) return false;
            
            const data = snapshot.data();
            
            if (data.buzzedPlayerId || data.player1Buzzed || data.player2Buzzed) {
                console.log('[MultiplayerFirestoreProvider] Someone already buzzed');
                return false;
            }

            const isPlayer1 = this.playerId === String(data.player1Id);
            const buzzField = isPlayer1 ? 'player1Buzzed' : 'player2Buzzed';

            await this.firestoreUpdateDoc(sessionRef, {
                buzzedPlayerId: this.playerId,
                [buzzField]: true,
                buzzTime: buzzTime,
                phase: 'buzz'
            });

            console.log('[MultiplayerFirestoreProvider] Buzz published:', this.playerId);
            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Publish buzz error:', err);
            return false;
        }
    },

    /**
     * Écoute les buzzs des autres joueurs
     */
    listenForBuzz(callback) {
        if (!this.db || !this.sessionId) return;

        this.onBuzzReceived = callback;
        const sessionRef = this.getSessionRef();

        this.unsubscribeBuzz = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
            if (!snapshot.exists()) return;
            
            const data = snapshot.data();
            const isPlayer1 = this.playerId === String(data.player1Id);
            
            let opponentBuzzed = false;
            if (isPlayer1 && data.player2Buzzed) {
                opponentBuzzed = true;
            } else if (!isPlayer1 && data.player1Buzzed) {
                opponentBuzzed = true;
            } else if (data.buzzedPlayerId && data.buzzedPlayerId !== this.playerId) {
                opponentBuzzed = true;
            }
            
            if (opponentBuzzed && this.onBuzzReceived) {
                console.log('[MultiplayerFirestoreProvider] Opponent buzzed');
                this.onBuzzReceived(data.buzzedPlayerId, data.buzzTime);
            }
        }, (error) => {
            console.error('[MultiplayerFirestoreProvider] Buzz listener error:', error);
        });
    },

    /**
     * Marque le joueur comme prêt pour la prochaine question
     */
    async markPlayerReady() {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.getSessionRef();
            const updateData = {
                playersReady: this.firestoreArrayUnion(this.playerId)
            };
            
            if (this.firestoreSetDoc) {
                await this.firestoreSetDoc(sessionRef, updateData, { merge: true });
            } else {
                await this.firestoreUpdateDoc(sessionRef, updateData);
            }

            console.log('[MultiplayerFirestoreProvider] Player marked ready:', this.playerId);
            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Mark ready error:', err);
            return false;
        }
    },

    /**
     * Écoute quand tous les joueurs sont prêts
     * @returns {Function} Unsubscribe function
     */
    listenForAllReady(expectedPlayers, callback) {
        if (!this.db || !this.sessionId) {
            callback();
            return () => {};
        }

        const sessionRef = this.getSessionRef();
        let hasResolved = false;

        const unsubscribe = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
            if (!snapshot.exists() || hasResolved) return;
            
            const data = snapshot.data();
            const playersReady = data.playersReady || [];
            
            let actualExpectedPlayers = expectedPlayers;
            if (data.player1Id && data.player2Id) {
                actualExpectedPlayers = 2;
            } else if (data.roster && Array.isArray(data.roster)) {
                actualExpectedPlayers = data.roster.length;
            }
            
            console.log('[MultiplayerFirestoreProvider] Players ready:', playersReady.length, '/', actualExpectedPlayers);
            
            if (playersReady.length >= actualExpectedPlayers) {
                hasResolved = true;
                console.log('[MultiplayerFirestoreProvider] All players ready, unsubscribing');
                unsubscribe();
                callback();
            }
        }, (error) => {
            console.error('[MultiplayerFirestoreProvider] Ready listener error:', error);
            if (!hasResolved) {
                hasResolved = true;
                callback();
            }
        });
        
        return unsubscribe;
    },

    /**
     * Met à jour le score d'un joueur
     */
    async updateScore(score) {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.getSessionRef();
            const snapshot = await this.firestoreGetDoc(sessionRef);
            if (!snapshot.exists()) return false;
            
            const data = snapshot.data();
            const isPlayer1 = this.playerId === String(data.player1Id);
            const scoreField = isPlayer1 ? 'player1Score' : 'player2Score';
            
            await this.firestoreUpdateDoc(sessionRef, {
                [scoreField]: score
            });

            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Update score error:', err);
            return false;
        }
    },

    /**
     * Écoute les scores adverses
     */
    listenForScores(callback) {
        if (!this.db || !this.sessionId) return;
        
        const sessionRef = this.getSessionRef();

        this.firestoreOnSnapshot(sessionRef, (snapshot) => {
            if (!snapshot.exists()) return;
            
            const data = snapshot.data();
            const isPlayer1 = this.playerId === String(data.player1Id);
            const opponentScore = isPlayer1 ? data.player2Score : data.player1Score;
            
            if (opponentScore !== undefined && callback) {
                callback(opponentScore);
            }
        });
    },

    /**
     * Nettoie les listeners
     */
    cleanup() {
        if (this.unsubscribeQuestion) {
            this.unsubscribeQuestion();
            this.unsubscribeQuestion = null;
        }
        if (this.unsubscribeBuzz) {
            this.unsubscribeBuzz();
            this.unsubscribeBuzz = null;
        }
        if (this.unsubscribeReady) {
            this.unsubscribeReady();
            this.unsubscribeReady = null;
        }
        console.log('[MultiplayerFirestoreProvider] Cleaned up');
    }
};

if (typeof window !== 'undefined') {
    window.MultiplayerFirestoreProvider = MultiplayerFirestoreProvider;
}
