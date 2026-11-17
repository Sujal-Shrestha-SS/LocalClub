<?php
session_start();
include 'db.php';

include 'admin_sessioncheck.php';
include 'player_career_data.php';

$careers = get_all_player_careers($conn);

// Sort by G/A desc, then goals desc, then name asc
uasort($careers, function ($a, $b) {
    if ($a['total_ga'] !== $b['total_ga']) return $b['total_ga'] <=> $a['total_ga'];
    if ($a['total_goals'] !== $b['total_goals']) return $b['total_goals'] <=> $a['total_goals'];
    return strcasecmp($a['display_name'], $b['display_name']);
});

$page_title = 'Player history | Admin';
$nav_mode = 'global';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page">
  <div class="page-head">
    <div>
      <h1>Player history</h1>
      <p class="page-sub">Career totals across every season on record. Open a player to see season by season.</p>
    </div>
  </div>

  <?php if (!empty($careers)): ?>
  <div class="toolbar">
    <div class="group">
      <label for="playerSearch" class="sr-only">Search players</label>
      <input class="input search" type="search" id="playerSearch" placeholder="Search player name" autocomplete="off">
    </div>
    <div class="group">
      <span class="label muted" id="posLabel">Position</span>
      <div class="seg" id="positionFilter" role="group" aria-labelledby="posLabel">
        <button type="button" data-value="all" aria-pressed="true">All</button>
        <button type="button" data-value="Goalkeeper" aria-pressed="false">Goalkeepers</button>
        <button type="button" data-value="Defender" aria-pressed="false">Defenders</button>
        <button type="button" data-value="Midfielder" aria-pressed="false">Midfielders</button>
        <button type="button" data-value="Forward" aria-pressed="false">Forwards</button>
      </div>
    </div>
    <span class="result-count" id="resultCount" aria-live="polite"></span>
  </div>

  <div class="player-grid" id="playerGrid">
    <?php foreach ($careers as $key => $c): ?>
      <a class="pcard career-card"
         data-name="<?php echo h(strtolower($c['display_name'])); ?>"
         data-category="<?php echo h($c['category']); ?>"
         href="player_profile.php?player=<?php echo urlencode($key); ?>">
        <div class="photo">
          <img class="pic" src="<?php echo h($c['picture'] ?: 'uploads/default.png'); ?>" alt="" loading="lazy">
          <?php if (!empty($c['flag_picture'])): ?>
            <img class="flag-chip" src="<?php echo h($c['flag_picture']); ?>" alt="<?php echo h($c['nationality']); ?> flag">
          <?php endif; ?>
        </div>
        <div class="meta">
          <div class="nm"><?php echo h($c['display_name']); ?></div>
          <div><span class="pos pos-<?php echo h($c['category']); ?>"><?php echo h($c['category']); ?></span></div>
          <div class="emo">
            <span title="Goals"><i aria-hidden="true">&#9917;</i><?php echo (int)$c['total_goals']; ?><span class="sr-only"> goals</span></span>
            <span title="Assists"><i aria-hidden="true">&#127919;</i><?php echo (int)$c['total_assists']; ?><span class="sr-only"> assists</span></span>
            <span title="Goals + assists"><i aria-hidden="true">&#11088;</i><?php echo (int)$c['total_ga']; ?><span class="sr-only"> goals plus assists</span></span>
          </div>
          <div class="sub">
            <?php echo (int)$c['seasons_count']; ?> season<?php echo $c['seasons_count'] == 1 ? '' : 's'; ?>,
            <?php echo count($c['clubs_list']); ?> club<?php echo count($c['clubs_list']) == 1 ? '' : 's'; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="empty" id="noMatch" hidden>
    <h3>No players match</h3>
    <p>Try a different name or position.</p>
  </div>
  <?php else: ?>
    <div class="empty">
      <h3>No players on record</h3>
      <p>Add players to a season and they'll appear here with their career totals.</p>
    </div>
  <?php endif; ?>
</main>

<script>
(function () {
  var position = 'all';
  var search = document.getElementById('playerSearch');
  if (!search) return;
  var cards = document.querySelectorAll('#playerGrid .career-card');

  function applyFilters() {
    var term = search.value.trim().toLowerCase();
    var shown = 0;
    cards.forEach(function (card) {
      var ok = card.dataset.name.indexOf(term) !== -1 &&
               (position === 'all' || card.dataset.category === position);
      card.hidden = !ok;
      if (ok) shown++;
    });
    document.getElementById('resultCount').textContent = shown + (shown === 1 ? ' player' : ' players');
    document.getElementById('noMatch').hidden = shown !== 0;
  }

  window.addEventListener('DOMContentLoaded', function () {
    segGroup(document.getElementById('positionFilter'), function (v) { position = v; applyFilters(); });
    search.addEventListener('input', applyFilters);
    applyFilters();
  });
})();
</script>
</body>
</html>
