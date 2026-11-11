<?php
/**
 * AMANG LAUNDRY - PORTUGAL FOOTBALL EDITION (CR7 THEME)
 * File 2/3: index.php
 * Landing Page Publik sekaligus Halaman Login Admin
 */
require_once __DIR__ . '/config.php';

$loginError = '';
$showLogin  = (isset($_GET['view']) && $_GET['view'] === 'login') || isset($_GET['denied']) || isset($_GET['logged_out']);

if (isset($_GET['denied'])) {
    $loginError = 'ACCESS DENIED — Silakan login terlebih dahulu.';
}

// Proses Login ketika tombol Login ditekan pada index.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_login') {
    $showLogin = true;

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $loginError = 'ACCESS DENIED — Token keamanan sesi tidak valid.';
    } else {
        $username = cleanInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $loginError = 'ACCESS DENIED — Username dan Secret Key wajib diisi!';
        } else {
            if (authenticateAdmin($pdo, $username, $password)) {
                if (isKeyValid()) {
                    $_SESSION['flash_success'] = 'Password Benar! Kunci AES-256-GCM cocok. Seluruh data dari database berhasil didekripsi menjadi data asli.';
                } else {
                    $_SESSION['flash_error'] = 'Password Salah! Kunci AES-256-GCM tidak cocok. Data yang diambil dari database ditampilkan dalam bentuk huruf & angka acak (Ciphertext).';
                }
                header('Location: admin.php');
                exit;
            } else {
                $loginError = 'ACCESS DENIED — Username "' . $username . '" tidak terdaftar!';
            }
        }
    }
}

$daftarLayanan = getAllLayanan($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMANG LAUNDRY #7 — Portugal Football Edition</title>
    <style>
        :root {
            --pt-green: #046A38;
            --pt-green-dark: #024724;
            --pt-red: #DA291C;
            --pt-red-dark: #9e1910;
            --pt-gold: #D4AF37;
            --pt-gold-light: #f3db89;
            --pt-black: #111315;
            --pt-cream: #F5E9D0;
            --pt-white: #FFFFFF;
            --pt-gray: #5c6460;
            --radius: 12px;
            --shadow: 0 8px 24px rgba(17, 19, 21, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--pt-cream);
            color: var(--pt-black);
            line-height: 1.6;
        }

        .portugal-ribbon {
            height: 6px;
            background: linear-gradient(90deg, var(--pt-green) 0%, var(--pt-green) 40%, var(--pt-gold) 40%, var(--pt-gold) 42%, var(--pt-red) 42%, var(--pt-red) 100%);
        }

        .navbar {
            background-color: var(--pt-black);
            border-bottom: 2px solid var(--pt-gold);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1160px;
            margin: 0 auto;
            padding: 0.9rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            color: var(--pt-white);
            font-weight: 900;
            font-size: 1.3rem;
            letter-spacing: 0.5px;
        }

        .cr7-crest {
            background: linear-gradient(135deg, var(--pt-red), var(--pt-green));
            border: 2px solid var(--pt-gold);
            color: var(--pt-gold);
            font-weight: 900;
            padding: 0.15rem 0.55rem;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 1.4rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--pt-cream);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.94rem;
        }

        .nav-links a:hover {
            color: var(--pt-gold);
        }

        .btn-nav-login {
            background: linear-gradient(135deg, var(--pt-red) 0%, var(--pt-red-dark) 100%);
            color: var(--pt-white) !important;
            padding: 0.5rem 1.1rem;
            border-radius: 6px;
            border: 1px solid var(--pt-gold);
            font-weight: 700 !important;
        }

        .btn-nav-login:hover {
            background: var(--pt-green);
        }

        .hero {
            background: linear-gradient(125deg, var(--pt-green-dark) 0%, var(--pt-green) 42%, var(--pt-black) 100%);
            color: var(--pt-white);
            padding: 4.5rem 1.5rem;
            border-bottom: 5px solid var(--pt-red);
        }

        .hero-container {
            max-width: 1160px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 2.5rem;
            align-items: center;
        }

        .edition-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: rgba(212, 175, 55, 0.18);
            border: 1px solid var(--pt-gold);
            color: var(--pt-gold-light);
            padding: 0.3rem 0.85rem;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .hero h1 {
            font-size: 2.65rem;
            line-height: 1.15;
            margin-bottom: 1rem;
            font-weight: 900;
        }

        .hero h1 span {
            color: var(--pt-gold);
        }

        .hero p {
            color: var(--pt-cream);
            font-size: 1.05rem;
            margin-bottom: 1.8rem;
            max-width: 560px;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 0.78rem 1.5rem;
            border-radius: 7px;
            font-weight: 700;
            text-decoration: none;
            font-size: 0.95rem;
            cursor: pointer;
            border: none;
        }

        .btn-gold {
            background-color: var(--pt-gold);
            color: var(--pt-black);
        }

        .btn-gold:hover {
            background-color: var(--pt-gold-light);
        }

        .btn-red {
            background-color: var(--pt-red);
            color: var(--pt-white);
            border: 1px solid rgba(255,255,255,0.25);
        }

        .btn-red:hover {
            background-color: var(--pt-red-dark);
        }

        .cr7-card {
            background: linear-gradient(155deg, var(--pt-red) 0%, var(--pt-red-dark) 65%, var(--pt-black) 100%);
            border: 3px solid var(--pt-gold);
            border-radius: 16px;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .cr7-number {
            font-size: 5.2rem;
            font-weight: 900;
            line-height: 1;
            color: var(--pt-gold);
            text-shadow: 0 4px 12px rgba(0,0,0,0.45);
        }

        .cr7-subtitle {
            font-weight: 800;
            letter-spacing: 2px;
            color: var(--pt-white);
            font-size: 1rem;
            margin-top: 0.25rem;
            margin-bottom: 1rem;
        }

        .cr7-badge-line {
            background-color: rgba(17, 19, 21, 0.55);
            border: 1px solid var(--pt-gold);
            color: var(--pt-cream);
            padding: 0.55rem 0.9rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .section {
            padding: 4rem 1.5rem;
        }

        .section-white {
            background-color: var(--pt-white);
        }

        .container {
            max-width: 1160px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 2.6rem;
        }

        .section-header h2 {
            font-size: 1.9rem;
            color: var(--pt-green);
            font-weight: 900;
        }

        .gold-divider {
            width: 75px;
            height: 4px;
            background: linear-gradient(90deg, var(--pt-green), var(--pt-gold), var(--pt-red));
            margin: 0.5rem auto 0.9rem auto;
            border-radius: 2px;
        }

        .section-header p {
            color: var(--pt-gray);
            max-width: 600px;
            margin: 0 auto;
        }

        .grid-4 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.4rem;
        }

        .service-card {
            background-color: var(--pt-white);
            border-radius: var(--radius);
            padding: 1.6rem;
            box-shadow: var(--shadow);
            border-top: 5px solid var(--pt-green);
            position: relative;
        }

        .service-card:nth-child(even) {
            border-top-color: var(--pt-red);
        }

        .service-num {
            position: absolute;
            top: 10px;
            right: 16px;
            font-size: 2.2rem;
            font-weight: 900;
            color: rgba(212, 175, 55, 0.22);
        }

        .service-card h3 {
            color: var(--pt-black);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .service-card p {
            color: var(--pt-gray);
            font-size: 0.92rem;
            margin-bottom: 1rem;
        }

        .service-tag {
            display: inline-block;
            font-size: 0.76rem;
            font-weight: 800;
            color: var(--pt-green);
            background-color: var(--pt-cream);
            padding: 0.25rem 0.65rem;
            border-radius: 5px;
            border-left: 3px solid var(--pt-gold);
        }

        .price-card {
            max-width: 840px;
            margin: 0 auto;
            background-color: var(--pt-white);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 2px solid var(--pt-gold);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background-color: var(--pt-green);
            color: var(--pt-white);
            padding: 1rem 1.25rem;
            text-align: left;
            font-size: 0.95rem;
            border-bottom: 3px solid var(--pt-gold);
        }

        table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #ececec;
            font-size: 0.95rem;
        }

        table tr:nth-child(even) {
            background-color: #faf7f0;
        }

        .price-badge {
            color: var(--pt-red);
            font-weight: 800;
            font-size: 1.05rem;
        }

        .about-panel {
            background: linear-gradient(135deg, var(--pt-black) 0%, #1c221e 100%);
            color: var(--pt-white);
            border-radius: var(--radius);
            padding: 2.5rem;
            border-left: 6px solid var(--pt-gold);
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2rem;
            align-items: center;
        }

        .about-panel h3 {
            color: var(--pt-gold);
            font-size: 1.4rem;
            margin-bottom: 0.6rem;
        }

        .about-panel p {
            color: var(--pt-cream);
            margin-bottom: 0.8rem;
        }

        .about-emblem {
            background-color: rgba(255,255,255,0.05);
            border: 2px solid var(--pt-gold);
            border-radius: 12px;
            padding: 1.2rem 1.6rem;
            text-align: center;
            min-width: 180px;
        }

        .about-emblem strong {
            display: block;
            font-size: 2.4rem;
            color: var(--pt-gold);
            line-height: 1;
        }

        .about-emblem span {
            font-size: 0.8rem;
            color: var(--pt-cream);
        }

        .login-section {
            background-color: var(--pt-black);
            color: var(--pt-white);
            padding: 4rem 1.5rem;
            border-top: 4px solid var(--pt-gold);
        }

        .login-box {
            max-width: 460px;
            margin: 0 auto;
            background-color: var(--pt-white);
            color: var(--pt-black);
            border-radius: var(--radius);
            padding: 2.2rem 2rem;
            border-top: 6px solid var(--pt-red);
            box-shadow: 0 12px 32px rgba(0,0,0,0.35);
        }

        .login-box-header {
            text-align: center;
            margin-bottom: 1.4rem;
        }

        .login-box-header .crest {
            display: inline-block;
            background-color: var(--pt-green);
            color: var(--pt-gold);
            border: 2px solid var(--pt-gold);
            font-weight: 900;
            padding: 0.35rem 0.9rem;
            border-radius: 6px;
            margin-bottom: 0.7rem;
            font-size: 0.9rem;
        }

        .login-box-header h2 {
            font-size: 1.4rem;
            color: var(--pt-black);
        }

        .login-box-header p {
            font-size: 0.85rem;
            color: var(--pt-gray);
            margin-top: 0.25rem;
        }

        .alert-denied {
            background-color: #fde8e7;
            border-left: 5px solid var(--pt-red);
            color: var(--pt-red-dark);
            padding: 0.8rem 1rem;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.86rem;
            margin-bottom: 1.1rem;
        }

        .alert-info {
            background-color: #e6f4ed;
            border-left: 5px solid var(--pt-green);
            color: var(--pt-green-dark);
            padding: 0.8rem 1rem;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.86rem;
            margin-bottom: 1.1rem;
        }

        .form-group {
            margin-bottom: 1.1rem;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.88rem;
            margin-bottom: 0.35rem;
        }

        .form-control {
            width: 100%;
            padding: 0.72rem 0.9rem;
            border: 1px solid #c9c9c9;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--pt-green);
            box-shadow: 0 0 0 2px rgba(4, 106, 56, 0.15);
        }

        .pass-group {
            display: flex;
            gap: 0.4rem;
        }

        .btn-toggle {
            background-color: var(--pt-cream);
            border: 1px solid #c9c9c9;
            padding: 0 0.8rem;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .btn-submit-login {
            width: 100%;
            background: linear-gradient(135deg, var(--pt-green) 0%, var(--pt-green-dark) 100%);
            color: var(--pt-white);
            border: 1px solid var(--pt-gold);
            padding: 0.85rem;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.96rem;
            cursor: pointer;
            margin-top: 0.25rem;
        }

        .btn-submit-login:hover {
            background: var(--pt-red);
        }

        .demo-hint {
            margin-top: 1.1rem;
            padding: 0.7rem;
            background-color: #faf7f0;
            border: 1px dashed var(--pt-gold);
            border-radius: 6px;
            font-size: 0.79rem;
            color: var(--pt-gray);
            line-height: 1.45;
        }

        .footer {
            background-color: var(--pt-green-dark);
            color: var(--pt-white);
            padding: 2.2rem 1.5rem;
            text-align: center;
            border-top: 3px solid var(--pt-gold);
        }

        .footer strong {
            color: var(--pt-gold);
        }

        .footer p {
            font-size: 0.86rem;
            color: var(--pt-cream);
            margin-top: 0.35rem;
        }

        @media (max-width: 768px) {
            .hero-container, .about-panel {
                grid-template-columns: 1fr;
            }
            .hero h1 {
                font-size: 2rem;
            }
            .nav-links {
                flex-wrap: wrap;
                gap: 0.8rem;
            }
        }
    </style>
</head>
<body>

    <div class="portugal-ribbon"></div>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="brand">
                <span class="cr7-crest">CR7</span>
                <span>AMANG LAUNDRY</span>
            </a>
            <ul class="nav-links">
                <li><a href="#beranda">Beranda</a></li>
                <li><a href="#layanan">Layanan</a></li>
                <li><a href="#harga">Daftar Harga</a></li>
                <li><a href="#tentang">Tentang Kami</a></li>
                <?php if (isAdminLoggedIn()): ?>
                    <li><a href="admin.php" class="btn-nav-login">Dashboard Admin (#7)</a></li>
                <?php else: ?>
                    <li><a href="#login-admin" class="btn-nav-login">Login Admin</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero" id="beranda">
        <div class="hero-container">
            <div>
                <div class="edition-pill">&#9917; PORTUGAL FOOTBALL EDITION &bull; CR7 SERIES</div>
                <h1>Solusi Laundry <span>Juara</span>, Bersih, Wangi & Terpercaya!</h1>
                <p>
                    Standar perawatan pakaian kelas juara dengan presisi tinggi. Seluruh data transaksi dan privasi pelanggan dilindungi kriptografi simetris <strong>AES-256-GCM</strong>.
                </p>
                <div class="hero-actions">
                    <a href="#harga" class="btn btn-gold">Lihat Daftar Harga</a>
                    <a href="#login-admin" class="btn btn-red">Akses Login Admin</a>
                </div>
            </div>

            <div class="cr7-card">
                <svg width="56" height="56" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-bottom:0.3rem;">
                    <circle cx="32" cy="32" r="28" fill="#111315" stroke="#D4AF37" stroke-width="3"/>
                    <polygon points="32,16 43,24 39,37 25,37 21,24" fill="#D4AF37"/>
                </svg>
                <div class="cr7-number">07</div>
                <div class="cr7-subtitle">SELEÇÃO QUINAS EDITION</div>
                <div class="cr7-badge-line">
                    &#128274; AES-256-GCM Authenticated Encryption
                </div>
            </div>
        </div>
    </section>

    <!-- INFORMASI LAYANAN -->
    <section class="section" id="layanan">
        <div class="container">
            <div class="section-header">
                <h2>Layanan Unggulan Kami</h2>
                <div class="gold-divider"></div>
                <p>Empat formasi layanan terbaik untuk menjaga pakaian Anda selalu bersih dan siap tampil prima.</p>
            </div>

            <div class="grid-4">
                <div class="service-card">
                    <span class="service-num">01</span>
                    <h3>Cuci Reguler</h3>
                    <p>Pencucian pakaian harian dengan proses standar higienis dan waktu pengerjaan normal.</p>
                    <span class="service-tag">Standar Terbaik</span>
                </div>
                <div class="service-card">
                    <span class="service-num">02</span>
                    <h3>Cuci Express</h3>
                    <p>Layanan pencucian super cepat sekelas serangan balik CR7 untuk pakaian yang segera dibutuhkan.</p>
                    <span class="service-tag">Prioritas Kilat</span>
                </div>
                <div class="service-card">
                    <span class="service-num">03</span>
                    <h3>Cuci Setrika</h3>
                    <p>Paket lengkap pencucian bersih wangi sekaligus penyetrikaan uap rapi siap masuk lemari.</p>
                    <span class="service-tag">Paket Favorit #7</span>
                </div>
                <div class="service-card">
                    <span class="service-num">04</span>
                    <h3>Setrika Saja</h3>
                    <p>Layanan penyetrikaan presisi tinggi untuk pakaian yang sudah dicuci agar tampil licin maksimal.</p>
                    <span class="service-tag">Rapi Maksimal</span>
                </div>
            </div>
        </div>
    </section>

    <!-- DAFTAR HARGA -->
    <section class="section section-white" id="harga">
        <div class="container">
            <div class="section-header">
                <h2>Daftar Harga Resmi</h2>
                <div class="gold-divider"></div>
                <p>Data harga di bawah ini diambil langsung dari database MySQL yang terproteksi AES-256-GCM.</p>
            </div>

            <div class="price-card">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 70px;">No</th>
                            <th>Jenis Layanan Laundry</th>
                            <th>Tarif per Kilogram</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($daftarLayanan)): ?>
                            <?php $no = 1; foreach ($daftarLayanan as $srv): ?>
                                <tr>
                                    <td><strong>#<?php echo $no++; ?></strong></td>
                                    <td><strong><?php echo e($srv['nama_layanan']); ?></strong></td>
                                    <td class="price-badge"><?php echo formatRupiah($srv['harga']); ?>/kg</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align:center;">Belum ada data layanan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- TENTANG KAMI -->
    <section class="section" id="tentang">
        <div class="container">
            <div class="about-panel">
                <div>
                    <h3>Tentang Amang Laundry — Portugal CR7 Edition</h3>
                    <p>
                        <strong>Amang Laundry</strong> hadir memberikan layanan pencucian pakaian yang bersih, wangi, dan terpercaya. Terinspirasi dari semangat juang tim nasional Portugal dan dedikasi Cristiano Ronaldo (CR7), kami mengutamakan kecepatan, ketepatan, dan kualitas tanpa kompromi.
                    </p>
                    <p style="font-size:0.9rem; margin-bottom:0;">
                        <strong>Keamanan Data Tingkat Tinggi:</strong> Proyek Sistem Informasi ini menerapkan kriptografi simetris <strong>AES-256-GCM</strong> dengan derivasi kunci KDF dan verifikasi <em>Authentication Tag</em> 128-bit.
                    </p>
                </div>
                <div class="about-emblem">
                    <strong>CR7</strong>
                    <span>PORTUGAL EDITION<br>AES-256-GCM</span>
                </div>
            </div>
        </div>
    </section>

    <!-- BAGIAN LOGIN ADMIN -->
    <section class="login-section" id="login-admin">
        <div class="login-box">
            <div class="login-box-header">
                <span class="crest">PORTUGAL #7 SECURITY GATE</span>
                <h2>Administrator Login</h2>
                <p>Masukkan Username dan Secret Key untuk menurunkan kunci dekripsi AES-256-GCM.</p>
            </div>

            <?php if ($loginError !== ''): ?>
                <div class="alert-denied"><?php echo e($loginError); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['logged_out'])): ?>
                <div class="alert-info">Sesi berakhir. Kunci enkripsi AES telah dihapus dari memori server.</div>
            <?php endif; ?>

            <form method="POST" action="index.php#login-admin" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo e(getCsrfToken()); ?>">
                <input type="hidden" name="action" value="admin_login">

                <div class="form-group">
                    <label for="username">Username Admin</label>
                    <input type="text" id="username" name="username" class="form-control" value="Kemal" placeholder="Masukkan username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password / Secret Key Derivasi AES</label>
                    <div class="pass-group">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan secret key" required>
                        <button type="button" class="btn-toggle" id="btnToggle" onclick="togglePass()">Lihat</button>
                    </div>
                </div>

                <button type="submit" class="btn-submit-login">Buka Dashboard & Jalankan KDF</button>
            </form>

            <div class="demo-hint">
                <strong>Simulasi Pembuktian Database AES-256-GCM:</strong><br>
                &bull; Jika Password <strong>Benar</strong>: Data dari database didekripsi & tampil normal.<br>
                &bull; Jika Password <strong>Salah</strong>: Tetap bisa login, tetapi data yang diambil dari database muncul sebagai <strong>angka & huruf acak (Ciphertext)</strong>.
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <strong>AMANG LAUNDRY #7 — PORTUGAL FOOTBALL EDITION</strong>
        <p>"Bersih, Wangi, dan Terpercaya." &bull; Kriptografi Simetris AES-256-GCM &copy; <?php echo date('Y'); ?></p>
    </footer>

    <script>
        function togglePass() {
            var input = document.getElementById('password');
            var btn = document.getElementById('btnToggle');
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = 'Tutup';
            } else {
                input.type = 'password';
                btn.textContent = 'Lihat';
            }
        }
        <?php if ($showLogin): ?>
        window.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('login-admin');
            if (el) el.scrollIntoView({ behavior: 'smooth' });
        });
        <?php endif; ?>
    </script>
</body>
</html>