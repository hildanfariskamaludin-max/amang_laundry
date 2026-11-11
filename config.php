<?php
/**
 * AMANG LAUNDRY - PORTUGAL FOOTBALL EDITION (CR7 THEME)
 * File 1/3: config.php (Perbaikan Auto-Repair & Sinkronisasi Kunci Tabel Layanan)
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
define('ADMIN_DEFAULT_PASS', 'Portugal 33');
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

// 3. Inisialisasi Tabel & Auto-Repair Data Layanan
initializeSystemDefaults($pdo);

function initializeSystemDefaults(PDO $pdo): void
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

    // Cek akun admin Kemal
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => ADMIN_DEFAULT_USER]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $hash = password_hash(ADMIN_DEFAULT_PASS, PASSWORD_BCRYPT);
        $ins  = $pdo->prepare('INSERT INTO admin (username, password_hash) VALUES (:u, :p)');
        $ins->execute([
            ':u' => ADMIN_DEFAULT_USER,
            ':p' => $hash
        ]);
    } else {
        if (!password_verify(ADMIN_DEFAULT_PASS, $admin['password_hash'])) {
            $newHash = password_hash(ADMIN_DEFAULT_PASS, PASSWORD_BCRYPT);
            $upd = $pdo->prepare('UPDATE admin SET password_hash = :p WHERE id = :id');
            $upd->execute([':p' => $newHash, ':id' => $admin['id']]);
        }
    }

    // Cek apakah layanan yang bisa didekripsi kosong (akibat beda kunci versi lama).
    // Jika kosong, bersihkan tabel layanan lama dan isi ulang 4 layanan standar.
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

    // Cek contoh pesanan awal
    $countPesanan = (int) $pdo->query('SELECT COUNT(*) FROM pesanan')->fetchColumn();
    if ($countPesanan === 0) {
        $stmtAdmin = $pdo->prepare('SELECT password_hash FROM admin WHERE username = :u LIMIT 1');
        $stmtAdmin->execute([':u' => ADMIN_DEFAULT_USER]);
        $admRow = $stmtAdmin->fetch();
        if ($admRow) {
            $trueKey = deriveEncryptionKey(ADMIN_DEFAULT_PASS, $admRow['password_hash']);
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

function deriveEncryptionKey(string $password, string $passwordHash): string
{
    $salt = substr(hash('sha256', 'CR7_PORTUGAL_SALT::' . $passwordHash, true), 0, 16);
    return hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);
}

/**
 * Kunci enkripsi tabel layanan (Konsisten 32-byte agar tidak rusak saat hash admin direset).
 */
function getServiceEncryptionKey(PDO $pdo): string
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

function encryptData(string $plaintext, string $key): array|false
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

function decryptData(array $encryptedPackage, string $key): string|false
{
    if (strlen($key) !== 32) {
        return false;
    }

    $ciphertextB64 = $encryptedPackage['encrypted_data'] ?? '';
    $nonceHex      = $encryptedPackage['nonce'] ?? '';
    $tagHex        = $encryptedPackage['auth_tag'] ?? '';

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

function extractDatabaseCipherSlices(array $row): array
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
// 5. AUTENTIKASI & KEAMANAN SESI
// ============================================================================

function authenticateAdmin(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return false;
    }

    $isValidPassword = password_verify($password, $admin['password_hash']);

    session_regenerate_id(true);
    $derivedKey = deriveEncryptionKey($password, $admin['password_hash']);

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']        = (int)$admin['id'];
    $_SESSION['admin_username']  = $admin['username'];
    $_SESSION['aes_key_hex']     = bin2hex($derivedKey);
    $_SESSION['is_key_valid']    = $isValidPassword;

    return true;
}

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_logged_in'])
           && !empty($_SESSION['aes_key_hex'])
           && strlen($_SESSION['aes_key_hex']) === 64;
}

function isKeyValid(): bool
{
    return !empty($_SESSION['is_key_valid']) && $_SESSION['is_key_valid'] === true;
}

function requireAdminAccess(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: index.php?view=login&denied=1');
        exit;
    }
}

function getSessionEncryptionKey(): string
{
    if (!isAdminLoggedIn()) {
        return '';
    }
    $rawKey = hex2bin($_SESSION['aes_key_hex']);
    return ($rawKey !== false && strlen($rawKey) === 32) ? $rawKey : '';
}

function logoutAdmin(): void
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

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    return !empty($_SESSION['csrf_token']) && $token !== '' && hash_equals($_SESSION['csrf_token'], $token);
}

function cleanInput(string $val): string
{
    return trim(strip_tags($val));
}

function e(mixed $val): string
{
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatRupiah(int|float $nominal): string
{
    return 'Rp' . number_format((float)$nominal, 0, ',', '.');
}

// ============================================================================
// 6. HELPER CRUD DATA PESANAN & LAYANAN (TERENKRIPSI AES-256-GCM)
// ============================================================================

function createPesanan(PDO $pdo, array $data, string $key): bool
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

function updatePesanan(PDO $pdo, int $id, array $data, string $key): bool
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
        ':id' => $id
    ]);
}

function deletePesanan(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM pesanan WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

function getAllPesananDecrypted(PDO $pdo, string $key): array
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

function getPesananByIdDecrypted(PDO $pdo, int $id, string $key): ?array
{
    $stmt = $pdo->prepare('SELECT id, encrypted_data, nonce, auth_tag, created_at, updated_at FROM pesanan WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $plain  = decryptData($row, $key);
    $data   = ($plain !== false) ? json_decode($plain, true) : null;
    $isOk   = is_array($data);

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

function createLayanan(PDO $pdo, string $nama, int $harga): bool
{
    $key  = getServiceEncryptionKey($pdo);
    $json = json_encode(['nama_layanan' => $nama, 'harga' => $harga], JSON_UNESCAPED_UNICODE);
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

function updateLayanan(PDO $pdo, int $id, string $nama, int $harga): bool
{
    $key  = getServiceEncryptionKey($pdo);
    $json = json_encode(['nama_layanan' => $nama, 'harga' => $harga], JSON_UNESCAPED_UNICODE);
    $enc  = encryptData((string)$json, $key);
    if ($enc === false) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE layanan SET encrypted_data = :d, nonce = :n, auth_tag = :t WHERE id = :id');
    return $stmt->execute([
        ':d'  => $enc['encrypted_data'],
        ':n'  => $enc['nonce'],
        ':t'  => $enc['auth_tag'],
        ':id' => $id
    ]);
}

function deleteLayanan(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM layanan WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

function getAllLayanan(PDO $pdo): array
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

function getAllLayananForAdmin(PDO $pdo, bool $keyIsValid): array
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