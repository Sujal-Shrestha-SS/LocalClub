<?php
session_start();
include 'db.php';

$season_id = $_SESSION['club_id'] ?? null;
if (!$season_id) {
    die("Season not selected.");
}

// Fetch season/club info
$season = mysqli_query($conn, "SELECT * FROM seasons WHERE id='$season_id'");
$seasonData = mysqli_fetch_assoc($season);
if (!$seasonData) die("Season not found.");

$club_name = $seasonData['club'];

// Overall metrics
$wins = $draws = $losses = $goals_scored = $goals_conceded = 0;

// Home stats
$home_wins = $home_draws = $home_losses = 0;
$home_goals_scored = $home_goals_conceded = 0;

// Away stats
$away_wins = $away_draws = $away_losses = 0;
$away_goals_scored = $away_goals_conceded = 0;

// Fetch all completed fixtures for this club
$sql = "SELECT * FROM fixtures 
        WHERE (home_team='$club_name' OR away_team='$club_name') 
        AND status='Match Completed'
        AND season_id=$season_id";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $home = $row['home_team'];
        $away = $row['away_team'];
        $home_score = (int)$row['home_score'];
        $away_score = (int)$row['away_score'];

        if ($home === $club_name) {
            // Goals
            $goals_scored += $home_score;
            $goals_conceded += $away_score;
            $home_goals_scored += $home_score;
            $home_goals_conceded += $away_score;

            // Result
            if ($home_score > $away_score) {
                $wins++; $home_wins++;
            } elseif ($home_score == $away_score) {
                $draws++; $home_draws++;
            } else {
                $losses++; $home_losses++;
            }
        } else {
            // Goals
            $goals_scored += $away_score;
            $goals_conceded += $home_score;
            $away_goals_scored += $away_score;
            $away_goals_conceded += $home_score;

            // Result
            if ($away_score > $home_score) {
                $wins++; $away_wins++;
            } elseif ($away_score == $home_score) {
                $draws++; $away_draws++;
            } else {
                $losses++; $away_losses++;
            }
        }
    }
}

// Overall
$goal_difference = $goals_scored - $goals_conceded;
$points = ($wins * 3) + $draws;

// Home
$home_goal_difference = $home_goals_scored - $home_goals_conceded;
$home_points = ($home_wins * 3) + $home_draws;

// Away
$away_goal_difference = $away_goals_scored - $away_goals_conceded;
$away_points = ($away_wins * 3) + $away_draws;

// Position from seasons table
$position = $seasonData['position'] ?? '-';
$club = $club_name ?? '-';
?>
<?php
$page_title = $club_name . ' | Club performance';
$nav_mode = 'public';
$played = $wins + $draws + $losses;
require_once __DIR__ . '/helpers.php';
$form = club_form($conn, $season_id, $club_name, 5);
$has_pos = ($position !== null && $position !== '' && $position !== '-');
$pct = function ($n) use ($played) { return $played ? round($n / $played * 100, 1) : 0; };
$gd = function ($n) { return ($n > 0 ? '+' : '') . $n; };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page stack">
  <div class="page-head" style="margin-bottom:0">
    <div>
      <h1>Club performance</h1>
      <p class="page-sub"><?php echo h($club); ?>, from <?php echo $played; ?> completed <?php echo $played === 1 ? 'match' : 'matches'; ?>.</p>
    </div>
    <?php if ($form): ?>
      <div>
        <div class="muted" style="font-size:.85rem; margin-bottom:6px">Last <?php echo count($form); ?> results</div>
        <div class="form-dots" aria-label="Recent form"><?php foreach ($form as $f) echo '<span class="' . $f . '">' . $f . '</span>'; ?></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="tiles">
    <?php if ($has_pos): ?><div class="tile"><div class="v"><?php echo h($position); ?></div><div class="l">League position</div></div><?php endif; ?>
    <div class="tile is-key"><div class="v"><?php echo $points; ?></div><div class="l">Points</div></div>
    <div class="tile"><div class="v"><?php echo $played; ?></div><div class="l">Played</div></div>
    <div class="tile"><div class="v"><?php echo $wins; ?></div><div class="l">Wins</div></div>
    <div class="tile"><div class="v"><?php echo $draws; ?></div><div class="l">Draws</div></div>
    <div class="tile"><div class="v"><?php echo $losses; ?></div><div class="l">Losses</div></div>
    <div class="tile"><div class="v"><?php echo $goals_scored; ?></div><div class="l">Goals for</div></div>
    <div class="tile"><div class="v"><?php echo $goals_conceded; ?></div><div class="l">Goals against</div></div>
    <div class="tile"><div class="v"><?php echo $gd($goal_difference); ?></div><div class="l">Goal difference</div></div>
  </div>

  <?php if ($played): ?>
  <section class="panel" aria-label="Results split">
    <div class="wdl" role="img" aria-label="<?php echo $wins; ?> wins, <?php echo $draws; ?> draws, <?php echo $losses; ?> losses">
      <i class="w" style="width:<?php echo $pct($wins); ?>%"></i><i class="d" style="width:<?php echo $pct($draws); ?>%"></i><i class="l" style="width:<?php echo $pct($losses); ?>%"></i>
    </div>
    <div class="legend">
      <span><span class="sw" style="background:var(--win)"></span><b><?php echo $wins; ?></b> won</span>
      <span><span class="sw" style="background:var(--card-yellow)"></span><b><?php echo $draws; ?></b> drawn</span>
      <span><span class="sw" style="background:var(--loss)"></span><b><?php echo $losses; ?></b> lost</span>
    </div>
  </section>
  <?php endif; ?>

  <section>
    <h2 style="margin-bottom:14px">Home and away</h2>
    <div class="table-wrap">
      <table class="t">
        <thead>
          <tr><th></th><th class="num">W</th><th class="num">D</th><th class="num">L</th><th class="num">GF</th><th class="num">GA</th><th class="num">GD</th><th class="num">Pts</th></tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Home</strong></td>
            <td class="num"><?php echo $home_wins; ?></td><td class="num"><?php echo $home_draws; ?></td><td class="num"><?php echo $home_losses; ?></td>
            <td class="num"><?php echo $home_goals_scored; ?></td><td class="num"><?php echo $home_goals_conceded; ?></td>
            <td class="num"><?php echo $gd($home_goal_difference); ?></td><td class="num strong"><?php echo $home_points; ?></td>
          </tr>
          <tr>
            <td><strong>Away</strong></td>
            <td class="num"><?php echo $away_wins; ?></td><td class="num"><?php echo $away_draws; ?></td><td class="num"><?php echo $away_losses; ?></td>
            <td class="num"><?php echo $away_goals_scored; ?></td><td class="num"><?php echo $away_goals_conceded; ?></td>
            <td class="num"><?php echo $gd($away_goal_difference); ?></td><td class="num strong"><?php echo $away_points; ?></td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="hint" style="margin-top:10px">W won, D drawn, L lost, GF goals for, GA goals against, GD goal difference, Pts points.</p>
  </section>
</main>
</body>
</html>
