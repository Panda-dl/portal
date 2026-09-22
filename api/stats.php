<?php
require_once 'config.php';

if (!defined('AJLB_PREFIX')) define('AJLB_PREFIX', 'ajlb_');
if (!defined('AJLB_BOARDS')) {
    define('AJLB_BOARDS', [
        'statistic_player_kills' => 'Kills',
        'statistic_deaths' => 'Deaths',
        'statistic_days_played' => 'Days Played',
    ]);
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed.');
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$boards = AJLB_BOARDS;
$prefix = AJLB_PREFIX;

function formatNumber($num) {
    if ($num === null) return '—';
    $num = (float)$num;
    if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
    if ($num >= 1000) return number_format($num);
    return number_format($num);
}
function calcKDR($k, $d) {
    $k = (float)$k; $d = (float)$d;
    if ($d <= 0) return $k > 0 ? number_format($k, 2) : '0.00';
    return number_format($k / $d, 2);
}
function formatType($type) {
    $map = ['BAN'=>'Ban','TEMP_BAN'=>'Temp Ban','IP_BAN'=>'IP Ban','TEMP_IP_BAN'=>'Temp IP Ban','MUTE'=>'Mute','TEMP_MUTE'=>'Temp Mute','WARNING'=>'Warning','TEMP_WARNING'=>'Temp Warning','KICK'=>'Kick','NOTE'=>'Note'];
    return $map[$type] ?? $type;
}
function formatDate($ms) {
    if (!$ms) return '—';
    return date('M j, Y · H:i', (int)($ms / 1000));
}
function formatExpires($end) {
    if ($end === null || $end == -1) return 'Permanent';
    $ts = (int)($end / 1000);
    return $ts <= time() ? 'Expired' : date('M j, Y · H:i', $ts);
}

/* ========== PLAYER PROFILE ========== */
if ($search !== '') {
    $playerName = $search;
    $playerStats = [];
    $killsVal = $deathsVal = $daysVal = null;

    foreach ($boards as $key => $name) {
        $table = $prefix . $key;
        try {
            $stmt = $pdo->prepare("SELECT `namecache`, `value` FROM `$table` WHERE LOWER(`namecache`) = LOWER(?) LIMIT 1");
            $stmt->execute([$search]);
            $row = $stmt->fetch();
            if ($row) {
                $rankStmt = $pdo->prepare("SELECT COUNT(*)+1 FROM `$table` WHERE `value` > ?");
                $rankStmt->execute([$row['value']]);
                $playerStats[$key] = ['name'=>$name,'value'=>$row['value'],'rank'=>(int)$rankStmt->fetchColumn()];
                $playerName = $row['namecache'];
                if ($key === 'statistic_player_kills') $killsVal = $row['value'];
                if ($key === 'statistic_deaths') $deathsVal = $row['value'];
                if ($key === 'statistic_days_played') $daysVal = $row['value'];
            } else {
                $playerStats[$key] = ['name'=>$name,'value'=>null,'rank'=>null];
            }
        } catch (Exception $e) {
            $playerStats[$key] = ['name'=>$name,'value'=>null,'rank'=>null];
        }
    }

    $playerKDR = ($killsVal !== null || $deathsVal !== null) ? calcKDR($killsVal ?? 0, $deathsVal ?? 0) : null;

    // Rank
    $playerRank = $playerRankRaw = null;
    try {
        $lp = (defined('LP_PREFIX') ? LP_PREFIX : 'luckperms_') . 'players';
        $stmt = $pdo->prepare("SELECT `primary_group`, `username` FROM `$lp` WHERE LOWER(`username`) = LOWER(?) LIMIT 1");
        $stmt->execute([$playerName]);
        $row = $stmt->fetch();
        if ($row && !empty($row['primary_group'])) {
            $playerRankRaw = strtolower(trim($row['primary_group']));
            $map = defined('GROUP_DISPLAY') ? GROUP_DISPLAY : [];
            $playerRank = $map[$playerRankRaw] ?? ucfirst($playerRankRaw);
            if (!empty($row['username'])) $playerName = $row['username'];
        }
    } catch (Exception $e) {}

    $isOp = $playerRankRaw && defined('OP_GROUPS') && OP_GROUPS && in_array($playerRankRaw, array_map('strtolower', OP_GROUPS), true);

    // Last seen from Plan — if under 1 minute, show Online
    $lastSeenText = null;
    $isOnlineNow = false;
    try {
        $st = $pdo->prepare("SELECT `id` FROM `plan_users` WHERE LOWER(`name`) = LOWER(?) LIMIT 1");
        $st->execute([$playerName]);
        $pu = $st->fetch();
        if ($pu) {
            $st = $pdo->prepare("SELECT MAX(`session_end`) mx, MAX(`session_start`) ms FROM `plan_sessions` WHERE `user_id` = ?");
            $st->execute([(int)$pu['id']]);
            $s = $st->fetch();
            $ls = (int)(($s['mx'] ?? 0) ?: ($s['ms'] ?? 0));
            if ($ls > 0) {
                $secs = (int)($ls / 1000);
                $diff = max(0, time() - $secs);
                // 0 min ago / under 60 seconds → treat as Online
                if ($diff < 60) {
                    $isOnlineNow = true;
                    $lastSeenText = 'Online';
                } elseif ($diff < 3600) {
                    $lastSeenText = floor($diff / 60) . ' min ago · ' . date('M j, Y H:i', $secs);
                } elseif ($diff < 86400) {
                    $lastSeenText = floor($diff / 3600) . ' hours ago · ' . date('M j, Y H:i', $secs);
                } elseif ($diff < 604800) {
                    $lastSeenText = floor($diff / 86400) . ' days ago · ' . date('M j, Y H:i', $secs);
                } else {
                    $lastSeenText = date('M j, Y · H:i', $secs);
                }
            }
        }
    } catch (Exception $e) {}

    $active = $history = [];
    try {
        $st = $pdo->prepare("SELECT * FROM `Punishments` WHERE LOWER(`name`) = LOWER(?) ORDER BY `start` DESC");
        $st->execute([$playerName]);
        $active = $st->fetchAll();
        $st = $pdo->prepare("SELECT * FROM `PunishmentHistory` WHERE LOWER(`name`) = LOWER(?) ORDER BY `start` DESC LIMIT 15");
        $st->execute([$playerName]);
        $history = $st->fetchAll();
    } catch (Exception $e) {}

    $hasStats = false;
    foreach ($playerStats as $s) { if ($s['value'] !== null) { $hasStats = true; break; } }

    // Player exists if found in any data source
    $playerFound = $hasStats || $playerRank !== null || !empty($active) || !empty($history);
    if (!$playerFound) {
        try {
            $lp = (defined('LP_PREFIX') ? LP_PREFIX : 'luckperms_') . 'players';
            $st = $pdo->prepare("SELECT 1 FROM `$lp` WHERE LOWER(`username`) = LOWER(?) LIMIT 1");
            $st->execute([$search]);
            if ($st->fetch()) $playerFound = true;
        } catch (Exception $e) {}
    }
    if (!$playerFound) {
        try {
            $st = $pdo->prepare("SELECT 1 FROM `plan_users` WHERE LOWER(`name`) = LOWER(?) LIMIT 1");
            $st->execute([$search]);
            if ($st->fetch()) $playerFound = true;
        } catch (Exception $e) {}
    }
    if (!$playerFound) {
        try {
            $st = $pdo->prepare("SELECT 1 FROM `PunishmentHistory` WHERE LOWER(`name`) = LOWER(?) LIMIT 1");
            $st->execute([$search]);
            if ($st->fetch()) $playerFound = true;
        } catch (Exception $e) {}
    }
    if (!$playerFound) {
        try {
            $st = $pdo->prepare("SELECT 1 FROM `Punishments` WHERE LOWER(`name`) = LOWER(?) LIMIT 1");
            $st->execute([$search]);
            if ($st->fetch()) $playerFound = true;
        } catch (Exception $e) {}
    }

    // Not found page
    if (!$playerFound) {
        $safe = htmlspecialchars($search);
        $site = htmlspecialchars(SITE_NAME);
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Player not found — {$site}</title>
<link rel="stylesheet" href="style.css">
<style>
body.home{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:1.5rem}
.box{width:100%;max-width:400px;padding:2rem 1.75rem;text-align:center}
.box .icon{font-size:2.5rem;margin-bottom:0.75rem}
.box h1{font-size:1.35rem;font-weight:800;margin-bottom:0.4rem}
.box p{color:var(--muted);font-size:0.92rem;margin-bottom:1.35rem}
</style>
</head>
<body class="bg-scene home">
<div class="card box">
<div class="icon">🔍</div>
<h1>Player not found</h1>
<p>No player named <strong style="color:#fff">{$safe}</strong> was found in the database.</p>
<a class="btn btn-primary" href="index.php">Back to search</a>
</div>
</body>
</html>
HTML;
        exit;
    }

    $rankColors = [
        'owner'=>['#ef4444','rgba(239,68,68,0.2)'],'co-owner'=>['#fb923c','rgba(249,115,22,0.2)'],
        'founder'=>['#fbbf24','rgba(251,191,36,0.2)'],'admin'=>['#f87171','rgba(239,68,68,0.18)'],
        'vip'=>['#c084fc','rgba(168,85,247,0.22)'],'mvp'=>['#38bdf8','rgba(14,165,233,0.22)'],
        'mod'=>['#60a5fa','rgba(59,130,246,0.2)'],'helper'=>['#4ade80','rgba(34,197,94,0.18)'],
    ];
    $rc = $rankColors[$playerRankRaw ?? ''] ?? ['#a78bfa','rgba(139,92,246,0.2)'];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($playerName) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { padding: 1.5rem 1rem 3rem; }
        .profile-wrap { max-width: 420px; margin: 0 auto; }
        .profile-card { overflow: hidden; margin-bottom: 1rem; }
        .profile-top {
            padding: 1.75rem 1.5rem 1.25rem;
            text-align: center;
            background: linear-gradient(160deg, rgba(139,92,246,0.22), transparent 70%);
            border-bottom: 1px solid var(--border);
        }
        .avatar {
            width: 88px; height: 88px; border-radius: 50%;
            border: 3px solid rgba(167,139,250,0.45);
            box-shadow: 0 10px 28px rgba(0,0,0,0.4);
            margin-bottom: 0.75rem;
        }
        .pname { font-size: 1.45rem; font-weight: 800; letter-spacing: -0.02em; }
        .pmeta { margin-top: 0.5rem; display: flex; gap: 0.4rem; justify-content: center; flex-wrap: wrap; align-items: center; }
        .pmeta .tag {
            padding: 0.22rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700;
        }
        .seen { margin-top: 0.45rem; font-size: 0.8rem; color: var(--muted); }
        .body { padding: 1.15rem 1.4rem 1.4rem; }
        .label {
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.07em;
            color: var(--muted); font-weight: 700; margin: 0.85rem 0 0.55rem;
        }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.55rem; }
        .stat {
            background: rgba(255,255,255,0.035);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 0.85rem;
            text-align: center;
        }
        .stat .v { font-size: 1.3rem; font-weight: 800; }
        .stat .l { font-size: 0.7rem; color: var(--muted); text-transform: uppercase; margin-top: 0.15rem; letter-spacing: 0.04em; }
        .stat .r { font-size: 0.7rem; color: rgba(255,255,255,0.3); margin-top: 0.1rem; }
        .stat.kills .v { color: #f87171; }
        .stat.deaths .v { color: #94a3b8; }
        .stat.kdr .v { color: var(--green); }
        .stat.days .v { color: var(--accent2); }
        .row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.5rem 0; border-bottom: 1px solid var(--border); font-size: 0.92rem;
        }
        .row:last-child { border-bottom: none; }
        .punish { margin-bottom: 1rem; padding: 1.15rem 1.25rem; }
        .punish h3 { font-size: 0.88rem; color: var(--muted); margin-bottom: 0.75rem; }
        .p-item {
            background: rgba(255,255,255,0.03);
            border-radius: 12px; padding: 0.7rem 0.85rem; margin-bottom: 0.5rem; font-size: 0.86rem;
        }
        .p-item:last-child { margin-bottom: 0; }
        .p-reason { margin: 0.25rem 0; }
        .p-meta { font-size: 0.78rem; color: var(--muted); }
        .empty { text-align: center; color: var(--muted); padding: 0.6rem 0; font-size: 0.88rem; }
        .back { display: block; text-align: center; margin-top: 0.75rem; color: var(--muted); font-size: 0.88rem; }
    </style>
</head>
<body class="bg-scene">
    <div class="profile-wrap">
        <div class="card profile-card">
            <div class="profile-top">
                <img class="avatar" src="https://mc-heads.net/avatar/<?= urlencode($playerName) ?>/88" onerror="this.src='https://mc-heads.net/avatar/Steve/88'" alt="">
                <div class="pname"><?= htmlspecialchars($playerName) ?></div>
                <div class="pmeta">
                    <?php if ($playerRank): ?>
                        <span class="tag" style="color:<?= $rc[0] ?>;background:<?= $rc[1] ?>"><?= htmlspecialchars($playerRank) ?></span>
                    <?php endif; ?>
                    <?php if ($isOp): ?>
                        <span class="tag" style="color:#fbbf24;background:rgba(251,191,36,0.2)">OP</span>
                    <?php endif; ?>
                </div>
                <?php if ($lastSeenText): ?>
                    <?php if ($isOnlineNow): ?>
                        <div class="seen" style="color:#4ade80;font-weight:600;">● Online</div>
                    <?php else: ?>
                        <div class="seen">Last seen <?= htmlspecialchars($lastSeenText) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="body">
                <div class="label">Statistics</div>
                <?php if (!$hasStats): ?>
                    <p class="empty">No stats found</p>
                <?php else: ?>
                    <div class="grid">
                        <div class="stat kills">
                            <div class="v"><?= $killsVal !== null ? formatNumber($killsVal) : '—' ?></div>
                            <div class="l">Kills</div>
                            <?php if (!empty($playerStats['statistic_player_kills']['rank'])): ?><div class="r">#<?= $playerStats['statistic_player_kills']['rank'] ?></div><?php endif; ?>
                        </div>
                        <div class="stat deaths">
                            <div class="v"><?= $deathsVal !== null ? formatNumber($deathsVal) : '—' ?></div>
                            <div class="l">Deaths</div>
                            <?php if (!empty($playerStats['statistic_deaths']['rank'])): ?><div class="r">#<?= $playerStats['statistic_deaths']['rank'] ?></div><?php endif; ?>
                        </div>
                        <div class="stat kdr">
                            <div class="v"><?= $playerKDR ?? '—' ?></div>
                            <div class="l">K/D</div>
                        </div>
                        <div class="stat days">
                            <div class="v"><?= $daysVal !== null ? formatNumber($daysVal) : '—' ?></div>
                            <div class="l">Days</div>
                            <?php if (!empty($playerStats['statistic_days_played']['rank'])): ?><div class="r">#<?= $playerStats['statistic_days_played']['rank'] ?></div><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <a class="back" href="index.php">← Back to search</a>
            </div>
        </div>

        <div class="card punish">
            <h3>Active punishments</h3>
            <?php if (!$active): ?><p class="empty">None</p>
            <?php else: foreach ($active as $p): ?>
                <div class="p-item">
                    <span class="badge badge-<?= strtolower($p['punishmentType']) ?>"><?= formatType($p['punishmentType']) ?></span>
                    <div class="p-reason"><?= htmlspecialchars($p['reason'] ?? 'No reason') ?></div>
                    <div class="p-meta"><?= formatDate($p['start']) ?> · <?= formatExpires($p['end']) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card punish">
            <h3>Punishment history</h3>
            <?php if (!$history): ?><p class="empty">None</p>
            <?php else: foreach ($history as $p): ?>
                <div class="p-item">
                    <span class="badge badge-<?= strtolower($p['punishmentType']) ?>"><?= formatType($p['punishmentType']) ?></span>
                    <div class="p-reason"><?= htmlspecialchars($p['reason'] ?? 'No reason') ?></div>
                    <div class="p-meta"><?= formatDate($p['start']) ?> · <?= formatExpires($p['end']) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

/* ========== LEADERBOARDS ========== */
$topBoards = [];
foreach ($boards as $key => $name) {
    try {
        $stmt = $pdo->prepare("SELECT `namecache`,`value` FROM `" . $prefix . $key . "` ORDER BY `value` DESC LIMIT 10");
        $stmt->execute();
        $topBoards[$key] = ['name'=>$name,'rows'=>$stmt->fetchAll()];
    } catch (Exception $e) {
        $topBoards[$key] = ['name'=>$name,'rows'=>[]];
    }
}
$kdrTop = [];
try {
    $kt = $prefix . 'statistic_player_kills';
    $dt = $prefix . 'statistic_deaths';
    $sql = "SELECT COALESCE(k.namecache,d.namecache) namecache, COALESCE(k.value,0) kills, COALESCE(d.value,0) deaths
            FROM `$kt` k LEFT JOIN `$dt` d ON k.id=d.id
            UNION SELECT COALESCE(k.namecache,d.namecache), COALESCE(k.value,0), COALESCE(d.value,0)
            FROM `$dt` d LEFT JOIN `$kt` k ON d.id=k.id WHERE k.id IS NULL";
    $all = $pdo->query($sql)->fetchAll();
    foreach ($all as &$p) {
        $k=(float)$p['kills']; $d=(float)$p['deaths'];
        $p['kdr'] = $d<=0 ? ($k>0?$k:0) : $k/$d;
    }
    unset($p);
    usort($all, fn($a,$b)=>$b['kdr']<=>$a['kdr']);
    $kdrTop = array_slice($all, 0, 10);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboards — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .boards { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.15rem; }
        .board { overflow: hidden; }
        .board h3 {
            margin: 0; padding: 0.95rem 1.1rem; font-size: 0.95rem;
            background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border);
        }
        .board table { background: transparent; }
        .board td { border-color: rgba(255,255,255,0.04); }
        .rank { width: 36px; font-weight: 800; color: var(--muted); }
        .rank-1 { color: #fbbf24; } .rank-2 { color: #94a3b8; } .rank-3 { color: #cd7f32; }
        .kdr { color: var(--green); font-weight: 700; }
        .search-inline { display: flex; gap: 0.5rem; }
        .search-inline input { min-width: 160px; padding: 0.55rem 0.85rem; border-radius: 10px; }
        .search-inline button { padding: 0.55rem 1rem; border-radius: 10px; }
        h1.page { font-size: 1.45rem; font-weight: 800; margin-bottom: 1.25rem; letter-spacing: -0.02em; }
    </style>
</head>
<body>
    <div class="container">
        <nav class="nav">
            <a href="index.php" class="nav-brand"><span class="icon">⛏</span><?= htmlspecialchars(SITE_NAME) ?></a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="stats.php" class="active">Leaderboards</a>
            </div>
            <form class="search-inline" method="get" action="stats.php">
                <input type="text" name="q" placeholder="Search player..." required>
                <button class="btn btn-primary" type="submit">Go</button>
            </form>
        </nav>

        <h1 class="page">Leaderboards</h1>
        <div class="boards">
            <?php foreach (['statistic_player_kills'=>'⚔️ Kills','statistic_deaths'=>'💀 Deaths'] as $key=>$title): ?>
            <?php if (!empty($topBoards[$key]['rows'])): ?>
            <div class="card board">
                <h3><?= $title ?> — Top 10</h3>
                <table><tbody>
                <?php foreach ($topBoards[$key]['rows'] as $i=>$row): ?>
                    <tr>
                        <td class="rank rank-<?= $i+1 ?>"><?= $i+1 ?></td>
                        <td>
                            <a class="player-chip" href="stats.php?q=<?= urlencode($row['namecache']) ?>">
                                <img src="https://mc-heads.net/avatar/<?= urlencode($row['namecache']) ?>/28" onerror="this.src='https://mc-heads.net/avatar/Steve/28'" alt="">
                                <?= htmlspecialchars($row['namecache']) ?>
                            </a>
                        </td>
                        <td style="text-align:right;font-weight:700"><?= formatNumber($row['value']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
            <?php endif; endforeach; ?>

            <div class="card board">
                <h3>📊 K/D — Top 10</h3>
                <?php if (!$kdrTop): ?><p class="muted" style="padding:1.5rem;text-align:center">No data</p>
                <?php else: ?>
                <table><tbody>
                <?php foreach ($kdrTop as $i=>$row): ?>
                    <tr>
                        <td class="rank rank-<?= $i+1 ?>"><?= $i+1 ?></td>
                        <td>
                            <a class="player-chip" href="stats.php?q=<?= urlencode($row['namecache']) ?>">
                                <img src="https://mc-heads.net/avatar/<?= urlencode($row['namecache']) ?>/28" onerror="this.src='https://mc-heads.net/avatar/Steve/28'" alt="">
                                <?= htmlspecialchars($row['namecache']) ?>
                            </a>
                        </td>
                        <td style="text-align:right" class="kdr"><?= number_format($row['kdr'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php endif; ?>
            </div>

            <?php if (!empty($topBoards['statistic_days_played']['rows'])): ?>
            <div class="card board">
                <h3>📅 Days Played — Top 10</h3>
                <table><tbody>
                <?php foreach ($topBoards['statistic_days_played']['rows'] as $i=>$row): ?>
                    <tr>
                        <td class="rank rank-<?= $i+1 ?>"><?= $i+1 ?></td>
                        <td>
                            <a class="player-chip" href="stats.php?q=<?= urlencode($row['namecache']) ?>">
                                <img src="https://mc-heads.net/avatar/<?= urlencode($row['namecache']) ?>/28" onerror="this.src='https://mc-heads.net/avatar/Steve/28'" alt="">
                                <?= htmlspecialchars($row['namecache']) ?>
                            </a>
                        </td>
                        <td style="text-align:right;font-weight:700"><?= formatNumber($row['value']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer"><?= htmlspecialchars(SITE_NAME) ?></div>
</body>
</html>
