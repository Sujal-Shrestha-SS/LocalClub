<?php
session_start();
include 'db.php';

if (!isset($_GET['id'])) die("Season ID not provided.");
$season_id = (int)$_GET['id'];
mysqli_query($conn, "UPDATE players SET season_id = $season_id WHERE season_id IS NULL");

// Fetch season info
$season = mysqli_query($conn, "SELECT * FROM seasons WHERE id=$season_id");
$seasonData = mysqli_fetch_assoc($season);
if (!$seasonData) die("Season not found.");

$_SESSION['club_id'] = $season_id;
$_SESSION['club_name'] = $seasonData['club'];

// Fetch players
$sql = "SELECT * FROM players WHERE season_id='$season_id' ORDER BY jersey_no";
$result = mysqli_query($conn, $sql);

// Fetch unique countries for nationality filter
$countryQuery = "SELECT DISTINCT nationality FROM players WHERE season_id='$season_id' ORDER BY nationality ASC";
$countryResult = mysqli_query($conn, $countryQuery);
?>
<?php
$page_title = $seasonData['title'] . ' | Squad';
$nav_mode = 'public';
$player_rows = [];
while ($result && ($p = mysqli_fetch_assoc($result))) { $player_rows[] = $p; }
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
      <p class="page-sub"><?php echo !empty($seasonData['club']) ? h($seasonData['club']) . ' squad' : 'Squad'; ?></p>
    </div>
  </div>

  <div class="toolbar">
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
    <div class="group">
      <label for="countryFilter">Nationality</label>
      <select id="countryFilter">
        <option value="all">All nationalities</option>
        <?php while($row = mysqli_fetch_assoc($countryResult)): ?>
          <option value="<?php echo h($row['nationality']); ?>"><?php echo h($row['nationality']); ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <span class="result-count" id="resultCount" aria-live="polite"></span>
  </div>

  <?php if ($player_rows): ?>
  <div class="player-grid" id="players">
    <?php foreach ($player_rows as $player): ?>
      <a class="pcard player-card"
           href="player_opponents.php?id=<?php echo $season_id; ?>&amp;player=<?php echo (int)$player['id']; ?>"
           title="See goals and assists against each opponent"
           data-category="<?php echo h($player['category']); ?>"
           data-country="<?php echo h($player['nationality']); ?>">
        <div class="photo">
          <img class="pic" src="<?php echo h($player['picture']); ?>" alt="<?php echo h($player['name']); ?>" loading="lazy">
          <?php if (!empty($player['flag_picture'])): ?>
            <img class="flag-chip" src="<?php echo h($player['flag_picture']); ?>" alt="<?php echo h($player['nationality']); ?> flag">
          <?php endif; ?>
          <span class="shirt"><?php echo h($player['jersey_no']); ?></span>
        </div>
        <div class="meta">
          <div class="nm"><?php echo h($player['name']); ?></div>
          <div><span class="pos pos-<?php echo h($player['category']); ?>"><?php echo h($player['category']); ?></span></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="empty" id="noMatch" hidden>
    <h3>No players match</h3>
    <p>Try a different position or nationality.</p>
  </div>
  <?php else: ?>
    <div class="empty">
      <h3>No players yet</h3>
      <p>The squad hasn't been added for this season.</p>
    </div>
  <?php endif; ?>
</main>

<script>
(function () {
  var position = 'all';
  var country = document.getElementById('countryFilter');
  var cards = document.querySelectorAll('.player-card');
  function filterPlayers() {
    var shown = 0;
    cards.forEach(function (c) {
      var ok = (position === 'all' || c.getAttribute('data-category') === position) &&
               (country.value === 'all' || c.getAttribute('data-country') === country.value);
      c.hidden = !ok;
      if (ok) shown++;
    });
    var count = document.getElementById('resultCount');
    if (count && cards.length) count.textContent = shown + (shown === 1 ? ' player' : ' players');
    var none = document.getElementById('noMatch');
    if (none) none.hidden = shown !== 0;
  }
  window.addEventListener('DOMContentLoaded', function () {
    segGroup(document.getElementById('positionFilter'), function (v) { position = v; filterPlayers(); });
    country.addEventListener('change', filterPlayers);
    filterPlayers();
  });
})();
</script>
</body>
</html>
