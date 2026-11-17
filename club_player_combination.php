<?php
session_start();
include 'db.php';

$season_id = $_SESSION['club_id'] ?? null;
if (!$season_id) {
    die("Season not selected.");
}

// Fetch all players for dropdown
$players = mysqli_query($conn, "SELECT id, name, picture FROM players WHERE season_id='$season_id' ORDER BY name");

$player1 = $player2 = null;
$linkups = []; // fixture_id => [fixture, goals]
$total_linkups = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player1 = (int)$_POST['player1'];
    $player2 = (int)$_POST['player2'];

    if ($player1 && $player2 && $player1 !== $player2) {
        // Fetch names
        $p1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM players WHERE id='$player1'"));
        $p2 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM players WHERE id='$player2'"));

        $player1_name = $p1['name'];
        $player2_name = $p2['name'];

        // Query linkups
        $sql = "
            SELECT g.fixture_id, g.goal_scorer, g.assist_provider, 
                f.home_team, f.away_team, f.home_score, f.away_score
            FROM goal_stats g
            JOIN fixtures f ON g.fixture_id = f.id
            WHERE f.season_id = '$season_id'
            AND (
                    (g.goal_scorer = '$player1_name' AND g.assist_provider = '$player2_name')
                OR (g.goal_scorer = '$player2_name' AND g.assist_provider = '$player1_name')
                )
            ORDER BY f.id
        ";

        $res = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($res)) {
            $fixture_id = $row['fixture_id'];

            if (!isset($linkups[$fixture_id])) {
                $linkups[$fixture_id] = [
                    'fixture' => [
                        'home_team' => $row['home_team'],
                        'away_team' => $row['away_team'],
                        'home_score' => $row['home_score'],
                        'away_score' => $row['away_score'],
                    ],
                    'goals' => []
                ];
            }

            // Add each linkup for this fixture
            $linkups[$fixture_id]['goals'][] = [
                'scorer' => $row['goal_scorer'],
                'assist' => $row['assist_provider']
            ];

            $total_linkups++;
        }
    }
}
?>
<?php
$page_title = 'Player combinations';
$nav_mode = 'public';
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
      <h1>Player combinations</h1>
      <p class="page-sub">Pick two players to see every goal one scored from the other's assist.</p>
    </div>
  </div>

  <form method="POST" class="stack">
    <div class="pick">
      <div class="pick-card">
        <label for="player1" class="label">Player 1</label>
        <img class="avatar" id="player1-img" src="" alt="" hidden>
        <select name="player1" id="player1" required onchange="showPlayerImage(this, 'player1-img')">
          <option value="">Select a player</option>
          <?php mysqli_data_seek($players, 0); while($row = mysqli_fetch_assoc($players)): ?>
            <option value="<?php echo $row['id']; ?>" data-picture="<?php echo h($row['picture'] ?? 'uploads/default.png'); ?>" <?php echo ($player1==$row['id'])?'selected':''; ?>><?php echo h($row['name']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="pick-link" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
      </div>

      <div class="pick-card">
        <label for="player2" class="label">Player 2</label>
        <img class="avatar" id="player2-img" src="" alt="" hidden>
        <?php $players2 = mysqli_query($conn, "SELECT id, name, picture FROM players WHERE season_id='$season_id' ORDER BY name"); ?>
        <select name="player2" id="player2" required onchange="showPlayerImage(this, 'player2-img')">
          <option value="">Select a player</option>
          <?php while($row = mysqli_fetch_assoc($players2)): ?>
            <option value="<?php echo $row['id']; ?>" data-picture="<?php echo h($row['picture'] ?? 'uploads/default.png'); ?>" <?php echo ($player2==$row['id'])?'selected':''; ?>><?php echo h($row['name']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>
    </div>
    <div class="btn-row" style="justify-content:center"><button class="btn" type="submit">Show combinations</button></div>
  </form>

  <?php if ($player1 && $player2): ?>
    <section style="margin-top:36px">
      <?php if ($player1 === $player2): ?>
        <div class="notice">Choose two different players.</div>
      <?php else: ?>
        <div class="tiles" style="max-width:420px; margin-bottom:20px">
          <div class="tile is-key"><div class="v"><?php echo $total_linkups; ?></div><div class="l">Goals together</div></div>
          <div class="tile"><div class="v"><?php echo count($linkups); ?></div><div class="l">Matches</div></div>
        </div>

        <?php if (!empty($linkups)): ?>
          <div class="fx-list">
            <?php foreach($linkups as $fixture): ?>
              <article class="fx">
                <div class="board">
                  <div class="team home"><?php echo crest($fixture['fixture']['home_team']); ?><span class="name"><?php echo h($fixture['fixture']['home_team']); ?></span></div>
                  <div class="score"><span><?php echo (int)$fixture['fixture']['home_score']; ?></span><span class="sep">–</span><span><?php echo (int)$fixture['fixture']['away_score']; ?></span></div>
                  <div class="team away"><?php echo crest($fixture['fixture']['away_team']); ?><span class="name"><?php echo h($fixture['fixture']['away_team']); ?></span></div>
                </div>
                <ul class="linkup">
                  <?php foreach($fixture['goals'] as $g): ?>
                    <li><?php echo ball_icon(); ?><strong><?php echo h($g['scorer']); ?></strong> <span class="arrow">scored, assisted by</span> <?php echo h($g['assist']); ?></li>
                  <?php endforeach; ?>
                </ul>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty"><h3>No goals together yet</h3><p>These two players haven't combined for a goal this season.</p></div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>

<script>
function showPlayerImage(selectElement, imgId) {
    var img = document.getElementById(imgId);
    var option = selectElement.options[selectElement.selectedIndex];
    var picture = option.getAttribute('data-picture');
    if (picture && selectElement.value) {
        img.dataset.fallback = '';
        img.src = picture;
        img.alt = option.text;
        img.hidden = false;
    } else {
        img.hidden = true;
    }
}
window.addEventListener('load', function () {
    showPlayerImage(document.getElementById('player1'), 'player1-img');
    showPlayerImage(document.getElementById('player2'), 'player2-img');
});
</script>
</body>
</html>
