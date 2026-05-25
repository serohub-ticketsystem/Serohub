/**
 * Globale Tastenkürzel und Rechtsklick-Steuerung für Serohub.
 * - Unterdrückt das Browser-Kontextmenü (Rechtsklick), damit eigene Kontextmenüs genutzt werden können.
 * - Unterdrückt Browser-Standard-Shortcuts (z. B. Cmd/Ctrl+S) und feuert ein Custom-Event,
 *   damit die App eigene Aktionen ausführen kann (z. B. Speichern).
 */
(function() {
    'use strict';

    // Rechtsklick: Browser-Kontextmenü unterdrücken (eigene Kontextmenüs pro Seite möglich)
    document.addEventListener('contextmenu', function(e) {
        // In Eingabefeldern Kontextmenü zulassen (Kopieren/Einfügen etc.)
        if (e.target.closest && (e.target.closest('input, textarea, [contenteditable="true"]'))) {
            return;
        }
        // Explizit erlauben: data-allow-context-menu="true"
        if (e.target.closest && e.target.closest('[data-allow-context-menu="true"]')) {
            return;
        }
        e.preventDefault();
    }, true);

    // Tastenkombinationen: Browser-Defaults unterbinden und Custom-Event auslösen
    var BLOCKED_KEYS = {
        's': true,  // Speichern (Cmd/Ctrl+S)
        'w': true,  // Fenster schließen (Cmd/Ctrl+W) – oft unerwünscht in Web-Apps
        'n': true,  // Neues Fenster (Cmd/Ctrl+N)
        't': true,  // Neuer Tab (Cmd/Ctrl+T)
        'p': true,  // Drucken (Cmd/Ctrl+P) – optional freigeben, hier blockiert für einheitliches Verhalten
        'h': true,  // Verlauf ausblenden (Cmd+Shift+H auf Mac)
        'j': true,  // Downloads (Cmd/Ctrl+J)
        'k': true   // Suchleiste (Cmd/Ctrl+K) – oft für eigene Suche gewünscht
    };

    document.addEventListener('keydown', function(e) {
        var isMac = /Mac|iPod|iPhone|iPad/.test(navigator.platform);
        var meta = isMac ? e.metaKey : e.ctrlKey;
        var key = (e.key || '').toLowerCase();

        // Nur Kombinationen mit Cmd (Mac) bzw. Ctrl (Win/Linux) abfangen
        if (!meta) {
            return;
        }

        if (BLOCKED_KEYS[key]) {
            e.preventDefault();
            e.stopPropagation();
            // Custom-Event für die App: Seiten können darauf reagieren (z. B. Cmd+S → Speichern)
            var detail = {
                key: key,
                keyCode: e.keyCode,
                metaKey: e.metaKey,
                ctrlKey: e.ctrlKey,
                shiftKey: e.shiftKey,
                altKey: e.altKey
            };
            try {
                document.dispatchEvent(new CustomEvent('app-shortcut', { detail: detail }));
            } catch (err) {
                // CustomEvent in sehr alten Browsern optional
            }
        }
    }, true);

    // Cmd/Ctrl+S global als „Speichern“: Event auslösen + Standard-Handler, der den sichtbaren Speichern-Button klickt
    document.addEventListener('app-shortcut', function(e) {
        if (e.detail && e.detail.key === 's') {
            document.dispatchEvent(new CustomEvent('app-save'));
        }
    });

    function isVisible(el) {
        if (!el || !el.getBoundingClientRect) return false;
        var rect = el.getBoundingClientRect();
        var style = window.getComputedStyle ? window.getComputedStyle(el) : el.style;
        return rect.width > 0 && rect.height > 0 &&
            style.visibility !== 'hidden' && style.display !== 'none' && style.opacity !== '0' &&
            !el.disabled;
    }

    document.addEventListener('app-save', function() {
        var target = null;

        // 1) Opt-in: Button/Element mit data-app-save
        var optIn = document.querySelector('[data-app-save="true"], [data-app-save]');
        if (optIn && isVisible(optIn)) {
            target = optIn;
        }

        // 2) Nav-Bar „Speichern“ (bei unsaved changes)
        if (!target) {
            var navBar = document.getElementById('navUnsavedChangesBar');
            var navSave = document.getElementById('navUnsavedChangesSave');
            if (navBar && navSave && !navBar.classList.contains('nav-unsaved-changes-bar-hidden') && isVisible(navSave)) {
                target = navSave;
            }
        }

        // 3) Bekannte Speichern-Buttons (IDs), nur wenn sichtbar
        if (!target) {
            var saveIds = [
                'saveAllBtn', 'drawerSaveBtn', 'editSubjectSaveBtn', 'event-modal-save', 'settings-modal-save',
                'subscription-modal-save', 'settings-save', 'edit-time-save', 'add-time-save', 'add-vacation-save',
                'saveLoginCardsBtn', 'saveBrandingBtn', 'save-ics-sources-btn', 'save-caldav-default-btn',
                'caldav-sync-save-btn', 'caldav-modal-save', 'saveTemplateMappingsBtn', 'wheel-save-btn',
                'record-save-btn', 'dumb-save-btn', 'ticket-search-scope-modal-save', 'order-search-scope-modal-save',
                'option-add-appointment', 'kb-submit'
            ];
            for (var i = 0; i < saveIds.length; i++) {
                var btn = document.getElementById(saveIds[i]);
                if (btn && isVisible(btn)) {
                    target = btn;
                    break;
                }
            }
        }

        // 4) Erstes sichtbares Formular mit type="submit"-Button (z. B. Aufgaben, Notizen, Ordner)
        if (!target) {
            var forms = document.querySelectorAll('form');
            for (var f = 0; f < forms.length; f++) {
                var form = forms[f];
                if (!form.getBoundingClientRect || form.getBoundingClientRect().height === 0) continue;
                var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submitBtn && isVisible(submitBtn)) {
                    target = submitBtn;
                    break;
                }
            }
        }

        if (target) {
            target.click();
        }
    });
})();
