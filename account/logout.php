<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

initAuth();
$pdo = getAuthPdo();

if (!empty($_COOKIE['remember_me'])) {
    $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
    $stmt->execute([$_COOKIE['remember_me']]);
    setcookie('remember_me', '', time() - 3600, '/', '', true, true);
}

clearUserSession();
session_destroy();
header("Location: /account/login.php");
exit;
