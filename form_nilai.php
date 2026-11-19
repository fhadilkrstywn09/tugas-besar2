<?php
require_once 'auth.php';
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Halaman ini khusus guru
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] !== 'guru') { header('Location: index.php'); exit; }

$uid      = (int) $_SESSION['user_id'];
$mapel_id = (int) ($_GET['mapel_id'] ?? 0);
$siswa_id = (int) ($_GET['siswa_id'] ?? 0);
$kelas    = trim($_GET['kelas'] ?? '');
$url_daftar = 'lihat_kelas_guru.php?mapel_id=' . $mapel_id . '&kelas=' . urlencode($kelas);

// Mapel harus milik guru yang sedang login
$stmt = mysqli_prepare($koneksi, "SELECT mp.id, mp.`nama-mapel` AS nama FROM mapel mp JOIN guru g ON mp.guru_id = g.id
                                  WHERE mp.id = ? AND g.user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $mapel_id, $uid);
mysqli_stmt_execute($stmt);
$mapel = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Siswa harus memang belajar mapel ini di kelas ini
$siswa = null;
if ($mapel) {
  $stmt = mysqli_prepare($koneksi, "SELECT DISTINCT s.id, s.nis, s.nama FROM mengajar m JOIN siswa s ON m.siswa_id = s.id
                                    WHERE m.mapel_id = ? AND s.kelas = ? AND s.id = ? LIMIT 1");
  mysqli_stmt_bind_param($stmt, 'isi', $mapel_id, $kelas, $siswa_id);
  mysqli_stmt_execute($stmt);
  $siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}
if (!$mapel || !$siswa) { header('Location: daftar_kelas_guru.php'); exit; }

// Token CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

// Pesan sekali tampil (flash)
$flash = $_SESSION['flash_nilai'] ?? null;
unset($_SESSION['flash_nilai']);

$n = null;
try {
  $stmt = mysqli_prepare($koneksi, "SELECT nilai_kkm, nilai_pengetahuan, nilai_keterampilan FROM nilai_rapor
                                    WHERE siswa_id = ? AND mapel_id = ? LIMIT 1");
  mysqli_stmt_bind_param($stmt, 'ii', $siswa_id, $mapel_id);
  mysqli_stmt_execute($stmt);
  $n = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
} catch (Throwable $ex) {
  $flash = ['tipe' => 'danger', 'pesan' => 'Data nilai tidak dapat dimuat. Periksa tabel nilai_rapor di database.', 'lama' => null];
}
$sudah = ($n !== null);

// Isi form: input sebelumnya (jika ada error), atau nilai tersimpan, atau KKM bawaan
$val_kkm = $flash['lama'][0] ?? ($n ? (string) (int) $n['nilai_kkm'] : (string) KKM_DEFAULT);
$val_p   = $flash['lama'][1] ?? ($n ? (string) (int) $n['nilai_pengetahuan'] : '');
$val_k   = $flash['lama'][2] ?? ($n ? (string) (int) $n['nilai_keterampilan'] : '');

require_once 'navbar.php';
?>
<div class="container mt-4" style="max-width: 720px;">
  <div class="card">
    <div class="card-header">
      <h4><?= $sudah ? 'Edit' : 'Tambah' ?> Nilai Siswa</h4>
    </div>
    <div class="card-body">

      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="text-secondary">Nama Siswa</div>
          <div class="fw-bold"><?= htmlspecialchars($siswa['nama']) ?></div>
        </div>
        <div class="col-5">
          <div class="text-secondary">Kelas</div>
          <div class="fw-bold"><?= htmlspecialchars($kelas) ?></div>
        </div>
        <div class="col-7">
          <div class="text-secondary">Mata Pelajaran</div>
          <div class="fw-bold"><?= htmlspecialchars($mapel['nama']) ?></div>
        </div>
      </div>

      <?php if ($flash) { ?>
        <div class="alert alert-<?= htmlspecialchars($flash['tipe']) ?> py-2"><?= htmlspecialchars($flash['pesan']) ?></div>
      <?php } ?>

      <form method="POST" action="aksi_simpan_nilai.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="mapel_id" value="<?= (int) $mapel_id ?>">
        <input type="hidden" name="siswa_id" value="<?= (int) $siswa_id ?>">
        <input type="hidden" name="kelas" value="<?= htmlspecialchars($kelas) ?>">

        <div class="mb-3">
          <label for="kkm" class="form-label mb-1">KKM (0-100):</label>
          <input type="number" class="form-control" id="kkm" name="kkm"
                 value="<?= htmlspecialchars($val_kkm) ?>" min="0" max="100" step="1" inputmode="numeric" required>
        </div>

        <div class="mb-3">
          <label for="pengetahuan" class="form-label mb-1">Nilai Pengetahuan (0-100):</label>
          <input type="number" class="form-control" id="pengetahuan" name="pengetahuan"
                 value="<?= htmlspecialchars($val_p) ?>" min="0" max="100" step="1" inputmode="numeric" required>
        </div>

        <div class="mb-3">
          <label for="keterampilan" class="form-label mb-1">Nilai Keterampilan (0-100):</label>
          <input type="number" class="form-control" id="keterampilan" name="keterampilan"
                 value="<?= htmlspecialchars($val_k) ?>" min="0" max="100" step="1" inputmode="numeric" required>
        </div>

        <p class="text-secondary small">Nilai Akhir dan Keterangan dihitung otomatis dari kedua nilai dan KKM.</p>

        <button type="submit" class="btn btn-primary">Simpan Nilai</button>
        <a href="<?= htmlspecialchars($url_daftar) ?>" class="btn btn-secondary">Kembali</a>
      </form>

    </div>
  </div>
</div>
<?php require_once 'footer.php'; ?>
