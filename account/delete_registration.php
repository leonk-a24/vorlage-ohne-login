<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';

$pdo = getAuthPdo();
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrierung loeschen - <?= h(appName()) ?></title>
    <link rel="stylesheet" href="/style.css">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>
<main class="container narrow-page">
<?php
if (empty($token)) {
    echo '<div class="card panel"><p>Ungueltiger oder fehlender Link.</p><a href="/" class="btn btn-primary">Zur Startseite</a></div>';
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE delete_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<div class="card panel"><p>Dieser Link ist ungueltig oder die Registrierung wurde bereits geloescht.</p><a href="/account/login.php" class="btn btn-primary">Zum Login</a></div>';
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit;
}

$wasApproved = false;
try {
    $status = fetchToolStatus($pdo, (int)$user['id']);
    $wasApproved = (bool)($status['is_approved'] ?? false);
} catch (Throwable $e) {
    error_log('Tool approval lookup before delete failed: ' . $e->getMessage());
}

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$user['id']]);

try {
    $adminStmt = $pdo->query("SELECT email FROM users WHERE is_global_admin = 1 AND email IS NOT NULL AND email != ''");
    $adminEmails = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($adminEmails)) {
        $subject = "Registrierung geloescht: {$user['username']} - " . appName();
        $message = "Ein Nutzer hat seine Registrierung rueckgaengig gemacht:\n\n"
                 . "Benutzername: {$user['username']}\n"
                 . "E-Mail: {$user['email']}\n"
                 . "Status: " . ($wasApproved ? 'bereits freigeschaltet' : 'noch nicht freigeschaltet') . "\n"
                 . "Zeit: " . date('Y-m-d H:i') . "\n";
        send_email($adminEmails, $subject, $message);
    }
} catch (Exception $e) {
    error_log('Admin notification after delete failed: ' . $e->getMessage());
}
?>
    <div class="card panel">
        <h1>Registrierung geloescht</h1>
        <p>Deine Registrierung wurde erfolgreich geloescht. Du kannst dich jederzeit erneut registrieren.</p>
        <a href="/account/login.php" class="btn btn-primary">Zum Login</a>
    </div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
