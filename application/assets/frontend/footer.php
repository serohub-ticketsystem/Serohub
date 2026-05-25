</div>

<?php
if (file_exists(__DIR__ . '/mobile_app_footer.php')) {
    include __DIR__ . '/mobile_app_footer.php';
}
$footerUserId = (int) ($_SESSION['user_id'] ?? 0);
?>

<!-- <script src="../assets/js/app.bundle.js" defer></script> -->
<script src="../assets/js/plz-ort.js" defer></script>
<?php if ($footerUserId > 0): ?>
<script>
(function() {
  try {
    var isMobile = window.matchMedia && window.matchMedia('(max-width: 1023px)').matches;
    if (!isMobile) return;
    var path = window.location.pathname || '/';
    var excluded = [
      '/login/',
      '/logout.php',
      '/settings/',
      '/settings/api/',
      '/admin/',
      '/api/'
    ];
    for (var i = 0; i < excluded.length; i++) {
      if (path.indexOf(excluded[i]) === 0) return;
    }
    var cookieName = 'mobile_last_path_user_<?php echo (int) $footerUserId; ?>';
    var cookieValue = encodeURIComponent(path + (window.location.search || ''));
    document.cookie = cookieName + '=' + cookieValue + '; path=/; max-age=' + (60 * 60 * 24 * 30) + '; samesite=Lax';
  } catch (err) {}
})();
</script>
<?php endif; ?>