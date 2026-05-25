/**
 * Leichtes haptisches Feedback (Tipp) für UI-Elemente.
 * – Android u. a.: Vibration API
 * – iOS Safari: verstecktes <input switch> (System-Haptik ab Safari 17.4)
 */
(function () {
  'use strict';

  /** iPhone/iPad (inkl. Chrome/Firefox auf iOS) — vibrate oft wirkungslos oder irreführend; dort nur switch-Fallback. */
  function isIOSLike() {
    if (typeof navigator === 'undefined') return false;
    var ua = navigator.userAgent || '';
    if (/iPad|iPhone|iPod/.test(ua)) return true;
    if (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) return true;
    return false;
  }

  function hapticLightTap() {
    try {
      /* Android & Co.: vibrate — Rückgabewert ist oft undefined, nicht true; trotzdem nur ein Aufruf. */
      if (!isIOSLike() && typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
        try {
          navigator.vibrate(12);
        } catch (e) { /* ignore */ }
        return;
      }
      var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
      var hasTouch = typeof navigator !== 'undefined' && navigator.maxTouchPoints > 0;
      if (!coarse && !hasTouch) {
        return;
      }
      var labelEl = document.createElement('label');
      labelEl.setAttribute('aria-hidden', 'true');
      labelEl.style.display = 'none';
      var inputEl = document.createElement('input');
      inputEl.type = 'checkbox';
      inputEl.setAttribute('switch', '');
      labelEl.appendChild(inputEl);
      document.head.appendChild(labelEl);
      labelEl.click();
      document.head.removeChild(labelEl);
    } catch (e) { /* keine Haptik verfügbar */ }
  }

  if (typeof window !== 'undefined') {
    window.hapticLightTap = hapticLightTap;
  }
})();
