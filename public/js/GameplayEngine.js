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
        phase: 'waiting', // waiting | intro | question | buzz | answer | result
        isHost: false,
        playerId: null,
        sessionId: null,
        mode: 'solo', // solo | duo | league_individual | league_team | master
        questionStartTime: null
    },

    // Pending question data for guests waiting for phase sync
    pendingQuestionData: null,

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

    // Skill state for multiplayer
    skillState: {
        blockAttackActive: false,
        counterChallengerActive: false,
        seeOpponentChoiceActive: false,
        shuffleInterval: null,
        activeAttacks: []
    },

    // Skill catalog for reference (affects_opponent mapping)
    attackSkills: ['fake_score', 'invert_answers', 'shuffle_answers', 'reduce_time'],
    challengerSkills: ['shuffle_answers', 'reduce_time'],

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
        
        // Store initial question data for Firebase publishing
        if (options.initialQuestion && options.initialQuestion.question_text) {
            this.currentQuestionData = options.initialQuestion;
            console.log('[GameplayEngine] Initial question stored:', options.initialQuestion.question_number);
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

    // Flag to prevent multiple event listener registrations
    _eventsBound: false,
    
    /**
     * Lie les événements avec délégation pour résister aux changements DOM
     * Les listeners sont enregistrés une seule fois (singleton pattern)
     */
    bindEvents() {
        // Guard against multiple bindings
        if (this._eventsBound) {
            return;
        }
        this._eventsBound = true;
        
        // Délégation d'événements pour le buzzer - fonctionne même si le DOM change
        document.addEventListener('click', (e) => {
            const buzzButton = e.target.closest('#buzzButton');
            if (buzzButton && !buzzButton.classList.contains('disabled') && !buzzButton.classList.contains('waiting')) {
                this.handleBuzz();
            }
        });

        // Délégation pour les skills
        document.addEventListener('click', (e) => {
            const skillBtn = e.target.closest('.skill-circle.clickable');
            if (skillBtn) {
                const skillId = skillBtn.dataset.skillId;
                if (skillId) this.activateSkill(skillId);
            }
        });
        
        console.log('[GameplayEngine] Event delegation bound (singleton)');
    },

    /**
     * Met à jour la référence du buzzer après re-rendu du DOM
     * Les événements utilisent la délégation donc pas besoin de re-bind
     */
    rebindBuzzerEvent() {
        const buzzButton = document.getElementById('buzzButton');
        if (buzzButton) {
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
     * For multiplayer: Both host and guest wait for phase 'question' before starting
     * This ensures sync with host's intro overlay (2.5s delay)
     * For solo: Starts immediately (no Firebase sync needed)
     * @param {Object} questionData The question data received
     */
    receiveQuestion(questionData) {
        console.log('[GameplayEngine] Received question from provider:', questionData.question_number, 'isHost:', this.state.isHost, 'mode:', this.state.mode);
        
        // Solo mode starts immediately (no Firebase sync needed)
        if (this.state.mode === 'solo') {
            this.startQuestion(questionData);
            return;
        }
        
        // Multiplayer mode: Both host and guest store pending question data
        // and wait for phase synchronization
        this.pendingQuestionData = questionData;
        
        if (this.state.isHost) {
            // Host: Show intro overlay, then publish 'question' phase after 2.5s
            // This triggers both players to start simultaneously
            console.log('[GameplayEngine] Host storing pending question, showing intro then publishing phase');
            this.showIntroThenPublishQuestion(questionData);
        } else {
            // Guest: Wait for phase 'question' via onPhaseChange callback
            console.log('[GameplayEngine] Guest storing pending question, waiting for phase sync');
        }
    },

    /**
     * Host-only: Shows intro overlay for 2.5 seconds, then publishes 'question' phase
     * This synchronizes the start time between host and guest
     * @param {Object} questionData The question data to start after intro
     */
    showIntroThenPublishQuestion(questionData) {
        if (!this.state.isHost) {
            console.warn('[GameplayEngine] showIntroThenPublishQuestion called on non-host');
            return;
        }

        console.log('[GameplayEngine] Host showing intro overlay for question:', questionData.question_number);
        
        // Show intro overlay (the UI should display theme/question number)
        this.state.phase = 'intro';
        
        // Cancel any pending intro timeout from previous questions
        if (this._introTimeout) {
            clearTimeout(this._introTimeout);
            this._introTimeout = null;
        }
        
        // After 2.5 seconds, publish 'question' phase
        // SYNC FIX: Do NOT start locally here - wait for Firestore callback in onPhaseChange
        // This ensures both host and guest receive the phase change and start simultaneously
        this._introTimeout = setTimeout(() => {
            console.log('[GameplayEngine] Host intro complete, publishing question phase');
            
            // Publish phase to Firebase - both host and guest will receive this via listenForPhases
            if (this.provider && this.provider.publishPhase) {
                this.provider.publishPhase('question', { question_number: questionData.question_number });
            }
            // Host will start via onPhaseChange callback, same as guest
        }, 2500);
    },

    /**
     * Called when a phase change is received from the host via Firebase
     * @param {string} phase The new phase ('intro' | 'question' | 'buzz' | 'reveal' | 'scoreboard')
     * @param {Object} data Optional phase data
     */
    onPhaseChange(phase, data = {}) {
        console.log('[GameplayEngine] Phase change received:', phase, 'pendingQuestion:', !!this.pendingQuestionData);
        
        // When phase changes to 'question', start the pending question
        if (phase === 'question' && this.pendingQuestionData) {
            console.log('[GameplayEngine] Phase is question, starting pending question:', this.pendingQuestionData.question_number);
            this.startQuestion(this.pendingQuestionData);
            this.pendingQuestionData = null;
        }
        
        // Update internal phase state
        this.state.phase = phase;
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
        // Remove waiting class now that we have question data
        const questionHeader = document.getElementById('questionHeader');
        if (questionHeader) {
            questionHeader.classList.remove('waiting-for-question');
        }
        
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
            this.triggerPassiveSkills('timeout');
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
            await this.handleAnswerResult(result);
        }
    },

    /**
     * Gère le résultat de la réponse
     */
    async handleAnswerResult(data) {
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

        // Get correct answer text from current question data
        const correctAnswer = this.currentQuestionData?.answers?.[correctIndex]?.text || 
                              this.currentQuestionData?.answers?.[correctIndex] || '';
        const points = data.points_earned || (isCorrect ? 10 : -2);
        const hasNextQuestion = !data.is_round_complete && !data.game_over;

        // Use PhaseController for reveal and scoreboard overlays (transition pages)
        if (typeof window.PhaseController !== 'undefined' && window.PhaseController.onAnswerComplete) {
            await window.PhaseController.onAnswerComplete(
                isCorrect,
                correctAnswer,
                points,
                data.player_score || this.state.playerScore,
                data.opponent_score || this.state.opponentScore,
                hasNextQuestion,
                this.state.currentQuestion,
                this.state.totalQuestions,
                false
            );
        }

        if (data.is_round_complete && data.redirect_url) {
            window.location.href = data.redirect_url;
        } else if (data.game_over) {
            this.onGameOver(data);
        } else if (this.state.mode === 'solo') {
            this.nextQuestion();
        } else if (this.state.isHost && this.state.mode !== 'solo') {
            this.nextQuestion();
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
    async activateSkill(skillId) {
        console.log('[GameplayEngine] Activating skill:', skillId);

        try {
            const response = await fetch(this.config.routes.skill, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                },
                body: JSON.stringify({ skill_id: skillId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.applySkillEffect(skillId, data);
                this.markSkillUsed(skillId);
                
                // For multiplayer: publish attack skills to Firebase
                const isAttackSkill = this.attackSkills.includes(skillId);
                if (isAttackSkill && this.provider && this.provider.publishSkillUsed) {
                    const skillData = {
                        affects_opponent: true,
                        effect: skillId,
                        duration: data.duration || 0
                    };
                    await this.provider.publishSkillUsed(skillId, skillData);
                    console.log('[GameplayEngine] Attack skill published to Firebase:', skillId);
                }
                
                // Handle defense skills locally
                if (skillId === 'block_attack') {
                    this.skillState.blockAttackActive = true;
                    console.log('[GameplayEngine] Block attack activated');
                } else if (skillId === 'counter_challenger') {
                    this.skillState.counterChallengerActive = true;
                    console.log('[GameplayEngine] Counter challenger activated');
                }
            }
        } catch (err) {
            console.error('[GameplayEngine] Skill error:', err);
        }
    },

    /**
     * Receives and applies opponent's attack skill from multiplayer
     * @param {string} skillId - The skill identifier
     * @param {Object} skillData - Skill data from Firebase
     * @param {string} fromPlayerId - The opponent's player ID
     */
    receiveSkill(skillId, skillData, fromPlayerId) {
        console.log('[GameplayEngine] Receiving opponent skill:', skillId, 'from:', fromPlayerId);
        
        // Check if block_attack is active
        if (this.skillState.blockAttackActive) {
            this.skillState.blockAttackActive = false;
            this.showBlockEffect(skillId);
            console.log('[GameplayEngine] Attack blocked:', skillId);
            return;
        }
        
        // Check if counter_challenger blocks this skill
        if (this.skillState.counterChallengerActive && this.challengerSkills.includes(skillId)) {
            this.showBlockEffect(skillId);
            console.log('[GameplayEngine] Challenger skill countered:', skillId);
            return;
        }
        
        // Apply the attack effect
        this.showAttackEffect(skillId);
        this.applyOpponentAttack(skillId, skillData);
    },

    /**
     * Apply opponent's attack effect locally
     */
    applyOpponentAttack(skillId, skillData) {
        console.log('[GameplayEngine] Applying opponent attack:', skillId);
        
        switch (skillId) {
            case 'fake_score':
                this.applyFakeScore();
                break;
            case 'invert_answers':
                this.applyInvertAnswers();
                break;
            case 'shuffle_answers':
                this.applyShuffleAnswers();
                break;
            case 'reduce_time':
                this.applyReduceTime();
                break;
            default:
                console.log('[GameplayEngine] Unknown attack skill:', skillId);
        }
        
        this.skillState.activeAttacks.push(skillId);
    },

    /**
     * Display fake lower score to player
     */
    applyFakeScore() {
        const opponentScoreEl = this.getElement('opponentScore');
        if (opponentScoreEl) {
            const realScore = parseInt(opponentScoreEl.textContent) || 0;
            const fakeScore = Math.max(0, realScore - 20 - Math.floor(Math.random() * 30));
            opponentScoreEl.dataset.realScore = realScore;
            opponentScoreEl.textContent = fakeScore;
            opponentScoreEl.classList.add('fake-score');
            console.log('[GameplayEngine] Fake score applied:', fakeScore, 'real:', realScore);
        }
    },

    /**
     * Invert answer positions visually
     */
    applyInvertAnswers() {
        const grid = this.getElement('answersGrid');
        if (!grid) return;
        
        const answers = Array.from(grid.querySelectorAll('.answer-option'));
        if (answers.length < 2) return;
        
        // Reverse the visual order
        answers.forEach((answer, idx) => {
            answer.style.order = answers.length - 1 - idx;
        });
        
        grid.classList.add('inverted-answers');
        console.log('[GameplayEngine] Answers inverted');
    },

    /**
     * Shuffle answers every second
     */
    applyShuffleAnswers() {
        const grid = this.getElement('answersGrid');
        if (!grid) return;
        
        // Clear any existing shuffle interval
        if (this.skillState.shuffleInterval) {
            clearInterval(this.skillState.shuffleInterval);
        }
        
        const shuffleOnce = () => {
            const answers = Array.from(grid.querySelectorAll('.answer-option'));
            if (answers.length < 2) return;
            
            // Fisher-Yates shuffle for visual order
            const orders = answers.map((_, i) => i);
            for (let i = orders.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [orders[i], orders[j]] = [orders[j], orders[i]];
            }
            
            answers.forEach((answer, idx) => {
                answer.style.order = orders[idx];
                answer.classList.add('shuffle-animation');
            });
            
            setTimeout(() => {
                answers.forEach(a => a.classList.remove('shuffle-animation'));
            }, 300);
        };
        
        shuffleOnce();
        this.skillState.shuffleInterval = setInterval(shuffleOnce, 1000);
        grid.classList.add('shuffling-answers');
        console.log('[GameplayEngine] Answer shuffle started');
    },

    /**
     * Reduce player's timer by 2 seconds
     */
    applyReduceTime() {
        this.state.timeLeft = Math.max(1, this.state.timeLeft - 2);
        
        const chronoEl = this.getElement('chronoTimer');
        if (chronoEl) {
            chronoEl.textContent = this.state.timeLeft;
            chronoEl.classList.add('time-reduced');
            setTimeout(() => chronoEl.classList.remove('time-reduced'), 500);
        }
        
        console.log('[GameplayEngine] Time reduced to:', this.state.timeLeft);
    },

    /**
     * Show attack visual effect (shake, flash)
     */
    showAttackEffect(skillId) {
        const overlay = document.getElementById('attackOverlay');
        const attackIcon = document.getElementById('attackIcon');
        
        if (overlay) {
            overlay.classList.add('active');
            
            const icons = {
                'fake_score': '🎭',
                'invert_answers': '🔄',
                'shuffle_answers': '🔀',
                'reduce_time': '⏱️'
            };
            
            if (attackIcon) {
                attackIcon.textContent = icons[skillId] || '⚡';
            }
            
            setTimeout(() => {
                overlay.classList.remove('active');
            }, 600);
        }
        
        // Shake effect on game container
        const container = document.querySelector('.game-container');
        if (container) {
            container.classList.add('attack-shake');
            setTimeout(() => container.classList.remove('attack-shake'), 500);
        }
        
        console.log('[GameplayEngine] Attack effect shown:', skillId);
    },

    /**
     * Show block/shield visual effect
     */
    showBlockEffect(skillId) {
        const overlay = document.getElementById('attackOverlay');
        const attackIcon = document.getElementById('attackIcon');
        
        if (overlay) {
            overlay.classList.add('active', 'blocked');
            
            if (attackIcon) {
                attackIcon.textContent = '🛡️';
            }
            
            setTimeout(() => {
                overlay.classList.remove('active', 'blocked');
            }, 700);
        }
        
        // Shield pulse effect
        const container = document.querySelector('.game-container');
        if (container) {
            container.classList.add('block-shield');
            setTimeout(() => container.classList.remove('block-shield'), 600);
        }
        
        console.log('[GameplayEngine] Block effect shown for:', skillId);
    },

    /**
     * Show opponent's answer choice (for see_opponent_choice skill)
     */
    showOpponentChoice(answerIndex) {
        if (!this.skillState.seeOpponentChoiceActive) return;
        
        const grid = this.getElement('answersGrid');
        if (!grid) return;
        
        const answers = grid.querySelectorAll('.answer-option');
        if (answers[answerIndex]) {
            answers[answerIndex].classList.add('opponent-selected');
            console.log('[GameplayEngine] Opponent selected answer:', answerIndex);
        }
    },

    /**
     * Clear active attack effects (called on new question)
     */
    clearAttackEffects() {
        if (this.skillState.shuffleInterval) {
            clearInterval(this.skillState.shuffleInterval);
            this.skillState.shuffleInterval = null;
        }
        
        this.skillState.activeAttacks = [];
        
        const grid = this.getElement('answersGrid');
        if (grid) {
            grid.classList.remove('inverted-answers', 'shuffling-answers');
            grid.querySelectorAll('.answer-option').forEach(a => {
                a.style.order = '';
                a.classList.remove('opponent-selected', 'shuffle-animation');
            });
        }
        
        const opponentScoreEl = this.getElement('opponentScore');
        if (opponentScoreEl && opponentScoreEl.dataset.realScore) {
            opponentScoreEl.textContent = opponentScoreEl.dataset.realScore;
            delete opponentScoreEl.dataset.realScore;
            opponentScoreEl.classList.remove('fake-score');
        }
        
        // Reset skill state for new question (but keep defense skills active for match duration)
        this.skillState.seeOpponentChoiceActive = false;
        
        console.log('[GameplayEngine] Attack effects cleared');
    },

    /**
     * Déclenche les skills passifs
     */
    triggerPassiveSkills(event) {
        console.log('[GameplayEngine] Trigger passive skills for event:', event);
        
        // Map events to game phases for visual updates
        const eventToPhase = {
            'question_start': 'question',
            'player_buzz': 'answers',
            'opponent_buzz': 'answers',
            'correct_answer': 'waiting',
            'incorrect_answer': 'waiting',
            'timeout': 'waiting'
        };
        
        const phase = eventToPhase[event];
        if (phase) {
            this.updateSkillVisuals(phase);
        }
    },
    
    /**
     * Met à jour l'apparence visuelle des skills selon la phase de jeu
     * @param {string} phase - 'question' | 'answers' | 'waiting'
     */
    updateSkillVisuals(phase) {
        console.log('[GameplayEngine] Updating skill visuals for phase:', phase);
        
        // Get all skill circles
        const skillCircles = document.querySelectorAll('.skill-circle[data-skill-trigger]');
        
        skillCircles.forEach(skillEl => {
            const trigger = skillEl.getAttribute('data-skill-trigger');
            const isUsed = skillEl.classList.contains('used');
            const isLocked = skillEl.getAttribute('data-locked') === 'true';
            
            // Skip used or locked skills
            if (isUsed || isLocked) {
                skillEl.classList.remove('usable-now', 'available');
                return;
            }
            
            // Determine if skill is usable NOW based on phase and trigger type
            let isUsableNow = false;
            let isAvailable = false;
            
            switch (trigger) {
                case 'Active_Pre':
                    // Active_Pre skills are usable during question phase (before buzzing)
                    isUsableNow = (phase === 'question');
                    isAvailable = !isUsableNow && (phase !== 'waiting');
                    break;
                    
                case 'Active_Post':
                    // Active_Post skills are usable during answers phase (after buzzing)
                    isUsableNow = (phase === 'answers');
                    isAvailable = !isUsableNow && (phase !== 'waiting');
                    break;
                    
                case 'Passive':
                    // Passive skills are never manually usable, show as available if not used
                    isUsableNow = false;
                    isAvailable = true;
                    break;
                    
                default:
                    // Unknown trigger type
                    isAvailable = true;
                    break;
            }
            
            // Update classes
            skillEl.classList.toggle('usable-now', isUsableNow);
            skillEl.classList.toggle('available', isAvailable && !isUsableNow);
        });
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
     * SOLO ISOLATION: Solo mode uses traditional redirect, never goes through provider.fetchQuestion
     */
    async nextQuestion() {
        this.resetState();
        
        // SOLO ISOLATION: Solo mode uses traditional page redirect, not provider.fetchQuestion
        // This prevents multiplayer changes from affecting Solo gameplay
        if (this.state.mode === 'solo') {
            if (this.config.routes.nextQuestion) {
                window.location.href = this.config.routes.nextQuestion;
            } else {
                console.error('[GameplayEngine] Solo mode: nextQuestion route not configured');
            }
            return;
        }
        
        const nextQuestionNumber = this.state.currentQuestion + 1;

        // MULTIPLAYER ARCHITECTURE:
        // Only the host calls the backend to trigger question generation
        // Backend generates question AND publishes to Firebase via DuoFirestoreService
        // Both players receive the question via listenForQuestions callback
        // This ensures perfect synchronization - no client-side publishing
        
        if (this.state.isHost && this.provider && this.provider.fetchQuestion) {
            try {
                console.log('[GameplayEngine] Host requesting next question from backend:', nextQuestionNumber);
                const result = await this.provider.fetchQuestion(nextQuestionNumber);
                
                // Handle redirect (game complete)
                if (result && result.redirect_url) {
                    // Publish match_complete phase so guest also redirects
                    if (this.provider.publishPhase) {
                        console.log('[GameplayEngine] Host publishing match_complete phase before redirect');
                        await this.provider.publishPhase('match_complete', { 
                            redirect_url: result.redirect_url,
                            player_score: this.state.playerScore,
                            opponent_score: this.state.opponentScore
                        });
                    }
                    window.location.href = result.redirect_url;
                    return;
                }
                
                // Backend has published to Firebase - both players will receive via listener
                console.log('[GameplayEngine] Backend published question, waiting for Firebase...');
                // DO NOT call receiveQuestion or onQuestionStart here
                // The listenForQuestions callback will handle it for both players
                
            } catch (err) {
                console.error('[GameplayEngine] Next question error:', err);
            }
        } else if (!this.state.isHost) {
            // Guest: Just wait for Firebase, backend publishes when host requests
            console.log('[GameplayEngine] Guest waiting for next question from Firebase...');
        } else if (this.config.routes.nextQuestion) {
            window.location.href = this.config.routes.nextQuestion;
        }
    },

    /**
     * Réinitialise l'état pour une nouvelle question
     * BUG FIX: Now properly clears skill state for BOTH host and non-host players
     */
    resetState() {
        this.stopTimer();
        
        // BUG FIX: Clear attack effects from previous question (clears shuffle intervals)
        this.clearAttackEffects();
        
        this.state.buzzed = false;
        this.state.answersShown = false;
        this.state.playerBuzzTime = null;
        this.state.timeLeft = this.config.timerDuration;
        this.state.phase = 'waiting';
        this.state.questionStartTime = null;
        this.currentQuestionData = null;
        this.pendingQuestionData = null;

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
        
        // BUG FIX 2 & 3: Reset local skill state flags for BOTH host and non-host
        // Defense skills (block_attack, counter_challenger) are per-question, not per-match
        // They must be reset between questions so they don't persist incorrectly
        this.skillState.counterChallengerActive = false;
        this.skillState.blockAttackActive = false;
        this.skillState.seeOpponentChoiceActive = false;
        
        // Clear active skills in Firebase for host, reset local tracking for ALL players
        if (this.provider) {
            if (this.state.isHost && this.provider.clearActiveSkills) {
                // Host: clear Firebase pendingAttacks AND reset local state
                this.provider.clearActiveSkills().catch(err => {
                    console.warn('[GameplayEngine] Failed to clear active skills:', err);
                });
            }
            // BUG FIX: Call resetLocalSkillState for BOTH host and non-host
            // This ensures attack tracking (lastProcessedAttackIds) is cleared for everyone
            if (this.provider.resetLocalSkillState) {
                this.provider.resetLocalSkillState();
            }
        }
        
        console.log('[GameplayEngine] State reset - skill flags cleared');
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
