<?php
require_once 'config.php';
session_start();

if (!defined('ADMIN_PASSWORD')) define('ADMIN_PASSWORD', 'admin123');
if (!defined('LP_PREFIX')) define('LP_PREFIX', 'luckperms_');
if (!defined('STAFF_GROUPS')) define('STAFF_GROUPS', ['owner','admin','mod']);
if (!defined('AJLB_PREFIX')) define('AJLB_PREFIX', 'ajlb_');
if (!defined('AJLB_BOARDS')) define('AJLB_BOARDS', ['statistic_player_kills'=>'Kills','statistic_deaths'=>'Deaths','statistic_days_played'=>'Days Played']);

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: admin.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['action'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Wrong password';
}

if (empty($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="style.css">
<style>
body{display:grid;place-items:center;min-height:100vh}
.login{width:100%;max-width:360px;padding:2rem}
.login h1{font-size:1.25rem;margin-bottom:1rem;text-align:center}
.err{color:var(--red);font-size:0.9rem;margin-bottom:0.75rem;text-align:center}
</style>
</head><body>
<div class="card login">
<h1>Admin Login</h1>
<?php if ($loginError): ?><div class="err"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
<form method="post">
<input type="password" name="password" placeholder="Password" required autofocus style="margin-bottom:0.75rem">
<button class="btn btn-primary" type="submit" style="width:100%">Login</button>
</form>
</div>
</body></html>
<?php exit; }

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) { die('DB failed'); }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$flash = ''; $flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $flash = 'Invalid token'; $flashType = 'err';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $table = (($_POST['table'] ?? '') === 'history') ? 'PunishmentHistory' : 'Punishments';
        if ($_POST['action'] === 'delete' && $id > 0 && ($_POST['confirm'] ?? '') === 'yes') {
            try {
                $st = $pdo->prepare("DELETE FROM `$table` WHERE `id`=? LIMIT 1");
                $st->execute([$id]);
                $flash = $st->rowCount() ? 'Deleted.' : 'Not found.';
                $flashType = $st->rowCount() ? 'ok' : 'err';
            } catch (Exception $e) { $flash = $e->getMessage(); $flashType = 'err'; }
        }
        if ($_POST['action'] === 'edit' && $id > 0) {
            $reason = trim($_POST['reason'] ?? '');
            $mode = $_POST['end_mode'] ?? 'keep';
            try {
                if ($mode === 'permanent') {
                    $pdo->prepare("UPDATE `$table` SET `reason`=?, `end`=-1 WHERE `id`=?")->execute([$reason,$id]);
                } elseif ($mode === 'expire_now') {
                    $pdo->prepare("UPDATE `$table` SET `reason`=?, `end`=? WHERE `id`=?")->execute([$reason,(int)(time()*1000),$id]);
                } elseif ($mode === 'custom' && !empty($_POST['end_datetime'])) {
                    $ts = strtotime($_POST['end_datetime']);
                    $pdo->prepare("UPDATE `$table` SET `reason`=?, `end`=? WHERE `id`=?")->execute([$reason,(int)($ts*1000),$id]);
                } else {
                    $pdo->prepare("UPDATE `$table` SET `reason`=? WHERE `id`=?")->execute([$reason,$id]);
                }
                $flash = 'Updated.'; $flashType = 'ok';
            } catch (Exception $e) { $flash = $e->getMessage(); $flashType = 'err'; }
        }
    }
}

$tab = $_GET['tab'] ?? 'punishments';
$view = $_GET['view'] ?? 'active';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50; $offset = ($page-1)*$perPage;

function formatType($t){ $m=['BAN'=>'Ban','TEMP_BAN'=>'Temp Ban','MUTE'=>'Mute','TEMP_MUTE'=>'Temp Mute','WARNING'=>'Warning','TEMP_WARNING'=>'Temp Warning','KICK'=>'Kick','IP_BAN'=>'IP Ban','TEMP_IP_BAN'=>'Temp IP Ban']; return $m[$t]??$t; }
function formatDate($ms){ return $ms? date('Y-m-d H:i',(int)($ms/1000)) : '—'; }
function formatExpires($end){
    if ($end===null||$end==-1) return '<span style="color:#f87171">Permanent</span>';
    $ts=(int)($end/1000);
    return $ts<=time() ? '<span style="color:#6a6a7a">Expired</span>' : date('Y-m-d H:i',$ts);
}
function formatNumber($n){ return $n===null?'—':number_format((float)$n); }

$punishments=[]; $totalPunish=0; $totalPages=1;
if ($tab==='punishments') {
    $table = $view==='history' ? 'PunishmentHistory' : 'Punishments';
    $where=[]; $params=[];
    if ($search!=='') {
        $where[]='(`name` LIKE ? OR `operator` LIKE ? OR `reason` LIKE ?)';
        $like='%'.$search.'%'; $params=[$like,$like,$like];
    }
    $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';
    $st=$pdo->prepare("SELECT COUNT(*) FROM `$table` $ws"); $st->execute($params); $totalPunish=(int)$st->fetchColumn();
    $totalPages=max(1,(int)ceil($totalPunish/$perPage));
    $st=$pdo->prepare("SELECT * FROM `$table` $ws ORDER BY `start` DESC LIMIT $perPage OFFSET $offset");
    $st->execute($params); $punishments=$st->fetchAll();
}

$fullBoards=[]; $kdrFull=[];
if ($tab==='leaderboards') {
    foreach (AJLB_BOARDS as $k=>$n) {
        try {
            $st=$pdo->query("SELECT namecache,value FROM `".AJLB_PREFIX."$k` ORDER BY value DESC LIMIT 200");
            $fullBoards[$k]=['name'=>$n,'rows'=>$st->fetchAll()];
        } catch(Exception $e){ $fullBoards[$k]=['name'=>$n,'rows'=>[]]; }
    }
    try {
        $kt=AJLB_PREFIX.'statistic_player_kills'; $dt=AJLB_PREFIX.'statistic_deaths';
        $all=$pdo->query("SELECT COALESCE(k.namecache,d.namecache) namecache,COALESCE(k.value,0) kills,COALESCE(d.value,0) deaths FROM `$kt` k LEFT JOIN `$dt` d ON k.id=d.id UNION SELECT COALESCE(k.namecache,d.namecache),COALESCE(k.value,0),COALESCE(d.value,0) FROM `$dt` d LEFT JOIN `$kt` k ON d.id=k.id WHERE k.id IS NULL")->fetchAll();
        foreach($all as &$p){ $k=(float)$p['kills'];$d=(float)$p['deaths'];$p['kdr']=$d<=0?($k>0?$k:0):$k/$d; } unset($p);
        usort($all,fn($a,$b)=>$b['kdr']<=>$a['kdr']); $kdrFull=array_slice($all,0,200);
    } catch(Exception $e){}
}

$staffList=[];
if ($tab==='staff') {
    try {
        $groups=STAFF_GROUPS; $ph=implode(',',array_fill(0,count($groups),'?'));
        $st=$pdo->prepare("SELECT username,primary_group FROM `".LP_PREFIX."players` WHERE LOWER(primary_group) IN ($ph) ORDER BY primary_group,username");
        $st->execute(array_map('strtolower',$groups));
        foreach ($st->fetchAll() as $s) {
            $g=strtolower($s['primary_group']);
            $staffList[]=['name'=>$s['username'],'role'=>(GROUP_DISPLAY[$g]??ucfirst($g)),'group'=>$g];
        }
    } catch(Exception $e){}
}
$tableKey = $view==='history' ? 'history' : 'active';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="style.css">
<style>
.flash{padding:0.75rem 1rem;border-radius:12px;margin-bottom:1rem;font-size:0.9rem}
.flash.ok{background:rgba(74,222,128,0.12);color:var(--green);border:1px solid rgba(74,222,128,0.25)}
.flash.err{background:rgba(239,68,68,0.12);color:var(--red);border:1px solid rgba(239,68,68,0.25)}
.tabs{display:flex;gap:0.45rem;margin-bottom:1.25rem;flex-wrap:wrap}
.tabs a{padding:0.5rem 1rem;border-radius:999px;font-size:0.88rem;font-weight:600;color:var(--muted);border:1px solid var(--border);background:var(--bg2)}
.tabs a.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.sub{display:flex;gap:0.4rem;margin-bottom:1rem}
.sub a{padding:0.35rem 0.85rem;border-radius:8px;font-size:0.82rem;color:var(--muted);background:var(--bg2);border:1px solid var(--border)}
.sub a.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.search-bar{display:flex;gap:0.5rem;margin-bottom:1rem}
.search-bar input{max-width:280px;padding:0.55rem 0.9rem}
.boards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem}
.board{overflow:hidden}
.board h3{padding:0.85rem 1rem;font-size:0.92rem;background:rgba(255,255,255,0.03);border-bottom:1px solid var(--border)}
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,0.65);display:none;align-items:center;justify-content:center;z-index:50;padding:1rem}
.modal-bg.show{display:flex}
.modal{background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:1.4rem;width:100%;max-width:420px}
.modal h3{margin-bottom:0.5rem}
.modal p{color:var(--muted);font-size:0.9rem;margin-bottom:1rem}
.modal label{display:block;font-size:0.78rem;color:var(--muted);margin:0.55rem 0 0.25rem}
.modal-actions{display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem}
</style>
</head>
<body>
<div class="container">
<nav class="nav">
    <div class="nav-brand"><span class="icon">⛏</span><?= htmlspecialchars(SITE_NAME) ?> Admin</div>
    <div class="nav-links">
        <a href="index.php">Public site</a>
        <a href="admin.php?logout=1">Logout</a>
    </div>
</nav>

<?php if ($flash): ?><div class="flash <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="tabs">
    <a href="admin.php?tab=punishments" class="<?= $tab==='punishments'?'active':'' ?>">Punishments</a>
    <a href="admin.php?tab=leaderboards" class="<?= $tab==='leaderboards'?'active':'' ?>">Leaderboards</a>
    <a href="admin.php?tab=staff" class="<?= $tab==='staff'?'active':'' ?>">Staff</a>
</div>

<?php if ($tab==='punishments'): ?>
<div class="sub">
    <a href="admin.php?tab=punishments&view=active" class="<?= $view==='active'?'active':'' ?>">Active</a>
    <a href="admin.php?tab=punishments&view=history" class="<?= $view==='history'?'active':'' ?>">History</a>
</div>
<form class="search-bar" method="get">
    <input type="hidden" name="tab" value="punishments">
    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
    <input type="text" name="q" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-primary btn-sm" type="submit">Search</button>
</form>
<p class="muted" style="margin-bottom:0.75rem;font-size:0.85rem"><?= count($punishments) ?> of <?= number_format($totalPunish) ?></p>
<div class="table-wrap">
<table>
<thead><tr><th>Player</th><th>Type</th><th>Reason</th><th>Operator</th><th>Issued</th><th>Expires</th><th></th></tr></thead>
<tbody>
<?php if (!$punishments): ?><tr><td colspan="7" class="muted" style="text-align:center">No results</td></tr>
<?php else: foreach ($punishments as $p): ?>
<tr>
<td><a class="player-chip" href="stats.php?q=<?= urlencode($p['name']) ?>"><img src="https://mc-heads.net/avatar/<?= urlencode($p['name']) ?>/24" onerror="this.src='https://mc-heads.net/avatar/Steve/24'"><?= htmlspecialchars($p['name']) ?></a></td>
<td><span class="badge badge-<?= strtolower($p['punishmentType']) ?>"><?= formatType($p['punishmentType']) ?></span></td>
<td><?= htmlspecialchars($p['reason']??'—') ?></td>
<td><?= htmlspecialchars($p['operator']??'—') ?></td>
<td><?= formatDate($p['start']) ?></td>
<td><?= formatExpires($p['end']) ?></td>
<td style="white-space:nowrap">
<button type="button" class="btn btn-sm btn-primary" onclick='openEdit(<?= (int)$p['id'] ?>,<?= json_encode($p['name']) ?>,<?= json_encode($p['reason']??'') ?>,<?= json_encode(formatType($p['punishmentType'])) ?>)'>Edit</button>
<button type="button" class="btn btn-sm btn-danger" onclick='openDelete(<?= (int)$p['id'] ?>,<?= json_encode($p['name']) ?>,<?= json_encode(formatType($p['punishmentType'])) ?>)'>Delete</button>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody></table>
</div>
<?php if ($totalPages>1): ?><div style="display:flex;gap:0.35rem;margin-top:1rem;flex-wrap:wrap">
<?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
<a class="btn btn-sm <?= $i===$page?'btn-primary':'btn-ghost' ?>" href="admin.php?tab=punishments&view=<?= urlencode($view) ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
<?php endfor; ?></div><?php endif; ?>

<?php elseif ($tab==='leaderboards'): ?>
<div class="boards">
<?php foreach ($fullBoards as $board): ?>
<div class="card board"><h3><?= htmlspecialchars($board['name']) ?> (<?= count($board['rows']) ?>)</h3>
<table><tbody>
<?php foreach ($board['rows'] as $i=>$row): ?>
<tr>
<td style="width:40px;font-weight:800;color:var(--muted)"><?= $i+1 ?></td>
<td><a class="player-chip" href="stats.php?q=<?= urlencode($row['namecache']) ?>"><img src="https://mc-heads.net/avatar/<?= urlencode($row['namecache']) ?>/24" onerror="this.src='https://mc-heads.net/avatar/Steve/24'"><?= htmlspecialchars($row['namecache']) ?></a></td>
<td style="text-align:right;font-weight:700"><?= formatNumber($row['value']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endforeach; ?>
<div class="card board"><h3>K/D (<?= count($kdrFull) ?>)</h3>
<table><tbody>
<?php foreach ($kdrFull as $i=>$row): ?>
<tr>
<td style="width:40px;font-weight:800;color:var(--muted)"><?= $i+1 ?></td>
<td><a class="player-chip" href="stats.php?q=<?= urlencode($row['namecache']) ?>"><img src="https://mc-heads.net/avatar/<?= urlencode($row['namecache']) ?>/24" onerror="this.src='https://mc-heads.net/avatar/Steve/24'"><?= htmlspecialchars($row['namecache']) ?></a></td>
<td style="text-align:right;color:var(--green);font-weight:700"><?= number_format($row['kdr'],2) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>

<?php else: ?>
<p class="muted" style="margin-bottom:0.75rem"><?= count($staffList) ?> staff members</p>
<div class="table-wrap"><table>
<thead><tr><th>Player</th><th>Role</th><th>Group</th></tr></thead>
<tbody>
<?php if (!$staffList): ?><tr><td colspan="3" class="muted" style="text-align:center">No staff found</td></tr>
<?php else: foreach ($staffList as $s): ?>
<tr>
<td><a class="player-chip" href="stats.php?q=<?= urlencode($s['name']) ?>"><img src="https://mc-heads.net/avatar/<?= urlencode($s['name']) ?>/24" onerror="this.src='https://mc-heads.net/avatar/Steve/24'"><?= htmlspecialchars($s['name']) ?></a></td>
<td><span class="badge" style="background:rgba(139,92,246,0.2);color:#a78bfa"><?= htmlspecialchars($s['role']) ?></span></td>
<td class="muted"><?= htmlspecialchars($s['group']) ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<?php endif; ?>
</div>

<div class="modal-bg" id="delModal">
<div class="modal">
<h3>Delete punishment?</h3>
<p>Delete for <strong id="delPlayer"></strong> <span id="delType" style="color:var(--red)"></span>?<br><br>Click <strong>Yes, Delete</strong> to confirm.</p>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" id="delId">
<input type="hidden" name="table" value="<?= htmlspecialchars($tableKey) ?>">
<input type="hidden" name="confirm" value="yes">
<div class="modal-actions">
<button type="button" class="btn btn-ghost" onclick="document.getElementById('delModal').classList.remove('show')">Cancel</button>
<button type="submit" class="btn btn-danger">Yes, Delete</button>
</div>
</form>
</div></div>

<div class="modal-bg" id="editModal">
<div class="modal">
<h3>Edit punishment</h3>
<p><strong id="editPlayer"></strong> · <span id="editType"></span></p>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
<input type="hidden" name="action" value="edit">
<input type="hidden" name="id" id="editId">
<input type="hidden" name="table" value="<?= htmlspecialchars($tableKey) ?>">
<label>Reason</label>
<textarea name="reason" id="editReason"></textarea>
<label>Expiry</label>
<select name="end_mode" id="endMode" onchange="document.getElementById('customEnd').style.display=this.value==='custom'?'block':'none'">
<option value="keep">Keep current</option>
<option value="permanent">Permanent</option>
<option value="expire_now">Expire now</option>
<option value="custom">Custom date</option>
</select>
<div id="customEnd" style="display:none"><label>End date</label><input type="datetime-local" name="end_datetime"></div>
<div class="modal-actions">
<button type="button" class="btn btn-ghost" onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
<button type="submit" class="btn btn-primary">Save</button>
</div>
</form>
</div></div>

<script>
function openDelete(id,player,type){document.getElementById('delId').value=id;document.getElementById('delPlayer').textContent=player;document.getElementById('delType').textContent='('+type+')';document.getElementById('delModal').classList.add('show')}
function openEdit(id,player,reason,type){document.getElementById('editId').value=id;document.getElementById('editPlayer').textContent=player;document.getElementById('editType').textContent=type;document.getElementById('editReason').value=reason;document.getElementById('editModal').classList.add('show')}
</script>
</body></html>
