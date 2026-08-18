<?php
$_footer_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$_footer_username = $currentUser['username'] ?? '';
$_account_url = userManagementUrl();
?>
<?php if ($currentUser ?? null): ?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-account">
            <span class="footer-avatar" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($_footer_username, 0, 1))) ?></span>
            <div>
                <span class="footer-label">Angemeldet als</span>
                <strong class="footer-user"><?= h($_footer_username) ?></strong>
            </div>
        </div>
        <nav class="footer-links" aria-label="Kontoverwaltung">
            <a href="/account/change_password.php"<?= $_footer_uri === '/account/change_password.php' ? ' class="active"' : '' ?>>Passwort</a>
            <?php if ($_account_url !== ''): ?>
                <a href="<?= h($_account_url) ?>" title="Zentrales Benutzerkonto verwalten" target="_blank" rel="noopener">Konto</a>
            <?php endif; ?>
            <a href="/account/logout.php" class="footer-logout">Abmelden</a>
        </nav>
    </div>
</footer>
<?php endif; ?>
</body>
</html>
