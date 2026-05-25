/**
 * Automatisches Ausfüllen des Orts (Ort) bei Eingabe einer deutschen PLZ.
 * Nutzt die kostenlose OpenPLZ-API: https://openplzapi.org/
 * Nach dem Verlassen des PLZ-Felds (blur) wird bei 5-stelliger PLZ der Ort geladen.
 */
(function() {
    var PLZ_ORT_PAIRS = [
        { plzId: 'plz', ortId: 'ort' },
        { plzId: 'liefer_plz', ortId: 'liefer_ort' },
        { plzId: 'rechnungs_plz', ortId: 'rechnungs_ort' }
    ];
    var ATTR_BOUND = 'data-plz-ort-bound';

    function normalizePlz(value) {
        if (typeof value !== 'string') return '';
        return value.replace(/\D/g, '').slice(0, 5);
    }

    function fetchOrtFromPlz(plz, callback) {
        var code = normalizePlz(plz);
        if (code.length !== 5) {
            callback(null);
            return;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'https://openplzapi.org/de/Localities?postalCode=' + encodeURIComponent(code), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            if (xhr.status !== 200) {
                callback(null);
                return;
            }
            try {
                var data = JSON.parse(xhr.responseText);
                if (Array.isArray(data) && data.length > 0 && data[0].name) {
                    callback(data[0].name);
                } else {
                    callback(null);
                }
            } catch (e) {
                callback(null);
            }
        };
        xhr.onerror = function() { callback(null); };
        xhr.send();
    }

    function setupPair(plzId, ortId) {
        var plzEl = document.getElementById(plzId);
        var ortEl = document.getElementById(ortId);
        if (!plzEl || !ortEl || plzEl.getAttribute(ATTR_BOUND)) return;
        plzEl.setAttribute(ATTR_BOUND, '1');
        plzEl.addEventListener('blur', function() {
            var plz = plzEl.value;
            if (normalizePlz(plz).length !== 5) return;
            fetchOrtFromPlz(plz, function(ort) {
                if (ort) ortEl.value = ort;
            });
        });
    }

    function init() {
        PLZ_ORT_PAIRS.forEach(function(pair) {
            setupPair(pair.plzId, pair.ortId);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.setupPlzOrtAutofill = init;
})();
