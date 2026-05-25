/**
 * Gerätemodell-Zeilen: sobald die letzte Zeile ausgefüllt wird, erscheint automatisch eine neue leere Zeile.
 */
(function (global) {
    'use strict';

    function ensureTrailingEmptyRow(container, addDeviceModelRow) {
        if (!container || typeof addDeviceModelRow !== 'function') return;
        var rows = Array.prototype.slice.call(container.querySelectorAll('[data-row-id]'));
        if (rows.length === 0) {
            addDeviceModelRow('', '');
            return;
        }
        var lastIdx = -1;
        for (var i = rows.length - 1; i >= 0; i--) {
            var h = (rows[i].querySelector('.consumable-hersteller') || {}).value || '';
            var m = (rows[i].querySelector('.consumable-modell') || {}).value || '';
            if (h.trim() || m.trim()) {
                lastIdx = i;
                break;
            }
        }
        if (lastIdx === -1) return;
        if (lastIdx === rows.length - 1) {
            addDeviceModelRow('', '');
        }
    }

    /**
     * @param {string} containerId z. B. 'deviceModelsContainer'
     * @param {function(string,string):void} addDeviceModelRow
     */
    function bind(containerId, addDeviceModelRow) {
        var container = document.getElementById(containerId);
        if (!container) return;

        function run() {
            ensureTrailingEmptyRow(container, addDeviceModelRow);
        }

        container.addEventListener('input', run);
        container.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('button') : null;
            if (!btn || !container.contains(btn) || !btn.closest('[data-row-id]')) return;
            global.setTimeout(run, 0);
        });

        run();
    }

    global.InvDeviceModelAutoRow = {
        ensure: ensureTrailingEmptyRow,
        bind: bind
    };
})(typeof window !== 'undefined' ? window : this);
