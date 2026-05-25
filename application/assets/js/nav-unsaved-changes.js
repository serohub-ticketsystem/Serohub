/**
 * Nav-Unsaved-Changes – modularer Banner „Nicht gespeicherte Änderungen“
 * Nutzbar auf Bearbeitungsseiten (Kunden, Firmen, Geräte, …).
 *
 * Verwendung:
 *   NavUnsavedChanges.init({
 *     form: 'customerForm',           // Form-ID oder DOM-Element
 *     discardUrl: baseUrl + 'customers/',
 *     onSave: function() { saveCustomer(); }
 *   });
 *
 * Die Nav zeigt den Banner (anstelle der Suchleiste) erst, sobald das Formular
 * geändert wurde. Speichern/Verwerfen rufen die hier übergebenen Callbacks/URL auf.
 */
(function() {
  'use strict';

  var EVENT_NAME = 'navUnsavedChangesDirty';

  function getFormSnapshot(form) {
    var snap = {};
    var inputs = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < inputs.length; i++) {
      var el = inputs[i];
      var name = el.name || el.id;
      if (!name) continue;
      if (el.type === 'radio') {
        if (el.checked) snap[name] = el.value;
      } else if (el.type === 'checkbox') {
        snap[name] = el.checked ? (el.value || '1') : '';
      } else {
        snap[name] = (el.value || '').trim();
      }
    }
    return snap;
  }

  function snapshotsDiffer(initial, current) {
    var key;
    for (key in initial) {
      if (current[key] !== initial[key]) return true;
    }
    for (key in current) {
      if (initial[key] === undefined && current[key] !== '') return true;
    }
    return false;
  }

  /**
   * @param {Object} options
   * @param {string|HTMLFormElement} [options.form] – ein Formular
   * @param {Array<string|HTMLFormElement>} [options.forms] – mehrere Formulare (z. B. Mobil + Desktop)
   * @param {function} [options.getActiveForm] – welches Formular gilt (bei mehreren)
   * @param {string} options.discardUrl – URL für „Verwerfen“
   * @param {function} options.onSave – Callback für „Speichern“
   */
  function resolveForm(ref) {
    if (!ref) return null;
    var el = typeof ref === 'string' ? document.getElementById(ref) : ref;
    return el && el.nodeName === 'FORM' ? el : null;
  }

  function init(options) {
    if (!options) return;

    var forms = [];
    if (options.forms && options.forms.length) {
      options.forms.forEach(function(ref) {
        var f = resolveForm(ref);
        if (f) forms.push(f);
      });
    } else if (options.form) {
      var single = resolveForm(options.form);
      if (single) forms.push(single);
    }
    if (!forms.length) return;

    var getActiveForm = typeof options.getActiveForm === 'function'
      ? options.getActiveForm
      : function() { return forms[0]; };

    window.navUnsavedChangesDiscardUrl = options.discardUrl || '#';
    window.navUnsavedChangesSave = typeof options.onSave === 'function' ? options.onSave : null;

    var lastActiveForm = null;
    var initialSnapshot = {};
    var lastDirty = false;

    function setBaseline(form) {
      if (!form) return;
      initialSnapshot = getFormSnapshot(form);
      lastActiveForm = form;
    }

    function checkAndDispatch() {
      var active = getActiveForm() || forms[0];
      if (!active) return;
      if (active !== lastActiveForm) {
        setBaseline(active);
        if (lastDirty) {
          lastDirty = false;
          document.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: { dirty: false } }));
        }
        return;
      }
      var dirty = snapshotsDiffer(initialSnapshot, getFormSnapshot(active));
      if (dirty !== lastDirty) {
        lastDirty = dirty;
        document.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: { dirty: dirty } }));
      }
    }

    setBaseline(getActiveForm() || forms[0]);

    forms.forEach(function(form) {
      form.addEventListener('input', checkAndDispatch);
      form.addEventListener('change', checkAndDispatch);
    });
    window.addEventListener('resize', checkAndDispatch);
  }

  window.NavUnsavedChanges = { init: init };
})();
