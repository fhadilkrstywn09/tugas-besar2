<?php
require_once 'auth.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$uid = (int) $_SESSION['user_id'];

// Ambil data terbaru dari database (bukan dari session)
$stmt = mysqli_prepare($koneksi, "SELECT id, username, role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$user || !in_array($user['role'], ROLE_BOLEH, true)) {
  header('Location: logout.php');
  exit;
}
$role = $user['role'];

// Admin tidak punya tabel profil, guru -> tabel guru, siswa -> tabel siswa
$profil = [];
if ($role === 'guru' || $role === 'siswa') {
  $stmt = mysqli_prepare($koneksi, "SELECT * FROM $role WHERE user_id = ? LIMIT 1");
  mysqli_stmt_bind_param($stmt, 'i', $uid);
  mysqli_stmt_execute($stmt);
  $profil = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
}

$nama = $profil['nama'] ?? ($role === 'admin' ? 'Admin' : $user['username']);
$inisial = mb_strtoupper(mb_substr($nama, 0, 1));

// Jenis kelamin: ubah L/P jadi teks lengkap bila perlu
$jk = $profil['jenis_kelamin'] ?? '';
if (strtoupper($jk) === 'L') { $jk = 'Laki-laki'; }
if (strtoupper($jk) === 'P') { $jk = 'Perempuan'; }

// Data yang ditampilkan di bagian "Data <Role>"
if ($role === 'guru') {
  $data = ['NIP' => $profil['nip'] ?? '', 'Nama' => $nama, 'Jenis Kelamin' => $jk];
} elseif ($role === 'siswa') {
  $data = ['NIS' => $profil['nis'] ?? '', 'Nama' => $nama, 'Kelas' => $profil['kelas'] ?? '', 'Jenis Kelamin' => $jk];
} else {
  $data = ['Nama' => $nama];
}

$warna_role = ['admin' => 'danger', 'guru' => 'success', 'siswa' => 'info'][$role] ?? 'secondary';

// Tombol Kembali menuju halaman pertama sesuai role
if ($role === 'admin') {
  $halaman_awal = 'daftar_user.php';
} elseif ($role === 'guru') {
  $halaman_awal = 'daftar_guru.php';
} else {
  $halaman_awal = 'daftar_siswa.php';
}

require_once 'navbar.php';
?>
<div class="container py-4" style="max-width: 720px;">
  <h2 class="fw-bold mb-1">Profile</h2>
  <p class="text-secondary mb-4">Informasi akun yang sedang login</p>

  <div class="card shadow-sm border-0">

    <!-- Cover + avatar + nama -->
    <div class="profil-cover"></div>
    <div class="card-body text-center pt-0 pb-4">
      <div class="profil-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3"
           style="width: 118px; height: 118px; font-size: 3.5rem;">
        <?= htmlspecialchars($inisial) ?>
      </div>
      <h4 class="fw-bold mb-2"><?= htmlspecialchars($nama) ?></h4>
      <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($role) ?></span>
    </div>

    <hr class="my-0">

    <!-- Informasi akun -->
    <div class="card-body">
      <h5 class="fw-bold mb-4">Informasi Akun</h5>

      <div class="mb-3">
        <div class="text-secondary">Username</div>
        <div class="fw-bold"><?= htmlspecialchars($user['username']) ?></div>
      </div>

      <div class="mb-1">
        <div class="text-secondary">Role</div>
        <span class="badge bg-<?= $warna_role ?>"><?= htmlspecialchars(ucfirst($role)) ?></span>
      </div>
    </div>

    <hr class="my-0">

    <!-- Data sesuai role -->
    <div class="card-body">
      <h5 class="fw-bold mb-4">Data <?= htmlspecialchars(ucfirst($role)) ?></h5>

      <?php foreach ($data as $label => $nilai) { ?>
        <div class="mb-3">
          <div class="text-secondary"><?= htmlspecialchars($label) ?></div>
          <div class="fw-bold"><?= htmlspecialchars($nilai !== '' ? $nilai : '-') ?></div>
        </div>
      <?php } ?>
    </div>

    <hr class="my-0">

    <!-- Tombol -->
    <div class="card-body d-flex justify-content-end gap-2">
      <a href="<?= htmlspecialchars($halaman_awal) ?>" class="btn btn-secondary">Kembali</a>
      <a href="pengaturan.php" class="btn btn-primary">Edit Profile</a>
    </div>

  </div>
</div>
<?php require_once 'footer.php'; ?>
