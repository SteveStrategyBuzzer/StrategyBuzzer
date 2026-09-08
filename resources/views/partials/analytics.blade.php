<script>
(function () {
    'use strict';

    window.trackEvent = function (name, data) {
        if (typeof name !== 'string' || name.length === 0) return;

        try {
            if (window.umami && typeof window.umami.track === 'function') {
                window.umami.track(name, data || {});
            }
        } catch (_) {
            // Analytics must never interrupt gameplay or navigation.
        }
    };

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-analytics-event]');
        if (!form) return;

        var data = {};
        Object.keys(form.dataset).forEach(function (key) {
            if (key !== 'analyticsEvent') {
                data[key.replace(/^analytics/, '').replace(/^[A-Z]/, function (letter) {
                    return letter.toLowerCase();
                }).replace(/[A-Z]/g, function (letter) {
                    return '_' + letter.toLowerCase();
                })] = form.dataset[key];
            }
        });

        window.trackEvent(form.dataset.analyticsEvent, data);
    }, true);

    document.addEventListener('click', function (event) {
        var element = event.target.closest('[data-analytics-event]:not(form)');
        if (!element) return;

        var data = {};
        Object.keys(element.dataset).forEach(function (key) {
            if (key !== 'analyticsEvent') {
                data[key.replace(/^analytics/, '').replace(/^[A-Z]/, function (letter) {
                    return letter.toLowerCase();
                }).replace(/[A-Z]/g, function (letter) {
                    return '_' + letter.toLowerCase();
                })] = element.dataset[key];
            }
        });

        window.trackEvent(element.dataset.analyticsEvent, data);
    }, true);
})();
</script>