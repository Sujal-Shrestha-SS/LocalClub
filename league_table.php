<?php
session_start();
include 'db.php';

// $season_id = $_SESSION['club_id'];
$season_id = (int)$_GET['id'];
// Choose the club you want stats for
$club_name = $_SESSION['club_name']; // You can make this dynamic with a dropdown if needed

include 'admin_sessioncheck.php';
// Initialize values
$wins = $draws = $losses = $goals_scored = $goals_conceded = 0;

// Fetch fixtures where club played (either home or away)
$sql = "SELECT * FROM fixtures 
        WHERE (home_team='$club_name' OR away_team='$club_name') 
        AND status='Match Completed'
        AND season_id=$season_id";
$result = $conn->query($sql);

$idCheck = "SELECT COUNT(id) AS total_id FROM fixtures WHERE season_id = $season_id";
$idResult = mysqli_query($conn, $idCheck);
$idCount = mysqli_fetch_assoc($idResult);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $home = $row['home_team'];
        $away = $row['away_team'];
        $home_score = (int)$row['home_score'];
        $away_score = (int)$row['away_score'];

        if ($home === $club_name) {
            $goals_scored += $home_score;
            $goals_conceded += $away_score;

            if ($home_score > $away_score) $wins++;
            elseif ($home_score == $away_score) $draws++;
            else $losses++;
        } else {
            $goals_scored += $away_score;
            $goals_conceded += $home_score;

            if ($away_score > $home_score) $wins++;
            elseif ($away_score == $home_score) $draws++;
            else $losses++;
        }
    }
}

$goal_difference = $goals_scored - $goals_conceded;
$points = ($wins * 3) + ($draws * 1);

// Handle position input
// $position = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_position'])) {
    $position = intval($_POST['position']);

    $sql = "UPDATE seasons SET position = '$position' WHERE id = '$season_id' ";

    mysqli_query($conn, $sql);
}

    $pos_query = "SELECT * FROM seasons WHERE id = '$season_id' ";
    $pos_result = mysqli_query($conn, $pos_query);
    $pos_output = mysqli_fetch_assoc($pos_result);


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_club'])) {
    $club = $_POST['club'];

    $sql = "UPDATE seasons SET club = '$club' WHERE id = '$season_id' ";

    mysqli_query($conn, $sql);
}

    $club_query = "SELECT * FROM seasons WHERE id = '$season_id' ";
    $club_result = mysqli_query($conn, $club_query);
    $club_output = mysqli_fetch_assoc($club_result);
?>
<?php
$page_title = 'Club info | Admin';
$nav_mode = 'admin';
require_once __DIR__ . '/helpers.php';
$form = club_form($conn, $season_id, $club_name, 5);
$gd = ($goal_difference > 0 ? '+' : '') . $goal_difference;
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
      <h1>Club info</h1>
      <p class="page-sub"><?php echo $club_name !== '' && $club_name !== null ? h($club_name) . ' league record for this season.' : 'Set the club name below to see its record.'; ?></p>
    </div>
    <?php if ($form): ?>
      <div>
        <div class="muted" style="font-size:.85rem; margin-bottom:6px">Last <?php echo count($form); ?> results</div>
        <div class="form-dots" aria-label="Recent form"><?php foreach ($form as $f) echo '<span class="' . $f . '">' . $f . '</span>'; ?></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="tiles">
    <?php if ($pos_output['position'] !== null): ?><div class="tile"><div class="v"><?php echo h($pos_output['position']); ?></div><div class="l">League position</div></div><?php endif; ?>
    <div class="tile is-key"><div class="v"><?php echo $points; ?></div><div class="l">Points</div></div>
    <div class="tile"><div class="v"><?php echo $idCount['total_id']; ?></div><div class="l">Fixtures</div></div>
    <div class="tile"><div class="v"><?php echo $wins; ?></div><div class="l">Wins</div></div>
    <div class="tile"><div class="v"><?php echo $draws; ?></div><div class="l">Draws</div></div>
    <div class="tile"><div class="v"><?php echo $losses; ?></div><div class="l">Losses</div></div>
    <div class="tile"><div class="v"><?php echo $goals_scored; ?></div><div class="l">Goals for</div></div>
    <div class="tile"><div class="v"><?php echo $goals_conceded; ?></div><div class="l">Goals against</div></div>
    <div class="tile"><div class="v"><?php echo $gd; ?></div><div class="l">Goal difference</div></div>
  </div>

  <details class="panel" <?php echo ($pos_output['club'] === null || $pos_output['club'] === '') ? 'open' : ''; ?>>
    <summary>Edit club details</summary>
    <div class="grid-2">
      <form method="post" class="club-form field" style="gap:10px">
        <label for="club">Club name<?php if ($pos_output['club'] !== null): ?> <span class="muted" style="font-weight:400">(now: <?php echo h($club_output['club']); ?>)</span><?php endif; ?></label>
        <input type="text" name="club" id="club" required>
        <div><button class="btn" type="submit" name="save_club">Save club name</button></div>
      </form>
      <form method="post" class="position-form field" style="gap:10px">
        <label for="position">League position<?php if ($pos_output['position'] !== null): ?> <span class="muted" style="font-weight:400">(now: <?php echo h($pos_output['position']); ?>)</span><?php endif; ?></label>
        <input type="number" name="position" id="position" min="1" required>
        <div><button class="btn" type="submit" name="save_position">Save position</button></div>
      </form>
    </div>
  </details>
</main>
</body>
</html>
