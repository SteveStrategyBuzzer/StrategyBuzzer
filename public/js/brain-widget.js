/**
 * brain-widget.js
 * Watches [data-stat="efficiencyPercent"] spans inside .brain-widget elements.
 * Applies the correct visual stage class whenever efficiency changes.
 * Works alongside GameplayRuntime.js — no modifications to existing files needed.
 */
(function () {
    'use strict';

    var STAGES = [
        { min: 0,  cls: 'brain-stage-frozen' },
        { min: 20, cls: 'brain-stage-awakening' },
        { min: 35, cls: 'brain-stage-synaptic' },
        { min: 50, cls: 'brain-stage-aura' },
        { min: 65, cls: 'brain-stage-energized' },
        { min: 80, cls: 'brain-stage-flames' },
        { min: 90, cls: 'brain-stage-ultimate' },
    ];
    var ALL_STAGE_CLASSES = STAGES.map(function (s) { return s.cls; });

    function parsePct(str) {
        if (!str) return 0;
        return parseFloat(String(str).replace('%', '').trim()) || 0;
    }

    function stageFor(pct) {
        var result = STAGES[0].cls;
        for (var i = 0; i < STAGES.length; i++) {
            if (pct >= STAGES[i].min) result = STAGES[i].cls;
        }
        return result;
    }

    function applyStage(brainEl, pct) {
        ALL_STAGE_CLASSES.forEach(function (c) { brainEl.classList.remove(c); });
        brainEl.classList.add(stageFor(pct));
    }

    function initWidget(brainEl) {
        var effSpan = brainEl.querySelector('[data-stat="efficiencyPercent"]');
        if (!effSpan) return;

        applyStage(brainEl, parsePct(effSpan.textContent));

        new MutationObserver(function () {
            applyStage(brainEl, parsePct(effSpan.textContent));
        }).observe(effSpan, { childList: true, characterData: true, subtree: true });
    }

    function init() {
        document.querySelectorAll('.brain-widget').forEach(initWidget);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
