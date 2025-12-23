/**
 * STRATEGYBUZZER - MultiplayerFirestoreProvider.js
 * Provider Firebase pour la synchronisation multijoueur
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
    unsubscribeQuestion: null,
    unsubscribeBuzz: null,
    unsubscribeReady: null,
    
    onQuestionReceived: null,
    onBuzzReceived: null,
    onOpponentReady: null,
    onAllPlayersReady: null,
    
    csrfToken: '',
    routes: {
        fetchQuestion: '/game/{mode}/fetch-question'
    },

    /**
     * Initialise le provider Firestore
     */
    async init(config = {}) {
        this.sessionId = config.sessionId;
        this.playerId = config.playerId;
        this.isHost = config.isHost || false;
        this.mode = config.mode || 'duo';
        this.csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        if (config.routes) {
            this.routes = { ...this.routes, ...config.routes };
        }

        if (!this.sessionId) {
            console.error('[MultiplayerFirestoreProvider] No sessionId provided');
            return false;
        }

        try {
            if (typeof firebase === 'undefined') {
                console.error('[MultiplayerFirestoreProvider] Firebase not loaded');
                return false;
            }

            if (!firebase.apps.length) {
                console.error('[MultiplayerFirestoreProvider] Firebase not initialized');
                return false;
            }

            if (!firebase.auth().currentUser) {
                await firebase.auth().signInAnonymously();
            }

            this.db = firebase.firestore();
            console.log('[MultiplayerFirestoreProvider] Initialized', { 
                sessionId: this.sessionId, 
                isHost: this.isHost,
                mode: this.mode 
            });
            
            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Init failed:', err);
            return false;
        }
    },

    /**
     * Écoute les questions publiées par l'hôte
     */
    listenForQuestions(callback) {
        if (!this.db || !this.sessionId) return;

        this.onQuestionReceived = callback;
        const sessionRef = this.db.collection('gameSessions').doc(this.sessionId);

        this.unsubscribeQuestion = sessionRef.onSnapshot((doc) => {
            if (!doc.exists) return;
            
            const data = doc.data();
            if (data.currentQuestionData && data.questionPublishedAt) {
                const questionData = data.currentQuestionData;
                console.log('[MultiplayerFirestoreProvider] Question received:', questionData.question_number);
                
                if (this.onQuestionReceived) {
                    this.onQuestionReceived(questionData);
                }
            }
        }, (error) => {
            console.error('[MultiplayerFirestoreProvider] Question listener error:', error);
        });

        console.log('[MultiplayerFirestoreProvider] Listening for questions');
    },

    /**
     * Publie une question (hôte uniquement)
     */
    async publishQuestion(questionData) {
        if (!this.isHost || !this.db || !this.sessionId) {
            console.warn('[MultiplayerFirestoreProvider] Not host or not initialized');
            return false;
        }

        try {
            const sessionRef = this.db.collection('gameSessions').doc(this.sessionId);
            await sessionRef.update({
                currentQuestionData: questionData,
                questionPublishedAt: firebase.firestore.FieldValue.serverTimestamp(),
                currentQuestionNumber: questionData.question_number,
                phase: 'question',
                buzzedBy: null,
                buzzTime: null,
                playersReady: []
            });

            console.log('[MultiplayerFirestoreProvider] Question published:', questionData.question_number);
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
                const questionData = {
                    question_number: data.question_number,
                    total_questions: data.total_questions,
                    question_text: data.question.question_text,
                    answers: data.question.answers,
                    theme: data.question.theme,
                    sub_theme: data.question.sub_theme,
                    chrono_time: data.chrono_time || 8
                };

                await this.publishQuestion(questionData);
                return data;
            } else if (data.redirect_url) {
                return data;
            }

            return null;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Fetch question error:', err);
            return null;
        }
    },

    /**
     * Publie un buzz
     */
    async publishBuzz(playerId, buzzTime) {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.db.collection('gameSessions').doc(this.sessionId);
            const doc = await sessionRef.get();
            
            if (!doc.exists) return false;
            
            const data = doc.data();
            
            if (data.buzzedBy) {
                console.log('[MultiplayerFirestoreProvider] Someone already buzzed');
                return false;
            }

            await sessionRef.update({
                buzzedBy: playerId,
                buzzTime: buzzTime,
                phase: 'buzz'
            });

            console.log('[MultiplayerFirestoreProvider] Buzz published:', playerId);
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
        const sessionRef = this.db.collection('gameSessions').doc(this.sessionId);

        this.unsubscribeBuzz = sessionRef.onSnapshot((doc) => {
            if (!doc.exists) return;
            
            const data = doc.data();
            if (data.buzzedBy && data.buzzedBy !== this.playerId) {
                console.log('[MultiplayerFirestoreProvider] Opponent buzzed:', data.buzzedBy);
                if (this.onBuzzReceived) {
                    this.onBuzzReceived(data.buzzedBy, data.buzzTime);
                }
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
            const sessionRef = this.db.collection('gameSessions').doc(this.sessionId);
            await sessionRef.update({
                playersReady: firebase.firestore.FieldValue.arrayUnion(this.playerId)
            });

            console.log('[MultiplayerFirestoreProvider] Player marked ready:', this.playerId);
            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Mark ready error:', err);
            return false;
        }
    },

    /**
     * Écoute quand tous les joueurs sont prêts
     */
    listenForAllReady(expectedPlayers, callback) {
        if (!this.db || !this.sessionId) return;

        this.onAllPlayersReady = callback;
        const sessionRef = this.db.collection('gameSessions').doc(this.sessionId);

        this.unsubscribeReady = sessionRef.onSnapshot((doc) => {
            if (!doc.exists) return;
            
            const data = doc.data();
            const playersReady = data.playersReady || [];
            
            if (playersReady.length >= expectedPlayers) {
                console.log('[MultiplayerFirestoreProvider] All players ready');
                if (this.onAllPlayersReady) {
                    this.onAllPlayersReady();
                }
            }
        }, (error) => {
            console.error('[MultiplayerFirestoreProvider] Ready listener error:', error);
        });
    },

    /**
     * Met à jour le score d'un joueur
     */
    async updateScore(playerId, score) {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.db.collection('gameSessions').doc(this.sessionId);
            const scoreField = playerId === this.playerId ? 'player1_score' : 'player2_score';
            
            await sessionRef.update({
                [scoreField]: score
            });

            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Update score error:', err);
            return false;
        }
    },

    /**
     * Met à jour la phase du jeu
     */
    async setPhase(phase) {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.db.collection('gameSessions').doc(this.sessionId);
            await sessionRef.update({ phase });
            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Set phase error:', err);
            return false;
        }
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
