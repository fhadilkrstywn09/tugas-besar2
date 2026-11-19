<?php
require_once 'auth.php';
// Halaman ini khusus admin
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] !== 'admin') { header('Location: index.php'); exit; }
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$aksi = $_GET['aksi'] ?? '';
$tujuan = [
  'editsiswa' => 'daftar_siswa.php',
  'editguru'  => 'daftar_guru.php',
  'edituser'  => 'daftar_user.php',   // admin
];

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($tujuan[$aksi])) {
  echo "<script>alert('Akses tidak diizinkan'); window.location.href='daftar_user.php';</script>";
  exit;
}
$kembali = $tujuan[$aksi];

// menangkap data dari form edit user
$user_id = (int) ($_POST['user_id'] ?? 0);
$user    = trim($_POST['username'] ?? '');
$pass    = $_POST['password'] ?? '';
$nama    = $_POST['nama_lengkap'] ?? '';
$jk      = $_POST['jenis_kelamin'] ?? '';

try {
  mysqli_begin_transaction($koneksi);

  // cek apakah user mau mengganti passwordnya atau tidak
  if ($pass !== "") {
    $password_hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($koneksi, "UPDATE users SET username = ?, password = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $user, $password_hash, $user_id);
    mysqli_stmt_execute($stmt);
  } else {
    $stmt = mysqli_prepare($koneksi, "UPDATE users SET username = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $user, $user_id);
    mysqli_stmt_execute($stmt);
  }

  // data profil sesuai jenis edit
  $tabel = '';
  if ($aksi === 'editsiswa') {
    $tabel = 'siswa';
    $data = ['nis' => $_POST['nis'] ?? '', 'nama' => $nama, 'kelas' => $_POST['kelas'] ?? '', 'jenis_kelamin' => $jk];
  } elseif ($aksi === 'editguru') {
    $tabel = 'guru';
    $data = ['nip' => $_POST['nip'] ?? '', 'nama' => $nama, 'jenis_kelamin' => $jk];
  }

  if ($tabel !== '') {
    $kolom = array_keys($data);
    $nilai = array_values($data);
    $tipe  = str_repeat('s', count($kolom)) . 'i';

    $stmt = mysqli_prepare($koneksi, "SELECT id FROM $tabel WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $cek = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($cek) > 0) {
      // query untuk mengupdate tabel siswa / guru
      $sql = "UPDATE $tabel SET " . implode(' = ?, ', $kolom) . " = ? WHERE user_id = ?";
    } else {
      // user belum punya data profil: dibuat baru
      $sql = "INSERT INTO $tabel (" . implode(', ', $kolom) . ", user_id) VALUES (" . str_repeat('?, ', count($kolom)) . "?)";
    }
    $stmt = mysqli_prepare($koneksi, $sql);
    $semua = array_merge($nilai, [$user_id]);
    mysqli_stmt_bind_param($stmt, $tipe, ...$semua);
    mysqli_stmt_execute($stmt);
  }

  mysqli_commit($koneksi);
  echo "<script>alert('Data Berhasil Diubah'); window.location.href='$kembali';</script>";
} catch (Throwable $e) {
  mysqli_rollback($koneksi);
  $pesan = addslashes($e->getMessage());
  echo "<script>alert('Data Tidak Berhasil Diubah: $pesan'); window.location.href='$kembali';</script>";
}
