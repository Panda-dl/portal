<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rules — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .rules-card { max-width: 640px; margin: 0 auto; padding: 1.75rem; }
        .rules-card h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; }
        .rules-card .sub { color: var(--muted); font-size: 0.9rem; margin-bottom: 1.5rem; }
        .rules-card h2 {
            font-size: 0.95rem; color: var(--accent2); margin: 1.25rem 0 0.5rem;
            text-transform: uppercase; letter-spacing: 0.04em;
        }
        .rules-card ol, .rules-card ul { padding-left: 1.2rem; color: rgba(255,255,255,0.85); }
        .rules-card li { margin: 0.4rem 0; font-size: 0.92rem; line-height: 1.45; }
        .nav-top { max-width: 640px; margin: 0 auto 1rem; padding: 1rem 0 0; }
    </style>
</head>
<body>
    <div class="container">
        <nav class="nav nav-top">
            <a href="index.php" class="nav-brand"><span class="icon">⛏</span><?= htmlspecialchars(SITE_NAME) ?></a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="stats.php">Leaderboards</a>
                <a href="rules.php" class="active">Rules</a>
                <?php if (defined('DISCORD_INVITE') && DISCORD_INVITE && strpos(DISCORD_INVITE, 'your-invite') === false): ?>
                    <a href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Discord</a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="card rules-card">
            <h1>Server Rules</h1>
            <p class="sub">Follow these rules. Staff decisions are final. Edit this page in <code>rules.php</code>.</p>

            <h2>1. General</h2>
            <ol>
                <li>Be respectful — no harassment, hate speech, or toxicity.</li>
                <li>No spam or advertising other servers without permission.</li>
                <li>Use an appropriate username and skin.</li>
            </ol>

            <h2>2. Gameplay</h2>
            <ol>
                <li>No cheating, hacks, or unfair advantages.</li>
                <li>No bug/exploit abuse — report exploits to staff.</li>
                <li>No griefing or stealing where the game mode forbids it.</li>
            </ol>

            <h2>3. Chat</h2>
            <ol>
                <li>Keep chat appropriate for all ages if the server is family-friendly.</li>
                <li>No excessive caps, spam, or flood.</li>
            </ol>

            <h2>4. Punishments</h2>
            <ul>
                <li>Breaking rules may result in mute, kick, or ban.</li>
                <li>Check your record: search your name on the <a href="index.php">stats site</a> or use Discord <code>/banlookup</code>.</li>
            </ul>

            <?php if (defined('DISCORD_INVITE') && DISCORD_INVITE && strpos(DISCORD_INVITE, 'your-invite') === false): ?>
                <p style="margin-top:1.5rem;">
                    <a class="btn btn-primary" href="<?= htmlspecialchars(DISCORD_INVITE) ?>" target="_blank" rel="noopener">Join Discord</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer"><?= htmlspecialchars(SITE_NAME) ?></div>
</body>
</html>
