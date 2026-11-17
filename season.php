<?php
session_start();
include 'db.php';

if (!isset($_GET['id'])) die("Season ID not provided.");
$season_id = (int)$_GET['id'];
include 'admin_sessioncheck.php';

// Fetch season info
$season = mysqli_query($conn, "SELECT * FROM seasons WHERE id=$season_id");
$seasonData = mysqli_fetch_assoc($season);
if (!$seasonData) die("Season not found.");

$_SESSION['club_id'] = $season_id;
$_SESSION['club_name'] = $seasonData['club'];

// Handle fixture creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_fixture'])) {
    $home = trim(mysqli_real_escape_string($conn, $_POST['home_team']));
    $away = trim(mysqli_real_escape_string($conn, $_POST['away_team']));

    mysqli_query($conn, "INSERT INTO fixtures (season_id, home_team, away_team) 
                         VALUES ('$season_id','$home','$away')");
    header("Location: season.php?id=$season_id");
    exit;
}

// Handle result submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['score_submit'])) {
    $fixture_id = (int)$_POST['fixture_id'];
    $home_score = (int)$_POST['home_score'];
    $away_score = (int)$_POST['away_score'];

    mysqli_query($conn, "UPDATE fixtures 
                         SET home_score='$home_score', away_score='$away_score', status='Match Completed' 
                         WHERE id='$fixture_id'");
    header("Location: season.php?id=$season_id");
    exit;
}

// Handle fixture deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_fixture'])) {
    $fixture_id = (int)$_POST['delete_fixture_id'];
    mysqli_query($conn, "DELETE FROM fixtures WHERE id='$fixture_id' AND season_id='$season_id'");
    mysqli_query($conn, "ALTER TABLE fixtures AUTO_INCREMENT = 1");
    header("Location: season.php?id=$season_id");
    exit;
}

// Fetch existing fixtures
$fixtures = mysqli_query($conn, "SELECT * FROM fixtures WHERE season_id=$season_id");
?>
<?php
$page_title = $seasonData['title'] . ' | Fixtures | Admin';
$nav_mode = 'admin';
$fixture_total = mysqli_num_rows($fixtures);
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
      <h1><?php echo h($seasonData['title']); ?></h1>
      <p class="page-sub"><?php echo !empty($seasonData['club']) ? h($seasonData['club']) . ': ' : ''; ?>add fixtures, enter results and open a match to record scorers.</p>
    </div>
    <div class="page-actions">
      <button class="btn btn-yellow" type="button" onclick="toggleForm('fixtureForm')">+ Create fixture</button>
    </div>
  </div>

  <div id="fixtureForm" class="panel" hidden style="margin-bottom:22px">
    <form method="POST" action="season.php?id=<?php echo $season_id; ?>" class="form-grid">
      <div class="field"><label for="home_team">Home team</label><input type="text" id="home_team" name="home_team" required></div>
      <div class="field"><label for="away_team">Away team</label><input type="text" id="away_team" name="away_team" required></div>
      <div class="field" style="align-self:end"><button class="btn" type="submit" name="create_fixture">Add fixture</button></div>
    </form>
  </div>

  <?php if ($fixture_total === 0) { ?>
    <div class="empty">
      <h3>No fixtures yet</h3>
      <p>Create the first fixture to start the season.</p>
      <button class="btn" type="button" onclick="toggleForm('fixtureForm')">+ Create fixture</button>
    </div>
  <?php } else { ?>

  <div class="toolbar">
    <div class="group">
      <span class="label muted" id="fLabel">Show</span>
      <div class="seg" id="resultFilter" role="group" aria-labelledby="fLabel">
        <button type="button" data-value="All" aria-pressed="true">All</button>
        <button type="button" data-value="Win" aria-pressed="false">Wins</button>
        <button type="button" data-value="Draw" aria-pressed="false">Draws</button>
        <button type="button" data-value="Loss" aria-pressed="false">Losses</button>
      </div>
    </div>
  </div>

  <div class="fx-list">
  <?php
    $matchday = 1;
    while($row = mysqli_fetch_assoc($fixtures)) {
        $outcome = "Pending";
        if ($row['status'] === 'Match Completed') {
            $club = $_SESSION['club_name'];
            if ($row['home_team'] === $club || $row['away_team'] === $club) {
                if ($row['home_score'] == $row['away_score']) {
                    $outcome = "Draw";
                } else if (
                    ($row['home_team'] === $club && $row['home_score'] > $row['away_score']) ||
                    ($row['away_team'] === $club && $row['away_score'] > $row['home_score'])
                ) {
                    $outcome = "Win";
                } else {
                    $outcome = "Loss";
                }
            }
        }

        // Stats-entered check
        $stats_check = mysqli_query($conn, "
            SELECT COUNT(*) as total_goals,
                   SUM(CASE WHEN status='Stats entered' THEN 1 ELSE 0 END) as entered_stats
            FROM goal_stats 
            WHERE fixture_id=".$row['id']
        );
        $statsData = mysqli_fetch_assoc($stats_check);
        $showTick = false;
        if ($statsData['total_goals'] > 0 && $statsData['total_goals'] == $statsData['entered_stats']) {
            $showTick = true;
        }
        $done = ($row['status'] === 'Match Completed');
        $hs = $row['home_score']; $as = $row['away_score'];
        $cls = ($done && $outcome !== 'Pending') ? ' r-' . strtolower($outcome) : '';
  ?>
    <article class="fx<?php echo $cls; ?>" id="fixture-<?php echo $row['id']; ?>" data-outcome="<?php echo $outcome; ?>">
      <div class="fx-top">
        <span class="md">Matchday <?php echo $matchday++; ?></span>
        <?php if ($done && $outcome !== 'Pending'): ?><span class="badge badge-<?php echo strtolower($outcome); ?>"><?php echo $outcome; ?></span><?php endif; ?>
        <?php if ($showTick): ?><span class="badge badge-ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>Stats entered</span><?php endif; ?>
        <span class="badge <?php echo $done ? 'badge-ft' : 'badge-up'; ?>"><?php echo h($row['status'] ?: 'Upcoming'); ?></span>
      </div>

      <div class="board">
        <div class="team home"><?php echo crest($row['home_team']); ?><span class="name"><?php echo h($row['home_team']); ?></span></div>
        <?php if ($done): ?>
          <div class="score"><span class="<?php echo $hs < $as ? 'dim' : ''; ?>"><?php echo (int)$hs; ?></span><span class="sep">–</span><span class="<?php echo $as < $hs ? 'dim' : ''; ?>"><?php echo (int)$as; ?></span></div>
        <?php else: ?>
          <div class="score is-upcoming">vs</div>
        <?php endif; ?>
        <div class="team away"><?php echo crest($row['away_team']); ?><span class="name"><?php echo h($row['away_team']); ?></span></div>
      </div>

      <?php if(!$done){ ?>
        <div class="fx-actions">
          <button class="btn btn-sm" type="button" onclick="toggleForm('resultForm-<?php echo $row['id']; ?>')">Enter result</button>
        </div>
        <div id="resultForm-<?php echo $row['id']; ?>" class="inline-form" hidden>
          <form method="POST" action="">
            <input type="hidden" name="fixture_id" value="<?php echo $row['id']; ?>">
            <input type="number" name="home_score" placeholder="Home" aria-label="Home score" min="0" required>
            <span class="muted">to</span>
            <input type="number" name="away_score" placeholder="Away" aria-label="Away score" min="0" required>
            <button class="btn btn-sm" type="submit" name="score_submit">Save result</button>
          </form>
        </div>
      <?php } else { ?>
        <div class="fx-actions">
          <a class="btn btn-ghost btn-sm" href="fixture_details.php?id=<?php echo $row['id']; ?>">Match details</a>
          <form method="POST" action="" data-confirm="Delete this fixture? This can't be undone.">
            <input type="hidden" name="delete_fixture_id" value="<?php echo $row['id']; ?>">
            <button class="btn btn-danger btn-sm" type="submit" name="delete_fixture">Delete</button>
          </form>
        </div>
      <?php } ?>
    </article>
  <?php } ?>
  </div>
  <div class="empty" id="noMatch" hidden><h3>No fixtures match this filter</h3><p>Choose another result to see more.</p></div>
  <?php } ?>
</main>

<script>
function toggleForm(id){
    var f = document.getElementById(id);
    f.hidden = !f.hidden;
    if (!f.hidden) { var i = f.querySelector('input'); if (i) i.focus(); }
}
window.addEventListener('DOMContentLoaded', function () {
    var group = document.getElementById('resultFilter');
    if (!group) return;
    segGroup(group, function (filter) {
        var shown = 0;
        document.querySelectorAll('.fx').forEach(function (fixture) {
            var outcome = fixture.getAttribute('data-outcome');
            var ok = (filter === 'All' || outcome === filter);
            fixture.hidden = !ok;
            if (ok) shown++;
        });
        document.getElementById('noMatch').hidden = shown !== 0;
    });
});
</script>
</body>
</html>
