<?php
require_once 'auth.php';
require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Halaman ini khusus guru
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] !== 'guru') { header('Location: index.php'); exit; }

$uid      = (int) $_SESSION['user_id'];
$mapel_id = (int) ($_GET['mapel_id'] ?? 0);
$kelas    = trim($_GET['kelas'] ?? '');

// Mapel harus milik guru yang sedang login
$stmt = mysqli_prepare($koneksi, "SELECT mp.id, mp.`nama-mapel` AS nama FROM mapel mp JOIN guru g ON mp.guru_id = g.id
                                  WHERE mp.id = ? AND g.user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $mapel_id, $uid);
mysqli_stmt_execute($stmt);
$mapel = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$mapel || $kelas === '') { header('Location: daftar_kelas_guru.php'); exit; }

// Token CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

// Pesan sekali tampil (flash)
$flash = $_SESSION['flash_nilai'] ?? null;
unset($_SESSION['flash_nilai']);

$siswa = [];
$error = '';
try {
  // Nilai disimpan di tabel nilai_rapor (skema dari guru, tidak diubah)
  $stmt = mysqli_prepare($koneksi, "SELECT DISTINCT s.id, s.nis, s.nama,
            n.nilai_kkm AS kkm, n.nilai_pengetahuan AS pengetahuan,
            n.nilai_keterampilan AS keterampilan, n.Keterangan AS ket
     FROM mengajar m
     JOIN siswa s ON m.siswa_id = s.id
     LEFT JOIN nilai_rapor n ON n.siswa_id = s.id AND n.mapel_id = m.mapel_id
     WHERE m.mapel_id = ? AND s.kelas = ?
     ORDER BY s.nama ASC");
  mysqli_stmt_bind_param($stmt, 'is', $mapel_id, $kelas);
  mysqli_stmt_execute($stmt);
  $siswa = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
} catch (Throwable $ex) {
  $error = 'Data nilai tidak dapat dimuat. Periksa tabel nilai_rapor di database.';
}

require_once 'navbar.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<div class="container mt-4" style="max-width: 960px;">
  <h4 class="mb-1">Daftar Nilai Siswa</h4>
  <div class="small text-secondary mb-3">
    Kelas : <strong><?= htmlspecialchars($kelas) ?></strong>
    <span class="mx-1">|</span>
    Mata Pelajaran: <strong><?= htmlspecialchars($mapel['nama']) ?></strong>
  </div>

  <?php if ($flash) { ?>
    <div class="alert alert-<?= htmlspecialchars($flash['tipe']) ?> py-2"><?= htmlspecialchars($flash['pesan']) ?></div>
  <?php } ?>
  <?php if ($error !== '') { ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
  <?php } else { ?>

  <div class="card">
    <div class="card-body">
      <table id="tabelNilaiKelas" class="table table-striped table-bordered align-middle" style="width:100%">
        <thead>
          <tr>
            <th>No</th>
            <th>Nama Siswa</th>
            <th>KKM</th>
            <th>Pengetahuan</th>
            <th>Keterampilan</th>
            <th>Nilai Akhir</th>
            <th>Keterangan</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($siswa as $i => $b) {
            $ada = ($b['pengetahuan'] !== null || $b['keterampilan'] !== null);
            $kkm = $b['kkm'] !== null ? (int) $b['kkm'] : KKM_DEFAULT;

            // Nilai akhir = rata-rata dari nilai yang terisi (kosong jika keduanya belum ada)
            $akhir = null;
            if ($b['pengetahuan'] !== null && $b['keterampilan'] !== null) {
              $akhir = ((float) $b['pengetahuan'] + (float) $b['keterampilan']) / 2;
            } elseif ($b['pengetahuan'] !== null) {
              $akhir = (float) $b['pengetahuan'];
            } elseif ($b['keterampilan'] !== null) {
              $akhir = (float) $b['keterampilan'];
            }

            // Keterangan: pakai yang tersimpan, kalau kosong dihitung dari nilai akhir
            if (($b['ket'] ?? '') !== '') {
              $ket = $b['ket'];
            } elseif ($akhir === null) {
              $ket = '-';
            } elseif ($akhir >= 90) {
              $ket = 'Sangat Baik';
            } elseif ($akhir >= $kkm) {
              $ket = 'Baik';
            } elseif ($akhir >= $kkm - 15) {
              $ket = 'Cukup';
            } else {
              $ket = 'Kurang';
            }

            // Tampilan angka: 85.00 -> 85, 85.50 -> 85.5
            $p_tampil = '';
            if ($b['pengetahuan'] !== null) { $p_tampil = rtrim(rtrim(number_format((float) $b['pengetahuan'], 2, '.', ''), '0'), '.'); }
            $k_tampil = '';
            if ($b['keterampilan'] !== null) { $k_tampil = rtrim(rtrim(number_format((float) $b['keterampilan'], 2, '.', ''), '0'), '.'); }
            $akhir_tampil = '';
            if ($akhir !== null) { $akhir_tampil = rtrim(rtrim(number_format($akhir, 2, '.', ''), '0'), '.'); }

            $url_form = 'form_nilai.php?mapel_id=' . $mapel_id . '&siswa_id=' . (int) $b['id'] . '&kelas=' . urlencode($kelas);
          ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($b['nama']) ?></td>
            <td><?= $ada ? $kkm : '-' ?></td>
            <td>
              <?php if ($b['pengetahuan'] === null) { ?><span class="text-muted small">Belum ada</span>
              <?php } else { ?><span class="badge bg-primary"><?= $p_tampil ?></span><?php } ?>
            </td>
            <td>
              <?php if ($b['keterampilan'] === null) { ?><span class="text-muted small">Belum ada</span>
              <?php } else { ?><span class="badge bg-primary"><?= $k_tampil ?></span><?php } ?>
            </td>
            <td>
              <?php if ($akhir === null) { ?><span class="text-muted small">Belum ada</span>
              <?php } else { ?><span class="badge <?= $akhir >= $kkm ? 'bg-success' : 'bg-danger' ?>"><?= $akhir_tampil ?></span><?php } ?>
            </td>
            <td><?= htmlspecialchars($ket) ?></td>
            <td>
              <div class="d-grid gap-1" style="min-width:70px;">
                <?php if (!$ada) { ?>
                  <a href="<?= htmlspecialchars($url_form) ?>" class="btn btn-primary btn-sm">Tambah</a>
                <?php } else { ?>
                  <a href="<?= htmlspecialchars($url_form) ?>" class="btn btn-warning btn-sm">Edit</a>
                  <form method="POST" action="aksi_hapus_nilai.php" class="d-grid" onsubmit="return confirm('Hapus nilai siswa ini?')">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="mapel_id" value="<?= (int) $mapel_id ?>">
                    <input type="hidden" name="siswa_id" value="<?= (int) $b['id'] ?>">
                    <input type="hidden" name="kelas" value="<?= htmlspecialchars($kelas) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                  </form>
                <?php } ?>
              </div>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php } ?>

  <a href="daftar_kelas_guru.php" class="btn btn-secondary mt-3">Kembali</a>
</div>

<?php if ($error === '') { ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
  $(document).ready(function () {
    $('#tabelNilaiKelas').DataTable({
      language: {
        lengthMenu: "Tampilkan _MENU_ data per halaman",
        search: "Cari:",
        zeroRecords: "Data tidak ditemukan",
        emptyTable: "Belum ada siswa di kelas ini",
        info: "Menampilkan halaman _PAGE_ dari _PAGES_",
        infoEmpty: "Tidak ada data",
        infoFiltered: "(disaring dari _MAX_ total data)",
        paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
      },
      columnDefs: [{ orderable: false, targets: 7 }]
    });
  });
</script>
<?php } ?>
<?php require_once 'footer.php'; ?>
