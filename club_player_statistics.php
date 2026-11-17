<?php
session_start();
include 'db.php';

$season_id = $_SESSION['club_id']; // Current season

// --- Fetch Goals ---
$sql_goals = "
    SELECT p.name, p.picture, COALESCE(SUM(g.goals),0) AS total_goals
    FROM players p
    LEFT JOIN (
        SELECT goal_scorer AS player, COUNT(*) AS goals
        FROM goal_stats g
        JOIN fixtures f ON g.fixture_id = f.id
        WHERE f.season_id = '$season_id'
        GROUP BY goal_scorer
    ) g ON p.name = g.player
    WHERE p.season_id='$season_id'
    GROUP BY p.id, p.name, p.picture
    ORDER BY total_goals DESC
";
$result_goals = mysqli_query($conn, $sql_goals);

$players_goals = [];
if ($result_goals && mysqli_num_rows($result_goals) > 0) {
    while($row = mysqli_fetch_assoc($result_goals)) {
        $players_goals[] = ['name'=>$row['name'], 'picture'=>$row['picture'], 'goals'=>$row['total_goals']];
    }
}

// --- Fetch Assists ---
$sql_assists = "
    SELECT p.name, p.picture, COALESCE(SUM(a.assists),0) AS total_assists
    FROM players p
    LEFT JOIN (
        SELECT assist_provider AS player, COUNT(*) AS assists
        FROM goal_stats g
        JOIN fixtures f ON g.fixture_id = f.id
        WHERE f.season_id = '$season_id'
        GROUP BY assist_provider
    ) a ON p.name = a.player
    WHERE p.season_id='$season_id'
    GROUP BY p.id, p.name, p.picture
    ORDER BY total_assists DESC
";
$result_assists = mysqli_query($conn, $sql_assists);

$players_assists = [];
if ($result_assists && mysqli_num_rows($result_assists) > 0) {
    while($row = mysqli_fetch_assoc($result_assists)) {
        $players_assists[] = ['name'=>$row['name'], 'picture'=>$row['picture'], 'assists'=>$row['total_assists']];
    }
}

// --- Fetch G/A (Goals + Assists) ---
$sql_ga = "
    SELECT p.name, p.picture,
           COALESCE(SUM(g.goals),0) AS total_goals,
           COALESCE(SUM(a.assists),0) AS total_assists,
           (COALESCE(SUM(g.goals),0) + COALESCE(SUM(a.assists),0)) AS ga
    FROM players p
    LEFT JOIN (
        SELECT goal_scorer AS player, COUNT(*) AS goals
        FROM goal_stats g
        JOIN fixtures f ON g.fixture_id = f.id
        WHERE f.season_id = '$season_id'
        GROUP BY goal_scorer
    ) g ON p.name = g.player
    LEFT JOIN (
        SELECT assist_provider AS player, COUNT(*) AS assists
        FROM goal_stats g
        JOIN fixtures f ON g.fixture_id = f.id
        WHERE f.season_id = '$season_id'
        GROUP BY assist_provider
    ) a ON p.name = a.player
    WHERE p.season_id='$season_id'
    GROUP BY p.id, p.name, p.picture
    ORDER BY ga DESC, total_goals DESC
";
$result_ga = mysqli_query($conn, $sql_ga);

$players_ga = [];
if ($result_ga && mysqli_num_rows($result_ga) > 0) {
    while($row = mysqli_fetch_assoc($result_ga)) {
        $players_ga[] = ['name'=>$row['name'], 'picture'=>$row['picture'], 'ga'=>$row['ga']];
    }
}
?>
<?php
$page_title = ($_SESSION['club_name'] ?? '') . ' | Top players';
$nav_mode = 'public';

function lb_panel($id, $rows, $key, $unit, $hidden) {
    $max = 0;
    foreach ($rows as $r) { if ((int)$r[$key] > $max) $max = (int)$r[$key]; }
    ob_start(); ?>
    <section id="<?php echo $id; ?>" role="tabpanel" <?php echo $hidden ? 'hidden' : ''; ?>>
      <?php if ($max === 0): ?>
        <div class="empty"><h3>No <?php echo h($unit); ?> recorded yet</h3><p>Totals appear after goal scorers are entered for completed matches.</p></div>
      <?php else:
        $slots = [1 => 'second', 0 => 'first', 2 => 'third'];
        echo '<div class="podium">';
        foreach ($slots as $i => $cls) {
            if (!isset($rows[$i])) continue;
            $r = $rows[$i]; ?>
            <div class="pod <?php echo $cls; ?>">
              <span class="rank"><?php echo $i + 1; ?></span>
              <img class="avatar" src="<?php echo h($r['picture']); ?>" alt="<?php echo h($r['name']); ?>">
              <div class="nm"><?php echo h($r['name']); ?></div>
              <div class="val"><?php echo (int)$r[$key]; ?></div>
            </div>
        <?php }
        echo '</div>';
        if (count($rows) > 3): ?>
        <div class="rank-list">
          <?php foreach (array_slice($rows, 3, null, true) as $i => $r): ?>
            <div class="rank-row">
              <span class="pos-n"><?php echo $i + 1; ?></span>
              <img class="avatar" src="<?php echo h($r['picture']); ?>" alt="" loading="lazy">
              <span class="nm"><?php echo h($r['name']); ?></span>
              <span class="meter" aria-hidden="true"><i style="--w:<?php echo round(((int)$r[$key]) / $max * 100); ?>%"></i></span>
              <span class="val"><?php echo (int)$r[$key]; ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif;
      endif; ?>
    </section>
    <?php return ob_get_clean();
}
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
      <h1>Top players</h1>
      <p class="page-sub">Leaders for <?php echo h($_SESSION['club_name'] ?? 'this season'); ?>, ranked by goals, assists and goal involvements.</p>
    </div>
    <div class="seg" role="tablist" aria-label="Statistic">
      <button type="button" role="tab" aria-selected="true" aria-controls="tab-goals">Goals</button>
      <button type="button" role="tab" aria-selected="false" aria-controls="tab-assists">Assists</button>
      <button type="button" role="tab" aria-selected="false" aria-controls="tab-ga">Goals + assists</button>
    </div>
  </div>

  <?php if (!empty($players_goals)): ?>
    <?php
      echo lb_panel('tab-goals',   $players_goals,   'goals',   'goals',   false);
      echo lb_panel('tab-assists', $players_assists, 'assists', 'assists', true);
      echo lb_panel('tab-ga',      $players_ga,      'ga',      'goal involvements', true);
    ?>
  <?php else: ?>
    <div class="empty">
      <h3>No player statistics yet</h3>
      <p>Add players and match results to see the leaderboards.</p>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
