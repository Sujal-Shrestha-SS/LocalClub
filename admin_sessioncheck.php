<?php
// Guards admin pages. Visitors who aren't logged in see a short message,
// then go back to the public site.
if (!isset($_SESSION['admin'])) {
    $page_title = 'Login required | eFootball League';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/head.php'; ?>
<meta http-equiv="refresh" content="4;url=index.php">
</head>
<body>
<main class="center-screen">
  <div class="panel auth-card" style="text-align:center">
    <h2>Admin login required</h2>
    <p class="muted" style="margin:0">This page is only for admins. Taking you back to the public site.</p>
    <div class="btn-row" style="justify-content:center">
      <a class="btn" href="admin_login.php">Log in</a>
      <a class="btn btn-ghost" href="index.php">Go to the public site</a>
    </div>
  </div>
</main>
</body>
</html>
<?php
    exit();
}
