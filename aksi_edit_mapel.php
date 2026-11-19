<?php
require_once 'auth.php';
// Halaman ini khusus admin
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] !== 'admin') { header('Location: index.php'); exit; }
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo "<script>alert('Akses tidak diizinkan'); window.location.href='daftar_mapel.php';</script>";
    exit;
}

$id      = (int) ($_POST['id'] ?? 0);
$kode    = trim($_POST['kode'] ?? '');
$nama    = trim($_POST['nama'] ?? '');
$guru_id = (int) ($_POST['guru_id'] ?? 0);
if ($id <= 0 || $kode === '' || $nama === '' || $guru_id <= 0) {
    echo "<script>alert('Data belum lengkap'); window.location.href='daftar_mapel.php';</script>";
    exit;
}

try {
    // kode + guru yang sama tidak boleh dobel (selain data ini sendiri)
    $cek = mysqli_prepare($koneksi, "SELECT 1 FROM mapel WHERE kode_mapel = ? AND guru_id = ? AND id <> ? LIMIT 1");
    mysqli_stmt_bind_param($cek, 'sii', $kode, $guru_id, $id);
    mysqli_stmt_execute($cek);
    if (mysqli_num_rows(mysqli_stmt_get_result($cek)) > 0) {
        echo "<script>alert('Mapel $kode untuk guru tersebut sudah ada'); window.location.href='daftar_mapel.php';</script>";
        exit;
    }

    $stmt = mysqli_prepare($koneksi, "UPDATE mapel SET kode_mapel = ?, `nama-mapel` = ?, guru_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssii', $kode, $nama, $guru_id, $id);
    mysqli_stmt_execute($stmt);

    echo "<script>alert('Data mapel berhasil diubah'); window.location.href='daftar_mapel.php';</script>";
    exit;
} catch (Throwable $e) {
    $pesan = addslashes($e->getMessage());
    echo "<script>alert('Data mapel gagal diubah: $pesan'); window.location.href='daftar_mapel.php';</script>";
    exit;
}
