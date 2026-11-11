<?php
/**
 * AMANG LAUNDRY - PORTUGAL FOOTBALL EDITION (CR7 THEME)
 * File 3/3: admin.php
 * - Jika Password Benar: Menampilkan data asli hasil dekripsi dari database.
 * - Jika Password Salah: Menampilkan angka & huruf acak (Ciphertext, Nonce, Tag) yang diambil langsung dari database.
 */
require_once __DIR__ . '/config.php';

// 1. Proses Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutAdmin();
    header('Location: index.php?logged_out=1#login-admin');
    exit;
}

// 2. Proteksi Halaman
requireAdminAccess();

$encryptionKey = getSessionEncryptionKey();
$keyIsValid    = isKeyValid();
$page          = $_GET['page'] ?? 'dashboard';
$allowedPages  = ['dashboard', 'pesanan', 'tambah', 'edit', 'detail', 'layanan'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// 3. Pemrosesan Seluruh Operasi CRUD (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token CSRF tidak valid. Aksi dibatalkan.';
        header('Location: admin.php?page=' . urlencode($page));
        exit;
    }

    if (!$keyIsValid) {
        $_SESSION['flash_error'] = 'Operasi ditolak! Anda sedang login dengan Password (Secret Key) yang salah. Silakan Logout dan login dengan password yang benar untuk mengubah data.';
        header('Location: admin.php?page=' . urlencode($page));
        exit;
    }

    $act = $_POST['form_action'];

    // A. Tambah Pesanan
    if ($act === 'create_pesanan') {
        $berat = (float)($_POST['berat'] ?? 0);
        $harga = (int)($_POST['harga_per_kg'] ?? 0);
        $payload = [
            'nama_pelanggan' => cleanInput($_POST['nama_pelanggan'] ?? ''),
            'nomor_telepon'  => cleanInput($_POST['nomor_telepon'] ?? ''),
            'alamat'         => cleanInput($_POST['alamat'] ?? ''),
            'jenis_layanan'  => cleanInput($_POST['jenis_layanan'] ?? ''),
            'berat'          => $berat,
            'harga_per_kg'   => $harga,
            'total_harga'    => (int)round($berat * $harga),
            'status'         => cleanInput($_POST['status'] ?? 'Menunggu'),
            'catatan'        => cleanInput($_POST['catatan'] ?? '')
        ];

        $validStatus = ['Menunggu', 'Diproses', 'Selesai', 'Diambil'];
        if ($payload['nama_pelanggan'] === '' || $payload['nomor_telepon'] === '' || $berat <= 0 || $harga <= 0 || !in_array($payload['status'], $validStatus, true)) {
            $_SESSION['flash_error'] = 'Harap isi seluruh data pesanan dengan benar.';
            header('Location: admin.php?page=tambah');
            exit;
        }

        if (createPesanan($pdo, $payload, $encryptionKey)) {
            $_SESSION['flash_success'] = 'Pesanan berhasil dienkripsi (AES-256-GCM) dan disimpan ke MySQL.';
            header('Location: admin.php?page=pesanan');
            exit;
        } else {
            $_SESSION['flash_error'] = 'Gagal mengenkripsi pesanan.';
            header('Location: admin.php?page=tambah');
            exit;
        }
    }

    // B. Edit Pesanan
    if ($act === 'update_pesanan') {
        $id    = (int)($_POST['id'] ?? 0);
        $berat = (float)($_POST['berat'] ?? 0);
        $harga = (int)($_POST['harga_per_kg'] ?? 0);
        $payload = [
            'nama_pelanggan' => cleanInput($_POST['nama_pelanggan'] ?? ''),
            'nomor_telepon'  => cleanInput($_POST['nomor_telepon'] ?? ''),
            'alamat'         => cleanInput($_POST['alamat'] ?? ''),
            'jenis_layanan'  => cleanInput($_POST['jenis_layanan'] ?? ''),
            'berat'          => $berat,
            'harga_per_kg'   => $harga,
            'total_harga'    => (int)round($berat * $harga),
            'status'         => cleanInput($_POST['status'] ?? 'Menunggu'),
            'catatan'        => cleanInput($_POST['catatan'] ?? '')
        ];

        if ($id > 0 && $payload['nama_pelanggan'] !== '' && $berat > 0 && $harga > 0) {
            if (updatePesanan($pdo, $id, $payload, $encryptionKey)) {
                $_SESSION['flash_success'] = 'Pesanan #' . $id . ' diperbarui & dienkripsi ulang dengan Nonce baru.';
                header('Location: admin.php?page=pesanan');
                exit;
            }
        }
        $_SESSION['flash_error'] = 'Gagal memperbarui pesanan.';
        header('Location: admin.php?page=edit&id=' . $id);
        exit;
    }

    // C. Hapus Pesanan
    if ($act === 'delete_pesanan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && deletePesanan($pdo, $id)) {
            $_SESSION['flash_success'] = 'Pesanan #' . $id . ' berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus pesanan.';
        }
        $from = $_POST['from_page'] ?? 'pesanan';
        header('Location: admin.php?page=' . urlencode($from));
        exit;
    }

    // D. Tambah Layanan
    if ($act === 'create_layanan') {
        $nama  = cleanInput($_POST['nama_layanan'] ?? '');
        $harga = (int)($_POST['harga'] ?? 0);
        if ($nama !== '' && $harga > 0 && createLayanan($pdo, $nama, $harga)) {
            $_SESSION['flash_success'] = 'Layanan baru berhasil dienkripsi dan ditambahkan.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menambah layanan.';
        }
        header('Location: admin.php?page=layanan');
        exit;
    }

    // E. Edit Layanan
    if ($act === 'update_layanan') {
        $id    = (int)($_POST['id'] ?? 0);
        $nama  = cleanInput($_POST['nama_layanan'] ?? '');
        $harga = (int)($_POST['harga'] ?? 0);
        if ($id > 0 && $nama !== '' && $harga > 0 && updateLayanan($pdo, $id, $nama, $harga)) {
            $_SESSION['flash_success'] = 'Layanan #' . $id . ' berhasil diperbarui.';
        } else {
            $_SESSION['flash_error'] = 'Gagal memperbarui layanan.';
        }
        header('Location: admin.php?page=layanan');
        exit;
    }

    // F. Hapus Layanan
    if ($act === 'delete_layanan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && deleteLayanan($pdo, $id)) {
            $_SESSION['flash_success'] = 'Layanan berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus layanan.';
        }
        header('Location: admin.php?page=layanan');
        exit;
    }
}

// 4. Ambil Data dari Database MySQL
$daftarLayanan = getAllLayananForAdmin($pdo, $keyIsValid);
$hasilPesanan  = getAllPesananDecrypted($pdo, $encryptionKey);
$daftarPesanan = $hasilPesanan['items'];

// 5. Hitung Statistik Pesanan
$totalPesanan = count($daftarPesanan);
$jmlMenunggu  = 0;
$jmlDiproses  = 0;
$jmlSelesai   = 0;

foreach ($daftarPesanan as $item) {
    if (!$item['decryption_ok']) {
        continue;
    }
    $st = $item['data']['status'] ?? '';
    if ($st === 'Menunggu') {
        $jmlMenunggu++;
    } elseif ($st === 'Diproses') {
        $jmlDiproses++;
    } elseif ($st === 'Selesai' || $st === 'Diambil') {
        $jmlSelesai++;
    }
}

// Sampel potongan hex dari database untuk ditampilkan di kartu statistik saat password salah
$sampleCipherStat1 = !empty($daftarPesanan) ? substr($daftarPesanan[0]['nonce'], 0, 8) : '9F4A2C1E';
$sampleCipherStat2 = !empty($daftarPesanan) ? substr($daftarPesanan[0]['auth_tag'], 0, 8) : '7B3D8E0A';
$sampleCipherStat3 = !empty($daftarPesanan) ? substr($daftarPesanan[0]['encrypted_data'], 0, 8) : 'X8kP2mQ9';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin CR7 Command Center — AMANG LAUNDRY</title>
    <style>
        :root {
            --pt-green: #046A38;
            --pt-green-dark: #024724;
            --pt-red: #DA291C;
            --pt-red-dark: #9e1910;
            --pt-gold: #D4AF37;
            --pt-black: #111315;
            --pt-cream: #F5E9D0;
            --pt-white: #FFFFFF;
            --pt-gray: #666666;
            --radius: 10px;
            --shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f2f4f3;
            color: var(--pt-black);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar CR7 Portugal */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--pt-black) 0%, var(--pt-green-dark) 100%);
            color: var(--pt-white);
            border-right: 3px solid var(--pt-gold);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 1.4rem 1.3rem;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-brand {
            font-weight: 900;
            font-size: 1.15rem;
            color: var(--pt-white);
            letter-spacing: 0.5px;
        }

        .badge-7 {
            background-color: var(--pt-red);
            color: var(--pt-gold);
            border: 1px solid var(--pt-gold);
            font-weight: 900;
            padding: 0.15rem 0.5rem;
            border-radius: 5px;
            font-size: 0.85rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0.8rem;
            flex: 1;
        }

        .sidebar-menu li {
            margin-bottom: 0.4rem;
        }

        .sidebar-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: var(--pt-cream);
            text-decoration: none;
            border-radius: 7px;
            font-weight: 600;
            font-size: 0.92rem;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.1);
            color: var(--pt-gold);
        }

        .sidebar-menu a.active {
            background-color: var(--pt-green);
            color: var(--pt-white);
            border-left: 4px solid var(--pt-gold);
            font-weight: 700;
        }

        .sidebar-footer {
            padding: 1rem 1.2rem;
            border-top: 1px solid rgba(212, 175, 55, 0.25);
            font-size: 0.78rem;
            color: var(--pt-cream);
        }

        /* Main Area */
        .main-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .topbar {
            background-color: var(--pt-white);
            padding: 1rem 1.8rem;
            border-bottom: 2px solid var(--pt-gold);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .topbar-title h1 {
            font-size: 1.35rem;
            color: var(--pt-green);
            font-weight: 900;
        }

        .topbar-title p {
            font-size: 0.85rem;
            color: var(--pt-gray);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .admin-tag {
            background-color: var(--pt-cream);
            border: 1px solid var(--pt-gold);
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .btn {
            display: inline-block;
            padding: 0.55rem 1.05rem;
            border-radius: 6px;
            font-size: 0.86rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }

        .btn-green { background-color: var(--pt-green); color: var(--pt-white); }
        .btn-green:hover { background-color: var(--pt-green-dark); }
        .btn-red { background-color: var(--pt-red); color: var(--pt-white); }
        .btn-red:hover { background-color: var(--pt-red-dark); }
        .btn-outline { background-color: var(--pt-cream); color: var(--pt-black); border: 1px solid #d4c49e; }
        .btn-sm { padding: 0.32rem 0.65rem; font-size: 0.78rem; }

        .content {
            padding: 1.75rem;
            flex: 1;
        }

        .alert {
            padding: 0.9rem 1.15rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .alert-success { background-color: #e6f4ed; color: var(--pt-green-dark); border-left: 5px solid var(--pt-green); }
        .alert-danger { background-color: #fde8e7; color: var(--pt-red-dark); border-left: 5px solid var(--pt-red); }

        /* Stat Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background-color: var(--pt-white);
            border-radius: var(--radius);
            padding: 1.35rem;
            box-shadow: var(--shadow);
            border-top: 4px solid var(--pt-green);
        }

        .stat-card.red { border-top-color: var(--pt-red); }
        .stat-card.gold { border-top-color: var(--pt-gold); }

        .stat-card span {
            font-size: 0.84rem;
            color: var(--pt-gray);
            font-weight: 700;
        }

        .stat-card strong {
            display: block;
            font-size: 1.75rem;
            margin-top: 0.25rem;
            color: var(--pt-black);
            word-break: break-all;
        }

        /* Security Banner */
        .security-banner {
            background: linear-gradient(135deg, var(--pt-black) 0%, var(--pt-green-dark) 100%);
            color: var(--pt-white);
            border-left: 6px solid var(--pt-gold);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .security-banner.locked {
            background: linear-gradient(135deg, #2b0907 0%, var(--pt-red-dark) 100%);
        }

        .security-banner h3 { color: var(--pt-gold); font-size: 1.02rem; }
        .security-banner p { font-size: 0.85rem; color: var(--pt-cream); }

        .sec-pills { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .sec-pill {
            background-color: rgba(255,255,255,0.12);
            border: 1px solid var(--pt-gold);
            color: var(--pt-white);
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        /* Panel Card & Tables */
        .card {
            background-color: var(--pt-white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.15rem;
            flex-wrap: wrap;
            gap: 0.8rem;
        }

        .card-header h2 {
            font-size: 1.15rem;
            color: var(--pt-green);
        }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        table th {
            background-color: var(--pt-green);
            color: var(--pt-white);
            text-align: left;
            padding: 0.8rem 0.9rem;
            font-size: 0.85rem;
            border-bottom: 2px solid var(--pt-gold);
        }
        table td {
            padding: 0.8rem 0.9rem;
            border-bottom: 1px solid #eeeeee;
            font-size: 0.88rem;
            vertical-align: middle;
        }

        /* Tampilan Angka & Huruf Acak dari Database saat Password Salah */
        .db-cipher-text {
            font-family: 'Consolas', 'Courier New', monospace;
            font-weight: 700;
            color: var(--pt-red-dark);
            background-color: #fff4f3;
            padding: 0.22rem 0.5rem;
            border-radius: 4px;
            border: 1px dashed #e69994;
            font-size: 0.82rem;
            display: inline-block;
            word-break: break-all;
        }

        .badge-status {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.76rem;
            font-weight: 800;
        }
        .st-Menunggu { background-color: #fff3cd; color: #856404; }
        .st-Diproses { background-color: #cce5ff; color: #004085; }
        .st-Selesai  { background-color: #d4edda; color: #155724; }
        .st-Diambil  { background-color: #e2e3e5; color: #383d41; }

        .actions { display: flex; gap: 0.35rem; flex-wrap: wrap; }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.86rem;
            margin-bottom: 0.35rem;
        }
        .form-control {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 1px solid #cccccc;
            border-radius: 6px;
            font-size: 0.92rem;
            font-family: inherit;
        }
        .form-control[readonly] {
            background-color: var(--pt-cream);
            font-weight: 800;
        }

        .cipher-inspector {
            background-color: var(--pt-black);
            color: var(--pt-gold);
            padding: 1rem;
            border-radius: 8px;
            font-family: Consolas, monospace;
            font-size: 0.82rem;
            word-break: break-all;
            margin-top: 1rem;
            border-left: 4px solid var(--pt-red);
        }

        @media (max-width: 820px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; }
            .sidebar-menu { display: flex; flex-wrap: wrap; gap: 0.4rem; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <span class="sidebar-brand">AMANG LAUNDRY</span>
            <span class="badge-7">CR7</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin.php?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a href="admin.php?page=pesanan" class="<?php echo in_array($page, ['pesanan', 'edit', 'detail'], true) ? 'active' : ''; ?>">Data Pesanan</a></li>
            <li><a href="admin.php?page=tambah" class="<?php echo $page === 'tambah' ? 'active' : ''; ?>">+ Tambah Pesanan</a></li>
            <li><a href="admin.php?page=layanan" class="<?php echo $page === 'layanan' ? 'active' : ''; ?>">Data Layanan</a></li>
            <li><a href="index.php">Lihat Landing Page</a></li>
            <li><a href="admin.php?action=logout" style="color:#ff9e97;">Logout</a></li>
        </ul>
        <div class="sidebar-footer">
            Portugal Football Edition<br>
            Cipher: <strong>AES-256-GCM</strong><br>
            Data: <strong><?php echo $keyIsValid ? 'TERDEKRIPSI (BENAR)' : 'ACAK (CIPHERTEXT DB)'; ?></strong>
        </div>
    </aside>

    <!-- MAIN AREA -->
    <div class="main-area">
        <header class="topbar">
            <div class="topbar-title">
                <h1>CR7 Admin Command Center</h1>
                <p>
                    Selamat Datang, <strong><?php echo e($_SESSION['admin_username']); ?></strong> &bull;
                    <?php if ($keyIsValid): ?>
                        <span style="color:var(--pt-green); font-weight:700;">Password Benar (Menampilkan Data Asli Hasil Dekripsi)</span>
                    <?php else: ?>
                        <span style="color:var(--pt-red); font-weight:700;">Password Salah (Menampilkan Huruf & Angka Acak dari Database)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="topbar-right">
                <span class="admin-tag">
                    <?php echo $keyIsValid ? '&#128275; Data Asli (Plaintext)' : '&#128274; Data Acak (Ciphertext)'; ?>
                </span>
                <a href="admin.php?action=logout" class="btn btn-red">Logout</a>
            </div>
        </header>

        <main class="content">
            <?php if ($flashSuccess !== ''): ?>
                <div class="alert alert-success"><?php echo e($flashSuccess); ?></div>
            <?php endif; ?>

            <?php if ($flashError !== ''): ?>
                <div class="alert alert-danger"><?php echo e($flashError); ?></div>
            <?php endif; ?>

            <?php if ($page === 'dashboard'): ?>
                <!-- STATISTIK PESANAN -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span>Total Pesanan</span>
                        <strong><?php echo $keyIsValid ? (int)$totalPesanan : e($sampleCipherStat1); ?></strong>
                    </div>
                    <div class="stat-card red">
                        <span>Pesanan Masuk (Menunggu)</span>
                        <strong><?php echo $keyIsValid ? (int)$jmlMenunggu : e($sampleCipherStat2); ?></strong>
                    </div>
                    <div class="stat-card gold">
                        <span>Sedang Diproses</span>
                        <strong><?php echo $keyIsValid ? (int)$jmlDiproses : e($sampleCipherStat3); ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Pesanan Selesai / Diambil</span>
                        <strong><?php echo $keyIsValid ? (int)$jmlSelesai : e(strrev($sampleCipherStat1)); ?></strong>
                    </div>
                </div>

                <!-- BANNER STATUS KEAMANAN -->
                <div class="security-banner <?php echo $keyIsValid ? '' : 'locked'; ?>">
                    <div>
                        <h3>
                            <?php echo $keyIsValid
                                ? '&#128275; Keamanan Data Aktif — Dekripsi AES-256-GCM Berhasil'
                                : '&#128274; Password Salah — Data Ditampilkan Sebagai Huruf & Angka Enkripsi Database'; ?>
                        </h3>
                        <p>
                            <?php echo $keyIsValid
                                ? 'Data pelanggan diambil dari database MySQL dan berhasil didekripsi menggunakan kunci AES-256 yang benar.'
                                : 'Karena password salah, dekripsi ditolak dan tabel menampilkan string encrypted_data, nonce, dan auth_tag langsung dari MySQL.'; ?>
                        </p>
                    </div>
                    <div class="sec-pills">
                        <span class="sec-pill">Enkripsi: Aktif</span>
                        <span class="sec-pill">Key Hash: <?php echo strtoupper(substr($_SESSION['aes_key_hex'], 0, 10)); ?>...</span>
                        <span class="sec-pill">Dekripsi: <?php echo $keyIsValid ? 'Berhasil' : 'Gagal (Tampil Ciphertext)'; ?></span>
                    </div>
                </div>

                <!-- TABEL PESANAN TERBARU -->
                <div class="card">
                    <div class="card-header">
                        <h2>Tabel Pesanan Terbaru (Dari Database MySQL)</h2>
                        <div>
                            <?php if ($keyIsValid): ?>
                                <a href="admin.php?page=tambah" class="btn btn-green">+ Tambah Pesanan</a>
                            <?php endif; ?>
                            <a href="admin.php?page=pesanan" class="btn btn-outline">Semua Pesanan</a>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Pelanggan</th>
                                    <th>Layanan</th>
                                    <th>Berat</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $terbaru = array_slice($daftarPesanan, 0, 5);
                                if (!empty($terbaru)):
                                    $no = 1;
                                    foreach ($terbaru as $r):
                                        $d = $r['data'];
                                ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <?php if ($r['decryption_ok']): ?>
                                            <!-- JIKA PASSWORD BENAR: TAMPIL DATA ASLI -->
                                            <td><strong><?php echo e($d['nama_pelanggan']); ?></strong></td>
                                            <td><?php echo e($d['jenis_layanan']); ?></td>
                                            <td><?php echo e($d['berat']); ?> kg</td>
                                            <td><strong><?php echo formatRupiah($d['total_harga']); ?></strong></td>
                                            <td><span class="badge-status st-<?php echo e($d['status']); ?>"><?php echo e($d['status']); ?></span></td>
                                            <td>
                                                <div class="actions">
                                                    <a href="admin.php?page=detail&id=<?php echo (int)$r['id']; ?>" class="btn btn-outline btn-sm">Detail</a>
                                                    <a href="admin.php?page=edit&id=<?php echo (int)$r['id']; ?>" class="btn btn-green btn-sm">Edit</a>
                                                    <form method="POST" action="admin.php?page=dashboard" onsubmit="return confirm('Hapus pesanan ini?');" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo e(getCsrfToken()); ?>">
                                                        <input type="hidden" name="form_action" value="delete_pesanan">
                                                        <input type="hidden" name="from_page" value="dashboard">
                                                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                                        <button type="submit" class="btn btn-red btn-sm">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        <?php else: ?>
                                            <!-- JIKA PASSWORD SALAH: TAMPIL HURUF & ANGKA ACAK DARI DATABASE -->
                                            <td><span class="db-cipher-text"><?php echo e($d['nama_pelanggan']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['jenis_layanan']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['berat']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['total_harga']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['status']); ?></span></td>
                                            <td>
                                                <a href="admin.php?page=detail&id=<?php echo (int)$r['id']; ?>" class="btn btn-outline btn-sm">Detail</a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="7" style="text-align:center;">Belum ada data pesanan di database.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($page === 'pesanan'): ?>
                <!-- DATA SELURUH PESANAN -->
                <div class="card">
                    <div class="card-header">
                        <h2>Data Seluruh Pesanan Laundry (Dari Database MySQL)</h2>
                        <?php if ($keyIsValid): ?>
                            <a href="admin.php?page=tambah" class="btn btn-green">+ Tambah Pesanan</a>
                        <?php endif; ?>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Pelanggan</th>
                                    <th>No. Telepon</th>
                                    <th>Alamat</th>
                                    <th>Layanan</th>
                                    <th>Berat</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($daftarPesanan)): $no = 1; foreach ($daftarPesanan as $r): $d = $r['data']; ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <?php if ($r['decryption_ok']): ?>
                                            <td><strong><?php echo e($d['nama_pelanggan']); ?></strong></td>
                                            <td><?php echo e($d['nomor_telepon']); ?></td>
                                            <td><?php echo e($d['alamat']); ?></td>
                                            <td><?php echo e($d['jenis_layanan']); ?></td>
                                            <td><?php echo e($d['berat']); ?> kg</td>
                                            <td><strong><?php echo formatRupiah($d['total_harga']); ?></strong></td>
                                            <td><span class="badge-status st-<?php echo e($d['status']); ?>"><?php echo e($d['status']); ?></span></td>
                                            <td>
                                                <div class="actions">
                                                    <a href="admin.php?page=detail&id=<?php echo (int)$r['id']; ?>" class="btn btn-outline btn-sm">Detail</a>
                                                    <a href="admin.php?page=edit&id=<?php echo (int)$r['id']; ?>" class="btn btn-green btn-sm">Edit</a>
                                                    <form method="POST" action="admin.php?page=pesanan" onsubmit="return confirm('Hapus pesanan ini?');" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo e(getCsrfToken()); ?>">
                                                        <input type="hidden" name="form_action" value="delete_pesanan">
                                                        <input type="hidden" name="from_page" value="pesanan">
                                                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                                        <button type="submit" class="btn btn-red btn-sm">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        <?php else: ?>
                                            <td><span class="db-cipher-text"><?php echo e($d['nama_pelanggan']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['nomor_telepon']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['alamat']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['jenis_layanan']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['berat']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['total_harga']); ?></span></td>
                                            <td><span class="db-cipher-text"><?php echo e($d['status']); ?></span></td>
                                            <td>
                                                <a href="admin.php?page=detail&id=<?php echo (int)$r['id']; ?>" class="btn btn-outline btn-sm">Detail</a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="9" style="text-align:center;">Belum ada pesanan tersimpan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($page === 'tambah'): ?>
                <!-- TAMBAH PESANAN -->
                <div class="card">
                    <div class="card-header">
                        <h2>Tambah Pesanan Baru (Enkripsi Otomatis AES-256-GCM)</h2>
                        <a href="admin.php?page=pesanan" class="btn btn-outline">&larr; Kembali</a>
                    </div>
                    <?php if (!$keyIsValid): ?>
                        <div class="alert alert-danger">
                            Anda sedang login menggunakan Password (Secret Key) yang salah. Silakan Logout dan login dengan password yang benar untuk menambahkan pesanan baru.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="admin.php?page=tambah">
                            <input type="hidden" name="csrf_token" value="<?php echo e(getCsrfToken()); ?>">
                            <input type="hidden" name="form_action" value="create_pesanan">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Nama Pelanggan</label>
                                    <input type="text" name="nama_pelanggan" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Nomor Telepon / WhatsApp</label>
                                    <input type="text" name="nomor_telepon" class="form-control" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Alamat Lengkap</label>
                                <textarea name="alamat" rows="2" class="form-control" required></textarea>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Jenis Layanan</label>
                                    <select id="jenis_layanan" name="jenis_layanan" class="form-control" onchange="syncHarga()" required>
                                        <option value="" data-harga="0">-- Pilih Layanan --</option>
                                        <?php foreach ($daftarLayanan as $l): if ($l['decryption_ok']): ?>
                                            <option value="<?php echo e($l['nama_layanan']); ?>" data-harga="<?php echo (int)$l['harga']; ?>">
                                                <?php echo e($l['nama_layanan']); ?> (<?php echo formatRupiah($l['harga']); ?>/kg)
                                            </option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Berat Cucian (Kg)</label>
                                    <input type="number" step="0.1" min="0.1" id="berat" name="berat" class="form-control" value="1" oninput="hitungTotal()" required>
                                </div>
                                <div class="form-group">
                                    <label>Harga per Kg (Rp)</label>
                                    <input type="number" id="harga_per_kg" name="harga_per_kg" class="form-control" value="0" oninput="hitungTotal()" required>
                                </div>
                                <div class="form-group">
                                    <label>Total Pembayaran</label>
                                    <input type="text" id="total_view" class="form-control" value="Rp0" readonly>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Status Pesanan</label>
                                    <select name="status" class="form-control" required>
                                        <option value="Menunggu">Menunggu</option>
                                        <option value="Diproses">Diproses</option>
                                        <option value="Selesai">Selesai</option>
                                        <option value="Diambil">Diambil</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Catatan</label>
                                    <input type="text" name="catatan" class="form-control" placeholder="Opsional">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-green">Enkripsi & Simpan Pesanan</button>
                        </form>
                    <?php endif; ?>
                </div>

            <?php elseif ($page === 'edit'):
                $idEdit = (int)($_GET['id'] ?? 0);
                $item   = getPesananByIdDecrypted($pdo, $idEdit, $encryptionKey);
            ?>
                <!-- EDIT PESANAN -->
                <div class="card">
                    <div class="card-header">
                        <h2>Edit Pesanan #<?php echo $idEdit; ?></h2>
                        <a href="admin.php?page=pesanan" class="btn btn-outline">&larr; Kembali</a>
                    </div>
                    <?php if (!$item || !$item['decryption_ok']): ?>
                        <div class="alert alert-danger">Data pesanan masih terenkripsi karena Password salah. Silakan login dengan password yang benar untuk mengedit.</div>
                    <?php else: $d = $item['data']; ?>
                        <form method="POST" action="admin.php?page=edit&id=<?php echo $idEdit; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo e(getCsrfToken()); ?>">
                            <input type="hidden" name="form_action" value="update_pesanan">
                            <input type="hidden" name="id" value="<?php echo $idEdit; ?>">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Nama Pelanggan</label>
                                    <input type="text" name="nama_pelanggan" class="form-control" value="<?php echo e($d['nama_pelanggan']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Nomor Telepon</label>
                                    <input type="text" name="nomor_telepon" class="form-control" value="<?php echo e($d['nomor_telepon']); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Alamat Lengkap</label>
                                <textarea name="alamat" rows="2" class="form-control" required><?php echo e($d['alamat']); ?></textarea>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Jenis Layanan</label>
                                    <select id="jenis_layanan" name="jenis_layanan" class="form-control" onchange="syncHarga()" required>
                                        <?php foreach ($daftarLayanan as $l): if ($l['decryption_ok']): ?>
                                            <option value="<?php echo e($l['nama_layanan']); ?>" data-harga="<?php echo (int)$l['harga']; ?>" <?php echo ($d['jenis_layanan'] === $l['nama_layanan']) ? 'selected' : ''; ?>>
                                                <?php echo e($l['nama_layanan']); ?> (<?php echo formatRupiah($l['harga']); ?>/kg)
                                            </option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Berat Cucian (Kg)</label>
                                    <input type="number" step="0.1" min="0.1" id="berat" name="berat" class="form-control" value="<?php echo e($d['berat']); ?>" oninput="hitungTotal()" required>
                                </div>
                                <div class="form-group">
                                    <label>Harga per Kg (Rp)</label>
                                    <input type="number" id="harga_per_kg" name="harga_per_kg" class="form-control" value="<?php echo (int)$d['harga_per_kg']; ?>" oninput="hitungTotal()" required>
                                </div>
                                <div class="form-group">
                                    <label>Total Pembayaran</label>
                                    <input type="text" id="total_view" class="form-control" value="<?php echo e(formatRupiah($d['total_harga'])); ?>" readonly>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Status Pesanan</label>
                                    <select name="status" class="form-control" required>
                                        <?php foreach (['Menunggu', 'Diproses', 'Selesai', 'Diambil'] as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo ($d['status'] === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Catatan</label>
                                    <input type="text" name="catatan" class="form-control" value="<?php echo e($d['catatan'] ?? ''); ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-green">Perbarui & Enkripsi Ulang (Nonce Baru)</button>
                        </form>
                    <?php endif; ?>
                </div>

            <?php elseif ($page === 'detail'):
                $idDetail = (int)($_GET['id'] ?? 0);
                $item     = getPesananByIdDecrypted($pdo, $idDetail, $encryptionKey);
            ?>
                <!-- DETAIL PESANAN -->
                <div class="card">
                    <div class="card-header">
                        <h2>Detail Pesanan #<?php echo $idDetail; ?></h2>
                        <a href="admin.php?page=pesanan" class="btn btn-outline">&larr; Kembali</a>
                    </div>
                    <?php if (!$item): ?>
                        <div class="alert alert-danger">Record pesanan tidak ditemukan.</div>
                    <?php else: $d = $item['data']; ?>
                        <table>
                            <tbody>
                                <tr>
                                    <th style="width:210px;">Nama Pelanggan</th>
                                    <td><?php echo $item['decryption_ok'] ? '<strong>' . e($d['nama_pelanggan']) . '</strong>' : '<span class="db-cipher-text">' . e($d['nama_pelanggan']) . '</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Nomor Telepon</th>
                                    <td><?php echo $item['decryption_ok'] ? e($d['nomor_telepon']) : '<span class="db-cipher-text">' . e($d['nomor_telepon']) . '</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Alamat</th>
                                    <td><?php echo $item['decryption_ok'] ? e($d['alamat']) : '<span class="db-cipher-text">' . e($d['alamat']) . '</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Layanan</th>
                                    <td><?php echo $item['decryption_ok'] ? e($d['jenis_layanan']) : '<span class="db-cipher-text">' . e($d['jenis_layanan']) . '</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Berat</th>
                                    <td><?php echo $item['decryption_ok'] ? e($d['berat']) . ' kg' : '<span class="db-cipher-text">' . e($d['berat']) . '</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Harga / Kg</th>
                                    <td><?php echo $item['decryption_ok'] ? formatRupiah($d['harga_per_kg']) : '<span class="db-cipher-text">' . e($d['harga_per_kg']) . '</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Total Harga</th>
                                    <td><?php echo $item['decryption_ok'] ? '<strong style="color:var(--pt-red);">' . formatRupiah($d['total_harga']) . '</strong>' : '<span class="db-cipher-text">' . e($d['total_harga']) . '</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td><?php echo $item['decryption_ok'] ? '<span class="badge-status st-' . e($d['status']) . '">' . e($d['status']) . '</span>' : '<span class="db-cipher-text">' . e($d['status']) . '</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Catatan</th>
                                    <td><?php echo $item['decryption_ok'] ? e($d['catatan'] ?: '-') : '<span class="db-cipher-text">' . e($d['catatan']) . '</span>'; ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="cipher-inspector">
                            <div><strong>[RECORD MENTAH DI DATABASE MYSQL — TABEL PESANAN]</strong></div>
                            <div>Nonce (12 Byte Hex)    : <?php echo e($item['nonce']); ?></div>
                            <div>Auth Tag (16 Byte Hex) : <?php echo e($item['auth_tag']); ?></div>
                            <div>Ciphertext (Base64)    : <?php echo e($item['encrypted_data']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($page === 'layanan'):
                $editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
                $editItem = null;
                if ($keyIsValid) {
                    foreach ($daftarLayanan as $srv) {
                        if ($srv['id'] === $editId && $srv['decryption_ok']) {
                            $editItem = $srv;
                            break;
                        }
                    }
                }
            ?>
                <!-- PENGELOLAAN DATA LAYANAN -->
                <div class="form-grid" style="align-items:start;">
                    <div class="card">
                        <div class="card-header">
                            <h2><?php echo $editItem ? 'Edit Layanan #' . $editItem['id'] : 'Tambah Layanan'; ?></h2>
                        </div>
                        <?php if (!$keyIsValid): ?>
                            <div class="alert alert-danger">Form dikunci karena Anda login dengan Password salah.</div>
                        <?php else: ?>
                            <form method="POST" action="admin.php?page=layanan">
                                <input type="hidden" name="csrf_token" value="<?php echo e(getCsrfToken()); ?>">
                                <?php if ($editItem): ?>
                                    <input type="hidden" name="form_action" value="update_layanan">
                                    <input type="hidden" name="id" value="<?php echo (int)$editItem['id']; ?>">
                                <?php else: ?>
                                    <input type="hidden" name="form_action" value="create_layanan">
                                <?php endif; ?>

                                <div class="form-group">
                                    <label>Nama Layanan</label>
                                    <input type="text" name="nama_layanan" class="form-control" value="<?php echo e($editItem['nama_layanan'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Harga per Kilogram (Rp)</label>
                                    <input type="number" min="500" step="500" name="harga" class="form-control" value="<?php echo e($editItem['harga'] ?? ''); ?>" required>
                                </div>

                                <button type="submit" class="btn btn-green">
                                    <?php echo $editItem ? 'Simpan Perubahan' : 'Enkripsi & Tambah'; ?>
                                </button>
                                <?php if ($editItem): ?>
                                    <a href="admin.php?page=layanan" class="btn btn-outline">Batal</a>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2>Daftar Layanan & Harga (Dari Database MySQL)</h2>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Layanan</th>
                                        <th>Harga</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($daftarLayanan)): $no = 1; foreach ($daftarLayanan as $l): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <?php if ($l['decryption_ok']): ?>
                                                <td><strong><?php echo e($l['nama_layanan']); ?></strong></td>
                                                <td style="color:var(--pt-red); font-weight:800;"><?php echo e($l['harga_tampil']); ?></td>
                                                <td>
                                                    <div class="actions">
                                                        <a href="admin.php?page=layanan&edit_id=<?php echo (int)$l['id']; ?>" class="btn btn-green btn-sm">Edit</a>
                                                        <form method="POST" action="admin.php?page=layanan" onsubmit="return confirm('Hapus layanan ini?');" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo e(getCsrfToken()); ?>">
                                                            <input type="hidden" name="form_action" value="delete_layanan">
                                                            <input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>">
                                                            <button type="submit" class="btn btn-red btn-sm">Hapus</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            <?php else: ?>
                                                <td><span class="db-cipher-text"><?php echo e($l['nama_layanan']); ?></span></td>
                                                <td><span class="db-cipher-text"><?php echo e($l['harga_tampil']); ?></span></td>
                                                <td><span class="db-cipher-text"><?php echo e(substr($l['nonce'], 0, 8)); ?></span></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function syncHarga() {
            var sel = document.getElementById('jenis_layanan');
            if (!sel) return;
            var opt = sel.options[sel.selectedIndex];
            var hrg = opt ? parseInt(opt.getAttribute('data-harga') || '0', 10) : 0;
            var inp = document.getElementById('harga_per_kg');
            if (inp && hrg > 0) inp.value = hrg;
            hitungTotal();
        }

        function hitungTotal() {
            var b = parseFloat((document.getElementById('berat') || {}).value) || 0;
            var h = parseInt((document.getElementById('harga_per_kg') || {}).value, 10) || 0;
            var out = document.getElementById('total_view');
            if (out) out.value = 'Rp' + Math.round(b * h).toLocaleString('id-ID');
        }
    </script>
</body>
</html>