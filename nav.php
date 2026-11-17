<?php
/**
 * Unified navigation for every page.
 *
 * Set $nav_mode before including:
 *   'public'  – season pages (Squad / Matches / Stats / Media)
 *   'admin'   – admin tools for one season
 *   'landing' – season list pages (index.php, home.php)
 */
require_once __DIR__ . '/helpers.php';

$__file  = basename($_SERVER['SCRIPT_NAME']);
$__sid   = (int)($season_id ?? ($_SESSION['club_id'] ?? 0));
$__club  = $_SESSION['club_name'] ?? '';
$__admin = !empty($_SESSION['admin']);
$nav_mode = $nav_mode ?? 'public';
$__q = $__sid ? '?id=' . $__sid : '';
$__admin_ui = ($nav_mode === 'admin' || $nav_mode === 'global' || ($nav_mode === 'landing' && $__file === 'home.php'));

$__icons = [
    'squad'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'matches' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'stats'   => '<path d="M3 3v18h18"/><path d="M8 17v-5M13 17V8M18 17v-8"/>',
    'media'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/>',
    'player'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'club'    => '<path d="M12 3 4 6v6c0 4.5 3.2 8 8 9 4.8-1 8-4.5 8-9V6z"/>',
    'flag'    => '<path d="M4 22V4"/><path d="M4 4h13l-2 4 2 4H4"/>',
    'lineup'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 12h18"/><circle cx="12" cy="12" r="2.5"/><path d="M9 3v3h6V3M9 21v-3h6v3"/>',
];
$__svg = function ($k) use ($__icons) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $__icons[$k] . '</svg>';
};

$__stats_files = ['club_player_statistics.php', 'club_player_performance.php', 'club_performance.php', 'club_player_combination.php'];

if ($nav_mode === 'admin') {
    $tabs = [
        ['Fixtures',    'season.php',            ['season.php', 'fixture_details.php'], 'matches'],
        ['Players',     'insert_players.php',    ['insert_players.php'],                'squad'],
        ['Lineup',      'lineup_admin.php',      ['lineup_admin.php'],                  'lineup'],
        ['Club info',   'league_table.php',      ['league_table.php'],                  'club'],
        ['Player stats','player_stats.php',      ['player_stats.php'],                  'stats'],
        ['Media',       'videos.php',            ['videos.php'],                        'media'],
    ];
} elseif ($nav_mode === 'global') {
    // Admin pages that work across every season (no season id in the links)
    $tabs = [
        ['Seasons',        'home.php',           ['home.php'],                                  'club',   true],
        ['Player history', 'player_history.php', ['player_history.php', 'player_profile.php'],  'player', true],
        ['Nationality',    'players_by_nationality.php', ['players_by_nationality.php'],            'flag',   true],
    ];
} elseif ($nav_mode === 'public') {
    $tabs = [
        ['Squad',   'club.php',                    ['club.php', 'player_opponents.php'], 'squad'],
        ['Lineup',  'lineup.php',                  ['lineup.php'],              'lineup'],
        ['Matches', 'club_fixtures.php',           ['club_fixtures.php'],       'matches'],
        ['Stats',   'club_player_statistics.php',  $__stats_files,              'stats'],
        ['Media',   'club_media.php',              ['club_media.php'],          'media'],
    ];
} else {
    $tabs = [];
}
?>
<header class="topbar<?php echo $__admin_ui ? ' is-admin' : ''; ?>">
  <div class="topbar-in">
    <a class="brand" href="index.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3v18"/><circle cx="12" cy="12" r="2.6"/></svg>
      eFootball League
    </a>
    <?php if ($nav_mode !== 'landing' && $nav_mode !== 'global' && $__club !== ''): ?>
      <span class="brand-club"><?php echo h($__club); ?></span>
    <?php endif; ?>

    <?php if ($tabs): ?>
    <nav class="tabs" aria-label="Main">
      <?php foreach ($tabs as $t): ?>
        <a href="<?php echo $t[1] . (empty($t[4]) ? $__q : ''); ?>"<?php echo in_array($__file, $t[2], true) ? ' aria-current="page"' : ''; ?>><?php echo h($t[0]); ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <div class="topbar-right">
      <?php if ($nav_mode === 'admin'): ?>
        <?php if ($__sid): ?><a class="topbar-link hide-sm" href="club.php?id=<?php echo $__sid; ?>">Public site</a><?php endif; ?>
        <!-- <a class="topbar-link hide-sm" href="player_history.php">Player history</a> -->
        <a class="topbar-link hide-sm" href="home.php">All seasons</a>
        <a class="topbar-link is-outline" href="logout.php">Log out</a>
      <?php elseif ($nav_mode === 'global' || $__file === 'home.php'): ?>
        <a class="topbar-link hide-sm" href="index.php">Public site</a>
        <a class="topbar-link is-outline" href="logout.php">Log out</a>
      <?php elseif ($__admin): ?>
        <a class="topbar-link hide-sm" href="home.php">Admin dashboard</a>
        <a class="topbar-link is-outline" href="logout.php">Log out</a>
      <?php else: ?>
        <a class="topbar-link is-outline" href="admin_login.php">Admin login</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php if ($__admin_ui): ?>
  <div class="admin-strip">Admin mode: changes you make here appear on the public site.</div>
<?php endif; ?>

<?php if ($nav_mode === 'public' && in_array($__file, $__stats_files, true)): ?>
  <nav class="subnav" aria-label="Statistics">
    <div class="subnav-in">
      <?php
      $sub = [
          ['Top players',        'club_player_statistics.php'],
          ['All players',        'club_player_performance.php'],
          ['Club performance',   'club_performance.php'],
          ['Player combinations','club_player_combination.php'],
      ];
      foreach ($sub as $s): ?>
        <a href="<?php echo $s[1] . $__q; ?>"<?php echo $__file === $s[1] ? ' aria-current="page"' : ''; ?>><?php echo h($s[0]); ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
<?php endif; ?>

<?php if ($tabs): ?>
<nav class="tabbar" aria-label="Main (mobile)">
  <?php foreach ($tabs as $t): ?>
    <a href="<?php echo $t[1] . (empty($t[4]) ? $__q : ''); ?>"<?php echo in_array($__file, $t[2], true) ? ' aria-current="page"' : ''; ?>>
      <?php echo $__svg($t[3]); ?><?php echo h($t[0]); ?>
    </a>
  <?php endforeach; ?>
</nav>
<script>document.body.classList.add('has-tabbar');</script>
<?php endif; ?>