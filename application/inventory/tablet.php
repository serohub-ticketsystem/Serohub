<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
require_once dirname(__DIR__) . '/assets/inventory_permissions.php';
requireLogin();

$userId = $_SESSION['user_id'];
inventory_permissions_ensure_columns($pdo);
$invUser = inventory_permissions_load_user($pdo, (int)$userId);
if (!$invUser) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}
if (!inventory_user_can_adjust_from_row($invUser)) {
    showPermissionDeniedPage();
}

$pageTitle = 'Lager Tablet';
include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>
<body class="bg-gray-100 dark:bg-primary-50 min-h-screen flex flex-col">
  <header class="flex-shrink-0 p-4 absolute top-0 left-0 right-0 z-10">
    <a href="<?php echo htmlspecialchars(BASE_URL); ?>inventory/" class="inline-flex items-center text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
      </svg>
      Zurück
    </a>
  </header>

  <main class="flex-1 flex flex-col items-center justify-center p-4 pt-16 w-full max-w-2xl mx-auto">
    <!-- Inhalt vertikal zentriert -->
    <div class="w-full flex flex-col items-center gap-8">
      <!-- Zwei Kacheln -->
      <div class="flex flex-col sm:flex-row gap-4 w-full">
        <button type="button" id="btn-einlagern" class="flex-1 min-h-[100px] sm:min-h-[120px] flex flex-col items-center justify-center gap-2 p-6 rounded-2xl border-2 border-green-400 bg-green-50 dark:bg-green-900/20 dark:border-green-600 shadow-md hover:shadow-lg text-2xl font-bold text-green-800 dark:text-green-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all duration-200 active:scale-[0.98]">
          <svg class="w-10 h-10 sm:w-12 sm:h-12 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
          Einlagern
        </button>
        <button type="button" id="btn-auslagern" class="flex-1 min-h-[100px] sm:min-h-[120px] flex flex-col items-center justify-center gap-2 p-6 rounded-2xl border-2 border-amber-400 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-600 shadow-md hover:shadow-lg text-2xl font-bold text-amber-800 dark:text-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all duration-200 active:scale-[0.98]">
          <svg class="w-10 h-10 sm:w-12 sm:h-12 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v14"/></svg>
          Auslagern
        </button>
      </div>

      <!-- Eingabe + Modus-Anzeige (nur sichtbar, wenn Einlagern oder Auslagern gewählt) -->
      <div id="input-area" class="w-full rounded-3xl bg-white dark:bg-primary-100 shadow-xl border border-gray-200/80 dark:border-primary-120 overflow-hidden hidden">
        <div class="p-6 sm:p-8">
          <div class="flex items-center justify-between gap-3 mb-3">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">EAN / Artikelnummer</span>
            <span id="mode-badge" class="text-xs font-bold uppercase tracking-wide px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-primary-200 text-gray-500 dark:text-gray-400">— wählen —</span>
          </div>
          <label for="tablet-code-input" class="sr-only">EAN oder Artikelnummer</label>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-stretch sm:gap-3">
            <input type="text" id="tablet-code-input" autocomplete="off" placeholder="Scannen oder eingeben, dann Enter" class="flex-1 min-w-0 px-5 py-5 text-xl md:text-2xl border-2 border-gray-200 dark:border-primary-120 rounded-2xl bg-gray-50 dark:bg-primary-50 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500 dark:focus:border-primary-400 transition-colors outline-none">
            <button type="button" id="inv-tablet-scan-btn" class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-4 text-base sm:text-lg font-semibold rounded-2xl border-2 border-primary-400 bg-primary-600 text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-primary-100 transition-colors active:scale-[0.98]" title="Barcode mit der Kamera scannen">
              <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              <span class="whitespace-nowrap">Kamera</span>
            </button>
          </div>
          <div id="tablet-message" class="mt-4 min-h-[4rem] rounded-xl px-4 py-3 border-2 border-gray-200 dark:border-primary-120 bg-gray-50 dark:bg-primary-50 flex flex-col justify-center hidden" role="status" aria-live="polite">
            <div id="tablet-message-inner" class="text-sm font-medium text-gray-400 dark:text-gray-500"></div>
          </div>
        </div>
      </div>
    </div>
  </main>

<!-- Vollbild-Scanner (Handy-Kamera) -->
<div id="inv-tablet-scan-overlay" class="hidden fixed inset-0 z-[100] flex flex-col bg-black/90 text-white" role="dialog" aria-modal="true" aria-labelledby="inv-tablet-scan-title">
  <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-white/10 shrink-0">
    <h2 id="inv-tablet-scan-title" class="text-base font-semibold">Barcode scannen</h2>
    <button type="button" id="inv-tablet-scan-close" class="px-4 py-2 text-sm font-medium rounded-lg bg-white/10 hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">Schließen</button>
  </div>
  <div id="inv-tablet-qr-reader" class="flex-1 min-h-[45vh] w-full max-w-lg mx-auto"></div>
  <p id="inv-tablet-scan-hint" class="px-4 py-3 text-center text-sm text-white/70 shrink-0">Halte den Strichcode im Rahmen. Nach dem Scan wird automatisch übernommen.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const baseUrl = (typeof window.baseUrl !== 'undefined' && window.baseUrl) ? window.baseUrl : '<?php echo BASE_URL; ?>';
  const apiUrl = baseUrl + 'inventory/api/consumables.php';

  const btnEinlagern = document.getElementById('btn-einlagern');
  const btnAuslagern = document.getElementById('btn-auslagern');
  const inputAreaWrapper = document.getElementById('input-area');
  const modeBadge = document.getElementById('mode-badge');
  const codeInput = document.getElementById('tablet-code-input');
  const messageEl = document.getElementById('tablet-message');
  const messageInner = document.getElementById('tablet-message-inner');
  const scanBtn = document.getElementById('inv-tablet-scan-btn');
  const scanOverlay = document.getElementById('inv-tablet-scan-overlay');
  const scanCloseBtn = document.getElementById('inv-tablet-scan-close');
  const scanReaderEl = document.getElementById('inv-tablet-qr-reader');

  let currentMode = null; // 'in' | 'out'
  let html5QrCodeInstance = null;
  let scanDecodeHandled = false;
  /** Native Kamera-Scan (BarcodeDetector): Cleanup beim Schließen/Erfolg */
  let nativeScanStop = null;

  function loadHtml5QrcodeScript() {
    if (typeof window.Html5Qrcode !== 'undefined') {
      return Promise.resolve();
    }
    return new Promise(function(resolve, reject) {
      var s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
      s.async = true;
      s.onload = function() { resolve(); };
      s.onerror = function() { reject(new Error('Scanner-Bibliothek konnte nicht geladen werden (Netzwerk).')); };
      document.head.appendChild(s);
    });
  }

  function stopTabletScanner() {
    scanDecodeHandled = false;
    document.body.style.overflow = '';
    if (nativeScanStop) {
      var ns = nativeScanStop;
      nativeScanStop = null;
      try { ns(); } catch (e) { /* ignore */ }
    }
    if (!html5QrCodeInstance) {
      if (scanOverlay) scanOverlay.classList.add('hidden');
      return;
    }
    var inst = html5QrCodeInstance;
    html5QrCodeInstance = null;
    inst.stop().then(function() {
      try { inst.clear(); } catch (e) { /* ignore */ }
    }).catch(function() {
      try { inst.clear(); } catch (e2) { /* ignore */ }
    }).finally(function() {
      if (scanOverlay) scanOverlay.classList.add('hidden');
    });
  }

  /**
   * Bevorzugt die Browser-API BarcodeDetector (Chrome/Android, teils Desktop):
   * erkennt EAN/Strichcodes deutlich zuverlässiger als nur ZXing im Canvas.
   */
  function startNativeBarcodeDetectorScan() {
    if (typeof window.BarcodeDetector === 'undefined') {
      return Promise.reject(new Error('NATIVE_UNSUPPORTED'));
    }
    var formats = ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'codabar', 'itf', 'qr_code'];
    var detector;
    try {
      detector = new BarcodeDetector({ formats: formats });
    } catch (e1) {
      try {
        detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'upc_a', 'upc_e', 'qr_code'] });
      } catch (e2) {
        return Promise.reject(new Error('NATIVE_UNSUPPORTED'));
      }
    }

    return navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1920 },
        height: { ideal: 1080 }
      },
      audio: false
    }).catch(function() {
      return navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      });
    }).catch(function() {
      return navigator.mediaDevices.getUserMedia({ video: true, audio: false });
    }).then(function(stream) {
      var video = document.createElement('video');
      video.setAttribute('playsinline', '');
      video.setAttribute('playsInline', '');
      video.setAttribute('autoplay', '');
      video.muted = true;
      video.className = 'w-full h-full max-h-[70vh] object-contain bg-black';
      video.srcObject = stream;

      scanReaderEl.innerHTML = '';
      scanReaderEl.appendChild(video);

      return video.play().then(function() { return { stream: stream, video: video, detector: detector }; });
    }).then(function(ctx) {
      var stream = ctx.stream;
      var video = ctx.video;
      var detector = ctx.detector;
      var canvas = document.createElement('canvas');
      var c2d = canvas.getContext('2d', { willReadFrequently: true });
      var timer = null;

      function cleanup() {
        if (timer !== null) {
          clearInterval(timer);
          timer = null;
        }
        stream.getTracks().forEach(function(t) { t.stop(); });
        if (scanReaderEl) scanReaderEl.innerHTML = '';
      }

      nativeScanStop = function() {
        cleanup();
      };

      timer = setInterval(function() {
        if (scanDecodeHandled) return;
        if (!video.videoWidth || !video.videoHeight) return;
        var vw = video.videoWidth;
        var vh = video.videoHeight;
        var maxDim = 1280;
        var scale = Math.min(1, maxDim / Math.max(vw, vh));
        canvas.width = Math.max(1, Math.floor(vw * scale));
        canvas.height = Math.max(1, Math.floor(vh * scale));
        try {
          c2d.drawImage(video, 0, 0, vw, vh, 0, 0, canvas.width, canvas.height);
        } catch (e) {
          return;
        }
        detector.detect(canvas).then(function(codes) {
          if (scanDecodeHandled || !codes || !codes.length) return;
          var raw = codes[0].rawValue;
          if (raw == null || String(raw).trim() === '') return;
          scanDecodeHandled = true;
          cleanup();
          nativeScanStop = null;
          document.body.style.overflow = '';
          if (scanOverlay) scanOverlay.classList.add('hidden');
          if (codeInput) codeInput.value = String(raw).trim();
          submitStock();
        }).catch(function() { /* einzelne Frames ohne Erkennung */ });
      }, 180);

      return Promise.resolve();
    });
  }

  function openTabletScanner() {
    if (!currentMode) {
      showMessage('Zuerst Einlagern oder Auslagern wählen', 'error');
      return;
    }
    if (!scanOverlay || !scanReaderEl) return;

    function beginScan() {
    if (nativeScanStop) {
      var ns0 = nativeScanStop;
      nativeScanStop = null;
      try { ns0(); } catch (e) { /* ignore */ }
    }
    scanReaderEl.innerHTML = '';
    scanOverlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    showMessage('', null);
    scanDecodeHandled = false;

    function startHtml5Fallback(cameraConfig) {
      return loadHtml5QrcodeScript().then(function() {
        var Html5Qrcode = window.Html5Qrcode;
        if (!Html5Qrcode) {
          throw new Error('Scanner nicht verfügbar.');
        }
        if (html5QrCodeInstance) {
          try { html5QrCodeInstance.clear(); } catch (e) { /* ignore */ }
          html5QrCodeInstance = null;
        }
        html5QrCodeInstance = new Html5Qrcode('inv-tablet-qr-reader');
        scanDecodeHandled = false;

        var config = {
          fps: 12,
          useBarCodeDetectorIfSupported: true,
          rememberLastUsedCamera: true,
          qrbox: function(viewfinderWidth, viewfinderHeight) {
            var w = Math.floor(viewfinderWidth * 0.96);
            var h = Math.min(260, Math.floor(viewfinderHeight * 0.45));
            return { width: w, height: Math.max(h, 120) };
          }
        };
        var F = window.Html5QrcodeSupportedFormats;
        if (F) {
          config.formatsToSupport = [
            F.EAN_13, F.EAN_8, F.CODE_128, F.CODE_39, F.CODE_93, F.CODABAR,
            F.UPC_A, F.UPC_E, F.ITF
          ].filter(function(x) { return typeof x !== 'undefined'; });
        }

        return html5QrCodeInstance.start(
          cameraConfig || { facingMode: 'environment' },
          config,
          function(decodedText) {
            if (scanDecodeHandled) return;
            var text = (decodedText || '').trim().replace(/\r?\n/g, '');
            if (!text) return;
            scanDecodeHandled = true;
            var inst = html5QrCodeInstance;
            html5QrCodeInstance = null;
            document.body.style.overflow = '';
            inst.stop().then(function() {
              try { inst.clear(); } catch (e) { /* ignore */ }
              if (scanOverlay) scanOverlay.classList.add('hidden');
              if (codeInput) codeInput.value = text;
              submitStock();
            }).catch(function() {
              if (scanOverlay) scanOverlay.classList.add('hidden');
              if (codeInput) codeInput.value = text;
              submitStock();
            });
          },
          function() { /* Scan läuft */ }
        );
      }).catch(function(err) {
        if (html5QrCodeInstance) {
          try { html5QrCodeInstance.clear(); } catch (e) { /* ignore */ }
          html5QrCodeInstance = null;
        }
        return Promise.reject(err);
      });
    }

    function tryHtml5WithCameraFallback() {
      return startHtml5Fallback({ facingMode: 'environment' }).catch(function(firstErr) {
        if (!window.Html5Qrcode || !window.Html5Qrcode.getCameras) {
          return Promise.reject(firstErr);
        }
        return window.Html5Qrcode.getCameras().then(function(devices) {
          if (!devices || !devices.length) {
            return Promise.reject(firstErr);
          }
          var back = null;
          for (var i = 0; i < devices.length; i++) {
            var label = (devices[i].label || '').toLowerCase();
            if (label.indexOf('back') !== -1 || label.indexOf('rear') !== -1 || label.indexOf('environment') !== -1) {
              back = devices[i].id;
              break;
            }
          }
          var id = back || devices[0].id;
          scanReaderEl.innerHTML = '';
          return startHtml5Fallback({ deviceId: { exact: id } });
        });
      });
    }

    startNativeBarcodeDetectorScan()
      .catch(function(err) {
        if (err && err.message === 'NATIVE_UNSUPPORTED') {
          return tryHtml5WithCameraFallback();
        }
        return Promise.reject(err);
      })
      .catch(function(err) {
        var msg = (err && err.name === 'NotAllowedError')
          ? 'Kamera-Zugriff verweigert. Bitte in den Browser-Einstellungen erlauben.'
          : ((err && err.name === 'NotFoundError')
            ? 'Keine Kamera gefunden.'
            : ((err && err.message) ? err.message : 'Kamera konnte nicht gestartet werden.'));
        stopTabletScanner();
        showMessage(msg, 'error');
      });
    }

    if (html5QrCodeInstance) {
      var prevInst = html5QrCodeInstance;
      html5QrCodeInstance = null;
      scanDecodeHandled = false;
      document.body.style.overflow = '';
      if (nativeScanStop) {
        var ns1 = nativeScanStop;
        nativeScanStop = null;
        try { ns1(); } catch (e) { /* ignore */ }
      }
      prevInst.stop().then(function() {
        try { prevInst.clear(); } catch (e) { /* ignore */ }
        beginScan();
      }).catch(function() {
        beginScan();
      });
      return;
    }
    beginScan();
  }

  function setMessageBox(type, html) {
    if (!messageEl || !messageInner) return;
    messageInner.innerHTML = html;
    if (type === null && !html) {
      messageEl.classList.add('hidden');
      return;
    }
    messageEl.classList.remove('hidden');
    messageEl.style.backgroundColor = '';
    messageEl.style.borderColor = '';
    messageEl.classList.remove('border-green-500', 'border-red-500');
    if (type === 'success') {
      messageEl.style.backgroundColor = '#dcfce7';
      messageEl.style.borderColor = '#22c55e';
      messageEl.classList.add('border-green-500');
    } else if (type === 'error') {
      messageEl.style.backgroundColor = '#fee2e2';
      messageEl.style.borderColor = '#ef4444';
      messageEl.classList.add('border-red-500');
    } else if (type === 'loading') {
      messageEl.style.backgroundColor = '#f3f4f6';
      messageEl.style.borderColor = '#d1d5db';
    } else {
      messageEl.style.backgroundColor = '';
      messageEl.style.borderColor = '';
    }
  }

  function showMessage(textOrData, type) {
    if (type === 'success' && textOrData && typeof textOrData === 'object' && 'bestand' in textOrData) {
      var bestand = textOrData.bestand != null ? String(textOrData.bestand) : '–';
      var lagerort = (textOrData.lagerort || '').trim();
      var mindest = textOrData.mindestbestand != null ? Number(textOrData.mindestbestand) : null;
      var unterMindest = !!textOrData.unter_mindestbestand;
      var bestellt = !!textOrData.bestellt;
      var bestellnummer = (textOrData.bestellnummer || '').trim();
      var orderError = (textOrData.order_error || '').trim();
      var neuAngelegt = !!textOrData.neu_angelegt;
      var quelle = (textOrData.produkt_quelle || '').trim();
      var prodName = (textOrData.produkt_name || '').trim();
      var html = '<div class="flex items-center gap-2" style="color:#166534"><span class="text-2xl">✓</span><span><strong>Bestand: ' + bestand + '</strong></span></div>';
      if (neuAngelegt) {
        var quelleLabel = quelle === 'openfoodfacts' ? 'Open Food Facts' : (quelle === 'openbeautyfacts' ? 'Open Beauty Facts' : (quelle === 'openproductsfacts' ? 'Open Products Facts (IT/allgemein)' : (quelle === 'upcitemdb' ? 'UPCitemdb (IT/Hardware)' : (quelle === 'fallback' ? 'keine externen Daten' : (quelle || 'Datenbank')))));
        html += '<div class="mt-2.5 rounded-lg px-3 py-2 text-sm font-semibold" style="background:#dbeafe;color:#1e40af;border:1px solid #3b82f6">Neu angelegt' + (prodName ? ': ' + escapeHtml(prodName) : '') + '<span class="block mt-1 text-xs font-normal opacity-90">Quelle: ' + escapeHtml(quelleLabel) + '</span></div>';
      }
      if (lagerort) {
        html += '<div class="flex items-center gap-2 mt-1.5 text-base" style="color:#166534"><span aria-hidden="true">📍</span><span>' + escapeHtml(lagerort) + '</span></div>';
      }
      if (mindest !== null && currentMode === 'out') {
        html += '<div class="flex items-center gap-2 mt-1.5 text-base" style="color:#166534"><span aria-hidden="true">📋</span><span>Mindest: ' + mindest + '</span></div>';
        if (unterMindest && bestellt && bestellnummer) {
          html += '<div class="flex items-center gap-2 mt-1 text-sm font-semibold" style="color:#166534">✓ Bestellung ausgelöst: ' + escapeHtml(bestellnummer) + '</div>';
        } else if (unterMindest && bestellt) {
          html += '<div class="flex items-center gap-2 mt-1 text-sm font-semibold" style="color:#166534">✓ Bestellung ausgelöst</div>';
        } else if (unterMindest) {
          html += '<div class="flex items-center gap-2 mt-1 text-sm font-semibold" style="color:#b45309">⚠ Bitte bestellen</div>';
        }
      }
      if (orderError) {
        html += '<div class="flex items-center gap-2 mt-1.5 text-xs" style="color:#b91c1c">Fehler Bestellung: ' + escapeHtml(orderError) + '</div>';
      }
      setMessageBox('success', html);
      return;
    }
    if (typeof textOrData === 'string' && textOrData) {
      if (type === 'error') {
        setMessageBox('error', '<div class="flex items-center gap-2" style="color:#b91c1c"><span class="text-xl">✕</span><span>' + escapeHtml(textOrData) + '</span></div>');
      } else if (type === 'loading') {
        setMessageBox('loading', '<div class="flex items-center gap-2 text-gray-500"><span class="inline-block w-5 h-5 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></span><span>Sende…</span></div>');
      } else {
        setMessageBox(null, '<span class="text-gray-400">' + escapeHtml(textOrData) + '</span>');
      }
      return;
    }
    setMessageBox(null, '');
  }

  function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  function showInput(mode) {
    currentMode = mode;
    if (inputAreaWrapper) inputAreaWrapper.classList.remove('hidden');
    showMessage('', null);
    if (modeBadge) {
      if (mode === 'in') {
        modeBadge.textContent = 'Einlagern';
        modeBadge.className = 'text-xs font-bold uppercase tracking-wide px-3 py-1.5 rounded-lg bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300';
      } else {
        modeBadge.textContent = 'Auslagern';
        modeBadge.className = 'text-xs font-bold uppercase tracking-wide px-3 py-1.5 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300';
      }
    }
    if (codeInput) {
      codeInput.value = '';
      codeInput.focus();
    }
  }

  function submitStock() {
    if (!currentMode) {
      showMessage('Zuerst Einlagern oder Auslagern wählen', 'error');
      return;
    }
    const delta = currentMode === 'in' ? 1 : -1;
    const code = (codeInput.value || '').trim().replace(/\r?\n/g, '');
    if (!code) {
      showMessage('EAN/Artikelnummer eingeben', 'error');
      return;
    }

    showMessage('', 'loading');

    var url = apiUrl;
    if (url.indexOf('://') === -1 && url.indexOf('/') !== 0) {
      url = (window.location.origin + window.location.pathname).replace(/\/[^/]*$/, '/') + url;
    }

    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'adjust_stock', code: code, delta: delta })
    })
      .then(function(r) {
        return r.text().then(function(text) {
          try {
            return { ok: r.ok, status: r.status, data: JSON.parse(text) };
          } catch (e) {
            return { ok: false, status: r.status, data: { success: false, error: r.ok ? 'Ungültige Antwort vom Server.' : (text || 'Fehler ' + r.status) } };
          }
        });
      })
      .then(function(result) {
        var data = result.data;
        if (result.ok && data && data.success) {
          var lagerort = (currentMode === 'in' && data.lagerort) ? data.lagerort.trim() : '';
          showMessage({
            bestand: data.lagerbestand,
            lagerort: lagerort,
            mindestbestand: data.mindestbestand,
            unter_mindestbestand: data.unter_mindestbestand,
            bestellt: data.bestellt,
            bestellnummer: data.bestellnummer,
            order_error: data.order_error,
            neu_angelegt: !!data.neu_angelegt,
            produkt_quelle: (data.produkt_quelle || '').trim(),
            produkt_name: (data.bezeichnung || '').trim()
          }, 'success');
          codeInput.value = '';
          codeInput.focus();
        } else {
          showMessage(data && data.error ? data.error : ('Fehler ' + result.status), 'error');
        }
      })
      .catch(function(err) {
        showMessage('Netzwerkfehler', 'error');
      });
  }

  if (btnEinlagern) btnEinlagern.addEventListener('click', function() { showInput('in'); });
  if (btnAuslagern) btnAuslagern.addEventListener('click', function() { showInput('out'); });

  if (codeInput) {
    codeInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitStock();
      }
    });
  }

  if (scanBtn) scanBtn.addEventListener('click', function() { openTabletScanner(); });
  if (scanCloseBtn) scanCloseBtn.addEventListener('click', function() { stopTabletScanner(); });
});
</script>
</body>
</html>
