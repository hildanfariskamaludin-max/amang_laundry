<?php
/**
 * AMANG LAUNDRY - PORTUGAL FOOTBALL EDITION (CR7 THEME)
 * File 1/3: config.php
 * - Kompatibel PHP 7.4 & PHP 8.x
 * - Login khusus Private Key (tanpa username)
 * - Private Key: kemaltamvan11
 * - Auto-repair data pesanan lama yang tidak cocok dengan Private Key baru
 * - Enkripsi seluruh tampilan satu website jika Private Key salah
 */

// 1. Konfigurasi Session Aman
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 2. Konfigurasi Database & Konstanta Kriptografi
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'amang_laundry');
define('DB_USER', 'root');
define('DB_PASS', '');

define('ADMIN_DEFAULT_USER', 'Kemal');
define('PRIVATE_KEY', 'kemaltamvan11');
define('ADMIN_DEFAULT_PASS', PRIVATE_KEY);
define('CIPHER_METHOD', 'aes-256-gcm');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Koneksi database gagal. Pastikan database "amang_laundry" aktif di Laragon/phpMyAdmin.');
}

// 3. Inisialisasi Tabel & Auto-Repair Data
initializeSystemDefaults($pdo);

function initializeSystemDefaults(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admin` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `pesanan` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `encrypted_data` TEXT NOT NULL,
            `nonce` CHAR(24) NOT NULL,
            `auth_tag` CHAR(32) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `layanan` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `encrypted_data` TEXT NOT NULL,
            `nonce` CHAR(24) NOT NULL,
            `auth_tag` CHAR(32) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Cek & sinkronkan Private Key di tabel admin
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => ADMIN_DEFAULT_USER]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $hash = password_hash(PRIVATE_KEY, PASSWORD_BCRYPT);
        $ins  = $pdo->prepare('INSERT INTO admin (username, password_hash) VALUES (:u, :p)');
        $ins->execute([
            ':u' => ADMIN_DEFAULT_USER,
            ':p' => $hash
        ]);
    } else {
        if (!password_verify(PRIVATE_KEY, $admin['password_hash'])) {
            $newHash = password_hash(PRIVATE_KEY, PASSWORD_BCRYPT);
            $upd = $pdo->prepare('UPDATE admin SET password_hash = :p WHERE id = :id');
            $upd->execute([':p' => $newHash, ':id' => $admin['id']]);
        }
    }

    // Cek apakah layanan yang bisa didekripsi kosong
    $validLayanan = getAllLayanan($pdo);
    if (empty($validLayanan)) {
        $pdo->exec('TRUNCATE TABLE `layanan`');
        $defaults = [
            ['nama_layanan' => 'Cuci Reguler', 'harga' => 7000],
            ['nama_layanan' => 'Cuci Express', 'harga' => 10000],
            ['nama_layanan' => 'Cuci Setrika', 'harga' => 8000],
            ['nama_layanan' => 'Setrika Saja', 'harga' => 5000],
        ];
        foreach ($defaults as $srv) {
            createLayanan($pdo, $srv['nama_layanan'], $srv['harga']);
        }
    }

    // Cek contoh pesanan awal & auto-repair jika semua data lama tidak cocok dengan Private Key baru
    $stmtAdmin = $pdo->prepare('SELECT password_hash FROM admin WHERE username = :u LIMIT 1');
    $stmtAdmin->execute([':u' => ADMIN_DEFAULT_USER]);
    $admRow = $stmtAdmin->fetch();

    if ($admRow) {
        $trueKey    = deriveEncryptionKey(PRIVATE_KEY, $admRow['password_hash']);
        $cekPesanan = getAllPesananDecrypted($pdo, $trueKey);
        $totalRow   = count($cekPesanan['items']);

        if ($totalRow === 0 || $cekPesanan['failed_count'] === $totalRow) {
            $pdo->exec('TRUNCATE TABLE `pesanan`');
            createPesanan($pdo, [
                'nama_pelanggan' => 'Cristiano Ronaldo',
                'nomor_telepon'  => '087777777777',
                'alamat'         => 'Av. da Liberdade No. 7, Lisboa',
                'jenis_layanan'  => 'Cuci Express',
                'berat'          => 3,
                'harga_per_kg'   => 10000,
                'total_harga'    => 30000,
                'status'         => 'Diproses',
                'catatan'        => 'Jersey Portugal #7 jangan disetrika terlalu panas'
            ], $trueKey);
        }
    }
}

// ============================================================================
// 4. DERIVASI KUNCI (KDF) & ENKRIPSI / DEKRIPSI AES-256-GCM
// ============================================================================

function deriveEncryptionKey($privateKey, $passwordHash)
{
    $salt = substr(hash('sha256', 'CR7_PORTUGAL_SALT::' . $passwordHash, true), 0, 16);
    return hash_pbkdf2('sha256', $privateKey, $salt, 100000, 32, true);
}

function getServiceEncryptionKey(PDO $pdo)
{
    return hash_pbkdf2(
        'sha256',
        'AMANG_PORTUGAL_7_CATALOG_SECRET',
        'AMANG_CR7_STATIC_SERVICE_SALT_2026',
        50000,
        32,
        true
    );
}

function encryptData($plaintext, $key)
{
    if (strlen($key) !== 32) {
        return false;
    }

    $nonce = random_bytes(12);
    $tag   = '';

    $ciphertext = openssl_encrypt(
        $plaintext,
        CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        '',
        16
    );

    if ($ciphertext === false || strlen($tag) !== 16) {
        return false;
    }

    return [
        'encrypted_data' => base64_encode($ciphertext),
        'nonce'          => bin2hex($nonce),
        'auth_tag'       => bin2hex($tag)
    ];
}

function decryptData(array $encryptedPackage, $key)
{
    if (strlen($key) !== 32) {
        return false;
    }

    $ciphertextB64 = isset($encryptedPackage['encrypted_data']) ? $encryptedPackage['encrypted_data'] : '';
    $nonceHex      = isset($encryptedPackage['nonce']) ? $encryptedPackage['nonce'] : '';
    $tagHex        = isset($encryptedPackage['auth_tag']) ? $encryptedPackage['auth_tag'] : '';

    if (!ctype_xdigit($nonceHex) || strlen($nonceHex) !== 24) {
        return false;
    }
    if (!ctype_xdigit($tagHex) || strlen($tagHex) !== 32) {
        return false;
    }

    $nonce      = hex2bin($nonceHex);
    $tag        = hex2bin($tagHex);
    $ciphertext = base64_decode($ciphertextB64, true);

    if ($nonce === false || $tag === false || $ciphertext === false) {
        return false;
    }

    $plaintext = openssl_decrypt(
        $ciphertext,
        CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );

    return ($plaintext !== false) ? $plaintext : false;
}

function extractDatabaseCipherSlices(array $row)
{
    $rawDbStream = $row['encrypted_data'] . $row['nonce'] . $row['auth_tag'];

    return [
        'nama_pelanggan' => substr($row['encrypted_data'], 0, 18),
        'nomor_telepon'  => substr($row['nonce'], 0, 14),
        'alamat'         => substr($row['encrypted_data'], 18, 28),
        'jenis_layanan'  => substr($row['encrypted_data'], 46, 16),
        'berat'          => substr($row['nonce'], 14, 8),
        'harga_per_kg'   => substr($row['auth_tag'], 0, 10),
        'total_harga'    => substr($row['auth_tag'], 10, 12),
        'status'         => substr($row['auth_tag'], 22, 10),
        'catatan'        => substr($rawDbStream, 62, 24)
    ];
}

// ============================================================================
// 5. AUTENTIKASI (KHUSUS PRIVATE KEY) & ENKRIPSI SELURUH WEBSITE
// ============================================================================

function authenticateAdmin(PDO $pdo, $privateKey)
{
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => ADMIN_DEFAULT_USER]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return false;
    }

    $isValidKey = password_verify($privateKey, $admin['password_hash']);

    session_regenerate_id(true);
    $derivedKey = deriveEncryptionKey($privateKey, $admin['password_hash']);

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']        = (int)$admin['id'];
    $_SESSION['admin_username']  = $admin['username'];
    $_SESSION['aes_key_hex']     = bin2hex($derivedKey);
    $_SESSION['is_key_valid']    = $isValidKey;

    return true;
}

function isAdminLoggedIn()
{
    return !empty($_SESSION['admin_logged_in'])
           && !empty($_SESSION['aes_key_hex'])
           && strlen($_SESSION['aes_key_hex']) === 64;
}

function isKeyValid()
{
    return !empty($_SESSION['is_key_valid']) && $_SESSION['is_key_valid'] === true;
}

function requireAdminAccess()
{
    if (!isAdminLoggedIn()) {
        header('Location: index.php?view=login&denied=1');
        exit;
    }
}

function getSessionEncryptionKey()
{
    if (!isAdminLoggedIn()) {
        return '';
    }
    $rawKey = hex2bin($_SESSION['aes_key_hex']);
    return ($rawKey !== false && strlen($rawKey) === 32) ? $rawKey : '';
}

function logoutAdmin()
{
    if (isset($_SESSION['aes_key_hex'])) {
        $_SESSION['aes_key_hex'] = str_repeat('0', 64);
    }
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Enkripsi seluruh teks tampilan satu website (index.php & admin.php)
 * secara otomatis ketika login dengan Private Key yang salah.
 */
function encryptEntireWebsite($html)
{
    if (!isAdminLoggedIn() || isKeyValid()) {
        return $html;
    }

    $wrongKey = getSessionEncryptionKey();
    if ($wrongKey === '') {
        $wrongKey = hash('sha256', 'INVALID_KEY_FALLBACK', true);
    }
    $iv = substr(hash('sha256', $wrongKey, true), 0, 16);

    // Pisahkan tag <script> dan <style> agar CSS/JS tidak rusak
    $parts = preg_split('/(<(?:script|style)\b[^>]*>.*?<\/(?:script|style)>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $html;
    }

    foreach ($parts as $index => $part) {
        if ($index % 2 === 0) {
            $parts[$index] = preg_replace_callback('/>([^<]+)</', function ($matches) use ($wrongKey, $iv) {
                $text = trim($matches[1]);
                if ($text === '') {
                    return $matches[0];
                }
                $cipher = openssl_encrypt($text, 'aes-256-ctr', $wrongKey, 0, $iv);
                if ($cipher === false) {
                    $cipher = base64_encode($text);
                }
                return '>' . htmlspecialchars($cipher, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<';
            }, $part);
        }
    }

    return implode('', $parts);
}

// Aktifkan Output Buffering untuk enkripsi satu website penuh saat Private Key salah
ob_start('encryptEntireWebsite');

function getCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    return !empty($_SESSION['csrf_token']) && $token !== '' && hash_equals($_SESSION['csrf_token'], $token);
}

function cleanInput($val)
{
    return trim(strip_tags((string)$val));
}

function e($val)
{
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatRupiah($nominal)
{
    return 'Rp' . number_format((float)$nominal, 0, ',', '.');
}

// ============================================================================
// 6. HELPER CRUD DATA PESANAN & LAYANAN (TERENKRIPSI AES-256-GCM)
// ============================================================================

function createPesanan(PDO $pdo, array $data, $key)
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $enc  = encryptData((string)$json, $key);
    if ($enc === false) {
        return false;
    }

    $stmt = $pdo->prepare('INSERT INTO pesanan (encrypted_data, nonce, auth_tag) VALUES (:d, :n, :t)');
    return $stmt->execute([
        ':d' => $enc['encrypted_data'],
        ':n' => $enc['nonce'],
        ':t' => $enc['auth_tag']
    ]);
}

function updatePesanan(PDO $pdo, $id, array $data, $key)
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $enc  = encryptData((string)$json, $key);
    if ($enc === false) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE pesanan SET encrypted_data = :d, nonce = :n, auth_tag = :t WHERE id = :id');
    return $stmt->execute([
        ':d'  => $enc['encrypted_data'],
        ':n'  => $enc['nonce'],
        ':t'  => $enc['auth_tag'],
        ':id' => (int)$id
    ]);
}

function deletePesanan(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('DELETE FROM pesanan WHERE id = :id');
    return $stmt->execute([':id' => (int)$id]);
}

function getAllPesananDecrypted(PDO $pdo, $key)
{
    $stmt = $pdo->query('SELECT id, encrypted_data, nonce, auth_tag, created_at, updated_at FROM pesanan ORDER BY id DESC');
    $rows = $stmt->fetchAll();

    $items       = [];
    $failedCount = 0;

    foreach ($rows as $row) {
        $plain = decryptData($row, $key);
        $data  = ($plain !== false) ? json_decode($plain, true) : null;

        if (!is_array($data)) {
            $failedCount++;
            $items[] = [
                'id'             => (int)$row['id'],
                'decryption_ok'  => false,
                'data'           => extractDatabaseCipherSlices($row),
                'encrypted_data' => $row['encrypted_data'],
                'nonce'          => $row['nonce'],
                'auth_tag'       => $row['auth_tag'],
                'created_at'     => $row['created_at'],
                'updated_at'     => $row['updated_at']
            ];
        } else {
            $items[] = [
                'id'             => (int)$row['id'],
                'decryption_ok'  => true,
                'data'           => $data,
                'encrypted_data' => $row['encrypted_data'],
                'nonce'          => $row['nonce'],
                'auth_tag'       => $row['auth_tag'],
                'created_at'     => $row['created_at'],
                'updated_at'     => $row['updated_at']
            ];
        }
    }

    return ['items' => $items, 'failed_count' => $failedCount];
}

function getPesananByIdDecrypted(PDO $pdo, $id, $key)
{
    $stmt = $pdo->prepare('SELECT id, encrypted_data, nonce, auth_tag, created_at, updated_at FROM pesanan WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $plain = decryptData($row, $key);
    $data  = ($plain !== false) ? json_decode($plain, true) : null;
    $isOk  = is_array($data);

    return [
        'id'             => (int)$row['id'],
        'decryption_ok'  => $isOk,
        'data'           => $isOk ? $data : extractDatabaseCipherSlices($row),
        'encrypted_data' => $row['encrypted_data'],
        'nonce'          => $row['nonce'],
        'auth_tag'       => $row['auth_tag'],
        'created_at'     => $row['created_at'],
        'updated_at'     => $row['updated_at']
    ];
}

function createLayanan(PDO $pdo, $nama, $harga)
{
    $key  = getServiceEncryptionKey($pdo);
    $json = json_encode(['nama_layanan' => $nama, 'harga' => (int)$harga], JSON_UNESCAPED_UNICODE);
    $enc  = encryptData((string)$json, $key);
    if ($enc === false) {
        return false;
    }

    $stmt = $pdo->prepare('INSERT INTO layanan (encrypted_data, nonce, auth_tag) VALUES (:d, :n, :t)');
    return $stmt->execute([
        ':d' => $enc['encrypted_data'],
        ':n' => $enc['nonce'],
        ':t' => $enc['auth_tag']
    ]);
}

function updateLayanan(PDO $pdo, $id, $nama, $harga)
{
    $key  = getServiceEncryptionKey($pdo);
    $json = json_encode(['nama_layanan' => $nama, 'harga' => (int)$harga], JSON_UNESCAPED_UNICODE);
    $enc  = encryptData((string)$json, $key);
    if ($enc === false) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE layanan SET encrypted_data = :d, nonce = :n, auth_tag = :t WHERE id = :id');
    return $stmt->execute([
        ':d'  => $enc['encrypted_data'],
        ':n'  => $enc['nonce'],
        ':t'  => $enc['auth_tag'],
        ':id' => (int)$id
    ]);
}

function deleteLayanan(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('DELETE FROM layanan WHERE id = :id');
    return $stmt->execute([':id' => (int)$id]);
}

function getAllLayanan(PDO $pdo)
{
    $key  = getServiceEncryptionKey($pdo);
    $stmt = $pdo->query('SELECT id, encrypted_data, nonce, auth_tag, created_at, updated_at FROM layanan ORDER BY id ASC');
    $rows = $stmt->fetchAll();

    $list = [];
    foreach ($rows as $row) {
        $plain = decryptData($row, $key);
        if ($plain !== false) {
            $d = json_decode($plain, true);
            if (is_array($d) && isset($d['nama_layanan'], $d['harga'])) {
                $list[] = [
                    'id'             => (int)$row['id'],
                    'decryption_ok'  => true,
                    'nama_layanan'   => $d['nama_layanan'],
                    'harga'          => (int)$d['harga'],
                    'encrypted_data' => $row['encrypted_data'],
                    'nonce'          => $row['nonce'],
                    'auth_tag'       => $row['auth_tag'],
                    'created_at'     => $row['created_at'],
                    'updated_at'     => $row['updated_at']
                ];
            }
        }
    }
    return $list;
}

function getAllLayananForAdmin(PDO $pdo, $keyIsValid)
{
    $serviceKey = $keyIsValid ? getServiceEncryptionKey($pdo) : str_repeat('0', 32);
    $stmt       = $pdo->query('SELECT id, encrypted_data, nonce, auth_tag, created_at, updated_at FROM layanan ORDER BY id ASC');
    $rows       = $stmt->fetchAll();

    $list = [];
    foreach ($rows as $row) {
        $plain = decryptData($row, $serviceKey);
        $d     = ($plain !== false) ? json_decode($plain, true) : null;

        if (is_array($d) && isset($d['nama_layanan'], $d['harga'])) {
            $list[] = [
                'id'             => (int)$row['id'],
                'decryption_ok'  => true,
                'nama_layanan'   => $d['nama_layanan'],
                'harga'          => (int)$d['harga'],
                'harga_tampil'   => formatRupiah((int)$d['harga']) . '/kg',
                'encrypted_data' => $row['encrypted_data'],
                'nonce'          => $row['nonce'],
                'auth_tag'       => $row['auth_tag'],
                'created_at'     => $row['created_at'],
                'updated_at'     => $row['updated_at']
            ];
        } else {
            $list[] = [
                'id'             => (int)$row['id'],
                'decryption_ok'  => false,
                'nama_layanan'   => substr($row['encrypted_data'], 0, 22),
                'harga'          => 0,
                'harga_tampil'   => substr($row['auth_tag'], 0, 14),
                'encrypted_data' => $row['encrypted_data'],
                'nonce'          => $row['nonce'],
                'auth_tag'       => $row['auth_tag'],
                'created_at'     => $row['created_at'],
                'updated_at'     => $row['updated_at']
            ];
        }
    }
    return $list;
}