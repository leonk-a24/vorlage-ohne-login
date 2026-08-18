<?php
require_once __DIR__ . '/includes/init.php';

$isAppAdmin = 1; // Hier auf 1 setzen, um die Admin-Seite zu aktivieren. In einer echten Anwendung sollte dies durch eine Authentifizierung überprüft werden.
if (!$isAppAdmin) {
    http_response_code(403);
    die('Zugriff verweigert. Nur Administratoren.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_settings') {
        setToolSetting('allow_self_registration', isset($_POST['allow_self_registration']) ? '1' : '0');
        header('Location: /admin.php?msg=settings_updated');
        exit;
    }
}

$allowSelfRegistration = isSelfRegistrationAllowed();
$messages = [
    'settings_updated' => 'Einstellungen gespeichert',
];

<?php

// Nur Testdaten – nicht für eine echte Benutzerverwaltung.
$users = [
    [
        'id'              => 1,
        'username'        => 'Käptn_Kartoffel',
        'email'           => 'kartoffel@reibekuchen.invalid',
        'is_approved'     => true,
        'is_global_admin' => true,
        'is_tool_admin'   => false,
    ],
    [
        'id'              => 2,
        'username'        => 'Sir_Pupsalot',
        'email'           => 'windmaschine@quatsch.invalid',
        'is_approved'     => true,
        'is_global_admin' => false,
        'is_tool_admin'   => true,
    ],
    [
        'id'              => 3,
        'username'        => 'Bratwurst-Orakel',
        'email'           => 'prophezeiung@senf.invalid',
        'is_approved'     => false,
        'is_global_admin' => false,
        'is_tool_admin'   => false,
    ],
    [
        'id'              => 4,
        'username'        => 'Professor_Unfug',
        'email'           => 'wissenschaft@blödsinn.invalid',
        'is_approved'     => true,
        'is_global_admin' => false,
        'is_tool_admin'   => false,
    ],
    [
        'id'              => 5,
        'username'        => 'Günther_vom_Mond',
        'email'           => 'kaese@mondbasis.invalid',
        'is_approved'     => false,
        'is_global_admin' => false,
        'is_tool_admin'   => true,
    ],
];

$userId = random_int(1, 5);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin - <?= h(appName()) ?></title>
    <link rel="stylesheet" href="/style.css">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>

<main class="container app-page">
    <div class="page-header">
        <div>
            <h1>Admin</h1>
            <p class="subtitle"><?= h(appName()) ?></p>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="card notice"><?= h($messages[$_GET['msg']] ?? 'Gespeichert') ?></div>
    <?php endif; ?>

    <section class="card panel">
        <h2>Einstellungen</h2>
        <form method="POST" class="inline-form">
            <input type="hidden" name="action" value="update_settings">
            <label class="checkbox-label">
                <input type="checkbox" name="allow_self_registration" <?= $allowSelfRegistration ? 'checked' : '' ?>>
                Selbst-Registrierung erlauben
            </label>
            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
    </section>
    <section class="card panel">
        <h2>Benutzerverwaltung (<?= count($users) ?> völlig seriöse Gestalten)</h2>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Benutzername</th>
                        <th>E-Mail</th>
                        <th>Status</th>
                        <th>Rolle</th>
                        <th>Sinnlose Aktionen</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= (int) $u['id'] ?></td>
                            <td><?= h($u['username']) ?></td>
                            <td><?= h($u['email']) ?></td>

                            <td>
                                <span class="status-badge <?= $u['is_approved']
                                    ? 'badge-active'
                                    : 'badge-inactive' ?>">
                                    <?= $u['is_approved']
                                        ? 'Darf Quatsch machen'
                                        : 'Wartet auf Senf' ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($u['is_global_admin']): ?>
                                    <span class="status-badge badge-global">
                                        Oberster Quatschminister
                                    </span>
                                <?php elseif ($u['is_tool_admin']): ?>
                                    <span class="status-badge badge-admin">
                                        Werkzeug-Zauberer
                                    </span>
                                <?php else: ?>
                                    Normaler Unsinnsmensch
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ((int) $u['id'] === $userId): ?>
                                    <span class="status-badge">
                                        Das bist offenbar du
                                    </span>
                                <?php else: ?>
                                    <div class="action-row">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-secondary"
                                            disabled
                                            title="Dieser Button ist nur Dekoration"
                                        >
                                            Konfetti beantragen
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-secondary"
                                            disabled
                                            title="Tut absolut nichts"
                                        >
                                            Zum Mond befördern
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="panel-note">
            Diese Benutzerverwaltung ist eine Attrappe. Änderungen werden weder
            gespeichert noch ernst genommen.
        </p>
    </section>
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
