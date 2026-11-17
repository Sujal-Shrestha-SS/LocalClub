<?php
// Start session and DB connection
session_start();
include 'db.php';
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle new season form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['season_title'])) {
    $title = mysqli_real_escape_string($conn, $_POST['season_title']);
    $sql = "INSERT INTO seasons (title) VALUES ('$title')";
    mysqli_query($conn, $sql);
}
?>
<?php
$page_title = 'Club seasons | eFootball League';
$nav_mode = 'landing';
$seasons = [];
$__r = mysqli_query($conn, "SELECT * FROM seasons");
while ($__r && ($row = mysqli_fetch_assoc($__r))) { $seasons[] = $row; }
$latest_id = 0;
foreach ($seasons as $s) { if ((int)$s['id'] > $latest_id) $latest_id = (int)$s['id']; }
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
      <h1>Club seasons</h1>
      <p class="page-sub">Pick a season to see the squad, fixtures, results and player stats.</p>
    </div>
  </div>

  <?php if ($seasons): ?>
  <div class="season-grid">
    <?php foreach ($seasons as $row): ?>
      <a class="season-card" href="club.php?id=<?php echo (int)$row['id']; ?>">
        <h2><?php echo h($row['title']); ?></h2>
        <div class="meta">
          <?php if (!empty($row['club'])): ?><span><?php echo h($row['club']); ?></span><?php endif; ?>
          <?php if (isset($row['position']) && $row['position'] !== ''): ?><span class="badge badge-ft">Finished <?php echo h($row['position']); ?></span><?php endif; ?>
          <?php if ((int)$row['id'] === $latest_id && count($seasons) > 1): ?><span class="badge badge-latest">Latest</span><?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty">
      <h3>No seasons yet</h3>
      <p>Seasons appear here once an admin creates them.</p>
      <a class="btn" href="admin_login.php">Admin login</a>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
