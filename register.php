<?php
require_once 'auth.php';
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$error = '';
$old = ['nis' => '', 'nama' => '', 'kelas' => '', 'jenis_kelamin' => '', 'username' => ''];

// ================= PROSES SIMPAN (saat form dikirim) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nis      = trim($_POST['nis'] ?? '');
  $nama     = trim($_POST['nama'] ?? '');
  $kelas    = trim($_POST['kelas'] ?? '');
  $jk       = $_POST['jenis_kelamin'] ?? '';
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $role     = 'siswa'; // register publik hanya untuk siswa

  $old = ['nis' => $nis, 'nama' => $nama, 'kelas' => $kelas, 'jenis_kelamin' => $jk, 'username' => $username];

  if ($nis === '' || $nama === '' || $kelas === '') {
    $error = 'NIS, nama, dan kelas wajib diisi.';
  } elseif (!in_array($jk, ['L', 'P'], true)) {
    $error = 'Jenis kelamin wajib dipilih.';
  } elseif ($username === '' || $password === '') {
    $error = 'Username dan password wajib diisi.';
  } else {
    try {
      $cek = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? LIMIT 1");
      mysqli_stmt_bind_param($cek, 's', $username);
      mysqli_stmt_execute($cek);
      $username_ada = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

      $cek = mysqli_prepare($koneksi, "SELECT id FROM siswa WHERE nis = ? LIMIT 1");
      mysqli_stmt_bind_param($cek, 's', $nis);
      mysqli_stmt_execute($cek);
      $nis_ada = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

      if ($username_ada) {
        $error = 'Username sudah dipakai.';
      } elseif ($nis_ada) {
        $error = 'NIS sudah terdaftar.';
      } else {
        mysqli_begin_transaction($koneksi);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($koneksi, "INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'sss', $username, $hash, $role);
        mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($koneksi);

        $stmt = mysqli_prepare($koneksi, "INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssssi', $nis, $nama, $kelas, $jk, $user_id);
        mysqli_stmt_execute($stmt);

        mysqli_commit($koneksi);
        echo "<script>alert('Registrasi berhasil, silakan login');window.location='login.php';</script>";
        exit;
      }
    } catch (Throwable $e) {
      try { mysqli_rollback($koneksi); } catch (Throwable $e2) {}
      $error = DEBUG ? 'Gagal mendaftar: ' . $e->getMessage() : 'Terjadi kesalahan pada server. Coba lagi.';
    }
  }
}

function lama($k) { global $old; return htmlspecialchars($old[$k] ?? ''); }
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="tema.css?v=<?= @filemtime(__DIR__ . '/tema.css') ?: 1 ?>" rel="stylesheet">
</head>
<body style="background:#f8f9fa;">

<div class="container mt-4 mb-4">
  <div class="row">
    <div class="col-12 col-md-10 mx-auto">

      <div class="card shadow-sm" style="border-radius:12px; border:1px solid #ddd;">

        <div class="card-header" style="background:#f3f3f3; padding:18px 25px;">
          <h3 style="margin:0; font-weight:600;">Form Register User (Siswa)</h3>
        </div>

        <div class="card-body" style="padding:30px;">

          <?php if ($error !== '') { ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php } ?>

          <form action="register.php" method="POST">
            <h3>Data Profil User</h3>

            <div class="mb-4">
              <label class="form-label">NIS:</label>
              <input type="text" name="nis" class="form-control form-control-lg"
              placeholder="Nomor Induk Siswa | Contoh: 1234567890" value="<?= lama('nis') ?>" required>
            </div>

            <div class="mb-4">
              <label class="form-label">Nama Lengkap:</label>
              <input type="text" name="nama" class="form-control form-control-lg"
              placeholder="Nama Lengkap Siswa | Contoh: Ujang Asep" value="<?= lama('nama') ?>" required>
            </div>

            <div class="mb-4">
              <label class="form-label">Kelas:</label>
              <input type="text" name="kelas" class="form-control form-control-lg"
              placeholder="Kelas Siswa | Contoh: XI-RPL-1" value="<?= lama('kelas') ?>" required>
            </div>

            <div class="mb-4">
              <label class="form-label">Jenis Kelamin:</label><br>
              <input type="radio" name="jenis_kelamin" value="L" <?= ($old['jenis_kelamin'] ?? '') === 'L' ? 'checked' : '' ?> required> Laki-laki <br>
              <input type="radio" name="jenis_kelamin" value="P" <?= ($old['jenis_kelamin'] ?? '') === 'P' ? 'checked' : '' ?>> Perempuan
            </div>

            <hr style="margin:35px 0; border-top:1px solid #ccc;">

            <h4 style="font-weight:600; margin-bottom:25px;">Data Kredensial Akun</h4>

            <div class="mb-4">
              <label class="form-label">Username:</label>
              <input type="text" name="username" class="form-control form-control-lg"
              placeholder="Username Akun | Contoh: ujang123" value="<?= lama('username') ?>" autocomplete="username" required>
            </div>

            <div class="mb-4">
              <label class="form-label">Password Akun:</label>
              <input type="password" name="password" class="form-control form-control-lg"
              placeholder="Password Akun | Contoh: password123" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Data</button>
            <a href="login.php" class="btn btn-secondary">Kembali</a>
          </form>

        </div>
      </div>

    </div>
  </div>
</div>

</body>
</html>
