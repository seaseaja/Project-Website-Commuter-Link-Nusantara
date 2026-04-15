<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo = getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$monthSel = $_GET['bulan']   ?? date('Y-m');
$lineSel  = $_GET['koridor'] ?? '';
$type     = $_GET['type']    ?? 'laporan'; // laporan | barang | pencocokan

$dateFrom = $monthSel . '-01';
$dateTo   = date('Y-m-t', strtotime($dateFrom));
$labelBulan = DateTime::createFromFormat('Y-m', $monthSel)->format('F_Y');

$filename = "CommuterLink_{$type}_{$labelBulan}.csv";

if ($type === 'barang') {
    $sql = "
        SELECT
            bt.kode_barang         AS 'Kode Barang',
            bt.nama_barang         AS 'Nama Barang',
            bt.kategori            AS 'Kategori',
            bt.warna               AS 'Warna',
            bt.merek               AS 'Merek',
            bt.lokasi_ditemukan    AS 'Lokasi Ditemukan',
            bt.no_krl              AS 'No KRL',
            bt.waktu_ditemukan     AS 'Waktu Ditemukan',
            bt.status              AS 'Status',
            bt.catatan             AS 'Catatan',
            u.nama                 AS 'Petugas',
            bt.created_at          AS 'Tanggal Input'
        FROM barang_temuan bt
        LEFT JOIN users u ON bt.petugas_id = u.id
        WHERE bt.deleted_at IS NULL
          AND DATE(bt.created_at) BETWEEN ? AND ?
        ORDER BY bt.created_at DESC
    ";
    $params = [$dateFrom, $dateTo];

} elseif ($type === 'pencocokan') {
    $sql = "
        SELECT
            p.id                   AS 'ID Pencocokan',
            lk.no_laporan          AS 'No Laporan',
            lk.nama_barang         AS 'Barang Hilang',
            bt.kode_barang         AS 'Kode Barang Temuan',
            bt.nama_barang         AS 'Barang Temuan',
            p.status               AS 'Status Cocok',
            p.catatan              AS 'Catatan',
            up.nama                AS 'Petugas Pencocok',
            p.created_at           AS 'Tanggal Pencocokan'
        FROM pencocokan p
        LEFT JOIN laporan_kehilangan lk ON p.laporan_id = lk.id
        LEFT JOIN barang_temuan bt       ON p.barang_id  = bt.id
        LEFT JOIN users up               ON p.petugas_id = up.id
        WHERE DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.created_at DESC
    ";
    $params = [$dateFrom, $dateTo];

} else {
    $sql = "
        SELECT
            lk.no_laporan          AS 'No Laporan',
            u.nama                 AS 'Nama Pelapor',
            u.email                AS 'Email Pelapor',
            u.no_telepon           AS 'No Telepon',
            lk.nama_barang         AS 'Nama Barang',
            lk.kategori            AS 'Kategori',
            lk.warna               AS 'Warna',
            lk.merek               AS 'Merek',
            lk.deskripsi           AS 'Deskripsi',
            lk.lokasi_hilang       AS 'Lokasi Hilang',
            lk.no_krl              AS 'No KRL',
            lk.waktu_hilang        AS 'Waktu Hilang',
            lk.status              AS 'Status',
            lk.catatan             AS 'Catatan',
            lk.created_at          AS 'Tanggal Lapor'
        FROM laporan_kehilangan lk
        LEFT JOIN users u ON lk.user_id = u.id
        WHERE lk.deleted_at IS NULL
          AND DATE(lk.created_at) BETWEEN ? AND ?
        ORDER BY lk.created_at DESC
    ";
    $params = [$dateFrom, $dateTo];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

$out = fopen('php://output', 'w');

fputs($out, "\xEF\xBB\xBF");

fputcsv($out, ['CommuterLink Nusantara — Lost & Found System']);
fputcsv($out, ['Laporan Ekspor: ' . ucfirst($type)]);
fputcsv($out, ['Periode: ' . DateTime::createFromFormat('Y-m', $monthSel)->format('F Y')]);
fputcsv($out, ['Diekspor pada: ' . date('d/m/Y H:i:s')]);
fputcsv($out, []); // baris kosong

if (empty($rows)) {
    fputcsv($out, ['Tidak ada data pada periode yang dipilih.']);
} else {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fputcsv($out, []);
    fputcsv($out, ['Total data: ' . count($rows) . ' baris']);
}

fclose($out);
exit;