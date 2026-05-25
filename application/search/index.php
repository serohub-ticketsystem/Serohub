<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/assets/config.php';
requireLogin();

// Benutzerdaten abrufen
$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT id, rolle, company_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $user['rolle'];
    $userCompanyId = $user['company_id'];
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$searchScope = isset($_GET['scope']) ? (string) $_GET['scope'] : '';

include dirname(__DIR__) . '/assets/frontend/head.php';
include dirname(__DIR__) . '/assets/frontend/nav.php';
include dirname(__DIR__) . '/assets/frontend/sidebar.php';
include dirname(__DIR__) . '/assets/frontend/toast.php';
?>

<div id="main-content" class="relative w-full bg-gray-50 dark:bg-primary-50 lg:ms-64 pt-12 lg:pt-0 overflow-hidden">
  <main class="pt-4 pr-4 pb-4 pl-1 flex flex-col overflow-hidden">
    <nav class="mb-4 flex flex-shrink-0" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        <li class="inline-flex items-center">
          <a href="<?php echo htmlspecialchars(BASE_URL); ?>dashboard/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-700 dark:text-gray-400 dark:hover:text-white">
            <svg class="me-2.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M11.3 3.3a1 1 0 0 1 1.4 0l6 6 2 2a1 1 0 0 1-1.4 1.4l-.3-.3V19a2 2 0 0 1-2 2h-3a1 1 0 0 1-1-1v-3h-2v3c0 .6-.4 1-1 1H7a2 2 0 0 1-2-2v-6.6l-.3.3a1 1 0 0 1-1.4-1.4l2-2 6-6Z" clip-rule="evenodd" />
            </svg>
            Startseite
          </a>
        </li>
        <li aria-current="page">
          <div class="flex items-center">
            <svg class="mx-1 h-4 w-4 text-gray-400 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
            </svg>
            <span class="ms-1 text-sm font-medium text-gray-500 dark:text-gray-400 md:ms-2">Suche</span>
          </div>
        </li>
      </ol>
    </nav>

    <div class="mb-4">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Suchergebnisse</h1>
      
      <!-- Suchformular -->
      <form method="GET" action="<?php echo BASE_URL; ?>search/" class="mb-4">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
            </svg>
          </div>
          <input type="text" 
                 name="q" 
                 id="search-input"
                 value="<?php echo htmlspecialchars($searchQuery); ?>"
                 placeholder="System durchsuchen…"
                 class="block w-full p-3 pl-10 pr-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-primary-820 focus:border-primary-820 dark:bg-primary-300 dark:border-primary-320 dark:placeholder-primary-210 dark:text-primary-200 dark:focus:ring-primary-820 dark:focus:border-primary-820"
                 autofocus>
          <button type="submit" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700 dark:text-primary-210 dark:hover:text-primary-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
          </button>
        </div>
      </form>
    </div>

    <!-- Suchergebnisse -->
    <div id="search-results-container" class="flex-1 overflow-y-auto">
      <div id="search-results-loading" class="hidden text-center py-8">
        <svg class="animate-spin h-8 w-8 mx-auto mb-3 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-sm text-gray-500 dark:text-gray-400">Suche läuft…</p>
      </div>
      
      <div id="search-results-empty" class="hidden text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <p class="text-sm font-medium text-gray-900 dark:text-white mb-1">Keine Ergebnisse gefunden</p>
        <p class="text-sm text-gray-500 dark:text-gray-400">Versuchen Sie es mit anderen Suchbegriffen.</p>
      </div>

      <div id="search-results-list" class="space-y-2"></div>
    </div>
  </main>
</div>

<script>
(function() {
    const searchInput = document.getElementById('search-input');
    const resultsContainer = document.getElementById('search-results-container');
    const resultsList = document.getElementById('search-results-list');
    const loadingEl = document.getElementById('search-results-loading');
    const emptyEl = document.getElementById('search-results-empty');
    const baseUrl = '<?php echo BASE_URL; ?>';
    const searchQuery = <?php echo json_encode($searchQuery, JSON_UNESCAPED_UNICODE); ?>;
    const searchScope = '<?php echo addslashes($searchScope); ?>';
    
    /* Pfad-d wie assets/frontend/sidebar_nav_content.php; Benutzer wie admin/index Benutzerverwaltung */
    const typeConfig = {
        ticket: { label: 'Tickets', icon: 'M10 3v4a1 1 0 0 1-1 1H5m4 8h6m-6-4h6m4-8v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z', color: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' },
        aufgabe: { label: 'Aufgabe', icon: 'M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', color: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
        geraet: { label: 'Gerät', icon: 'M9 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v1M9 12H4m8 8V9h8v11h-8Zm0 0H9m8-4a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z', color: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' },
        bestellung: { label: 'Bestellung', icon: 'M4 13h3.439a.991.991 0 0 1 .908.6 3.978 3.978 0 0 0 7.306 0 .99.99 0 0 1 .908-.6H20M4 13v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 13l2-9h12l2 9M9 7h6m-7 3h8', color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
        firma: { label: 'Firma', icon: 'M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z', color: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200' },
        kunde: { label: 'Kunde', icon: 'M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z', color: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200' },
        artikel: { label: 'Wissensdatenbank', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5c-1.747 0-3.332.477-4.5 1.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', color: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200' },
        projekt: { label: 'Projekt', icon: 'M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z', color: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
        benutzer: { label: 'Benutzer', icon: 'M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z', color: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200' },
        inventar: { label: 'Lager', icon: 'M9.143 4H4.857A.857.857 0 0 0 4 4.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 10 9.143V4.857A.857.857 0 0 0 9.143 4Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286A.857.857 0 0 0 20 9.143V4.857A.857.857 0 0 0 19.143 4Zm-10 10H4.857a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286A.857.857 0 0 0 9.143 14Zm10 0h-4.286a.857.857 0 0 0-.857.857v4.286c0 .473.384.857.857.857h4.286a.857.857 0 0 0 .857-.857v-4.286a.857.857 0 0 0-.857-.857Z', color: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' }
    };
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function globalSearchNormalizeWs(s) {
        return String(s).replace(/[\u200B-\u200D\uFEFF]/g, '').replace(/\s+/g, ' ').trim();
    }
    function globalSearchBuildCompactMap(text) {
        const lower = String(text).toLocaleLowerCase('de-DE');
        let compact = '';
        const map = [];
        for (let i = 0; i < lower.length; i++) {
            const c = lower.charAt(i);
            if (/\s/.test(c)) continue;
            compact += c;
            map.push(i);
        }
        return { compact, map };
    }
    function globalSearchFuzzyMatchRange(text, rawQueryTrimmed) {
        const n = globalSearchNormalizeWs(String(rawQueryTrimmed).toLocaleLowerCase('de-DE'));
        const qf = n.replace(/\s/g, '');
        if (qf.length < 3) return null;
        if (qf.indexOf('%') !== -1 || qf.indexOf('_') !== -1) return null;
        const bm = globalSearchBuildCompactMap(text);
        const hayNorm = bm.compact;
        const map = bm.map;
        const hayLen = hayNorm.length;
        const len = qf.length;
        if (hayLen === 0) return null;
        let j;
        let sub;
        const max1 = Math.min(25, len);
        for (let i = 0; i < max1; i++) {
            const before = qf.substring(0, i);
            const after = qf.substring(i + 1);
            const bl = before.length;
            const al = after.length;
            const total = bl + 1 + al;
            if (hayLen < total) continue;
            const maxStart = hayLen - total;
            for (j = 0; j <= maxStart; j++) {
                sub = hayNorm.substring(j, j + total);
                if (sub.substring(0, bl) !== before) continue;
                if (sub.substring(bl + 1, bl + 1 + al) !== after) continue;
                return { start: map[j], end: map[j + total - 1] + 1 };
            }
        }
        const numPairs = Math.min(Math.pow(2, Math.min(4, Math.max(0, Math.floor((len - 2) / 2)))), len * (len - 1) / 2, 35);
        let count = 0;
        for (let i = 0; i < len - 1 && count < numPairs; i++) {
            for (let jj = i + 1; jj < len && count < numPairs; jj++, count++) {
                const before = qf.substring(0, i);
                const mid = qf.substring(i + 1, jj);
                const after = qf.substring(jj + 1);
                const bl = before.length;
                const ml = mid.length;
                const al = after.length;
                const total = bl + 1 + ml + 1 + al;
                if (hayLen < total) continue;
                const maxStart = hayLen - total;
                for (j = 0; j <= maxStart; j++) {
                    sub = hayNorm.substring(j, j + total);
                    if (sub.substring(0, bl) !== before) continue;
                    if (sub.substring(bl + 1, bl + 1 + ml) !== mid) continue;
                    if (sub.substring(bl + 1 + ml + 1, bl + 1 + ml + 1 + al) !== after) continue;
                    return { start: map[j], end: map[j + total - 1] + 1 };
                }
            }
        }
        if (len >= 5) {
            const numTriples = Math.min(18, len * (len - 1) * (len - 2) / 6);
            let count3 = 0;
            for (let i = 0; i < len - 2 && count3 < numTriples; i++) {
                for (let jj2 = i + 1; jj2 < len - 1 && count3 < numTriples; jj2++) {
                    for (let k = jj2 + 1; k < len && count3 < numTriples; k++, count3++) {
                        const p1 = qf.substring(0, i);
                        const p2 = qf.substring(i + 1, jj2);
                        const p3 = qf.substring(jj2 + 1, k);
                        const p4 = qf.substring(k + 1);
                        const l1 = p1.length;
                        const l2 = p2.length;
                        const l3 = p3.length;
                        const l4 = p4.length;
                        const total = l1 + 1 + l2 + 1 + l3 + 1 + l4;
                        if (hayLen < total) continue;
                        const maxStart = hayLen - total;
                        for (j = 0; j <= maxStart; j++) {
                            sub = hayNorm.substring(j, j + total);
                            if (sub.substring(0, l1) !== p1) continue;
                            if (sub.substring(l1 + 1, l1 + 1 + l2) !== p2) continue;
                            if (sub.substring(l1 + 1 + l2 + 1, l1 + 1 + l2 + 1 + l3) !== p3) continue;
                            if (sub.substring(l1 + 1 + l2 + 1 + l3 + 1) !== p4) continue;
                            return { start: map[j], end: map[j + total - 1] + 1 };
                        }
                    }
                }
            }
        }
        return null;
    }
    function highlightExactQuery(text, rawQuery) {
        if (!text) return '';
        const q = (rawQuery || '').trim();
        if (!q) return escapeHtml(text);
        const lt = text.toLocaleLowerCase('de-DE');
        const lq = q.toLocaleLowerCase('de-DE');
        let out = '';
        let pos = 0;
        let idx;
        while ((idx = lt.indexOf(lq, pos)) !== -1) {
            out += escapeHtml(text.substring(pos, idx));
            out += '<strong class="font-bold text-primary-600 dark:text-primary-400">' + escapeHtml(text.substring(idx, idx + q.length)) + '</strong>';
            pos = idx + q.length;
        }
        out += escapeHtml(text.substring(pos));
        return out;
    }
    function highlightGlobalSearchQuery(text, rawQuery) {
        if (!text) return '';
        const q = (rawQuery || '').trim();
        if (!q) return escapeHtml(text);
        const lt = text.toLocaleLowerCase('de-DE');
        const lq = q.toLocaleLowerCase('de-DE');
        if (lq !== '' && lt.indexOf(lq) !== -1) {
            return highlightExactQuery(text, rawQuery);
        }
        const fr = globalSearchFuzzyMatchRange(text, q);
        if (fr) {
            return escapeHtml(text.substring(0, fr.start)) + '<strong class="font-bold text-primary-600 dark:text-primary-400">' + escapeHtml(text.substring(fr.start, fr.end)) + '</strong>' + escapeHtml(text.substring(fr.end));
        }
        return escapeHtml(text);
    }

    /** Wie tickets/index.php getStatusBadgeClass */
    function ticketStatusSearchBadgeClasses(status) {
        const map = {
            'Neu': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'In Bearbeitung': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'Warteschlange': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            'Geplant': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'Bestellung offen': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            'Geschlossen': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            'Archiv': 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
        };
        const k = (status || '').trim();
        return map[k] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }

    /** Wie todos/index.php – Aufgaben-Status */
    function todoStatusSearchBadgeClasses(raw) {
        const map = {
            offen: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            in_bearbeitung: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            erledigt: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
        };
        const k = (raw || '').trim();
        return map[k] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }

    function formatTodoSearchDue(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function buildTodoSearchMetaHtml(r, hq) {
        const parts = [];
        const sep = '<span class="text-gray-400 dark:text-gray-500">|</span>';
        const pid = r.todo_project_id;
        const pnum = (r.todo_project_nummer != null && r.todo_project_nummer !== '') ? String(r.todo_project_nummer).trim() : '';
        if (pid && pnum) {
            parts.push(`<span class="whitespace-nowrap inline-flex items-center gap-1"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400 flex-shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/></svg><span class="text-primary-600 dark:text-primary-400">#${escapeHtml(pnum)}</span></span>`);
        }
        const hasDesc = r.todo_has_description === true || r.todo_has_description === 1;
        const hasTicket = r.todo_ticket_nummer && String(r.todo_ticket_nummer).trim();
        const due = formatTodoSearchDue(r.todo_faellig_am);
        const comp = (r.todo_company_name && String(r.todo_company_name).trim()) || '';
        const att = parseInt(r.todo_attachment_count, 10) || 0;
        if (hasDesc) {
            if (parts.length) parts.push(sep);
            parts.push('<span class="whitespace-nowrap inline-flex items-center gap-1"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg><span>Beschreibung</span></span>');
        }
        if (hasTicket) {
            if (parts.length) parts.push(sep);
            parts.push(`<span class="whitespace-nowrap inline-flex items-center gap-1"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/></svg><span class="text-primary-600 dark:text-primary-400">#${highlightGlobalSearchQuery(String(r.todo_ticket_nummer).trim(), hq)}</span></span>`);
        }
        if (due) {
            if (parts.length) parts.push(sep);
            parts.push(`<span class="whitespace-nowrap inline-flex items-center gap-1"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z"/></svg>${escapeHtml(due)}</span>`);
        }
        if (comp) {
            if (parts.length) parts.push(sep);
            parts.push(`<span class="inline-flex items-center gap-1 min-w-0 max-w-full"><svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400 flex-shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12c.263 0 .524-.06.767-.175a2 2 0 0 0 .65-.491c.186-.21.333-.46.433-.734.1-.274.15-.568.15-.864a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 12 9.736a2.4 2.4 0 0 0 .586 1.591c.375.422.884.659 1.414.659.53 0 1.04-.237 1.414-.659A2.4 2.4 0 0 0 16 9.736c0 .295.052.588.152.861s.248.521.434.73a2 2 0 0 0 .649.488 1.809 1.809 0 0 0 1.53 0 2.03 2.03 0 0 0 .65-.488c.185-.209.332-.457.433-.73.1-.273.152-.566.152-.861 0-.974-1.108-3.85-1.618-5.121A.983.983 0 0 0 17.466 4H6.456a.986.986 0 0 0-.93.645C5.045 5.962 4 8.905 4 9.736c.023.59.241 1.148.611 1.567.37.418.865.667 1.389.697Zm0 0c.328 0 .651-.091.94-.266A2.1 2.1 0 0 0 7.66 11h.681a2.1 2.1 0 0 0 .718.734c.29.175.613.266.942.266.328 0 .651-.091.94-.266.29-.174.537-.427.719-.734h.681a2.1 2.1 0 0 0 .719.734c.289.175.612.266.94.266.329 0 .652-.091.942-.266.29-.174.536-.427.718-.734h.681c.183.307.43.56.719.734.29.174.613.266.941.266a1.819 1.819 0 0 0 1.06-.351M6 12a1.766 1.766 0 0 1-1.163-.476M5 12v7a1 1 0 0 0 1 1h2v-5h3v5h7a1 1 0 0 0 1-1v-7m-5 3v2h2v-2h-2Z"/></svg><span class="truncate">${highlightGlobalSearchQuery(comp, hq)}</span></span>`);
        }
        if (att > 0) {
            if (parts.length) parts.push(sep);
            parts.push(`<span class="whitespace-nowrap inline-flex items-center gap-1 text-gray-500 dark:text-gray-400"><svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8v8a5 5 0 1 0 10 0V6.5a3.5 3.5 0 1 0-7 0V15a2 2 0 0 0 4 0V8"/></svg><span>${att}</span></span>`);
        }
        if (!parts.length) return '';
        return `<div class="flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400 mt-1 flex-wrap leading-snug">${parts.join('')}</div>`;
    }
    
    function renderResult(result) {
        const config = typeConfig[result.type] || { label: result.type_label || result.type, icon: '', color: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' };
        const hq = (searchQuery || '').trim();
        let subtitleBlock = '';
        if (result.type === 'ticket') {
            const l1 = ((result.ticket_subtitle_line1 || '').trim());
            const l2 = ((result.ticket_subtitle_line2 || '').trim());
            if (l1) subtitleBlock += `<p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1 break-words leading-snug">${highlightGlobalSearchQuery(l1, hq)}</p>`;
            if (l2) subtitleBlock += `<p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5 break-words leading-snug">${highlightGlobalSearchQuery(l2, hq)}</p>`;
        } else if (result.type === 'aufgabe') {
            subtitleBlock = buildTodoSearchMetaHtml(result, hq);
        } else if (result.subtitle) {
            subtitleBlock = `<p class="text-sm text-gray-500 dark:text-gray-400 mt-1 break-words leading-relaxed">${highlightGlobalSearchQuery(result.subtitle, hq)}</p>`;
        }
        let rightMeta = '';
        if (result.type === 'ticket') {
            const st = ((result.ticket_status || '').trim()) || '—';
            const num = (result.ticket_nummer || '').trim();
            const af = ((result.ticket_anforderer || '').trim());
            const stCls = ticketStatusSearchBadgeClasses(st === '—' ? '' : st);
            rightMeta = `
                            <div class="shrink-0 flex flex-col items-end gap-0.5 text-right max-w-[10rem]">
                                <span class="inline-flex items-center max-w-full truncate px-2 py-1 text-xs font-semibold rounded-full ${stCls}">${highlightGlobalSearchQuery(st, hq)}</span>
                                ${num ? `<span class="text-[10px] text-gray-400 dark:text-gray-500 font-mono tabular-nums leading-tight opacity-90">${highlightGlobalSearchQuery(num, hq)}</span>` : ''}
                                ${af ? `<span class="text-[10px] text-gray-400 dark:text-gray-500 leading-tight line-clamp-2 max-w-full text-right" title="${escapeHtml(af)}">${highlightGlobalSearchQuery(af, hq)}</span>` : ''}
                            </div>`;
        } else if (result.type === 'aufgabe') {
            const tsRaw = (result.todo_status || '').trim() || 'offen';
            const tsLab = (result.todo_status_label || '').trim() || '—';
            const todoStCls = todoStatusSearchBadgeClasses(tsRaw);
            rightMeta = `<span class="shrink-0 inline-flex items-center max-w-full truncate px-2 py-1 text-xs font-semibold rounded-full ${todoStCls}">${highlightGlobalSearchQuery(tsLab, hq)}</span>`;
        } else {
            rightMeta = `<span class="shrink-0 text-xs font-medium px-2 py-1 rounded ${config.color}">${escapeHtml(config.label)}</span>`;
        }
        return `
            <a href="${escapeHtml(result.url)}" class="block bg-white dark:bg-primary-100 border border-gray-200 dark:border-primary-120 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-primary-140 transition-colors">
                <div class="flex items-start gap-4">
                    <div class="shrink-0 w-10 h-10 rounded-lg ${config.color} flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${config.icon}"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-base font-medium text-gray-900 dark:text-white break-words">${highlightGlobalSearchQuery(result.title || '', hq)}</h3>
                            ${rightMeta}
                        </div>
                        ${subtitleBlock}
                    </div>
                </div>
            </a>
        `;
    }
    
    function showLoading() {
        loadingEl.classList.remove('hidden');
        emptyEl.classList.add('hidden');
        resultsList.innerHTML = '';
    }
    
    function showEmpty() {
        loadingEl.classList.add('hidden');
        emptyEl.classList.remove('hidden');
        resultsList.innerHTML = '';
    }
    
    function showResults(results) {
        loadingEl.classList.add('hidden');
        emptyEl.classList.add('hidden');
        
        if (results.length === 0) {
            showEmpty();
            return;
        }
        
        resultsList.innerHTML = results.map(renderResult).join('');
    }
    
    function performSearch() {
        if (!searchQuery || searchQuery.length < 2) {
            showEmpty();
            return;
        }
        
        showLoading();
        
        let url = baseUrl + 'search/api/search.php?q=' + encodeURIComponent(searchQuery) + '&limit=50';
        if (searchScope) {
            url += '&search_scope=' + encodeURIComponent(searchScope);
        }
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.results && data.results.length > 0) {
                    showResults(data.results);
                } else {
                    showEmpty();
                }
            })
            .catch(error => {
                console.error('Fehler bei der Suche:', error);
                showEmpty();
            });
    }
    
    // Suche ausführen, wenn Query vorhanden
    if (searchQuery) {
        performSearch();
    } else {
        showEmpty();
    }
    
    // Enter-Taste im Suchfeld - Formular absenden
    if (searchInput) {
        const form = searchInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query.length >= 2) {
                    let url = baseUrl + 'search/?q=' + encodeURIComponent(query);
                    if (searchScope) {
                        url += '&scope=' + encodeURIComponent(searchScope);
                    }
                    window.location.href = url;
                }
            });
        }
    }
})();
</script>

<?php include dirname(__DIR__) . '/assets/frontend/footer.php'; ?>
