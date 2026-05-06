<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$confirm = getenv('EURFOREX_CONFIRM_FULL_RESET') === 'yes-delete-business-data';
$preserveBossAccount = getenv('EURFOREX_RESET_PRESERVE_BOSS') !== '0';

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
        env_string('DB_NAME', 'eurforex_c2c')
    ),
    env_string('DB_USER', 'eurforex_c2c'),
    env_string('DB_PASSWORD', ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
    $stmt->execute([':table_name' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function count_table(PDO $pdo, string $table): int
{
    if (!table_exists($pdo, $table)) {
        return 0;
    }
    return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

$tables = [
    'admin_tokens',
    'admin_login_rate_limits',
    'admin_role_templates',
    'audit_logs',
    'order_evidences',
    'c2c_orders',
    'c2c_listings',
    'trade_feed_events',
    'user_financial_subscriptions',
    'financial_products',
    'wallet_ledger',
    'user_wallet_balances',
    'deposit_requests',
    'withdrawal_requests',
    'deposit_addresses',
    'user_payout_methods',
    'user_kyc_applications',
    'user_tier_profiles',
    'invitation_codes',
    'verification_codes',
    'user_tokens',
    'login_rate_limits',
    'auth_events',
    'support_tickets',
    'users',
];

if (!$preserveBossAccount) {
    $tables[] = 'admin_groups';
    $tables[] = 'admin_users';
}

$before = [];
foreach ($tables as $table) {
    $before[$table] = count_table($pdo, $table);
}

if (!$confirm) {
    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'message' => 'No data was deleted. Set EURFOREX_CONFIRM_FULL_RESET=yes-delete-business-data to execute.',
        'preserve_system_configs' => true,
        'preserve_boss_account' => $preserveBossAccount,
        'table_counts' => $before,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$deleted = [];
$pdo->beginTransaction();
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        if (!table_exists($pdo, $table)) {
            $deleted[$table] = 0;
            continue;
        }
        if ($preserveBossAccount && $table === 'admin_users') {
            continue;
        }
        $deleted[$table] = $pdo->exec('DELETE FROM ' . $table);
    }

    if ($preserveBossAccount && table_exists($pdo, 'admin_users')) {
        $deleted['admin_users.non_boss'] = $pdo->exec('DELETE FROM admin_users WHERE role_codes NOT LIKE \'%"big_boss"%\' AND display_name != "大老板"');
    }
    if ($preserveBossAccount && table_exists($pdo, 'admin_groups')) {
        $deleted['admin_groups.non_boss'] = $pdo->exec('DELETE FROM admin_groups WHERE group_code != "boss"');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo json_encode([
    'ok' => true,
    'dry_run' => false,
    'preserve_system_configs' => true,
    'preserve_boss_account' => $preserveBossAccount,
    'before' => $before,
    'deleted' => $deleted,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
