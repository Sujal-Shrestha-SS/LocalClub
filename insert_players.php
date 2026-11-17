<?php
session_start();
include 'db.php';
$season_id = $_SESSION['club_id'];
include 'admin_sessioncheck.php';
require_once 'flag_helpers.php';

$target_dir = "uploads/"; 
if(!is_dir($target_dir)){
    mkdir($target_dir, 0755, true);
}

// ------------------- HANDLE ADD / UPDATE -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_update') {

    $name = mysqli_real_escape_string($conn, trim($_POST['player_name']));
    $jersey = mysqli_real_escape_string($conn, trim($_POST['jersey_no']));
    $nationality = mysqli_real_escape_string($conn, trim($_POST['nationality']));
    $category = mysqli_real_escape_string($conn, trim($_POST['category']));
    $position = mysqli_real_escape_string($conn, trim($_POST['position']));

    // Handle images
    $player_file = ""; $flag_file = "";
    if(isset($_FILES['player_image']) && $_FILES['player_image']['error'] == 0){
        $filename = time() . "_" . basename($_FILES['player_image']['name']);
        $player_file = $target_dir . $filename;
        move_uploaded_file($_FILES['player_image']['tmp_name'], $player_file);
    }
    // Flags are shared per nationality: a new upload becomes the flag for that nationality,
    // and with no upload we reuse the flag already saved for it.
    $flag_uploaded = false;
    $flag_file = flag_store_upload($_FILES['flag_image'] ?? null, $_POST['nationality']);
    if ($flag_file !== "") {
        $flag_uploaded = true;
    } else {
        $flag_file = flag_find_existing($conn, $_POST['nationality']);
    }

    if(!empty($_POST['player_id'])){
        $id = intval($_POST['player_id']);
        $update_sql = "UPDATE players SET 
                        name='$name', jersey_no='$jersey', nationality='$nationality',
                        category='$category', position='$position'";
        if($player_file != "") $update_sql .= ", picture='$player_file'";
        if($flag_file != "") $update_sql .= ", flag_picture='" . mysqli_real_escape_string($conn, $flag_file) . "'";
        $update_sql .= " WHERE id=$id";
        mysqli_query($conn, $update_sql);
    } else {
        mysqli_query($conn, "INSERT INTO players 
            (name, jersey_no, nationality, category, position, picture, flag_picture, season_id) 
            VALUES ('$name','$jersey','$nationality','$category','$position','$player_file','" . mysqli_real_escape_string($conn, $flag_file) . "', '$season_id')");
    }

    if ($flag_uploaded) flag_apply_to_nationality($conn, $_POST['nationality'], $flag_file);

    header("Location: insert_players.php?id=$season_id");
    exit;
}

// ------------------- HANDLE DELETE VIA AJAX -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $player_id = intval($_POST['player_id']);
    $result = mysqli_query($conn, "SELECT picture, flag_picture FROM players WHERE id=$player_id");
    if($result && mysqli_num_rows($result) > 0){
        $player_image = mysqli_fetch_assoc($result);
        if(!empty($player_image['picture']) && file_exists($player_image['picture'])){
            unlink($player_image['picture']);
        }
        // The flag file is shared with other players of the same nationality: only delete it if nobody else uses it.
        if(!empty($player_image['flag_picture']) && file_exists($player_image['flag_picture'])
           && flag_safe_path($player_image['flag_picture'])
           && !flag_path_in_use($conn, $player_image['flag_picture'], $player_id)){
            unlink($player_image['flag_picture']);
        }
        mysqli_query($conn, "DELETE FROM players WHERE id=$player_id");
         // Reset AUTO_INCREMENT to fill deleted IDs
        mysqli_query($conn, "ALTER TABLE players AUTO_INCREMENT = 1");
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error']);
    }
    exit;
}

// ------------------- FETCH PLAYERS -------------------
$players_result = mysqli_query($conn, "SELECT * FROM players WHERE season_id = $season_id ORDER BY id ASC");
$players = mysqli_fetch_all($players_result, MYSQLI_ASSOC);
$flag_map = flag_map($conn);
?>
<?php
$page_title = 'Players | Admin';
$nav_mode = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
<script>
// Flags already saved per nationality (lowercase name => file)
const FLAG_MAP = <?php echo json_encode($flag_map, JSON_UNESCAPED_SLASHES); ?>;
function updateFlagHint() {
    const nat = document.getElementById('pnat').value.trim().toLowerCase();
    const hint = document.getElementById('flagHint');
    const prev = document.getElementById('flagPreview');
    if (nat && FLAG_MAP[nat]) {
        prev.src = FLAG_MAP[nat]; prev.style.display = 'inline-block';
        hint.textContent = 'A flag is already saved for this nationality and will be reused. Upload a file only to replace it for everyone.';
    } else {
        prev.style.display = 'none';
        hint.textContent = nat ? 'No flag saved for this nationality yet. Upload one and it will be reused for future players.' : 'Type the nationality first.';
    }
}

// Open Add drawer
function openModal() {
    document.getElementById('playerModal').style.display='block';
    document.getElementById('playerForm').reset();
    document.getElementById('edit_player_id').value = '';
    document.querySelector('.modal-title').innerText = "Add player";
    updateFlagHint();
    document.querySelector('input[name="player_name"]').focus();
}

// Open Edit drawer
function openEditModal(playerId) {
    const players = <?php echo json_encode($players); ?>;
    const player = players.find(p => p.id == playerId);
    if (!player) return;

    document.getElementById('playerModal').style.display='block';
    document.getElementById('edit_player_id').value = player.id;
    document.querySelector('input[name="player_name"]').value = player.name;
    document.querySelector('input[name="jersey_no"]').value = player.jersey_no;
    document.querySelector('input[name="nationality"]').value = player.nationality;
    document.querySelector('select[name="category"]').value = player.category;
    document.querySelector('select[name="position"]').value = player.position;
    updateFlagHint();
    document.querySelector('.modal-title').innerText = "Edit player";
}

// Close drawer
function closeModal() { document.getElementById('playerModal').style.display='none'; }

// Delete player with AJAX
function deletePlayer(playerId, element) {
    if(!confirm("Delete this player? This can't be undone.")) return;

    const formData = new FormData();
    formData.append('action','delete');
    formData.append('player_id', playerId);

    fetch('insert_players.php', {
        method:'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success'){
            element.closest('.rcard').remove();
            toast('Player deleted', 'ok');
        } else {
            toast("Couldn't delete this player. Try again.", 'error');
        }
    })
    .catch(err => { console.error(err); toast("Couldn't reach the server. Try again.", 'error'); });
}
</script>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page">
  <div class="page-head">
    <div>
      <h1>Players</h1>
      <p class="page-sub"><?php echo count($players); ?> in the squad<?php echo !empty($_SESSION['club_name']) ? ' for ' . h($_SESSION['club_name']) : ''; ?>.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-ghost" href="dedupe_flags.php">Clean up flags</a>
      <button class="btn btn-yellow" type="button" onclick="openModal()">+ Add player</button>
    </div>
  </div>

  <?php if (!empty($players)): ?>
  <div class="roster">
    <?php foreach ($players as $p): ?>
      <article class="rcard">
        <div class="top">
          <img class="avatar lg" src="<?php echo !empty($p['picture']) ? h($p['picture']) : 'uploads/default.png'; ?>" alt="">
          <div class="info">
            <div class="nm"><?php echo h($p['name']); ?></div>
            <div class="muted" style="font-size:.88rem"><?php echo h($p['nationality']); ?></div>
          </div>
          <span class="jersey" aria-label="Shirt number"><?php echo h($p['jersey_no']); ?></span>
        </div>
        <div class="tags">
          <span class="pos pos-<?php echo h($p['category']); ?>"><?php echo h($p['category']); ?></span>
          <span class="badge"><?php echo h($p['position']); ?></span>
          <?php if (!empty($p['flag_picture'])): ?><img class="flag" src="<?php echo h($p['flag_picture']); ?>" alt="<?php echo h($p['nationality']); ?> flag" style="margin-left:auto"><?php endif; ?>
        </div>
        <div class="acts">
          <button class="btn btn-ghost btn-sm" type="button" onclick="openEditModal(<?php echo (int)$p['id']; ?>)">Edit</button>
          <button class="btn btn-danger btn-sm" type="button" onclick="deletePlayer(<?php echo (int)$p['id']; ?>, this)">Delete</button>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty">
      <h3>No players yet</h3>
      <p>Add the first player to build this season's squad.</p>
      <button class="btn" type="button" onclick="openModal()">+ Add player</button>
    </div>
  <?php endif; ?>
</main>

<!-- Drawer for Add/Edit Player -->
<div id="playerModal" class="modal">
  <div class="drawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle">
    <div class="drawer-head">
      <h2 class="modal-title" id="drawerTitle">Add player</h2>
      <button class="drawer-close" type="button" onclick="closeModal()" aria-label="Close">&times;</button>
    </div>
    <form id="playerForm" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_update">
      <input type="hidden" name="player_id" id="edit_player_id">

      <div class="field"><label for="pname">Name</label><input type="text" id="pname" name="player_name" required></div>
      <div class="form-grid">
        <div class="field"><label for="pjersey">Shirt number</label><input type="number" id="pjersey" name="jersey_no" min="0" required></div>
        <div class="field"><label for="pnat">Nationality</label><input type="text" id="pnat" name="nationality" list="natList" autocomplete="off" required oninput="updateFlagHint()"></div>
      </div>
      <div class="form-grid">
        <div class="field">
          <label for="pcat">Category</label>
          <select id="pcat" name="category" required>
            <option value="">Select category</option>
            <option value="Goalkeeper">Goalkeeper</option>
            <option value="Defender">Defender</option>
            <option value="Midfielder">Midfielder</option>
            <option value="Forward">Forward</option>
          </select>
        </div>
        <div class="field">
          <label for="ppos">Position</label>
          <select id="ppos" name="position" required>
            <option value="">Select position</option>
            <option value="GK">GK</option><option value="CB">CB</option><option value="LB">LB</option>
            <option value="RB">RB</option><option value="CM">CM</option><option value="CAM">CAM</option>
            <option value="RW">RW</option><option value="LW">LW</option><option value="ST">ST</option>
          </select>
        </div>
      </div>
      <datalist id="natList">
        <?php foreach (array_keys($flag_map) as $n): ?><option value="<?php echo h(ucwords($n)); ?>"><?php endforeach; ?>
      </datalist>
      <div class="field">
        <label for="pflag">Nationality flag <img id="flagPreview" class="flag" alt="" style="display:none;margin-left:8px;vertical-align:middle"></label>
        <input type="file" id="pflag" name="flag_image" accept="image/*">
        <span class="hint" id="flagHint">Type the nationality first.</span>
      </div>
      <div class="field"><label for="ppic">Player photo</label><input type="file" id="ppic" name="player_image" accept="image/*"><span class="hint">When editing, leave a file empty to keep the current image.</span></div>
      <button class="btn" type="submit">Save player</button>
    </form>
  </div>
</div>
</body>
</html>
