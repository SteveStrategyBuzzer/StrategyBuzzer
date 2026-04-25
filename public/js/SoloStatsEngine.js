/**
 * SoloStatsEngine.js
 *
 * SCOPE — read me before extending this file.
 *
 * This module is a LOCAL, PRIVATE, CLIENT-ONLY display helper used to
 * improve the UX of a single player's Solo session. It is NOT a source
 * of truth and MUST NOT be treated as one.
 *
 * Hard rules:
 *   - DO NOT use these values to write final stats to the database.
 *   - DO NOT feed these values into the public pedigree, leaderboards,
 *     ranking, level progression, quest/title unlocks, or any other
 *     player-visible "official" surface.
 *   - DO NOT POST snapshots of this state to any persistence endpoint.
 *
 * For anything official (pedigree, ranks, unlocked titles, validated
 * final stats, level progression, quest completion, leaderboards), use
 * the backend-persisted data computed by Laravel:
 *   - App\Models\MatchPerformance (saveRoundStatistics, getLast10Stats)
 *   - App\Http\Controllers\SoloController (round/match finalization)
 *   - routes/api.php pedigree endpoints
 *
 * Data here lives in sessionStorage only and is intentionally
 * discardable: closing the tab, clearing storage, or moving devices
 * loses it without consequence.
 */
(function () {
    'use strict';
    if (window.__SOLO_STATS_ENGINE_LOADED__) return;
    window.__SOLO_STATS_ENGINE_LOADED__ = true;

    var MATCH_UUID = (window.SB_GAME_CONTEXT && window.SB_GAME_CONTEXT.matchId)
        || window.MATCH_ID
        || (document.body && document.body.getAttribute('data-match-uuid'))
        || 'solo';
    var STORAGE_KEY = 'sb.soloStats.' + MATCH_UUID;
    var QSTART_KEY  = 'sb.soloQStart.' + MATCH_UUID;

    function loadState() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (raw) return JSON.parse(raw);
        } catch (e) {}
        return {
            score: 0,
            correctAnswers: 0,
            totalAnswers: 0,
            currentStreak: 0,
            bestStreak: 0,
            totalResponseMs: 0,
            buzzCount: 0
        };
    }

    function saveState(s) {
        try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(s)); } catch (e) {}
    }

    function fmtMs(ms) {
        if (!ms || ms < 0) return '0 ms';
        return Math.round(ms) + ' ms';
    }

    function paint(s) {
        var totalAns = Math.max(0, s.totalAnswers | 0);
        var correct  = Math.max(0, s.correctAnswers | 0);
        var buzz     = Math.max(0, s.buzzCount | 0);
        var efficiency = buzz > 0 ? Math.round((correct / buzz) * 100) : 0;
        var accuracy   = totalAns > 0 ? Math.round((correct / totalAns) * 100) : 0;
        var avgMs      = correct > 0 ? Math.round(s.totalResponseMs / correct) : 0;

        var values = {
            score: s.score,
            efficiencyPercent: efficiency + '%',
            accuracyPercent: accuracy + '%',
            currentStreak: s.currentStreak,
            bestStreak: s.bestStreak,
            averageResponseMs: fmtMs(avgMs),
            correctAnswers: correct,
            totalAnswers: totalAns,
            buzzCount: buzz,
            wrongAnswers: Math.max(0, totalAns - correct)
        };

        Object.keys(values).forEach(function (k) {
            var nodes = document.querySelectorAll('[data-stat="' + k + '"][data-player="self"]');
            for (var i = 0; i < nodes.length; i++) nodes[i].textContent = values[k];
        });
    }

    function getServerScore() {
        var n = document.querySelector('[data-stat="score"][data-player="self"]');
        if (!n) return null;
        var v = parseInt(n.getAttribute('data-server-score') || n.textContent || '0', 10);
        return isNaN(v) ? null : v;
    }

    function reconcileFromPage(opts) {
        opts = opts || {};
        var s = loadState();

        var serverScore = getServerScore();
        if (serverScore !== null) s.score = serverScore;

        if (typeof opts.totalAnswers === 'number') s.totalAnswers = opts.totalAnswers;
        if (typeof opts.correctAnswers === 'number') {
            var prevCorrect = s.correctAnswers;
            s.correctAnswers = opts.correctAnswers;
            if (s.correctAnswers > prevCorrect) {
                s.currentStreak = (s.currentStreak | 0) + (s.correctAnswers - prevCorrect);
                if (s.currentStreak > s.bestStreak) s.bestStreak = s.currentStreak;
            } else if (s.correctAnswers < prevCorrect) {
                s.currentStreak = 0;
            } else if (typeof opts.lastAnswerCorrect === 'boolean' && !opts.lastAnswerCorrect) {
                s.currentStreak = 0;
            }
        }
        if (typeof opts.buzzCount === 'number') s.buzzCount = opts.buzzCount;
        if (typeof opts.lastResponseMs === 'number' && opts.lastResponseMs >= 0) {
            s.totalResponseMs = (s.totalResponseMs || 0) + opts.lastResponseMs;
        }
        saveState(s);
        paint(s);
        return s;
    }

    function markQuestionStart() {
        try { sessionStorage.setItem(QSTART_KEY, String(Date.now())); } catch (e) {}
    }

    function consumeQuestionElapsedMs() {
        try {
            var raw = sessionStorage.getItem(QSTART_KEY);
            if (!raw) return null;
            sessionStorage.removeItem(QSTART_KEY);
            var t = parseInt(raw, 10);
            if (isNaN(t)) return null;
            return Date.now() - t;
        } catch (e) { return null; }
    }

    function reset() {
        try {
            sessionStorage.removeItem(STORAGE_KEY);
            sessionStorage.removeItem(QSTART_KEY);
        } catch (e) {}
    }

    window.SoloStatsEngine = {
        load: loadState,
        save: saveState,
        paint: paint,
        reconcile: reconcileFromPage,
        markQuestionStart: markQuestionStart,
        consumeQuestionElapsedMs: consumeQuestionElapsedMs,
        reset: reset
    };

    document.addEventListener('DOMContentLoaded', function () {
        paint(loadState());
    });
})();
