/**
 * Sidebar / Mobile-Menü: Keine URL-Vorschau in der Browser-Statusleiste beim Hover.
 * href wird nur während Maus-Hover entfernt; Klick, Mittelklick und Tastatur bleiben nutzbar.
 */
document.addEventListener('DOMContentLoaded', function () {
    var containers = [];
    var sidebar = document.getElementById('sidebar');
    var menuSheet = document.querySelector('.app-mobile-menu-sheet');
    if (sidebar) containers.push(sidebar);
    if (menuSheet) containers.push(menuSheet);
    if (!containers.length) return;

    function stashHref(anchor) {
        if (!anchor || anchor.dataset.svNavHref !== undefined) return;
        var href = anchor.getAttribute('href');
        if (!href || href.charAt(0) === '#') return;
        anchor.dataset.svNavHref = href;
        anchor.removeAttribute('href');
    }

    function unstashHref(anchor) {
        if (!anchor || anchor.dataset.svNavHref === undefined) return;
        anchor.setAttribute('href', anchor.dataset.svNavHref);
        delete anchor.dataset.svNavHref;
    }

    function unstashAll(container) {
        container.querySelectorAll('a[data-sv-nav-href]').forEach(unstashHref);
    }

    function navigateFromStashed(anchor, e) {
        var href = anchor.getAttribute('href') || anchor.dataset.svNavHref;
        if (!href || href.charAt(0) === '#') return false;
        if (anchor.getAttribute('href')) return false;
        e.preventDefault();
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) {
            window.open(href, '_blank', 'noopener');
        } else {
            window.location.assign(href);
        }
        return true;
    }

    containers.forEach(function (container) {
        container.addEventListener('mouseover', function (e) {
            var a = e.target.closest('a');
            if (a && container.contains(a)) stashHref(a);
        });

        container.addEventListener('mouseout', function (e) {
            var a = e.target.closest('a[data-sv-nav-href]');
            if (!a || !container.contains(a)) return;
            if (e.relatedTarget && a.contains(e.relatedTarget)) return;
            unstashHref(a);
        });

        container.addEventListener('mouseleave', function () {
            unstashAll(container);
        });

        container.addEventListener('click', function (e) {
            var a = e.target.closest('a');
            if (!a || !container.contains(a)) return;
            navigateFromStashed(a, e);
        });

        container.addEventListener('auxclick', function (e) {
            if (e.button !== 1) return;
            var a = e.target.closest('a');
            if (!a || !container.contains(a)) return;
            navigateFromStashed(a, e);
        });
    });
});
