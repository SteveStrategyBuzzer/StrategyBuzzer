/**
 * STRATEGYBUZZER - FirestoreProvider.js
 * Provider for multiplayer modes (Duo, League, Master)
 * Implements the provider contract for GameplayEngine synchronization
 */

const FirestoreProvider = {
    name: 'FirestoreProvider',
    
    db: null,
    sessionId: null,
    playerId: null,
    isHost: false,
    engine: null,
    
    firestoreDoc: null,
    firestoreOnSnapshot: null,
    firestoreUpdateDoc: null,
    firestoreSetDoc: null,
    firestoreServerTimestamp: null,
    firestoreArrayUnion: null,
    firestoreGetDoc: null,
    
    unsubscribeQuestion: null,
    unsubscribeBuzz: null,
    lastQuestionNumber: 0,
    
    csrfToken: '',
    routes: {
        fetchQuestion: '/api/game/question'
    },

    /**
     * Initialize the provider with Firebase instances
     * @param {Object} config - Configuration object
     * @param {Object} config.db - Firestore database instance
     * @param {Function} config.doc - doc function from firebase/firestore
     * @param {Function} config.onSnapshot - onSnapshot function
     * @param {Function} config.updateDoc - updateDoc function
     * @param {Function} config.serverTimestamp - serverTimestamp function
     * @param {Function} config.arrayUnion - arrayUnion function
     * @param {Function} config.getDoc - getDoc function
     * @param {string} config.sessionId - Game session ID
     * @param {string} config.playerId - Current player ID
     * @param {boolean} config.isHost - Whether this client is the host
     * @param {Object} config.engine - Reference to GameplayEngine
     */
    init(config = {}) {
        this.db = config.db;
        this.firestoreDoc = config.doc;
        this.firestoreOnSnapshot = config.onSnapshot;
        this.firestoreUpdateDoc = config.updateDoc;
        this.firestoreSetDoc = config.setDoc;
        this.firestoreServerTimestamp = config.serverTimestamp;
        this.firestoreArrayUnion = config.arrayUnion;
        this.firestoreGetDoc = config.getDoc;
        
        this.sessionId = config.sessionId;
        this.playerId = String(config.playerId);
        this.isHost = config.isHost || false;
        this.engine = config.engine || null;
        
        this.csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        if (config.routes) {
            this.routes = { ...this.routes, ...config.routes };
        }

        if (!this.db || !this.firestoreDoc || !this.firestoreOnSnapshot) {
            console.error('[FirestoreProvider] Firebase instances not provided');
            return false;
        }

        if (!this.sessionId) {
            console.error('[FirestoreProvider] No sessionId provided');
            return false;
        }

        if (!this.isHost) {
            this.setupListeners();
        }

        console.log('[FirestoreProvider] Initialized', {
            sessionId: this.sessionId,
            playerId: this.playerId,
            isHost: this.isHost
        });

        return true;
    },

    /**
     * Get session document reference
     * Path: gameSessions/{sessionId}
     */
    getSessionRef() {
        return this.firestoreDoc(this.db, 'gameSessions', this.sessionId);
    },

    /**
     * Set up listeners for non-host clients
     */
    setupListeners() {
        const sessionRef = this.getSessionRef();

        this.unsubscribeQuestion = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
            if (!snapshot.exists()) return;

            const data = snapshot.data();

            if (data.currentQuestionData && data.currentQuestionNumber > this.lastQuestionNumber) {
                this.lastQuestionNumber = data.currentQuestionNumber;
                console.log('[FirestoreProvider] Question received:', data.currentQuestionNumber);
                
                if (this.engine && this.engine.receiveQuestion) {
                    this.engine.receiveQuestion(data.currentQuestionData);
                }
            }

            if (data.buzzedBy && data.buzzedBy !== this.playerId) {
                console.log('[FirestoreProvider] Opponent buzzed:', data.buzzedBy);
                if (this.engine && this.engine.receiveBuzz) {
                    this.engine.receiveBuzz(data.buzzedBy, data.buzzedAt);
                }
            }
        }, (error) => {
            console.error('[FirestoreProvider] Listener error:', error);
        });

        console.log('[FirestoreProvider] Listeners set up for non-host client');
    },

    /**
     * HOST ONLY: Fetch question from server endpoint
     * @param {number} questionNumber - The question number to fetch
     * @returns {Promise<Object|null>} Question data or null
     */
    async fetchQuestion(questionNumber) {
        if (!this.isHost) {
            console.log('[FirestoreProvider] Not host, cannot fetch question');
            return null;
        }

        try {
            const response = await fetch(this.routes.fetchQuestion, {
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
                    question_number: data.question_number || questionNumber,
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

                return questionData;
            }

            if (data.redirect_url) {
                return data;
            }

            console.error('[FirestoreProvider] Fetch question failed:', data);
            return null;
        } catch (err) {
            console.error('[FirestoreProvider] Fetch question error:', err);
            return null;
        }
    },

    /**
     * HOST ONLY: Publish question to Firebase with correct_index
     * Uses setDoc with merge:true to create document if it doesn't exist
     * @param {Object} questionData - Question data including correct_index
     * @returns {Promise<boolean>} Success status
     */
    async onQuestionStart(questionData) {
        if (!this.isHost) {
            console.warn('[FirestoreProvider] Not host, cannot publish question');
            return false;
        }

        try {
            const sessionRef = this.getSessionRef();
            const updateData = {
                currentQuestionData: questionData,
                currentQuestionNumber: questionData.question_number,
                buzzedBy: null,
                buzzedAt: null,
                playersReady: [],
                hostId: this.playerId,
                sessionId: this.sessionId
            };

            if (this.firestoreSetDoc) {
                await this.firestoreSetDoc(sessionRef, updateData, { merge: true });
            } else {
                await this.firestoreUpdateDoc(sessionRef, updateData);
            }

            this.lastQuestionNumber = questionData.question_number;
            console.log('[FirestoreProvider] Question published:', questionData.question_number);
            return true;
        } catch (err) {
            console.error('[FirestoreProvider] Publish question error:', err);
            return false;
        }
    },

    /**
     * Publish buzz to Firebase
     * @param {number} buzzTime - The buzz timestamp
     * @returns {Promise<boolean>} true if first to buzz, false if someone else was faster
     */
    async onPlayerBuzz(buzzTime) {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.getSessionRef();
            const snapshot = await this.firestoreGetDoc(sessionRef);
            
            if (!snapshot.exists()) return false;

            const data = snapshot.data();

            if (data.buzzedBy) {
                console.log('[FirestoreProvider] Someone already buzzed:', data.buzzedBy);
                return false;
            }

            await this.firestoreUpdateDoc(sessionRef, {
                buzzedBy: this.playerId,
                buzzedAt: buzzTime
            });

            console.log('[FirestoreProvider] Buzz published:', this.playerId, 'at', buzzTime);
            return true;
        } catch (err) {
            console.error('[FirestoreProvider] Publish buzz error:', err);
            return false;
        }
    },

    /**
     * Handle opponent behavior - returns null for multiplayer (real opponents)
     * @param {boolean} playerBuzzed - Whether player has buzzed
     * @param {number} playerBuzzTime - Player's buzz time
     * @returns {null} Always null for multiplayer
     */
    handleOpponentBehavior(playerBuzzed, playerBuzzTime) {
        return null;
    },

    /**
     * Update player score in Firebase after answer submitted
     * @param {Object} data - Answer result data containing score
     * @returns {Promise<boolean>} Success status
     */
    async onAnswerSubmitted(data) {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.getSessionRef();
            const snapshot = await this.firestoreGetDoc(sessionRef);
            
            if (!snapshot.exists()) return false;

            const sessionData = snapshot.data();
            const scoreField = `scores.${this.playerId}`;

            await this.firestoreUpdateDoc(sessionRef, {
                [scoreField]: data.player_score || data.score || 0
            });

            console.log('[FirestoreProvider] Score updated for player:', this.playerId);
            return true;
        } catch (err) {
            console.error('[FirestoreProvider] Update score error:', err);
            return false;
        }
    },

    /**
     * Wait for sync between players
     * HOST: waits for all players to be ready
     * NON-HOST: waits for next question to be published
     * @param {number} expectedPlayers - Number of expected players (for host)
     * @returns {Promise<void>}
     */
    waitForSync(expectedPlayers = 2) {
        return new Promise((resolve) => {
            if (!this.db || !this.sessionId) {
                resolve();
                return;
            }

            const sessionRef = this.getSessionRef();

            if (this.isHost) {
                const unsubscribe = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
                    if (!snapshot.exists()) return;

                    const data = snapshot.data();
                    const playersReady = data.playersReady || [];

                    console.log('[FirestoreProvider] Players ready:', playersReady.length, '/', expectedPlayers);

                    if (playersReady.length >= expectedPlayers) {
                        unsubscribe();
                        console.log('[FirestoreProvider] All players ready');
                        resolve();
                    }
                }, (error) => {
                    console.error('[FirestoreProvider] waitForSync error:', error);
                    resolve();
                });
            } else {
                const currentQuestion = this.lastQuestionNumber;
                const unsubscribe = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
                    if (!snapshot.exists()) return;

                    const data = snapshot.data();

                    if (data.currentQuestionNumber > currentQuestion) {
                        unsubscribe();
                        console.log('[FirestoreProvider] New question detected, sync complete');
                        resolve();
                    }
                }, (error) => {
                    console.error('[FirestoreProvider] waitForSync error:', error);
                    resolve();
                });
            }
        });
    },

    /**
     * Mark current player as ready for next question
     * @returns {Promise<boolean>} Success status
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

            console.log('[FirestoreProvider] Player marked ready:', this.playerId);
            return true;
        } catch (err) {
            console.error('[FirestoreProvider] Mark ready error:', err);
            return false;
        }
    },

    /**
     * Set the GameplayEngine reference
     * @param {Object} engine - GameplayEngine instance
     */
    setEngine(engine) {
        this.engine = engine;
    },

    /**
     * Clean up listeners
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
        console.log('[FirestoreProvider] Cleaned up');
    }
};

if (typeof window !== 'undefined') {
    window.FirestoreProvider = FirestoreProvider;
}
