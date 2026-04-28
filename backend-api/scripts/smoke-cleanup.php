<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$writeAccount = getenv('SMOKE_WRITE_ACCOUNT') ?: 'smoke.write@example.com';

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }
    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env_string(string $key, string $default = ''): string
{
    $value = $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return trim((string) $value);
}

load_env_file($rootDir . '/.env');

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env_string('DB_HOST', '127.0.0.1'),
        env_string('DB_PORT', '3306'),
        env_string('DB_NAME', 'eurnyse_c2c')
    ),
    env_string('DB_USER', 'eurnyse_c2c'),
    env_string('DB_PASSWORD', ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function fetchAllAssoc(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function fetchOneAssoc(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function executeStmt(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function deleteByIds(PDO $pdo, string $table, string $column, array $ids): int
{
    if (!$ids) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return executeStmt($pdo, "DELETE FROM {$table} WHERE {$column} IN ({$placeholders})", array_values($ids));
}

function deleteAuditLogsByTargetIds(PDO $pdo, string $targetType, array $ids): int
{
    if (!$ids) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$targetType], array_values($ids));
    return executeStmt($pdo, "DELETE FROM audit_logs WHERE target_type = ? AND target_id IN ({$placeholders})", $params);
}

function resolveUploadPath(string $rootDir, string $url): ?string
{
    if ($url === '') {
        return null;
    }
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || strpos($path, '/storage/') !== 0) {
        return null;
    }
    $fullPath = $rootDir . $path;
    return is_file($fullPath) ? $fullPath : null;
}

function maybeRestoreListings(PDO $pdo, array $orders): array
{
    $restored = [];
    foreach ($orders as $order) {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            continue;
        }
        $evidence = fetchOneAssoc(
            $pdo,
            "SELECT content FROM order_evidences WHERE order_id = ? AND content LIKE 'Order created from listing #%'
             ORDER BY id ASC LIMIT 1",
            [$orderId]
        );
        $content = (string)($evidence['content'] ?? '');
        if ($content === '' || !preg_match('/listing #(\d+)/', $content, $matches)) {
            continue;
        }
        $listingId = (int)$matches[1];
        $amount = (float)($order['amount'] ?? 0);
        if ($listingId <= 0 || $amount <= 0) {
            continue;
        }
        executeStmt(
            $pdo,
            'UPDATE c2c_listings SET available_amount = CAST(CAST(available_amount AS DECIMAL(24,8)) + ? AS CHAR) WHERE id = ?',
            [$amount, $listingId]
        );
        $restored[] = [
            'order_id' => $orderId,
            'listing_id' => $listingId,
            'amount' => $amount
        ];
    }
    return $restored;
}

$user = fetchOneAssoc(
    $pdo,
    'SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1',
    [$writeAccount, $writeAccount]
);

if (!$user) {
    echo json_encode([
        'ok' => true,
        'account' => $writeAccount,
        'deleted' => false,
        'message' => 'smoke user not found'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$userId = (int)$user['id'];
$depositRows = fetchAllAssoc($pdo, 'SELECT id, proof_url FROM deposit_requests WHERE user_id = ?', [$userId]);
$withdrawalRows = fetchAllAssoc($pdo, 'SELECT id FROM withdrawal_requests WHERE user_id = ?', [$userId]);
$kycRows = fetchAllAssoc(
    $pdo,
    'SELECT id, id_doc_front_url, id_doc_back_url, selfie_url FROM user_kyc_applications WHERE user_id = ?',
    [$userId]
);
$orderRows = fetchAllAssoc(
    $pdo,
    'SELECT id, amount FROM c2c_orders WHERE buyer_user_id = ? OR seller_user_id = ?',
    [$userId, $userId]
);
$payoutRows = fetchAllAssoc($pdo, 'SELECT id FROM user_payout_methods WHERE user_id = ?', [$userId]);

$depositIds = array_map(static fn($row) => (int)$row['id'], $depositRows);
$withdrawalIds = array_map(static fn($row) => (int)$row['id'], $withdrawalRows);
$kycIds = array_map(static fn($row) => (int)$row['id'], $kycRows);
$orderIds = array_map(static fn($row) => (int)$row['id'], $orderRows);
$payoutIds = array_map(static fn($row) => (int)$row['id'], $payoutRows);

$uploadPaths = [];
foreach ($depositRows as $row) {
    $path = resolveUploadPath($rootDir, (string)($row['proof_url'] ?? ''));
    if ($path) {
        $uploadPaths[$path] = true;
    }
}
foreach ($kycRows as $row) {
    foreach (['id_doc_front_url', 'id_doc_back_url', 'selfie_url'] as $field) {
        $path = resolveUploadPath($rootDir, (string)($row[$field] ?? ''));
        if ($path) {
            $uploadPaths[$path] = true;
        }
    }
}

$pdo->beginTransaction();
try {
    $restoredListings = maybeRestoreListings($pdo, $orderRows);

    $deleted = [
        'order_evidences' => deleteByIds($pdo, 'order_evidences', 'order_id', $orderIds),
        'audit_logs.orders' => deleteAuditLogsByTargetIds($pdo, 'order', $orderIds),
        'audit_logs.deposits' => deleteAuditLogsByTargetIds($pdo, 'deposit_request', $depositIds),
        'audit_logs.withdrawals' => deleteAuditLogsByTargetIds($pdo, 'withdrawal_request', $withdrawalIds),
        'audit_logs.kyc' => deleteAuditLogsByTargetIds($pdo, 'kyc_application', $kycIds),
        'audit_logs.payout_methods' => deleteAuditLogsByTargetIds($pdo, 'payout_method', $payoutIds),
        'audit_logs.user' => deleteAuditLogsByTargetIds($pdo, 'user', [$userId]),
        'c2c_orders' => deleteByIds($pdo, 'c2c_orders', 'id', $orderIds),
        'deposit_requests' => deleteByIds($pdo, 'deposit_requests', 'id', $depositIds),
        'withdrawal_requests' => deleteByIds($pdo, 'withdrawal_requests', 'id', $withdrawalIds),
        'user_kyc_applications' => deleteByIds($pdo, 'user_kyc_applications', 'id', $kycIds),
        'user_payout_methods' => deleteByIds($pdo, 'user_payout_methods', 'id', $payoutIds),
        'user_tokens' => executeStmt($pdo, 'DELETE FROM user_tokens WHERE user_id = ?', [$userId]),
        'user_wallet_balances' => executeStmt($pdo, 'DELETE FROM user_wallet_balances WHERE user_id = ?', [$userId]),
        'user_tier_profiles' => executeStmt($pdo, 'DELETE FROM user_tier_profiles WHERE user_id = ?', [$userId]),
        'invitation_codes' => executeStmt($pdo, 'DELETE FROM invitation_codes WHERE user_id = ?', [$userId]),
        'verification_codes' => executeStmt($pdo, 'DELETE FROM verification_codes WHERE target = ?', [$writeAccount]),
        'users' => executeStmt($pdo, 'DELETE FROM users WHERE id = ?', [$userId])
    ];

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

$deletedFiles = [];
foreach (array_keys($uploadPaths) as $filePath) {
    if (@unlink($filePath)) {
        $deletedFiles[] = $filePath;
    }
}

echo json_encode([
    'ok' => true,
    'account' => $writeAccount,
    'deleted' => true,
    'user_id' => $userId,
    'restored_listings' => $restoredListings ?? [],
    'deleted_rows' => $deleted,
    'deleted_upload_files' => $deletedFiles
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
