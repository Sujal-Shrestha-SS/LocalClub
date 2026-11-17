<?php
session_start();
include 'db.php';

$season_id = $_SESSION['club_id']; // Current season
$club_name = $_SESSION['club_name'];

// --- Fetch Fixtures ---
$sql_fixtures = "
    SELECT id, home_team, away_team, home_score, away_score, status
    FROM fixtures
    WHERE season_id = '$season_id'
    ORDER BY id ASC
";
$result_fixtures = mysqli_query($conn, $sql_fixtures);

$fixtures = [];
if ($result_fixtures && mysqli_num_rows($result_fixtures) > 0) {
    while ($row = mysqli_fetch_assoc($result_fixtures)) {
        // Determine match result from perspective of club
        if ($row['home_team'] === $club_name) {
            $row['result'] = ($row['home_score'] > $row['away_score']) ? 'win' :
                             (($row['home_score'] == $row['away_score']) ? 'draw' : 'loss');
            $row['venue'] = 'home';
        } elseif ($row['away_team'] === $club_name) {
            $row['result'] = ($row['away_score'] > $row['home_score']) ? 'win' :
                             (($row['away_score'] == $row['home_score']) ? 'draw' : 'loss');
            $row['venue'] = 'away';
        } else {
            $row['result'] = '';
            $row['venue'] = '';
        }
        $fixtures[] = $row;
    }
}

// --- Fetch Goal Stats grouped by fixture_id ---
$sql_goal_stats = "
    SELECT fixture_id, goal_scorer, assist_provider, team
    FROM goal_stats
    WHERE fixture_id IN (
        SELECT id FROM fixtures WHERE season_id = '$season_id'
    )
    ORDER BY fixture_id ASC, id ASC
";
$result_goal_stats = mysqli_query($conn, $sql_goal_stats);
$goal_stats = [];
if ($result_goal_stats && mysqli_num_rows($result_goal_stats) > 0) {
    while ($row = mysqli_fetch_assoc($result_goal_stats)) {
        $goal_stats[$row['fixture_id']][] = [
            'scorer' => $row['goal_scorer'],
            'assist' => $row['assist_provider'],
            'team'   => $row['team']
        ];
    }
}
?>
<?php
$page_title = $club_name . ' | Matches';
$nav_mode = 'public';
$played_count = 0;
foreach ($fixtures as $__f) { if ($__f['status'] === 'Match Completed' || strtolower($__f['status']) === 'finished') $played_count++; }
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
      <h1>Matches</h1>
      <p class="page-sub"><?php echo h($club_name); ?>: <?php echo $played_count; ?> played, <?php echo count($fixtures) - $played_count; ?> to play.</p>
    </div>
  </div>

  <?php if (!empty($fixtures)): ?>
  <div class="toolbar">
    <div class="group">
      <span class="label muted" id="venueLabel">Venue</span>
      <div class="seg" id="venueFilter" role="group" aria-labelledby="venueLabel">
        <button type="button" data-value="all" aria-pressed="true">All</button>
        <button type="button" data-value="home" aria-pressed="false">Home</button>
        <button type="button" data-value="away" aria-pressed="false">Away</button>
      </div>
    </div>
    <div class="group">
      <span class="label muted" id="resultLabel">Result</span>
      <div class="seg" id="resultFilter" role="group" aria-labelledby="resultLabel">
        <button type="button" data-value="all" aria-pressed="true">All</button>
        <button type="button" data-value="win" aria-pressed="false">Wins</button>
        <button type="button" data-value="draw" aria-pressed="false">Draws</button>
        <button type="button" data-value="loss" aria-pressed="false">Losses</button>
      </div>
    </div>
  </div>

  <div class="fx-list">
    <?php $matchday = 1; ?>
    <?php foreach ($fixtures as $fixture): ?>
      <?php
        $fixture_id = $fixture['id'];
        $done = ($fixture['status'] === 'Match Completed' || strtolower($fixture['status']) === 'finished');
        $home_goals = $away_goals = [];
        if (!empty($goal_stats[$fixture_id])) {
            foreach ($goal_stats[$fixture_id] as $goal) {
                if (isset($goal['team']) && $goal['team'] === "Home") $home_goals[] = $goal;
                else $away_goals[] = $goal;
            }
        }
        $hs = $fixture['home_score']; $as = $fixture['away_score'];
        $cls = ($done && $fixture['result']) ? ' r-' . $fixture['result'] : '';
      ?>
      <article class="fx fixture<?php echo $cls; ?>"
               data-result="<?php echo h($fixture['result']); ?>"
               data-venue="<?php echo h($fixture['venue']); ?>">
        <div class="fx-top">
          <span class="md">Matchday <?php echo $matchday++; ?></span>
          <?php if ($done && $fixture['result']): ?>
            <span class="badge badge-<?php echo h($fixture['result']); ?>"><?php echo ['win' => 'Win', 'draw' => 'Draw', 'loss' => 'Loss'][$fixture['result']]; ?></span>
          <?php endif; ?>
          <span class="badge <?php echo $done ? 'badge-ft' : 'badge-up'; ?>"><?php echo $done ? 'Full time' : 'Upcoming'; ?></span>
        </div>

        <div class="board">
          <div class="team home"><?php echo crest($fixture['home_team']); ?><span class="name"><?php echo h($fixture['home_team']); ?></span></div>
          <?php if ($done && $hs !== null && $as !== null): ?>
            <div class="score" aria-label="<?php echo (int)$hs; ?> to <?php echo (int)$as; ?>">
              <span class="<?php echo $hs < $as ? 'dim' : ''; ?>"><?php echo (int)$hs; ?></span>
              <span class="sep">–</span>
              <span class="<?php echo $as < $hs ? 'dim' : ''; ?>"><?php echo (int)$as; ?></span>
            </div>
          <?php else: ?>
            <div class="score is-upcoming">vs</div>
          <?php endif; ?>
          <div class="team away"><?php echo crest($fixture['away_team']); ?><span class="name"><?php echo h($fixture['away_team']); ?></span></div>
        </div>

        <?php if ($home_goals || $away_goals): ?>
        <div class="fx-goals">
          <ul class="goal-list home">
            <?php foreach ($home_goals as $goal): ?>
              <li><?php echo h($goal['scorer']); ?><?php echo ball_icon(); ?><?php if (!empty($goal['assist'])): ?><br><span class="assist">Assist: <?php echo h($goal['assist']); ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
          <ul class="goal-list away">
            <?php foreach ($away_goals as $goal): ?>
              <li><?php echo ball_icon(); ?><?php echo h($goal['scorer']); ?><?php if (!empty($goal['assist'])): ?><br><span class="assist">Assist: <?php echo h($goal['assist']); ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <div class="empty" id="noMatch" hidden>
    <h3>No matches fit these filters</h3>
    <p>Change the venue or result filter to see more.</p>
  </div>
  <?php else: ?>
    <div class="empty">
      <h3>No fixtures yet</h3>
      <p>Matches will show up here once they're scheduled.</p>
    </div>
  <?php endif; ?>
</main>

<script>
(function () {
  var venue = 'all', result = 'all';
  function filterFixtures() {
    var shown = 0;
    document.querySelectorAll('.fixture').forEach(function (fix) {
      var show = !(venue !== 'all' && fix.getAttribute('data-venue') !== venue) &&
                 !(result !== 'all' && fix.getAttribute('data-result') !== result);
      fix.hidden = !show;
      if (show) shown++;
    });
    var none = document.getElementById('noMatch');
    if (none) none.hidden = shown !== 0;
  }
  window.addEventListener('DOMContentLoaded', function () {
    var v = document.getElementById('venueFilter'), r = document.getElementById('resultFilter');
    if (!v || !r) return;
    segGroup(v, function (x) { venue = x; filterFixtures(); });
    segGroup(r, function (x) { result = x; filterFixtures(); });
  });
})();
</script>
</body>
</html>
