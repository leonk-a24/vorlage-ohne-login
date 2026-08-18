<?php
$_footer_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>
<footer class="site-footer">
    <div class="footer-inner">
        <a> Dies ist dein Footer.</a>
    </div>
</footer>
</body>
</html>
