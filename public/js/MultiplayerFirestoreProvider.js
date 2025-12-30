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
    laravelUserId: null,
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
    lastQuestionVersion: 0,
    lastProcessedQuestionVersion: 0,
    lastProcessedQuestionSequence: 0, // Server-generated unique sequence for Option C
    
    onQuestionReceived: null,
    onBuzzReceived: null,
    onOpponentReady: null,
    onAllPlayersReady: null,
    
    csrfToken: '',
    routes: {
        fetchQuestion: '/game/{mode}/fetch-question'
    },

    /**
     * Normalise l'ID de match pour correspondre au backend PHP
     * Cette logique doit être identique à DuoFirestoreService::normalizeMatchId()
     * @param {string|number} matchId - Le code de lobby ou match_id brut
     * @returns {number} - L'ID normalisé
     */
    normalizeMatchId(matchId) {
        if (typeof matchId === 'number' && matchId > 0) {
            return matchId;
        }
        const matchIdStr = String(matchId);
        const numericId = parseInt(matchIdStr.replace(/[^0-9]/g, ''), 10) || 0;
        if (numericId === 0) {
            let crc = 0xFFFFFFFF;
            for (let i = 0; i < matchIdStr.length; i++) {
                crc ^= matchIdStr.charCodeAt(i);
                for (let j = 0; j < 8; j++) {
                    crc = (crc >>> 1) ^ (crc & 1 ? 0xEDB88320 : 0);
                }
            }
            return ((crc ^ 0xFFFFFFFF) >>> 0) & 0x7FFFFFFF;
        }
        return numericId;
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
        this.laravelUserId = config.laravelUserId || config.playerId;
        this.isHost = config.isHost || false;
        this.mode = config.mode || 'duo';
        this.csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        // Reset sync state for new session - prevents stale timestamps from blocking question delivery
        this.lastQuestionPublishedAt = 0;
        this.lastQuestionVersion = 0;
        this.lastProcessedQuestionVersion = 0;
        this.lastProcessedQuestionSequence = 0;
        
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
        
        // Si ce n'est pas l'hôte, s'enregistrer comme player2 dans Firestore
        if (!this.isHost) {
            await this.registerAsPlayer2();
        }
        
        return true;
    },
    
    /**
     * Enregistre le joueur invité comme player2 dans Firestore
     * Force l'écriture de l'UID Firebase pour éviter les problèmes d'ID legacy
     */
    async registerAsPlayer2() {
        if (!this.db || !this.sessionId) return;
        
        try {
            const sessionRef = this.getSessionRef();
            const snapshot = await this.firestoreGetDoc(sessionRef);
            
            if (snapshot.exists()) {
                const data = snapshot.data();
                // Toujours mettre à jour player2Id avec l'UID Firebase
                // même si un ancien ID Laravel existe
                const currentPlayer2Id = data.player2Id;
                if (currentPlayer2Id !== this.playerId) {
                    await this.firestoreUpdateDoc(sessionRef, {
                        player2Id: this.playerId,
                        player2LaravelId: this.laravelUserId
                    });
                    console.log('[MultiplayerFirestoreProvider] Registered as player2:', this.playerId, 'Laravel ID:', this.laravelUserId);
                }
            } else {
                // Si le document n'existe pas encore, le créer avec player2Id
                if (this.firestoreSetDoc) {
                    await this.firestoreSetDoc(sessionRef, {
                        player2Id: this.playerId,
                        player2LaravelId: this.laravelUserId,
                        player1Score: 0,
                        player2Score: 0
                    }, { merge: true });
                    console.log('[MultiplayerFirestoreProvider] Created session with player2:', this.playerId);
                }
            }
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Error registering as player2:', err);
        }
    },

    /**
     * Obtient la référence du document de session
     * Utilise l'ID normalisé pour correspondre au backend PHP
     */
    getSessionRef() {
        const normalizedId = this.normalizeMatchId(this.sessionId);
        const firestoreGameId = `${this.mode}-match-${normalizedId}`;
        console.log('[MultiplayerFirestoreProvider] Session ref:', firestoreGameId, '(raw:', this.sessionId, ')');
        return this.firestoreDoc(this.db, 'games', firestoreGameId);
    },

    /**
     * Écoute les questions publiées par le BACKEND
     * OPTION C: Uses server-generated questionSequence for deduplication
     * Both host and guest receive questions the same way from Firebase
     * Backend publishes via DuoFirestoreService, ensuring perfect synchronization
     */
    listenForQuestions(callback) {
        if (!this.db || !this.sessionId) return;

        this.onQuestionReceived = callback;
        const sessionRef = this.getSessionRef();

        this.unsubscribeQuestion = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
            if (!snapshot.exists()) return;
            
            const data = snapshot.data();
            
            // OPTION C: Use server-generated questionSequence as primary dedup key
            // This is a microsecond timestamp that's unique for each backend publish
            const questionSequence = data.questionSequence || 0;
            const questionVersion = data.questionVersion || 0;
            const publishedBy = data.publishedBy || 'unknown';
            
            // Skip if we've already processed this exact question sequence
            // questionSequence is unique per backend publish, so this allows ALL
            // clients (including host) to receive backend-published questions
            if (questionSequence <= this.lastProcessedQuestionSequence) {
                console.log('[MultiplayerFirestoreProvider] Skipping already processed sequence:', questionSequence);
                return;
            }
            
            // Only process questions published by backend (security + consistency)
            if (publishedBy !== 'backend') {
                console.log('[MultiplayerFirestoreProvider] Ignoring non-backend publish:', publishedBy);
                return;
            }
            
            if (data.currentQuestionData && questionVersion > 0) {
                // Update tracking BEFORE callback to prevent race conditions
                this.lastProcessedQuestionSequence = questionSequence;
                this.lastProcessedQuestionVersion = questionVersion;
                this.lastQuestionVersion = questionVersion;
                
                const questionData = data.currentQuestionData;
                const questionNumber = data.currentQuestion || questionData.question_number || 1;
                
                console.log('[MultiplayerFirestoreProvider] Question from backend:', questionNumber, 
                    'sequence:', questionSequence, 'isHost:', this.isHost);
                
                if (this.onQuestionReceived) {
                    this.onQuestionReceived(questionData, questionNumber);
                }
            }
        }, (error) => {
            console.error('[MultiplayerFirestoreProvider] Question listener error:', error);
        });

        console.log('[MultiplayerFirestoreProvider] Listening for backend-published questions on:', this.getSessionRef().path);
    },

    /**
     * DEPRECATED - Client-side publish is no longer used with Option C architecture
     * Backend now publishes directly via DuoFirestoreService.publishQuestion()
     * Kept for backward compatibility but should not be called
     */
    async publishQuestion(questionData, questionNumber) {
        console.warn('[MultiplayerFirestoreProvider] DEPRECATED: Client-side publishQuestion should not be used. Backend publishes via DuoFirestoreService.');
        if (!this.isHost || !this.db || !this.sessionId) {
            console.warn('[MultiplayerFirestoreProvider] Not host or not initialized');
            return false;
        }

        try {
            const sessionRef = this.getSessionRef();
            
            // Increment local questionVersion for publishing
            this.lastQuestionVersion = questionNumber;
            
            const updateData = {
                currentQuestionData: questionData,
                questionPublishedAt: this.firestoreServerTimestamp(),
                questionVersion: questionNumber,
                currentQuestion: questionNumber,
                currentPhase: 'intro',
                buzzedPlayerId: null,
                player1Buzzed: false,
                player2Buzzed: false,
                buzzTime: null,
                playersReady: [],
                player1Id: this.playerId,
                player1LaravelId: this.laravelUserId,
                hostId: this.playerId,
                mode: this.mode,
                sessionId: this.sessionId
            };
            
            // Réinitialiser les scores à 0 pour la première question d'un nouveau jeu
            if (questionNumber === 1) {
                updateData.player1Score = 0;
                updateData.player2Score = 0;
                updateData.questionVersion = 1;
                updateData.gameStartedAt = this.firestoreServerTimestamp();
                console.log('[MultiplayerFirestoreProvider] Scores reset to 0 for new game');
            }
            
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
     * DEPRECATED - Use fetchQuestion instead
     * With Option C architecture, backend publishes directly to Firebase
     * This method is kept for backward compatibility but should not be used
     */
    async fetchAndPublishQuestion(questionNumber) {
        console.warn('[MultiplayerFirestoreProvider] DEPRECATED: fetchAndPublishQuestion - backend publishes directly now');
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
            }
            
            if (data.success && data.question_text && data.answers) {
                const correctIndex = data.answers.findIndex(a => a.is_correct === true);
                
                const questionData = {
                    question_number: data.question_number,
                    total_questions: parseInt(data.total_questions) || 10,
                    question_text: data.question_text,
                    answers: data.answers.map(a => ({
                        text: a.text || a,
                        is_correct: a.is_correct || false
                    })),
                    correct_index: correctIndex >= 0 ? correctIndex : 0,
                    theme: data.theme || '',
                    sub_theme: data.sub_theme || '',
                    chrono_time: data.chrono_time || 8
                };

                console.log('[MultiplayerFirestoreProvider] Publishing question to Firebase:', questionData.question_number);
                await this.publishQuestion(questionData, data.question_number);
                return { success: true, question: data, questionData };
            }
            
            if (data.redirect_url) {
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
     * Méthode appelée par GameplayEngine pour obtenir la prochaine question
     * - Host: Fetch depuis l'API (sans publier - la publication se fait via onQuestionStart)
     * - Non-host: Retourne null, attend la question via listenForQuestions()
     */
    async fetchQuestion(questionNumber) {
        if (!this.isHost) {
            console.log('[MultiplayerFirestoreProvider] Non-host waiting for question via Firebase listener');
            return null;
        }
        
        console.log('[MultiplayerFirestoreProvider] Host fetching question', questionNumber);
        
        try {
            let route = this.routes.fetchQuestion;
            if (route && route.includes('{mode}')) {
                route = route.replace('{mode}', this.mode);
            }
            console.log('[MultiplayerFirestoreProvider] Fetching from route:', route);
            const response = await fetch(route, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ question_number: questionNumber })
            });

            const data = await response.json();
            
            if (data.redirect_url) {
                console.log('[MultiplayerFirestoreProvider] Game complete, redirecting');
                return { redirect_url: data.redirect_url };
            }
            
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
                    chrono_time: data.chrono_time || 8,
                    player_score: data.player_score,
                    opponent_score: data.opponent_score
                };

                return questionData;
            }
            
            if (data.success && data.question_text && data.answers) {
                const correctIndex = data.answers.findIndex(a => a.is_correct === true);
                
                const questionData = {
                    question_number: data.question_number,
                    total_questions: parseInt(data.total_questions) || 10,
                    question_text: data.question_text,
                    answers: data.answers.map(a => ({
                        text: a.text || a,
                        is_correct: a.is_correct || false
                    })),
                    correct_index: correctIndex >= 0 ? correctIndex : 0,
                    theme: data.theme || '',
                    sub_theme: data.sub_theme || '',
                    chrono_time: data.chrono_time || 8,
                    player_score: data.player_score,
                    opponent_score: data.opponent_score
                };
                
                console.log('[MultiplayerFirestoreProvider] Question parsed successfully:', questionData.question_number);
                return questionData;
            }

            console.error('[MultiplayerFirestoreProvider] Fetch failed:', data);
            return null;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Fetch question error:', err);
            return null;
        }
    },
    
    /**
     * HOST ONLY: Publie la question sur Firebase après fetch
     * Appelée par GameplayEngine après fetchQuestion()
     * CRITICAL: correct_index is authoritative - do not recalculate from is_correct flags
     */
    async onQuestionStart(questionData) {
        if (!this.isHost) {
            console.warn('[MultiplayerFirestoreProvider] Not host, cannot publish question');
            return false;
        }
        
        // Validate required fields to prevent Firebase invalid-argument error
        if (!questionData || !questionData.question_text || !Array.isArray(questionData.answers)) {
            console.error('[MultiplayerFirestoreProvider] Invalid question data:', questionData);
            return false;
        }
        
        // CRITICAL: Use correct_index from source data as authoritative
        // Only fallback to finding from is_correct if correct_index is truly missing
        let correctIndex = questionData.correct_index;
        if (typeof correctIndex !== 'number' || correctIndex < 0 || correctIndex >= questionData.answers.length) {
            // Last resort: find from is_correct flags
            correctIndex = questionData.answers.findIndex(a => a && a.is_correct === true);
            if (correctIndex < 0) correctIndex = 0;
            console.warn('[MultiplayerFirestoreProvider] correct_index was invalid, recalculated to:', correctIndex);
        }
        
        // Sanitize answers - preserve text only, strip is_correct to avoid confusion
        // correct_index is the single source of truth for the correct answer
        const sanitizedQuestion = {
            question_number: questionData.question_number || 1,
            total_questions: questionData.total_questions || 10,
            question_text: questionData.question_text,
            answers: questionData.answers.map((a, idx) => ({
                text: a.text || (typeof a === 'string' ? a : ''),
                is_correct: idx === correctIndex
            })),
            correct_index: correctIndex,
            theme: questionData.theme || 'Culture générale',
            sub_theme: questionData.sub_theme || '',
            chrono_time: questionData.chrono_time || 8
        };
        
        console.log('[MultiplayerFirestoreProvider] Publishing question:', sanitizedQuestion.question_number, 'correct_index:', correctIndex);
        return await this.publishQuestion(sanitizedQuestion, sanitizedQuestion.question_number);
    },

    /**
     * Publie un buzz avec timestamp serveur pour synchronisation précise
     * @param {number} buzzTime - Temps local restant (pour affichage)
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
            const buzzTimestampField = isPlayer1 ? 'player1BuzzTimestamp' : 'player2BuzzTimestamp';

            // Use server timestamp for fair comparison between players
            await this.firestoreUpdateDoc(sessionRef, {
                buzzedPlayerId: this.playerId,
                [buzzField]: true,
                [buzzTimestampField]: this.firestoreServerTimestamp(),
                buzzTime: buzzTime,
                buzzTimestamp: this.firestoreServerTimestamp(),
                phase: 'buzz'
            });

            console.log('[MultiplayerFirestoreProvider] Buzz published with server timestamp:', this.playerId);
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
                this.onBuzzReceived(data.buzzedPlayerId, data.buzzTime, data.buzzTimestamp);
            }
        }, (error) => {
            console.error('[MultiplayerFirestoreProvider] Buzz listener error:', error);
        });
    },

    /**
     * Calcule qui a buzzé en premier basé sur les timestamps serveur
     * @returns {Promise<Object>} { winnerId, player1Time, player2Time } en millisecondes depuis questionPublishedAt
     */
    async getFirstBuzzer() {
        if (!this.db || !this.sessionId) return null;

        try {
            const sessionRef = this.getSessionRef();
            const snapshot = await this.firestoreGetDoc(sessionRef);
            if (!snapshot.exists()) return null;

            const data = snapshot.data();
            const questionStart = data.questionPublishedAt?.toMillis?.() || data.questionPublishedAt;
            
            if (!questionStart) {
                console.warn('[MultiplayerFirestoreProvider] No questionPublishedAt timestamp');
                return null;
            }

            const p1Buzz = data.player1BuzzTimestamp?.toMillis?.() || data.player1BuzzTimestamp;
            const p2Buzz = data.player2BuzzTimestamp?.toMillis?.() || data.player2BuzzTimestamp;

            const result = {
                player1Time: p1Buzz ? (p1Buzz - questionStart) : null,
                player2Time: p2Buzz ? (p2Buzz - questionStart) : null,
                winnerId: null
            };

            if (p1Buzz && p2Buzz) {
                result.winnerId = p1Buzz <= p2Buzz ? data.player1Id : data.player2Id;
            } else if (p1Buzz) {
                result.winnerId = data.player1Id;
            } else if (p2Buzz) {
                result.winnerId = data.player2Id;
            }

            console.log('[MultiplayerFirestoreProvider] First buzzer calculation:', result);
            return result;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] getFirstBuzzer error:', err);
            return null;
        }
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
     * Met à jour le score d'un joueur avec retry
     */
    async updateScore(score, retries = 3) {
        if (!this.db || !this.sessionId) return false;

        for (let attempt = 1; attempt <= retries; attempt++) {
            try {
                const sessionRef = this.getSessionRef();
                
                // Essayer d'abord de déterminer si on est player1 ou player2
                let isPlayer1 = this.isHost;
                
                // Vérifier dans Firestore si possible
                if (this.firestoreGetDoc) {
                    const snapshot = await this.firestoreGetDoc(sessionRef);
                    if (snapshot.exists()) {
                        const data = snapshot.data();
                        // Comparer avec l'ID Firebase OU l'ID Laravel
                        isPlayer1 = this.playerId === String(data.player1Id) || 
                                    this.playerId === String(data.hostId) ||
                                    this.laravelUserId === String(data.player1LaravelId);
                    }
                }
                
                const scoreField = isPlayer1 ? 'player1Score' : 'player2Score';
                
                // Utiliser setDoc avec merge pour créer le doc si nécessaire
                if (this.firestoreSetDoc) {
                    await this.firestoreSetDoc(sessionRef, {
                        [scoreField]: score
                    }, { merge: true });
                } else {
                    await this.firestoreUpdateDoc(sessionRef, {
                        [scoreField]: score
                    });
                }

                console.log('[MultiplayerFirestoreProvider] Score updated:', scoreField, '=', score);
                return true;
            } catch (err) {
                console.error(`[MultiplayerFirestoreProvider] Update score error (attempt ${attempt}/${retries}):`, err);
                if (attempt < retries) {
                    await new Promise(r => setTimeout(r, 500 * attempt));
                }
            }
        }
        return false;
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
     * Publie un changement de phase (host only)
     * @param {string} phase - 'intro' | 'question' | 'buzz' | 'reveal' | 'scoreboard'
     * @param {Object} data - Phase data
     */
    async publishPhase(phase, data = {}) {
        if (!this.isHost) {
            console.log('[MultiplayerFirestoreProvider] Only host can publish phases');
            return false;
        }
        
        if (!this.db || !this.sessionId) return false;
        
        try {
            const sessionRef = this.getSessionRef();
            await this.firestoreUpdateDoc(sessionRef, {
                currentPhase: phase,
                phaseData: data,
                phasePublishedAt: this.firestoreServerTimestamp()
            });
            console.log('[MultiplayerFirestoreProvider] Phase published:', phase);
            return true;
        } catch (error) {
            console.error('[MultiplayerFirestoreProvider] Failed to publish phase:', error);
            return false;
        }
    },

    /**
     * Écoute les changements de phase (non-host players)
     * Handles special phases like 'match_complete' to redirect guest to results
     * @param {Function} callback - Called with (phase, data)
     */
    listenForPhases(callback) {
        if (!this.db || !this.sessionId) return;
        // SYNC FIX: Host MUST also listen for phases to ensure simultaneous timer start
        // Both host and guest will receive 'question' phase via Firestore and start together
        
        const sessionRef = this.getSessionRef();
        let lastPhaseTime = 0;

        this.unsubscribePhase = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
            if (!snapshot.exists()) return;
            
            const data = snapshot.data();
            const phaseTime = data.phasePublishedAt?.toMillis?.() || data.phasePublishedAt || 0;
            
            if (data.currentPhase && phaseTime > lastPhaseTime) {
                lastPhaseTime = phaseTime;
                console.log('[MultiplayerFirestoreProvider] Phase received:', data.currentPhase);
                
                // Handle match_complete phase - redirect guest to results
                if (data.currentPhase === 'match_complete') {
                    const phaseData = data.phaseData || {};
                    if (phaseData.redirect_url) {
                        console.log('[MultiplayerFirestoreProvider] Match complete, redirecting guest to:', phaseData.redirect_url);
                        window.location.href = phaseData.redirect_url;
                        return;
                    }
                }
                
                if (callback) {
                    callback(data.currentPhase, data.phaseData || {});
                }
            }
        });
    },

    // ==========================================
    // SKILL SYNCHRONIZATION FOR MULTIPLAYER
    // ==========================================

    unsubscribeSkills: null,
    unsubscribeOpponentChoice: null,
    lastSkillVersion: 0,
    activeBlockSkill: false,
    counterChallengerActive: false,
    opponentPlayerId: null, // Cached opponent ID for skill targeting
    lastProcessedAttackIds: new Set(), // Track processed attacks to prevent duplicates

    /**
     * Publishes when a skill is activated
     * BUG FIX: Now uses pendingAttacks with unique attackId instead of targetPlayerId
     * This eliminates the need to know opponent's ID at publish time
     * @param {string} skillId - The skill identifier
     * @param {Object} skillData - Skill configuration
     * @param {string} skillData.effect - Effect type (e.g., 'invert_answers', 'reduce_time')
     * @param {string} skillData.targetPlayer - 'opponent' or 'self'
     * @param {number} skillData.duration - Duration if applicable
     * @param {boolean} skillData.affects_opponent - Whether this affects the opponent
     */
    async publishSkillUsed(skillId, skillData = {}) {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.getSessionRef();
            
            // Generate unique attack ID to prevent duplicates
            const attackId = `${this.playerId}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            
            // Get current question version for filtering stale skills
            const currentQuestionVersion = this.lastProcessedQuestionVersion || this.lastQuestionVersion || 0;
            
            // BUG FIX: Use pendingAttacks structure - opponent reads attacks where fromPlayerId !== their ID
            // No need for explicit targetPlayerId since attacks always go to the opponent
            const attackPayload = {
                attackId: attackId,
                skillId: skillId,
                timestamp: this.firestoreServerTimestamp(),
                effect: skillData.effect || skillId,
                duration: skillData.duration || 0,
                fromPlayerId: this.playerId,
                fromPlayerRole: this.isHost ? 'host' : 'guest',
                questionVersion: currentQuestionVersion,
                affects_opponent: skillData.affects_opponent !== false // Default true for attack skills
            };

            const updateData = {
                [`pendingAttacks.${attackId}`]: attackPayload,
                lastSkillUpdate: this.firestoreServerTimestamp()
            };

            if (this.firestoreSetDoc) {
                await this.firestoreSetDoc(sessionRef, updateData, { merge: true });
            } else {
                await this.firestoreUpdateDoc(sessionRef, updateData);
            }

            console.log('[MultiplayerFirestoreProvider] Attack published:', skillId, 'attackId:', attackId, 'from:', this.playerId);
            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Publish skill error:', err);
            return false;
        }
    },

    /**
     * Listen for opponent's skill activations
     * BUG FIX: Uses pendingAttacks structure - processes attacks where fromPlayerId !== this.playerId
     * No explicit targetPlayerId needed since attacks always target the opponent
     * @param {Function} callback - Called with (skillId, skillData, fromPlayerId)
     */
    listenForSkills(callback) {
        if (!this.db || !this.sessionId) return;

        const sessionRef = this.getSessionRef();

        this.unsubscribeSkills = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
            if (!snapshot.exists()) return;

            const data = snapshot.data();
            const currentQuestionVersion = this.lastProcessedQuestionVersion || this.lastQuestionVersion || 0;

            // BUG FIX: Use pendingAttacks structure instead of activeSkills
            const pendingAttacks = data.pendingAttacks || {};

            for (const [attackId, attackData] of Object.entries(pendingAttacks)) {
                // Skip already processed attacks (using Set for O(1) lookup)
                if (this.lastProcessedAttackIds.has(attackId)) {
                    continue;
                }

                // BUG FIX: Skip self-published attacks - only process opponent's attacks
                // This is the key fix: no need for targetPlayerId, just check fromPlayerId
                if (attackData.fromPlayerId === this.playerId) {
                    continue;
                }

                // Skip attacks from previous questions (stale attacks)
                const attackQuestionVersion = attackData.questionVersion || 0;
                if (attackQuestionVersion > 0 && attackQuestionVersion < currentQuestionVersion) {
                    console.log('[MultiplayerFirestoreProvider] Skipping stale attack from question:', 
                                attackQuestionVersion, 'current:', currentQuestionVersion);
                    continue;
                }

                // Mark as processed BEFORE callback to prevent race conditions
                this.lastProcessedAttackIds.add(attackId);

                // Process the attack - it passed all filters
                console.log('[MultiplayerFirestoreProvider] Processing opponent attack:', attackData.skillId, 
                            'attackId:', attackId, 'from:', attackData.fromPlayerId);
                
                if (callback) {
                    callback(attackData.skillId, attackData, attackData.fromPlayerId);
                }
            }
        }, (error) => {
            console.error('[MultiplayerFirestoreProvider] Skill listener error:', error);
        });

        console.log('[MultiplayerFirestoreProvider] Listening for pendingAttacks (no targetPlayerId needed)');
    },

    /**
     * Publishes when player selects an answer (for see_opponent_choice skill)
     * @param {number} answerIndex - The index of the selected answer
     */
    async publishAnswerChoice(answerIndex) {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.getSessionRef();
            const choiceData = {
                answerIndex: answerIndex,
                timestamp: this.firestoreServerTimestamp(),
                playerId: this.playerId,
                version: Date.now()
            };

            const updateData = {
                [`answerChoices.${this.playerId}`]: choiceData
            };

            if (this.firestoreSetDoc) {
                await this.firestoreSetDoc(sessionRef, updateData, { merge: true });
            } else {
                await this.firestoreUpdateDoc(sessionRef, updateData);
            }

            console.log('[MultiplayerFirestoreProvider] Answer choice published:', answerIndex);
            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Publish answer choice error:', err);
            return false;
        }
    },

    /**
     * Listen for opponent's answer selection (for see_opponent_choice skill)
     * @param {Function} callback - Called with (answerIndex, playerId)
     */
    listenForOpponentChoice(callback) {
        if (!this.db || !this.sessionId) return;

        const sessionRef = this.getSessionRef();
        let lastProcessedChoices = {};

        this.unsubscribeOpponentChoice = this.firestoreOnSnapshot(sessionRef, (snapshot) => {
            if (!snapshot.exists()) return;

            const data = snapshot.data();
            const answerChoices = data.answerChoices || {};

            for (const [playerId, choiceData] of Object.entries(answerChoices)) {
                if (playerId === this.playerId) continue;

                const version = choiceData.version || 0;
                if (lastProcessedChoices[playerId] >= version) continue;

                lastProcessedChoices[playerId] = version;

                console.log('[MultiplayerFirestoreProvider] Opponent choice received:', choiceData.answerIndex, 'from:', playerId);
                
                if (callback) {
                    callback(choiceData.answerIndex, playerId);
                }
            }
        }, (error) => {
            console.error('[MultiplayerFirestoreProvider] Opponent choice listener error:', error);
        });

        console.log('[MultiplayerFirestoreProvider] Listening for opponent choices');
    },

    /**
     * Clear active skills for new question (host only - clears Firebase)
     * BUG FIX: Clears pendingAttacks structure and resets local tracking
     */
    async clearActiveSkills() {
        if (!this.db || !this.sessionId) return false;

        try {
            const sessionRef = this.getSessionRef();
            
            // BUG FIX: Clear pendingAttacks instead of activeSkills
            const updateData = {
                pendingAttacks: {},
                activeSkills: {}, // Also clear legacy structure
                answerChoices: {}
            };

            if (this.firestoreSetDoc) {
                await this.firestoreSetDoc(sessionRef, updateData, { merge: true });
            } else {
                await this.firestoreUpdateDoc(sessionRef, updateData);
            }

            // Reset local tracking - clear processed attack IDs for new question
            this.lastProcessedAttackIds.clear();
            this.lastSkillVersion = 0;
            this.activeBlockSkill = false;
            this.counterChallengerActive = false;
            
            console.log('[MultiplayerFirestoreProvider] Pending attacks cleared in Firebase and locally');
            return true;
        } catch (err) {
            console.error('[MultiplayerFirestoreProvider] Clear skills error:', err);
            return false;
        }
    },
    
    /**
     * Reset local skill state (for non-host players or when syncing)
     * BUG FIX: Ensures skill effects don't persist between questions
     * Called for BOTH host and non-host players now
     */
    resetLocalSkillState() {
        // Clear processed attack IDs to allow new attacks to be processed
        this.lastProcessedAttackIds.clear();
        this.lastSkillVersion = 0;
        this.activeBlockSkill = false;
        this.counterChallengerActive = false;
        console.log('[MultiplayerFirestoreProvider] Local skill state reset');
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
        if (this.unsubscribePhase) {
            this.unsubscribePhase();
            this.unsubscribePhase = null;
        }
        if (this.unsubscribeSkills) {
            this.unsubscribeSkills();
            this.unsubscribeSkills = null;
        }
        if (this.unsubscribeOpponentChoice) {
            this.unsubscribeOpponentChoice();
            this.unsubscribeOpponentChoice = null;
        }
        console.log('[MultiplayerFirestoreProvider] Cleaned up');
    }
};

if (typeof window !== 'undefined') {
    window.MultiplayerFirestoreProvider = MultiplayerFirestoreProvider;
}
