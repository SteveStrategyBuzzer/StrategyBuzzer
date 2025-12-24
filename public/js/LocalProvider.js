/**
 * STRATEGYBUZZER - LocalProvider.js
 * Provider for Solo mode gameplay
 * Handles communication with server for local/solo games
 */

const LocalProvider = {
    mode: 'solo',
    csrfToken: '',
    routes: {
        fetchQuestion: '',
        buzz: '',
        answer: ''
    },
    niveau: 1,
    questionNumber: 1,

    /**
     * Initialize the provider
     * @param {Object} config Configuration object
     * @param {string} config.csrfToken CSRF token for server requests
     * @param {Object} config.routes Server endpoints
     * @param {number} config.niveau Player level for AI difficulty
     */
    init(config = {}) {
        this.csrfToken = config.csrfToken || '';
        this.routes = config.routes || this.routes;
        this.niveau = config.niveau || 1;
        this.questionNumber = config.questionNumber || 1;
        
        if (config.mode) {
            this.mode = config.mode;
        }
        
        console.log('[LocalProvider] Initialized', { mode: this.mode, niveau: this.niveau });
    },

    /**
     * Fetch question data from server
     * @param {number} questionNumber The question number to fetch
     * @returns {Promise<Object>} Question data
     */
    async fetchQuestion(questionNumber) {
        this.questionNumber = questionNumber;
        
        const response = await fetch(this.routes.fetchQuestion, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                question_number: questionNumber
            })
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch question: ${response.status}`);
        }

        return await response.json();
    },

    /**
     * Called when a question starts
     * No-op for Solo mode (no synchronization needed)
     * @param {Object} questionData The question data
     */
    onQuestionStart(questionData) {
        // No-op for Solo mode
    },

    /**
     * Handle player buzz event
     * In Solo mode, always returns true (no competition)
     * @param {number} buzzTime The time when player buzzed
     * @returns {boolean} Always true for Solo mode
     */
    onPlayerBuzz(buzzTime) {
        return true;
    },

    /**
     * Get AI opponent behavior from server
     * @param {boolean} playerBuzzed Whether the player buzzed
     * @param {number} playerBuzzTime The time when player buzzed (if applicable)
     * @returns {Promise<Object>} AI opponent behavior data
     */
    async handleOpponentBehavior(playerBuzzed, playerBuzzTime) {
        const response = await fetch(this.routes.buzz, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                player_buzzed: playerBuzzed,
                player_buzz_time: playerBuzzTime,
                question_number: this.questionNumber,
                niveau: this.niveau
            })
        });

        if (!response.ok) {
            throw new Error(`Failed to get opponent behavior: ${response.status}`);
        }

        return await response.json();
    },

    /**
     * Submit answer to server and get result
     * @param {Object} data Answer submission data
     * @param {number} data.answer_id The selected answer index
     * @param {boolean} data.timed_out Whether the player timed out
     * @param {number} data.buzz_time Time when player buzzed
     * @returns {Promise<Object>} Answer result with scores and next state
     */
    async onAnswerSubmitted(data) {
        const response = await fetch(this.routes.answer, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                answer_id: data.answer_id,
                timed_out: data.timed_out || false,
                buzz_time: data.buzz_time,
                question_number: this.questionNumber
            })
        });

        if (!response.ok) {
            throw new Error(`Failed to submit answer: ${response.status}`);
        }

        return await response.json();
    },

    /**
     * Wait for synchronization
     * Returns immediately for Solo mode (no sync needed)
     * @returns {Promise<void>}
     */
    async waitForSync() {
        return Promise.resolve();
    },

    /**
     * Mark player as ready
     * No-op for Solo mode
     */
    markPlayerReady() {
        // No-op for Solo mode
    }
};

window.LocalProvider = LocalProvider;
