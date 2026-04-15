<?php

$conn = new mysqli("localhost", "root", "021936", "commuter_link_nusantara");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

session_start();
$uid   = $_SESSION['user_id']   ?? 1;
$uNama = $_SESSION['user_nama'] ?? 'User';
$uRole = $_SESSION['user_role'] ?? 'penumpang';
$uAwal = strtoupper(substr($uNama, 0, 1));

$search   = trim($_GET['q']      ?? '');
$stFilter = trim($_GET['status'] ?? '');
$detailId = intval($_GET['id']   ?? 0);

$statCnt = ['diproses'=>0,'ditemukan'=>0,'selesai'=>0,'ditutup'=>0];
$total   = 0;
$sRes = $conn->prepare("SELECT status, COUNT(*) c FROM laporan_kehilangan WHERE deleted_at IS NULL AND user_id=? GROUP BY status");
if ($sRes) {
    $sRes->bind_param("i", $uid);
    $sRes->execute();
    foreach ($sRes->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $statCnt[$r['status']] = (int)$r['c'];
        $total += (int)$r['c'];
    }
}

$wh = "lk.deleted_at IS NULL AND lk.user_id=?";
$bt = "i"; $bv = [$uid];
if ($stFilter) { $wh .= " AND lk.status=?"; $bt .= "s"; $bv[] = $stFilter; }
if ($search)   {
    $lk = "%$search%";
    $wh .= " AND (lk.no_laporan LIKE ? OR lk.nama_barang LIKE ? OR lk.lokasi_hilang LIKE ?)";
    $bt .= "sss"; $bv = array_merge($bv, [$lk,$lk,$lk]);
}

$lstSt = $conn->prepare("
    SELECT lk.id, lk.no_laporan, lk.nama_barang, lk.lokasi_hilang,
           lk.waktu_hilang, lk.status, lk.created_at,
           MAX(p.id) AS cocok_id
    FROM laporan_kehilangan lk
    LEFT JOIN pencocokan p
           ON p.laporan_id = lk.id
          AND p.deleted_at IS NULL
          AND p.status != 'ditolak'
    WHERE $wh
    GROUP BY lk.id, lk.no_laporan, lk.nama_barang, lk.lokasi_hilang,
             lk.waktu_hilang, lk.status, lk.created_at
    ORDER BY lk.created_at DESC
    LIMIT 100
");
$laporan = [];
if ($lstSt) {
    $lstSt->bind_param($bt, ...$bv);
    $lstSt->execute();
    $laporan = $lstSt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$autoId = (!$detailId && !empty($laporan)) ? $laporan[0]['id'] : 0;
if ($autoId && !$detailId) {
    $qs = '?id='.$autoId.($stFilter?'&status='.urlencode($stFilter):'').($search?'&q='.urlencode($search):'');
    header("Location: track.php$qs"); exit;
}

$dr = null; $timeline = []; $sScore = 0;
if ($detailId > 0) {
    $colCheck = $conn->query("SHOW COLUMNS FROM pencocokan LIKE 'skor_kecocokan'");
    $hasSkor  = ($colCheck && $colCheck->num_rows > 0);
    $skorSel  = $hasSkor ? 'p.skor_kecocokan,' : '0 AS skor_kecocokan,';

    $dSt = $conn->prepare("
        SELECT lk.*,
               p.id           AS cocok_id,
               p.status       AS cocok_status,
               p.catatan      AS cocok_catatan,
               p.created_at   AS cocok_tgl,
               p.petugas_id,
               $skorSel
               bt.kode_barang,
               bt.nama_barang AS bt_nama,
               bt.deskripsi   AS bt_deskripsi,
               bt.lokasi_ditemukan,
               bt.waktu_ditemukan,
               bt.foto_barang,
               u.nama         AS nama_petugas
        FROM laporan_kehilangan lk
        LEFT JOIN pencocokan p
               ON p.laporan_id = lk.id
              AND p.deleted_at IS NULL
              AND p.status != 'ditolak'
        LEFT JOIN barang_temuan bt ON bt.id = p.barang_id
        LEFT JOIN users u          ON u.id  = p.petugas_id
        WHERE lk.id = ? AND lk.user_id = ? AND lk.deleted_at IS NULL
        LIMIT 1
    ");
    if ($dSt) {
        $dSt->bind_param("ii", $detailId, $uid);
        $dSt->execute();
        $dr = $dSt->get_result()->fetch_assoc();
    }

    if ($dr) {
        $sScore    = (int)($dr['skor_kecocokan'] ?? 0);
        $hasCocok  = (bool)$dr['cocok_id'];
        $isDone    = in_array($dr['status'], ['ditemukan','selesai']);
        $isSelesai = $dr['status'] === 'selesai';
        $timeline  = [
            ['lbl'=>'Laporan Dibuat',     'sub'=>'Laporan '.$dr['no_laporan'].' berhasil dikirim ke sistem',                                                                                               'done'=>true,      'tgl'=>$dr['created_at']],
            ['lbl'=>'Pencocokan Barang',  'sub'=>$hasCocok ? 'Kecocokan '.($sScore?$sScore.'% — ':'').'Barang '.$dr['kode_barang'].' teridentifikasi' : 'Tim sedang mencari kecocokan di database barang temuan', 'done'=>$hasCocok, 'tgl'=>$hasCocok?$dr['cocok_tgl']:null],
            ['lbl'=>'Verifikasi Petugas', 'sub'=>$isDone   ? 'Petugas '.($dr['nama_petugas']??'KRL').' telah memverifikasi kecocokan' : 'Menunggu konfirmasi dari petugas stasiun',                       'done'=>$isDone,   'tgl'=>$isDone?$dr['updated_at']:null],
            ['lbl'=>'Serah Terima',       'sub'=>$isSelesai? 'Barang berhasil dikembalikan ke pemilik' : 'Hadir ke stasiun dengan kartu identitas (KTP/SIM)',                                             'done'=>$isSelesai,'tgl'=>$isSelesai?$dr['updated_at']:null],
        ];
    }
}

$stLbl = ['diproses'=>'Diproses','ditemukan'=>'Ditemukan','selesai'=>'Selesai','ditutup'=>'Ditutup'];
$csLbl = ['menunggu_verifikasi'=>'Menunggu Verifikasi','diverifikasi'=>'Terverifikasi','ditolak'=>'Ditolak'];
$csCl  = ['menunggu_verifikasi'=>'st-wait','diverifikasi'=>'st-verified','ditolak'=>'st-rejected'];
function qs(int $id, string $st='', string $q=''): string {
    return 'track.php?id='.$id.($st?'&status='.urlencode($st):'').($q?'&q='.urlencode($q):'');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Lacak Laporan – CommuterLink</title>
<link rel="icon" href="uploads/favicon.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<style>
:root {
  --bg:        #0A1628;
  --bg-2:      #0F1F38;
  --navy:      #0D1B2E;
  --navy-2:    #152640;
  --navy-3:    #1E3357;
  --card:      #132035;
  --card-2:    #192A45;
  --card-3:    #1F3254;
  --amber:     #F59E0B;
  --amber-lt:  #FCD34D;
  --amber-dim: rgba(245,158,11,0.12);
  --blue-lt:   #60A5FA;
  --text:      #EBF4FF;
  --text-2:    #A8BDD6;
  --text-3:    #5A7A9E;
  --border:    rgba(255,255,255,0.07);
  --border-2:  rgba(255,255,255,0.13);
  --danger:    #F87171;
  --success:   #34D399;
  --info:      #60A5FA;
  --purple:    #A78BFA;
  --radius:    14px;
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
::-webkit-scrollbar{width:5px;height:5px;}
::-webkit-scrollbar-track{background:var(--card);}
::-webkit-scrollbar-thumb{background:var(--card-3);border-radius:99px;}

/* NAVBAR */
.cl-navbar {
  position:sticky; top:0; z-index:300;
  background:rgba(10,22,40,0.96); backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  padding:0 1.5rem; height:60px;
  display:flex; align-items:center; justify-content:space-between;
}
.cl-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
.cl-brand-icon { width:32px; height:32px; background:linear-gradient(135deg,var(--amber),var(--amber-lt)); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.95rem; box-shadow:0 4px 12px rgba(245,158,11,.35); }
.cl-brand-name { font-family:'Fraunces',serif; font-size:1rem; font-weight:700; color:var(--text); line-height:1; }
.cl-brand-name em { font-style:italic; color:var(--amber); }
.cl-brand-sub { font-size:.58rem; color:var(--text-3); text-transform:uppercase; letter-spacing:.1em; }
.cl-nav-links { display:flex; align-items:center; gap:.2rem; }
.cl-nav-link { display:flex; align-items:center; gap:6px; padding:.45rem .8rem; border-radius:8px; font-size:.79rem; font-weight:600; color:var(--text-2); text-decoration:none; transition:all .2s; }
.cl-nav-link:hover { background:var(--card-2); color:var(--text); }
.cl-nav-link.active { background:var(--amber-dim); color:var(--amber); }
.cl-nav-link i { font-size:.88rem; }
.cl-right { display:flex; align-items:center; gap:.6rem; }
.role-chip { font-size:.63rem; font-weight:700; padding:3px 9px; border-radius:99px; background:rgba(96,165,250,.12); color:var(--blue-lt); border:1px solid rgba(96,165,250,.22); letter-spacing:.06em; }
.cl-avatar-chip { display:flex; align-items:center; gap:8px; padding:4px 12px 4px 4px; border:1px solid var(--border); border-radius:99px; background:var(--card); text-decoration:none; transition:all .2s; }
.cl-avatar-chip:hover { border-color:var(--amber); }
.cl-avt { width:28px; height:28px; background:linear-gradient(135deg,var(--amber),var(--amber-lt)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; color:var(--navy); }
.cl-avt-name { font-size:.78rem; font-weight:600; color:var(--text); }

/* PAGE HEADER */
.page-header { background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%); border-bottom:1px solid var(--border); padding:1.6rem 2rem; position:relative; overflow:hidden; }
.page-header::after { content:''; position:absolute; right:-60px; top:-60px; width:260px; height:260px; background:radial-gradient(circle,rgba(96,165,250,.08) 0%,transparent 70%); border-radius:50%; pointer-events:none; }
.ph-inner { position:relative; z-index:1; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
.bc { display:flex; align-items:center; gap:5px; margin-bottom:.65rem; }
.bc a,.bc span { font-size:.73rem; color:var(--text-3); text-decoration:none; }
.bc a:hover { color:var(--amber); }
.bc i { font-size:.6rem; color:var(--text-3); }
.ph-left {}
.ph-title { font-family:'Fraunces',serif; font-size:1.55rem; font-weight:900; color:var(--text); display:flex; align-items:center; gap:10px; margin-bottom:.25rem; }
.ph-icon { width:36px; height:36px; background:rgba(96,165,250,.1); border:1px solid rgba(96,165,250,.22); border-radius:9px; display:flex; align-items:center; justify-content:center; color:var(--info); font-size:1rem; }
.ph-sub { font-size:.8rem; color:var(--text-3); }

/* FILTER PILLS */
.filter-strip { display:flex; align-items:center; gap:.5rem; margin-top:1rem; flex-wrap:wrap; }
.fp { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:99px; font-size:.73rem; font-weight:700; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.04); color:var(--text-3); text-decoration:none; transition:.15s; cursor:pointer; }
.fp:hover { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }
.fp.on { border-color:var(--amber); color:var(--amber); background:var(--amber-dim); }
.fp .fdot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }

/*  MAIN WRAP */
.main-wrap { padding:1.75rem 2rem 4rem; }
@media(max-width:767px){.main-wrap{padding:1.25rem 1rem 3rem;}.page-header{padding:1.25rem 1rem;}.cl-navbar{padding:0 1rem;}}

/* STAT STRIP  */
.stat-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:.7rem; margin-bottom:1.4rem; }
@media(max-width:767px){.stat-strip{grid-template-columns:repeat(2,1fr);}}
.ss-item { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1rem 1.1rem; display:flex; align-items:center; gap:10px; text-decoration:none; transition:all .2s; cursor:pointer; }
.ss-item:hover { border-color:var(--border-2); transform:translateY(-2px); }
.ss-item.active { border-color:var(--amber); background:var(--amber-dim); }
.ss-num { font-family:'Fraunces',serif; font-size:1.55rem; font-weight:700; color:var(--text); line-height:1; }
.ss-lbl { font-size:.7rem; color:var(--text-3); margin-top:2px; font-weight:500; }
.ss-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-left:auto; }

/* TWO-COLUMN SPLIT */
.split-layout { display:grid; grid-template-columns:320px 1fr; gap:1.25rem; align-items:start; }
@media(max-width:1100px){ .split-layout { grid-template-columns:1fr; } }

/* SEARCH */
.cl-toolbar { display:flex; align-items:center; gap:.6rem; margin-bottom:.9rem; }
.cl-search { position:relative; flex:1; }
.cl-search i { position:absolute; left:.85rem; top:50%; transform:translateY(-50%); color:var(--text-3); font-size:.82rem; pointer-events:none; }
.cl-search input { width:100%; padding:.58rem .9rem .58rem 2.3rem; background:var(--card); border:1px solid var(--border); border-radius:10px; color:var(--text); font-family:'DM Sans',sans-serif; font-size:.82rem; outline:none; transition:border-color .2s; }
.cl-search input:focus { border-color:var(--amber); }
.cl-search input::placeholder { color:var(--text-3); }

/* BUTTONS */
.btn-cl { display:inline-flex; align-items:center; gap:6px; padding:.58rem 1.1rem; border-radius:10px; border:none; cursor:pointer; font-family:'DM Sans',sans-serif; font-size:.81rem; font-weight:700; text-decoration:none; transition:all .2s; white-space:nowrap; }
.btn-amber { background:var(--amber); color:var(--navy); box-shadow:0 4px 14px rgba(245,158,11,.25); }
.btn-amber:hover { background:var(--amber-lt); transform:translateY(-1px); color:var(--navy); }
.btn-ghost { background:transparent; color:var(--text-2); border:1px solid var(--border-2); }
.btn-ghost:hover { border-color:var(--text-2); color:var(--text); background:var(--card-2); }

/* LAPORAN CARDS */
.lcard { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:14px 16px; margin-bottom:8px; text-decoration:none; display:block; color:var(--text); position:relative; overflow:hidden; transition:all .16s; cursor:pointer; }
.lcard::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; border-radius:12px 0 0 12px; background:var(--border); }
.lcard.dp::before { background:var(--amber); }
.lcard.dt::before { background:var(--info); }
.lcard.sl::before { background:var(--success); }
.lcard.xx::before { background:var(--text-3); }
.lcard:hover { border-color:var(--border-2); transform:translateY(-1px); box-shadow:0 4px 20px rgba(0,0,0,.3); }
.lcard.active { border-color:var(--amber); background:var(--amber-dim); }
.lc-top { display:flex; align-items:flex-start; justify-content:space-between; gap:6px; margin-bottom:6px; }
.lc-code { font-family:'Courier New',monospace; font-size:.72rem; background:rgba(245,158,11,.1); color:var(--amber); padding:2px 7px; border-radius:4px; border:1px solid rgba(245,158,11,.2); white-space:nowrap; }
.lc-nama { font-family:'Fraunces',serif; font-size:.88rem; font-weight:700; color:var(--text); margin-bottom:5px; line-height:1.3; }
.lc-meta { font-size:.73rem; color:var(--text-3); display:flex; flex-wrap:wrap; gap:8px; }
.lc-meta i { font-size:.73rem; }
.lc-foot { display:flex; align-items:center; justify-content:space-between; margin-top:8px; gap:5px; flex-wrap:wrap; }
.lc-date { font-size:.68rem; color:var(--text-3); }
.match-pill { display:inline-flex; align-items:center; gap:3px; font-size:.68rem; font-weight:700; color:var(--amber); background:var(--amber-dim); border:1px solid rgba(245,158,11,.22); padding:2px 8px; border-radius:99px; }

/* STATUS BADGES */
.st-badge { display:inline-flex; align-items:center; gap:5px; font-size:.67rem; font-weight:700; padding:3px 9px; border-radius:99px; }
.st-badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
.st-dp   { background:rgba(245,158,11,.12); color:var(--amber); }
.st-dt   { background:rgba(96,165,250,.12); color:var(--blue-lt); }
.st-sl   { background:rgba(52,211,153,.12); color:var(--success); }
.st-xx   { background:rgba(90,122,158,.12); color:var(--text-3); }
.st-wait     { background:rgba(245,158,11,.12); color:var(--amber); }
.st-verified { background:rgba(52,211,153,.12); color:var(--success); }
.st-rejected { background:rgba(248,113,113,.1); color:var(--danger); }

/* DETAIL PANEL */
.dpanel { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; position:sticky; top:72px; }
.dp-head { background:var(--card-2); border-bottom:1px solid var(--border); padding:1.1rem 1.3rem; }
.dp-code { font-family:'Courier New',monospace; font-size:.75rem; color:var(--amber); background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.2); padding:2px 8px; border-radius:4px; display:inline-block; margin-bottom:5px; }
.dp-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:900; color:var(--text); line-height:1.25; }
.dp-sub { font-size:.75rem; color:var(--text-3); margin-top:3px; }
.dp-body { padding:1.2rem 1.3rem; max-height:calc(100vh - 200px); overflow-y:auto; }
.slbl { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--text-3); margin-bottom:.6rem; display:flex; align-items:center; gap:6px; }
.slbl i { font-size:.82rem; }

/* INFO GRID */
.ig { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.9rem; }
.ii { background:var(--card-2); border:1px solid var(--border); border-radius:8px; padding:.6rem .8rem; }
.ik { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--text-3); margin-bottom:3px; }
.iv { font-size:.8rem; font-weight:600; color:var(--text); line-height:1.35; }

/* MAP */
.map-wrap { border-radius:10px; overflow:hidden; border:1px solid var(--border); margin-bottom:.9rem; position:relative; }
.map-container { height:200px; width:100%; background:var(--card-2); }
.map-legend { display:flex; align-items:center; gap:12px; padding:7px 11px; background:var(--card-2); border-top:1px solid var(--border); flex-wrap:wrap; }
.map-leg-item { display:flex; align-items:center; gap:5px; font-size:.68rem; font-weight:600; color:var(--text-3); }
.map-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.map-loading { position:absolute; inset:0; background:rgba(19,32,53,.9); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; font-size:.75rem; color:var(--text-3); z-index:10; }
.map-loading .spinner { width:20px; height:20px; border:2px solid var(--border); border-top-color:var(--amber); border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin{to{transform:rotate(360deg);}}

/* TIMELINE */
.tl { position:relative; padding-left:24px; margin-bottom:1rem; }
.tl::before { content:''; position:absolute; left:7px; top:10px; bottom:6px; width:2px; background:var(--border); }
.tl-item { position:relative; padding-bottom:14px; }
.tl-item:last-child { padding-bottom:0; }
.tl-dot { position:absolute; left:-24px; top:1px; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; z-index:1; border:2px solid var(--card); }
.tl-dot.done { background:var(--success); }
.tl-dot.done i { color:var(--navy); font-size:.55rem; font-weight:900; }
.tl-dot.pend { background:var(--card-3); border-color:var(--border); }
.tl-dot.pend i { color:var(--text-3); font-size:.55rem; }
.tl-lbl { font-size:.8rem; font-weight:700; margin-bottom:2px; }
.tl-done .tl-lbl { color:var(--text); }
.tl-pend .tl-lbl { color:var(--text-3); }
.tl-sub { font-size:.73rem; line-height:1.5; }
.tl-done .tl-sub { color:var(--text-2); }
.tl-pend .tl-sub { color:var(--text-3); }
.tl-time { font-size:.68rem; color:var(--text-3); margin-top:2px; display:flex; align-items:center; gap:3px; }

/* MATCH BOX */
.mbox { background:rgba(245,158,11,.06); border:1px solid rgba(245,158,11,.18); border-radius:12px; padding:1rem 1.1rem; margin-bottom:.9rem; }
.mbox-h { display:flex; align-items:center; justify-content:space-between; margin-bottom:.75rem; flex-wrap:wrap; gap:.4rem; }
.mbox-t { font-size:.84rem; font-weight:700; color:var(--amber); display:flex; align-items:center; gap:5px; }
.score-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; }
.score-lbl { font-size:.73rem; color:var(--text-3); }
.score-val { font-family:'Fraunces',serif; font-size:1rem; font-weight:700; }
.score-bar { height:5px; background:var(--border); border-radius:99px; overflow:hidden; margin-bottom:.75rem; }
.score-fill { height:100%; border-radius:99px; transition:width .9s ease; }
.mbox-grid { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.7rem; }
.mi { background:var(--card); border:1px solid var(--border); border-radius:8px; padding:.6rem .75rem; }
.mi-k { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; margin-bottom:3px; }
.mi-v { font-size:.8rem; font-weight:700; color:var(--text); }
.mi-s { font-size:.68rem; color:var(--text-3); margin-top:2px; }
.foto { border-radius:8px; overflow:hidden; border:1px solid var(--border); background:var(--card-2); margin-bottom:.7rem; }
.foto img { width:100%; display:block; max-height:140px; object-fit:cover; }
.foto-e { display:flex; align-items:center; justify-content:center; height:60px; font-size:2rem; opacity:.15; }

/* ALERTS */
.alrt-ok { background:rgba(52,211,153,.07); border:1px solid rgba(52,211,153,.22); border-radius:10px; padding:.85rem 1rem; margin-bottom:.9rem; }
.alrt-ok-t { font-size:.8rem; font-weight:700; color:var(--success); display:flex; align-items:center; gap:5px; margin-bottom:5px; }
.alrt-ok-b { font-size:.73rem; color:#7bcfac; line-height:1.65; }
.wait-box { background:var(--card-2); border:1px solid var(--border); border-radius:10px; padding:.9rem 1rem; }
.wait-box-t { font-size:.84rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:7px; margin-bottom:.4rem; }
.wait-box-b { font-size:.73rem; color:var(--text-3); line-height:1.7; }
.note-box { background:rgba(245,158,11,.06); border:1px solid rgba(245,158,11,.15); border-radius:8px; padding:.7rem .9rem; margin-bottom:.7rem; font-size:.73rem; color:var(--text-2); display:flex; gap:7px; line-height:1.55; }
.note-box i { flex-shrink:0; margin-top:1px; color:var(--amber); }
.dv { height:1px; background:var(--border); margin:.9rem 0; }
.act-row { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
.btn-edit { display:inline-flex; align-items:center; gap:5px; padding:6px 13px; background:var(--amber-dim); color:var(--amber); border:1px solid rgba(245,158,11,.3); border-radius:8px; font-size:.75rem; font-weight:700; text-decoration:none; transition:.15s; }
.btn-edit:hover { background:rgba(245,158,11,.22); color:var(--amber); }
.btn-back { display:inline-flex; align-items:center; gap:5px; padding:6px 13px; background:transparent; color:var(--text-3); border:1px solid var(--border-2); border-radius:8px; font-size:.75rem; font-weight:700; text-decoration:none; transition:.15s; }
.btn-back:hover { background:var(--card-2); color:var(--text); }
.upd-time { margin-left:auto; font-size:.68rem; color:var(--text-3); display:flex; align-items:center; gap:3px; }
.code-tag { font-family:'Courier New',monospace; font-size:.72rem; background:var(--amber-dim); color:var(--amber); padding:2px 6px; border-radius:4px; border:1px solid rgba(245,158,11,.2); }

/* EMPTY STATES */
.emp { text-align:center; padding:3rem 1.5rem; }
.emp-ico { font-size:3rem; opacity:.15; margin-bottom:.75rem; display:block; }
.emp-h { font-family:'Fraunces',serif; font-size:.95rem; font-weight:700; color:var(--text-2); margin-bottom:.35rem; }
.emp-s { font-size:.78rem; color:var(--text-3); line-height:1.65; }
.dp-emp { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:3.5rem 2rem; text-align:center; min-height:280px; }
.dp-emp i { font-size:2.5rem; color:var(--text-3); margin-bottom:.9rem; opacity:.4; }
.dp-emp h4 { font-family:'Fraunces',serif; font-size:.95rem; font-weight:700; color:var(--text-2); margin-bottom:.35rem; }
.dp-emp p { font-size:.78rem; color:var(--text-3); line-height:1.55; }

/* ANIMATIONS */
.fade-up { opacity:0; transform:translateY(14px); animation:fadeUp .4s ease forwards; }
@keyframes fadeUp { to{opacity:1;transform:translateY(0);} }
.d1{animation-delay:.05s;} .d2{animation-delay:.1s;} .d3{animation-delay:.15s;}
@media(max-width:991px){.cl-nav-links{display:none;}}
</style>
</head>
<body>


<!-- PAGE HEADER -->
<div class="page-header">
  <div class="ph-inner">
    <div class="ph-left">
      <div class="bc">
        <a href="index_petugas.php"><i class="bi bi-house"></i>Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <span>Lacak Laporan</span>
      </div>
      <div class="ph-title">
        <div class="ph-icon"><i class="bi bi-radar"></i></div>
        Lacak Laporan
      </div>
      <div class="ph-sub">Pantau status real-time laporan kehilangan barang Anda di KRL.</div>
      <div class="filter-strip">
        <a href="track.php<?= $search?'?q='.urlencode($search):'' ?>" class="fp <?= !$stFilter?'on':'' ?>">
          <div class="fdot" style="background:var(--text-3)"></div>
          Semua &nbsp;<strong><?= $total ?></strong>
        </a>
        <?php foreach (['diproses'=>['Diproses','#F59E0B'],'ditemukan'=>['Ditemukan','#60A5FA'],'selesai'=>['Selesai','#34D399']] as $k=>[$lbl,$col]):
          $cnt = $statCnt[$k];
          if (!$cnt && $k !== 'diproses') continue; ?>
        <a href="track.php?status=<?= $k ?><?= $search?'&q='.urlencode($search):'' ?>" class="fp <?= $stFilter===$k?'on':'' ?>">
          <div class="fdot" style="background:<?= $col ?>"></div>
          <?= $lbl ?> &nbsp;<strong><?= $cnt ?></strong>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    
    </a>
  </div>
</div>

<!-- MAIN WRAP -->
<div class="main-wrap">

  <!-- STAT STRIP -->
  <div class="stat-strip fade-up">
    <?php
    $strips = [
      ['key'=>'all',        'lbl'=>'Total Laporan', 'dot'=>'#5A7A9E', 'cnt'=>$total],
      ['key'=>'diproses',   'lbl'=>'Diproses',      'dot'=>'#F59E0B', 'cnt'=>$statCnt['diproses']],
      ['key'=>'ditemukan',  'lbl'=>'Ditemukan',     'dot'=>'#60A5FA', 'cnt'=>$statCnt['ditemukan']],
      ['key'=>'selesai',    'lbl'=>'Selesai',       'dot'=>'#34D399', 'cnt'=>$statCnt['selesai']],
    ];
    foreach ($strips as $s):
      $isActive = ($s['key']==='all' && !$stFilter) || ($s['key']===$stFilter);
      $href = $s['key']==='all' ? 'track.php' : 'track.php?status='.$s['key'];
    ?>
    <a href="<?= $href ?>" class="ss-item <?= $isActive?'active':'' ?>">
      <div>
        <div class="ss-num"><?= $s['cnt'] ?></div>
        <div class="ss-lbl"><?= $s['lbl'] ?></div>
      </div>
      <div class="ss-dot" style="background:<?= $s['dot'] ?>;"></div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- SPLIT LAYOUT -->
  <div class="split-layout">

    <!-- LIST PANEL -->
    <div class="fade-up d1">
      <div class="cl-toolbar">
        <form method="GET" class="cl-search">
          <?php if ($stFilter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($stFilter) ?>"><?php endif; ?>
          <i class="bi bi-search"></i>
          <input type="text" name="q" placeholder="Cari no. laporan, barang, lokasi…"
            value="<?= htmlspecialchars($search) ?>" autocomplete="off">
        </form>
        <?php if ($search || $stFilter): ?>
        <a href="track.php" class="btn-cl btn-ghost" style="padding:.4rem .8rem;font-size:.75rem;">
          <i class="bi bi-x"></i> Reset
        </a>
        <?php endif; ?>
      </div>

      <?php if (empty($laporan)): ?>
      <div class="emp">
        <span class="emp-ico">📋</span>
        <div class="emp-h"><?= $search||$stFilter ? 'Tidak ada hasil' : 'Belum ada laporan' ?></div>
        <div class="emp-s">
          <?php if ($search || $stFilter): ?>
            Coba ubah kata kunci atau hapus filter.
          <?php else: ?>
            Anda belum pernah membuat laporan kehilangan.<br>
            Klik <strong style="color:var(--amber)">Laporan Baru</strong> untuk memulai.
          <?php endif; ?>
        </div>
        <?php if (!$search && !$stFilter): ?>
        <a href="laporan_kehilangan.php?action=baru" class="btn-cl btn-amber" style="margin-top:1rem;">
          <i class="bi bi-plus-lg"></i> Buat Laporan Pertama
        </a>
        <?php endif; ?>
      </div>

      <?php else: ?>
      <?php foreach ($laporan as $lp):
        $cls  = ['diproses'=>'dp','ditemukan'=>'dt','selesai'=>'sl','ditutup'=>'xx'][$lp['status']] ?? 'xx';
        $bcl  = ['diproses'=>'st-dp','ditemukan'=>'st-dt','selesai'=>'st-sl','ditutup'=>'st-xx'][$lp['status']] ?? 'st-xx';
        $isAct = ($detailId === $lp['id']);
        $href  = qs($lp['id'], $stFilter, $search);
      ?>
      <a href="<?= $href ?>" class="lcard <?= $cls ?><?= $isAct?' active':'' ?>">
        <div class="lc-top">
          <span class="lc-code"><?= htmlspecialchars($lp['no_laporan']) ?></span>
          <span class="st-badge <?= $bcl ?>"><?= $stLbl[$lp['status']] ?? $lp['status'] ?></span>
        </div>
        <div class="lc-nama"><?= htmlspecialchars($lp['nama_barang']) ?></div>
        <div class="lc-meta">
          <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars(mb_substr($lp['lokasi_hilang'],0,30)).(mb_strlen($lp['lokasi_hilang'])>30?'…':'') ?></span>
          <span><i class="bi bi-calendar3"></i> <?= $lp['waktu_hilang'] ? date('d M Y', strtotime($lp['waktu_hilang'])) : '—' ?></span>
        </div>
        <div class="lc-foot">
          <span class="lc-date">Dilaporkan <?= $lp['created_at'] ? date('d/m/Y', strtotime($lp['created_at'])) : '—' ?></span>
          <?php if ($lp['cocok_id']): ?>
          <span class="match-pill"><i class="bi bi-puzzle"></i> Cocok ditemukan</span>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div><!-- /list panel -->

    <!-- DETAIL PANEL -->
    <div class="fade-up d2">
      <div class="dpanel">
        <?php if (!$dr): ?>
        <div class="dp-emp">
          <i class="bi bi-radar"></i>
          <h4>Pilih Laporan</h4>
          <p>Klik salah satu laporan di sebelah kiri<br>untuk melihat detail dan status terkini.</p>
        </div>

        <?php else:
          $bcl2   = ['diproses'=>'st-dp','ditemukan'=>'st-dt','selesai'=>'st-sl','ditutup'=>'st-xx'][$dr['status']] ?? 'st-xx';
          $sColor = $sScore>=70 ? 'var(--success)' : ($sScore>=50 ? 'var(--amber)' : 'var(--danger)');
          $mapLokHilang = addslashes($dr['lokasi_hilang']    ?? '');
          $mapLokTemuan = addslashes($dr['lokasi_ditemukan'] ?? '');
          $mapHasCocok  = (!empty($dr['cocok_id']) && !empty($dr['lokasi_ditemukan'])) ? 'true' : 'false';
        ?>

        <!-- HEADER -->
        <div class="dp-head">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <div>
              <span class="dp-code"><?= htmlspecialchars($dr['no_laporan']) ?></span>
              <div class="dp-title"><?= htmlspecialchars($dr['nama_barang']) ?></div>
              <div class="dp-sub">Dilaporkan <?= $dr['created_at'] ? date('d M Y, H:i', strtotime($dr['created_at'])) : '—' ?></div>
            </div>
            <span class="st-badge <?= $bcl2 ?>" style="font-size:.72rem;padding:4px 11px;white-space:nowrap;">
              <?= $stLbl[$dr['status']] ?? $dr['status'] ?>
            </span>
          </div>
        </div>

        <div class="dp-body">

          <!-- PETA -->
          <div class="slbl"><i class="bi bi-map" style="color:var(--info)"></i>Peta Lokasi</div>
          <div class="map-wrap">
            <div class="map-loading" id="mapLoading">
              <div class="spinner"></div>
              <span>Memuat peta…</span>
            </div>
            <div id="mainMap" class="map-container"></div>
            <div class="map-legend">
              <div class="map-leg-item">
                <div class="map-dot" style="background:#F87171;box-shadow:0 0 0 2px rgba(248,113,113,.25);"></div>
                Lokasi Hilang
              </div>
              <?php if (!empty($dr['lokasi_ditemukan'])): ?>
              <div class="map-leg-item">
                <div class="map-dot" style="background:#34D399;box-shadow:0 0 0 2px rgba(52,211,153,.25);"></div>
                Lokasi Ditemukan
              </div>
              <?php endif; ?>
              <span style="margin-left:auto;font-size:.65rem;color:var(--text-3);">© OpenStreetMap</span>
            </div>
          </div>

          <!-- TIMELINE -->
          <div class="slbl"><i class="bi bi-activity" style="color:var(--info)"></i>Progress Laporan</div>
          <div class="tl">
            <?php foreach ($timeline as $tl): ?>
            <div class="tl-item <?= $tl['done'] ? 'tl-done' : 'tl-pend' ?>">
              <div class="tl-dot <?= $tl['done'] ? 'done' : 'pend' ?>">
                <?php if ($tl['done']): ?><i class="bi bi-check-lg"></i>
                <?php else: ?><i class="bi bi-three-dots"></i><?php endif; ?>
              </div>
              <div class="tl-lbl"><?= htmlspecialchars($tl['lbl']) ?></div>
              <div class="tl-sub"><?= htmlspecialchars($tl['sub']) ?></div>
              <?php if ($tl['tgl']): ?>
              <div class="tl-time"><i class="bi bi-clock"></i><?= date('d M Y, H:i', strtotime($tl['tgl'])) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- DETAIL BARANG HILANG -->
          <div class="slbl"><i class="bi bi-search" style="color:var(--amber)"></i>Detail Barang Hilang</div>
          <div class="ig">
            <div class="ii"><div class="ik">Nama Barang</div><div class="iv"><?= htmlspecialchars($dr['nama_barang']) ?></div></div>
            <div class="ii"><div class="ik">Waktu Hilang</div><div class="iv"><?= $dr['waktu_hilang'] ? date('d M Y H:i', strtotime($dr['waktu_hilang'])) : '—' ?></div></div>
            <div class="ii"><div class="ik">Lokasi Hilang</div><div class="iv"><?= htmlspecialchars($dr['lokasi_hilang']) ?></div></div>
            <div class="ii"><div class="ik">Status</div><div class="iv"><span class="st-badge <?= $bcl2 ?>"><?= $stLbl[$dr['status']] ?? $dr['status'] ?></span></div></div>
          </div>
          <?php if (!empty($dr['deskripsi'])): ?>
          <div style="background:var(--card-2);border:1px solid var(--border);border-radius:8px;padding:.65rem .85rem;margin-bottom:.9rem;font-size:.75rem;color:var(--text-2);line-height:1.6;">
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-3);margin-bottom:4px;">Deskripsi</div>
            <?= htmlspecialchars($dr['deskripsi']) ?>
          </div>
          <?php endif; ?>

          <!-- MATCH BOX -->
          <?php if ($dr['cocok_id']): ?>
          <div class="dv"></div>
          <div class="slbl"><i class="bi bi-puzzle" style="color:var(--amber)"></i>Barang Temuan Dicocokkan</div>
          <div class="mbox">
            <div class="mbox-h">
              <div class="mbox-t"><i class="bi bi-stars"></i> Kecocokan Ditemukan!</div>
              <span class="st-badge <?= $csCl[$dr['cocok_status']] ?? 'st-wait' ?>"><?= $csLbl[$dr['cocok_status']] ?? $dr['cocok_status'] ?></span>
            </div>
            <?php if ($sScore > 0): ?>
            <div class="score-row">
              <span class="score-lbl">Tingkat Kecocokan</span>
              <span class="score-val" style="color:<?= $sColor ?>"><?= $sScore ?>%</span>
            </div>
            <div class="score-bar">
              <div class="score-fill" id="sf1" style="width:0%;background:<?= $sColor ?>"></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($dr['foto_barang'])): ?>
            <div class="foto"><img src="<?= htmlspecialchars($dr['foto_barang']) ?>" alt="Barang temuan" loading="lazy"></div>
            <?php else: ?><div class="foto"><div class="foto-e">📦</div></div><?php endif; ?>
            <div class="mbox-grid">
              <div class="mi">
                <div class="mi-k" style="color:var(--success);">📦 Barang Temuan</div>
                <div class="mi-v"><?= htmlspecialchars($dr['bt_nama'] ?? '—') ?></div>
                <div class="mi-s"><span class="code-tag"><?= htmlspecialchars($dr['kode_barang'] ?? '—') ?></span></div>
              </div>
              <div class="mi">
                <div class="mi-k" style="color:var(--info);">📍 Lokasi Ditemukan</div>
                <div class="mi-v"><?= htmlspecialchars($dr['lokasi_ditemukan'] ?? '—') ?></div>
                <div class="mi-s"><?= !empty($dr['waktu_ditemukan']) ? date('d M Y', strtotime($dr['waktu_ditemukan'])) : '—' ?></div>
              </div>
            </div>
            <?php if (!empty($dr['bt_deskripsi'])): ?>
            <div style="font-size:.73rem;color:var(--text-2);background:var(--card);border:1px solid var(--border);border-radius:7px;padding:.5rem .7rem;margin-bottom:.6rem;line-height:1.5;">
              <?= htmlspecialchars(mb_substr($dr['bt_deskripsi'],0,110)).(mb_strlen($dr['bt_deskripsi'])>110?'…':'') ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($dr['nama_petugas'])): ?>
            <div style="display:flex;align-items:center;gap:5px;font-size:.73rem;color:var(--text-3);">
              <i class="bi bi-person-badge" style="color:var(--amber);"></i>
              Ditangani oleh: <strong style="color:var(--text);"><?= htmlspecialchars($dr['nama_petugas']) ?></strong>
            </div>
            <?php endif; ?>
          </div>

          <?php if (in_array($dr['status'], ['ditemukan','selesai'])): ?>
          <div class="alrt-ok">
            <div class="alrt-ok-t">
              <i class="bi bi-check-circle-fill"></i>
              <?= $dr['status']==='selesai' ? 'Serah terima selesai! 🎉' : 'Barang Anda ditemukan!' ?>
            </div>
            <div class="alrt-ok-b">
              <?php if ($dr['status']==='selesai'): ?>
                Proses serah terima telah selesai. Terima kasih telah menggunakan layanan CommuterLink.
              <?php else: ?>
                Harap <strong>segera hubungi petugas stasiun</strong> atau tunggu konfirmasi dari tim kami.<br>
                Bawa <strong>kartu identitas (KTP/SIM)</strong> saat pengambilan barang.
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($dr['cocok_catatan']) && !str_contains($dr['cocok_catatan'],'Auto-suggested')): ?>
          <div class="note-box">
            <i class="bi bi-chat-text"></i>
            <div><strong style="color:var(--amber);">Catatan Petugas:</strong> <?= htmlspecialchars($dr['cocok_catatan']) ?></div>
          </div>
          <?php endif; ?>

          <?php else: ?>
          <div class="dv"></div>
          <div class="wait-box">
            <div class="wait-box-t"><i class="bi bi-hourglass-split" style="color:var(--amber);"></i>Laporan sedang diproses</div>
            <div class="wait-box-b">
              Tim kami aktif memantau setiap barang temuan yang masuk.<br>
              Anda akan mendapat notifikasi bila ada kecocokan.<br><br>
              <span style="color:var(--amber);font-weight:700;">💡 Tips:</span>
              Pastikan deskripsi barang Anda <strong style="color:var(--text);">selengkap mungkin</strong> untuk mempercepat pencocokan.
            </div>
          </div>
          <?php endif; ?>

          <div class="dv"></div>
          <div class="act-row">
            <?php if ($dr['status'] === 'diproses'): ?>
            <a href="laporan_kehilangan.php?edit=<?= $dr['id'] ?>" class="btn-edit"><i class="bi bi-pencil"></i> Edit</a>
            <?php endif; ?>
            <a href="track.php<?= $stFilter?'?status='.urlencode($stFilter):'' ?>" class="btn-back"><i class="bi bi-arrow-left"></i> Semua</a>
            <span class="upd-time"><i class="bi bi-arrow-clockwise"></i><?= !empty($dr['updated_at']) ? date('d/m/Y H:i', strtotime($dr['updated_at'])) : date('d/m/Y H:i') ?></span>
          </div>

        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/*  SCORE BAR  */
document.addEventListener('DOMContentLoaded', () => {
  const sf = document.getElementById('sf1');
  if (sf) setTimeout(() => { sf.style.width = '<?= $sScore ?>%'; }, 300);
});

/* LEAFLET MAP  */
<?php if ($dr): ?>
(async function initMap() {
  const mapEl  = document.getElementById('mainMap');
  const loadEl = document.getElementById('mapLoading');
  if (!mapEl) return;

  const lokHilang = "<?= $mapLokHilang ?>, Indonesia";
  const lokTemuan = "<?= $mapLokTemuan ?>, Indonesia";
  const hasCocok  = <?= $mapHasCocok ?>;

  const iconRed = L.divIcon({
    className: '',
    html: `<div style="width:14px;height:14px;background:#F87171;border:2.5px solid #fff;border-radius:50%;box-shadow:0 0 0 3px rgba(248,113,113,.3),0 2px 8px rgba(0,0,0,.4);"></div>`,
    iconSize: [14,14], iconAnchor: [7,7]
  });
  const iconGreen = L.divIcon({
    className: '',
    html: `<div style="width:14px;height:14px;background:#34D399;border:2.5px solid #fff;border-radius:50%;box-shadow:0 0 0 3px rgba(52,211,153,.3),0 2px 8px rgba(0,0,0,.4);"></div>`,
    iconSize: [14,14], iconAnchor: [7,7]
  });

  async function geocode(q) {
    try {
      const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=json&limit=1&countrycodes=id`;
      const res = await fetch(url, { headers: { 'Accept-Language': 'id' } });
      const d   = await res.json();
      if (d && d[0]) return [parseFloat(d[0].lat), parseFloat(d[0].lon)];
    } catch(e) {}
    return null;
  }

  const map = L.map('mainMap', { zoomControl:true, attributionControl:false })
    .setView([-6.2, 106.82], 11);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom:19 }).addTo(map);
  L.control.attribution({ prefix:'© <a href="https://openstreetmap.org" style="color:#5A7A9E">OSM</a>' }).addTo(map);

  const bounds = [];

  const ptHilang = await geocode(lokHilang);
  if (ptHilang) {
    bounds.push(ptHilang);
    L.marker(ptHilang, { icon: iconRed })
      .addTo(map)
      .bindPopup(`
        <div style="font-family:'DM Sans',sans-serif;min-width:150px;background:#132035;color:#EBF4FF;border-radius:8px;padding:4px;">
          <div style="font-size:11px;font-weight:700;color:#F87171;margin-bottom:3px;">📍 Lokasi Hilang</div>
          <div style="font-size:11px;line-height:1.4;"><?= htmlspecialchars(addslashes($dr['lokasi_hilang'])) ?></div>
          <?php if ($dr['waktu_hilang']): ?>
          <div style="font-size:10px;color:#5A7A9E;margin-top:4px;">🕐 <?= date('d M Y H:i', strtotime($dr['waktu_hilang'])) ?></div>
          <?php endif; ?>
        </div>`, { maxWidth:220, className:'dark-popup' })
      .openPopup();
  }

  if (hasCocok && lokTemuan.trim().length > 5) {
    const ptTemuan = await geocode(lokTemuan);
    if (ptTemuan) {
      bounds.push(ptTemuan);
      L.marker(ptTemuan, { icon: iconGreen })
        .addTo(map)
        .bindPopup(`
          <div style="font-family:'DM Sans',sans-serif;min-width:150px;">
            <div style="font-size:11px;font-weight:700;color:#34D399;margin-bottom:3px;">✅ Lokasi Ditemukan</div>
            <div style="font-size:11px;line-height:1.4;"><?= htmlspecialchars(addslashes($dr['lokasi_ditemukan'] ?? '')) ?></div>
            <?php if (!empty($dr['waktu_ditemukan'])): ?>
            <div style="font-size:10px;color:#5A7A9E;margin-top:4px;">🕐 <?= date('d M Y', strtotime($dr['waktu_ditemukan'] ?? 'now')) ?></div>
            <?php endif; ?>
          </div>`, { maxWidth:220 });

      if (bounds.length === 2) {
        L.polyline(bounds, {
          color:'#F59E0B', weight:2.5,
          dashArray:'6,5', opacity:.7
        }).addTo(map);
      }
    }
  }

  if (bounds.length > 1) {
    map.fitBounds(bounds, { padding:[30,30] });
  } else if (bounds.length === 1) {
    map.setView(bounds[0], 14);
  }

  if (loadEl) loadEl.style.display = 'none';
  setTimeout(() => map.invalidateSize(), 200);
})();
<?php endif; ?>
</script>
</body>
</html>