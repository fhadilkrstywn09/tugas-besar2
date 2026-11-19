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
$url_daftar = 'lihat_kelas_guru.php?mapel_id=' . $mapel_id . '&kelas=' . urlencode($kelas);

// Cek token CSRF
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
  $_SESSION['flash_nilai'] = ['tipe' => 'danger', 'pesan' => 'Sesi form tidak valid. Muat ulang halaman lalu coba lagi.', 'lama' => null];
  header('Location: ' . $url_daftar);
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

try {
  $stmt = mysqli_prepare($koneksi, "DELETE FROM nilai_rapor WHERE siswa_id = ? AND mapel_id = ?");
  mysqli_stmt_bind_param($stmt, 'ii', $siswa_id, $mapel_id);
  mysqli_stmt_execute($stmt);
  $_SESSION['flash_nilai'] = ['tipe' => 'success', 'pesan' => 'Nilai ' . $siswa['nama'] . ' berhasil dihapus.', 'lama' => null];
} catch (Throwable $ex) {
  $pesan = 'Nilai gagal dihapus. Coba lagi.';
  if (defined('DEBUG') && DEBUG) { $pesan .= ' [' . $ex->getMessage() . ']'; }
  $_SESSION['flash_nilai'] = ['tipe' => 'danger', 'pesan' => $pesan, 'lama' => null];
}
header('Location: ' . $url_daftar);
exit;
