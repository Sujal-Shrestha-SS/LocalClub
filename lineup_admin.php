<?php
session_start();
include 'db.php';
include 'admin_sessioncheck.php';
require_once 'lineup_helpers.php';

$season_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_SESSION['club_id'] ?? 0);
$season = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM seasons WHERE id=" . (int)$season_id));
if (!$season) die("Season not found.");
$_SESSION['club_id'] = $season_id;
$_SESSION['club_name'] = $season['club'];

lineup_ensure_table($conn);

// ------------------- SAVE (AJAX) -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    $data = json_decode($_POST['payload'] ?? '', true);
    $formation = $data['formation'] ?? '';
    if (!is_array($data) || !in_array($formation, lineup_formations(), true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid formation.']); exit;
    }

    // Only keep players that belong to this season, each at most once.
    $valid = [];
    $r = mysqli_query($conn, "SELECT id FROM players WHERE season_id = $season_id");
    while ($r && ($row = mysqli_fetch_assoc($r))) $valid[(int)$row['id']] = true;
    $seen = [];
    $clean = function ($list, $size) use ($valid, &$seen) {
        $out = [];
        foreach (array_slice(array_pad((array)$list, $size, null), 0, $size) as $v) {
            $v = (int)$v;
            if ($v && isset($valid[$v]) && !isset($seen[$v])) { $out[] = $v; $seen[$v] = true; }
            else $out[] = null;
        }
        return $out;
    };
    $slots = $clean($data['slots'] ?? [], 11);
    $bench = $clean($data['bench'] ?? [], lineup_bench_size());
    // The captain must be one of the starting XI.
    $captain = (int)($data['captain'] ?? 0);
    $captain = ($captain && in_array($captain, $slots, true)) ? $captain : null;
    $title = mb_substr(trim((string)($data['title'] ?? '')), 0, 100);
    $json = json_encode($slots);
    $benchJson = json_encode($bench);

    $stmt = mysqli_prepare($conn, "INSERT INTO lineups (season_id, title, formation, slots, bench, captain) VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE title=VALUES(title), formation=VALUES(formation), slots=VALUES(slots), bench=VALUES(bench), captain=VALUES(captain)");
    mysqli_stmt_bind_param($stmt, 'issssi', $season_id, $title, $formation, $json, $benchJson, $captain);
    $ok = mysqli_stmt_execute($stmt);
    echo json_encode($ok ? ['status' => 'success', 'slots' => $slots, 'bench' => $bench, 'captain' => $captain] : ['status' => 'error', 'message' => 'Could not save.']);
    exit;
}

$lineup  = lineup_load($conn, $season_id);
$players = lineup_players($conn, $season_id);

$page_title = 'Lineup | Admin';
$nav_mode = 'admin';
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
      <p class="page-sub">Pick a formation, then click a spot on the pitch or the bench and choose a player, or drag players onto either.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-ghost" href="lineup.php?id=<?php echo (int)$season_id; ?>">View public page</a>
      <button class="btn btn-yellow" type="button" id="saveBtn">Save lineup</button>
    </div>
  </div>

  <div class="lu-admin">
    <div class="lu-wrap"><div id="lineup"></div></div>

    <aside class="panel lu-side">
      <div class="field">
        <label for="luTitle">Lineup title</label>
        <input type="text" id="luTitle" maxlength="100" placeholder="<?php echo h($season['club'] ?: 'Starting XI'); ?>" value="<?php echo h($lineup['title']); ?>">
      </div>
      <div class="field">
        <label for="luFormation">Formation</label>
        <select id="luFormation">
          <?php foreach (lineup_formations() as $f): ?>
            <option value="<?php echo h($f); ?>"<?php echo $f === $lineup['formation'] ? ' selected' : ''; ?>><?php echo h($f); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="lu-hint" id="luHint" aria-live="polite">Click a spot on the pitch to start.</div>
      <div class="lu-captain-line"><span class="lu-cap-chip" aria-hidden="true">C</span> Captain: <strong id="luCaptain">not set</strong></div>
      <p class="muted" style="margin:-6px 0 0;font-size:.85rem">To set the captain, hover a starter and press the C on their photo.</p>

      <div class="seg" id="luFilter" role="group" aria-label="Filter squad">
        <button type="button" data-value="all" aria-pressed="true">All</button>
        <button type="button" data-value="Goalkeeper" aria-pressed="false">GK</button>
        <button type="button" data-value="Defender" aria-pressed="false">DEF</button>
        <button type="button" data-value="Midfielder" aria-pressed="false">MID</button>
        <button type="button" data-value="Forward" aria-pressed="false">FWD</button>
      </div>

      <div class="lu-list" id="luList">
        <?php if (!$players): ?>
          <p class="muted" style="margin:0">No players in this season yet. <a href="insert_players.php?id=<?php echo (int)$season_id; ?>">Add players</a> first.</p>
        <?php endif; ?>
      </div>

      <button class="btn btn-danger btn-sm" type="button" id="clearBtn">Clear lineup</button>
    </aside>
  </div>
</main>

<script>
window.addEventListener('DOMContentLoaded', function () {
  var players = <?php echo json_encode($players); ?>;
  var list = document.getElementById('luList');
  var hint = document.getElementById('luHint');
  var filter = 'all', dirty = false, board;

  function inLineup(id) { return board.getState().slots.indexOf(id) !== -1; }

  function renderList() {
    if (!players.length) return;
    list.innerHTML = '';
    var st = board.getState(), used = st.slots.concat(st.bench);
    players.forEach(function (p) {
      if (filter !== 'all' && p.category !== filter) return;
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'lu-item' + (used.indexOf(p.id) !== -1 ? ' is-used' : '');
      b.draggable = true;
      var img = document.createElement('img');
      img.className = 'avatar'; img.alt = ''; img.src = p.picture || 'uploads/default.png';
      var nm = document.createElement('span'); nm.className = 'lu-item-name'; nm.textContent = p.name;
      var sub = document.createElement('span'); sub.className = 'lu-item-sub';
      sub.textContent = '#' + p.jersey + ' \u00b7 ' + p.position;
      var txt = document.createElement('span'); txt.className = 'lu-item-txt';
      txt.appendChild(nm); txt.appendChild(sub);
      b.appendChild(img); b.appendChild(txt);
      if (used.indexOf(p.id) !== -1) {
        var tag = document.createElement('span'); tag.className = 'badge badge-ok'; tag.textContent = st.bench.indexOf(p.id) !== -1 ? 'Bench' : 'In XI';
        b.appendChild(tag);
      }
      b.addEventListener('click', function () { board.place(null, p.id); });
      b.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('text/plain', JSON.stringify({ type: 'player', id: p.id }));
        e.dataTransfer.effectAllowed = 'move';
      });
      list.appendChild(b);
    });
  }

  board = LineupBoard(document.getElementById('lineup'), {
    editable: true,
    clubName: <?php echo json_encode($season['club'] ?: 'eFootball League'); ?>,
    players: players,
    state: <?php echo json_encode($lineup); ?>,
    onChange: function () { dirty = true; renderList(); showCaptain(); },
    onSelect: function (i, role) {
      hint.textContent = role === 'SUB' ? 'Choose a substitute for the bench.' : 'Choose a player for the ' + role + ' spot.';
      setFilter(board.roleCategory(role) || 'all');
    }
  });

  function showCaptain() {
    var id = board.getState().captain, p = players.filter(function (x) { return x.id === id; })[0];
    document.getElementById('luCaptain').textContent = p ? p.name : 'not set';
  }

  function setFilter(v) {
    filter = v;
    document.querySelectorAll('#luFilter button').forEach(function (b) {
      b.setAttribute('aria-pressed', b.dataset.value === v ? 'true' : 'false');
    });
    renderList();
  }
  segGroup(document.getElementById('luFilter'), function (v) { filter = v; renderList(); });

  document.getElementById('luFormation').addEventListener('change', function () { board.setFormation(this.value); });
  document.getElementById('luTitle').addEventListener('input', function () { board.setTitle(this.value); dirty = true; });
  document.getElementById('clearBtn').addEventListener('click', function () {
    if (confirm('Clear all players from the lineup and bench?')) board.clear();
  });

  document.getElementById('saveBtn').addEventListener('click', function () {
    var btn = this; btn.disabled = true;
    var fd = new FormData();
    fd.append('action', 'save');
    fd.append('payload', JSON.stringify(board.getState()));
    fetch('lineup_admin.php?id=<?php echo (int)$season_id; ?>', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        btn.disabled = false;
        if (d.status === 'success') { dirty = false; toast('Lineup saved', 'ok'); }
        else toast(d.message || "Couldn't save the lineup. Try again.", 'error');
      })
      .catch(function () { btn.disabled = false; toast("Couldn't reach the server. Try again.", 'error'); });
  });

  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  renderList();
  showCaptain();
});
</script>
</body>
</html>
