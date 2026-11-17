<?php
session_start();
include 'db.php';
require_once 'lineup_helpers.php';

$season_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_SESSION['club_id'] ?? 0);
if (!$season_id) die("Season ID not provided.");

$season = mysqli_query($conn, "SELECT * FROM seasons WHERE id=$season_id");
$seasonData = mysqli_fetch_assoc($season);
if (!$seasonData) die("Season not found.");

$_SESSION['club_id'] = $season_id;
$_SESSION['club_name'] = $seasonData['club'];

$lineup  = lineup_load($conn, $season_id);
$players = lineup_players($conn, $season_id);
$placed  = count(array_filter($lineup['slots'])) + count(array_filter($lineup['bench']));

$page_title = $seasonData['title'] . ' | Lineup';
$nav_mode = 'public';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
<link rel="stylesheet" href="styles/lineup.css?v=1">
<script src="scripts/lineup.js?v=1" defer></script>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page">
  <div class="page-head">
    <div>
      <h1>Lineup</h1>
      <p class="page-sub"><?php echo $placed ? h($seasonData['club'] ?: $seasonData['title']) . ' starting XI, ' . h($lineup['formation']) . '.' : 'The starting XI for this season.'; ?></p>
    </div>
  </div>

  <?php if ($placed): ?>
    <div class="lu-wrap"><div id="lineup"></div></div>
    <script>
    window.addEventListener('DOMContentLoaded', function () {
      LineupBoard(document.getElementById('lineup'), {
        editable: false,
        clubName: <?php echo json_encode($seasonData['club'] ?: 'eFootball League'); ?>,
        players: <?php echo json_encode($players); ?>,
        state: <?php echo json_encode($lineup); ?>
      });
    });
    </script>
  <?php else: ?>
    <div class="empty">
      <h3>No lineup yet</h3>
      <p>The starting XI hasn't been picked for this season.</p>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
