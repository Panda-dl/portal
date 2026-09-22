<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body.home {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            min-height: 100vh; padding: 1.5rem;
        }
        .hero {
            width: 100%; max-width: 400px;
            padding: 2rem 1.75rem;
            text-align: center;
        }
        .hero .icon {
            width: 56px; height: 56px; margin: 0 auto 1rem;
            border-radius: 16px;
            background: linear-gradient(135deg, #7c3aed, #a78bfa);
            display: grid; place-items: center; font-size: 1.6rem;
            box-shadow: 0 12px 30px rgba(124,58,237,0.35);
        }
        .hero h1 {
            font-size: 1.55rem; font-weight: 800; letter-spacing: -0.03em;
            margin-bottom: 0.35rem;
        }
        .hero p { color: var(--muted); font-size: 0.9rem; margin-bottom: 1.5rem; }
        .search-stack { display: flex; flex-direction: column; gap: 0.65rem; }
        .links {
            margin-top: 1.25rem; display: flex; justify-content: center;
            gap: 0.85rem; flex-wrap: wrap;
        }
        .links a { font-size: 0.88rem; color: var(--muted); font-weight: 600; }
        .links a:hover { color: var(--accent2); }
        .links a.discord { color: #5865F2; }
        .links a.discord:hover { color: #7289da; }
    </style>
</head>
<body class="bg-scene home">
    <div class="card hero">
        <div class="icon">⛏</div>
        <h1><?= htmlspecialchars(SITE_NAME) ?></h1>
        <p>Search a player for stats &amp; punishments</p>
        <form class="search-stack" method="get" action="stats.php">
            <input type="text" name="q" placeholder="Player name..." required autofocus autocomplete="off">
            <button class="btn btn-primary" type="submit">Search</button>
        </form>
        <div class="links">
            <a href="stats.php">Leaderboards</a>
            <a href="rules.php">Rules</a>
            <?php if (defined('DISCORD_INVITE') && DISCORD_INVITE && strpos(DISCORD_INVITE, 'your-invite') === false): ?>
                <a class="discord" href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
            <?php endif; ?>
        </div>
        <p style="margin-top:1rem;font-size:0.78rem;color:var(--muted);">
            Also try Discord: <code style="color:#a78bfa;">/stats</code>
        </p>
    </div>
    <div class="footer" style="position:fixed;bottom:0;left:0;right:0;"><?= htmlspecialchars(SITE_NAME) ?></div>
</body>
</html>
