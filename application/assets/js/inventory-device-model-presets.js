/**
 * Benannte Vorlagen für Lager-Gerätemodelle (localStorage).
 * Eine Zeichnung / eine Drucker-Serie kann so für mehrere Artikel wiederverwendet werden.
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'inventory_device_model_presets_v1';
    var MAX_PRESETS = 40;

    function read() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            var a = JSON.parse(raw);
            return Array.isArray(a) ? a : [];
        } catch (e) {
            return [];
        }
    }

    function write(arr) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
        } catch (e) {}
    }

    function genId() {
        return 'p_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9);
    }

    function normalizeModels(deviceModels) {
        if (!deviceModels || !deviceModels.length) return [];
        var out = [];
        deviceModels.forEach(function (dm) {
            var h = (dm && dm.hersteller != null) ? String(dm.hersteller).trim() : '';
            var m = (dm && dm.modell != null) ? String(dm.modell).trim() : '';
            if (h || m) out.push({ hersteller: h, modell: m });
        });
        return out;
    }

    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = String(s);
        return div.innerHTML;
    }

    var api = {
        list: function () {
            return read().sort(function (a, b) {
                return (b.updatedAt || 0) - (a.updatedAt || 0);
            });
        },

        save: function (name, deviceModels) {
            name = (name || '').trim();
            if (!name) return { success: false, error: 'Bitte einen Namen eingeben.' };
            var models = normalizeModels(deviceModels);
            if (models.length === 0) {
                return { success: false, error: 'Mindestens ein Hersteller oder Modell eintragen.' };
            }
            var arr = read();
            var lower = name.toLowerCase();
            var existing = -1;
            for (var i = 0; i < arr.length; i++) {
                if ((arr[i].name || '').toLowerCase() === lower) {
                    existing = i;
                    break;
                }
            }
            var entry = {
                id: existing >= 0 ? arr[existing].id : genId(),
                name: name,
                device_models: models,
                updatedAt: Date.now()
            };
            if (existing >= 0) {
                arr[existing] = entry;
            } else {
                if (arr.length >= MAX_PRESETS) arr.pop();
                arr.push(entry);
            }
            write(arr);
            return { success: true };
        },

        remove: function (id) {
            if (!id) return;
            write(read().filter(function (p) { return p.id !== id; }));
        },

        normalizeModels: normalizeModels,

        refreshSelect: function (selectEl) {
            if (!selectEl) return;
            var presets = api.list();
            selectEl.innerHTML = '<option value="">— Vorlage wählen —</option>' +
                presets.map(function (p) {
                    var n = (p.device_models && p.device_models.length) ? p.device_models.length : 0;
                    return '<option value="' + escapeHtml(p.id) + '">' + escapeHtml(p.name) + ' (' + n + ')</option>';
                }).join('');
        },

        /**
         * @param {{ selectId: string, applyBtnId: string, saveBtnId: string, deleteBtnId: string,
         *   getModels: function(): Array<{hersteller:string,modell:string}>,
         *   applyModels: function(Array<{hersteller:string,modell:string}>): void }} opts
         */
        bindUi: function (opts) {
            var sel = document.getElementById(opts.selectId);
            var applyBtn = document.getElementById(opts.applyBtnId);
            var saveBtn = document.getElementById(opts.saveBtnId);
            var delBtn = document.getElementById(opts.deleteBtnId);
            if (!sel || !applyBtn || !saveBtn || !delBtn || typeof opts.getModels !== 'function' || typeof opts.applyModels !== 'function') {
                return;
            }

            function toast(msg, type) {
                if (typeof global.showToast === 'function') global.showToast(msg, type || 'info');
            }

            function refresh() {
                api.refreshSelect(sel);
            }

            refresh();

            applyBtn.addEventListener('click', function () {
                var id = sel.value;
                if (!id) {
                    toast('Bitte zuerst eine Vorlage auswählen.', 'error');
                    return;
                }
                var presets = api.list();
                var preset = null;
                for (var i = 0; i < presets.length; i++) {
                    if (presets[i].id === id) {
                        preset = presets[i];
                        break;
                    }
                }
                if (!preset) {
                    refresh();
                    toast('Vorlage nicht gefunden.', 'error');
                    return;
                }
                var current = normalizeModels(opts.getModels());
                var hasContent = current.length > 0;
                if (hasContent && !global.confirm('Die aktuellen Gerätemodelle werden durch die Vorlage ersetzt. Fortfahren?')) {
                    return;
                }
                opts.applyModels(preset.device_models || []);
                toast('Vorlage „' + preset.name + '“ übernommen.', 'success');
            });

            saveBtn.addEventListener('click', function () {
                var models = normalizeModels(opts.getModels());
                if (models.length === 0) {
                    toast('Keine Gerätemodelle zum Speichern (mindestens eine Zeile ausfüllen).', 'error');
                    return;
                }
                var name = global.prompt('Name für diese Vorlage (z. B. eine Drucker-Serie aus der Explosionszeichnung):', '');
                if (name === null) return;
                var r = api.save(name, models);
                if (r.success) {
                    refresh();
                    toast('Vorlage gespeichert.', 'success');
                } else {
                    toast(r.error || 'Fehler', 'error');
                }
            });

            delBtn.addEventListener('click', function () {
                var id = sel.value;
                if (!id) {
                    toast('Bitte zuerst eine Vorlage auswählen.', 'error');
                    return;
                }
                var presets = api.list();
                var preset = null;
                for (var j = 0; j < presets.length; j++) {
                    if (presets[j].id === id) {
                        preset = presets[j];
                        break;
                    }
                }
                if (!preset) {
                    refresh();
                    return;
                }
                if (!global.confirm('Vorlage „' + preset.name + '“ wirklich löschen?')) return;
                api.remove(id);
                refresh();
                sel.value = '';
                toast('Vorlage gelöscht.', 'success');
            });
        }
    };

    global.InvDeviceModelPresets = api;
})(typeof window !== 'undefined' ? window : this);
