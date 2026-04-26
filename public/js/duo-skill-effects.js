/**
 * DuoSkillEffects — extracted from resources/views/duo_answer.blade.php (Task #56).
 *
 * Owns every per-skill visual effect, activation handler and the answers-shuffle
 * loop that used to live inline. Buttons are looked up by `[data-skill-id="..."]`
 * (the canonical AvatarSkillService id), so the view template only renders the
 * generic skill loop — no per-skill JS plumbing needed.
 *
 * The module is intentionally framework-free and DOM-coupled (it queries
 * `.answer-button` and `[data-skill-id]` on demand) so it survives reconnect
 * hydration that rebuilds the choice buttons from server state.
 *
 * Usage from the view IIFE:
 *
 *   var skillEffects = window.DuoSkillEffects.init({
 *     socket: window.DuoSocketClient,
 *     isAnswered: function() { return answered; },
 *     computeCurrentPotentialPoints: function() { return calculatePotentialPoints(timeLeft); },
 *     extendTime: function(s) { timeLeft += s; ANSWER_TIME += s; ... },
 *     shuffleActive: SHUFFLE_ACTIVE,
 *     labels: { lockCorrectError: '...' },
 *   });
 *
 *   // Returned helpers:
 *   skillEffects.onSkillEffect(data)
 *   skillEffects.onSkillFailed(data)
 *   skillEffects.shuffleAnswers()
 *   skillEffects.startShuffleInterval(force)
 *   skillEffects.stopShuffleInterval()
 */
(function (root) {
    'use strict';

    function getAnswerButtons() {
        return document.querySelectorAll('.answer-button');
    }

    function getSkillButton(skillId) {
        return document.querySelector('[data-skill-id="' + skillId + '"]');
    }

    function init(opts) {
        opts = opts || {};
        var socket               = opts.socket || null;
        var isAnswered           = opts.isAnswered || function () { return false; };
        var currentPotentialPoints = opts.computeCurrentPotentialPoints || function () { return 0; };
        var extendTime           = opts.extendTime || function () {};
        var labels               = opts.labels || {};
        var lockCorrectErrorText = labels.lockCorrectError || 'Skill unavailable';

        // Per-skill local "used" guard. Mirrors the previous inline `skillsUsed`
        // map. Keys are the *short* skill ids the activate functions used (kept
        // distinct from the catalog id to preserve the original control flow).
        var used = {
            illuminate:  false,
            acidify:     false,
            eliminate:   false,
            aiSuggest:   false,
            lockCorrect: false,
            extraTime:   false,
        };

        var shuffleActive   = !!opts.shuffleActive;
        var shuffleInterval = null;

        // ── Visual effects (server-confirmed result) ────────────────────────

        function _applyIlluminateEffect() {
            var questionBox = document.querySelector('.question-text-box');
            if (!questionBox) return;
            if (!/\d/.test(questionBox.textContent || '')) return;
            questionBox.innerHTML = questionBox.innerHTML.replace(
                /(\d+)/g,
                '<span class="illuminated-number">$1</span>'
            );
            console.log('[Skills] Illuminate numbers applied to question text');
        }

        function _applyAcidifyEffect(wrongIndices) {
            var buttons = getAnswerButtons();
            if (Array.isArray(wrongIndices) && wrongIndices.length > 0) {
                wrongIndices.forEach(function (idx) {
                    if (buttons[idx]) buttons[idx].classList.add('acidified');
                });
            } else {
                var available = [];
                buttons.forEach(function (button, idx) {
                    if (!button.classList.contains('correct')) available.push(idx);
                });
                if (available.length > 0) {
                    var r = available[Math.floor(Math.random() * available.length)];
                    buttons[r].classList.add('acidified');
                }
            }
            console.log('[Skills] Acidify error visual applied', wrongIndices);
        }

        function _applyAiSuggestionEffect(suggestedIndex) {
            var buttons = getAnswerButtons();
            if (suggestedIndex !== undefined && suggestedIndex !== null && buttons[suggestedIndex]) {
                buttons[suggestedIndex].classList.add('ai-suggested');
            } else {
                var available = [];
                buttons.forEach(function (button, idx) {
                    if (!button.classList.contains('eliminated') && !button.classList.contains('acidified')) {
                        available.push(idx);
                    }
                });
                if (available.length > 0) {
                    var r = available[Math.floor(Math.random() * available.length)];
                    buttons[r].classList.add('ai-suggested');
                }
            }
            console.log('[Skills] AI suggestion visual applied', suggestedIndex);
        }

        // ── Socket-driven effect / failure handlers ─────────────────────────

        function onSkillEffect(data) {
            var skillId = data && data.skillId;
            if (!skillId) return;

            if (skillId === 'illuminate_numbers') {
                var btn = getSkillButton('illuminate_numbers');
                if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
                _applyIlluminateEffect();
            } else if (skillId === 'acidify_error') {
                var btn2 = getSkillButton('acidify_error');
                if (btn2) { btn2.classList.remove('pending'); btn2.classList.add('used'); }
                _applyAcidifyEffect(data.wrongIndices);
            } else if (skillId === 'ai_suggestion') {
                var btn3 = getSkillButton('ai_suggestion');
                if (btn3) { btn3.classList.remove('pending'); btn3.classList.add('used'); }
                _applyAiSuggestionEffect(data.suggestedIndex);
            }
        }

        function onSkillFailed(data) {
            var skillId = data && data.skillId;
            if (!skillId) return;
            if (skillId === 'illuminate_numbers') {
                used.illuminate = false;
                var b = getSkillButton('illuminate_numbers');
                if (b) b.classList.remove('pending');
            } else if (skillId === 'acidify_error') {
                used.acidify = false;
                var b2 = getSkillButton('acidify_error');
                if (b2) b2.classList.remove('pending');
            } else if (skillId === 'ai_suggestion') {
                used.aiSuggest = false;
                var b3 = getSkillButton('ai_suggestion');
                if (b3) b3.classList.remove('pending');
            }
            console.log('[Skills] Skill activation failed:', skillId, (data && data.reason) || '');
        }

        // ── Activate handlers ───────────────────────────────────────────────

        function _socketOk() {
            return socket && typeof socket.isConnected === 'function' && socket.isConnected();
        }

        function activateIlluminate() {
            if (used.illuminate || isAnswered()) return;
            used.illuminate = true;
            var btn = getSkillButton('illuminate_numbers');
            if (btn) btn.classList.add('pending');
            if (_socketOk()) {
                socket.useSkill('illuminate_numbers');
            } else {
                if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
                _applyIlluminateEffect();
            }
            console.log('[Skills] Illuminate numbers requested');
        }

        function activateAcidify() {
            if (used.acidify || isAnswered()) return;
            used.acidify = true;
            var btn = getSkillButton('acidify_error');
            if (btn) btn.classList.add('pending');
            if (_socketOk()) {
                socket.useSkill('acidify_error');
            } else {
                if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
                _applyAcidifyEffect(null);
            }
            console.log('[Skills] Acidify error requested');
        }

        function activateEliminate() {
            if (used.eliminate || isAnswered()) return;
            used.eliminate = true;
            var btn = getSkillButton('eliminate_two');
            if (btn) btn.classList.add('used');

            var buttons = getAnswerButtons();
            var wrongAnswers = [];
            buttons.forEach(function (button, idx) {
                if (!button.classList.contains('ai-suggested')) wrongAnswers.push(idx);
            });

            for (var i = wrongAnswers.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var tmp = wrongAnswers[i];
                wrongAnswers[i] = wrongAnswers[j];
                wrongAnswers[j] = tmp;
            }

            var eliminated = 0;
            for (var k = 0; k < wrongAnswers.length && eliminated < 2; k++) {
                var idx = wrongAnswers[k];
                if (buttons.length - eliminated > 2) {
                    buttons[idx].classList.add('eliminated');
                    eliminated++;
                }
            }
            console.log('[Skills] Eliminate 2 activated, removed', eliminated, 'answers');
        }

        function activateAiSuggest() {
            if (used.aiSuggest || isAnswered()) return;
            used.aiSuggest = true;
            var btn = getSkillButton('ai_suggestion');
            if (btn) btn.classList.add('pending');
            if (_socketOk()) {
                socket.useSkill('ai_suggestion');
            } else {
                if (btn) { btn.classList.remove('pending'); btn.classList.add('used'); }
                _applyAiSuggestionEffect(null);
            }
            console.log('[Skills] AI suggestion requested');
        }

        function activateLockCorrect() {
            if (used.lockCorrect || isAnswered()) return;
            if (currentPotentialPoints() !== 2) {
                window.alert(lockCorrectErrorText);
                return;
            }
            used.lockCorrect = true;
            var btn = getSkillButton('lock_correct');
            if (btn) btn.classList.add('used');
            getAnswerButtons().forEach(function (b) { b.classList.add('locked-correct'); });
            console.log('[Skills] Lock correct activated - 2 points secured');
        }

        function activateExtraTime() {
            if (used.extraTime || isAnswered()) return;
            used.extraTime = true;
            var btn = getSkillButton('extra_answer_time');
            if (btn) btn.classList.add('used');
            extendTime(2);
            console.log('[Skills] Extra time activated, +2s');
        }

        // ── Wire up rendered buttons by data-skill-id ───────────────────────

        var bindings = {
            'illuminate_numbers': activateIlluminate,
            'acidify_error':      activateAcidify,
            'eliminate_two':      activateEliminate,
            'ai_suggestion':      activateAiSuggest,
            'lock_correct':       activateLockCorrect,
            'extra_answer_time':  activateExtraTime,
        };
        Object.keys(bindings).forEach(function (skillId) {
            var btn = getSkillButton(skillId);
            if (btn) btn.addEventListener('click', bindings[skillId]);
        });

        // ── Shuffle helpers ─────────────────────────────────────────────────

        function shuffleAnswers() {
            if (isAnswered()) return;
            var container = document.getElementById('answersContainer');
            if (!container) return;
            var buttons   = Array.from(container.querySelectorAll('.answer-button'));
            var indicator = container.querySelector('.shuffle-indicator');

            for (var i = buttons.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var tmp = buttons[i];
                buttons[i] = buttons[j];
                buttons[j] = tmp;
            }

            buttons.forEach(function (btn) { btn.classList.add('shuffling'); });
            buttons.forEach(function (btn) { container.appendChild(btn); });
            if (indicator) container.insertBefore(indicator, container.firstChild);

            setTimeout(function () {
                buttons.forEach(function (btn) { btn.classList.remove('shuffling'); });
            }, 300);
        }

        function startShuffleInterval(force) {
            if (!force && !shuffleActive) return;
            if (shuffleInterval) return;
            shuffleInterval = setInterval(shuffleAnswers, 1500);
        }

        function stopShuffleInterval() {
            if (shuffleInterval) {
                clearInterval(shuffleInterval);
                shuffleInterval = null;
            }
        }

        return {
            onSkillEffect:       onSkillEffect,
            onSkillFailed:       onSkillFailed,
            shuffleAnswers:      shuffleAnswers,
            startShuffleInterval: startShuffleInterval,
            stopShuffleInterval:  stopShuffleInterval,
        };
    }

    root.DuoSkillEffects = { init: init };
})(typeof window !== 'undefined' ? window : this);
