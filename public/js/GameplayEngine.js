/**
 * STRATEGYBUZZER - GameplayEngine.js
 * Module unifié pour la gestion du gameplay dans tous les modes
 * Garantit un comportement IDENTIQUE entre Solo, Duo, League, Master
 * 
 * Responsabilités :
 * - Timer (chronométrage)
 * - Buzz (gestion du buzzer)
 * - Skills (avatars stratégiques)
 * - Scores (mise à jour UI)
 * - Transitions (animations, sons)
 */

const GameplayEngine = {
    // État du jeu
    state: {
        currentQuestion: 0,
        totalQuestions: 10,
        playerScore: 0,
        opponentScore: 0,
        timeLeft: 8,
        buzzed: false,
        answersShown: false,
        playerBuzzTime: null,
        phase: 'waiting', // waiting | question | buzz | answer | result
        isHost: false,
        playerId: null,
        sessionId: null,
        mode: 'solo' // solo | duo | league_individual | league_team | master
    },

    // Configuration
    config: {
        timerDuration: 8,
        csrfToken: '',
        routes: {
            answer: '',
            nextQuestion: '',
            skill: '',
            fetchQuestion: ''
        },
        sounds: {
            buzz: null,
            correct: null,
            incorrect: null,
            timer: null,
            timerEnd: null
        }
    },

    // Références DOM
    elements: {
        chronoTimer: null,
        buzzButton: null,
        buzzContainer: null,
        answersGrid: null,
        questionText: null,
        questionNumber: null,
        playerScore: null,
        opponentScore: null,
        skillButtons: []
    },

    // Timer
    timerInterval: null,

    // Provider (LocalProvider ou FirestoreProvider)
    provider: null,

    /**
     * Initialise le moteur de gameplay
     * @param {Object} options Configuration initiale
     */
    init(options = {}) {
        this.state = { ...this.state, ...options.state };
        this.config = { ...this.config, ...options.config };
        
        this.cacheElements();
        this.bindEvents();
        
        if (options.provider) {
            this.setProvider(options.provider);
        }

        console.log('[GameplayEngine] Initialized', { mode: this.state.mode, isHost: this.state.isHost });
    },

    /**
     * Définit le provider de données (Local ou Firestore)
     */
    setProvider(provider) {
        this.provider = provider;
        if (provider.onQuestionReceived) {
            provider.onQuestionReceived = (questionData) => this.startQuestion(questionData);
        }
    },

    /**
     * Cache les éléments DOM fréquemment utilisés
     */
    cacheElements() {
        this.elements.chronoTimer = document.getElementById('chronoTimer');
        this.elements.buzzButton = document.getElementById('buzzButton');
        this.elements.buzzContainer = document.getElementById('buzzContainer');
        this.elements.answersGrid = document.getElementById('answersGrid');
        this.elements.questionText = document.getElementById('questionText');
        this.elements.questionNumber = document.getElementById('questionNumber');
        this.elements.playerScore = document.getElementById('playerScore');
        this.elements.opponentScore = document.getElementById('opponentScore');
        this.elements.skillButtons = document.querySelectorAll('.skill-circle.clickable');
    },

    /**
     * Lie les événements
     */
    bindEvents() {
        if (this.elements.buzzButton) {
            this.elements.buzzButton.addEventListener('click', () => this.handleBuzz());
        }

        this.elements.skillButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const skillId = e.target.dataset.skillId;
                if (skillId) this.activateSkill(skillId);
            });
        });
    },

    // ==========================================
    // GESTION DES QUESTIONS
    // ==========================================

    /**
     * Démarre une nouvelle question
     * @param {Object} questionData Données de la question
     */
    startQuestion(questionData) {
        console.log('[GameplayEngine] Starting question', questionData.question_number);

        this.resetState();
        
        this.state.currentQuestion = questionData.question_number;
        this.state.totalQuestions = questionData.total_questions || this.state.totalQuestions;
        this.state.phase = 'question';

        this.displayQuestion(questionData);
        this.startTimer();
        this.triggerPassiveSkills('question_start');
    },

    /**
     * Affiche la question dans l'UI
     */
    displayQuestion(questionData) {
        if (this.elements.questionText) {
            this.elements.questionText.textContent = questionData.question_text;
        }

        if (this.elements.questionNumber) {
            const themeInfo = questionData.sub_theme 
                ? `${questionData.theme} - ${questionData.sub_theme}` 
                : questionData.theme;
            this.elements.questionNumber.textContent = 
                `${themeInfo} | Question ${questionData.question_number}/${questionData.total_questions}`;
        }

        this.prepareAnswers(questionData.answers);
        this.showBuzzer();
    },

    /**
     * Prépare les boutons de réponse
     */
    prepareAnswers(answers) {
        if (!this.elements.answersGrid) return;

        this.elements.answersGrid.innerHTML = '';
        this.elements.answersGrid.style.display = 'none';

        answers.forEach((answer, idx) => {
            const btn = document.createElement('button');
            btn.className = 'answer-option';
            btn.dataset.index = idx;
            btn.textContent = typeof answer === 'object' ? answer.text : answer;
            btn.addEventListener('click', () => this.submitAnswer(idx));
            this.elements.answersGrid.appendChild(btn);
        });
    },

    // ==========================================
    // GESTION DU TIMER
    // ==========================================

    /**
     * Démarre le timer
     */
    startTimer() {
        this.stopTimer();
        this.state.timeLeft = this.config.timerDuration;

        if (this.elements.chronoTimer) {
            this.elements.chronoTimer.textContent = this.state.timeLeft;
        }

        this.timerInterval = setInterval(() => {
            this.state.timeLeft--;

            if (this.elements.chronoTimer) {
                this.elements.chronoTimer.textContent = this.state.timeLeft;
            }

            if (this.state.timeLeft <= 3 && this.config.sounds.timer) {
                this.config.sounds.timer.play().catch(() => {});
            }

            if (this.state.timeLeft <= 0) {
                this.onTimerExpired();
            }
        }, 1000);
    },

    /**
     * Arrête le timer
     */
    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
    },

    /**
     * Appelé quand le timer expire
     */
    onTimerExpired() {
        this.stopTimer();

        if (this.config.sounds.timerEnd) {
            this.config.sounds.timerEnd.play().catch(() => {});
        }

        if (!this.state.buzzed) {
            this.state.phase = 'result';
            this.notifyTimeExpired();
        }
    },

    /**
     * Notifie le serveur que le temps a expiré
     */
    notifyTimeExpired() {
        fetch(this.config.routes.answer, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.config.csrfToken
            },
            body: JSON.stringify({
                answer_id: -1,
                timed_out: true
            })
        }).then(response => response.json())
          .then(data => this.handleAnswerResult(data))
          .catch(err => console.error('[GameplayEngine] Time expired error:', err));
    },

    // ==========================================
    // GESTION DU BUZZ
    // ==========================================

    /**
     * Affiche le buzzer
     */
    showBuzzer() {
        if (this.elements.buzzContainer) {
            this.elements.buzzContainer.style.display = 'flex';
        }
        if (this.elements.buzzButton) {
            this.elements.buzzButton.disabled = false;
            this.elements.buzzButton.style.opacity = '1';
        }
    },

    /**
     * Cache le buzzer
     */
    hideBuzzer() {
        if (this.elements.buzzContainer) {
            this.elements.buzzContainer.style.display = 'none';
        }
    },

    /**
     * Gère le clic sur le buzzer
     */
    handleBuzz() {
        if (this.state.buzzed || this.state.phase !== 'question') return;

        this.state.buzzed = true;
        this.state.playerBuzzTime = Date.now();
        this.state.phase = 'buzz';

        if (this.elements.buzzButton) {
            this.elements.buzzButton.disabled = true;
            this.elements.buzzButton.style.opacity = '0.5';
        }

        if (this.config.sounds.buzz) {
            this.config.sounds.buzz.play().catch(() => {});
        }

        this.stopTimer();
        this.showAnswers();

        if (this.provider && this.provider.publishBuzz) {
            this.provider.publishBuzz(this.state.playerId, this.state.playerBuzzTime);
        }

        this.triggerPassiveSkills('player_buzz');
    },

    /**
     * Gère le buzz d'un adversaire
     */
    onOpponentBuzz(opponentId, buzzTime) {
        if (this.state.buzzed) return;

        console.log('[GameplayEngine] Opponent buzzed:', opponentId);
        this.state.phase = 'answer';

        if (this.elements.buzzButton) {
            this.elements.buzzButton.disabled = true;
            this.elements.buzzButton.style.opacity = '0.5';
        }

        this.triggerPassiveSkills('opponent_buzz');
    },

    /**
     * Affiche les réponses
     */
    showAnswers() {
        this.state.answersShown = true;
        this.hideBuzzer();

        if (this.elements.answersGrid) {
            this.elements.answersGrid.style.display = 'grid';
        }
    },

    // ==========================================
    // GESTION DES RÉPONSES
    // ==========================================

    /**
     * Soumet une réponse
     */
    async submitAnswer(answerIndex) {
        if (this.state.phase === 'result') return;

        const buttons = this.elements.answersGrid?.querySelectorAll('.answer-option');
        buttons?.forEach(btn => btn.classList.add('disabled'));

        this.state.phase = 'result';

        let result;
        if (this.state.mode === 'solo' && this.provider && this.provider.submitAnswer) {
            const buzzTime = (Date.now() - (this.state.questionStartTime || Date.now())) / 1000;
            result = await this.provider.submitAnswer(answerIndex, buzzTime);
        } else {
            const response = await fetch(this.config.routes.answer, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                },
                body: JSON.stringify({
                    answer_id: answerIndex,
                    buzz_time: this.state.playerBuzzTime
                })
            });
            result = await response.json();
        }

        if (result) {
            this.handleAnswerResult(result);
        }
    },

    /**
     * Gère le résultat de la réponse
     */
    handleAnswerResult(data) {
        const isCorrect = data.is_correct;
        const correctIndex = data.correct_index;

        const buttons = this.elements.answersGrid?.querySelectorAll('.answer-option');
        buttons?.forEach((btn, idx) => {
            if (idx === correctIndex) {
                btn.classList.add('correct');
            } else if (idx === data.selected_index && !isCorrect) {
                btn.classList.add('incorrect');
            }
        });

        if (isCorrect && this.config.sounds.correct) {
            this.config.sounds.correct.play().catch(() => {});
        } else if (!isCorrect && this.config.sounds.incorrect) {
            this.config.sounds.incorrect.play().catch(() => {});
        }

        this.updateScores(data.player_score, data.opponent_score);

        if (isCorrect) {
            this.triggerPassiveSkills('correct_answer');
        } else {
            this.triggerPassiveSkills('incorrect_answer');
        }

        if (data.is_round_complete && data.redirect_url) {
            setTimeout(() => {
                window.location.href = data.redirect_url;
            }, 1500);
        } else if (data.game_over) {
            this.onGameOver(data);
        } else if (this.state.mode === 'solo') {
            setTimeout(() => {
                this.nextQuestion();
            }, 1500);
        }
    },

    // ==========================================
    // GESTION DES SCORES
    // ==========================================

    /**
     * Met à jour les scores dans l'UI
     */
    updateScores(playerScore, opponentScore) {
        if (playerScore !== undefined) {
            this.state.playerScore = playerScore;
            if (this.elements.playerScore) {
                this.elements.playerScore.textContent = playerScore;
            }
        }

        if (opponentScore !== undefined) {
            this.state.opponentScore = opponentScore;
            if (this.elements.opponentScore) {
                this.elements.opponentScore.textContent = opponentScore;
            }
        }
    },

    // ==========================================
    // GESTION DES SKILLS
    // ==========================================

    /**
     * Active un skill manuellement
     */
    activateSkill(skillId) {
        console.log('[GameplayEngine] Activating skill:', skillId);

        fetch(this.config.routes.skill, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.config.csrfToken
            },
            body: JSON.stringify({ skill_id: skillId })
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  this.applySkillEffect(skillId, data);
                  this.markSkillUsed(skillId);
              }
          })
          .catch(err => console.error('[GameplayEngine] Skill error:', err));
    },

    /**
     * Déclenche les skills passifs
     */
    triggerPassiveSkills(event) {
        // Implémenter selon les skills définis
        console.log('[GameplayEngine] Trigger passive skills for event:', event);
    },

    /**
     * Applique l'effet d'un skill
     */
    applySkillEffect(skillId, data) {
        console.log('[GameplayEngine] Applying skill effect:', skillId, data);
    },

    /**
     * Marque un skill comme utilisé
     */
    markSkillUsed(skillId) {
        const skillEl = document.querySelector(`.skill-circle[data-skill-id="${skillId}"]`);
        if (skillEl) {
            skillEl.classList.add('used');
            skillEl.classList.remove('active', 'clickable');
        }
    },

    // ==========================================
    // TRANSITIONS
    // ==========================================

    /**
     * Passe à la question suivante
     */
    async nextQuestion() {
        this.resetState();
        
        if (this.provider && this.provider.fetchAndPublishQuestion) {
            const data = await this.provider.fetchAndPublishQuestion(this.state.currentQuestion + 1);
            if (data && data.success && this.state.mode === 'solo') {
                this.startQuestion({
                    question_number: data.question_number,
                    question_text: data.question.question_text,
                    answers: data.question.answers,
                    theme: data.question.theme,
                    sub_theme: data.question.sub_theme,
                    total_questions: data.total_questions,
                    chrono_time: data.chrono_time
                });
                this.updateScores(data.player_score, data.opponent_score);
            } else if (data && data.redirect_url) {
                window.location.href = data.redirect_url;
            }
        } else if (this.config.routes.nextQuestion) {
            window.location.href = this.config.routes.nextQuestion;
        }
    },

    /**
     * Réinitialise l'état pour une nouvelle question
     */
    resetState() {
        this.stopTimer();
        
        this.state.buzzed = false;
        this.state.answersShown = false;
        this.state.playerBuzzTime = null;
        this.state.timeLeft = this.config.timerDuration;
        this.state.phase = 'waiting';

        if (this.elements.chronoTimer) {
            this.elements.chronoTimer.textContent = this.config.timerDuration;
        }

        if (this.elements.buzzButton) {
            this.elements.buzzButton.disabled = false;
            this.elements.buzzButton.style.opacity = '1';
        }

        if (this.elements.answersGrid) {
            this.elements.answersGrid.style.display = 'none';
            this.elements.answersGrid.querySelectorAll('.answer-option').forEach(btn => {
                btn.classList.remove('correct', 'incorrect', 'disabled');
            });
        }
    },

    /**
     * Appelé en fin de partie
     */
    onGameOver(data) {
        console.log('[GameplayEngine] Game over', data);
        this.state.phase = 'finished';
    }
};

// ==========================================
// PROVIDERS
// ==========================================

/**
 * LocalProvider - Pour le mode Solo
 * Gère les données localement via API Laravel (SPA mode)
 */
const LocalProvider = {
    onQuestionReceived: null,
    csrfToken: '',
    routes: {
        fetchQuestion: '/solo/fetch-question',
        submitAnswer: '/solo/submit-answer'
    },

    init(config = {}) {
        this.csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (config.routes) {
            this.routes = { ...this.routes, ...config.routes };
        }
        console.log('[LocalProvider] Initialized for Solo SPA mode');
    },

    async fetchAndPublishQuestion(questionNumber) {
        try {
            const response = await fetch(this.routes.fetchQuestion, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ question_number: questionNumber })
            });

            const data = await response.json();

            if (data.success && this.onQuestionReceived) {
                this.onQuestionReceived({
                    question_number: data.question_number,
                    question_text: data.question.question_text,
                    answers: data.question.answers,
                    theme: data.question.theme,
                    sub_theme: data.question.sub_theme,
                    total_questions: data.total_questions,
                    chrono_time: data.chrono_time,
                    player_score: data.player_score,
                    opponent_score: data.opponent_score
                });
            }

            return data;
        } catch (err) {
            console.error('[LocalProvider] Fetch question error:', err);
            return null;
        }
    },

    async submitAnswer(answerIndex, buzzTime) {
        try {
            const response = await fetch(this.routes.submitAnswer, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    answer_index: answerIndex,
                    buzz_time: buzzTime
                })
            });

            return await response.json();
        } catch (err) {
            console.error('[LocalProvider] Submit answer error:', err);
            return { success: false, error: err.message };
        }
    },

    publishBuzz(playerId, buzzTime) {
        console.log('[LocalProvider] Solo buzz recorded locally:', buzzTime);
    }
};

/**
 * FirestoreProvider - Pour les modes multijoueur
 * Synchronise via Firebase Firestore
 */
const FirestoreProvider = {
    db: null,
    sessionRef: null,
    sessionId: null,
    unsubscribe: null,
    onQuestionReceived: null,
    lastQuestionPublishedAt: 0,

    init(db, sessionId) {
        this.db = db;
        this.sessionId = sessionId;
        this.sessionRef = db.collection('gameSessions').doc(sessionId);
        this.startListening();
    },

    startListening() {
        if (this.unsubscribe) this.unsubscribe();

        this.unsubscribe = this.sessionRef.onSnapshot((doc) => {
            if (!doc.exists) return;
            
            const data = doc.data();
            this.handleUpdate(data);
        });
    },

    handleUpdate(data) {
        const questionPublishedAt = data.questionPublishedAt || 0;

        if (data.currentQuestionData && questionPublishedAt > this.lastQuestionPublishedAt) {
            this.lastQuestionPublishedAt = questionPublishedAt;
            
            if (this.onQuestionReceived) {
                this.onQuestionReceived(data.currentQuestionData);
            }
        }
    },

    async fetchAndPublishQuestion(questionNumber) {
        try {
            const response = await fetch(`/game/${GameplayEngine.state.mode}/fetch-question`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': GameplayEngine.config.csrfToken
                },
                body: JSON.stringify({ question_number: questionNumber })
            });

            const data = await response.json();

            if (data.success) {
                await this.publishQuestion(data);
            }
        } catch (err) {
            console.error('[FirestoreProvider] Fetch question error:', err);
        }
    },

    async publishQuestion(questionData) {
        try {
            await this.sessionRef.update({
                currentQuestionData: {
                    question_number: questionData.question_number,
                    question_text: questionData.question_text,
                    answers: questionData.answers,
                    theme: questionData.theme,
                    sub_theme: questionData.sub_theme,
                    total_questions: questionData.total_questions
                },
                currentQuestionIndex: questionData.question_number,
                questionPublishedAt: Date.now()
            });
            console.log('[FirestoreProvider] Question published:', questionData.question_number);
        } catch (err) {
            console.error('[FirestoreProvider] Publish question error:', err);
        }
    },

    async publishBuzz(playerId, buzzTime) {
        try {
            await this.sessionRef.collection('buzzers').doc(playerId).set({
                buzzedAt: buzzTime,
                reactionTimeMs: buzzTime - this.lastQuestionPublishedAt,
                valid: true
            });
            
            await this.sessionRef.collection('players').doc(playerId).update({
                buzzed: true,
                buzzTime: buzzTime
            });
        } catch (err) {
            console.error('[FirestoreProvider] Publish buzz error:', err);
        }
    },

    destroy() {
        if (this.unsubscribe) {
            this.unsubscribe();
            this.unsubscribe = null;
        }
    }
};

// Export pour utilisation globale
window.GameplayEngine = GameplayEngine;
window.LocalProvider = LocalProvider;
window.FirestoreProvider = FirestoreProvider;
