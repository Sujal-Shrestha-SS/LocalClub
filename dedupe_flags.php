<?php
// One-time cleanup: make every player of the same nationality share a single flag file.
// Step 1 is a preview that changes nothing. Step 2 applies it, saving a backup of the old
// values and MOVING (not deleting) the unused duplicate files so everything can be undone.
session_start();
include 'db.php';
include 'admin_sessioncheck.php';
require_once 'helpers.php';
require_once 'flag_helpers.php';

$season_id = (int)($_SESSION['club_id'] ?? 0);
if (empty($_SESSION['flag_token'])) $_SESSION['flag_token'] = bin2hex(random_bytes(16));

/** Work out what the cleanup would do, without doing it. */
function flag_plan($conn) {
    $groups = [];
    $res = mysqli_query($conn, "SELECT id, nationality, flag_picture FROM players");
    while ($res && ($p = mysqli_fetch_assoc($res))) {
        $key = flag_norm($p['nationality']);
        if ($key === '') continue;
        $groups[$key]['label'] = $groups[$key]['label'] ?? trim($p['nationality']);
        $p['flag_picture'] = (string)$p['flag_picture'];
        $groups[$key]['players'][] = $p;
    }

    $plan = [];
    foreach ($groups as $key => $g) {
        $counts = [];
        foreach ($g['players'] as $p) {
            if ($p['flag_picture'] !== '') $counts[$p['flag_picture']] = ($counts[$p['flag_picture']] ?? 0) + 1;
        }
        // Keep the most-used file that still exists (ties: the oldest name).
        $keep = '';
        $best = 0;
        ksort($counts);
        foreach ($counts as $path => $c) {
            if (file_exists($path) && $c > $best) { $keep = $path; $best = $c; }
        }
        if ($keep === '') { $plan[$key] = ['label' => $g['label'], 'players' => count($g['players']), 'keep' => '', 'update_ids' => [], 'archive' => [], 'files' => array_keys($counts)]; continue; }

        $update = [];
        foreach ($g['players'] as $p) if ($p['flag_picture'] !== $keep) $update[] = (int)$p['id'];

        $archive = [];
        foreach (array_keys($counts) as $path) {
            if ($path === $keep || !flag_safe_path($path) || !file_exists($path)) continue;
            // Only archive a file if no player outside this nationality uses it.
            $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM players WHERE flag_picture = ? AND LOWER(TRIM(nationality)) <> ?");
            mysqli_stmt_bind_param($stmt, 'ss', $path, $key);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $others);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            if ($others == 0) $archive[] = $path;
        }
        $plan[$key] = ['label' => $g['label'], 'players' => count($g['players']), 'keep' => $keep, 'update_ids' => $update, 'archive' => $archive, 'files' => array_keys($counts)];
    }
    ksort($plan);
    return $plan;
}

$plan = flag_plan($conn);
$todo = array_filter($plan, function ($g) { return $g['keep'] !== '' && ($g['update_ids'] || $g['archive']); });
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['flag_token'], $_POST['token'] ?? '')) {
    // Backup table: the previous flag path of every player we are about to change.
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS players_flag_backup (
        player_id INT NOT NULL, old_flag VARCHAR(255) NOT NULL, saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (player_id)) CHARACTER SET utf8mb4");

    $updated = 0; $moved = 0;
    $trash = 'uploads/_duplicate_flags/';
    foreach ($todo as $g) {
        foreach ($g['update_ids'] as $id) {
            $stmt = mysqli_prepare($conn, "INSERT INTO players_flag_backup (player_id, old_flag) SELECT id, flag_picture FROM players WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $stmt = mysqli_prepare($conn, "UPDATE players SET flag_picture = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $g['keep'], $id);
            if (mysqli_stmt_execute($stmt)) $updated++;
        }
        foreach ($g['archive'] as $path) {
            if (!is_dir($trash)) mkdir($trash, 0755, true);
            $dest = $trash . basename($path);
            if (file_exists($dest)) $dest = $trash . uniqid() . '_' . basename($path);
            if (@rename($path, $dest)) $moved++;
        }
    }
    $result = ['updated' => $updated, 'moved' => $moved];
    $_SESSION['flag_token'] = bin2hex(random_bytes(16));
    $plan = flag_plan($conn);
    $todo = array_filter($plan, function ($g) { return $g['keep'] !== '' && ($g['update_ids'] || $g['archive']); });
}

$total_updates = array_sum(array_map(function ($g) { return count($g['update_ids']); }, $todo));
$total_files   = array_sum(array_map(function ($g) { return count($g['archive']); }, $todo));

$page_title = 'Clean up flags | Admin';
$nav_mode = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page stack">
  <div class="page-head">
    <div>
      <h1>Clean up duplicate flags</h1>
      <p class="page-sub">Make every player of the same nationality share one flag image.</p>
    </div>
    <div class="page-actions"><a class="btn btn-ghost" href="insert_players.php?id=<?php echo $season_id; ?>">Back to players</a></div>
  </div>

  <?php if ($result): ?>
    <div class="panel">
      <h2 style="margin-top:0">Done</h2>
      <p>Updated <strong><?php echo (int)$result['updated']; ?></strong> players and moved <strong><?php echo (int)$result['moved']; ?></strong> duplicate files into <code>uploads/_duplicate_flags/</code>.</p>
      <p class="muted" style="margin-bottom:0">To undo: move the files back from that folder, then run
        <code>UPDATE players p JOIN players_flag_backup b ON p.id = b.player_id SET p.flag_picture = b.old_flag;</code>
        Once you are happy, you can delete that folder.</p>
    </div>
  <?php endif; ?>

  <?php if (!$todo): ?>
    <div class="empty"><h3>Nothing to clean up</h3><p>Every nationality already uses a single flag file.</p></div>
  <?php else: ?>
    <div class="panel">
      <p style="margin-top:0">This would update <strong><?php echo $total_updates; ?></strong> players and move <strong><?php echo $total_files; ?></strong> duplicate files out of the way. Nothing has been changed yet.</p>
      <ul class="muted" style="margin:0 0 14px;padding-left:20px">
        <li>The most-used flag file for each nationality is kept.</li>
        <li>Duplicates are moved to <code>uploads/_duplicate_flags/</code>, not deleted, and the old values are saved in a backup table.</li>
        <li>Nationalities are matched ignoring capital letters and extra spaces. "England" and "english" are different spellings and are not merged.</li>
      </ul>
      <form method="POST" onsubmit="return confirm('Apply the flag cleanup now?');">
        <input type="hidden" name="token" value="<?php echo h($_SESSION['flag_token']); ?>">
        <button class="btn btn-yellow" type="submit">Apply cleanup</button>
      </form>
    </div>

    <div class="table-wrap">
      <table class="t">
        <thead><tr><th>Nationality</th><th class="num">Players</th><th class="num">Flag files now</th><th>Kept</th><th class="num">Players to update</th><th class="num">Files to move</th></tr></thead>
        <tbody>
        <?php foreach ($todo as $g): ?>
          <tr>
            <td><strong><?php echo h($g['label']); ?></strong></td>
            <td class="num"><?php echo $g['players']; ?></td>
            <td class="num"><?php echo count($g['files']); ?></td>
            <td><img class="flag" src="<?php echo h($g['keep']); ?>" alt=""> <span class="muted"><?php echo h(basename($g['keep'])); ?></span></td>
            <td class="num"><?php echo count($g['update_ids']); ?></td>
            <td class="num"><?php echo count($g['archive']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php $noflag = array_filter($plan, function ($g) { return $g['keep'] === ''; }); if ($noflag): ?>
    <p class="muted">No usable flag file was found for: <?php echo h(implode(', ', array_map(function ($g) { return $g['label']; }, $noflag))); ?>. Upload one when you next edit a player of that nationality.</p>
  <?php endif; ?>
</main>
</body>
</html>
