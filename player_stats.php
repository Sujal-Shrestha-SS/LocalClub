<?php
include 'db.php';
session_start();

$season_id = $_SESSION['club_id']; // Current season ID

include 'admin_sessioncheck.php';

// Fetch players with stats (joined with players table)
$sql = "
    SELECT p.id, p.name, p.jersey_no, p.nationality, p.picture,
           COALESCE(SUM(goals),0) AS total_goals,
           COALESCE(SUM(assists),0) AS total_assists,
           (COALESCE(SUM(goals),0) + COALESCE(SUM(assists),0)) AS ga
    FROM players p
    LEFT JOIN (
        SELECT goal_scorer AS player, COUNT(*) AS goals, 0 AS assists
        FROM goal_stats g
        JOIN fixtures f ON g.fixture_id = f.id
        WHERE f.season_id = '$season_id'
        GROUP BY goal_scorer
        UNION ALL
        SELECT assist_provider AS player, 0 AS goals, COUNT(*) AS assists
        FROM goal_stats g
        JOIN fixtures f ON g.fixture_id = f.id
        WHERE f.season_id = '$season_id'
        GROUP BY assist_provider
    ) stats ON p.name = stats.player
     WHERE p.season_id = '$season_id'
    GROUP BY p.id, p.name, p.jersey_no, p.nationality, p.picture
    ORDER BY ga DESC, total_goals DESC
";

$result = mysqli_query($conn, $sql);
?>
<?php
$page_title = 'Player stats | Admin';
$nav_mode = 'admin';
$rows = [];
while ($result && ($r = mysqli_fetch_assoc($result))) { $rows[] = $r; }
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
      <h1>Player stats</h1>
      <p class="page-sub">Ranked by goal involvements (goals plus assists). Click a column to re-sort.</p>
    </div>
    <?php if ($rows): ?><input class="input search" type="search" placeholder="Search players" aria-label="Search players" data-table-search="#statsTable"><?php endif; ?>
  </div>

  <?php if ($rows): ?>
  <div class="table-wrap">
    <table class="t" id="statsTable" data-sortable>
      <thead>
        <tr>
          <th class="num" style="width:56px">#</th>
          <th data-sort="text">Player</th>
          <th data-sort="num" class="num">Goals</th>
          <th data-sort="num" class="num">Assists</th>
          <th data-sort="num" class="num">G+A</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $row): ?>
          <tr>
            <td class="num muted"><?php echo $i + 1; ?></td>
            <td data-v="<?php echo h($row['name']); ?>"><div class="who"><img class="avatar" src="<?php echo h($row['picture']); ?>" alt="" loading="lazy"><?php echo h($row['name']); ?></div></td>
            <td class="num"><?php echo (int)$row['total_goals']; ?></td>
            <td class="num"><?php echo (int)$row['total_assists']; ?></td>
            <td class="num strong"><?php echo (int)$row['ga']; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty">
      <h3>No players yet</h3>
      <p>Add players first, then enter match scorers to see their stats here.</p>
      <a class="btn" href="insert_players.php?id=<?php echo (int)$season_id; ?>">Add players</a>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
