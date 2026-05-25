/**
 * System-Sounds für Serohub (Web Audio API, keine Audiodateien).
 * - Aufgabe erledigt: kurzer aufsteigender Ton (C-E-G)
 * - Ticket geschlossen: tieferer „abschließender“ Zweiklang
 * - Neue Benachrichtigung: dezenter Hinweiston (zwei Töne)
 * An/Aus in Einstellungen > Präferenzen > Töne (user_settings.sounds_enabled / localStorage sounds_enabled).
 * Browser erlauben Audio oft erst nach Nutzerinteraktion.
 */
(function() {
    'use strict';

    function soundsEnabled() {
        try {
            return localStorage.getItem('sounds_enabled') !== '0';
        } catch (e) {
            return true;
        }
    }

    var C = window.AudioContext || window.webkitAudioContext;
    if (!C) {
        window.playTaskCompletedSound = window.playTicketClosedSound = window.playNewNotificationSound = function() {};
        return;
    }

    function playTone(ctx, freq, start, duration, gainVal, type) {
        type = type || 'sine';
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = type;
        osc.frequency.setValueAtTime(freq, start);
        gain.gain.setValueAtTime(gainVal, start);
        gain.gain.exponentialRampToValueAtTime(0.01, start + duration);
        osc.start(start);
        osc.stop(start + duration);
    }

    function playTaskCompletedSound() {
        if (!soundsEnabled()) return;
        try {
            var ctx = new C();
            var now = ctx.currentTime;
            var g = 0.15;
            playTone(ctx, 523.25, now, 0.08, g);
            playTone(ctx, 659.25, now + 0.08, 0.08, g);
            playTone(ctx, 783.99, now + 0.16, 0.12, g);
        } catch (e) {}
    }

    /** Ton wenn ein Ticket auf „Geschlossen“ gesetzt wird */
    function playTicketClosedSound() {
        if (!soundsEnabled()) return;
        try {
            var ctx = new C();
            var now = ctx.currentTime;
            var g = 0.18;
            playTone(ctx, 392, now, 0.1, g);       // G4
            playTone(ctx, 523.25, now + 0.12, 0.2, g); // C5 – abschließend
        } catch (e) {}
    }

    /** Ton wenn neue Benachrichtigung(en) da sind (z. B. beim Polling) */
    function playNewNotificationSound() {
        if (!soundsEnabled()) return;
        try {
            var ctx = new C();
            var now = ctx.currentTime;
            var g = 0.12;
            playTone(ctx, 659.25, now, 0.06, g);   // E5
            playTone(ctx, 783.99, now + 0.1, 0.12, g); // G5
        } catch (e) {}
    }

    window.playTaskCompletedSound = playTaskCompletedSound;
    window.playTicketClosedSound = playTicketClosedSound;
    window.playNewNotificationSound = playNewNotificationSound;
})();
