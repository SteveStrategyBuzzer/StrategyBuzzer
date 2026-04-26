/**
 * Patch 4/4 — Browser-driven UI verification of the Duo result overlay.
 *
 * Companion to `duo-patch4-score.spec.ts` (which validates the wire-level
 * scoring grid by driving raw socket clients against the live game-server).
 * This spec proves the OTHER half of Patch 4: that the user-facing DOM
 * actually renders Node's `pointsEarned` correctly for every score-grid
 * value, and never leaks judgment text to the page.
 *
 * Strategy
 * --------
 * `showResult(isCorrect, correctIndex, pointsEarned)` is the only function
 * in `resources/views/duo_answer.blade.php` that paints the result overlay.
 * We load a minimal HTML fixture into a real Chromium tab — the fixture
 * embeds the production result-overlay HTML verbatim and a faithful copy of
 * `showResult()` (kept byte-identical to the blade source by the
 * "fixture-stays-in-sync" guard at the bottom of this file).
 *
 * For each grid value pts ∈ {-2, 0, 1, 2} we:
 *   1. call `showResult(pts > 0, 0, pts)` from the test;
 *   2. assert the visible `#pointsText` shows the expected signed badge
 *      ("+2 points", "0 point", "+1 point", "-2 points" …);
 *   3. assert `#resultText` and `#correctAnswerText` are EMPTY (no
 *      "Correct"/"Incorrect"/"Bonne réponse"/"Mauvaise réponse" leak);
 *   4. assert `#resultOverlay` carries the expected `correct`/`incorrect`
 *      CSS variant for visual feedback;
 *   5. assert no navigation happened (the page never hops back to
 *      `/duo/question`, the parasitic regression the original task warned
 *      about).
 *
 * Together with the wire-level spec, this fully validates AC #6:
 *   • Node is the sole arbiter of the score delta (wire-level spec);
 *   • the Duo result page renders that delta verbatim and visually only
 *     (this spec).
 */

import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

// ---------------------------------------------------------------------------
// Fixture: a minimal HTML page that mounts the EXACT result-overlay HTML
// from `duo_answer.blade.php` and a copy of the production `showResult`.
// The bottom-of-file `Source-of-truth check` test asserts this copy is
// byte-identical to the function in the blade view, so any drift fails CI.
// ---------------------------------------------------------------------------

// Keep the answer-button block trivially renderable so showResult can paint
// `correct` / `incorrect` / `selected` classes onto buttons #answerButton0..3.
const FIXTURE_HTML = String.raw`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Patch 4 result overlay fixture</title>
  <style>
    .result-overlay { display: none; padding: 1em; }
    .result-overlay.correct  { background: #d4edda; }
    .result-overlay.incorrect { background: #f8d7da; }
    .answer-btn { display: block; width: 200px; margin: 4px; }
    .answer-btn.correct   { outline: 2px solid green; }
    .answer-btn.incorrect { outline: 2px solid red; }
  </style>
</head>
<body>
  <div id="answerGrid">
    <button class="answer-btn" id="answerButton0" data-answer-index="0">A
      <span class="indicator" id="indicator0"></span>
    </button>
    <button class="answer-btn" id="answerButton1" data-answer-index="1">B
      <span class="indicator" id="indicator1"></span>
    </button>
    <button class="answer-btn" id="answerButton2" data-answer-index="2">C
      <span class="indicator" id="indicator2"></span>
    </button>
    <button class="answer-btn" id="answerButton3" data-answer-index="3">D
      <span class="indicator" id="indicator3"></span>
    </button>
  </div>

  <!-- VERBATIM copy of the result overlay markup from duo_answer.blade.php -->
  <div class="result-overlay" id="resultOverlay">
      <div class="result-text" id="resultText"></div>
      <div class="points-text" id="pointsText"></div>
      <div class="correct-answer-text" id="correctAnswerText"></div>
  </div>

  <script>
    // Mirror the blade-side i18n constants. The production blade uses
    //   @json(__('point'))  /  @json(__('points'))  /  @json(__('0 point'))
    // here. The translation strings used at runtime are French.
    const I18N = { POINT: 'point', POINTS: 'points', ZERO: '0 point' };

    // Mirror the DOM lookups the blade does on load.
    const answerButtons       = Array.from(document.querySelectorAll('[data-answer-index]'));
    const resultOverlay       = document.getElementById('resultOverlay');
    const resultText          = document.getElementById('resultText');
    const pointsText          = document.getElementById('pointsText');
    const correctAnswerText   = document.getElementById('correctAnswerText');
    const correctSound        = null;  // audio cues stubbed (not under test)
    const incorrectSound      = null;
    let   selectedIndex       = -1;    // matches blade default

    // Public helper so the test can simulate the player having clicked a
    // wrong answer before showResult runs.
    window.__setSelected = function(i) { selectedIndex = i; };

    // ── BEGIN showResult — KEEP IN SYNC with duo_answer.blade.php ────────
    function showResult(isCorrect, correctIndex, pointsEarned) {
        // Audio cue (non-textuel) — autorisé.
        if (isCorrect && correctSound) {
            correctSound.play().catch(function() {});
        } else if (!isCorrect && incorrectSound) {
            incorrectSound.play().catch(function() {});
        }
        answerButtons.forEach(function(btn, idx) {
            btn.classList.remove('selected');
            const indicator = document.getElementById('indicator' + idx);
            if (idx === correctIndex) {
                btn.classList.add('correct');
                if (indicator) indicator.textContent = '✓';
            } else if (idx === selectedIndex && !isCorrect) {
                btn.classList.add('incorrect');
                if (indicator) indicator.textContent = '✗';
            }
        });
        var pts = Number(pointsEarned);
        if (!isFinite(pts)) pts = 0;
        if (pointsText) {
            if (pts === 0) {
                pointsText.textContent = I18N.ZERO;
            } else {
                var unit = (pts === 1 || pts === -1) ? I18N.POINT : I18N.POINTS;
                pointsText.textContent = (pts > 0 ? '+' : '') + pts + ' ' + unit;
            }
        }
        if (resultText)        resultText.textContent = '';
        if (correctAnswerText) correctAnswerText.textContent = '';
        if (resultOverlay) {
            var variant = pts > 0 ? 'correct' : (pts < 0 ? 'incorrect' : '');
            resultOverlay.className = 'result-overlay' + (variant ? ' ' + variant : '');
            resultOverlay.style.display = 'block';
        }
    }
    // ── END showResult ───────────────────────────────────────────────────

    window.showResult = showResult;
  </script>
</body>
</html>`;

// ---------------------------------------------------------------------------
// Helpers.
// ---------------------------------------------------------------------------

const BANNED_VISIBLE_PHRASES = [
  'correct',
  'incorrect',
  'bonne réponse',
  'mauvaise réponse',
  'wrong',
  'right',
];

interface OverlaySnapshot {
  pointsText: string;
  resultText: string;
  correctAnswerText: string;
  overlayClass: string;
  overlayVisible: boolean;
  url: string;
}

// ---------------------------------------------------------------------------
// Test suite.
// ---------------------------------------------------------------------------

test.describe('Duo Patch 4 — Result overlay (browser-driven)', () => {
  test.beforeEach(async ({ page }) => {
    await page.setContent(FIXTURE_HTML, { waitUntil: 'domcontentloaded' });
  });

  // The 4 distinct grid values Node can emit when a player buzzed:
  //   +2 (1st buzz + correct)
  //   +1 (2nd buzz + correct)
  //    0 (timeout_forgiveness skill — no penalty)
  //   -2 (buzz + wrong, or buzz + timeout)
  // The "no buzz at all" scenario does NOT trigger showResult on the
  // silent player's screen — it's covered by the wire-level spec instead.
  for (const { pts, expectedBadge, expectedVariant, isCorrect, label } of [
    { pts:  2, expectedBadge: '+2 points', expectedVariant: 'correct',   isCorrect: true,  label: 'S1 (1st + correct)  → +2' },
    { pts:  1, expectedBadge: '+1 point',  expectedVariant: 'correct',   isCorrect: true,  label: 'S3 (2nd + correct)  → +1' },
    { pts:  0, expectedBadge: '0 point',   expectedVariant: '',          isCorrect: true,  label: 'timeout_forgiveness → 0' },
    { pts: -2, expectedBadge: '-2 points', expectedVariant: 'incorrect', isCorrect: false, label: 'S2/S4/S5 (wrong/timeout) → -2' },
  ]) {
    test(`grid ${pts}: ${label}`, async ({ page }) => {
      const startUrl = page.url();

      // Simulate the player having selected answer #1 if they were wrong
      // (so showResult paints the red `incorrect` outline on the right btn).
      if (!isCorrect) {
        await page.evaluate(() => (window as any).__setSelected(1));
      }

      // Drive showResult exactly as production does.
      // correctIndex = 2 in every scenario (matches the wire-level spec).
      await page.evaluate(
        ({ pts, isCorrect }) => {
          (window as any).showResult(isCorrect, 2, pts);
        },
        { pts, isCorrect },
      );

      const snapshot: OverlaySnapshot = await page.evaluate(() => {
        const overlay = document.getElementById('resultOverlay')!;
        return {
          pointsText: document.getElementById('pointsText')!.textContent ?? '',
          resultText: document.getElementById('resultText')!.textContent ?? '',
          correctAnswerText:
            document.getElementById('correctAnswerText')!.textContent ?? '',
          overlayClass: overlay.className,
          overlayVisible: overlay.style.display !== 'none' && overlay.style.display !== '',
          url: location.href,
        };
      });

      // (1) The numeric badge matches the expected signed string.
      expect(
        snapshot.pointsText,
        `pointsText badge should reflect Node pointsEarned=${pts}`,
      ).toBe(expectedBadge);

      // (2) Banned judgment text never appears in user-visible channels.
      for (const visible of [
        snapshot.pointsText,
        snapshot.resultText,
        snapshot.correctAnswerText,
      ]) {
        const lower = visible.toLowerCase();
        for (const banned of BANNED_VISIBLE_PHRASES) {
          // The badge is allowed to contain the literal word "point(s)"
          // and the leading "+/-/0" — none of those are in BANNED.
          expect(
            lower.includes(banned),
            `banned phrase "${banned}" leaked into result overlay text "${visible}"`,
          ).toBe(false);
        }
      }

      // (3) resultText / correctAnswerText must be EMPTY post-Patch4.
      expect(
        snapshot.resultText,
        'resultText must be empty (Patch 4 forbids judgment text)',
      ).toBe('');
      expect(
        snapshot.correctAnswerText,
        'correctAnswerText must be empty (Patch 4 forbids correct-answer text)',
      ).toBe('');

      // (4) The overlay must carry the right variant class for visual feedback.
      const expectedFullClass =
        'result-overlay' + (expectedVariant ? ' ' + expectedVariant : '');
      expect(
        snapshot.overlayClass,
        `overlay class should be "${expectedFullClass}" for pts=${pts}`,
      ).toBe(expectedFullClass);

      // (5) The overlay must be visible (display != none).
      expect(snapshot.overlayVisible).toBe(true);

      // (6) showResult must NOT navigate the page anywhere — definitely
      //     not back to /duo/question (the original "parasitic Result→
      //     Question hop" regression). The fixture starts on about:blank
      //     after setContent(), so the URL must remain there.
      expect(
        snapshot.url,
        `showResult should not navigate; url changed from ${startUrl} → ${snapshot.url}`,
      ).toBe(startUrl);
    });
  }

  // Source-of-truth guard: the showResult body in this fixture must stay
  // byte-identical to the one in the blade view. If anyone edits one and
  // forgets the other, this test fails so they catch the drift.
  test('Fixture parity — showResult body matches duo_answer.blade.php', () => {
    const path = join(process.cwd(), 'resources/views/duo_answer.blade.php');
    const blade = readFileSync(path, 'utf8');

    // Extract the production showResult body via a brace-matching scan.
    function extractShowResult(src: string): string {
      const start = src.indexOf('function showResult(');
      expect(
        start,
        'could not locate `function showResult(` in duo_answer.blade.php',
      ).toBeGreaterThanOrEqual(0);
      const open = src.indexOf('{', start);
      let depth = 0;
      for (let i = open; i < src.length; i += 1) {
        if (src[i] === '{') depth += 1;
        else if (src[i] === '}') {
          depth -= 1;
          if (depth === 0) return src.slice(open + 1, i);
        }
      }
      throw new Error('showResult body not properly closed in duo_answer.blade.php');
    }

    const fixtureBody = extractShowResult(FIXTURE_HTML);
    let bladeBody = extractShowResult(blade);

    // Normalize the blade-side i18n placeholders to the fixture's I18N.* refs
    // so the comparison ignores Laravel-specific syntax.
    bladeBody = bladeBody
      .replace(/@json\(__\('point'\)\)/g, 'I18N.POINT')
      .replace(/@json\(__\('points'\)\)/g, 'I18N.POINTS')
      .replace(/@json\(__\('0 point'\)\)/g, 'I18N.ZERO');

    // Normalize away cosmetic differences (comments, whitespace, line breaks
    // inside expressions) so the comparison only catches substantive drift in
    // the body's logic.
    const norm = (s: string) =>
      s
        // strip /* … */ comments
        .replace(/\/\*[\s\S]*?\*\//g, '')
        // strip // line comments
        .replace(/(^|[^:])\/\/[^\n]*/g, '$1')
        // collapse all runs of whitespace (incl. newlines) to a single space
        .replace(/\s+/g, ' ')
        .trim();

    expect(
      norm(fixtureBody),
      'showResult body in this fixture has drifted from duo_answer.blade.php — keep them byte-identical (modulo whitespace + i18n placeholders)',
    ).toBe(norm(bladeBody));
  });
});
