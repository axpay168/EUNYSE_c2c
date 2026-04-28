<?php

declare(strict_types=1);

$base = rtrim(getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$userAccount = getenv('SMOKE_USER_ACCOUNT') ?: '';
$userPassword = getenv('SMOKE_USER_PASSWORD') ?: '';
$adminAccount = getenv('SMOKE_ADMIN_ACCOUNT') ?: '';
$adminPassword = getenv('SMOKE_ADMIN_PASSWORD') ?: '';

foreach ([
    'SMOKE_USER_ACCOUNT' => $userAccount,
    'SMOKE_USER_PASSWORD' => $userPassword,
    'SMOKE_ADMIN_ACCOUNT' => $adminAccount,
    'SMOKE_ADMIN_PASSWORD' => $adminPassword,
] as $key => $value) {
    if ($value === '') {
        fwrite(STDERR, '[smoke] Missing required environment variable: ' . $key . PHP_EOL);
        exit(2);
    }
}

function smoke_request(string $method, string $url, ?array $body = null, ?string $token = null): array
{
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $error !== '') {
        throw new RuntimeException($method . ' ' . $url . ' failed: ' . $error);
    }
    $payload = json_decode((string) $raw, true);
    if (!is_array($payload)) {
        throw new RuntimeException($method . ' ' . $url . ' returned invalid JSON with HTTP ' . $status);
    }
    if ($status >= 400 || (int) ($payload['code'] ?? 0) !== 1) {
        throw new RuntimeException($method . ' ' . $url . ' failed: ' . ($payload['msg'] ?? 'unknown error'));
    }
    return $payload;
}

function smoke_step(string $label, callable $fn): mixed
{
    echo '[smoke] ' . $label . ' ... ';
    $result = $fn();
    echo "ok\n";
    return $result;
}

try {
    smoke_step('health', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/health'));

    $userLogin = smoke_step('user login', fn () => smoke_request('POST', $GLOBALS['base'] . '/api/user/login', [
        'account' => $GLOBALS['userAccount'],
        'password' => $GLOBALS['userPassword'],
        'lang' => 'zh-Hant',
    ]));
    $userToken = (string) ($userLogin['data']['userinfo']['token'] ?? '');
    if ($userToken === '') {
        throw new RuntimeException('user login did not return token');
    }
    smoke_step('user me', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/user/me', null, $userToken));
    smoke_step('user overview', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/user/overview', null, $userToken));
    smoke_step('user app config', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/user/app-config', null, $userToken));

    $adminLogin = smoke_step('admin login', fn () => smoke_request('POST', $GLOBALS['base'] . '/api/admin/auth/login', [
        'account' => $GLOBALS['adminAccount'],
        'password' => $GLOBALS['adminPassword'],
    ]));
    $adminToken = (string) ($adminLogin['data']['token'] ?? '');
    if ($adminToken === '') {
        throw new RuntimeException('admin login did not return token');
    }
    smoke_step('admin me', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/admin/auth/me', null, $adminToken));
    smoke_step('admin users', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/admin/users?page=1&page_size=5', null, $adminToken));
    smoke_step('admin system configs', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/admin/system/configs', null, $adminToken));
    smoke_step('admin wallet ledger', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/admin/users/1/wallets/ledger?page=1&page_size=5', null, $adminToken));
    smoke_step('admin tier templates', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/admin/tier-templates', null, $adminToken));

    $templateCode = 'smoke_' . time();
    $createdTemplate = smoke_step('admin tier template create', fn () => smoke_request('POST', $GLOBALS['base'] . '/api/admin/tier-templates', [
        'template_code' => $templateCode,
        'display_name' => 'Smoke Template',
        'level' => 2,
        'group_code' => 'vip',
        'merchant_enabled' => true,
        'is_verified' => true,
        'reason' => 'smoke test',
    ], $adminToken));
    $templateId = (int) ($createdTemplate['data']['id'] ?? 0);
    if ($templateId <= 0) {
        throw new RuntimeException('tier template create did not return id');
    }
    smoke_step('admin tier template update', fn () => smoke_request('PATCH', $GLOBALS['base'] . '/api/admin/tier-templates/' . $templateId, [
        'display_name' => 'Smoke Template Updated',
        'status' => 'disabled',
        'reason' => 'smoke test update',
    ], $adminToken));
    smoke_step('admin tier template delete', fn () => smoke_request('DELETE', $GLOBALS['base'] . '/api/admin/tier-templates/' . $templateId, [
        'reason' => 'smoke test cleanup',
    ], $adminToken));

    $noticeBefore = smoke_step('admin home content read', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/admin/home-content', null, $adminToken));
    $previousNotice = (string) ($noticeBefore['data']['notice'] ?? '');
    smoke_step('admin home notice update', fn () => smoke_request('PATCH', $GLOBALS['base'] . '/api/admin/home-content/notice', [
        'notice' => 'Smoke notice ' . time(),
        'reason' => 'smoke test notice update',
    ], $adminToken));
    smoke_step('admin home notice restore', fn () => smoke_request('PATCH', $GLOBALS['base'] . '/api/admin/home-content/notice', [
        'notice' => $previousNotice,
        'reason' => 'smoke test notice restore',
    ], $adminToken));

    $createdBanner = smoke_step('admin banner create', fn () => smoke_request('POST', $GLOBALS['base'] . '/api/admin/home-content/banners', [
        'title' => 'Smoke Banner',
        'subtitle' => 'Created by smoke test',
        'image_url' => 'https://example.test/smoke-banner.png',
        'link_url' => '#/pages/index/hall',
        'status' => 'active',
        'reason' => 'smoke test banner create',
    ], $adminToken));
    $bannerId = (int) ($createdBanner['data']['banner_id'] ?? 0);
    if ($bannerId <= 0) {
        throw new RuntimeException('banner create did not return id');
    }
    smoke_step('admin banner update', fn () => smoke_request('PATCH', $GLOBALS['base'] . '/api/admin/home-content/banners/' . $bannerId, [
        'title' => 'Smoke Banner Updated',
        'status' => 'inactive',
        'reason' => 'smoke test banner update',
    ], $adminToken));
    smoke_step('admin banner delete', fn () => smoke_request('DELETE', $GLOBALS['base'] . '/api/admin/home-content/banners/' . $bannerId, [
        'reason' => 'smoke test banner cleanup',
    ], $adminToken));

    $swapBefore = smoke_step('admin eur swap settings read', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/admin/withdrawal-settings/eur-swap', null, $adminToken));
    $swapItem = $swapBefore['data']['item'] ?? [];
    smoke_step('admin eur swap settings update', fn () => smoke_request('PATCH', $GLOBALS['base'] . '/api/admin/withdrawal-settings/eur-swap', [
        'rate' => '1.01000000',
        'fee_mode' => 'percent',
        'fee_rate' => '0.1000',
        'fee_fixed_usdt' => '0.00000000',
        'reason' => 'smoke test eur swap update',
    ], $adminToken));
    smoke_step('admin eur swap settings restore', fn () => smoke_request('PATCH', $GLOBALS['base'] . '/api/admin/withdrawal-settings/eur-swap', [
        'rate' => (string) ($swapItem['rate'] ?? '0.96000000'),
        'fee_mode' => (string) ($swapItem['fee_mode'] ?? 'percent'),
        'fee_rate' => (string) ($swapItem['fee_rate'] ?? '0.0000'),
        'fee_fixed_usdt' => (string) ($swapItem['fee_fixed_usdt'] ?? '0.00000000'),
        'reason' => 'smoke test eur swap restore',
    ], $adminToken));

    $createdFeed = smoke_step('admin trade feed create', fn () => smoke_request('POST', $GLOBALS['base'] . '/api/admin/trade-feed-events', [
        'action_type' => 'custom',
        'title' => 'Smoke trade feed',
        'actor_name' => 'smoke@example.test',
        'asset_code' => 'USDT',
        'amount' => '123.45',
        'status' => 'active',
    ], $adminToken));
    $eventId = (int) ($createdFeed['data']['event_id'] ?? 0);
    if ($eventId <= 0) {
        throw new RuntimeException('trade feed create did not return id');
    }
    smoke_step('admin trade feed update', fn () => smoke_request('PATCH', $GLOBALS['base'] . '/api/admin/trade-feed-events/' . $eventId, [
        'title' => 'Smoke trade feed updated',
        'actor_name' => 'smoke@example.test',
        'amount' => '124.45',
        'status' => 'inactive',
    ], $adminToken));
    smoke_step('admin trade feed delete', fn () => smoke_request('DELETE', $GLOBALS['base'] . '/api/admin/trade-feed-events/' . $eventId, null, $adminToken));

    $productCode = 'smoke_' . time();
    $createdProduct = smoke_step('admin financial product create', fn () => smoke_request('POST', $GLOBALS['base'] . '/api/admin/financial-products', [
        'product_code' => $productCode,
        'asset_code' => 'USDT',
        'wallet_code' => 'cash_usdt',
        'display_name' => 'Smoke Product',
        'subtitle' => 'Smoke test product',
        'detail_note' => 'Created by smoke test',
        'apr_rate' => '1.20',
        'term_days' => 7,
        'min_subscribe_amount' => '1',
        'personal_limit_amount' => '10',
        'total_quota_amount' => '100',
        'default_return_mode' => 'manual',
        'default_return_delay_days' => 0,
        'status' => 'inactive',
    ], $adminToken));
    $productId = (int) ($createdProduct['data']['item']['id'] ?? 0);
    if ($productId <= 0) {
        throw new RuntimeException('financial product create did not return id');
    }
    smoke_step('admin financial product update', fn () => smoke_request('PATCH', $GLOBALS['base'] . '/api/admin/financial-products/' . $productId, [
        'product_code' => $productCode,
        'asset_code' => 'USDT',
        'wallet_code' => 'cash_usdt',
        'display_name' => 'Smoke Product Updated',
        'subtitle' => 'Smoke test product',
        'detail_note' => 'Updated by smoke test',
        'apr_rate' => '1.30',
        'term_days' => 7,
        'min_subscribe_amount' => '1',
        'personal_limit_amount' => '10',
        'total_quota_amount' => '100',
        'default_return_mode' => 'manual',
        'default_return_delay_days' => 0,
        'status' => 'inactive',
    ], $adminToken));

    smoke_step('user support ticket create', fn () => smoke_request('POST', $GLOBALS['base'] . '/api/user/support-tickets', [
        'category' => 'smoke',
        'subject' => 'Smoke support ticket',
        'content' => 'Created by smoke test',
    ], $userToken));
    smoke_step('admin support tickets', fn () => smoke_request('GET', $GLOBALS['base'] . '/api/admin/support-tickets?page=1&page_size=5', null, $adminToken));

    echo "[smoke] all checks passed\n";
} catch (Throwable $exception) {
    fwrite(STDERR, '[smoke] failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
