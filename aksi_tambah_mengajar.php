<?php
require_once 'auth.php';
// Halaman ini khusus admin
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] !== 'admin') { header('Location: index.php'); exit; }
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo "<script>alert('Akses tidak diizinkan'); window.location.href='daftar_mengajar.php';</script>";
    exit;
}

$kelas   = trim($_POST['kelas'] ?? '');
$guru_id = (int) ($_POST['guru_id'] ?? 0);
if ($kelas === '' || $guru_id <= 0) {
    echo "<script>alert('Data belum lengkap'); window.location.href='form_tambah_mengajar.php';</script>";
    exit;
}

try {
    // cek guru sudah punya mapel atau belum
    $s = mysqli_prepare($koneksi, "SELECT COUNT(*) FROM mapel WHERE guru_id = ?");
    mysqli_stmt_bind_param($s, 'i', $guru_id);
    mysqli_stmt_execute($s);
    $jumlah_mapel = (int) mysqli_fetch_row(mysqli_stmt_get_result($s))[0];
    if ($jumlah_mapel == 0) {
        echo "<script>alert('Guru ini belum punya mata pelajaran. Tambahkan mapel untuk guru ini dulu.'); window.location.href='form_tambah_mengajar.php';</script>";
        exit;
    }

    // cek kelas sudah punya siswa atau belum
    $s = mysqli_prepare($koneksi, "SELECT COUNT(*) FROM siswa WHERE kelas = ?");
    mysqli_stmt_bind_param($s, 's', $kelas);
    mysqli_stmt_execute($s);
    $jumlah_siswa = (int) mysqli_fetch_row(mysqli_stmt_get_result($s))[0];
    if ($jumlah_siswa == 0) {
        echo "<script>alert('Kelas ini belum punya siswa.'); window.location.href='form_tambah_mengajar.php';</script>";
        exit;
    }

    // Hubungkan semua siswa di kelas itu dengan semua mapel milik guru tersebut (yang sudah ada dilewati)
    mysqli_begin_transaction($koneksi);

    // id diisi manual karena kolom id di tabel mengajar belum AUTO_INCREMENT
    $r = mysqli_query($koneksi, "SELECT COALESCE(MAX(id), 0) FROM mengajar FOR UPDATE");
    $id_berikut = (int) mysqli_fetch_row($r)[0];

    $cari = mysqli_prepare($koneksi, "SELECT mp.id, s.id
        FROM mapel mp
        JOIN siswa s ON s.kelas = ?
        WHERE mp.guru_id = ?
          AND NOT EXISTS (SELECT 1 FROM mengajar m WHERE m.mapel_id = mp.id AND m.siswa_id = s.id)");
    mysqli_stmt_bind_param($cari, 'si', $kelas, $guru_id);
    mysqli_stmt_execute($cari);
    $pasangan = mysqli_fetch_all(mysqli_stmt_get_result($cari));

    if (count($pasangan) == 0) {
        mysqli_rollback($koneksi);
        echo "<script>alert('Guru ini sudah mengajar di kelas tersebut.'); window.location.href='form_tambah_mengajar.php';</script>";
        exit;
    }

    $ins = mysqli_prepare($koneksi, "INSERT INTO mengajar (id, mapel_id, siswa_id) VALUES (?, ?, ?)");
    foreach ($pasangan as $p) {
        $mapel_id = $p[0];
        $siswa_id = $p[1];
        $id_berikut++;
        mysqli_stmt_bind_param($ins, 'iii', $id_berikut, $mapel_id, $siswa_id);
        mysqli_stmt_execute($ins);
    }
    mysqli_commit($koneksi);

    echo "<script>alert('Data mengajar berhasil ditambahkan'); window.location.href='daftar_mengajar.php';</script>";
    exit;
} catch (Throwable $e) {
    @mysqli_rollback($koneksi);
    $pesan = addslashes($e->getMessage());
    echo "<script>alert('Data mengajar gagal ditambahkan: $pesan'); window.location.href='form_tambah_mengajar.php';</script>";
    exit;
}
