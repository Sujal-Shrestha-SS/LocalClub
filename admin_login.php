<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Simple hardcoded login (you can later connect this to DB)
    if ($username === 'admin' && $password === 'password123') {
        $_SESSION['admin'] = true;
        header("Location: home.php");
        exit;
    } else {
        $error = "Invalid login credentials!";
    }
}
?>
<?php $page_title = 'Admin login | eFootball League'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
</head>
<body>
<div class="split">
  <section class="split-art">
    <a class="brand" href="index.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3v18"/><circle cx="12" cy="12" r="2.6"/></svg>
      eFootball League
    </a>
    <div>
      <h1>Run the season.</h1>
      <p>Add fixtures, enter results, manage the squad and publish match footage.</p>
    </div>
    <span></span>
  </section>

  <section class="split-form">
    <div class="auth-card">
      <div>
        <h2>Admin login</h2>
        <p class="muted" style="margin:6px 0 0">Sign in to edit seasons, fixtures and players.</p>
      </div>

      <?php if (!empty($error)): ?><div class="notice" role="alert"><?php echo h($error); ?></div><?php endif; ?>

      <form method="POST" class="stack" style="--gap:16px">
        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" autocomplete="username" required autofocus>
        </div>
        <div class="field" style="margin-top:16px">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn" type="submit" style="width:100%; margin-top:20px">Log in</button>
      </form>

      <a class="back-link" href="index.php" style="margin:0">← Back to the public site</a>
    </div>
  </section>
</div>
</body>
</html>
