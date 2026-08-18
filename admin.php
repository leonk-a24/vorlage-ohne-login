<?php
require_once __DIR__ . '/includes/init.php';

requirePageAuth();

$userId = (int)$currentUser['id'];
$isGlobal = !empty($currentUser['is_global_admin']);
$isAppAdmin = isAdmin($currentUser);

if (!$isAppAdmin && !$isGlobal) {
    http_response_code(403);
    die('Zugriff verweigert. Nur Administratoren.');
}

$authPdo = getAuthPdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if ($action === 'toggle_approve' && $targetUserId && $targetUserId !== $userId) {
        setToolApproval($authPdo, $targetUserId, (int)$_POST['new_status'], $userId);
        invalidateUserCache();
        header('Location: /admin.php?msg=user_updated');
        exit;
    }

    if ($action === 'toggle_tool_admin' && $targetUserId && $targetUserId !== $userId) {
        setToolAdmin($authPdo, $targetUserId, (int)$_POST['new_status'], $userId);
        invalidateUserCache();
        header('Location: /admin.php?msg=admin_updated');
        exit;
    }

    if ($action === 'update_settings') {
        setToolSetting('allow_self_registration', isset($_POST['allow_self_registration']) ? '1' : '0');
        header('Location: /admin.php?msg=settings_updated');
        exit;
    }
}

try {
    $usersTable = sqlIdentifier(toolUsersTable());
    $adminColumn = sqlIdentifier(toolAdminColumn());
    $stmt = $authPdo->query("
        SELECT u.id, u.username, u.email, u.is_global_admin, u.created_at,
               COALESCE(tu.{$adminColumn}, 0) AS is_tool_admin,
               COALESCE(tu.is_approved, 0) AS is_approved
        FROM users u
        LEFT JOIN {$usersTable} tu ON tu.user_id = u.id
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Admin user list failed: ' . $e->getMessage());
    $users = $authPdo->query("
        SELECT id, username, email, is_global_admin, created_at,
               0 AS is_tool_admin, 0 AS is_approved
        FROM users
        ORDER BY created_at DESC
    ")->fetchAll();
}

$allowSelfRegistration = isSelfRegistrationAllowed();
$messages = [
    'user_updated' => 'Benutzerstatus aktualisiert',
    'admin_updated' => 'Admin-Status aktualisiert',
    'settings_updated' => 'Einstellungen gespeichert',
];
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
        <span class="status-badge <?= $isGlobal ? 'badge-global' : 'badge-admin' ?>"><?= $isGlobal ? 'Globaler Admin' : 'Tool-Admin' ?></span>
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
        <h2>Benutzerverwaltung (<?= count($users) ?>)</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Benutzername</th>
                        <th>E-Mail</th>
                        <th>Status</th>
                        <th>Rolle</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><?= h($u['username']) ?></td>
                        <td><?= h($u['email']) ?></td>
                        <td><span class="status-badge <?= $u['is_approved'] ? 'badge-active' : 'badge-inactive' ?>"><?= $u['is_approved'] ? 'Aktiv' : 'Wartend' ?></span></td>
                        <td>
                            <?php if ($u['is_global_admin']): ?>
                                <span class="status-badge badge-global">Global</span>
                            <?php elseif ($u['is_tool_admin']): ?>
                                <span class="status-badge badge-admin">Tool-Admin</span>
                            <?php else: ?>
                                User
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$u['id'] === $userId): ?>
                                Du selbst
                            <?php elseif ($u['is_global_admin'] && !$isGlobal): ?>
                                Globaler Admin
                            <?php else: ?>
                                <div class="action-row">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_approve">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $u['is_approved'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= $u['is_approved'] ? 'btn-secondary' : 'btn-primary' ?>"><?= $u['is_approved'] ? 'Deaktivieren' : 'Freischalten' ?></button>
                                    </form>
                                    <?php if (!$u['is_global_admin']): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="toggle_tool_admin">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= $u['is_tool_admin'] ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm"><?= $u['is_tool_admin'] ? 'Admin entziehen' : 'Admin machen' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
