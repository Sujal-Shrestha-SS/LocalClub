<?php
// Start session and DB connection
session_start();
include 'db.php';
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}


include 'admin_sessioncheck.php';


//Handle new season form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['season_title'])) {
    $title = mysqli_real_escape_string($conn, $_POST['season_title']);
    $sql = "INSERT INTO seasons (title) VALUES ('$title')";
    mysqli_query($conn, $sql);
}



?>
<?php
$page_title = 'Seasons | Admin | eFootball League';
$nav_mode = 'global';
$seasons = [];
$__r = mysqli_query($conn, "SELECT * FROM seasons");
while ($__r && ($row = mysqli_fetch_assoc($__r))) { $seasons[] = $row; }
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
      <h1>Seasons</h1>
      <p class="page-sub">Open a season to manage its fixtures, players, club info and media.</p>
    </div>
    <div class="page-actions">
      <!-- <a class="btn btn-ghost" href="player_history.php">Player history</a> -->
      <button class="btn btn-yellow" type="button" onclick="document.getElementById('createForm').hidden = !document.getElementById('createForm').hidden; document.getElementById('season_title').focus();">+ Create season</button>
    </div>
  </div>

  <div id="createForm" class="panel" hidden style="margin-bottom:24px">
    <form method="POST" action="" class="btn-row">
      <div class="field" style="flex:1; min-width:240px">
        <label for="season_title">Season title</label>
        <input type="text" id="season_title" name="season_title" placeholder="e.g. 2026/27 Premier Division" required>
      </div>
      <button class="btn" type="submit" style="align-self:flex-end">Create season</button>
    </form>
  </div>

  <?php if ($seasons): ?>
  <div class="season-grid">
    <?php foreach ($seasons as $row): ?>
      <a class="season-card" href="season.php?id=<?php echo (int)$row['id']; ?>">
        <h2><?php echo h($row['title']); ?></h2>
        <div class="meta">
          <?php if (!empty($row['club'])): ?><span><?php echo h($row['club']); ?></span><?php else: ?><span>No club set yet</span><?php endif; ?>
          <?php if (isset($row['position']) && $row['position'] !== ''): ?><span class="badge badge-ft">Position <?php echo h($row['position']); ?></span><?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty">
      <h3>No seasons yet</h3>
      <p>Create your first season to start adding fixtures and players.</p>
      <button class="btn" type="button" onclick="document.getElementById('createForm').hidden = false; document.getElementById('season_title').focus();">+ Create season</button>
    </div>
  <?php endif; ?>
</main>
</body>
</html>