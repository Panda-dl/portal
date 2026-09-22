<?php
/**
 * Vortextiers Web Panel - Configuration
 */

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'idk');
define('DB_USER', 'root');
define('DB_PASS', 'root');

define('SITE_NAME', 'Vortextiers');
define('SITE_TITLE', 'Player Stats');
define('ITEMS_PER_PAGE', 25);

date_default_timezone_set('Asia/Karachi');
define('HIDE_IP_BANS', false);
define('ADMIN_PASSWORD', 'admin123');

// Public links (shown on home / rules)
define('DISCORD_INVITE', 'https://discord.gg/your-invite');  // change to your invite
define('WEBSITE_URL', '');  // optional public URL e.g. https://stats.example.com

define('AJLB_PREFIX', 'ajlb_');
define('AJLB_BOARDS', [
    'statistic_player_kills' => 'Kills',
    'statistic_deaths'       => 'Deaths',
    'statistic_days_played'  => 'Days Played',
]);

define('LP_PREFIX', 'luckperms_');

define('STAFF_GROUPS', [
    'owner', 'co-owner', 'founder', 'admin', 'sradmin', 'manager',
    'gm', 'developer', 'srmod', 'mod', 'helper', 't.staff', 'tester',
]);

define('OP_GROUPS', [
    'owner', 'co-owner', 'founder',
]);

define('GROUP_DISPLAY', [
    'owner' => 'Owner', 'co-owner' => 'Co-Owner', 'founder' => 'Founder',
    'admin' => 'Admin', 'sradmin' => 'Sr. Admin', 'manager' => 'Manager',
    'gm' => 'Game Master', 'developer' => 'Developer', 'srmod' => 'Sr. Mod',
    'mod' => 'Moderator', 'helper' => 'Helper', 't.staff' => 'Trial Staff',
    'tester' => 'Tester', 'media' => 'Media', 'mvp' => 'MVP', 'vip' => 'VIP',
    'legend' => 'Legend', 'slayer' => 'Slayer', 'cosmetics' => 'Cosmetics',
    'done' => 'Done', 'dones' => 'Dones', 'default' => 'Player',
]);
?>
