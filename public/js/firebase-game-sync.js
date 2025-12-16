const FirebaseGameSync = {
    app: null,
    db: null,
    auth: null,
    matchRef: null,
    unsubscribers: [],
    isReady: false,
    userId: null,
    laravelUserId: null,
    matchId: null,
    mode: null,
    isHost: false,
    callbacks: {},
    docExists: false,
    currentQuestionData: null,
    lastQuestionNumber: 0,

    firebaseConfig: {
        apiKey: "AIzaSyAB5-A0NsX9I9eFX76ZBYQQG_bagWp_dHw",
        authDomain: "strategybuzzergame.firebaseapp.com",
        projectId: "strategybuzzergame",
        storageBucket: "strategybuzzergame.appspot.com",
        messagingSenderId: "776091953448",
        appId: "1:776091953448:web:af3f6c8f3c8f3c8f3c8f3c"
    },

    async init(config) {
        if (this.isReady) return true;
        
        this.matchId = config.matchId;
        this.mode = config.mode;
        this.laravelUserId = config.laravelUserId;
        this.isHost = config.isHost || false;
        this.callbacks = config.callbacks || {};

        try {
            const { initializeApp } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js');
            const { getAuth, signInAnonymously, onAuthStateChanged } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js');
            const { getFirestore, doc, onSnapshot, updateDoc, serverTimestamp, getDoc, setDoc } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js');

            this.app = initializeApp(this.firebaseConfig, 'game-sync-' + Date.now());
            this.auth = getAuth(this.app);
            this.db = getFirestore(this.app);

            this.firestoreMethods = { doc, onSnapshot, updateDoc, serverTimestamp, getDoc, setDoc };

            return new Promise((resolve, reject) => {
                onAuthStateChanged(this.auth, async (user) => {
                    if (user) {
                        this.userId = user.uid;
                        this.isReady = true;
                        console.log('[FirebaseGameSync] Authenticated:', user.uid);
                        
                        await this.ensureDocExists();
                        await this.setupMatchListener();
                        
                        if (this.callbacks.onReady) {
                            this.callbacks.onReady();
                        }
                        resolve(true);
                    }
                });

                signInAnonymously(this.auth).catch((error) => {
                    console.error('[FirebaseGameSync] Auth error:', error);
                    reject(error);
                });
            });
        } catch (error) {
            console.error('[FirebaseGameSync] Init error:', error);
            throw error;
        }
    },

    getMatchDocPath() {
        // Structure unifiée : tous les modes utilisent /gameSessions/{sessionId}
        return `gameSessions/${this.matchId}`;
    },

    async ensureDocExists() {
        if (!this.matchId || !this.db) return;

        const { doc, getDoc, setDoc, serverTimestamp } = this.firestoreMethods;
        const docPath = this.getMatchDocPath();
        const [collection, docId] = docPath.split('/');
        
        this.matchRef = doc(this.db, collection, docId);

        try {
            const snapshot = await getDoc(this.matchRef);
            if (snapshot.exists()) {
                this.docExists = true;
                console.log('[FirebaseGameSync] Doc exists');
            } else {
                await setDoc(this.matchRef, {
                    matchId: this.matchId,
                    mode: this.mode,
                    phase: 'question',
                    currentQuestion: 1,
                    createdAt: serverTimestamp(),
                    createdByLaravelId: this.laravelUserId
                });
                this.docExists = true;
                console.log('[FirebaseGameSync] Doc created');
            }
        } catch (error) {
            console.error('[FirebaseGameSync] ensureDocExists error:', error);
            this.docExists = false;
        }
    },

    async setupMatchListener() {
        if (!this.matchRef || !this.db) return;

        const { onSnapshot } = this.firestoreMethods;

        const unsubscribe = onSnapshot(this.matchRef, (snapshot) => {
            if (snapshot.exists()) {
                const data = snapshot.data();
                console.log('[FirebaseGameSync] State update:', data);
                this.handleStateUpdate(data);
            }
        }, (error) => {
            console.error('[FirebaseGameSync] Listener error:', error);
        });

        this.unsubscribers.push(unsubscribe);
    },

    handleStateUpdate(data) {
        if (data.phase && this.callbacks.onPhaseChange) {
            this.callbacks.onPhaseChange(data.phase, data);
        }

        if (data.buzzWinnerLaravelId && this.callbacks.onBuzz) {
            const isOpponentBuzz = data.buzzWinnerLaravelId !== this.laravelUserId;
            const buzzWinnerRole = isOpponentBuzz ? 'opponent' : 'player';
            this.callbacks.onBuzz(buzzWinnerRole, data.buzzTime, data, isOpponentBuzz);
        }

        if (data.currentQuestion !== undefined && data.currentQuestion !== this.lastQuestionNumber) {
            this.lastQuestionNumber = data.currentQuestion;
            
            if (data.currentQuestionData) {
                this.currentQuestionData = data.currentQuestionData;
                if (this.callbacks.onQuestionDataReceived) {
                    this.callbacks.onQuestionDataReceived(data.currentQuestionData, data.currentQuestion, data);
                }
            }
            
            if (this.callbacks.onQuestionChange) {
                this.callbacks.onQuestionChange(data.currentQuestion, data);
            }
        }

        if (data.scores && this.callbacks.onScoreUpdate) {
            this.callbacks.onScoreUpdate(data.scores, data);
        }

        if (data.lastAnswerSubmit && this.callbacks.onAnswerSubmit) {
            const isOpponentAnswer = data.lastAnswerSubmit.laravelUserId !== this.laravelUserId;
            this.callbacks.onAnswerSubmit(data.lastAnswerSubmit, data, isOpponentAnswer);
        }

        if (data.readyForNext && this.callbacks.onReadyStateChange) {
            this.callbacks.onReadyStateChange(data.readyForNext, data);
        }

        if (this.callbacks.onStateUpdate) {
            this.callbacks.onStateUpdate(data);
        }
    },

    async updateState(updates) {
        if (!this.matchRef || !this.isReady) {
            console.warn('[FirebaseGameSync] Not ready to update state');
            return false;
        }

        try {
            const { updateDoc, setDoc, serverTimestamp, getDoc } = this.firestoreMethods;
            
            const updateData = {
                ...updates,
                lastUpdated: serverTimestamp(),
                lastUpdatedByLaravelId: this.laravelUserId,
                lastUpdatedByFirebaseId: this.userId
            };
            
            if (!this.docExists) {
                const snapshot = await getDoc(this.matchRef);
                if (!snapshot.exists()) {
                    await setDoc(this.matchRef, {
                        matchId: this.matchId,
                        mode: this.mode,
                        createdAt: serverTimestamp(),
                        ...updateData
                    });
                    this.docExists = true;
                    console.log('[FirebaseGameSync] Doc created on update');
                    return true;
                }
                this.docExists = true;
            }
            
            await updateDoc(this.matchRef, updateData);
            console.log('[FirebaseGameSync] State updated:', updates);
            return true;
        } catch (error) {
            console.error('[FirebaseGameSync] Update error:', error);
            return false;
        }
    },

    async sendBuzz(buzzTime) {
        return this.updateState({
            buzzWinnerLaravelId: this.laravelUserId,
            buzzWinnerFirebaseId: this.userId,
            buzzTime: buzzTime,
            phase: 'answering'
        });
    },

    async sendAnswerAfterServerConfirm(answerData, serverResult) {
        return this.updateState({
            lastAnswerSubmit: {
                laravelUserId: this.laravelUserId,
                firebaseUserId: this.userId,
                answerIndex: answerData.answerIndex,
                isCorrect: answerData.isCorrect,
                points: serverResult.points || 0,
                timestamp: Date.now()
            },
            phase: 'transition',
            [`scores.${this.laravelUserId}`]: serverResult.newScore
        });
    },

    async advanceQuestionAfterServerConfirm(nextQuestionNumber) {
        if (!this.isHost) {
            console.log('[FirebaseGameSync] Not host, skipping question advancement');
            return false;
        }
        
        return this.updateState({
            currentQuestion: nextQuestionNumber,
            phase: 'question',
            buzzWinnerLaravelId: null,
            buzzWinnerFirebaseId: null,
            buzzTime: null,
            lastAnswerSubmit: null
        });
    },

    async setPhase(phase) {
        return this.updateState({ phase });
    },

    async setPlayerReady(currentQuestion) {
        const readyKey = `readyForNext.${this.laravelUserId}`;
        return this.updateState({
            [readyKey]: { ready: true, question: currentQuestion }
        });
    },

    async resetReadyState() {
        return this.updateState({
            readyForNext: {}
        });
    },

    async resetPlayerReady() {
        const readyKey = `readyForNext.${this.laravelUserId}`;
        return this.updateState({
            [readyKey]: { ready: false, question: 0 }
        });
    },

    async publishQuestion(questionNumber, questionData) {
        if (!this.isHost) {
            console.log('[FirebaseGameSync] Not host, skipping question publish');
            return false;
        }
        
        const safeAnswers = questionData.answers.map(a => ({
            index: a.index,
            text: a.text
        }));
        
        return this.updateState({
            currentQuestion: questionNumber,
            currentQuestionData: {
                question_text: questionData.question_text,
                answers: safeAnswers,
                theme: questionData.theme || '',
                sub_theme: questionData.sub_theme || '',
                niveau: questionData.niveau || 1,
                total_questions: questionData.total_questions || 10
            },
            phase: 'question',
            buzzWinnerLaravelId: null,
            buzzWinnerFirebaseId: null,
            buzzTime: null,
            lastAnswerSubmit: null,
            readyForNext: {},
            questionPublishedAt: Date.now()
        });
    },

    async publishTransition(transitionData) {
        return this.updateState({
            phase: 'transition',
            transitionData: transitionData
        });
    },

    async fetchNextQuestion(apiUrl, csrfToken) {
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    match_id: this.matchId,
                    mode: this.mode
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch question');
            }
            
            return await response.json();
        } catch (error) {
            console.error('[FirebaseGameSync] fetchNextQuestion error:', error);
            return null;
        }
    },

    isPlayerReadyForQuestion(readyState, playerId, currentQuestion) {
        const playerReady = readyState[playerId];
        if (!playerReady) return false;
        if (typeof playerReady === 'boolean') return false;
        return playerReady.ready === true && playerReady.question === currentQuestion;
    },

    checkBothPlayersReady(data, player1Id, player2Id) {
        const readyState = data.readyForNext || {};
        const player1Ready = readyState[player1Id] === true;
        const player2Ready = readyState[player2Id] === true;
        return player1Ready && player2Ready;
    },

    cleanup() {
        this.unsubscribers.forEach(unsub => {
            try { unsub(); } catch (e) {}
        });
        this.unsubscribers = [];
        this.isReady = false;
        this.matchRef = null;
        this.docExists = false;
        console.log('[FirebaseGameSync] Cleaned up');
    }
};

window.FirebaseGameSync = FirebaseGameSync;
