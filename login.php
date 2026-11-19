<?php
require_once 'auth.php';
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$error = $_SESSION['login_error'] ?? '';
$username_lama = $_SESSION['login_username'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_username']);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="tema.css?v=<?= @filemtime(__DIR__ . '/tema.css') ?: 1 ?>" rel="stylesheet">
</head>
<body class="login-page">
  <div class="container min-vh-100 d-flex align-items-center justify-content-center px-3">
    <div class="card login-card w-100" style="max-width: 440px;">
      <div class="card-body p-4">
        <div class="login-logo">S</div>
        <h3 class="text-center mb-1">SIPN</h3>
        <p class="text-center text-secondary mb-4">Silakan login untuk melanjutkan</p>

        <?php if ($error !== '') { ?>
          <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php } ?>

        <form method="POST" action="aksi_login.php">
          <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" value="<?= htmlspecialchars($username_lama) ?>" autocomplete="username" required>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
        </form>

        <p class="text-center mt-3 mb-0">Belum memiliki akun? <a href="register.php" class="text-decoration-none">Register</a></p>
      </div>
    </div>
  </div>
</body>
</html>
