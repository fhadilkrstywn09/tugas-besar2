<?php
require_once 'auth.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$role = $_SESSION['role'];

// Guru: menu Nilai Siswa membuka Daftar Kelas yang Diajar (lalu tombol Lihat untuk input nilai)
if ($role === 'guru') { header('Location: daftar_kelas_guru.php'); exit; }
// Hanya siswa yang melihat halaman ini
if ($role !== 'siswa') { header('Location: index.php'); exit; }

require_once 'koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$uid = (int) $_SESSION['user_id'];
$saya = [];
$baris = [];
$rata_total = null;
$error = '';

try {
  $s = mysqli_prepare($koneksi, "SELECT id, nama, nis, kelas FROM siswa WHERE user_id = ? LIMIT 1");
  mysqli_stmt_bind_param($s, 'i', $uid);
  mysqli_stmt_execute($s);
  $saya = mysqli_fetch_assoc(mysqli_stmt_get_result($s)) ?: [];

  if ($saya) {
    $s = mysqli_prepare($koneksi,
      "SELECT DISTINCT mp.`nama-mapel` AS mapel, n.nilai_kkm AS kkm, n.nilai_pengetahuan AS pengetahuan,
              n.nilai_keterampilan AS keterampilan, n.Keterangan AS ket
       FROM mengajar m
       JOIN mapel mp ON m.mapel_id = mp.id
       LEFT JOIN nilai_rapor n ON n.siswa_id = m.siswa_id AND n.mapel_id = mp.id
       WHERE m.siswa_id = ?
       ORDER BY mp.`nama-mapel` ASC");
    $sid = (int) $saya['id'];
    mysqli_stmt_bind_param($s, 'i', $sid);
    mysqli_stmt_execute($s);
    $baris = mysqli_fetch_all(mysqli_stmt_get_result($s), MYSQLI_ASSOC);
  }
} catch (Throwable $ex) {
  $error = 'Data nilai tidak dapat dimuat. Periksa tabel nilai_rapor di database.';
}

// Rata-rata per mapel dan rata-rata keseluruhan (dari semua nilai yang sudah terisi)
$semua = [];
foreach ($baris as &$b) {
  $skor = [];
  foreach (['pengetahuan', 'keterampilan'] as $kolom) {
    if ($b[$kolom] !== null) { $skor[] = (float) $b[$kolom]; $semua[] = (float) $b[$kolom]; }
  }
  $b['rata'] = $skor ? array_sum($skor) / count($skor) : null;
}
unset($b);
if ($semua) { $rata_total = array_sum($semua) / count($semua); }

require_once 'navbar.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<div class="container mt-4" style="max-width: 900px;">

  <div class="text-center mb-4">
    <h2 class="mb-2">Nilai Siswa</h2>
    <h4 class="mb-2"><?= htmlspecialchars($saya['nama'] ?? $_SESSION['nama'] ?? '') ?></h4>
    <div>NIS: <strong><?= htmlspecialchars($saya['nis'] ?? '-') ?></strong></div>
    <div>Kelas: <strong><?= htmlspecialchars($saya['kelas'] ?? '-') ?></strong></div>
  </div>

  <?php if ($error !== '') { ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php } else { ?>

  <div class="card text-center mb-4">
    <div class="card-body py-4">
      <h5 class="mb-3">Rata-rata Nilai</h5>
      <div class="display-4 fw-light"><?= $rata_total === null ? '-' : number_format($rata_total, 2, '.', '') ?></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h4 class="mb-3">Daftar Nilai</h4>
      <div class="table-responsive">
        <table id="tabelNilai" class="table table-striped table-bordered align-middle" style="width:100%">
          <thead>
            <tr>
              <th>No</th>
              <th>Mata Pelajaran</th>
              <th>KKM</th>
              <th>Pengetahuan</th>
              <th>Keterampilan</th>
              <th>Rata-rata</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($baris as $i => $b) {
              // Tampilan angka: 85.00 -> 85, 85.50 -> 85.5
              $p_tampil = '-';
              if ($b['pengetahuan'] !== null) { $p_tampil = rtrim(rtrim(number_format((float) $b['pengetahuan'], 2, '.', ''), '0'), '.'); }
              $k_tampil = '-';
              if ($b['keterampilan'] !== null) { $k_tampil = rtrim(rtrim(number_format((float) $b['keterampilan'], 2, '.', ''), '0'), '.'); }
              $rata_tampil = '-';
              if ($b['rata'] !== null) { $rata_tampil = rtrim(rtrim(number_format((float) $b['rata'], 2, '.', ''), '0'), '.'); }

              // Keterangan: pakai yang tersimpan, kalau kosong dihitung dari rata-rata
              $kkm = $b['kkm'] !== null ? (int) $b['kkm'] : KKM_DEFAULT;
              if ($b['rata'] === null) {
                $ket = '<span class="text-muted">Belum dinilai</span>';
              } else {
                if (($b['ket'] ?? '') !== '') {
                  $ket = $b['ket'];
                } elseif ($b['rata'] >= 90) {
                  $ket = 'Sangat Baik';
                } elseif ($b['rata'] >= $kkm) {
                  $ket = 'Baik';
                } elseif ($b['rata'] >= $kkm - 15) {
                  $ket = 'Cukup';
                } else {
                  $ket = 'Kurang';
                }
                $ket = htmlspecialchars($ket);
              }
            ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= htmlspecialchars($b['mapel']) ?></td>
              <td><?= $b['kkm'] === null ? '-' : (int) $b['kkm'] ?></td>
              <td><?= $p_tampil ?></td>
              <td><?= $k_tampil ?></td>
              <td><?= $rata_tampil ?></td>
              <td><?= $ket ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php } ?>
</div>

<?php if ($error === '') { ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
  $(document).ready(function () {
    $('#tabelNilai').DataTable({
      language: {
        lengthMenu: "Tampilkan _MENU_ data per halaman",
        search: "Cari:",
        zeroRecords: "Data tidak ditemukan",
        info: "Menampilkan halaman _PAGE_ dari _PAGES_",
        infoEmpty: "Tidak ada data",
        infoFiltered: "(disaring dari _MAX_ total data)",
        paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
      }
    });
  });
</script>
<?php } ?>
<?php require_once 'footer.php'; ?>
