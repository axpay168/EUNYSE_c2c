<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$emailLike = getenv('SMOKE_EMAIL_LIKE') ?: 'e2e.smoke-%@test.local';
$listingLike = getenv('SMOKE_LISTING_LIKE') ?: 'smoke-%';
$financialProductLike = getenv('SMOKE_FINANCIAL_PRODUCT_LIKE') ?: 'smoke_%';

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
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env_string(string $key, string $default = ''): string
{
    $value = $_SERVER[$key] ?? getenv($key);
    return $value === false || $value === null ? $default : trim((string) $value);
}

function ids(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function delete_in(PDO $pdo, string $table, string $column, array $ids): int
{
    if (!$ids) return 0;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$column} IN ({$placeholders})");
    $stmt->execute(array_values($ids));
    return $stmt->rowCount();
}

function exec_stmt(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
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

$userIds = ids($pdo, 'SELECT id FROM users WHERE email LIKE ? OR username LIKE ?', [$emailLike, $emailLike]);
$listingIds = ids($pdo, 'SELECT id FROM c2c_listings WHERE nickname LIKE ?', [$listingLike]);
$financialProductIds = ids($pdo, 'SELECT id FROM financial_products WHERE product_code LIKE ?', [$financialProductLike]);
$orderIds = $userIds
    ? ids($pdo, 'SELECT id FROM c2c_orders WHERE buyer_user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ') OR seller_user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', array_merge($userIds, $userIds))
    : [];
if ($listingIds) {
    $listingOrderIds = ids($pdo, 'SELECT order_id FROM order_evidences WHERE content REGEXP ?', ['listing #(' . implode('|', $listingIds) . ')']);
    $orderIds = array_values(array_unique(array_merge($orderIds, $listingOrderIds)));
}

$pdo->beginTransaction();
try {
    $deleted = [
        'order_evidences' => delete_in($pdo, 'order_evidences', 'order_id', $orderIds),
        'c2c_orders' => delete_in($pdo, 'c2c_orders', 'id', $orderIds),
        'c2c_listings' => delete_in($pdo, 'c2c_listings', 'id', $listingIds),
        'financial_products' => delete_in($pdo, 'financial_products', 'id', $financialProductIds),
        'deposit_requests' => $userIds ? exec_stmt($pdo, 'DELETE FROM deposit_requests WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) : 0,
        'withdrawal_requests' => $userIds ? exec_stmt($pdo, 'DELETE FROM withdrawal_requests WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) : 0,
        'user_kyc_applications' => $userIds ? exec_stmt($pdo, 'DELETE FROM user_kyc_applications WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) : 0,
        'user_payout_methods' => $userIds ? exec_stmt($pdo, 'DELETE FROM user_payout_methods WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) : 0,
        'user_tokens' => $userIds ? exec_stmt($pdo, 'DELETE FROM user_tokens WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) : 0,
        'wallet_ledger' => $userIds ? exec_stmt($pdo, 'DELETE FROM wallet_ledger WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) : 0,
        'user_wallet_balances' => $userIds ? exec_stmt($pdo, 'DELETE FROM user_wallet_balances WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) : 0,
        'user_tier_profiles' => $userIds ? exec_stmt($pdo, 'DELETE FROM user_tier_profiles WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) : 0,
        'invitation_codes' => $userIds ? exec_stmt($pdo, 'DELETE FROM invitation_codes WHERE user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) : 0,
        'users' => delete_in($pdo, 'users', 'id', $userIds),
    ];
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

echo json_encode([
    'ok' => true,
    'email_like' => $emailLike,
    'listing_like' => $listingLike,
    'financial_product_like' => $financialProductLike,
    'user_ids' => $userIds,
    'listing_ids' => $listingIds,
    'financial_product_ids' => $financialProductIds,
    'order_ids' => $orderIds,
    'deleted_rows' => $deleted,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
