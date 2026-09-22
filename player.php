<?php
require_once 'config.php';

// Database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed. Check config.php");
}

$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$uuid = isset($_GET['uuid']) ? trim($_GET['uuid']) : '';

if ($name === '' && $uuid === '') {
    header('Location: index.php');
    exit;
}

// Fetch active punishments
$active = [];
$history = [];

try {
    if ($uuid !== '') {
        $stmt = $pdo->prepare("SELECT * FROM `Punishments` WHERE `uuid` = ? ORDER BY `start` DESC");
        $stmt->execute([$uuid]);
        $active = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT * FROM `PunishmentHistory` WHERE `uuid` = ? ORDER BY `start` DESC");
        $stmt->execute([$uuid]);
        $history = $stmt->fetchAll();
    } else {
        // Fallback to name search
        $stmt = $pdo->prepare("SELECT * FROM `Punishments` WHERE `name` = ? ORDER BY `start` DESC");
        $stmt->execute([$name]);
        $active = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT * FROM `PunishmentHistory` WHERE `name` = ? ORDER BY `start` DESC");
        $stmt->execute([$name]);
        $history = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // tables might not exist yet
}

// Get display name & uuid from first result
$displayName = $name;
$displayUuid = $uuid;
if (!empty($active)) {
    $displayName = $active[0]['name'] ?? $name;
    $displayUuid = $active[0]['uuid'] ?? $uuid;
} elseif (!empty($history)) {
    $displayName = $history[0]['name'] ?? $name;
    $displayUuid = $history[0]['uuid'] ?? $uuid;
}

// Helpers (same as index)
function formatType($type) {
    $map = [
        'BAN' => 'Ban', 'TEMP_BAN' => 'Temp Ban',
        'IP_BAN' => 'IP Ban', 'TEMP_IP_BAN' => 'Temp IP Ban',
        'MUTE' => 'Mute', 'TEMP_MUTE' => 'Temp Mute',
        'WARNING' => 'Warning', 'TEMP_WARNING' => 'Temp Warning',
        'KICK' => 'Kick', 'NOTE' => 'Note',
    ];
    return $map[$type] ?? $type;
}

function badgeClass($type) {
    return 'badge-' . strtolower($type);
}

function formatDate($ms) {
    if ($ms === null || $ms == 0) return '—';
    return date('Y-m-d H:i', (int)($ms / 1000));
}

function formatExpires($end) {
    if ($end === null || $end == -1) {
        return '<span class="status-active">Permanent</span>';
    }
    $ts = (int)($end / 1000);
    if ($ts <= time()) {
        return '<span class="status-expired">Expired</span>';
    }
    return '<span class="status-active">' . date('Y-m-d H:i', $ts) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($displayName) ?> — Punishments — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo">
                <div class="logo-icon">⛏</div>
                <div><?= htmlspecialchars(SITE_NAME) ?> <span>Bans</span></div>
            </a>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <a href="index.php" class="tab" style="padding:0.45rem 0.9rem;">Punishments</a>
                <a href="stats.php" class="tab" style="padding:0.45rem 0.9rem;">Player Stats</a>
            </div>
            <form class="search-box" method="get" action="index.php">
                <input type="text" name="q" placeholder="Search player..." autocomplete="off">
                <button type="submit">Search</button>
            </form>
        </div>
    </header>

    <main class="container">
        <a href="index.php" class="back-link">← Back to all punishments</a>

        <div class="player-header">
            <img src="https://mc-heads.net/avatar/<?= htmlspecialchars($displayName) ?>/80" 
                 alt="<?= htmlspecialchars($displayName) ?>"
                 onerror="this.src='https://mc-heads.net/avatar/Steve/80'">
            <div>
                <h1><?= htmlspecialchars($displayName) ?></h1>
                <?php if ($displayUuid): ?>
                    <div class="uuid"><?= htmlspecialchars($displayUuid) ?></div>
                <?php endif; ?>
                <div style="margin-top:0.5rem;color:var(--text-secondary);font-size:0.9rem;">
                    <?= count($active) ?> active · <?= count($history) ?> total in history
                </div>
            </div>
        </div>

        <!-- Active -->
        <h2 style="margin-bottom:1rem;font-size:1.2rem;">Active Punishments</h2>
        <div class="table-wrapper" style="margin-bottom:2.5rem;">
            <?php if (empty($active)): ?>
                <div class="empty">
                    <div class="empty-icon">✅</div>
                    <p>No active punishments.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Operator</th>
                            <th class="hide-mobile">Issued</th>
                            <th>Expires</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active as $p): ?>
                            <tr>
                                <td>
                                    <span class="badge <?= badgeClass($p['punishmentType']) ?>">
                                        <?= formatType($p['punishmentType']) ?>
                                    </span>
                                </td>
                                <td class="reason" title="<?= htmlspecialchars($p['reason'] ?? '') ?>">
                                    <?= htmlspecialchars($p['reason'] ?? '—') ?>
                                </td>
                                <td class="operator"><?= htmlspecialchars($p['operator'] ?? '—') ?></td>
                                <td class="date hide-mobile"><?= formatDate($p['start']) ?></td>
                                <td><?= formatExpires($p['end']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- History -->
        <h2 style="margin-bottom:1rem;font-size:1.2rem;">Full History</h2>
        <div class="table-wrapper">
            <?php if (empty($history)): ?>
                <div class="empty">
                    <div class="empty-icon">📭</div>
                    <p>No punishment history found.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Operator</th>
                            <th class="hide-mobile">Issued</th>
                            <th>Expires / Ended</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $p): ?>
                            <tr>
                                <td>
                                    <span class="badge <?= badgeClass($p['punishmentType']) ?>">
                                        <?= formatType($p['punishmentType']) ?>
                                    </span>
                                </td>
                                <td class="reason" title="<?= htmlspecialchars($p['reason'] ?? '') ?>">
                                    <?= htmlspecialchars($p['reason'] ?? '—') ?>
                                </td>
                                <td class="operator"><?= htmlspecialchars($p['operator'] ?? '—') ?></td>
                                <td class="date hide-mobile"><?= formatDate($p['start']) ?></td>
                                <td><?= formatExpires($p['end']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            Powered by <a href="https://www.spigotmc.org/resources/advancedban.8695/" target="_blank">AdvancedBan</a> · 
            <?= htmlspecialchars(SITE_NAME) ?> Punishment Panel
        </div>
    </footer>
</body>
</html>
