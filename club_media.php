<?php
session_start();
include 'db.php';

$season_id = $_SESSION['club_id']; // Current season
$clubName = $_SESSION['club_name'];

// Fetch only videos for this season
$result = mysqli_query($conn, "SELECT * FROM videos WHERE season_id = $season_id ORDER BY id DESC");
?>
<?php
$page_title = $clubName . ' | Media';
$nav_mode = 'public';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
<style>
  .vthumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  /* Close button: white block with a red cross (the icon was white-on-white before). */
  .lightbox .lb-close {
    width: 40px; height: 40px; padding: 0; display: grid; place-items: center; flex: none;
    background: #fff; color: var(--card-red); border: 0; border-radius: var(--r-sm); cursor: pointer;
  }
  .lightbox .lb-close:hover { background: #fde8eb; }
  .lightbox .lb-close:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page">
  <div class="page-head">
    <div>
      <h1>Media</h1>
      <p class="page-sub">Highlights and clips from <?php echo h($clubName ?: 'this season'); ?>.</p>
    </div>
  </div>

  <?php if (mysqli_num_rows($result) > 0): ?>
    <div class="video-grid">
      <?php while($row = mysqli_fetch_assoc($result)): ?>
        <?php $src = 'uploads/videos/' . rawurlencode($row['filename']); ?>
        <article class="vcard">
          <button type="button" class="vthumb" data-src="<?php echo h($src); ?>" data-title="<?php echo h($row['title']); ?>" aria-label="Play <?php echo h($row['title']); ?>">
            <?php $thumb = (!empty($row['thumbnail']) && file_exists($row['thumbnail'])) ? $row['thumbnail'] : ''; ?>
            <?php if ($thumb): ?>
              <img src="<?php echo h($thumb); ?>" alt="" loading="lazy">
            <?php else: ?>
              <video src="<?php echo h($src); ?>#t=0.6" preload="metadata" muted playsinline tabindex="-1"></video>
            <?php endif; ?>
            <span class="play"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l13-7.5z"/></svg></span>
          </button>
          <div class="vbody"><div class="vtitle"><?php echo h($row['title']); ?></div></div>
        </article>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="empty"><h3>No videos yet</h3><p>Clips for this season will appear here once they're uploaded.</p></div>
  <?php endif; ?>
</main>

<dialog class="lightbox" id="lightbox" aria-label="Video player">
  <div class="lb-bar"><span id="lbTitle"></span><button type="button" class="lb-close" id="lbClose" aria-label="Close video" title="Close"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <video id="lbVideo" controls playsinline></video>
</dialog>

<script>
(function () {
  var dlg = document.getElementById('lightbox'), vid = document.getElementById('lbVideo');
  document.querySelectorAll('.vthumb').forEach(function (b) {
    b.addEventListener('click', function () {
      vid.src = b.dataset.src;
      document.getElementById('lbTitle').textContent = b.dataset.title;
      if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
      vid.play().catch(function () {});
    });
  });
  function close() { vid.pause(); vid.removeAttribute('src'); vid.load(); if (dlg.close) dlg.close(); else dlg.removeAttribute('open'); }
  document.getElementById('lbClose').addEventListener('click', close);
  dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });
  dlg.addEventListener('cancel', function () { vid.pause(); });
})();
</script>
</body>
</html>
