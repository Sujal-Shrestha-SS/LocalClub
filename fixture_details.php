<?php
session_start();

include 'db.php';
$season_id = $_SESSION['club_id'];
if (!isset($_GET['id'])) die("Fixture ID missing.");
$fixture_id = (int)$_GET['id'];

include 'admin_sessioncheck.php';

// Fetch fixture info
$fixture = mysqli_query($conn, "SELECT * FROM fixtures WHERE id=$fixture_id");
$fixtureData = mysqli_fetch_assoc($fixture);
if (!$fixtureData) die("Fixture not found.");

// Handle fixture info update
if (isset($_POST['update_fixture'])) {
    $home_team = trim(mysqli_real_escape_string($conn, $_POST['home_team']));
    $away_team = trim(mysqli_real_escape_string($conn, $_POST['away_team']));
    $status = trim(mysqli_real_escape_string($conn, $_POST['status']));

    mysqli_query($conn, "UPDATE fixtures 
                         SET home_team='$home_team', away_team='$away_team' 
                         WHERE id='$fixture_id'");
    header("Location: fixture_details.php?id=$fixture_id");
    exit;
}

// Handle result update
if (isset($_POST['update_result'])) {
    $home_score = (int)$_POST['home_score'];
    $away_score = (int)$_POST['away_score'];
    mysqli_query($conn, "UPDATE fixtures 
                         SET home_score='$home_score', away_score='$away_score', status='Match Completed' 
                         WHERE id='$fixture_id'");
    header("Location: fixture_details.php?id=$fixture_id");
    exit;
}

// Handle initial goal/assist stats insertion
if (isset($_POST['submit_stats'])) {
    $total_goals = (int)$_POST['total_goals'];
    for ($i = 1; $i <= $total_goals; $i++) {
        $goal_scorer = trim(mysqli_real_escape_string($conn, $_POST["goal_scorer_$i"]));
        $assist_provider = trim(mysqli_real_escape_string($conn, $_POST["assist_provider_$i"]));
        $team = trim(mysqli_real_escape_string($conn, $_POST["team_$i"]));

        mysqli_query($conn, "INSERT INTO goal_stats (fixture_id, team, goal_scorer, assist_provider, season_id, status) 
                             VALUES ('$fixture_id','$team','$goal_scorer','$assist_provider' , '$season_id', 'Stats entered' )");
    }
    header("Location: fixture_details.php?id=$fixture_id");
    exit;
}

// Handle update of existing stats
if (isset($_POST['update_stats'])) {
    $stats_id_array = $_POST['stats_id']; // array of stat ids
    foreach($stats_id_array as $id) {
        $goal_scorer = trim(mysqli_real_escape_string($conn, $_POST["goal_scorer_$id"]));
        $assist_provider = trim(mysqli_real_escape_string($conn, $_POST["assist_provider_$id"]));
        $team = trim(mysqli_real_escape_string($conn, $_POST["team_$id"]));

        mysqli_query($conn, "UPDATE goal_stats 
                             SET goal_scorer='$goal_scorer', assist_provider='$assist_provider', team='$team'
                             WHERE id='$id'");
    }
    header("Location: fixture_details.php?id=$fixture_id");
    exit;
}

// Handle deletion of all player stats for this fixture
if (isset($_POST['delete_players'])) {
    mysqli_query($conn, "DELETE FROM goal_stats WHERE fixture_id=$fixture_id");
     // Reset AUTO_INCREMENT to fill deleted IDs
        mysqli_query($conn, "ALTER TABLE goal_stats AUTO_INCREMENT = 1");
    header("Location: fixture_details.php?id=$fixture_id");
    exit;
}

// Fetch existing stats for display
$stats = mysqli_query($conn, "SELECT * FROM goal_stats WHERE fixture_id=$fixture_id");
$existing_stats_count = mysqli_num_rows($stats);

// Total goals
$total_goals = $fixtureData['home_score'] + $fixtureData['away_score'];
?>
<?php
$page_title = $fixtureData['home_team'] . ' v ' . $fixtureData['away_team'] . ' | Match details';
$nav_mode = 'admin';
$stat_rows = [];
mysqli_data_seek($stats, 0);
while ($s = mysqli_fetch_assoc($stats)) { $stat_rows[] = $s; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page page-narrow stack" style="--gap:24px">
  <div>
    <a class="back-link" href="season.php?id=<?php echo $season_id; ?>#fixture-<?php echo $fixture_id; ?>">← Back to fixtures</a>
    <div class="panel" style="padding:28px 20px">
      <div class="board">
        <div class="team home"><?php echo crest($fixtureData['home_team'], 'lg'); ?><span class="name"><?php echo h($fixtureData['home_team']); ?></span></div>
        <div class="score lg"><span><?php echo (int)$fixtureData['home_score']; ?></span><span class="sep">–</span><span><?php echo (int)$fixtureData['away_score']; ?></span></div>
        <div class="team away"><?php echo crest($fixtureData['away_team'], 'lg'); ?><span class="name"><?php echo h($fixtureData['away_team']); ?></span></div>
      </div>
    </div>
  </div>

  <section class="panel">
    <div class="panel-head"><h2>Teams</h2></div>
    <form method="POST" class="form-grid">
      <div class="field"><label for="home_team">Home team</label><input type="text" id="home_team" name="home_team" value="<?php echo h($fixtureData['home_team']); ?>" required></div>
      <div class="field"><label for="away_team">Away team</label><input type="text" id="away_team" name="away_team" value="<?php echo h($fixtureData['away_team']); ?>" required></div>
      <div class="field span-all"><div><button class="btn" type="submit" name="update_fixture">Save teams</button></div></div>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Result</h2></div>
    <form method="POST" class="form-grid">
      <div class="field"><label for="home_score">Home score</label><input type="number" id="home_score" name="home_score" min="0" value="<?php echo h($fixtureData['home_score']); ?>" required></div>
      <div class="field"><label for="away_score">Away score</label><input type="number" id="away_score" name="away_score" min="0" value="<?php echo h($fixtureData['away_score']); ?>" required></div>
      <div class="field span-all"><div><button class="btn" type="submit" name="update_result">Save result</button></div></div>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Scorers and assists</h2></div>
    <?php if ($total_goals < 1 && $existing_stats_count < 1): ?>
      <p class="muted" style="margin:0">Enter a result with at least one goal to record scorers and assists.</p>
    <?php elseif ($existing_stats_count > 0): ?>
      <form method="POST" class="stack" style="--gap:12px">
        <?php $n = 1; foreach ($stat_rows as $s): $id = $s['id']; ?>
          <div class="form-grid" style="grid-template-columns: 60px repeat(auto-fit, minmax(140px, 1fr)); align-items:center; margin-top:12px">
            <strong class="muted">Goal <?php echo $n++; ?></strong>
            <input type="text" name="goal_scorer_<?php echo $id; ?>" value="<?php echo h($s['goal_scorer']); ?>" placeholder="Scorer" aria-label="Goal scorer">
            <input type="text" name="assist_provider_<?php echo $id; ?>" value="<?php echo h($s['assist_provider']); ?>" placeholder="Assist" aria-label="Assist provider">
            <select name="team_<?php echo $id; ?>" aria-label="Team">
              <option value="Home" <?php if($s['team']=="Home") echo "selected"; ?>>Home</option>
              <option value="Away" <?php if($s['team']=="Away") echo "selected"; ?>>Away</option>
            </select>
            <input type="hidden" name="stats_id[]" value="<?php echo $id; ?>">
          </div>
        <?php endforeach; ?>
        <div class="btn-row" style="margin-top:20px">
          <button class="btn" type="submit" name="update_stats">Save scorers</button>
          <button class="btn btn-danger" type="submit" name="delete_players" onclick="return confirm('Delete every recorded scorer and assist for this match?');">Delete all scorers</button>
        </div>
      </form>
    <?php else: ?>
      <form method="POST">
        <input type="hidden" name="total_goals" value="<?php echo $total_goals; ?>">
        <?php for($i=1; $i<=$total_goals; $i++){ ?>
          <div class="form-grid" style="grid-template-columns: 60px repeat(auto-fit, minmax(140px, 1fr)); align-items:center; margin-bottom:12px">
            <strong class="muted">Goal <?php echo $i; ?></strong>
            <input type="text" name="goal_scorer_<?php echo $i; ?>" placeholder="Scorer" aria-label="Goal scorer <?php echo $i; ?>">
            <input type="text" name="assist_provider_<?php echo $i; ?>" placeholder="Assist" aria-label="Assist provider <?php echo $i; ?>">
            <select name="team_<?php echo $i; ?>" aria-label="Team for goal <?php echo $i; ?>">
              <option value="">Select team</option>
              <option value="Home">Home</option>
              <option value="Away">Away</option>
            </select>
          </div>
        <?php } ?>
        <button class="btn" type="submit" name="submit_stats">Save scorers</button>
      </form>
    <?php endif; ?>
  </section>

  <section>
    <h2 style="margin-bottom:14px">Recorded goals</h2>
    <div class="grid-2">
      <?php foreach (['Home' => $fixtureData['home_team'], 'Away' => $fixtureData['away_team']] as $side => $team): ?>
        <div class="panel">
          <div class="panel-head" style="margin-bottom:10px"><h3><?php echo h($team); ?></h3></div>
          <?php $found = false; ?>
          <ul class="goal-list" style="gap:8px">
            <?php foreach ($stat_rows as $s): if ($s['team'] == $side): $found = true; ?>
              <li><?php echo ball_icon(); ?><strong><?php echo h($s['goal_scorer']); ?></strong><?php if (!empty($s['assist_provider'])): ?> <span class="assist">assist: <?php echo h($s['assist_provider']); ?></span><?php endif; ?></li>
            <?php endif; endforeach; ?>
          </ul>
          <?php if (!$found): ?><p class="muted" style="margin:0">No goals recorded yet.</p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</body>
</html>
