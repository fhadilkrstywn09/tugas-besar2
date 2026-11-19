<?php
require_once 'auth.php';
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Halaman ini khusus guru
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] !== 'guru') { header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: daftar_kelas_guru.php'); exit; }

$uid      = (int) $_SESSION['user_id'];
$mapel_id = (int) ($_POST['mapel_id'] ?? 0);
$siswa_id = (int) ($_POST['siswa_id'] ?? 0);
$kelas    = trim($_POST['kelas'] ?? '');
$kkm_raw  = trim((string) ($_POST['kkm'] ?? ''));
$p_raw    = trim((string) ($_POST['pengetahuan'] ?? ''));
$k_raw    = trim((string) ($_POST['keterampilan'] ?? ''));
$lama     = [$kkm_raw, $p_raw, $k_raw];
$form        = 'form_nilai.php?mapel_id=' . $mapel_id . '&siswa_id=' . $siswa_id . '&kelas=' . urlencode($kelas);
$url_daftar  = 'lihat_kelas_guru.php?mapel_id=' . $mapel_id . '&kelas=' . urlencode($kelas);

// Cek token CSRF
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
  $_SESSION['flash_nilai'] = ['tipe' => 'danger', 'pesan' => 'Sesi form tidak valid. Muat ulang halaman lalu coba lagi.', 'lama' => $lama];
  header('Location: ' . $form);
  exit;
}

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

// Kolom di nilai_rapor bertipe int: harus angka bulat 0-100
$kkm = null;
if (preg_match('/^\d{1,3}$/', $kkm_raw) && (int) $kkm_raw <= 100) { $kkm = (int) $kkm_raw; }
$p = null;
if (preg_match('/^\d{1,3}$/', $p_raw) && (int) $p_raw <= 100) { $p = (int) $p_raw; }
$k = null;
if (preg_match('/^\d{1,3}$/', $k_raw) && (int) $k_raw <= 100) { $k = (int) $k_raw; }

if ($kkm === null || $p === null || $k === null) {
  $_SESSION['flash_nilai'] = ['tipe' => 'danger', 'pesan' => 'KKM, Pengetahuan, dan Keterampilan wajib diisi dengan angka bulat 0 sampai 100.', 'lama' => $lama];
  header('Location: ' . $form);
  exit;
}

// Keterangan: >=90 Sangat Baik, >=KKM Baik, >=KKM-15 Cukup, selain itu Kurang
$nilai_akhir = ($p + $k) / 2;
if ($nilai_akhir >= 90) {
  $keterangan = 'Sangat Baik';
} elseif ($nilai_akhir >= $kkm) {
  $keterangan = 'Baik';
} elseif ($nilai_akhir >= $kkm - 15) {
  $keterangan = 'Cukup';
} else {
  $keterangan = 'Kurang';
}

try {
  $stmt = mysqli_prepare($koneksi, "SELECT COUNT(*) FROM nilai_rapor WHERE siswa_id = ? AND mapel_id = ?");
  mysqli_stmt_bind_param($stmt, 'ii', $siswa_id, $mapel_id);
  mysqli_stmt_execute($stmt);
  $ada = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] > 0;

  if ($ada) {
    $stmt = mysqli_prepare($koneksi, "UPDATE nilai_rapor SET nilai_kkm = ?, nilai_pengetahuan = ?, nilai_keterampilan = ?, Keterangan = ?
                                      WHERE siswa_id = ? AND mapel_id = ?");
    mysqli_stmt_bind_param($stmt, 'iiisii', $kkm, $p, $k, $keterangan, $siswa_id, $mapel_id);
    mysqli_stmt_execute($stmt);
  } else {
    try {
      $stmt = mysqli_prepare($koneksi, "INSERT INTO nilai_rapor (siswa_id, mapel_id, nilai_kkm, nilai_pengetahuan, nilai_keterampilan, Keterangan)
                                        VALUES (?, ?, ?, ?, ?, ?)");
      mysqli_stmt_bind_param($stmt, 'iiiiis', $siswa_id, $mapel_id, $kkm, $p, $k, $keterangan);
      mysqli_stmt_execute($stmt);
    } catch (mysqli_sql_exception $e1) {
      // Jika kolom id di database tidak auto increment, isi id secara manual (skema tidak diubah)
      if (stripos($e1->getMessage(), "'id'") === false) { throw $e1; }
      $id_baru = (int) mysqli_fetch_row(mysqli_query($koneksi, "SELECT COALESCE(MAX(id), 0) + 1 FROM nilai_rapor"))[0];
      $stmt = mysqli_prepare($koneksi, "INSERT INTO nilai_rapor (id, siswa_id, mapel_id, nilai_kkm, nilai_pengetahuan, nilai_keterampilan, Keterangan)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
      mysqli_stmt_bind_param($stmt, 'iiiiiis', $id_baru, $siswa_id, $mapel_id, $kkm, $p, $k, $keterangan);
      mysqli_stmt_execute($stmt);
    }
  }
} catch (Throwable $ex) {
  $pesan = 'Nilai gagal disimpan. Coba lagi.';
  if (defined('DEBUG') && DEBUG) { $pesan .= ' [' . $ex->getMessage() . ']'; }
  $_SESSION['flash_nilai'] = ['tipe' => 'danger', 'pesan' => $pesan, 'lama' => $lama];
  header('Location: ' . $form);
  exit;
}

$_SESSION['flash_nilai'] = ['tipe' => 'success', 'pesan' => 'Nilai ' . $siswa['nama'] . ' berhasil disimpan.', 'lama' => null];
header('Location: ' . $url_daftar);
exit;
