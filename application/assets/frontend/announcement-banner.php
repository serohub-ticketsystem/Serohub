<!-- Ankündigungs-Banner -->
<div id="announcement-banner" class="hidden fixed z-50 w-[calc(100%-2rem)] max-w-7xl left-1/2 -translate-x-1/2 top-24" role="alert">
    <div class="p-4 mb-4 text-sm rounded-lg bg-primary-100 dark:bg-blue-500 text-primary-800 dark:text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="flex-1">
                <span class="font-medium" id="announcement-creator">Serohub</span>
                <span id="announcement-message" class="ml-2"></span>
                <a id="announcement-link" href="#" class="hidden ml-2 font-medium underline hover:no-underline">
                    <span id="announcement-link-text"></span>
                </a>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button data-dismiss-target="#announcement-banner" type="button" class="hidden md:inline-flex justify-center text-sm w-6 h-6 items-center text-primary-800 hover:bg-primary-200 rounded-sm dark:text-primary-200 dark:hover:bg-primary-800">
                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6"/></svg>
                    <span class="sr-only">Banner schließen</span>
                </button>
                <button type="button" class="md:hidden text-xs font-medium text-primary-800 dark:text-primary-200 hover:underline" onclick="dismissAnnouncementBanner();">
                    Schließen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Banner laden beim Seitenaufbau
document.addEventListener('DOMContentLoaded', function() {
    loadAnnouncementBanner();
});

async function loadAnnouncementBanner() {
    const banner = document.getElementById('announcement-banner');
    if (!banner) return;
    
    try {
        const baseUrl = '<?php echo BASE_URL; ?>';
        const response = await fetch(baseUrl + 'admin/api/announcements.php?public=true');
        const data = await response.json();
        
        if (data.success && data.announcement) {
            const announcement = data.announcement;
            
            // Prüfe ob dieses spezifische Banner bereits geschlossen wurde (basierend auf ID)
            const dismissedBanners = JSON.parse(localStorage.getItem('dismissed_announcement_banners') || '[]');
            if (dismissedBanners.includes(announcement.id)) {
                banner.classList.add('hidden');
                return; // Dieses Banner wurde bereits geschlossen
            }
            
            const messageEl = document.getElementById('announcement-message');
            const linkEl = document.getElementById('announcement-link');
            const linkTextEl = document.getElementById('announcement-link-text');
            const creatorEl = document.getElementById('announcement-creator');
            
            if (messageEl) {
                messageEl.textContent = announcement.nachricht;
            }
            
            // Erstellernamen anzeigen
            if (creatorEl && announcement.ersteller_name) {
                creatorEl.textContent = announcement.ersteller_name;
            }
            
            // Link anzeigen falls vorhanden
            if (announcement.link_text && announcement.link_url) {
                if (linkEl) {
                    linkEl.href = announcement.link_url;
                    linkEl.classList.remove('hidden');
                }
                if (linkTextEl) {
                    linkTextEl.textContent = announcement.link_text;
                }
            } else {
                if (linkEl) {
                    linkEl.classList.add('hidden');
                }
            }
            
            // Banner-ID als data-Attribut speichern für späteres Schließen
            banner.setAttribute('data-announcement-id', announcement.id);
            
            // Banner anzeigen
            banner.classList.remove('hidden');
        } else {
            banner.classList.add('hidden');
        }
    } catch (error) {
        console.error('Fehler beim Laden des Banners:', error);
        banner.classList.add('hidden');
    }
}

// Funktion zum Schließen des Banners
function dismissAnnouncementBanner() {
    const banner = document.getElementById('announcement-banner');
    if (banner) {
        const announcementId = banner.getAttribute('data-announcement-id');
        if (announcementId) {
            // Speichere die ID dieses spezifischen Banners in einem Array
            const dismissedBanners = JSON.parse(localStorage.getItem('dismissed_announcement_banners') || '[]');
            if (!dismissedBanners.includes(parseInt(announcementId))) {
                dismissedBanners.push(parseInt(announcementId));
                localStorage.setItem('dismissed_announcement_banners', JSON.stringify(dismissedBanners));
            }
        }
        banner.classList.add('hidden');
    }
}

// Banner-Schließen-Event
document.addEventListener('click', function(e) {
    const target = e.target.closest('[data-dismiss-target="#announcement-banner"]');
    if (target) {
        dismissAnnouncementBanner();
    }
});
</script>
