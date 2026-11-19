<?php
require_once 'auth.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Hanya akun yang sedang login yang bisa diubah (tidak pakai id dari URL)
$uid  = (int) $_SESSION['user_id'];
$stmt = mysqli_prepare($koneksi, "SELECT id, username, role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$user) {
  header('Location: logout.php');
  exit;
}

// Token CSRF
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$sukses = $_SESSION['flash_sukses'] ?? '';
unset($_SESSION['flash_sukses']);
$username_form = $user['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $username_form = $username;

  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    $error = 'Sesi form tidak valid. Muat ulang halaman lalu coba lagi.';
  } elseif ($username === '') {
    $error = 'Username tidak boleh kosong.';
  } elseif (mb_strlen($username) > 50) {
    $error = 'Username maksimal 50 karakter.';
  } elseif ($password !== '' && strlen($password) < 6) {
    $error = 'Password baru minimal 6 karakter.';
  } else {
    try {
      // Username tidak boleh sama dengan akun lain
      $stmt = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
      mysqli_stmt_bind_param($stmt, 'si', $username, $uid);
      mysqli_stmt_execute($stmt);
      $dipakai = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
      if ($dipakai) {
        $error = 'Username sudah dipakai akun lain.';
      } else {
        if ($password !== '') {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = mysqli_prepare($koneksi, "UPDATE users SET username = ?, password = ? WHERE id = ?");
          mysqli_stmt_bind_param($stmt, 'ssi', $username, $hash, $uid);
          mysqli_stmt_execute($stmt);
        } else {
          $stmt = mysqli_prepare($koneksi, "UPDATE users SET username = ? WHERE id = ?");
          mysqli_stmt_bind_param($stmt, 'si', $username, $uid);
          mysqli_stmt_execute($stmt);
        }

        $_SESSION['username'] = $username;
        if ($password !== '') {
          session_regenerate_id(true); // password berubah -> perbarui session
        }
        $_SESSION['flash_sukses'] = 'Data akun berhasil disimpan.';
        header('Location: pengaturan.php');
        exit;
      }
    } catch (Throwable $ex) {
      $error = 'Terjadi kesalahan pada server. Coba lagi.';
    }
  }
}

require_once 'navbar.php';
?>
<div class="container mt-4" style="max-width: 720px;">
  <div class="card">
    <div class="card-header">
      <h4 class="mb-0">Form Edit User (<?= htmlspecialchars(ucfirst($user['role'])) ?>)</h4>
    </div>
    <div class="card-body">

      <?php if ($sukses !== '') { ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($sukses) ?></div>
      <?php } ?>
      <?php if ($error !== '') { ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
      <?php } ?>

      <h4 class="mb-3">Data Kredensial Akun</h4>

      <form method="POST" action="pengaturan.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

        <div class="mb-4">
          <label for="username" class="form-label mb-1">Username:</label>
          <input type="text" class="form-control" id="username" name="username"
                 value="<?= htmlspecialchars($username_form) ?>" maxlength="50" autocomplete="username" required>
        </div>

        <div class="mb-4">
          <label for="password" class="form-label mb-1">Password Akun Baru:</label>
          <input type="password" class="form-control" id="password" name="password"
                 placeholder="Password baru | jika tidak diubah, kosongkan" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">Simpan Data</button>
        <a href="profile.php" class="btn btn-secondary">Kembali</a>
      </form>

    </div>
  </div>
</div>
<?php require_once 'footer.php'; ?>
