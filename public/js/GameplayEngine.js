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
        mode: 'solo', // solo | duo | league_individual | league_team | master
        questionStartTime: null
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

    // Current question data (for answer validation)
    currentQuestionData: null,

    /**
     * Initialise le moteur de gameplay
     * @param {Object} options Configuration initiale
     */
    init(options = {}) {
        this.state = { ...this.state, ...options.state };
        this.config = { ...this.config, ...options.config };
        
        this.cacheElements();
        this.bindEvents();
        
        // Set buzzer to waiting state immediately (visible but inactive)
        this.setBuzzerWaiting();
        
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
        if (provider.setEngine) {
            provider.setEngine(this);
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
     * Rafraîchit les références DOM (à appeler après chaque re-rendu de la question)
     * Résout le problème des références périmées quand le HTML est reconstruit
     */
    refreshElements() {
        this.elements.chronoTimer = document.getElementById('chronoTimer');
        this.elements.buzzButton = document.getElementById('buzzButton');
        this.elements.buzzContainer = document.getElementById('buzzContainer');
        this.elements.answersGrid = document.getElementById('answersGrid');
        this.elements.questionText = document.getElementById('questionText');
        this.elements.questionNumber = document.getElementById('questionNumber');
        this.elements.playerScore = document.getElementById('playerScore');
        this.elements.opponentScore = document.getElementById('opponentScore');
        // Don't re-bind skill buttons as they have event listeners
    },

    /**
     * Vérifie si un élément est toujours attaché au DOM
     */
    isElementAttached(element) {
        return element && document.body.contains(element);
    },

    /**
     * Récupère un élément DOM, le recache si nécessaire
     */
    getElement(name) {
        if (!this.isElementAttached(this.elements[name])) {
            this.elements[name] = document.getElementById(name === 'buzzButton' ? 'buzzButton' : 
                                  name === 'buzzContainer' ? 'buzzContainer' :
                                  name === 'chronoTimer' ? 'chronoTimer' :
                                  name === 'answersGrid' ? 'answersGrid' :
                                  name === 'questionText' ? 'questionText' :
                                  name === 'questionNumber' ? 'questionNumber' :
                                  name === 'playerScore' ? 'playerScore' :
                                  name === 'opponentScore' ? 'opponentScore' : name);
        }
        return this.elements[name];
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

    /**
     * Réattache l'événement click du buzzer après re-rendu du DOM
     * Doit être appelé AVANT refreshElements() pour détecter le changement
     */
    rebindBuzzerEvent() {
        const buzzButton = document.getElementById('buzzButton');
        if (buzzButton) {
            // Toujours rattacher le handler pour garantir qu'il est présent
            // Utiliser une référence pour éviter les doublons
            if (!buzzButton._gameplayBound) {
                buzzButton.addEventListener('click', () => this.handleBuzz());
                buzzButton._gameplayBound = true;
                console.log('[GameplayEngine] Buzzer event bound');
            }
            this.elements.buzzButton = buzzButton;
        }
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

        // Rebind buzzer FIRST (before refresh to detect new elements)
        this.rebindBuzzerEvent();
        // Then refresh all DOM references
        this.refreshElements();

        this.resetState();
        
        // Store question data for answer validation
        this.currentQuestionData = questionData;
        
        this.state.currentQuestion = questionData.question_number;
        this.state.totalQuestions = questionData.total_questions || this.state.totalQuestions;
        this.state.phase = 'question';
        this.state.questionStartTime = Date.now();

        this.displayQuestion(questionData);
        this.startTimer();
        this.triggerPassiveSkills('question_start');
    },

    /**
     * Called by non-host clients when they receive a question from the provider
     * @param {Object} questionData The question data received
     */
    receiveQuestion(questionData) {
        console.log('[GameplayEngine] Received question from provider:', questionData.question_number);
        this.startQuestion(questionData);
    },

    /**
     * Called when a buzz notification is received from multiplayer
     * @param {string} playerId The ID of the player who buzzed
     * @param {number} buzzTime The time when they buzzed
     */
    receiveBuzz(playerId, buzzTime) {
        if (this.state.buzzed) return;
        if (playerId === this.state.playerId) return;

        console.log('[GameplayEngine] Received buzz from opponent:', playerId);
        this.onOpponentBuzz(playerId, buzzTime);
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
    async notifyTimeExpired() {
        try {
            if (this.provider && this.provider.onAnswerSubmitted) {
                await this.provider.onAnswerSubmitted({
                    answer_id: -1,
                    timed_out: true,
                    question_number: this.state.currentQuestion
                });
            }
            
            const response = await fetch(this.config.routes.answer, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                },
                body: JSON.stringify({
                    answer_id: -1,
                    timed_out: true
                })
            });
            const data = await response.json();
            this.handleAnswerResult(data);
        } catch (err) {
            console.error('[GameplayEngine] Time expired error:', err);
        }
    },

    // ==========================================
    // GESTION DU BUZZ
    // ==========================================

    /**
     * Affiche le buzzer (active state with animation)
     */
    showBuzzer() {
        if (this.elements.buzzContainer) {
            this.elements.buzzContainer.classList.remove('buzzer-waiting', 'buzzer-hidden');
            this.elements.buzzContainer.classList.add('buzzer-ready');
        }
        if (this.elements.buzzButton) {
            this.elements.buzzButton.disabled = false;
            // Clear any inline opacity from previous buzz states
            this.elements.buzzButton.style.opacity = '';
        }
    },

    /**
     * Cache le buzzer (hidden state after buzz/answer)
     */
    hideBuzzer() {
        if (this.elements.buzzContainer) {
            this.elements.buzzContainer.classList.remove('buzzer-ready', 'buzzer-waiting');
            this.elements.buzzContainer.classList.add('buzzer-hidden');
        }
    },
    
    /**
     * Met le buzzer en attente (visible mais inactif)
     */
    setBuzzerWaiting() {
        if (this.elements.buzzContainer) {
            this.elements.buzzContainer.classList.remove('buzzer-ready', 'buzzer-hidden');
            this.elements.buzzContainer.classList.add('buzzer-waiting');
        }
        if (this.elements.buzzButton) {
            this.elements.buzzButton.disabled = true;
        }
    },

    /**
     * Gère le clic sur le buzzer
     */
    async handleBuzz() {
        if (this.state.buzzed || this.state.phase !== 'question') return;

        const buzzTime = Date.now();
        
        // Play buzz sound immediately for feedback
        if (this.config.sounds.buzz) {
            this.config.sounds.buzz.play().catch(() => {});
        }

        // Check with provider if buzz is accepted (for multiplayer race condition)
        if (this.provider && this.provider.onPlayerBuzz) {
            const buzzAccepted = await this.provider.onPlayerBuzz(buzzTime);
            
            if (!buzzAccepted) {
                // Someone else buzzed first - don't show answers
                console.log('[GameplayEngine] Buzz rejected - opponent was faster');
                if (this.elements.buzzButton) {
                    this.elements.buzzButton.disabled = true;
                }
                this.state.phase = 'answer';
                this.triggerPassiveSkills('opponent_buzz');
                return;
            }
        }

        // Buzz accepted - proceed
        this.state.buzzed = true;
        this.state.playerBuzzTime = buzzTime;
        this.state.phase = 'buzz';

        if (this.elements.buzzButton) {
            this.elements.buzzButton.disabled = true;
        }

        this.stopTimer();
        this.showAnswers();

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
        }

        this.stopTimer();
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

        const buzzTime = this.state.playerBuzzTime 
            ? (this.state.playerBuzzTime - (this.state.questionStartTime || this.state.playerBuzzTime)) / 1000
            : 0;

        let result;
        
        // For Solo mode, get AI opponent behavior first
        if (this.state.mode === 'solo' && this.provider && this.provider.handleOpponentBehavior) {
            try {
                const opponentBehavior = await this.provider.handleOpponentBehavior(
                    this.state.buzzed, 
                    buzzTime
                );
                if (opponentBehavior) {
                    console.log('[GameplayEngine] AI opponent behavior:', opponentBehavior);
                }
            } catch (err) {
                console.error('[GameplayEngine] Error getting opponent behavior:', err);
            }
        }

        // Submit answer to server
        try {
            const response = await fetch(this.config.routes.answer, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                },
                body: JSON.stringify({
                    answer_id: answerIndex,
                    buzz_time: this.state.playerBuzzTime,
                    question_number: this.state.currentQuestion
                })
            });
            result = await response.json();
        } catch (err) {
            console.error('[GameplayEngine] Submit answer error:', err);
            return;
        }

        // Notify provider of answer submission
        if (this.provider && this.provider.onAnswerSubmitted) {
            try {
                await this.provider.onAnswerSubmitted({
                    answer_id: answerIndex,
                    is_correct: result.is_correct,
                    player_score: result.player_score,
                    question_number: this.state.currentQuestion
                });
            } catch (err) {
                console.error('[GameplayEngine] Provider onAnswerSubmitted error:', err);
            }
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
        
        const nextQuestionNumber = this.state.currentQuestion + 1;

        if (this.provider && this.provider.fetchQuestion) {
            try {
                const questionData = await this.provider.fetchQuestion(nextQuestionNumber);
                
                if (!questionData) {
                    console.error('[GameplayEngine] No question data returned');
                    return;
                }

                // Handle redirect (game complete)
                if (questionData.redirect_url) {
                    window.location.href = questionData.redirect_url;
                    return;
                }

                // For multiplayer host, publish the question
                if (this.state.isHost && this.provider.onQuestionStart) {
                    await this.provider.onQuestionStart(questionData);
                }

                // Start the question locally
                this.startQuestion(questionData);
                
                // Update scores if provided
                if (questionData.player_score !== undefined || questionData.opponent_score !== undefined) {
                    this.updateScores(questionData.player_score, questionData.opponent_score);
                }
            } catch (err) {
                console.error('[GameplayEngine] Next question error:', err);
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
        this.state.questionStartTime = null;
        this.currentQuestionData = null;

        if (this.elements.chronoTimer) {
            this.elements.chronoTimer.textContent = this.config.timerDuration;
        }

        // Set buzzer to waiting state (visible but inactive) until question starts
        this.setBuzzerWaiting();

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

// Export pour utilisation globale
window.GameplayEngine = GameplayEngine;
