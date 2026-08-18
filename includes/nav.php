<?php
$_nav_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
function _nav_is_active(string $path, string $uri): string {
    return $uri === $path ? ' class="active"' : '';
}
?>
<nav class="navbar">
    <a class="nav-brand" href="/">
        <img src="/images/favicon-32x32.png" alt="" width="20" height="20"><?= h(appName()) ?>
    </a>
    <div class="nav-links">
        <a href="/"<?= _nav_is_active('/', $_nav_uri) ?>>Start</a>
        <a href="/admin.php" class="nav-admin<?= $_nav_uri === '/admin.php' ? ' active' : '' ?>">Admin</a>
    </div>
    <button class="nav-toggle" aria-label="Menue" onclick="document.querySelector('.nav-links').classList.toggle('open')">Menu</button>
</nav>