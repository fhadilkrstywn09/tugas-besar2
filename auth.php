<?php
// Sistem login sederhana berbasis session.
// File ini hanya berisi session dan pengaturan (konstanta). Cek login ditulis langsung di tiap halaman.
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Role yang boleh login
if (!defined('ROLE_BOLEH')) {
  define('ROLE_BOLEH', ['admin', 'guru', 'siswa']);
}

// true = tampilkan pesan error database di layar (untuk uji coba). Ubah ke false saat dikumpulkan.
if (!defined('DEBUG')) {
  define('DEBUG', true);
}

// KKM (nilai minimal tuntas) untuk semua mata pelajaran
if (!defined('KKM_DEFAULT')) {
  define('KKM_DEFAULT', 75);
}
