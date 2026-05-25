<?php
$toastDisplay = 'errors_only';
if (!empty($pdo) && !empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'toast_display'");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['setting_value'] !== null && in_array($row['setting_value'], ['all', 'errors_only', 'none'], true)) {
            $toastDisplay = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // Default beibehalten
    }
}
?>
<style>
/* Smartphone/Tablet: iOS-Banner-ähnlich (von oben, leichter Federimpuls), direkt unter #main-nav (h-14) */
@media (max-width: 1023px) {
    /* data-toast-exiting: Eintritts-Animation aus, damit Ausblend (WAAPI/Transition) nicht von Keyframes geschluckt wird */
    #toast-top-right:not(.hidden):not([data-toast-exiting="1"]) {
        transform-origin: top center;
        touch-action: none;
        /* Easing wie iOS: schneller Start, weiches Auslaufen */
        animation: toast-ios-banner 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
}
@media (max-width: 1023px) and (prefers-reduced-motion: reduce) {
    #toast-top-right:not(.hidden):not([data-toast-exiting="1"]) {
        animation: none;
    }
}
/* Während Ausblend-Animation keine Touches/Klicks abfangen (sonst „hängender“ Overlay-Effekt auf Mobilgeräten) */
#toast-top-right[data-toast-exiting="1"] {
    pointer-events: none;
}

@keyframes toast-ios-banner {
    0% {
        opacity: 0;
        transform: translate3d(0, -1.25rem, 0) scale(0.92);
    }
    /* leichter „Aufprall“ wie beim Banner, dann einrasten */
    65% {
        opacity: 1;
        transform: translate3d(0, 0.125rem, 0) scale(1.01);
    }
    82% {
        transform: translate3d(0, -0.0625rem, 0) scale(1);
    }
    100% {
        opacity: 1;
        transform: translate3d(0, 0, 0) scale(1);
    }
}
</style>
<div id="toast-top-right" class="fixed flex items-center gap-2 max-w-none inset-x-4 top-[calc(3.5rem+env(safe-area-inset-top,0px)+0.5rem)] bottom-auto p-3.5 text-gray-900 bg-white dark:bg-gray-800 dark:text-white rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 ring-1 ring-black/[0.04] dark:ring-white/[0.06] z-50 hidden lg:gap-0 lg:inset-x-auto lg:top-20 lg:end-5 lg:w-full lg:max-w-xs lg:p-4 lg:rounded-lg lg:shadow-lg lg:ring-0" role="alert">
    <div class="text-sm font-medium leading-snug lg:font-normal flex-1 min-w-0 pr-1 whitespace-pre-line" id="toast-message">Top right positioning.</div>
    <button type="button" class="ms-auto shrink-0 -mx-1 -my-1 bg-white text-gray-400 hover:text-gray-900 rounded-full focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700 lg:rounded-lg lg:-mx-1.5 lg:-my-1.5" onclick="hideToast()" aria-label="Close">
        <span class="sr-only">Close</span>
        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
        </svg>
    </button>
</div>
<div id="upload-progress-toast" class="fixed hidden inset-x-4 top-[calc(3.5rem+env(safe-area-inset-top,0px)+0.5rem)] z-50 p-3.5 rounded-2xl shadow-xl border border-blue-200/90 bg-blue-50 text-blue-900 ring-1 ring-black/[0.04] dark:ring-white/[0.06] dark:bg-blue-950/90 dark:text-blue-100 dark:border-blue-800 lg:inset-x-auto lg:top-20 lg:end-5 lg:w-full lg:max-w-xs lg:p-4 lg:rounded-lg lg:shadow-lg lg:ring-0" role="status" aria-live="polite" aria-atomic="true">
    <div class="flex items-center justify-between gap-3">
        <div id="upload-progress-toast-message" class="text-sm font-medium leading-snug min-w-0 flex-1 whitespace-pre-line">Datei wird hochgeladen...</div>
        <div id="upload-progress-toast-percent" class="text-xs font-semibold tabular-nums shrink-0">0%</div>
    </div>
    <div class="mt-2 h-2 w-full rounded-full bg-blue-200/80 dark:bg-blue-900/70 overflow-hidden">
        <div id="upload-progress-toast-bar" class="h-full rounded-full bg-blue-600 dark:bg-blue-400 transition-all duration-200 ease-out" style="width:0%"></div>
    </div>
</div>

<script>
window.toastDisplaySetting = <?php echo json_encode($toastDisplay); ?>;

var toastHideTimer = null;
/** Verhindert, dass verzögertes resetInlineVisual (nach Zurückfedern) die Ausblend-Animation zerstört */
var toastResetVisualTimer = null;

function clearToastResetVisualTimer() {
    if (toastResetVisualTimer) {
        clearTimeout(toastResetVisualTimer);
        toastResetVisualTimer = null;
    }
}

/** Anzeigedauer bis zum automatischen Schließen */
var TOAST_DEFAULT_DURATION_MS = 4800;
/** Dauer der Ausblend-Animation (nach oben, wie Wisch) */
var TOAST_EXIT_DURATION_MS = 440;

function syncUploadToastPosition() {
    var mainToast = document.getElementById('toast-top-right');
    var uploadToast = document.getElementById('upload-progress-toast');
    if (!mainToast || !uploadToast || uploadToast.classList.contains('hidden')) return;

    // Standardposition: identisch zur normalen Toast-Position
    uploadToast.style.top = '';
    uploadToast.style.left = '';
    uploadToast.style.right = '';
    uploadToast.style.width = '';
    uploadToast.style.maxWidth = '';

    // Wenn die normale Toast sichtbar ist, Upload-Toast darunter stapeln
    if (!mainToast.classList.contains('hidden')) {
        var rect = mainToast.getBoundingClientRect();
        var spacing = 8;
        uploadToast.style.top = (rect.bottom + spacing) + 'px';
        uploadToast.style.left = rect.left + 'px';
        uploadToast.style.right = 'auto';
        uploadToast.style.width = rect.width + 'px';
        uploadToast.style.maxWidth = rect.width + 'px';
    }
}

function showToast(message, type = 'info', duration = TOAST_DEFAULT_DURATION_MS) {
    const setting = typeof window.toastDisplaySetting !== 'undefined' ? window.toastDisplaySetting : 'errors_only';
    if (setting === 'none') return;
    if (setting === 'errors_only' && type !== 'error') return;

    const toast = document.getElementById('toast-top-right');
    const toastMessage = document.getElementById('toast-message');
    
    if (!toast || !toastMessage) return;

    if (toastHideTimer) {
        clearTimeout(toastHideTimer);
        toastHideTimer = null;
    }
    clearToastResetVisualTimer();
    try {
        toast.getAnimations().forEach(function (a) { a.cancel(); });
    } catch (e) {}
    if (toast._waExitAnim && typeof toast._waExitAnim.cancel === 'function') {
        try { toast._waExitAnim.cancel(); } catch (e) {}
        toast._waExitAnim = null;
    }
    toast.removeAttribute('data-toast-exiting');
    toast.style.transform = '';
    toast.style.opacity = '';
    toast.style.transition = '';
    toast.style.animation = '';
    
    // Nachricht setzen
    toastMessage.textContent = message || 'Top right positioning.';
    
    // Typ-basierte Farben (Mobile: unter #main-nav h-14; Desktop lg: unverändert oben rechts)
    const baseClasses = 'fixed flex items-center gap-2 max-w-none inset-x-4 top-[calc(3.5rem+env(safe-area-inset-top,0px)+0.5rem)] bottom-auto p-3.5 rounded-2xl shadow-xl z-50 lg:gap-0 lg:inset-x-auto lg:top-20 lg:end-5 lg:w-full lg:max-w-xs lg:p-4 lg:rounded-lg lg:shadow-lg';
    const mobileRing = ' ring-1 ring-black/[0.04] dark:ring-white/[0.06] lg:ring-0';
    if (type === 'success') {
        toast.className = baseClasses + mobileRing + ' text-green-800 bg-green-50 dark:bg-green-900 dark:text-green-200 border border-green-200/90 dark:border-green-700';
    } else if (type === 'error') {
        toast.className = baseClasses + mobileRing + ' text-red-800 bg-red-50 dark:bg-red-900 dark:text-red-200 border border-red-200/90 dark:border-red-700';
    } else if (type === 'warning') {
        toast.className = baseClasses + mobileRing + ' text-yellow-800 bg-yellow-50 dark:bg-yellow-900 dark:text-yellow-200 border border-yellow-200/90 dark:border-yellow-700';
    } else {
        // Standard (info)
        toast.className = baseClasses + mobileRing + ' text-gray-900 bg-white dark:bg-gray-800 dark:text-white border border-gray-200 dark:border-gray-700';
    }
    
    // Toast anzeigen
    toast.classList.remove('hidden');
    syncUploadToastPosition();
    
    // Automatisch nach duration verstecken (mit gleicher Ausblend-Animation wie Wisch)
    if (duration > 0) {
        toastHideTimer = setTimeout(function () {
            toastHideTimer = null;
            hideToast();
        }, duration);
    }
}

function hideToastImmediate() {
    const toast = document.getElementById('toast-top-right');
    if (!toast) return;
    if (toastHideTimer) {
        clearTimeout(toastHideTimer);
        toastHideTimer = null;
    }
    clearToastResetVisualTimer();
    try {
        toast.getAnimations().forEach(function (a) { a.cancel(); });
    } catch (e) {}
    if (toast._waExitAnim && typeof toast._waExitAnim.cancel === 'function') {
        try { toast._waExitAnim.cancel(); } catch (e) {}
        toast._waExitAnim = null;
    }
    toast.style.transform = '';
    toast.style.opacity = '';
    toast.style.transition = '';
    toast.style.animation = '';
    toast.removeAttribute('data-toast-exiting');
    toast.classList.add('hidden');
    syncUploadToastPosition();
}

/** Gleiche Bewegung wie beim Nach-oben-Wischen: nach oben aus dem Bild fahren, dann ausblenden. */
function runToastExitAnimationThen(doneCb) {
    var toast = document.getElementById('toast-top-right');
    if (!toast || toast.classList.contains('hidden')) {
        if (doneCb) doneCb();
        return;
    }
    if (toastHideTimer) {
        clearTimeout(toastHideTimer);
        toastHideTimer = null;
    }
    clearToastResetVisualTimer();
    try {
        toast.getAnimations().forEach(function (a) { a.cancel(); });
    } catch (e) {}
    toast._waExitAnim = null;

    void toast.offsetWidth;
    var csBefore = window.getComputedStyle(toast);
    var tStart = csBefore.transform;
    if (!tStart || tStart === 'none') {
        tStart = 'translate3d(0,0,0)';
    }
    var oStart = parseFloat(csBefore.opacity);
    if (isNaN(oStart)) oStart = 1;

    toast.setAttribute('data-toast-exiting', '1');
    toast.style.animation = 'none';
    toast.style.transform = tStart;
    toast.style.opacity = String(oStart);
    void toast.offsetWidth;

    var exitDone = false;
    var exitFallbackTimer = null;

    function finish() {
        if (exitDone) return;
        exitDone = true;
        if (exitFallbackTimer !== null) {
            clearTimeout(exitFallbackTimer);
            exitFallbackTimer = null;
        }
        toast._waExitAnim = null;
        hideToastImmediate();
        if (doneCb) doneCb();
    }

    /* Web Animations API: auf iOS/Safari zuverlässiger als transition nach CSS-Keyframes */
    if (typeof toast.animate === 'function') {
        var anim = toast.animate(
            [
                { transform: tStart, opacity: oStart },
                { transform: 'translate3d(0,-130%,0)', opacity: 0 }
            ],
            {
                duration: TOAST_EXIT_DURATION_MS,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                fill: 'forwards'
            }
        );
        toast._waExitAnim = anim;
        anim.onfinish = function () {
            finish();
        };
        anim.oncancel = function () {
            toast._waExitAnim = null;
        };
        exitFallbackTimer = setTimeout(function () {
            if (toast._waExitAnim === anim) finish();
        }, TOAST_EXIT_DURATION_MS + 400);
        return;
    }

    /* Fallback: transition + erzwungener Reflow (ältere Browser) */
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            var s = (TOAST_EXIT_DURATION_MS / 1000) + 's';
            toast.style.transition = 'transform ' + s + ' cubic-bezier(0.22, 1, 0.36, 1), opacity ' + s + ' ease-out';
            toast.style.transform = 'translate3d(0,-130%,0)';
            toast.style.opacity = '0';
            var trDone = false;
            function done() {
                if (trDone) return;
                trDone = true;
                toast.removeEventListener('transitionend', te);
                finish();
            }
            function te(ev) {
                if (ev.propertyName !== 'transform' && ev.propertyName !== 'opacity') return;
                done();
            }
            toast.addEventListener('transitionend', te);
            setTimeout(done, TOAST_EXIT_DURATION_MS + 280);
        });
    });
}

function hideToast() {
    var toast = document.getElementById('toast-top-right');
    if (!toast || toast.classList.contains('hidden')) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        if (toastHideTimer) {
            clearTimeout(toastHideTimer);
            toastHideTimer = null;
        }
        hideToastImmediate();
        return;
    }
    runToastExitAnimationThen();
}

function showUploadProgressToast(message, progressPercent) {
    var toast = document.getElementById('upload-progress-toast');
    var messageEl = document.getElementById('upload-progress-toast-message');
    var percentEl = document.getElementById('upload-progress-toast-percent');
    var barEl = document.getElementById('upload-progress-toast-bar');
    if (!toast || !messageEl || !percentEl || !barEl) return;

    var safeProgress = Number.isFinite(progressPercent) ? progressPercent : 0;
    safeProgress = Math.max(0, Math.min(100, Math.round(safeProgress)));

    messageEl.textContent = message || 'Datei wird hochgeladen...';
    percentEl.textContent = safeProgress + '%';
    barEl.style.width = safeProgress + '%';
    toast.classList.remove('hidden');
    syncUploadToastPosition();
}

function updateUploadProgressToast(progressPercent, message) {
    var messageEl = document.getElementById('upload-progress-toast-message');
    var fallbackMessage = messageEl ? messageEl.textContent : 'Datei wird hochgeladen...';
    showUploadProgressToast(message || fallbackMessage || 'Datei wird hochgeladen...', progressPercent);
}

function hideUploadProgressToast() {
    var toast = document.getElementById('upload-progress-toast');
    if (!toast) return;
    toast.style.top = '';
    toast.style.left = '';
    toast.style.right = '';
    toast.style.width = '';
    toast.style.maxWidth = '';
    toast.classList.add('hidden');
}

window.addEventListener('resize', syncUploadToastPosition);
window.addEventListener('scroll', syncUploadToastPosition, { passive: true });

(function initToastSwipeDismiss() {
    var toast = document.getElementById('toast-top-right');
    if (!toast) return;
    var mq = window.matchMedia('(max-width: 1023px)');
    var startY = 0;
    var startT = 0;
    var dragging = false;

    function resetInlineVisual() {
        if (!toast.classList.contains('hidden')) {
            toast.style.transform = '';
            toast.style.opacity = '';
        }
        toast.style.transition = '';
        toast.style.animation = '';
    }

    function scheduleResetInlineVisual(delay) {
        clearToastResetVisualTimer();
        toastResetVisualTimer = setTimeout(function () {
            toastResetVisualTimer = null;
            resetInlineVisual();
        }, delay);
    }

    function onTouchStart(e) {
        if (!mq.matches || toast.classList.contains('hidden')) return;
        if (!e.touches || !e.touches.length) return;
        clearToastResetVisualTimer();
        startY = e.touches[0].clientY;
        startT = Date.now();
        dragging = true;
        toast.style.animation = 'none';
        toast.style.transition = '';
    }

    function onTouchMove(e) {
        if (!dragging || !mq.matches || !e.touches || !e.touches.length) return;
        var dy = e.touches[0].clientY - startY;
        if (dy < 0) {
            e.preventDefault();
            var y = Math.max(dy, -200);
            toast.style.transform = 'translate3d(0,' + y + 'px,0)';
            toast.style.opacity = String(Math.max(0.2, 1 + y / 130));
        } else if (dy > 14) {
            dragging = false;
            toast.style.transition = 'transform 0.2s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.2s ease';
            toast.style.transform = '';
            toast.style.opacity = '';
            scheduleResetInlineVisual(220);
        }
    }

    function onTouchEnd(e) {
        if (!dragging) return;
        dragging = false;
        if (!mq.matches || toast.classList.contains('hidden')) return;
        var t = e.changedTouches && e.changedTouches[0];
        if (!t) return;
        var dy = t.clientY - startY;
        var dt = Math.max(1, Date.now() - startT);
        var vel = dy / dt;
        var dismiss = dy < -44 || vel < -0.42;
        if (dismiss) {
            runToastExitAnimationThen();
        } else {
            toast.style.transition = 'transform 0.22s cubic-bezier(0.34, 1.2, 0.64, 1), opacity 0.22s ease';
            toast.style.transform = '';
            toast.style.opacity = '';
            scheduleResetInlineVisual(230);
        }
    }

    toast.addEventListener('touchstart', onTouchStart, { passive: true });
    toast.addEventListener('touchmove', onTouchMove, { passive: false });
    toast.addEventListener('touchend', onTouchEnd, { passive: true });
    toast.addEventListener('touchcancel', onTouchEnd, { passive: true });
})();

// Toast automatisch anzeigen, wenn Session-Variable gesetzt ist
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['toast_message'])): ?>
        var _type = <?php echo json_encode($_SESSION['toast_type'] ?? 'info'); ?>;
        var _setting = typeof window.toastDisplaySetting !== 'undefined' ? window.toastDisplaySetting : 'errors_only';
        if (_setting !== 'none' && (_setting === 'all' || _type === 'error')) {
            showToast(<?php echo json_encode($_SESSION['toast_message']); ?>, _type);
        }
        <?php 
        unset($_SESSION['toast_message']);
        unset($_SESSION['toast_type']);
        ?>
    <?php endif; ?>
});
</script>