<?php
session_start();
include 'db.php';
include 'admin_sessioncheck.php'; 

$season_id = $_SESSION['club_id']; // Current season
$clubName = $_SESSION['club_name'];

// Custom thumbnails: one optional image per video, stored in uploads/videos/thumbs/.
$col = mysqli_query($conn, "SHOW COLUMNS FROM videos LIKE 'thumbnail'");
if ($col && mysqli_num_rows($col) === 0) {
    mysqli_query($conn, "ALTER TABLE videos ADD COLUMN thumbnail VARCHAR(255) NULL");
}

function thumb_delete_file($path) {
    if ($path && strpos($path, 'uploads/videos/thumbs/') === 0 && strpos($path, '..') === false && file_exists($path)) @unlink($path);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['set_thumbnail']) || isset($_POST['reset_thumbnail']))) {
    $video_id = (int)($_POST['video_id'] ?? 0);
    $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    $out = ['status' => 'error', 'message' => 'Video not found.'];

    $stmt = mysqli_prepare($conn, "SELECT id, thumbnail FROM videos WHERE id = ? AND season_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $video_id, $season_id);
    mysqli_stmt_execute($stmt);
    $v = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($v && isset($_POST['reset_thumbnail'])) {
        thumb_delete_file($v['thumbnail']);
        mysqli_query($conn, "UPDATE videos SET thumbnail = NULL WHERE id = $video_id");
        $out = ['status' => 'success', 'message' => 'Thumbnail reset to automatic.', 'thumbnail' => ''];
    } elseif ($v) {
        $f = $_FILES['thumb'] ?? null;
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        $info = ($f && ($f['error'] ?? 1) === 0 && $f['size'] <= 5 * 1024 * 1024 && is_uploaded_file($f['tmp_name'])) ? @getimagesize($f['tmp_name']) : false;
        if ($info && isset($types[$info[2]])) {
            $dir = 'uploads/videos/thumbs/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $dest = $dir . 'thumb_' . $video_id . '_' . time() . '.' . $types[$info[2]];
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                thumb_delete_file($v['thumbnail']);
                $stmt = mysqli_prepare($conn, "UPDATE videos SET thumbnail = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'si', $dest, $video_id);
                mysqli_stmt_execute($stmt);
                $out = ['status' => 'success', 'message' => 'Thumbnail updated.', 'thumbnail' => $dest];
            } else {
                $out['message'] = "The thumbnail couldn't be saved. Check that the uploads folder is writable.";
            }
        } else {
            $out['message'] = 'Choose a JPG, PNG or WebP image under 5 MB.';
        }
    }

    if ($ajax) { header('Content-Type: application/json'); echo json_encode($out); exit; }
    $_SESSION['media_flash'] = $out;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle video upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['video'])) {
    $title = mysqli_real_escape_string($conn, trim($_POST['title'])); // sanitize input
    $target_dir = "uploads/videos/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

    $video_name = basename($_FILES['video']['name']);
    $target_file = $target_dir . $video_name;

    if (move_uploaded_file($_FILES['video']['tmp_name'], $target_file)) {
        $sql = "INSERT INTO videos (season_id, filename, title) VALUES ($season_id, '$video_name', '$title')";
        mysqli_query($conn, $sql);
        header("Location: " . $_SERVER['PHP_SELF']); // refresh page
        exit;
    } else {
        $upload_error = true;
    }
}

// Handle video deletion
if (isset($_POST['delete_video'])) {
    $video_id = (int)$_POST['video_id'];
    $res = mysqli_query($conn, "SELECT filename, thumbnail FROM videos WHERE id=$video_id");
    if ($row = mysqli_fetch_assoc($res)) {
        $file = "uploads/videos/" . $row['filename'];
        if (file_exists($file)) unlink($file);
        thumb_delete_file($row['thumbnail'] ?? '');
    }
    mysqli_query($conn, "DELETE FROM videos WHERE id=$video_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle video title edit
if (isset($_POST['edit_video'])) {
    $video_id = (int)$_POST['video_id'];
    $new_title = mysqli_real_escape_string($conn, trim($_POST['new_title']));
    mysqli_query($conn, "UPDATE videos SET title='$new_title' WHERE id=$video_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$flash = $_SESSION['media_flash'] ?? null;
unset($_SESSION['media_flash']);

// Fetch only videos for this season
$result = mysqli_query($conn, "SELECT * FROM videos WHERE season_id = $season_id ORDER BY id DESC");
?>
<?php
$page_title = 'Media | Admin';
$nav_mode = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
<style>
  .thumb-tools { display: grid; gap: 8px; padding-top: 10px; border-top: 1px solid var(--line); }
  .thumb-tools .thumb-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .thumb-tools img.thumb-prev { width: 96px; aspect-ratio: 16/9; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); background: var(--board); }
  .thumb-tools input[type=file] { max-width: 100%; font-size: .85rem; }
  .thumb-tools .thumb-label { font-weight: 700; font-size: .85rem; }
  .thumb-msg { font-size: .85rem; min-height: 1.1em; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page stack">
  <div class="page-head" style="margin-bottom:0">
    <div>
      <h1>Media</h1>
      <p class="page-sub">Upload match clips for <?php echo h($clubName ?: 'this season'); ?>. They appear on the public Media tab.</p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="notice" role="<?php echo $flash['status'] === 'error' ? 'alert' : 'status'; ?>"><?php echo h($flash['message']); ?></div>
  <?php endif; ?>

  <?php if (!empty($upload_error)): ?>
    <div class="notice" role="alert">The video didn't upload. The file may be too large for the server. Try a smaller file.</div>
  <?php endif; ?>

  <section class="panel">
    <div class="panel-head"><h2>Upload a video</h2></div>
    <form method="POST" enctype="multipart/form-data" class="form-grid">
      <div class="field"><label for="vtitle">Title</label><input type="text" id="vtitle" name="title" required></div>
      <div class="field"><label for="vfile">Video file</label><input type="file" id="vfile" name="video" accept="video/*" required></div>
      <div class="field" style="align-self:end"><button class="btn" type="submit">Upload video</button></div>
    </form>
  </section>

  <section>
    <h2 style="margin-bottom:14px">Uploaded videos</h2>
    <?php if (mysqli_num_rows($result) > 0): ?>
    <div class="video-grid">
      <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <article class="vcard">
          <div class="vthumb">
            <video controls preload="metadata"<?php echo (!empty($row['thumbnail']) && file_exists($row['thumbnail'])) ? ' poster="' . h($row['thumbnail']) . '"' : ''; ?>>
              <source src="uploads/videos/<?php echo h($row['filename']); ?>" type="video/mp4">
              Your browser does not support video playback.
            </video>
          </div>
          <div class="vbody">
            <div class="vtitle"><?php echo h($row['title']); ?></div>
            <form method="POST">
              <input type="text" name="new_title" placeholder="New title" aria-label="New title for <?php echo h($row['title']); ?>" required>
              <input type="hidden" name="video_id" value="<?php echo (int)$row['id']; ?>">
              <button class="btn btn-ghost btn-sm" type="submit" name="edit_video">Rename</button>
            </form>
            <?php $hasThumb = !empty($row['thumbnail']) && file_exists($row['thumbnail']); ?>
            <div class="thumb-tools">
              <span class="thumb-label">Thumbnail</span>
              <div class="thumb-row">
                <img class="thumb-prev" alt="" <?php echo $hasThumb ? 'src="' . h($row['thumbnail']) . '"' : 'hidden'; ?>>
                <span class="muted" style="font-size:.85rem"><?php echo $hasThumb ? 'Custom thumbnail' : 'Automatic (a frame from the video)'; ?></span>
              </div>
              <button class="btn btn-ghost btn-sm" type="button" data-use-frame data-video-id="<?php echo (int)$row['id']; ?>">Use current video frame</button>
              <form method="POST" enctype="multipart/form-data" class="thumb-row">
                <input type="hidden" name="video_id" value="<?php echo (int)$row['id']; ?>">
                <input type="file" name="thumb" accept="image/jpeg,image/png,image/webp" aria-label="Thumbnail image for <?php echo h($row['title']); ?>" required>
                <button class="btn btn-ghost btn-sm" type="submit" name="set_thumbnail" value="1">Upload image</button>
              </form>
              <?php if ($hasThumb): ?>
              <form method="POST">
                <input type="hidden" name="video_id" value="<?php echo (int)$row['id']; ?>">
                <button class="btn btn-ghost btn-sm" type="submit" name="reset_thumbnail" value="1">Reset to automatic</button>
              </form>
              <?php endif; ?>
              <div class="thumb-msg" aria-live="polite"></div>
            </div>
            <form method="POST" data-confirm="Delete this video? This can't be undone.">
              <input type="hidden" name="video_id" value="<?php echo (int)$row['id']; ?>">
              <button class="btn btn-danger btn-sm" type="submit" name="delete_video">Delete video</button>
            </form>
          </div>
        </article>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
      <div class="empty"><h3>No videos yet</h3><p>Upload a clip above and it will show up here and on the public site.</p></div>
    <?php endif; ?>
  </section>
</main>

<script>
// "Use current video frame": pause the video on the frame you like, then click the button.
document.querySelectorAll('[data-use-frame]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var card = btn.closest('.vcard'), video = card.querySelector('video'), msg = card.querySelector('.thumb-msg');
    if (!video.videoWidth) { msg.textContent = 'Play or scrub the video to the frame you want first.'; return; }
    var w = Math.min(video.videoWidth, 1280), h = Math.round(video.videoHeight * w / video.videoWidth);
    var canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h;
    try {
      canvas.getContext('2d').drawImage(video, 0, 0, w, h);
    } catch (e) { msg.textContent = "This browser couldn't capture the frame. Upload an image instead."; return; }
    btn.disabled = true; msg.textContent = 'Saving thumbnail...';
    canvas.toBlob(function (blob) {
      if (!blob) { btn.disabled = false; msg.textContent = "Couldn't capture the frame. Upload an image instead."; return; }
      var fd = new FormData();
      fd.append('set_thumbnail', '1');
      fd.append('video_id', btn.dataset.videoId);
      fd.append('thumb', blob, 'frame.jpg');
      fetch(location.pathname, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          btn.disabled = false;
          msg.textContent = d.message;
          if (d.status === 'success') {
            var url = d.thumbnail + '?t=' + Date.now();
            video.poster = url;
            var prev = card.querySelector('.thumb-prev');
            prev.src = url; prev.hidden = false;
          }
        })
        .catch(function () { btn.disabled = false; msg.textContent = "Couldn't reach the server. Try again."; });
    }, 'image/jpeg', 0.85);
  });
});
</script>
</body>
</html>
