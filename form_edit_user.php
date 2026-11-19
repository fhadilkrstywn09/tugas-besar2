<?php
require_once 'auth.php';
// Halaman ini khusus admin
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] !== 'admin') { header('Location: index.php'); exit; }
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = (int) ($_GET['id'] ?? 0);
$stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$user) { echo "<script>alert('User tidak ditemukan');window.location='daftar_user.php';</script>"; exit; }
$role = $user['role'];
$kembali = ['siswa' => 'daftar_siswa.php', 'guru' => 'daftar_guru.php'][$role] ?? 'daftar_user.php';

// data profil: guru -> tabel guru, siswa -> tabel siswa, admin -> tidak ada
$profil = [];
if ($role == 'guru' || $role == 'siswa') {
  $stmt = mysqli_prepare($koneksi, "SELECT * FROM $role WHERE user_id = ?");
  mysqli_stmt_bind_param($stmt, 'i', $id);
  mysqli_stmt_execute($stmt);
  $profil = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
}
$semua = ['guru' => ['nip' => 'NIP', 'nama' => 'Nama Lengkap'], 'siswa' => ['nis' => 'NIS', 'nama' => 'Nama Lengkap', 'kelas' => 'Kelas']];
$isi = $semua[$role] ?? [];
$jk = $role != 'admin';
$aksi = ['siswa' => 'editsiswa', 'guru' => 'editguru'][$role] ?? 'edituser';
$error = '';

include 'navbar.php';
include 'edit_tampil.php';
include 'footer.php';
