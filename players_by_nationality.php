<?php
session_start();
include 'db.php';
include 'admin_sessioncheck.php';
require_once 'helpers.php';
require_once 'player_career_data.php'; // same "same player" rule as the career pages

// "Manage" links jump to the Players tab of the right season.
if (isset($_GET['manage'])) {
    $sid = (int)$_GET['manage'];
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, club FROM seasons WHERE id=$sid"));
    if ($row) {
        $_SESSION['club_id'] = (int)$row['id'];
        $_SESSION['club_name'] = $row['club'];
        header("Location: insert_players.php?id=" . (int)$row['id']);
        exit;
    }
}

$selected = mb_strtolower(trim($_GET['nat'] ?? ''));

// One entry per real person. The players table has one row per season, so the same player
// appears once for every season he was entered in. We merge rows with the same name
// (trimmed, ignoring capitals), exactly as the Player history pages do.
$res = mysqli_query($conn, "SELECT p.*, s.title AS season_title FROM players p
    LEFT JOIN seasons s ON s.id = p.season_id ORDER BY p.id ASC");
$people = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $key = normalize_player_name($row['name']);
    if ($key === '') $key = 'id:' . $row['id'];
    if (!isset($people[$key])) $people[$key] = ['key' => $key, 'seasons' => [], 'picture' => '', 'flag_picture' => ''];
    $keepPic  = $people[$key]['picture'];
    $keepFlag = $people[$key]['flag_picture'];
    $seasons  = $people[$key]['seasons'];
    // Later rows are the most recent look, nationality, shirt and position.
    $people[$key] = $row + ['key' => $key];
    $people[$key]['picture']      = !empty($row['picture']) ? $row['picture'] : $keepPic;
    $people[$key]['flag_picture'] = !empty($row['flag_picture']) ? $row['flag_picture'] : $keepFlag;
    $seasons[(int)$row['season_id']] = $row['season_title'] ?? '';
    ksort($seasons);
    $people[$key]['seasons'] = $seasons;
}
$all = array_values($people);
usort($all, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });

// Nationality chips: count players per nationality (ignoring capitals / spaces).
$nats = [];
foreach ($all as $p) {
    $k = mb_strtolower(trim($p['nationality']));
    if ($k === '') $k = '(none)';
    if (!isset($nats[$k])) $nats[$k] = ['label' => trim($p['nationality']) !== '' ? trim($p['nationality']) : 'No nationality', 'count' => 0, 'flag' => ''];
    $nats[$k]['count']++;
    if ($nats[$k]['flag'] === '' && !empty($p['flag_picture'])) $nats[$k]['flag'] = $p['flag_picture'];
}
uasort($nats, function ($a, $b) { return $b['count'] <=> $a['count'] ?: strcasecmp($a['label'], $b['label']); });

$shown = array_values(array_filter($all, function ($p) use ($selected) {
    if ($selected === '') return true;
    $k = mb_strtolower(trim($p['nationality']));
    return ($k === '' ? '(none)' : $k) === $selected;
}));

function nat_url($nat) {
    return 'players_by_nationality.php' . ($nat !== '' ? '?nat=' . urlencode($nat) : '');
}

$page_title = 'Players by nationality | Admin';
$nav_mode = 'global';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
<style>
  .nat-chips { display: flex; flex-wrap: wrap; gap: 8px; }
  .nat-chip {
    display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px;
    background: var(--paper); border: 1px solid var(--line); color: var(--ink); text-decoration: none; font-weight: 600; font-size: .92rem;
  }
  .nat-chip:hover { border-color: var(--turf-2); background: var(--turf-tint); }
  .nat-chip[aria-current="true"] { background: var(--turf); border-color: var(--turf); color: #fff; }
  .nat-chip .n { font-weight: 700; opacity: .7; font-size: .82rem; }
  .nat-chip .flag { width: 20px; height: 20px; }
  .nat-tools { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }
  .nat-tools input[type=search] { max-width: 260px; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page stack">
  <div class="page-head">
    <div>
      <h1>Players by nationality</h1>
      <p class="page-sub"><?php echo count($all); ?> players across all seasons, from <?php echo count($nats); ?> nationalities. A player who appears in several seasons is counted once.</p>
    </div>
  </div>

  <?php if (!$all): ?>
    <div class="empty"><h3>No players yet</h3><p>Add players to a season first.</p></div>
  <?php else: ?>

  <nav class="nat-chips" aria-label="Filter by nationality">
    <a class="nat-chip" href="<?php echo h(nat_url('')); ?>"<?php echo $selected === '' ? ' aria-current="true"' : ''; ?>>All <span class="n"><?php echo count($all); ?></span></a>
    <?php foreach ($nats as $key => $n): ?>
      <a class="nat-chip" href="<?php echo h(nat_url($key)); ?>"<?php echo $selected === $key ? ' aria-current="true"' : ''; ?>>
        <?php if ($n['flag']): ?><img class="flag" src="<?php echo h($n['flag']); ?>" alt=""><?php endif; ?>
        <?php echo h($n['label']); ?> <span class="n"><?php echo $n['count']; ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="nat-tools">
    <strong><?php echo count($shown); ?> player<?php echo count($shown) === 1 ? '' : 's'; ?><?php echo $selected !== '' && isset($nats[$selected]) ? ' from ' . h($nats[$selected]['label']) : ''; ?></strong>
    <input type="search" id="nameFilter" placeholder="Search by name" aria-label="Search by name">
  </div>

  <?php if (!$shown): ?>
    <div class="empty"><h3>No players found</h3><p>No players match that nationality here.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="t" data-sortable id="natTable">
      <thead>
        <tr>
          <th data-sort="text">Player</th>
          <th data-sort="text">Nationality</th>
          <th data-sort="text">Category</th>
          <th data-sort="text">Position</th>
          <th class="num" data-sort="num">Seasons</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($shown as $p): ?>
        <tr>
          <td>
            <span style="display:inline-flex;align-items:center;gap:10px">
              <img class="avatar" src="<?php echo h($p['picture'] ?: 'uploads/default.png'); ?>" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover">
              <strong class="pname"><?php echo h($p['name']); ?></strong>
            </span>
          </td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:8px">
              <?php if (!empty($p['flag_picture'])): ?><img class="flag" src="<?php echo h($p['flag_picture']); ?>" alt="" width="26" height="17"><?php endif; ?>
              <?php echo h($p['nationality']); ?>
            </span>
          </td>
          <td><span class="pos pos-<?php echo h($p['category']); ?>"><?php echo h($p['category']); ?></span></td>
          <td><?php echo h($p['position']); ?></td>
          <td class="num" data-v="<?php echo count($p['seasons']); ?>" title="<?php echo h(implode(', ', $p['seasons'])); ?>">
            <?php echo count($p['seasons']); ?>
            <span class="muted" style="font-size:.8rem;font-weight:400;display:block;max-width:220px;margin-left:auto"><?php echo h(implode(', ', $p['seasons'])); ?></span>
          </td>
          <td style="white-space:nowrap">
            <a class="btn btn-ghost btn-sm" href="player_profile.php?player=<?php echo urlencode($p['key']); ?>">Career</a>
            <a class="btn btn-ghost btn-sm" href="players_by_nationality.php?manage=<?php echo (int)$p['season_id']; ?>" title="Edit in the latest season">Manage</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</main>

<script>
(function () {
  var input = document.getElementById('nameFilter');
  if (!input) return;
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    document.querySelectorAll('#natTable tbody tr').forEach(function (tr) {
      var name = tr.querySelector('.pname').textContent.toLowerCase();
      tr.hidden = q !== '' && name.indexOf(q) === -1;
    });
  });
})();
</script>
</body>
</html>
