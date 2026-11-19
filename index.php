<?php
require_once 'auth.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Halaman pertama setelah login, sesuai role
if ($_SESSION['role'] === 'admin') {
  header('Location: daftar_user.php');
} elseif ($_SESSION['role'] === 'guru') {
  header('Location: daftar_guru.php');
} else {
  header('Location: daftar_siswa.php');
}
exit;
