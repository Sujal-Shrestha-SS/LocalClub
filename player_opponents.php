<?php
session_start();
include 'db.php';

$season_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_SESSION['club_id'] ?? 0);
$player_id = isset($_GET['player']) ? (int)$_GET['player'] : 0;
if (!$season_id) die("Season ID not provided.");

$season = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM seasons WHERE id=$season_id"));
if (!$season) die("Season not found.");
$_SESSION['club_id'] = $season_id;
$_SESSION['club_name'] = $season['club'];

$stmt = mysqli_prepare($conn, "SELECT * FROM players WHERE id=? AND season_id=?");
mysqli_stmt_bind_param($stmt, 'ii', $player_id, $season_id);
mysqli_stmt_execute($stmt);
$player = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$opponents = [];   // opponent name => ['goals','assists','matches'=>[fixture ids]]
$matches   = [];   // fixture id => details
$total_goals = $total_assists = 0;

if ($player) {
    // Matchday numbers follow the fixture order used on the Matches tab.
    $matchday = [];
    $n = 1;
    $r = mysqli_query($conn, "SELECT id FROM fixtures WHERE season_id=$season_id");
    while ($r && ($row = mysqli_fetch_assoc($r))) $matchday[(int)$row['id']] = $n++;

    // Goals/assists are stored by player name, same as the stats pages.
    $key = mb_strtolower(trim($player['name']));
    $stmt = mysqli_prepare($conn,
        "SELECT g.fixture_id, g.team, g.goal_scorer, g.assist_provider,
                f.home_team, f.away_team, f.home_score, f.away_score, f.status
         FROM goal_stats g JOIN fixtures f ON g.fixture_id = f.id
         WHERE f.season_id = ? AND (LOWER(TRIM(g.goal_scorer)) = ? OR LOWER(TRIM(g.assist_provider)) = ?)
         ORDER BY f.id");
    mysqli_stmt_bind_param($stmt, 'iss', $season_id, $key, $key);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($res)) {
        // "team" on a goal is the side that scored (Home/Away); the opponent is the other side.
        if ($row['team'] === 'Home')      $opp = $row['away_team'];
        elseif ($row['team'] === 'Away')  $opp = $row['home_team'];
        else                              $opp = (strcasecmp($row['home_team'], $season['club']) === 0) ? $row['away_team'] : $row['home_team'];

        $fid = (int)$row['fixture_id'];
        $isGoal   = mb_strtolower(trim($row['goal_scorer'])) === $key;
        $isAssist = mb_strtolower(trim($row['assist_provider'])) === $key;

        if (!isset($opponents[$opp])) $opponents[$opp] = ['goals' => 0, 'assists' => 0, 'matches' => []];
        if (!isset($matches[$fid])) {
            $matches[$fid] = ['md' => $matchday[$fid] ?? 0, 'opp' => $opp, 'home' => $row['home_team'], 'away' => $row['away_team'],
                              'hs' => $row['home_score'], 'as' => $row['away_score'], 'done' => $row['status'] === 'Match Completed',
                              'goals' => 0, 'assists' => 0];
        }
        if ($isGoal)   { $opponents[$opp]['goals']++;   $matches[$fid]['goals']++;   $total_goals++; }
        if ($isAssist) { $opponents[$opp]['assists']++; $matches[$fid]['assists']++; $total_assists++; }
        $opponents[$opp]['matches'][$fid] = true;
    }
    uasort($opponents, function ($a, $b) {
        return ($b['goals'] + $b['assists']) <=> ($a['goals'] + $a['assists']) ?: $b['goals'] <=> $a['goals'];
    });
}

$page_title = ($player ? $player['name'] : 'Player') . ' | vs opponents';
$nav_mode = 'public';
require_once 'helpers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page stack">
  <div><a class="back-link" href="club.php?id=<?php echo $season_id; ?>">← Back to squad</a></div>

<?php if (!$player): ?>
  <div class="empty">
    <h3>Player not found</h3>
    <p>This player isn't in this season's squad.</p>
    <a class="btn" href="club.php?id=<?php echo $season_id; ?>">Back to squad</a>
  </div>
<?php else: ?>
  <section class="panel">
    <div class="profile-head">
      <div class="profile-photo-wrap">
        <img class="profile-photo" src="<?php echo h($player['picture'] ?: 'uploads/default.png'); ?>" alt="<?php echo h($player['name']); ?>">
        <?php if (!empty($player['flag_picture'])): ?>
          <img class="flag-chip" src="<?php echo h($player['flag_picture']); ?>" alt="<?php echo h($player['nationality']); ?> flag">
        <?php endif; ?>
      </div>
      <div class="profile-meta">
        <h1><?php echo h($player['name']); ?></h1>
        <div class="row">
          <span class="pos pos-<?php echo h($player['category']); ?>"><?php echo h($player['category']); ?></span>
          <span class="badge"><?php echo h($player['position']); ?></span>
          <span class="nat"><?php echo h($player['nationality']); ?></span>
        </div>
        <div class="muted">Shirt <?php echo h($player['jersey_no']); ?> · <?php echo h($season['title']); ?></div>
      </div>
    </div>
  </section>

  <div class="tiles">
    <div class="tile is-key"><div class="v"><?php echo $total_goals; ?></div><div class="l">Goals</div></div>
    <div class="tile"><div class="v"><?php echo $total_assists; ?></div><div class="l">Assists</div></div>
    <div class="tile"><div class="v"><?php echo count($opponents); ?></div><div class="l">Opponents</div></div>
    <div class="tile"><div class="v"><?php echo count($matches); ?></div><div class="l">Matches with a goal or assist</div></div>
  </div>

  <?php if ($opponents): ?>
  <section>
    <h2 style="margin-bottom:14px">By opponent</h2>
    <div class="table-wrap">
      <table class="t" data-sortable>
        <thead>
          <tr>
            <th data-sort="text">Opponent</th>
            <th class="num" data-sort="num">Matches</th>
            <th class="num" data-sort="num">Goals</th>
            <th class="num" data-sort="num">Assists</th>
            <th class="num" data-sort="num">G/A</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($opponents as $name => $o): ?>
            <tr>
              <td><span style="display:inline-flex;align-items:center;gap:10px"><?php echo crest($name); ?><strong><?php echo h($name); ?></strong></span></td>
              <td class="num"><?php echo count($o['matches']); ?></td>
              <td class="num"><?php echo $o['goals']; ?></td>
              <td class="num"><?php echo $o['assists']; ?></td>
              <td class="num strong"><?php echo $o['goals'] + $o['assists']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section>
    <h2 style="margin-bottom:14px">Match by match</h2>
    <div class="table-wrap">
      <table class="t">
        <thead>
          <tr>
            <th>Matchday</th>
            <th>Match</th>
            <th class="num">Goals</th>
            <th class="num">Assists</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matches as $m): ?>
            <tr>
              <td><?php echo $m['md'] ? (int)$m['md'] : '–'; ?></td>
              <td>
                <?php echo h($m['home']); ?>
                <strong class="tnum"><?php echo $m['done'] ? (int)$m['hs'] . '–' . (int)$m['as'] : 'v'; ?></strong>
                <?php echo h($m['away']); ?>
              </td>
              <td class="num"><?php echo $m['goals']; ?></td>
              <td class="num"><?php echo $m['assists']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php else: ?>
    <div class="empty">
      <h3>No goals or assists yet</h3>
      <p><?php echo h($player['name']); ?> hasn't scored or set up a goal this season.</p>
    </div>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
