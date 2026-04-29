<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

header_remove('X-Powered-By');

require_once dirname(__DIR__) . '/mysql_schema.php';

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
        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim((string) $parts[0]);
        $value = trim((string) $parts[1]);
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

load_env_file(dirname(__DIR__) . '/.env');

function env_string(string $key, string $default = ''): string
{
    $value = $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return trim((string) $value);
}

function env_bool(string $key, bool $default = false): bool
{
    $raw = env_string($key, $default ? '1' : '0');
    return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
}

function request_origin(): string
{
    return trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
}

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    return $forwardedProto === 'https';
}

function cors_allowed_origins(): array
{
    $configured = array_filter(array_map('trim', explode(',', env_string('API_ALLOWED_ORIGINS', ''))));
    $defaults = [
        'http://127.0.0.1:8094',
        'http://localhost:8094',
        'http://127.0.0.1:8095',
        'http://localhost:8095',
    ];
    return array_values(array_unique(array_filter(array_merge($defaults, $configured))));
}

function is_private_ipv4_host(string $host): bool
{
    if (preg_match('/^10\.\d+\.\d+\.\d+$/', $host)) {
        return true;
    }
    if (preg_match('/^192\.168\.\d+\.\d+$/', $host)) {
        return true;
    }
    if (preg_match('/^172\.(\d+)\.\d+\.\d+$/', $host, $matches)) {
        $secondOctet = (int) ($matches[1] ?? 0);
        return $secondOctet >= 16 && $secondOctet <= 31;
    }
    return false;
}

function is_local_dev_origin(string $origin): bool
{
    if ($origin === '') {
        return false;
    }
    $parts = parse_url($origin);
    if (!is_array($parts)) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $port = isset($parts['port']) ? (int) $parts['port'] : 0;

    if (!in_array($scheme, ['http', 'https'], true) || $host === '' || $port <= 0) {
        return false;
    }

    if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
        return true;
    }

    return is_private_ipv4_host($host);
}

function configure_cors(): void
{
    $origin = request_origin();
    $allowedOrigins = cors_allowed_origins();
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Timezone');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    if ($origin === '') {
        return;
    }
    if (in_array($origin, $allowedOrigins, true) || is_local_dev_origin($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(403);
        exit;
    }
}

configure_cors();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function now_iso(): string
{
    return gmdate('c');
}

function request_timezone(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $candidates = [
        trim((string) ($_SERVER['HTTP_X_TIMEZONE'] ?? '')),
        trim((string) ($_SERVER['HTTP_CF_TIMEZONE'] ?? '')),
        trim((string) ($_SERVER['GEOIP_TIMEZONE'] ?? '')),
        trim((string) ($_SERVER['HTTP_X_GEOIP_TIMEZONE'] ?? '')),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && in_array($candidate, timezone_identifiers_list(), true)) {
            $resolved = $candidate;
            return $resolved;
        }
    }

    $countryCode = strtoupper(trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? $_SERVER['HTTP_X_COUNTRY_CODE'] ?? '')));
    $resolved = match ($countryCode) {
        'DE', 'AT', 'NL', 'BE', 'LU', 'DK', 'SE', 'NO' => 'Europe/Berlin',
        'FR' => 'Europe/Paris',
        'IT' => 'Europe/Rome',
        'IE', 'PT' => 'Europe/Dublin',
        'ES' => 'Europe/Madrid',
        'GR' => 'Europe/Athens',
        'PL' => 'Europe/Warsaw',
        'HU' => 'Europe/Budapest',
        'FI' => 'Europe/Helsinki',
        'JP' => 'Asia/Tokyo',
        'KR' => 'Asia/Seoul',
        'VN' => 'Asia/Ho_Chi_Minh',
        'TH' => 'Asia/Bangkok',
        'IN' => 'Asia/Kolkata',
        'PK' => 'Asia/Karachi',
        'NP' => 'Asia/Kathmandu',
        'AF' => 'Asia/Kabul',
        'PH' => 'Asia/Manila',
        'SG' => 'Asia/Singapore',
        'HK', 'MO' => 'Asia/Hong_Kong',
        'AU' => 'Australia/Sydney',
        default => 'UTC',
    };

    return $resolved;
}

function format_time_for_request_timezone(?string $value): ?string
{
    $source = trim((string) $value);
    if ($source === '') {
        return null;
    }

    try {
        $dateTime = new DateTimeImmutable($source);
        $localized = $dateTime->setTimezone(new DateTimeZone(request_timezone()));
        return $localized->format('Y-m-d H:i:s');
    } catch (Throwable $exception) {
        return $source;
    }
}

/**
 * Adds *_utc originals and replaces listed fields with request-localized datetimes (same as c2c orders).
 *
 * @param  array<string, mixed>  $row
 * @param  list<string>  $fields
 * @return array<string, mixed>
 */
function localize_timestamps_on_row(array $row, array $fields): array
{
    foreach ($fields as $field) {
        if (!array_key_exists($field, $row)) {
            continue;
        }
        $raw = $row[$field];
        if ($raw === null || $raw === '') {
            $row[$field . '_utc'] = null;
            $row[$field] = null;

            continue;
        }
        $rawStr = (string) $raw;
        $row[$field . '_utc'] = $rawStr;
        $row[$field] = format_time_for_request_timezone($rawStr) ?? $rawStr;
    }

    return $row;
}

function random_token(int $length = 40): string
{
    return bin2hex(random_bytes((int) ceil($length / 2)));
}

function lang_map(string $lang): string
{
    return match ($lang) {
        'eng' => 'eng',
        'hkg', 'zh-Hant' => 'hkg',
        'jpn', 'jp' => 'jpn',
        'kor', 'kr' => 'kor',
        'vnm', 'vi' => 'vnm',
        default => 'eng',
    };
}

function read_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input') ?: '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        return $_GET;
    }

    if (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    if ($method === 'PATCH') {
        parse_str($raw, $parsed);
        return is_array($parsed) ? $parsed : [];
    }

    return $_POST;
}

function success(string $successCode, string $msg, array $data = []): void
{
    echo json_encode([
        'code' => 1,
        'success_code' => $successCode,
        'error_code' => null,
        'msg' => $msg,
        'time' => (string) time(),
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function failure(string $errorCode, string $msg, ?array $data = null, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'code' => 0,
        'success_code' => null,
        'error_code' => $errorCode,
        'msg' => $msg,
        'time' => (string) time(),
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensure_storage_dir(): string
{
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function ensure_cache_dir(): string
{
    $dir = ensure_storage_dir() . '/cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function read_cache_json(string $key, int $ttlSeconds): ?array
{
    $path = ensure_cache_dir() . '/' . preg_replace('/[^a-z0-9_\-]/i', '-', $key) . '.json';
    if (!is_file($path)) {
        return null;
    }
    $mtime = @filemtime($path);
    if (!$mtime || ($mtime + $ttlSeconds) < time()) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function read_cache_json_any_age(string $key): ?array
{
    $path = ensure_cache_dir() . '/' . preg_replace('/[^a-z0-9_\-]/i', '-', $key) . '.json';
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function write_cache_json(string $key, array $payload): void
{
    $path = ensure_cache_dir() . '/' . preg_replace('/[^a-z0-9_\-]/i', '-', $key) . '.json';
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function http_get_json(string $url, int $timeoutSeconds = 4): ?array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
            'header' => "User-Agent: New_C2C/1.0\r\nAccept: application/json\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function format_market_volume(float $value): string
{
    if (!is_finite($value) || $value < 0) {
        return '—';
    }
    if ($value >= 1000000000) {
        return rtrim(rtrim(number_format($value / 1000000000, 2, '.', ''), '0'), '.') . 'B';
    }
    if ($value >= 1000000) {
        return rtrim(rtrim(number_format($value / 1000000, 1, '.', ''), '0'), '.') . 'M';
    }
    if ($value >= 1000) {
        return rtrim(rtrim(number_format($value / 1000, 1, '.', ''), '0'), '.') . 'K';
    }
    return (string) (int) round($value);
}

function default_market_spark_points(string $code): array
{
    $upper = strtoupper(trim($code));
    return match ($upper) {
        'BTC' => [1.00, 1.01, 1.00, 1.02, 1.03, 1.04, 1.03, 1.05, 1.04, 1.06, 1.07, 1.08],
        'ETH' => [1.00, 1.00, 1.01, 1.00, 1.02, 1.03, 1.02, 1.04, 1.05, 1.06, 1.05, 1.07],
        'XRP' => [1.00, 0.99, 1.00, 1.01, 1.00, 1.02, 1.01, 1.02, 1.03, 1.02, 1.04, 1.03],
        default => [1, 1, 1, 1],
    };
}

function market_snapshot_items_complete(?array $items): bool
{
    if (!is_array($items) || count($items) < 3) {
        return false;
    }
    foreach ($items as $item) {
        if (!is_array($item) || !array_key_exists('volume', $item) || !array_key_exists('spark', $item)) {
            return false;
        }
        if (trim((string) ($item['volume'] ?? '')) === '' || trim((string) ($item['volume'] ?? '')) === '—') {
            return false;
        }
    }
    return true;
}

function fetch_market_spark_points(array $pairs): array
{
    $result = [];
    foreach ($pairs as $pair => $code) {
        $symbol = strtoupper(trim((string) $pair));
        $coin = strtoupper(trim((string) $code));
        if ($symbol === '' || $coin === '') {
            continue;
        }
        $cacheKey = 'market-spark-' . strtolower($symbol);
        $cached = read_cache_json($cacheKey, 900);
        if (is_array($cached) && $cached) {
            $result[$coin] = $cached;
            continue;
        }
        $endpoint = 'https://api.binance.com/api/v3/klines?symbol=' . rawurlencode($symbol) . '&interval=1h&limit=12';
        $rows = http_get_json($endpoint, 2);
        if (!is_array($rows) || !$rows) {
            $result[$coin] = read_cache_json_any_age($cacheKey) ?: default_market_spark_points($coin);
            continue;
        }
        $points = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row[4])) {
                continue;
            }
            $close = (float) $row[4];
            if (is_finite($close) && $close > 0) {
                $points[] = (float) number_format($close, 6, '.', '');
            }
        }
        $result[$coin] = count($points) >= 4 ? $points : (read_cache_json_any_age($cacheKey) ?: default_market_spark_points($coin));
        if (count($points) >= 4) {
            write_cache_json($cacheKey, $result[$coin]);
        }
    }
    return $result;
}

function fetch_market_snapshot_coingecko(): ?array
{
    $endpoint = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=bitcoin,ethereum,ripple&order=market_cap_desc&per_page=3&page=1&sparkline=true&price_change_percentage=24h';
    $payload = http_get_json($endpoint, 2);
    if (!is_array($payload) || !$payload) {
        return null;
    }
    $codeMap = [
        'bitcoin' => 'BTC',
        'ethereum' => 'ETH',
        'ripple' => 'XRP',
    ];
    $items = [];
    foreach ($payload as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = strtolower(trim((string) ($row['id'] ?? '')));
        $symbol = $codeMap[$id] ?? null;
        if ($symbol === null) {
            continue;
        }
        $price = isset($row['current_price']) ? (float) $row['current_price'] : 0.0;
        $changePercent = isset($row['price_change_percentage_24h']) ? (float) $row['price_change_percentage_24h'] : 0.0;
        $volume = isset($row['total_volume']) ? (float) $row['total_volume'] : null;
        $spark = $row['sparkline_in_7d']['price'] ?? null;
        $sparkPoints = is_array($spark) ? array_values(array_slice(array_filter(array_map(static function ($value): float {
            return (float) $value;
        }, $spark), static function (float $value): bool {
            return is_finite($value) && $value > 0;
        }), -12)) : [];
        $items[] = [
            'code' => $symbol,
            'price' => $price >= 100 ? number_format($price, 2, '.', ',') : number_format($price, $price >= 1 ? 2 : 4, '.', ','),
            'delta' => sprintf('%s%.2f%%', $changePercent >= 0 ? '+' : '', $changePercent),
            'change' => $changePercent >= 0 ? 1 : -1,
            'volume' => $volume !== null ? format_market_volume($volume) : '—',
            'spark' => count($sparkPoints) >= 4 ? $sparkPoints : default_market_spark_points($symbol),
        ];
    }
    if (!$items) {
        return null;
    }
    usort($items, static function (array $left, array $right): int {
        $order = ['BTC' => 0, 'ETH' => 1, 'XRP' => 2];
        return ($order[$left['code']] ?? 99) <=> ($order[$right['code']] ?? 99);
    });
    return $items;
}

function fetch_market_snapshot(): array
{
    $cacheKey = 'market-snapshot-binance';
    $cached = read_cache_json($cacheKey, 120);
    if (market_snapshot_items_complete($cached)) {
        return $cached;
    }
    $stale = read_cache_json_any_age($cacheKey);
    if (!market_snapshot_items_complete($stale)) {
        $stale = null;
    }

    $default = [
        ['code' => 'BTC', 'price' => '67,432.00', 'delta' => '+2.45%', 'change' => 1, 'volume' => '—', 'spark' => default_market_spark_points('BTC')],
        ['code' => 'ETH', 'price' => '3,492.00', 'delta' => '+1.12%', 'change' => 1, 'volume' => '—', 'spark' => default_market_spark_points('ETH')],
        ['code' => 'XRP', 'price' => '1.24', 'delta' => '-0.86%', 'change' => -1, 'volume' => '—', 'spark' => default_market_spark_points('XRP')],
    ];

    $endpoint = 'https://api.binance.com/api/v3/ticker/24hr?symbols=%5B%22BTCUSDT%22,%22ETHUSDT%22,%22XRPUSDT%22%5D';
    $payload = http_get_json($endpoint, 2);
    if (!$payload) {
        $coingecko = fetch_market_snapshot_coingecko();
        if (market_snapshot_items_complete($coingecko)) {
            write_cache_json($cacheKey, $coingecko);
            return $coingecko;
        }
        return is_array($stale) && $stale ? $stale : $default;
    }

    $symbolMap = [
        'BTCUSDT' => 'BTC',
        'ETHUSDT' => 'ETH',
        'XRPUSDT' => 'XRP',
    ];
    $sparkByCode = fetch_market_spark_points($symbolMap);

    $items = [];
    foreach ($payload as $row) {
        if (!is_array($row)) {
            continue;
        }
        $pair = strtoupper(trim((string) ($row['symbol'] ?? '')));
        $symbol = $symbolMap[$pair] ?? null;
        if ($symbol === null || !isset($row['lastPrice'])) {
            continue;
        }
        $price = (float) $row['lastPrice'];
        $changePercent = isset($row['priceChangePercent']) ? (float) $row['priceChangePercent'] : 0.0;
        $quoteVolume = isset($row['quoteVolume']) ? (float) $row['quoteVolume'] : null;
        $items[] = [
            'code' => $symbol,
            'price' => $price >= 100 ? number_format($price, 2, '.', ',') : number_format($price, $price >= 1 ? 2 : 4, '.', ','),
            'delta' => sprintf('%s%.2f%%', $changePercent >= 0 ? '+' : '', $changePercent),
            'change' => $changePercent >= 0 ? 1 : -1,
            'volume' => $quoteVolume !== null ? format_market_volume($quoteVolume) : '—',
            'spark' => $sparkByCode[$symbol] ?? default_market_spark_points($symbol),
        ];
    }

    if (!$items) {
        $coingecko = fetch_market_snapshot_coingecko();
        if (market_snapshot_items_complete($coingecko)) {
            write_cache_json($cacheKey, $coingecko);
            return $coingecko;
        }
        return is_array($stale) && $stale ? $stale : $default;
    }

    usort($items, static function (array $left, array $right): int {
        $order = ['BTC' => 0, 'ETH' => 1, 'XRP' => 2];
        return ($order[$left['code']] ?? 99) <=> ($order[$right['code']] ?? 99);
    });

    write_cache_json($cacheKey, $items);
    return $items;
}

function public_origin(): string
{
    $scheme = request_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    return $scheme . '://' . $host;
}

function request_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($candidates as $candidate) {
        $parts = array_map('trim', explode(',', (string) $candidate));
        foreach ($parts as $part) {
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_IP)) {
                return $part;
            }
        }
    }
    return '0.0.0.0';
}

function ip_in_cidr(string $ip, string $cidr): bool
{
    if (!str_contains($cidr, '/')) {
        return $ip === $cidr;
    }
    [$subnet, $maskBits] = explode('/', $cidr, 2);
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP)) {
        return false;
    }
    $maskBits = (int) $maskBits;
    $ipBinary = @inet_pton($ip);
    $subnetBinary = @inet_pton($subnet);
    if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
        return false;
    }
    $maxBits = strlen($ipBinary) * 8;
    if ($maskBits < 0 || $maskBits > $maxBits) {
        return false;
    }
    $fullBytes = intdiv($maskBits, 8);
    $remainingBits = $maskBits % 8;
    if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
        return false;
    }
    if ($remainingBits === 0) {
        return true;
    }
    $mask = ((0xFF00 >> $remainingBits) & 0xFF);
    return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);
}

function admin_allowed_ips(): array
{
    return array_values(array_filter(array_map('trim', explode(',', env_string('ADMIN_ALLOWED_IPS', '')))));
}

function enforce_admin_ip_allowlist(): void
{
    $allowlist = admin_allowed_ips();
    if ($allowlist === []) {
        return;
    }
    $ip = request_ip();
    foreach ($allowlist as $rule) {
        if (ip_in_cidr($ip, $rule)) {
            return;
        }
    }
    failure('ADMIN_IP_NOT_ALLOWED', 'Admin access from this IP is not allowed', ['ip' => $ip], 403);
}

function public_file_url(string $relativePath): string
{
    $normalized = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    return public_origin() . $normalized;
}

function uploads_dir(string $category = 'general'): string
{
    $base = ensure_storage_dir() . '/uploads/' . preg_replace('/[^a-z0-9_\-]/i', '-', $category);
    if (!is_dir($base)) {
        mkdir($base, 0750, true);
    }
    return $base;
}

function store_uploaded_file(array $file, string $category = 'general'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        failure('AUTH_INVALID_PARAMS', 'Upload failed');
    }
    $maxBytes = max(1024, (int) env_string('UPLOAD_MAX_BYTES', '5242880'));
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        failure('AUTH_INVALID_PARAMS', 'Upload size is invalid');
    }
    if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        failure('AUTH_INVALID_PARAMS', 'Upload payload is invalid');
    }
    $originalName = (string) ($file['name'] ?? 'upload.bin');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedMimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
    ];
    if (!isset($allowedMimeMap[$extension])) {
        failure('AUTH_INVALID_PARAMS', 'File type is not allowed');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($detectedMime !== $allowedMimeMap[$extension]) {
        failure('AUTH_INVALID_PARAMS', 'Upload MIME type is not allowed');
    }
    if ($detectedMime !== 'application/pdf' && @getimagesize((string) $file['tmp_name']) === false) {
        failure('AUTH_INVALID_PARAMS', 'Upload image is invalid');
    }
    $dir = uploads_dir($category) . '/' . gmdate('Ymd');
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $filename = substr(random_token(20), 0, 20) . ($extension ? '.' . $extension : '');
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        failure('AUTH_INVALID_PARAMS', 'Unable to store uploaded file');
    }
    chmod($target, 0640);
    $relativePath = str_replace(dirname(__DIR__), '', $target);
    return [
        'url' => public_file_url($relativePath),
        'path' => $relativePath,
        'filename' => $filename,
    ];
}

function delete_local_storage_file_by_url(?string $url): void
{
    $rawUrl = trim((string) $url);
    if ($rawUrl === '') {
        return;
    }
    $path = parse_url($rawUrl, PHP_URL_PATH);
    if (!is_string($path) || $path === '' || !str_starts_with($path, '/storage/')) {
        return;
    }
    $publicRoot = realpath(dirname(__DIR__));
    if ($publicRoot === false) {
        return;
    }
    $target = $publicRoot . $path;
    if (!is_file($target)) {
        return;
    }
    $targetReal = realpath($target);
    $storageRoot = realpath($publicRoot . '/storage');
    if ($targetReal === false || $storageRoot === false || !str_starts_with($targetReal, $storageRoot . DIRECTORY_SEPARATOR)) {
        return;
    }
    @unlink($targetReal);
}

function delete_local_storage_files(array $urls): void
{
    foreach (array_unique(array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $urls)))) as $url) {
        delete_local_storage_file_by_url($url);
    }
}

function get_system_config(PDO $pdo, string $group, string $key, ?string $default = null): ?string
{
    $stmt = $pdo->prepare('SELECT config_value FROM system_configs WHERE config_group = :config_group AND config_key = :config_key LIMIT 1');
    $stmt->execute([
        ':config_group' => $group,
        ':config_key' => $key,
    ]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function get_system_config_json(PDO $pdo, string $group, string $key, array $default = []): array
{
    $raw = get_system_config($pdo, $group, $key, '');
    if ($raw === null || $raw === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function set_system_config(PDO $pdo, string $group, string $key, mixed $value): void
{
    $encodedValue = is_bool($value) || is_array($value)
        ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : (string) $value;
    $existing = $pdo->prepare('SELECT id FROM system_configs WHERE config_group = :config_group AND config_key = :config_key LIMIT 1');
    $existing->execute([
        ':config_group' => $group,
        ':config_key' => $key,
    ]);
    if ($existing->fetchColumn()) {
        $pdo->prepare('UPDATE system_configs SET config_value = :config_value, updated_at = :updated_at
            WHERE config_group = :config_group AND config_key = :config_key')
            ->execute([
                ':config_value' => $encodedValue,
                ':updated_at' => now_iso(),
                ':config_group' => $group,
                ':config_key' => $key,
            ]);
        return;
    }

    $pdo->prepare('INSERT INTO system_configs (config_group, config_key, config_value, created_at, updated_at)
        VALUES (:config_group, :config_key, :config_value, :created_at, :updated_at)')
        ->execute([
            ':config_group' => $group,
            ':config_key' => $key,
            ':config_value' => $encodedValue,
            ':created_at' => now_iso(),
            ':updated_at' => now_iso(),
        ]);
}

function normalize_public_base_url(mixed $value, string $errorCode = 'ADMIN_SYSTEM_CONFIG_URL_INVALID'): string
{
    $normalized = rtrim(trim((string) $value), '/');
    if ($normalized === '') {
        return '';
    }
    if (!filter_var($normalized, FILTER_VALIDATE_URL)) {
        failure($errorCode, 'URL is invalid');
    }
    $parts = parse_url($normalized);
    if (!is_array($parts)) {
        failure($errorCode, 'URL is invalid');
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = trim((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        failure($errorCode, 'URL is invalid');
    }
    return $normalized;
}

function parse_public_base_url_list(mixed $value, bool $strict = false): array
{
    $items = [];
    if (is_array($value)) {
        $items = $value;
    } else {
        $raw = trim((string) $value);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/[\r\n,]+/', $raw) ?: [];
        }
    }

    $normalized = [];
    foreach ($items as $item) {
        $candidate = trim((string) $item);
        if ($candidate === '') {
            continue;
        }
        try {
            $url = normalize_public_base_url($candidate, 'ADMIN_SYSTEM_CONFIG_URL_LIST_INVALID');
        } catch (Throwable $error) {
            if ($strict) {
                throw $error;
            }
            continue;
        }
        $normalized[$url] = $url;
    }
    return array_values($normalized);
}

function site_config_payload(PDO $pdo): array
{
    $frontendBaseUrls = parse_public_base_url_list(get_system_config($pdo, 'site', 'frontend_base_urls', ''), false);
    return [
        'frontend_base_urls' => $frontendBaseUrls,
        'frontend_primary_base_url' => $frontendBaseUrls[0] ?? '',
        'api_public_base_url' => get_system_config($pdo, 'site', 'api_public_base_url', ''),
    ];
}

function normalize_admin_system_config_value(string $group, string $key, mixed $value): mixed
{
    if ($group === 'site' && $key === 'frontend_base_urls') {
        try {
            return parse_public_base_url_list($value, true);
        } catch (Throwable $error) {
            failure('ADMIN_SYSTEM_CONFIG_URL_LIST_INVALID', 'Frontend URL list is invalid');
        }
    }
    if ($group === 'site' && $key === 'api_public_base_url') {
        return normalize_public_base_url($value, 'ADMIN_SYSTEM_CONFIG_URL_INVALID');
    }
    return $value;
}

function project_root_path(string $relativePath = ''): string
{
    $root = dirname(__DIR__, 2);
    if ($relativePath === '') {
        return $root;
    }
    return $root . '/' . ltrim($relativePath, '/');
}

function project_relative_path(string $absolutePath): string
{
    $root = rtrim(project_root_path(), '/');
    if (str_starts_with($absolutePath, $root . '/')) {
        return substr($absolutePath, strlen($root) + 1);
    }
    return $absolutePath;
}

function read_text_file_contents(string $path, bool $required = true): ?string
{
    if (!is_file($path)) {
        if ($required) {
            throw new RuntimeException('Config file not found: ' . project_relative_path($path));
        }
        return null;
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        if ($required) {
            throw new RuntimeException('Config file is not readable: ' . project_relative_path($path));
        }
        return null;
    }
    return $contents;
}

function backup_config_file_contents(string $path, bool $required = true): ?array
{
    $contents = read_text_file_contents($path, $required);
    if ($contents === null) {
        return null;
    }
    return [
        'path' => $path,
        'relative_path' => project_relative_path($path),
        'contents' => $contents,
    ];
}

function write_text_file_contents(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        throw new RuntimeException('Config directory not found: ' . project_relative_path($directory));
    }
    $tempPath = $path . '.tmp.' . bin2hex(random_bytes(6));
    $written = @file_put_contents($tempPath, $contents, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('Config file is not writable: ' . project_relative_path($path));
    }
    if (!@rename($tempPath, $path)) {
        @unlink($tempPath);
        throw new RuntimeException('Failed to replace config file: ' . project_relative_path($path));
    }
}

function read_env_key_from_contents(string $contents, string $key): string
{
    if (!preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)$/m', $contents, $matches)) {
        return '';
    }
    $value = trim((string) ($matches[1] ?? ''));
    if (
        strlen($value) >= 2
        && (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        )
    ) {
        return substr($value, 1, -1);
    }
    return $value;
}

function write_env_key_to_contents(string $contents, string $key, string $value): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $contents);
    $replacement = $key . '=' . $value;
    $pattern = '/^\s*' . preg_quote($key, '/') . '\s*=.*$/m';
    if (preg_match($pattern, $normalized)) {
        $updated = preg_replace($pattern, $replacement, $normalized, 1);
        return $updated === null ? $normalized : $updated;
    }
    if ($normalized !== '' && !str_ends_with($normalized, "\n")) {
        $normalized .= "\n";
    }
    return $normalized . $replacement . "\n";
}

function parse_csv_config_list(string $value): array
{
    $items = array_map('trim', explode(',', $value));
    $normalized = [];
    foreach ($items as $item) {
        if ($item === '') {
            continue;
        }
        $normalized[$item] = $item;
    }
    return array_values($normalized);
}

function restore_config_file_backups(array $backups): void
{
    foreach (array_reverse($backups) as $backup) {
        $path = (string) ($backup['path'] ?? '');
        $contents = $backup['contents'] ?? null;
        if ($path === '' || !is_string($contents)) {
            continue;
        }
        write_text_file_contents($path, $contents);
    }
}

function public_url_origin(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }
    $scheme = strtolower(trim((string) ($parts['scheme'] ?? '')));
    $host = strtolower(trim((string) ($parts['host'] ?? '')));
    if ($scheme === '' || $host === '') {
        return '';
    }
    $origin = $scheme . '://' . $host;
    if (isset($parts['port']) && (int) $parts['port'] > 0) {
        $origin .= ':' . (int) $parts['port'];
    }
    return $origin;
}

function public_url_origin_list(array $urls): array
{
    $origins = [];
    foreach ($urls as $url) {
        $origin = public_url_origin((string) $url);
        if ($origin === '') {
            continue;
        }
        $origins[$origin] = $origin;
    }
    return array_values($origins);
}

function sync_frontend_origin_env_file(string $path, array $previousFrontendUrls, array $nextFrontendUrls, bool $required = true): ?string
{
    $contents = read_text_file_contents($path, $required);
    if ($contents === null) {
        return null;
    }
    $currentAllowedOrigins = parse_csv_config_list(read_env_key_from_contents($contents, 'API_ALLOWED_ORIGINS'));
    $previousOrigins = public_url_origin_list($previousFrontendUrls);
    $nextOrigins = public_url_origin_list($nextFrontendUrls);
    $remainingOrigins = [];
    foreach ($currentAllowedOrigins as $origin) {
        if (in_array($origin, $previousOrigins, true)) {
            continue;
        }
        $remainingOrigins[$origin] = $origin;
    }
    foreach ($nextOrigins as $origin) {
        $remainingOrigins[$origin] = $origin;
    }
    $updated = write_env_key_to_contents($contents, 'API_ALLOWED_ORIGINS', implode(',', array_values($remainingOrigins)));
    write_text_file_contents($path, $updated);
    return project_relative_path($path);
}

function derive_admin_runtime_config_from_api_url(string $publicApiUrl): array
{
    $parts = parse_url($publicApiUrl);
    if (!is_array($parts)) {
        throw new RuntimeException('API public base URL is invalid');
    }
    $scheme = strtolower(trim((string) ($parts['scheme'] ?? '')));
    $host = trim((string) ($parts['host'] ?? ''));
    if ($scheme === '' || $host === '') {
        throw new RuntimeException('API public base URL is invalid');
    }
    $base = $scheme . '://' . $host;
    if (isset($parts['port']) && (int) $parts['port'] > 0) {
        $base .= ':' . (int) $parts['port'];
    }
    $path = trim((string) ($parts['path'] ?? ''));
    $path = preg_replace('#/api/?$#i', '', $path) ?? $path;
    $path = rtrim($path, '/');
    return [
        'apiBase' => $base,
        'apiBasePath' => $path === '' ? '' : (str_starts_with($path, '/') ? $path : '/' . $path),
    ];
}

function read_admin_runtime_env_name(string $contents, string $default = 'production'): string
{
    if (preg_match('/envName\s*:\s*([\'"])(.*?)\1/', $contents, $matches)) {
        $value = trim((string) ($matches[2] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return $default;
}

function build_admin_runtime_config_contents(string $apiBase, string $apiBasePath, string $envName): string
{
    $apiBase = rtrim($apiBase, '/');
    $path = trim((string) $apiBasePath);
    if ($path !== '' && $path !== '/') {
        $apiBaseUrl = $apiBase . (str_starts_with($path, '/') ? $path : '/' . $path);
    } else {
        $apiBaseUrl = $apiBase;
    }
    $apiBaseUrl = rtrim($apiBaseUrl, '/');

    return "window.__ADMIN_RUNTIME_CONFIG__ = {\n"
        . '  apiBaseUrl: ' . json_encode($apiBaseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ",\n"
        . '  envName: ' . json_encode($envName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ",\n"
        . "  prototypeUi: false\n"
        . "};\n";
}

function sync_admin_runtime_config_file(string $path, string $publicApiUrl, bool $required = true, string $defaultEnvName = 'production'): ?string
{
    $contents = read_text_file_contents($path, $required);
    if ($contents === null) {
        return null;
    }
    $runtimeConfig = derive_admin_runtime_config_from_api_url($publicApiUrl);
    $envName = read_admin_runtime_env_name($contents, $defaultEnvName);
    $updated = build_admin_runtime_config_contents($runtimeConfig['apiBase'], $runtimeConfig['apiBasePath'], $envName);
    write_text_file_contents($path, $updated);
    return project_relative_path($path);
}

function sync_frontend_api_env_file(string $path, string $publicApiUrl, bool $required = false): ?string
{
    $contents = read_text_file_contents($path, $required);
    if ($contents === null) {
        return null;
    }
    $updated = write_env_key_to_contents($contents, 'VUE_APP_API_BASE_URL', $publicApiUrl);
    $updated = write_env_key_to_contents($updated, 'REAL_API_BASE', $publicApiUrl);
    write_text_file_contents($path, $updated);
    return project_relative_path($path);
}

function sync_system_config_to_files(string $group, string $key, mixed $previousValue, mixed $nextValue, array &$fileBackups = []): array
{
    $syncedFiles = [];
    if ($group === 'site' && $key === 'frontend_base_urls') {
        $previousUrls = parse_public_base_url_list($previousValue, false);
        $nextUrls = is_array($nextValue) ? $nextValue : parse_public_base_url_list($nextValue, false);
        foreach ([
            ['path' => project_root_path('backend-api/.env'), 'required' => true],
            ['path' => project_root_path('backend-api/.env.example'), 'required' => false],
        ] as $target) {
            $backup = backup_config_file_contents($target['path'], $target['required']);
            if ($backup !== null) {
                $fileBackups[] = $backup;
            }
            $synced = sync_frontend_origin_env_file($target['path'], $previousUrls, $nextUrls, $target['required']);
            if ($synced !== null) {
                $syncedFiles[] = $synced;
            }
        }
        return $syncedFiles;
    }
    if ($group === 'site' && $key === 'api_public_base_url') {
        $publicApiUrl = trim((string) $nextValue);
        foreach ([
            ['path' => project_root_path('admin-web/runtime-config.js'), 'required' => true, 'env_name' => 'production-local-template'],
            ['path' => project_root_path('admin-web/runtime-config.example.js'), 'required' => false, 'env_name' => 'production'],
        ] as $target) {
            $backup = backup_config_file_contents($target['path'], $target['required']);
            if ($backup !== null) {
                $fileBackups[] = $backup;
            }
            $synced = sync_admin_runtime_config_file($target['path'], $publicApiUrl, $target['required'], $target['env_name']);
            if ($synced !== null) {
                $syncedFiles[] = $synced;
            }
        }
        foreach ([
            ['path' => project_root_path('frontend-uniapp/.env.example'), 'required' => false],
            ['path' => project_root_path('frontend-uniapp/.env.production.local.example'), 'required' => false],
        ] as $target) {
            $backup = backup_config_file_contents($target['path'], $target['required']);
            if ($backup !== null) {
                $fileBackups[] = $backup;
            }
            $synced = sync_frontend_api_env_file($target['path'], $publicApiUrl, $target['required']);
            if ($synced !== null) {
                $syncedFiles[] = $synced;
            }
        }
        return $syncedFiles;
    }
    return $syncedFiles;
}

function is_deprecated_system_config_key(string $group, string $key): bool
{
    return $group === 'finance' && in_array($key, [
        'usdt_trc20_deposit_address',
        'usdt_trc20_network_label',
    ], true);
}

/** 全站 USDT 充值預設地址之 system_configs 鍵名（僅大老板可 PATCH）。 */
function is_usdt_platform_deposit_system_config(string $group, string $key): bool
{
    return $group === 'finance' && in_array($key, [
        'usdt_platform_deposit_trc20',
        'usdt_platform_deposit_erc20',
        'usdt_platform_deposit_bep20',
    ], true);
}

function normalize_home_banner_status(mixed $value): string
{
    $status = trim((string) $value);
    return $status === 'inactive' ? 'inactive' : 'active';
}

function home_banner_list(PDO $pdo): array
{
    $items = get_system_config_json($pdo, 'home', 'banners', []);
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $bannerId = (int) ($item['id'] ?? 0);
        if ($bannerId <= 0) {
            continue;
        }
        $normalized[] = [
            'id' => $bannerId,
            'title' => trim((string) ($item['title'] ?? '')),
            'subtitle' => trim((string) ($item['subtitle'] ?? '')),
            'badge_text' => trim((string) ($item['badge_text'] ?? '')),
            'image_url' => trim((string) ($item['image_url'] ?? '')),
            'link_url' => trim((string) ($item['link_url'] ?? '')),
            'sort_order' => (int) ($item['sort_order'] ?? 0),
            'status' => normalize_home_banner_status($item['status'] ?? 'active'),
            'created_at' => trim((string) ($item['created_at'] ?? '')),
            'updated_at' => trim((string) ($item['updated_at'] ?? '')),
        ];
    }

    usort($normalized, static function (array $left, array $right): int {
        $sortCompare = ($left['sort_order'] <=> $right['sort_order']);
        if ($sortCompare !== 0) {
            return $sortCompare;
        }
        return $right['id'] <=> $left['id'];
    });

    return array_values($normalized);
}

function save_home_banner_list(PDO $pdo, array $items): void
{
    set_system_config($pdo, 'home', 'banners', array_values($items));
}

function normalize_home_tutorial_link_status(mixed $value): string
{
    $status = trim((string) $value);

    return $status === 'inactive' ? 'inactive' : 'active';
}

function home_tutorial_link_list(PDO $pdo): array
{
    $items = get_system_config_json($pdo, 'home', 'tutorial_links', []);
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $linkId = (int) ($item['id'] ?? 0);
        if ($linkId <= 0) {
            continue;
        }
        $normalized[] = [
            'id' => $linkId,
            'title' => trim((string) ($item['title'] ?? '')),
            'subtitle' => trim((string) ($item['subtitle'] ?? '')),
            'link_url' => trim((string) ($item['link_url'] ?? '')),
            'sort_order' => (int) ($item['sort_order'] ?? 0),
            'status' => normalize_home_tutorial_link_status($item['status'] ?? 'active'),
            'created_at' => trim((string) ($item['created_at'] ?? '')),
            'updated_at' => trim((string) ($item['updated_at'] ?? '')),
        ];
    }
    usort($normalized, static function (array $left, array $right): int {
        $sortCompare = ($left['sort_order'] <=> $right['sort_order']);
        if ($sortCompare !== 0) {
            return $sortCompare;
        }

        return $right['id'] <=> $left['id'];
    });

    return array_values($normalized);
}

function save_home_tutorial_link_list(PDO $pdo, array $items): void
{
    set_system_config($pdo, 'home', 'tutorial_links', array_values($items));
}

function normalize_deposit_asset_code(mixed $value): string
{
    $assetCode = strtoupper(trim((string) $value));
    return $assetCode === '' ? 'USDT' : $assetCode;
}

function normalize_deposit_network_code(mixed $value): string
{
    $networkCode = strtoupper(trim((string) $value));
    if ($networkCode === '') {
        failure('DEPOSIT_ADDRESS_NETWORK_REQUIRED', 'Network code is required');
    }
    if (!preg_match('/^[A-Z0-9_-]{2,24}$/', $networkCode)) {
        failure('DEPOSIT_ADDRESS_NETWORK_INVALID', 'Network code is invalid');
    }
    return $networkCode;
}

function normalize_deposit_address_status(mixed $value): string
{
    $status = trim((string) $value);
    if ($status === '') {
        return 'enabled';
    }
    if (!in_array($status, ['enabled', 'disabled'], true)) {
        failure('DEPOSIT_ADDRESS_STATUS_INVALID', 'Deposit address status is invalid');
    }
    return $status;
}

function normalize_deposit_address_user_id(mixed $value): int
{
    $userId = (int) $value;
    if ($userId <= 0) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }
    return $userId;
}

function sanitize_deposit_address_payload(array $input, bool $isCreate = true): array
{
    $assetCode = normalize_deposit_asset_code($input['asset_code'] ?? 'USDT');
    $networkCode = normalize_deposit_network_code($input['network_code'] ?? '');
    $networkLabel = trim((string) ($input['network_label'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));
    $remark = trim((string) ($input['remark'] ?? ''));
    $status = normalize_deposit_address_status($input['status'] ?? 'enabled');

    if ($networkLabel === '') {
        $networkLabel = $assetCode . '(' . $networkCode . ')';
    }
    if ($address === '') {
        failure('DEPOSIT_ADDRESS_VALUE_REQUIRED', 'Deposit address is required');
    }
    if (mb_strlen($networkLabel) > 64) {
        failure('DEPOSIT_ADDRESS_NETWORK_LABEL_INVALID', 'Network label is too long');
    }
    if (mb_strlen($address) > 255) {
        failure('DEPOSIT_ADDRESS_VALUE_INVALID', 'Deposit address is too long');
    }
    if (mb_strlen($remark) > 255) {
        failure('DEPOSIT_ADDRESS_REMARK_INVALID', 'Remark is too long');
    }

    return [
        'asset_code' => $assetCode,
        'network_code' => $networkCode,
        'network_label' => $networkLabel,
        'address' => $address,
        'qr_code_url' => null,
        'remark' => $remark !== '' ? $remark : null,
        'status' => $status,
    ];
}

function sanitize_user_deposit_address_payload(array $input): array
{
    $userId = normalize_deposit_address_user_id($input['user_id'] ?? 0);
    $addresses = [
        'TRC20' => trim((string) ($input['trc20_address'] ?? '')),
        'ERC20' => trim((string) ($input['erc20_address'] ?? '')),
        'BEP20' => trim((string) ($input['bep20_address'] ?? '')),
    ];
    foreach ($addresses as $networkCode => $address) {
        if ($address !== '' && mb_strlen($address) > 255) {
            failure('DEPOSIT_ADDRESS_VALUE_INVALID', 'Deposit address is too long');
        }
    }
    if ($addresses['TRC20'] === '' && $addresses['ERC20'] === '' && $addresses['BEP20'] === '') {
        failure('DEPOSIT_ADDRESS_VALUE_REQUIRED', 'Deposit address is required');
    }
    return [
        'user_id' => $userId,
        'asset_code' => 'USDT',
        'addresses' => $addresses,
    ];
}

function deposit_qr_code_url(?string $address): ?string
{
    return null;
}

function serialize_deposit_address(array $item): array
{
    return [
        'id' => (int) $item['id'],
        'user_id' => isset($item['user_id']) ? (int) $item['user_id'] : null,
        'asset_code' => (string) $item['asset_code'],
        'network_code' => (string) $item['network_code'],
        'network_label' => (string) $item['network_label'],
        'address' => (string) $item['address'],
        'qr_code_url' => deposit_qr_code_url($item['address'] ?? ''),
        'remark' => $item['remark'] ?: null,
        'status' => (string) $item['status'],
        'network_enabled_count' => isset($item['network_enabled_count']) ? (int) $item['network_enabled_count'] : 0,
        'created_at' => (string) $item['created_at'],
        'updated_at' => (string) $item['updated_at'],
    ];
}

function admin_user_source_label(mixed $invitedByAdminId, int $userId): string
{
    if ($userId <= 0) {
        return 'agent';
    }
    return $invitedByAdminId !== null && (int) $invitedByAdminId > 0 ? 'agent' : 'user';
}

/** 全站 USDT 充值預設地址（system_configs）；與後台「平台預設充值地址」對齊，供無 per-user 記錄時回退。 */
function platform_default_usdt_deposit_address(PDO $pdo, string $networkCode): string
{
    $code = strtoupper(trim($networkCode));
    if (!in_array($code, ['TRC20', 'ERC20', 'BEP20'], true)) {
        $code = 'TRC20';
    }
    $key = match ($code) {
        'TRC20' => 'usdt_platform_deposit_trc20',
        'ERC20' => 'usdt_platform_deposit_erc20',
        'BEP20' => 'usdt_platform_deposit_bep20',
    };
    $addr = trim((string) get_system_config($pdo, 'finance', $key, ''));
    if ($addr === '' && $code === 'TRC20') {
        $addr = trim((string) get_system_config($pdo, 'finance', 'usdt_trc20_deposit_address', ''));
    }

    return $addr;
}

/** 合成一筆與 deposit_addresses 相同欄位結構，供 serialize_deposit_address（平台預設、非 DB 列）。 */
function synthetic_platform_deposit_row(string $networkCode, string $address): array
{
    $code = strtoupper(trim($networkCode));
    $now = now_iso();

    return [
        'id' => 0,
        'user_id' => null,
        'asset_code' => 'USDT',
        'network_code' => $code,
        'network_label' => 'USDT(' . $code . ')',
        'address' => $address,
        'qr_code_url' => null,
        'remark' => null,
        'status' => 'enabled',
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

function user_enabled_deposit_addresses(PDO $pdo, int $userId, string $assetCode = 'USDT'): array
{
    $stmt = $pdo->prepare('SELECT *
        FROM deposit_addresses
        WHERE user_id = :user_id AND asset_code = :asset_code AND status = "enabled"
        ORDER BY CASE network_code
            WHEN "TRC20" THEN 0
            WHEN "ERC20" THEN 1
            WHEN "BEP20" THEN 2
            ELSE 99
        END ASC, id ASC');
    $stmt->execute([
        ':user_id' => $userId,
        ':asset_code' => strtoupper($assetCode),
    ]);
    return $stmt->fetchAll();
}

function build_user_deposit_address_item(PDO $pdo, int $userId, ?array $rows = null): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, email, mobile_e164 FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }
    $addressRows = $rows ?? user_enabled_deposit_addresses($pdo, $userId, 'USDT');
    $item = [
        'user_id' => $userId,
        'username' => $user['username'] ?? null,
        'email' => $user['email'] ?? null,
        'mobile_e164' => $user['mobile_e164'] ?? null,
        'trc20_address' => '',
        'erc20_address' => '',
        'bep20_address' => '',
        'created_at' => null,
        'updated_at' => null,
    ];
    foreach ($addressRows ?: [] as $row) {
        $networkCode = strtoupper((string) ($row['network_code'] ?? ''));
        if ($networkCode === 'TRC20') {
            $item['trc20_address'] = (string) ($row['address'] ?? '');
        } elseif ($networkCode === 'ERC20') {
            $item['erc20_address'] = (string) ($row['address'] ?? '');
        } elseif ($networkCode === 'BEP20') {
            $item['bep20_address'] = (string) ($row['address'] ?? '');
        }
        $createdAt = (string) ($row['created_at'] ?? '');
        $updatedAt = (string) ($row['updated_at'] ?? '');
        if ($createdAt !== '' && ($item['created_at'] === null || $createdAt < $item['created_at'])) {
            $item['created_at'] = $createdAt;
        }
        if ($updatedAt !== '' && ($item['updated_at'] === null || $updatedAt > $item['updated_at'])) {
            $item['updated_at'] = $updatedAt;
        }
    }
    return $item;
}

function save_user_deposit_addresses(PDO $pdo, int $userId, array $addresses, int $adminUserId): array
{
    require_user_exists($pdo, $userId);
    $existingStmt = $pdo->prepare('SELECT * FROM deposit_addresses WHERE user_id = :user_id AND asset_code = "USDT"');
    $existingStmt->execute([':user_id' => $userId]);
    $existingRows = $existingStmt->fetchAll();
    $existingByNetwork = [];
    foreach ($existingRows as $row) {
        $existingByNetwork[strtoupper((string) $row['network_code'])] = $row;
    }
    $now = now_iso();
    foreach (['TRC20', 'ERC20', 'BEP20'] as $networkCode) {
        $address = trim((string) ($addresses[$networkCode] ?? ''));
        $existing = $existingByNetwork[$networkCode] ?? null;
        if ($address === '') {
            if ($existing) {
                $pdo->prepare('DELETE FROM deposit_addresses WHERE id = :id')->execute([':id' => (int) $existing['id']]);
            }
            continue;
        }
        $duplicateStmt = $pdo->prepare('SELECT id FROM deposit_addresses
            WHERE asset_code = "USDT" AND network_code = :network_code AND address = :address AND id != :id
            LIMIT 1');
        $duplicateStmt->execute([
            ':network_code' => $networkCode,
            ':address' => $address,
            ':id' => $existing ? (int) $existing['id'] : 0,
        ]);
        if ($duplicateStmt->fetch()) {
            failure('ADMIN_DEPOSIT_ADDRESS_DUPLICATE', 'Deposit address already exists');
        }
        $networkLabel = 'USDT(' . $networkCode . ')';
        if ($existing) {
            $pdo->prepare('UPDATE deposit_addresses SET
                user_id = :user_id,
                asset_code = "USDT",
                network_code = :network_code,
                network_label = :network_label,
                address = :address,
                qr_code_url = null,
                status = "enabled",
                updated_at = :updated_at
                WHERE id = :id')->execute([
                ':user_id' => $userId,
                ':network_code' => $networkCode,
                ':network_label' => $networkLabel,
                ':address' => $address,
                ':updated_at' => $now,
                ':id' => (int) $existing['id'],
            ]);
        } else {
            $pdo->prepare('INSERT INTO deposit_addresses (
                user_id, asset_code, network_code, network_label, address, qr_code_url, remark, status, created_at, updated_at
            ) VALUES (
                :user_id, "USDT", :network_code, :network_label, :address, null, null, "enabled", :created_at, :updated_at
            )')->execute([
                ':user_id' => $userId,
                ':network_code' => $networkCode,
                ':network_label' => $networkLabel,
                ':address' => $address,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }
    $item = build_user_deposit_address_item($pdo, $userId);
    if (!$item) {
        failure('DEPOSIT_ADDRESS_VALUE_REQUIRED', 'Deposit address is required');
    }
    audit(
        $pdo,
        'admin',
        'deposit_address_saved',
        'admin',
        $adminUserId,
        'deposit_address_user',
        $userId,
        null,
        null,
        $item
    );
    return $item;
}

function enabled_deposit_networks(PDO $pdo, string $assetCode = 'USDT'): array
{
    $stmt = $pdo->prepare('SELECT network_code, MAX(network_label) AS network_label, COUNT(*) AS enabled_count
        FROM deposit_addresses
        WHERE asset_code = :asset_code AND status = "enabled"
        GROUP BY network_code
        ORDER BY CASE network_code
            WHEN "TRC20" THEN 0
            WHEN "ERC20" THEN 1
            WHEN "BEP20" THEN 2
            ELSE 99
        END ASC, network_code ASC');
    $stmt->execute([':asset_code' => strtoupper($assetCode)]);
    return array_map(static function (array $item): array {
        return [
            'network_code' => (string) $item['network_code'],
            'network_label' => (string) $item['network_label'],
            'enabled_count' => (int) $item['enabled_count'],
        ];
    }, $stmt->fetchAll());
}

function db_identifier_cast_expr(string $expr): string
{
    return "CAST({$expr} AS CHAR)";
}

function db_balance_total_expr(): string
{
    return "COALESCE(CAST(ROUND(SUM(CAST(w.available_balance AS DECIMAL(24,8)) + CAST(w.reserved_balance AS DECIMAL(24,8))), 8) AS CHAR), '0.00000000')";
}

function db_balance_available_sum_expr(): string
{
    return "COALESCE(CAST(ROUND(SUM(CAST(w.available_balance AS DECIMAL(24,8))), 8) AS CHAR), '0.00000000')";
}

function db_balance_reserved_sum_expr(): string
{
    return "COALESCE(CAST(ROUND(SUM(CAST(w.reserved_balance AS DECIMAL(24,8))), 8) AS CHAR), '0.00000000')";
}

function db_due_datetime_condition(string $column): string
{
    return "STR_TO_DATE(REPLACE(SUBSTRING_INDEX({$column}, '+', 1), 'T', ' '), '%Y-%m-%d %H:%i:%s') <= UTC_TIMESTAMP()";
}

function db_table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);
    return (int) $stmt->fetchColumn() > 0;
}

function db_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (db_table_has_column($pdo, $table, $column)) {
        return;
    }
    $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
}

function mysql_bootstrap_database(): PDO
{
    $host = env_string('DB_HOST', '127.0.0.1');
    $port = env_string('DB_PORT', '3306');
    $name = env_string('DB_NAME', 'eurnyse_c2c');
    $user = env_string('DB_USER', 'eurnyse_c2c');
    $password = env_string('DB_PASSWORD', '');

    $bootstrap = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $bootstrap->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    return $bootstrap;
}

function pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    mysql_bootstrap_database();
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

    ensure_schema($pdo);
    migrate_legacy_eur_wallet_code($pdo);
    migrate_trade_feed_action_types($pdo);
    cleanup_legacy_global_deposit_addresses($pdo);
    seed_data($pdo);

    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    ensure_schema_mysql($pdo);
}

function ensure_schema_mysql(PDO $pdo): void
{
    foreach (mysql_schema_queries() as $query) {
        $pdo->exec($query);
    }

    foreach (mysql_schema_missing_columns() as $table => $columns) {
        foreach ($columns as $column => $definition) {
            db_add_column_if_missing($pdo, $table, $column, $definition);
        }
    }
}

function normalize_tier_level(mixed $value, int $default = 1): int
{
    $level = is_numeric($value) ? (int) $value : $default;
    if ($level < 1) {
        $level = 1;
    }
    if ($level > 3) {
        $level = 3;
    }
    return $level;
}

function tier_group_code_for_level(int $level): string
{
    return match (normalize_tier_level($level)) {
        1 => 'basic',
        2 => 'vip',
        default => 'vip_plus',
    };
}

function tier_daily_sell_limit_for_level(int $level): ?int
{
    return match (normalize_tier_level($level)) {
        1 => 1,
        2 => 2,
        default => null,
    };
}

function migrate_legacy_eur_wallet_code(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT DISTINCT user_id FROM user_wallet_balances WHERE wallet_code IN ("usd", "eur") ORDER BY user_id ASC');
    $userIds = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!$userIds) {
        $pdo->prepare('UPDATE withdrawal_requests SET source_wallet_code = "eur" WHERE source_wallet_code = "usd"')->execute();
        return;
    }

    $pdo->beginTransaction();
    try {
        foreach ($userIds as $userIdValue) {
            $userId = (int) $userIdValue;
            $walletStmt = $pdo->prepare('SELECT * FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code IN ("usd", "eur") ORDER BY id ASC');
            $walletStmt->execute([':user_id' => $userId]);
            $wallets = $walletStmt->fetchAll();
            $usdWallet = null;
            $eurWallet = null;

            foreach ($wallets as $wallet) {
                if (($wallet['wallet_code'] ?? '') === 'usd') {
                    $usdWallet = $wallet;
                } elseif (($wallet['wallet_code'] ?? '') === 'eur') {
                    $eurWallet = $wallet;
                }
            }

            if (!$usdWallet) {
                if ($eurWallet) {
                    $pdo->prepare('UPDATE user_wallet_balances SET currency_code = "EUR" WHERE id = :id')
                        ->execute([':id' => (int) $eurWallet['id']]);
                }
                continue;
            }

            if ($eurWallet) {
                $pdo->prepare('UPDATE user_wallet_balances
                    SET available_balance = :available_balance, reserved_balance = :reserved_balance, currency_code = "EUR", updated_at = :updated_at
                    WHERE id = :id')
                    ->execute([
                        ':available_balance' => number_format((float) $eurWallet['available_balance'] + (float) $usdWallet['available_balance'], 8, '.', ''),
                        ':reserved_balance' => number_format((float) $eurWallet['reserved_balance'] + (float) $usdWallet['reserved_balance'], 8, '.', ''),
                        ':updated_at' => now_iso(),
                        ':id' => (int) $eurWallet['id'],
                    ]);
                $pdo->prepare('DELETE FROM user_wallet_balances WHERE id = :id')->execute([':id' => (int) $usdWallet['id']]);
            } else {
                $pdo->prepare('UPDATE user_wallet_balances
                    SET wallet_code = "eur", currency_code = "EUR", updated_at = :updated_at
                    WHERE id = :id')
                    ->execute([
                        ':updated_at' => now_iso(),
                        ':id' => (int) $usdWallet['id'],
                    ]);
            }
        }

        $pdo->prepare('UPDATE withdrawal_requests SET source_wallet_code = "eur" WHERE source_wallet_code = "usd"')->execute();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function normalize_trade_feed_action_type(mixed $value): string
{
    $normalized = trim(mb_strtolower((string) $value));
    return match ($normalized) {
        'completed_buy', 'complete_buy', 'buy_completed' => 'completed_buy',
        'completed_sell', 'complete_sell', 'sell_completed' => 'completed_sell',
        default => 'custom',
    };
}

function infer_trade_feed_action_type_from_title(string $title): string
{
    $normalized = trim(mb_strtolower($title));
    return match ($normalized) {
        '完成买入', '完成買入', '买入完成', '買入完成', 'completed buy' => 'completed_buy',
        '完成卖出', '完成賣出', '卖出完成', '賣出完成', 'completed sell', 'completed sale' => 'completed_sell',
        default => 'custom',
    };
}

function trade_feed_action_default_title(string $actionType): string
{
    return match (normalize_trade_feed_action_type($actionType)) {
        'completed_buy' => 'Completed buy',
        'completed_sell' => 'Completed sell',
        default => '',
    };
}

function trade_feed_action_label(string $actionType, string $lang = 'eng'): string
{
    $langCode = lang_map($lang);
    $normalized = normalize_trade_feed_action_type($actionType);
    if ($langCode === 'eng') {
        return match ($normalized) {
            'completed_buy' => 'Completed buy',
            'completed_sell' => 'Completed sell',
            default => 'Custom',
        };
    }
    return match ($normalized) {
        'completed_buy' => '完成買入',
        'completed_sell' => '完成賣出',
        default => '自定義',
    };
}

function trade_feed_display_title(array $item, string $lang = 'eng'): string
{
    $actionType = normalize_trade_feed_action_type($item['action_type'] ?? infer_trade_feed_action_type_from_title((string) ($item['title'] ?? '')));
    if ($actionType === 'custom') {
        $rawTitle = trim((string) ($item['title'] ?? ''));
        return $rawTitle !== '' ? $rawTitle : trade_feed_action_label('custom', $lang);
    }
    return trade_feed_action_label($actionType, $lang);
}

function random_trade_feed_email(): string
{
    $countryDomainPools = [
        ['gmail.com', 'outlook.com', 'yahoo.gr'],
        ['gmail.com', 'outlook.com', 'hotmail.nl', 'live.nl'],
        ['gmail.com', 'outlook.com', 'proximus.be', 'skynet.be'],
        ['gmail.com', 'outlook.com', 'orange.fr', 'free.fr', 'laposte.net'],
        ['gmail.com', 'outlook.com', 'hotmail.es', 'yahoo.es'],
        ['gmail.com', 'outlook.com', 'freemail.hu'],
        ['gmail.com', 'outlook.com', 'libero.it', 'virgilio.it'],
        ['gmail.com', 'outlook.com', 'gmx.at'],
        ['gmail.com', 'outlook.com', 'hotmail.co.uk', 'yahoo.co.uk'],
        ['gmail.com', 'outlook.com', 'mail.dk'],
        ['gmail.com', 'outlook.com', 'hotmail.se'],
        ['gmail.com', 'outlook.com', 'wp.pl', 'onet.pl'],
        ['gmail.com', 'outlook.com', 'gmx.de', 'web.de'],
        ['gmail.com', 'outlook.com', 'bigpond.com'],
        ['gmail.com', 'outlook.com', 'yahoo.com.ph'],
        ['gmail.com', 'outlook.com', 'yahoo.com.sg'],
        ['gmail.com', 'outlook.com', 'yahoo.co.th'],
        ['gmail.com', 'outlook.com', 'yahoo.co.jp'],
        ['gmail.com', 'outlook.com', 'naver.com', 'daum.net'],
        ['gmail.com', 'outlook.com', 'yahoo.com', 'zoho.com'],
        ['gmail.com', 'outlook.com', '163.com'],
        ['gmail.com', 'outlook.com', 'yahoo.in', 'rediffmail.com'],
        ['gmail.com', 'outlook.com', 'yahoo.com'],
        ['gmail.com', 'outlook.com', 'yahoo.com'],
        ['gmail.com', 'outlook.com', 'sapo.pt'],
        ['gmail.com', 'outlook.com', 'pt.lu'],
        ['gmail.com', 'outlook.com', 'eircom.net'],
        ['gmail.com', 'outlook.com', 'luukku.com'],
        ['gmail.com', 'outlook.com', 'yahoo.com.hk'],
        ['gmail.com', 'outlook.com', 'yahoo.com.hk'],
        ['gmail.com', 'outlook.com', 'yahoo.com'],
    ];
    $prefixSeeds = ['nova', 'prime', 'atlas', 'luna', 'river', 'orbit', 'cedar', 'pixel', 'mango', 'silver', 'amber', 'swift'];
    $suffixSeeds = ['flow', 'desk', 'trade', 'mail', 'hub', 'node', 'line', 'box', 'zone', 'wave'];
    $domainPool = $countryDomainPools[random_int(0, count($countryDomainPools) - 1)];
    $domain = $domainPool[random_int(0, count($domainPool) - 1)];
    $local = $prefixSeeds[random_int(0, count($prefixSeeds) - 1)]
        . '.'
        . $suffixSeeds[random_int(0, count($suffixSeeds) - 1)]
        . random_int(100, 9999);
    return $local . '@' . $domain;
}

function parse_tronscan_usdt_transfer_amount(array $transfer): ?float
{
    $amountValue = $transfer['amount_str'] ?? $transfer['amount'] ?? null;
    if ($amountValue !== null && is_numeric($amountValue)) {
        $amount = (float) $amountValue;
        return $amount > 0 ? $amount : null;
    }

    $quant = $transfer['quant'] ?? $transfer['value'] ?? null;
    if ($quant === null || !is_numeric($quant)) {
        return null;
    }

    $tokenInfo = is_array($transfer['tokenInfo'] ?? null) ? $transfer['tokenInfo'] : [];
    $decimals = (int) ($tokenInfo['tokenDecimal'] ?? $transfer['decimals'] ?? 6);
    $divisor = $decimals > 0 ? (10 ** min($decimals, 18)) : 1;
    $amount = ((float) $quant) / $divisor;
    return $amount > 0 ? $amount : null;
}

function parse_tronscan_transfer_timestamp_ms(array $transfer): ?int
{
    $timestamp = $transfer['block_ts'] ?? $transfer['timestamp'] ?? $transfer['block_timestamp'] ?? $transfer['time'] ?? null;
    if ($timestamp === null || !is_numeric($timestamp)) {
        return null;
    }
    $timestampMs = (int) $timestamp;
    if ($timestampMs < 100000000000) {
        $timestampMs *= 1000;
    }
    return $timestampMs;
}

/**
 * 自 Tronscan 拉取 USDT TRC20 轉帳，於時間窗內篩選；金額須 ≥ $minAmount（預設百位以上，即 ≥ 100）。
 *
 * @return list<array{amount: string, occurred_at: string, txid: string}>
 */
function collect_usdt_trc20_transfer_candidates(int $windowMs, float $minAmount, int $maxPages): array
{
    $endMs = (int) floor(microtime(true) * 1000);
    $startMs = $endMs - $windowMs;
    $candidates = [];
    $pageLimit = 50;
    $offset = 0;

    for ($page = 0; $page < $maxPages; $page++) {
        // Tronscan 對 start_timestamp/end_timestamp 與 confirm 的組合常回傳空陣列；改抓「最新分頁」再在本地依 block_ts 篩選時間窗。
        $endpoint = 'https://apilist.tronscanapi.com/api/token_trc20/transfers?' . http_build_query([
            'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'start' => $offset,
            'limit' => $pageLimit,
        ]);
        $payload = http_get_json($endpoint, 10);
        if (!is_array($payload)) {
            break;
        }

        $items = [];
        foreach (['token_transfers', 'data', 'transfers'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $items = $payload[$key];
                break;
            }
        }
        if (!$items) {
            break;
        }

        $oldestTsInPage = null;
        foreach ($items as $transfer) {
            if (!is_array($transfer)) {
                continue;
            }
            $timestampMs = parse_tronscan_transfer_timestamp_ms($transfer);
            if ($timestampMs === null) {
                continue;
            }
            $oldestTsInPage = $oldestTsInPage === null ? $timestampMs : min($oldestTsInPage, $timestampMs);
            $amount = parse_tronscan_usdt_transfer_amount($transfer);
            if ($amount === null || $amount < $minAmount || $timestampMs < $startMs || $timestampMs > $endMs) {
                continue;
            }
            $candidates[] = [
                'amount' => number_format($amount, 2, '.', ''),
                'occurred_at' => gmdate('c', (int) floor($timestampMs / 1000)),
                'txid' => (string) ($transfer['transaction_id'] ?? $transfer['transactionHash'] ?? $transfer['hash'] ?? ''),
            ];
        }

        if ($oldestTsInPage !== null && $oldestTsInPage < $startMs) {
            break;
        }
        if (count($items) < $pageLimit) {
            break;
        }
        $offset += $pageLimit;
    }

    return $candidates;
}

function fetch_random_recent_usdt_trc20_transfer(): ?array
{
    $minUsdt = 100.0;
    $candidates = collect_usdt_trc20_transfer_candidates(3600000, $minUsdt, 24);
    if (!$candidates) {
        $candidates = collect_usdt_trc20_transfer_candidates(86400000, $minUsdt, 32);
    }
    if (!$candidates) {
        return null;
    }
    return $candidates[random_int(0, count($candidates) - 1)];
}

function migrate_trade_feed_action_types(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT id, action_type, title FROM trade_feed_events ORDER BY id ASC');
    $items = $stmt ? $stmt->fetchAll() : [];
    if (!$items) {
        return;
    }
    $update = $pdo->prepare('UPDATE trade_feed_events SET action_type = :action_type WHERE id = :id');
    foreach ($items as $item) {
        $actionType = normalize_trade_feed_action_type($item['action_type'] ?? '');
        if ($actionType === 'custom') {
            $actionType = infer_trade_feed_action_type_from_title((string) ($item['title'] ?? ''));
        }
        $update->execute([
            ':action_type' => $actionType,
            ':id' => (int) $item['id'],
        ]);
    }
}

function cleanup_legacy_global_deposit_addresses(PDO $pdo): void
{
    $legacyRowsStmt = $pdo->query('SELECT id
        FROM deposit_addresses
        WHERE asset_code = "USDT"
          AND network_code = "TRC20"
          AND status = "enabled"
          AND remark = "初始迁移地址"
          AND (user_id IS NULL OR user_id <= 0)
        ORDER BY id ASC');
    $legacyIds = $legacyRowsStmt ? $legacyRowsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!$legacyIds) {
        return;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM deposit_addresses WHERE id = :id');
    foreach ($legacyIds as $legacyIdValue) {
        $deleteStmt->execute([':id' => (int) $legacyIdValue]);
    }
}

function seed_data(PDO $pdo): void
{
    $demoSeedPassword = env_string('APP_DEMO_SEED_USER_PASSWORD', '');
    if ($demoSeedPassword === '') {
        $demoSeedPassword = bin2hex(random_bytes(12));
    }
    $seedUsers = [
        [
            'account_type' => 'email',
            'username' => 'seed.user@example.com',
            'email' => 'seed.user@example.com',
            'mobile' => null,
            'country_code' => null,
            'mobile_e164' => null,
            'password' => $demoSeedPassword,
            'status' => 'normal',
            'lang' => 'eng',
            'invitation_code' => 'EU000001',
            'invited_by_user_id' => null,
        ],
        [
            'account_type' => 'email',
            'username' => 'merchant@example.com',
            'email' => 'merchant@example.com',
            'mobile' => null,
            'country_code' => null,
            'mobile_e164' => null,
            'password' => $demoSeedPassword,
            'status' => 'normal',
            'lang' => 'eng',
            'invitation_code' => 'EU000002',
            'invited_by_user_id' => 1,
        ],
    ];

    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0 && env_bool('APP_ENABLE_DEMO_SEED_USERS', false)) {
        foreach ($seedUsers as $seedUser) {
            $exists = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
            $exists->execute([':username' => $seedUser['username']]);
            if ($exists->fetch()) {
                continue;
            }
            $now = now_iso();
            $passwordHash = password_hash($seedUser['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (
                account_type, username, email, mobile, country_code, mobile_e164, password_hash, status, lang, avatar_id,
                invitation_code, invited_by_user_id, login_failure_count, created_at, updated_at
            ) VALUES (
                :account_type, :username, :email, :mobile, :country_code, :mobile_e164, :password_hash, :status, :lang, :avatar_id,
                :invitation_code, :invited_by_user_id, :login_failure_count, :created_at, :updated_at
            )');
            $stmt->execute([
                ':account_type' => $seedUser['account_type'],
                ':username' => $seedUser['username'],
                ':email' => $seedUser['email'],
                ':mobile' => $seedUser['mobile'],
                ':country_code' => $seedUser['country_code'],
                ':mobile_e164' => $seedUser['mobile_e164'],
                ':password_hash' => $passwordHash,
                ':status' => $seedUser['status'],
                ':lang' => $seedUser['lang'],
                ':avatar_id' => random_user_avatar_id(),
                ':invitation_code' => $seedUser['invitation_code'],
                ':invited_by_user_id' => $seedUser['invited_by_user_id'],
                ':login_failure_count' => 0,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO invitation_codes (user_id, code, status, is_primary, issued_at, expires_at, created_at, updated_at)
                VALUES (:user_id, :code, "active", 1, :issued_at, null, :created_at, :updated_at)')
                ->execute([
                    ':user_id' => $userId,
                    ':code' => $seedUser['invitation_code'],
                    ':issued_at' => $now,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
        }
    }

    $defaultAdminAccount = env_string('APP_DEFAULT_ADMIN_ACCOUNT', '');
    if ($defaultAdminAccount === '') {
        $defaultAdminAccount = 'admin888';
    }
    $defaultAdminPassword = env_string('APP_DEFAULT_ADMIN_PASSWORD', '');
    $defaultAdminEmail = admin_account_email($defaultAdminAccount);
    $defaultAdminPermissions = json_encode(admin_permissions_from_access([], true), JSON_UNESCAPED_UNICODE);
    $defaultAdminRoles = json_encode(['super_admin'], JSON_UNESCAPED_UNICODE);
    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    $enableDefaultAdmin = env_bool('APP_ENABLE_DEFAULT_ADMIN', false);
    if ($adminCount === 0 && $enableDefaultAdmin) {
        if ($defaultAdminPassword === '') {
            $defaultAdminPassword = bin2hex(random_bytes(16));
            error_log('APP_DEFAULT_ADMIN_PASSWORD is empty; generated a random initial admin password. Set APP_DEFAULT_ADMIN_PASSWORD before production bootstrap.');
        }
        $now = now_iso();
        $stmt = $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, status, role_codes, permissions, display_name, admin_group_code, admin_group_name, can_view_group_global_data, created_by_admin_id, parent_admin_id, created_at, updated_at)
            VALUES (:name, :email, :password_hash, :status, :role_codes, :permissions, :display_name, :admin_group_code, :admin_group_name, 1, null, null, :created_at, :updated_at)');
        $stmt->execute([
            ':name' => $defaultAdminAccount,
            ':email' => $defaultAdminEmail,
            ':password_hash' => password_hash($defaultAdminPassword, PASSWORD_BCRYPT),
            ':status' => 'normal',
            ':role_codes' => $defaultAdminRoles,
            ':permissions' => $defaultAdminPermissions,
            ':display_name' => '大老板',
            ':admin_group_code' => 'boss',
            ':admin_group_name' => '大老板',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    } elseif ($adminCount > 0 && $enableDefaultAdmin) {
        $legacyAdminStmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE name = :legacy_name OR email = :legacy_email LIMIT 1');
        $legacyAdminStmt->execute([
            ':legacy_name' => 'super-admin',
            ':legacy_email' => 'admin@example.com',
        ]);
        $legacyAdmin = $legacyAdminStmt->fetch();
        $defaultAdminExistsStmt = $pdo->prepare('SELECT id FROM admin_users WHERE name = :name LIMIT 1');
        $defaultAdminExistsStmt->execute([':name' => $defaultAdminAccount]);
        $defaultAdminExists = $defaultAdminExistsStmt->fetch();
        if ($legacyAdmin && (!$defaultAdminExists || (int) $defaultAdminExists['id'] === (int) $legacyAdmin['id'])) {
            $pdo->prepare('UPDATE admin_users SET
                name = :name,
                email = :email,
                password_hash = :password_hash,
                status = :status,
                role_codes = :role_codes,
                permissions = :permissions,
                display_name = :display_name,
                admin_group_code = :admin_group_code,
                admin_group_name = :admin_group_name,
                can_view_group_global_data = 1,
                updated_at = :updated_at
                WHERE id = :id')
                ->execute([
                    ':name' => $defaultAdminAccount,
                    ':email' => $defaultAdminEmail,
                    ':password_hash' => $defaultAdminPassword !== '' ? password_hash($defaultAdminPassword, PASSWORD_BCRYPT) : (string) ($legacyAdmin['password_hash'] ?? ''),
                    ':status' => 'normal',
                    ':role_codes' => $defaultAdminRoles,
                    ':permissions' => $defaultAdminPermissions,
                    ':display_name' => '大老板',
                    ':admin_group_code' => 'boss',
                    ':admin_group_name' => '大老板',
                    ':updated_at' => now_iso(),
                    ':id' => (int) $legacyAdmin['id'],
                ]);
        }
    }

    if (env_bool('APP_ENABLE_RUNTIME_SUPER_ADMIN', false)) {
        $runtimeAdminAccount = 'admin666';
        $runtimeAdminEmail = admin_account_email($runtimeAdminAccount);
        $runtimeAdminStmt = $pdo->prepare('SELECT id FROM admin_users WHERE name = :name LIMIT 1');
        $runtimeAdminStmt->execute([':name' => $runtimeAdminAccount]);
        $runtimeAdmin = $runtimeAdminStmt->fetch();
        if (!$runtimeAdmin) {
            $password = env_string('APP_RUNTIME_SUPER_ADMIN_PASSWORD', '');
            if ($password === '') {
                $password = bin2hex(random_bytes(16));
            }
            $now = now_iso();
            $pdo->prepare('INSERT INTO admin_users (
                name, email, password_hash, status, role_codes, permissions, display_name, staff_invite_code, admin_group_code, admin_group_name, can_view_group_global_data, created_by_admin_id, parent_admin_id, created_at, updated_at
            ) VALUES (
                :name, :email, :password_hash, "normal", :role_codes, :permissions, :display_name, :staff_invite_code, :admin_group_code, :admin_group_name, 1, null, null, :created_at, :updated_at
            )')->execute([
                ':name' => $runtimeAdminAccount,
                ':email' => $runtimeAdminEmail,
                ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                ':role_codes' => $defaultAdminRoles,
                ':permissions' => $defaultAdminPermissions,
                ':display_name' => '大老板',
                ':staff_invite_code' => generate_admin_staff_invite_code($pdo),
                ':admin_group_code' => 'boss',
                ':admin_group_name' => '大老板',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }

    $demoInviteCode = '847392';
    $demoInviteExists = $pdo->prepare('SELECT id FROM invitation_codes WHERE code = :code LIMIT 1');
    $demoInviteExists->execute([':code' => $demoInviteCode]);
    if (!$demoInviteExists->fetch()) {
        $firstUserStmt = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1');
        $firstUser = $firstUserStmt ? $firstUserStmt->fetch() : null;
        if ($firstUser) {
            $now = now_iso();
            $pdo->prepare('INSERT INTO invitation_codes (user_id, code, status, is_primary, issued_at, expires_at, created_at, updated_at)
                VALUES (:user_id, :code, "active", 0, :issued_at, null, :created_at, :updated_at)')
                ->execute([
                    ':user_id' => (int) $firstUser['id'],
                    ':code' => $demoInviteCode,
                    ':issued_at' => $now,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
        }
    }

    $seedUsernames = array_column($seedUsers, 'username');
    foreach ($seedUsernames as $index => $seedUsername) {
        $userStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $userStmt->execute([':username' => $seedUsername]);
        $user = $userStmt->fetch();
        if (!$user) {
            continue;
        }
        $userId = (int) $user['id'];
        $now = now_iso();
        $walletSeeds = [
            ['wallet_code' => 'cash_usdt', 'currency_code' => 'USDT', 'available_balance' => $index === 0 ? '120.00000000' : '100.00000000', 'reserved_balance' => '0.00000000'],
            ['wallet_code' => 'eur', 'currency_code' => 'EUR', 'available_balance' => $index === 0 ? '50.00000000' : '20.00000000', 'reserved_balance' => '0.00000000'],
            ['wallet_code' => 'eur_reserved', 'currency_code' => 'EUR', 'available_balance' => '0.00000000', 'reserved_balance' => $index === 0 ? '10.00000000' : '30.00000000'],
            ['wallet_code' => 'cny', 'currency_code' => 'CNY', 'available_balance' => $index === 0 ? '0.00000000' : '5.00000000', 'reserved_balance' => '0.00000000'],
        ];
        foreach ($walletSeeds as $wallet) {
            $exists = $pdo->prepare('SELECT id FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
            $exists->execute([
                ':user_id' => $userId,
                ':wallet_code' => $wallet['wallet_code'],
            ]);
            if ($exists->fetch()) {
                continue;
            }
            $pdo->prepare('INSERT INTO user_wallet_balances (
                user_id, wallet_code, currency_code, available_balance, reserved_balance, created_at, updated_at
            ) VALUES (
                :user_id, :wallet_code, :currency_code, :available_balance, :reserved_balance, :created_at, :updated_at
            )')->execute([
                ':user_id' => $userId,
                ':wallet_code' => $wallet['wallet_code'],
                ':currency_code' => $wallet['currency_code'],
                ':available_balance' => $wallet['available_balance'],
                ':reserved_balance' => $wallet['reserved_balance'],
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        $tierExists = $pdo->prepare('SELECT id FROM user_tier_profiles WHERE user_id = :user_id LIMIT 1');
        $tierExists->execute([':user_id' => $userId]);
        if (!$tierExists->fetch()) {
            $pdo->prepare('INSERT INTO user_tier_profiles (
                user_id, level, group_code, score, merchant_enabled, is_verified, daily_trade_limit,
                min_sell_amount, margin_amount, margin_ratio, risk_status, violation_message, created_at, updated_at
            ) VALUES (
                :user_id, :level, :group_code, :score, :merchant_enabled, :is_verified, :daily_trade_limit,
                :min_sell_amount, :margin_amount, :margin_ratio, :risk_status, :violation_message, :created_at, :updated_at
            )')->execute([
                ':user_id' => $userId,
                ':level' => $index === 0 ? 3 : 1,
                ':group_code' => tier_group_code_for_level($index === 0 ? 3 : 1),
                ':score' => $index === 0 ? 100 : 20,
                ':merchant_enabled' => $index === 0 ? 1 : 0,
                ':is_verified' => $index === 0 ? 1 : 0,
                ':daily_trade_limit' => $index === 0 ? null : 1,
                ':min_sell_amount' => $index === 0 ? '20.00000000' : '10.00000000',
                ':margin_amount' => $index === 0 ? '50.00000000' : '0.00000000',
                ':margin_ratio' => $index === 0 ? '0.1000' : '0.0000',
                ':risk_status' => 'normal',
                ':violation_message' => null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }

    $defaultFrontendBaseUrls = env_string('FRONTEND_BASE_URLS', '["http://127.0.0.1:8094/h5","http://localhost:8094/h5"]');
    $defaultApiPublicBaseUrl = env_string('API_PUBLIC_BASE_URL', public_origin() . '/api');
    $configSeeds = [
        ['config_group' => 'auth', 'config_key' => 'register_requires_invitation', 'config_value' => 'true'],
        ['config_group' => 'auth', 'config_key' => 'token_ttl_hours', 'config_value' => '168'],
        ['config_group' => 'verification', 'config_key' => 'send_code_cooldown_seconds', 'config_value' => '60'],
        ['config_group' => 'verification', 'config_key' => 'verification_code_ttl_seconds', 'config_value' => '600'],
        ['config_group' => 'lang', 'config_key' => 'default_lang', 'config_value' => 'eng'],
        ['config_group' => 'finance', 'config_key' => 'usdt_to_eur_rate', 'config_value' => '0.96000000'],
        ['config_group' => 'finance', 'config_key' => 'eur_to_usdt_withdraw_rate', 'config_value' => '0.96000000'],
        ['config_group' => 'finance', 'config_key' => 'eur_to_usdt_withdraw_fee_mode', 'config_value' => 'percent'],
        ['config_group' => 'finance', 'config_key' => 'eur_to_usdt_withdraw_fee_rate', 'config_value' => '0.00'],
        ['config_group' => 'finance', 'config_key' => 'eur_to_usdt_withdraw_fee_fixed_usdt', 'config_value' => '0.00'],
        ['config_group' => 'finance', 'config_key' => 'usdt_platform_deposit_trc20', 'config_value' => 'TUxyywtEffhzRoACbvHUh3756FPE1CYV56'],
        ['config_group' => 'finance', 'config_key' => 'usdt_platform_deposit_erc20', 'config_value' => '0x9208A00F5D4B652a68687335Fae4050B5f006332'],
        ['config_group' => 'finance', 'config_key' => 'usdt_platform_deposit_bep20', 'config_value' => '0x9208A00F5D4B652a68687335Fae4050B5f006332'],
        ['config_group' => 'trade', 'config_key' => 'hall_notice', 'config_value' => 'FROM 11AM TO 11PM EVERY DAY ! Market notice and service update.'],
        ['config_group' => 'home', 'config_key' => 'banners', 'config_value' => '[]'],
        ['config_group' => 'site', 'config_key' => 'frontend_base_urls', 'config_value' => $defaultFrontendBaseUrls],
        ['config_group' => 'site', 'config_key' => 'api_public_base_url', 'config_value' => $defaultApiPublicBaseUrl],
    ];
    foreach ($configSeeds as $config) {
        $exists = $pdo->prepare('SELECT id FROM system_configs WHERE config_group = :config_group AND config_key = :config_key LIMIT 1');
        $exists->execute([
            ':config_group' => $config['config_group'],
            ':config_key' => $config['config_key'],
        ]);
        if ($exists->fetch()) {
            continue;
        }
        $pdo->prepare('INSERT INTO system_configs (config_group, config_key, config_value, created_at, updated_at)
            VALUES (:config_group, :config_key, :config_value, :created_at, :updated_at)')
            ->execute([
                ':config_group' => $config['config_group'],
                ':config_key' => $config['config_key'],
                ':config_value' => $config['config_value'],
                ':created_at' => now_iso(),
                ':updated_at' => now_iso(),
            ]);
    }

    $seedUsersWithIds = [];
    foreach ($seedUsernames as $seedUsername) {
        $userStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $userStmt->execute([':username' => $seedUsername]);
        $user = $userStmt->fetch();
        if ($user) {
            $seedUsersWithIds[] = (int) $user['id'];
        }
    }
    foreach ($seedUsersWithIds as $index => $userId) {
        $now = now_iso();

        $payoutSeeds = [
            [
                'channel_type' => 'bank',
                'status' => $index === 0 ? 'approved' : 'pending',
                'bank_name' => 'Example Bank',
                'account_holder' => $index === 0 ? 'Seed User' : 'Pending User',
                'account_no_masked' => $index === 0 ? '6222 **** **** 1001' : '6222 **** **** 2002',
                'usdt_network' => null,
                'payout_address' => null,
                'pix_key' => null,
                'is_default' => 1,
            ],
            [
                'channel_type' => 'usdt',
                'status' => $index === 0 ? 'approved' : 'pending',
                'bank_name' => null,
                'account_holder' => null,
                'account_no_masked' => null,
                'usdt_network' => 'TRC20',
                'payout_address' => $index === 0 ? 'TPSeedWalletAddress0001' : 'TPPendingWalletAddress0002',
                'pix_key' => null,
                'is_default' => 0,
            ],
        ];

        foreach ($payoutSeeds as $seed) {
            $exists = $pdo->prepare('SELECT id FROM user_payout_methods WHERE user_id = :user_id AND channel_type = :channel_type AND COALESCE(account_no_masked, "") = :account_no_masked AND COALESCE(payout_address, "") = :payout_address LIMIT 1');
            $exists->execute([
                ':user_id' => $userId,
                ':channel_type' => $seed['channel_type'],
                ':account_no_masked' => $seed['account_no_masked'] ?? '',
                ':payout_address' => $seed['payout_address'] ?? '',
            ]);
            if ($exists->fetch()) {
                continue;
            }

            $pdo->prepare('INSERT INTO user_payout_methods (
                user_id, channel_type, status, bank_name, account_holder, account_no_masked, usdt_network,
                payout_address, pix_key, is_default, review_note, reviewed_by_admin_id, reviewed_at, created_at, updated_at
            ) VALUES (
                :user_id, :channel_type, :status, :bank_name, :account_holder, :account_no_masked, :usdt_network,
                :payout_address, :pix_key, :is_default, :review_note, :reviewed_by_admin_id, :reviewed_at, :created_at, :updated_at
            )')->execute([
                ':user_id' => $userId,
                ':channel_type' => $seed['channel_type'],
                ':status' => $seed['status'],
                ':bank_name' => $seed['bank_name'],
                ':account_holder' => $seed['account_holder'],
                ':account_no_masked' => $seed['account_no_masked'],
                ':usdt_network' => $seed['usdt_network'],
                ':payout_address' => $seed['payout_address'],
                ':pix_key' => $seed['pix_key'],
                ':is_default' => $seed['is_default'],
                ':review_note' => $seed['status'] === 'approved' ? 'seed approved' : null,
                ':reviewed_by_admin_id' => $seed['status'] === 'approved' ? 1 : null,
                ':reviewed_at' => $seed['status'] === 'approved' ? $now : null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        $kycExists = $pdo->prepare('SELECT id FROM user_kyc_applications WHERE user_id = :user_id LIMIT 1');
        $kycExists->execute([':user_id' => $userId]);
        if (!$kycExists->fetch()) {
            $pdo->prepare('INSERT INTO user_kyc_applications (
                user_id, legal_name, id_number_masked, id_doc_front_url, id_doc_back_url, selfie_url,
                status, review_note, reviewed_by_admin_id, reviewed_at, submitted_at, created_at, updated_at
            ) VALUES (
                :user_id, :legal_name, :id_number_masked, :id_doc_front_url, :id_doc_back_url, :selfie_url,
                :status, :review_note, :reviewed_by_admin_id, :reviewed_at, :submitted_at, :created_at, :updated_at
            )')->execute([
                ':user_id' => $userId,
                ':legal_name' => $index === 0 ? 'Seed User' : 'Pending User',
                ':id_number_masked' => $index === 0 ? 'A123****01' : 'B456****02',
                ':id_doc_front_url' => 'https://example.com/kyc/front-' . $userId . '.jpg',
                ':id_doc_back_url' => 'https://example.com/kyc/back-' . $userId . '.jpg',
                ':selfie_url' => 'https://example.com/kyc/selfie-' . $userId . '.jpg',
                ':status' => $index === 0 ? 'approved' : 'pending',
                ':review_note' => $index === 0 ? 'seed approved' : null,
                ':reviewed_by_admin_id' => $index === 0 ? 1 : null,
                ':reviewed_at' => $index === 0 ? $now : null,
                ':submitted_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        $depositExists = $pdo->prepare('SELECT id FROM deposit_requests WHERE user_id = :user_id LIMIT 1');
        $depositExists->execute([':user_id' => $userId]);
        if (!$depositExists->fetch()) {
            $pdo->prepare('INSERT INTO deposit_requests (
                user_id, amount, asset_code, network, target_wallet_code, proof_url, reference_text,
                status, admin_note, reviewed_by_admin_id, reviewed_at, created_at, updated_at
            ) VALUES (
                :user_id, :amount, :asset_code, :network, :target_wallet_code, :proof_url, :reference_text,
                :status, :admin_note, :reviewed_by_admin_id, :reviewed_at, :created_at, :updated_at
            )')->execute([
                ':user_id' => $userId,
                ':amount' => $index === 0 ? '25.00000000' : '40.00000000',
                ':asset_code' => 'USDT',
                ':network' => 'TRC20',
                ':target_wallet_code' => 'cash_usdt',
                ':proof_url' => 'https://example.com/deposit-proof-' . $userId . '.jpg',
                ':reference_text' => 'seed-tx-' . strtoupper(substr(random_token(12), 0, 12)),
                ':status' => 'pending',
                ':admin_note' => null,
                ':reviewed_by_admin_id' => null,
                ':reviewed_at' => null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        $methodStmt = $pdo->prepare('SELECT id, channel_type, payout_address FROM user_payout_methods WHERE user_id = :user_id ORDER BY id ASC');
        $methodStmt->execute([':user_id' => $userId]);
        $methods = $methodStmt->fetchAll();
        $preferredMethod = $methods[0] ?? null;
        $withdrawExists = $pdo->prepare('SELECT id FROM withdrawal_requests WHERE user_id = :user_id LIMIT 1');
        $withdrawExists->execute([':user_id' => $userId]);
        if (!$withdrawExists->fetch() && $preferredMethod) {
            $pdo->prepare('INSERT INTO withdrawal_requests (
                user_id, amount, asset_code, channel_type, payout_method_id, payout_address, source_wallet_code,
                status, admin_note, reviewed_by_admin_id, reviewed_at, created_at, updated_at
            ) VALUES (
                :user_id, :amount, :asset_code, :channel_type, :payout_method_id, :payout_address, :source_wallet_code,
                :status, :admin_note, :reviewed_by_admin_id, :reviewed_at, :created_at, :updated_at
            )')->execute([
                ':user_id' => $userId,
                ':amount' => $index === 0 ? '12.00000000' : '8.00000000',
                ':asset_code' => $preferredMethod['channel_type'] === 'bank' ? 'USD' : 'USDT',
                ':channel_type' => $preferredMethod['channel_type'],
                ':payout_method_id' => (int) $preferredMethod['id'],
                ':payout_address' => $preferredMethod['payout_address'] ?? null,
                ':source_wallet_code' => $preferredMethod['channel_type'] === 'bank' ? 'eur' : 'cash_usdt',
                ':status' => 'pending',
                ':admin_note' => null,
                ':reviewed_by_admin_id' => null,
                ':reviewed_at' => null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        $orderExists = $pdo->prepare('SELECT id FROM c2c_orders WHERE buyer_user_id = :buyer_user_id OR seller_user_id = :seller_user_id LIMIT 1');
        $orderExists->execute([
            ':buyer_user_id' => $userId,
            ':seller_user_id' => $userId,
        ]);
        if (!$orderExists->fetch() && count($seedUsersWithIds) >= 2) {
            $buyerUserId = (int) $seedUsersWithIds[0];
            $sellerUserId = (int) $seedUsersWithIds[count($seedUsersWithIds) - 1];
            if ($buyerUserId !== $sellerUserId) {
                $pdo->prepare('INSERT INTO c2c_orders (
                    order_no, side, buyer_user_id, seller_user_id, amount, price, total_amount, asset_code, fiat_code,
                    payment_method_summary, status, completed_at, cancel_reason, dispute_reason, created_at, updated_at
                ) VALUES (
                    :order_no, :side, :buyer_user_id, :seller_user_id, :amount, :price, :total_amount, :asset_code, :fiat_code,
                    :payment_method_summary, :status, :completed_at, :cancel_reason, :dispute_reason, :created_at, :updated_at
                )')->execute([
                    ':order_no' => 'OD' . strtoupper(substr(random_token(14), 0, 10)),
                    ':side' => 'buy',
                    ':buyer_user_id' => $buyerUserId,
                    ':seller_user_id' => $sellerUserId,
                    ':amount' => '15.00000000',
                    ':price' => '7.20000000',
                    ':total_amount' => '108.00000000',
                    ':asset_code' => 'USDT',
                    ':fiat_code' => 'CNY',
                    ':payment_method_summary' => '银行卡尾号 1001',
                    ':status' => 'paid_pending_release',
                    ':completed_at' => null,
                    ':cancel_reason' => null,
                    ':dispute_reason' => null,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
            }
        }
    }

    $orders = $pdo->query('SELECT id FROM c2c_orders ORDER BY id ASC')->fetchAll();
    foreach ($orders as $order) {
        $exists = $pdo->prepare('SELECT id FROM order_evidences WHERE order_id = :order_id LIMIT 1');
        $exists->execute([':order_id' => (int) $order['id']]);
        if ($exists->fetch()) {
            continue;
        }

        $pdo->prepare('INSERT INTO order_evidences (
            order_id, actor_type, actor_id, evidence_type, content, attachment_url, created_at
        ) VALUES (
            :order_id, :actor_type, :actor_id, :evidence_type, :content, :attachment_url, :created_at
        )')->execute([
            ':order_id' => (int) $order['id'],
            ':actor_type' => 'system',
            ':actor_id' => null,
            ':evidence_type' => 'timeline',
            ':content' => 'Seed order created for dispute/release flow validation.',
            ':attachment_url' => null,
            ':created_at' => now_iso(),
        ]);
    }

    $merchantStmt = $pdo->prepare('SELECT id, username FROM users WHERE username = :username LIMIT 1');
    $merchantStmt->execute([':username' => 'merchant@example.com']);
    $merchant = $merchantStmt->fetch();
    if ($merchant) {
        $listingSeeds = [
            [
                'side' => 'sell',
                'nickname' => 'Prime Flow',
                'asset_code' => 'USDT',
                'fiat_code' => 'EUR',
                'price' => '0.93000000',
                'min_amount' => '50.00000000',
                'max_amount' => '5000.00000000',
                'available_amount' => '5800.00000000',
                'payment_method_summary' => 'SEPA / Bank transfer',
                'completion_rate' => '98%',
            ],
            [
                'side' => 'buy',
                'nickname' => 'Prime Flow',
                'asset_code' => 'USDT',
                'fiat_code' => 'EUR',
                'price' => '0.99000000',
                'min_amount' => '500.00000000',
                'max_amount' => '10000.00000000',
                'available_amount' => '5800.00000000',
                'payment_method_summary' => 'SEPA / Fast settlement',
                'completion_rate' => '100%',
            ],
        ];
        foreach ($listingSeeds as $listing) {
            $exists = $pdo->prepare('SELECT id FROM c2c_listings WHERE owner_user_id = :owner_user_id AND side = :side LIMIT 1');
            $exists->execute([
                ':owner_user_id' => (int) $merchant['id'],
                ':side' => $listing['side'],
            ]);
            if ($exists->fetch()) {
                continue;
            }
            $pdo->prepare('INSERT INTO c2c_listings (
                owner_user_id, nickname, side, asset_code, fiat_code, price, min_amount, max_amount,
                available_amount, payment_method_summary, completion_rate, badge_vip, badge_pro, badge_stars, status, created_at, updated_at
            ) VALUES (
                :owner_user_id, :nickname, :side, :asset_code, :fiat_code, :price, :min_amount, :max_amount,
                :available_amount, :payment_method_summary, :completion_rate, :badge_vip, :badge_pro, :badge_stars, "active", :created_at, :updated_at
            )')->execute([
                ':owner_user_id' => (int) $merchant['id'],
                ':nickname' => $listing['nickname'],
                ':side' => $listing['side'],
                ':asset_code' => $listing['asset_code'],
                ':fiat_code' => $listing['fiat_code'],
                ':price' => $listing['price'],
                ':min_amount' => $listing['min_amount'],
                ':max_amount' => $listing['max_amount'],
                ':available_amount' => $listing['available_amount'],
                ':payment_method_summary' => $listing['payment_method_summary'],
                ':completion_rate' => $listing['completion_rate'],
                ':badge_vip' => 0,
                ':badge_pro' => 0,
                ':badge_stars' => 0,
                ':created_at' => now_iso(),
                ':updated_at' => now_iso(),
            ]);
        }
    }

    $tradeFeedSeeds = [
        ['action_type' => 'completed_buy', 'title' => 'Completed buy', 'actor_name' => 'Prime Flow', 'asset_code' => 'USDT', 'amount' => '450.00000000', 'occurred_at' => now_iso(), 'sort_order' => 10],
        ['action_type' => 'completed_sell', 'title' => 'Completed sell', 'actor_name' => 'Elite Desk', 'asset_code' => 'USDT', 'amount' => '1200.00000000', 'occurred_at' => now_iso(), 'sort_order' => 20],
        ['action_type' => 'completed_buy', 'title' => 'Completed buy', 'actor_name' => 'GlobalTrader', 'asset_code' => 'USDT', 'amount' => '89.00000000', 'occurred_at' => now_iso(), 'sort_order' => 30],
    ];
    foreach ($tradeFeedSeeds as $event) {
        $exists = $pdo->prepare('SELECT id FROM trade_feed_events WHERE actor_name = :actor_name AND title = :title AND amount = :amount LIMIT 1');
        $exists->execute([
            ':actor_name' => $event['actor_name'],
            ':title' => $event['title'],
            ':amount' => $event['amount'],
        ]);
        if ($exists->fetch()) {
            continue;
        }
        $pdo->prepare('INSERT INTO trade_feed_events (
            action_type, title, actor_name, asset_code, amount, occurred_at, sort_order, status, created_at, updated_at
        ) VALUES (
            :action_type, :title, :actor_name, :asset_code, :amount, :occurred_at, :sort_order, "active", :created_at, :updated_at
        )')->execute([
            ':action_type' => normalize_trade_feed_action_type($event['action_type'] ?? 'custom'),
            ':title' => $event['title'],
            ':actor_name' => $event['actor_name'],
            ':asset_code' => $event['asset_code'],
            ':amount' => $event['amount'],
            ':occurred_at' => $event['occurred_at'],
            ':sort_order' => $event['sort_order'],
            ':created_at' => now_iso(),
            ':updated_at' => now_iso(),
        ]);
    }

    $pdo->prepare('DELETE FROM user_financial_subscriptions WHERE product_code = "eth30d" OR asset_code = "ETH" OR wallet_code = "spot_eth"')
        ->execute();
    $pdo->prepare('DELETE FROM financial_products WHERE product_code = "eth30d" OR asset_code = "ETH" OR wallet_code = "spot_eth"')
        ->execute();
    $pdo->prepare('DELETE FROM user_wallet_balances WHERE wallet_code = "spot_eth"')
        ->execute();

    $financialProducts = [
        [
            'product_code' => 'usdt7d',
            'asset_code' => 'USDT',
            'wallet_code' => 'cash_usdt',
            'display_name' => 'USDT 定期 7天',
            'subtitle' => '短周期稳健产品',
            'detail_note' => '收益與結算規則以平台配置為準。',
            'apr_rate' => '4.80',
            'term_days' => 7,
            'min_subscribe_amount' => '100.00000000',
            'personal_limit_amount' => '20000.00000000',
            'total_quota_amount' => '500000.00000000',
            'sold_quota_amount' => '1200.00000000',
            'auto_renew_default' => 0,
            'default_return_mode' => 'auto',
            'default_return_delay_days' => 0,
            'status' => 'active',
            'sort_order' => 10,
        ],
        [
            'product_code' => 'usdt14d',
            'asset_code' => 'USDT',
            'wallet_code' => 'cash_usdt',
            'display_name' => 'USDT 定期 14天',
            'subtitle' => '中短周期增益产品',
            'detail_note' => '收益與結算規則以平台配置為準。',
            'apr_rate' => '6.20',
            'term_days' => 14,
            'min_subscribe_amount' => '300.00000000',
            'personal_limit_amount' => '50000.00000000',
            'total_quota_amount' => '300000.00000000',
            'sold_quota_amount' => '2400.00000000',
            'auto_renew_default' => 0,
            'default_return_mode' => 'auto',
            'default_return_delay_days' => 0,
            'status' => 'active',
            'sort_order' => 20,
        ],
        [
            'product_code' => 'usdt30d',
            'asset_code' => 'USDT',
            'wallet_code' => 'cash_usdt',
            'display_name' => 'USDT 定期 30天',
            'subtitle' => '长周期高收益产品',
            'detail_note' => '收益與結算規則以平台配置為準。',
            'apr_rate' => '7.40',
            'term_days' => 30,
            'min_subscribe_amount' => '500.00000000',
            'personal_limit_amount' => '80000.00000000',
            'total_quota_amount' => '800000.00000000',
            'sold_quota_amount' => '8000.00000000',
            'auto_renew_default' => 1,
            'default_return_mode' => 'auto',
            'default_return_delay_days' => 0,
            'status' => 'active',
            'sort_order' => 30,
        ],
    ];
    foreach ($financialProducts as $product) {
        $exists = $pdo->prepare('SELECT id FROM financial_products WHERE product_code = :product_code LIMIT 1');
        $exists->execute([':product_code' => $product['product_code']]);
        if ($exists->fetch()) {
            $pdo->prepare('UPDATE financial_products SET
                display_name = COALESCE(NULLIF(display_name, ""), :display_name),
                subtitle = COALESCE(NULLIF(subtitle, ""), :subtitle),
                detail_note = COALESCE(NULLIF(detail_note, ""), :detail_note),
                default_return_mode = COALESCE(NULLIF(default_return_mode, ""), :default_return_mode),
                default_return_delay_days = CASE
                    WHEN default_return_delay_days IS NULL THEN :default_return_delay_days
                    ELSE default_return_delay_days
                END
                WHERE product_code = :product_code')->execute([
                ':display_name' => $product['display_name'],
                ':subtitle' => $product['subtitle'],
                ':detail_note' => $product['detail_note'],
                ':default_return_mode' => $product['default_return_mode'],
                ':default_return_delay_days' => $product['default_return_delay_days'],
                ':product_code' => $product['product_code'],
            ]);
            continue;
        }

        $pdo->prepare('INSERT INTO financial_products (
            product_code, asset_code, wallet_code, display_name, subtitle, detail_note, apr_rate, term_days, min_subscribe_amount,
            personal_limit_amount, total_quota_amount, sold_quota_amount, auto_renew_default, default_return_mode, default_return_delay_days,
            status, sort_order, created_at, updated_at
        ) VALUES (
            :product_code, :asset_code, :wallet_code, :display_name, :subtitle, :detail_note, :apr_rate, :term_days, :min_subscribe_amount,
            :personal_limit_amount, :total_quota_amount, :sold_quota_amount, :auto_renew_default, :default_return_mode, :default_return_delay_days,
            :status, :sort_order, :created_at, :updated_at
        )')->execute([
            ':product_code' => $product['product_code'],
            ':asset_code' => $product['asset_code'],
            ':wallet_code' => $product['wallet_code'],
            ':display_name' => $product['display_name'],
            ':subtitle' => $product['subtitle'],
            ':detail_note' => $product['detail_note'],
            ':apr_rate' => $product['apr_rate'],
            ':term_days' => $product['term_days'],
            ':min_subscribe_amount' => $product['min_subscribe_amount'],
            ':personal_limit_amount' => $product['personal_limit_amount'],
            ':total_quota_amount' => $product['total_quota_amount'],
            ':sold_quota_amount' => $product['sold_quota_amount'],
            ':auto_renew_default' => $product['auto_renew_default'],
            ':default_return_mode' => $product['default_return_mode'],
            ':default_return_delay_days' => $product['default_return_delay_days'],
            ':status' => $product['status'],
            ':sort_order' => $product['sort_order'],
            ':created_at' => now_iso(),
            ':updated_at' => now_iso(),
        ]);
    }
}

function audit(PDO $pdo, string $category, string $action, ?string $operatorType = null, ?int $operatorId = null, ?string $targetType = null, ?int $targetId = null, ?string $reason = null, ?array $before = null, ?array $after = null, ?string $errorCode = null, ?array $payload = null): void
{
    $pdo->prepare('INSERT INTO audit_logs (
        category, action, operator_type, operator_id, target_type, target_id, reason,
        before_json, after_json, error_code, payload_json, created_at, ip, account, msg
    ) VALUES (
        :category, :action, :operator_type, :operator_id, :target_type, :target_id, :reason,
        :before_json, :after_json, :error_code, :payload_json, :created_at, :ip, :account, :msg
    )')->execute([
        ':category' => $category,
        ':action' => $action,
        ':operator_type' => $operatorType,
        ':operator_id' => $operatorId,
        ':target_type' => $targetType,
        ':target_id' => $targetId,
        ':reason' => $reason,
        ':before_json' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ':after_json' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ':error_code' => $errorCode,
        ':payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ':created_at' => now_iso(),
        ':ip' => $payload['ip'] ?? null,
        ':account' => $payload['account'] ?? null,
        ':msg' => $payload['msg'] ?? null,
    ]);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function is_email_account(string $value): bool
{
    return str_contains($value, '@');
}

function normalize_mobile(string $mobile): ?array
{
    $mobile = preg_replace('/[\s\-()]/', '', trim($mobile));
    if ($mobile === '') {
        return null;
    }

    if ($mobile[0] !== '+') {
        $mobile = '+' . ltrim($mobile, '+');
    }

    if (!preg_match('/^\+\d{7,15}$/', $mobile)) {
        return null;
    }

    preg_match('/^\+(\d{1,4})(\d{6,})$/', $mobile, $matches);
    if (!$matches) {
        return null;
    }

    return [
        'country_code' => '+' . $matches[1],
        'mobile_e164' => $mobile,
        'mobile' => $matches[2],
    ];
}

function apply_admin_lookup_filter(array &$where, array &$params, string $rawValue, array $exactExpressions, array $likeExpressions, string $paramPrefix): void
{
    $value = trim($rawValue);
    if ($value === '') {
        return;
    }

    $conditions = [];
    if (preg_match('/^\d+$/', $value)) {
        $idParam = ':' . $paramPrefix . '_id';
        $params[$idParam] = (int) $value;
        foreach ($exactExpressions as $expression) {
            $conditions[] = $expression . ' = ' . $idParam;
        }
    }

    $lookupParam = ':' . $paramPrefix . '_lookup';
    $params[$lookupParam] = '%' . mb_strtolower($value) . '%';
    foreach ($likeExpressions as $expression) {
        $conditions[] = 'LOWER(COALESCE(' . $expression . ', "")) LIKE ' . $lookupParam;
    }

    if ($conditions) {
        $where[] = '(' . implode(' OR ', $conditions) . ')';
    }
}

function find_user_id_by_admin_lookup(PDO $pdo, string $rawValue): ?int
{
    $value = trim($rawValue);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d+$/', $value)) {
        return (int) $value;
    }

    $normalized = mb_strtolower($value);
    $stmt = $pdo->prepare('SELECT id FROM users
        WHERE LOWER(COALESCE(email, "")) = :lookup
           OR LOWER(COALESCE(mobile_e164, "")) = :lookup
        LIMIT 1');
    $stmt->execute([':lookup' => $normalized]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function generate_user_display_code(PDO $pdo): string
{
    while (true) {
        $code = 'EN' . (string) random_int(234588, 999999);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
}

function generate_invitation_numeric_code(PDO $pdo): string
{
    while (true) {
        $code = (string) random_int(100000, 999999);
        $stmt = $pdo->prepare('SELECT id FROM invitation_codes WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        if ($stmt->fetch()) {
            continue;
        }
        $userStmt = $pdo->prepare('SELECT id FROM users WHERE invitation_code = :code LIMIT 1');
        $userStmt->execute([':code' => $code]);
        if ($userStmt->fetch()) {
            continue;
        }
        $adminStmt = $pdo->prepare('SELECT id FROM admin_users WHERE staff_invite_code = :code LIMIT 1');
        $adminStmt->execute([':code' => $code]);
        if ($adminStmt->fetch()) {
            continue;
        }

        return $code;
    }
}

function initialize_user_defaults(PDO $pdo, int $userId): void
{
    $now = now_iso();
    $walletSeeds = [
        ['wallet_code' => 'cash_usdt', 'currency_code' => 'USDT', 'available_balance' => '0.00000000', 'reserved_balance' => '0.00000000'],
        ['wallet_code' => 'eur', 'currency_code' => 'EUR', 'available_balance' => '0.00000000', 'reserved_balance' => '0.00000000'],
        ['wallet_code' => 'eur_reserved', 'currency_code' => 'EUR', 'available_balance' => '0.00000000', 'reserved_balance' => '0.00000000'],
        ['wallet_code' => 'cny', 'currency_code' => 'CNY', 'available_balance' => '0.00000000', 'reserved_balance' => '0.00000000'],
    ];

    foreach ($walletSeeds as $wallet) {
        $pdo->prepare('INSERT INTO user_wallet_balances (
            user_id, wallet_code, currency_code, available_balance, reserved_balance, created_at, updated_at
        ) VALUES (
            :user_id, :wallet_code, :currency_code, :available_balance, :reserved_balance, :created_at, :updated_at
        )')->execute([
            ':user_id' => $userId,
            ':wallet_code' => $wallet['wallet_code'],
            ':currency_code' => $wallet['currency_code'],
            ':available_balance' => $wallet['available_balance'],
            ':reserved_balance' => $wallet['reserved_balance'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    $pdo->prepare('INSERT INTO user_tier_profiles (
        user_id, level, group_code, score, merchant_enabled, is_verified, daily_trade_limit,
        min_sell_amount, margin_amount, margin_ratio, risk_status, violation_message, created_at, updated_at
    ) VALUES (
        :user_id, :level, :group_code, :score, :merchant_enabled, :is_verified, :daily_trade_limit,
        :min_sell_amount, :margin_amount, :margin_ratio, :risk_status, :violation_message, :created_at, :updated_at
    )')->execute([
        ':user_id' => $userId,
        ':level' => 1,
        ':group_code' => tier_group_code_for_level(1),
        ':score' => 30,
        ':merchant_enabled' => 0,
        ':is_verified' => 0,
        ':daily_trade_limit' => 1,
        ':min_sell_amount' => '10.00000000',
        ':margin_amount' => '0.00000000',
        ':margin_ratio' => '0.0000',
        ':risk_status' => 'normal',
        ':violation_message' => null,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function admin_module_catalog(): array
{
    return [
        'users' => ['label' => '用户管理', 'permissions' => ['users.read', 'users.write']],
        'invitations' => ['label' => '邀请管理', 'permissions' => ['invitations.read', 'invitations.write']],
        'wallets' => ['label' => '资金钱包', 'permissions' => ['wallets.read', 'wallets.write']],
        'tiers' => ['label' => '等级与限额', 'permissions' => ['tiers.read', 'tiers.write']],
        'deposits' => ['label' => '充值审核', 'permissions' => ['deposits.read', 'deposits.write']],
        'deposit-addresses' => ['label' => '充值地址管理', 'permissions' => ['deposit_addresses.read', 'deposit_addresses.write']],
        'withdrawals' => ['label' => '提现审核', 'permissions' => ['withdrawals.read', 'withdrawals.write']],
        'orders' => ['label' => '订单中心', 'permissions' => ['orders.read', 'orders.write']],
        'financial-products' => ['label' => '理财配置', 'permissions' => ['financial_products.read', 'financial_products.write']],
        'financial-orders' => ['label' => '理财订单', 'permissions' => ['financial_orders.read', 'financial_orders.write']],
        'listings' => ['label' => '商户挂单', 'permissions' => ['listings.read', 'listings.write']],
        'trade-feed' => ['label' => '成交播报', 'permissions' => ['trade_feed.read', 'trade_feed.write']],
        'home-content' => ['label' => '运营配置', 'permissions' => ['home_content.read', 'home_content.write']],
        'kyc' => ['label' => '实名 / KYC', 'permissions' => ['kyc.read', 'kyc.write']],
        'payout-methods' => ['label' => '收款方式', 'permissions' => ['payout_methods.read', 'payout_methods.write']],
        'auth-events' => ['label' => '认证事件', 'permissions' => ['auth.read']],
        'system-config' => ['label' => '系统配置', 'permissions' => ['system.read', 'system.write']],
        'admin-users' => ['label' => '后台管理员', 'permissions' => ['admins.read', 'admins.write']],
    ];
}

function admin_role_template_catalog(): array
{
    return [
        'big_boss' => [
            'label' => '大老板',
            'module_access' => array_fill_keys(array_keys(admin_module_catalog()), 'write'),
        ],
        'customer_service' => [
            'label' => '客服',
            'module_access' => [
                'users' => 'read',
                'invitations' => 'read',
                'orders' => 'read',
                'auth-events' => 'read',
            ],
        ],
        'reviewer' => [
            'label' => '审核员',
            'module_access' => [
                'invitations' => 'write',
                'deposits' => 'write',
                'deposit-addresses' => 'read',
                'withdrawals' => 'write',
                'kyc' => 'write',
                'payout-methods' => 'write',
                'auth-events' => 'read',
            ],
        ],
        'finance_ops' => [
            'label' => '财务',
            'module_access' => [
                'wallets' => 'write',
                'deposits' => 'write',
                'deposit-addresses' => 'write',
                'withdrawals' => 'write',
                'orders' => 'read',
                'financial-products' => 'write',
                'financial-orders' => 'write',
            ],
        ],
    ];
}

/** 內建 + 不可被自訂覆寫的模版鍵 */
function admin_role_template_reserved_keys(): array
{
    return ['big_boss', 'super_admin', 'custom', 'customer_service', 'reviewer', 'finance_ops'];
}

/** 資料庫自訂管理員角色模版（template_key => 列資料） */
function admin_custom_role_templates_rows(PDO $pdo, ?array $admin = null): array
{
    if ($admin !== null) {
        if (admin_is_root_admin($admin)) {
            $stmt = $pdo->prepare('SELECT template_key, label, module_access_json, created_by_admin_id, created_at, updated_at FROM admin_role_templates WHERE created_by_admin_id = :admin_id OR created_by_admin_id IS NULL ORDER BY template_key ASC');
        } else {
            $stmt = $pdo->prepare('SELECT template_key, label, module_access_json, created_by_admin_id, created_at, updated_at FROM admin_role_templates WHERE created_by_admin_id = :admin_id ORDER BY template_key ASC');
        }
        $stmt->execute([':admin_id' => (int) $admin['admin_user_id']]);
    } else {
        $stmt = $pdo->query('SELECT template_key, label, module_access_json, created_by_admin_id, created_at, updated_at FROM admin_role_templates ORDER BY template_key ASC');
        if ($stmt === false) {
            return [];
        }
    }
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = trim((string) ($row['template_key'] ?? ''));
        if ($key === '') {
            continue;
        }
        $mod = json_decode((string) ($row['module_access_json'] ?? ''), true);
        if (!is_array($mod)) {
            $mod = [];
        }
        $out[$key] = [
            'label' => (string) ($row['label'] ?? $key),
            'module_access' => normalize_admin_module_access($mod),
            'created_by_admin_id' => isset($row['created_by_admin_id']) && $row['created_by_admin_id'] !== null && $row['created_by_admin_id'] !== ''
                ? (int) $row['created_by_admin_id']
                : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $out;
}

/** 內建 catalog 合併 DB 自訂模版（DB 鍵不得覆寫內建） */
function admin_role_template_all(PDO $pdo): array
{
    $merged = admin_role_template_catalog();
    foreach (admin_custom_role_templates_rows($pdo) as $k => $row) {
        if (isset($merged[$k])) {
            continue;
        }
        $merged[$k] = [
            'label' => $row['label'],
            'module_access' => $row['module_access'],
        ];
    }

    return $merged;
}

function admin_role_template_all_for_admin(PDO $pdo, array $admin): array
{
    $merged = [];
    foreach (admin_role_template_catalog() as $key => $row) {
        $access = normalize_admin_module_access($row['module_access'] ?? []);
        if (admin_module_access_within_admin_grant($admin, $access)) {
            $merged[$key] = $row;
        }
    }
    foreach (admin_custom_role_templates_rows($pdo, $admin) as $k => $row) {
        if (isset($merged[$k])) {
            continue;
        }
        $merged[$k] = [
            'label' => $row['label'],
            'module_access' => $row['module_access'],
            'created_by_admin_id' => $row['created_by_admin_id'],
        ];
    }

    return $merged;
}

function admin_role_template_key_in_use(PDO $pdo, string $key): bool
{
    $stmt = $pdo->query('SELECT role_codes FROM admin_users');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (in_array($key, admin_role_codes($row), true)) {
            return true;
        }
    }

    return false;
}

function normalize_admin_role_template_key(string $raw): string
{
    $key = trim($raw);
    if ($key === '' || !preg_match('/^[a-z][a-z0-9_]{1,62}$/', $key)) {
        failure('ADMIN_ROLE_TEMPLATE_KEY_INVALID', 'Invalid template key (use lowercase snake_case)');
    }
    if (in_array($key, admin_role_template_reserved_keys(), true)) {
        failure('ADMIN_ROLE_TEMPLATE_KEY_RESERVED', 'This template key is reserved');
    }

    return $key;
}

function admin_account_email(string $account): string
{
    return mb_strtolower($account) . '@admin.local';
}

function admin_json_array(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_filter($value, static fn ($item) => is_scalar($item) && trim((string) $item) !== ''));
    }
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return [];
    }
    return array_values(array_filter($decoded, static fn ($item) => is_scalar($item) && trim((string) $item) !== ''));
}

function admin_role_codes(array $admin): array
{
    return admin_json_array($admin['role_codes'] ?? []);
}

function admin_role_template(array $admin, ?PDO $pdo = null): string
{
    if (admin_is_root_admin($admin)) {
        return 'big_boss';
    }
    if (admin_is_super_admin($admin)) {
        return 'super_admin';
    }
    $templates = $pdo ? admin_role_template_all($pdo) : admin_role_template_catalog();
    foreach (admin_role_codes($admin) as $roleCode) {
        if (isset($templates[$roleCode])) {
            return $roleCode;
        }
    }
    return 'custom';
}

function admin_permissions(array $admin): array
{
    $permissions = admin_json_array($admin['permissions'] ?? []);
    if (!admin_is_super_admin($admin)) {
        return $permissions;
    }

    $catalogPermissions = [];
    foreach (admin_module_catalog() as $module) {
        foreach (($module['permissions'] ?? []) as $permission) {
            $catalogPermissions[] = (string) $permission;
        }
    }

    return array_values(array_unique(array_merge($permissions, $catalogPermissions)));
}

function admin_is_super_admin(array $admin): bool
{
    return in_array('super_admin', admin_role_codes($admin), true);
}

function admin_is_root_admin(array $admin): bool
{
    return trim((string) ($admin['display_name'] ?? '')) === '大老板';
}

function normalize_admin_group_code(mixed $raw): string
{
    $code = mb_strtolower(trim((string) $raw));
    $code = preg_replace('/[^a-z0-9_-]+/', '-', $code) ?? '';
    $code = trim($code, '-_');
    return mb_substr($code, 0, 64);
}

function normalize_admin_group_name(mixed $raw): string
{
    return mb_substr(trim((string) $raw), 0, 64);
}

function admin_group_code(array $admin): string
{
    return normalize_admin_group_code($admin['admin_group_code'] ?? '');
}

function admin_group_name(array $admin): string
{
    $name = normalize_admin_group_name($admin['admin_group_name'] ?? '');
    return $name !== '' ? $name : admin_group_code($admin);
}

function admin_group_rows_for_admin(PDO $pdo, array $admin): array
{
    if (admin_is_root_admin($admin)) {
        $stmt = $pdo->query('SELECT id, group_name, group_code, created_by_admin_id, created_at, updated_at FROM admin_groups ORDER BY id ASC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    $stmt = $pdo->prepare('SELECT id, group_name, group_code, created_by_admin_id, created_at, updated_at
        FROM admin_groups
        WHERE created_by_admin_id = :admin_id
        ORDER BY id ASC');
    $stmt->execute([':admin_id' => (int) ($admin['admin_user_id'] ?? $admin['id'] ?? 0)]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function serialize_admin_group(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'group_name' => (string) $row['group_name'],
        'group_code' => (string) $row['group_code'],
        'created_by_admin_id' => isset($row['created_by_admin_id']) && $row['created_by_admin_id'] !== null && $row['created_by_admin_id'] !== ''
            ? (int) $row['created_by_admin_id']
            : null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function admin_can_view_group_global_data(array $admin): bool
{
    return admin_is_root_admin($admin) || !empty($admin['can_view_group_global_data']);
}

function require_super_admin(array $admin): void
{
    if (!admin_is_super_admin($admin)) {
        failure('ADMIN_FORBIDDEN', 'Super admin required');
    }
}

function require_root_admin(array $admin): void
{
    if (!admin_is_root_admin($admin)) {
        failure('ADMIN_ROOT_REQUIRED', 'Root admin required');
    }
}

function admin_select_columns(string $alias = ''): string
{
    $p = $alias !== '' ? $alias . '.' : '';
    return $p . 'id, ' .
        $p . 'name, ' .
        $p . 'email, ' .
        $p . 'display_name, ' .
        $p . 'staff_invite_code, ' .
        $p . 'admin_group_code, ' .
        $p . 'admin_group_name, ' .
        $p . 'can_view_group_global_data, ' .
        $p . 'status, ' .
        $p . 'login_failure_count, ' .
        $p . 'login_first_failure_at, ' .
        $p . 'login_last_failure_at, ' .
        $p . 'login_locked_until, ' .
        $p . 'login_lock_level, ' .
        $p . 'login_permanent_locked_at, ' .
        $p . 'role_codes, ' .
        $p . 'permissions, ' .
        $p . 'created_by_admin_id, ' .
        $p . 'parent_admin_id, ' .
        $p . 'password_must_change, ' .
        $p . 'created_at, ' .
        $p . 'updated_at';
}

function normalize_admin_account(string $account): string
{
    return mb_strtolower(trim($account));
}

function validate_admin_account(string $account): void
{
    if (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,31}$/', $account)) {
        failure('ADMIN_ACCOUNT_INVALID', 'Admin account must use 3-32 letters, numbers, _, . or -');
    }
}

function admin_login_limit_max_attempts(): int
{
    return max(3, (int) env_string('ADMIN_LOGIN_MAX_ATTEMPTS', '5'));
}

function admin_login_limit_window_seconds(): int
{
    return max(60, (int) env_string('ADMIN_LOGIN_WINDOW_SECONDS', '900'));
}

function admin_login_limit_block_seconds(): int
{
    return max(60, (int) env_string('ADMIN_LOGIN_BLOCK_SECONDS', '900'));
}

function admin_login_rate_limit_keys(string $account, string $ip): array
{
    $normalizedAccount = normalize_admin_account($account);
    return [
        'ip:' . $ip,
        'account_ip:' . $normalizedAccount . '|' . $ip,
    ];
}

function admin_login_throttle_row(PDO $pdo, string $rateKey): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM admin_login_rate_limits WHERE rate_key = :rate_key LIMIT 1');
    $stmt->execute([':rate_key' => $rateKey]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_login_assert_not_limited(PDO $pdo, string $account, string $ip): void
{
    foreach (admin_login_rate_limit_keys($account, $ip) as $rateKey) {
        $row = admin_login_throttle_row($pdo, $rateKey);
        if (!$row || empty($row['blocked_until'])) {
            continue;
        }
        $blockedUntil = strtotime((string) $row['blocked_until']);
        if ($blockedUntil !== false && $blockedUntil > time()) {
            $retryAfter = max(1, $blockedUntil - time());
            failure('ADMIN_LOGIN_RATE_LIMITED', 'Too many admin login attempts, please try again later', [
                'retry_after_seconds' => $retryAfter,
            ], 429);
        }
    }
}

function admin_login_record_failure(PDO $pdo, string $account, string $ip): void
{
    $windowSeconds = admin_login_limit_window_seconds();
    $blockSeconds = admin_login_limit_block_seconds();
    $maxAttempts = admin_login_limit_max_attempts();
    $normalizedAccount = normalize_admin_account($account);
    $now = now_iso();

    foreach (admin_login_rate_limit_keys($normalizedAccount, $ip) as $rateKey) {
        $row = admin_login_throttle_row($pdo, $rateKey);
        $count = 1;
        $firstFailureAt = $now;
        if ($row) {
            $firstAttemptTs = !empty($row['first_failure_at']) ? strtotime((string) $row['first_failure_at']) : false;
            if ($firstAttemptTs !== false && (time() - $firstAttemptTs) < $windowSeconds) {
                $count = ((int) ($row['failure_count'] ?? 0)) + 1;
                $firstFailureAt = (string) $row['first_failure_at'];
            }
        }
        $blockedUntil = $count >= $maxAttempts ? gmdate('c', time() + $blockSeconds) : null;
        if ($row) {
            $pdo->prepare('UPDATE admin_login_rate_limits
                SET account = :account, ip = :ip, failure_count = :failure_count, first_failure_at = :first_failure_at,
                    last_attempt_at = :last_attempt_at, blocked_until = :blocked_until, updated_at = :updated_at
                WHERE rate_key = :rate_key')
                ->execute([
                    ':account' => $normalizedAccount,
                    ':ip' => $ip,
                    ':failure_count' => $count,
                    ':first_failure_at' => $firstFailureAt,
                    ':last_attempt_at' => $now,
                    ':blocked_until' => $blockedUntil,
                    ':updated_at' => $now,
                    ':rate_key' => $rateKey,
                ]);
            continue;
        }
        $pdo->prepare('INSERT INTO admin_login_rate_limits (
            rate_key, account, ip, failure_count, first_failure_at, last_attempt_at, blocked_until, created_at, updated_at
        ) VALUES (
            :rate_key, :account, :ip, :failure_count, :first_failure_at, :last_attempt_at, :blocked_until, :created_at, :updated_at
        )')->execute([
            ':rate_key' => $rateKey,
            ':account' => $normalizedAccount,
            ':ip' => $ip,
            ':failure_count' => $count,
            ':first_failure_at' => $firstFailureAt,
            ':last_attempt_at' => $now,
            ':blocked_until' => $blockedUntil,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}

function admin_login_clear_failures(PDO $pdo, string $account, string $ip): void
{
    $keys = admin_login_rate_limit_keys($account, $ip);
    $placeholders = implode(', ', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("DELETE FROM admin_login_rate_limits WHERE rate_key IN ({$placeholders})");
    $stmt->execute($keys);
}

function admin_login_account_failure_threshold(): int
{
    return 3;
}

function admin_first_lock_seconds(): int
{
    return max(60, (int) env_string('ADMIN_FIRST_LOCK_SECONDS', '1800'));
}

function admin_temp_locked_until(array $admin): ?string
{
    $lockedUntil = trim((string) ($admin['login_locked_until'] ?? ''));
    if ($lockedUntil === '') {
        return null;
    }
    $timestamp = strtotime($lockedUntil);
    if ($timestamp === false || $timestamp <= time()) {
        return null;
    }
    return $lockedUntil;
}

function admin_is_permanently_locked(array $admin): bool
{
    return (($admin['status'] ?? '') !== 'normal') && !empty($admin['login_permanent_locked_at']);
}

function admin_effective_status(array $admin): string
{
    if (admin_is_permanently_locked($admin)) {
        return 'disabled';
    }
    if (admin_temp_locked_until($admin)) {
        return 'locked';
    }
    return (string) ($admin['status'] ?? 'normal');
}

function admin_lock_state(array $admin): string
{
    if (admin_is_permanently_locked($admin)) {
        return 'permanent';
    }
    if (admin_temp_locked_until($admin)) {
        return 'temporary';
    }
    return 'none';
}

function admin_clear_account_lock_metadata(PDO $pdo, int $adminUserId, bool $resetLockLevel = false): void
{
    $pdo->prepare('UPDATE admin_users SET
        login_failure_count = 0,
        login_first_failure_at = null,
        login_last_failure_at = null,
        login_locked_until = null,
        login_lock_level = CASE WHEN :reset_lock_level = 1 THEN 0 ELSE login_lock_level END,
        login_permanent_locked_at = CASE WHEN :reset_lock_level = 1 THEN null ELSE login_permanent_locked_at END,
        updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':reset_lock_level' => $resetLockLevel ? 1 : 0,
            ':updated_at' => now_iso(),
            ':id' => $adminUserId,
        ]);
}

function admin_register_failed_login(PDO $pdo, array $admin): array
{
    $failureCount = (int) ($admin['login_failure_count'] ?? 0) + 1;
    $firstFailureAt = (string) ($admin['login_first_failure_at'] ?? '');
    $now = now_iso();
    if ($firstFailureAt === '') {
        $firstFailureAt = $now;
    }
    $lockLevel = (int) ($admin['login_lock_level'] ?? 0);
    $next = [
        'failure_count' => $failureCount,
        'first_failure_at' => $firstFailureAt,
        'last_failure_at' => $now,
        'locked_until' => null,
        'lock_level' => $lockLevel,
        'permanent_locked_at' => $admin['login_permanent_locked_at'] ?? null,
        'status' => (string) ($admin['status'] ?? 'normal'),
        'locked' => false,
        'permanent' => false,
    ];

    if ($failureCount >= admin_login_account_failure_threshold()) {
        if ($lockLevel < 1) {
            $next['failure_count'] = 0;
            $next['first_failure_at'] = null;
            $next['locked_until'] = gmdate('c', time() + admin_first_lock_seconds());
            $next['lock_level'] = 1;
            $next['locked'] = true;
        } else {
            $next['failure_count'] = 0;
            $next['first_failure_at'] = null;
            $next['locked_until'] = null;
            $next['lock_level'] = 2;
            $next['permanent_locked_at'] = $now;
            $next['status'] = 'disabled';
            $next['locked'] = true;
            $next['permanent'] = true;
        }
    }

    $pdo->prepare('UPDATE admin_users SET
        status = :status,
        login_failure_count = :login_failure_count,
        login_first_failure_at = :login_first_failure_at,
        login_last_failure_at = :login_last_failure_at,
        login_locked_until = :login_locked_until,
        login_lock_level = :login_lock_level,
        login_permanent_locked_at = :login_permanent_locked_at,
        updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':status' => $next['status'],
            ':login_failure_count' => $next['failure_count'],
            ':login_first_failure_at' => $next['first_failure_at'],
            ':login_last_failure_at' => $next['last_failure_at'],
            ':login_locked_until' => $next['locked_until'],
            ':login_lock_level' => $next['lock_level'],
            ':login_permanent_locked_at' => $next['permanent_locked_at'],
            ':updated_at' => $now,
            ':id' => (int) $admin['id'],
        ]);

    return $next;
}

function serialize_admin_user(array $item, ?PDO $pdo = null): array
{
    $roleCodes = admin_role_codes($item);
    $permissions = admin_permissions($item);
    $isSuperAdmin = in_array('super_admin', $roleCodes, true);
    $displayName = trim((string) ($item['display_name'] ?? ''));
    $staffInvite = trim((string) ($item['staff_invite_code'] ?? ''));
    return [
        'id' => (int) $item['id'],
        'account' => $item['name'],
        'display_name' => $displayName,
        'staff_invite_code' => $staffInvite !== '' ? strtoupper($staffInvite) : null,
        'admin_group_code' => admin_group_code($item) !== '' ? admin_group_code($item) : null,
        'admin_group_name' => admin_group_name($item) !== '' ? admin_group_name($item) : null,
        'can_view_group_global_data' => admin_can_view_group_global_data($item),
        'status' => (string) $item['status'],
        'display_status' => admin_effective_status($item),
        'lock_state' => admin_lock_state($item),
        'lock_level' => (int) ($item['login_lock_level'] ?? 0),
        'temporary_locked_until' => admin_temp_locked_until($item),
        'permanent_locked_at' => !empty($item['login_permanent_locked_at']) ? (string) $item['login_permanent_locked_at'] : null,
        'role_codes' => $roleCodes,
        'role_template' => admin_role_template($item, $pdo),
        'permissions' => $permissions,
        'module_access' => admin_module_access_from_permissions($permissions, $isSuperAdmin),
        'is_super_admin' => $isSuperAdmin,
        'created_by_admin_id' => isset($item['created_by_admin_id']) && $item['created_by_admin_id'] !== null && $item['created_by_admin_id'] !== ''
            ? (int) $item['created_by_admin_id']
            : null,
        'created_by_admin_account' => $item['created_by_admin_account'] ?? null,
        'created_by_admin_display_name' => $item['created_by_admin_display_name'] ?? null,
        'parent_admin_id' => isset($item['parent_admin_id']) && $item['parent_admin_id'] !== null && $item['parent_admin_id'] !== ''
            ? (int) $item['parent_admin_id']
            : null,
        'parent_admin_account' => $item['parent_admin_account'] ?? null,
        'parent_admin_display_name' => $item['parent_admin_display_name'] ?? null,
        'password_must_change' => !empty($item['password_must_change']),
        'created_at' => $item['created_at'] ?? null,
        'updated_at' => $item['updated_at'] ?? null,
    ];
}

function normalize_admin_display_name(mixed $raw): string
{
    $value = trim((string) $raw);

    return mb_substr($value, 0, 64);
}

function normalize_admin_staff_invite_code(mixed $raw): ?string
{
    $code = trim((string) $raw);
    if ($code === '') {
        return null;
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        failure('ADMIN_STAFF_INVITE_CODE_INVALID', 'Staff invite code must be 6 digits');
    }

    return $code;
}

function admin_staff_invite_code_taken(PDO $pdo, string $code, ?int $excludeAdminId): bool
{
    $inviteCheck = $pdo->prepare('SELECT 1 FROM invitation_codes WHERE code = :code LIMIT 1');
    $inviteCheck->execute([':code' => $code]);
    if ($inviteCheck->fetch()) {
        return true;
    }
    $userCheck = $pdo->prepare('SELECT 1 FROM users WHERE invitation_code = :code LIMIT 1');
    $userCheck->execute([':code' => $code]);
    if ($userCheck->fetch()) {
        return true;
    }
    $sql = 'SELECT 1 FROM admin_users WHERE staff_invite_code = :code';
    $params = [':code' => $code];
    if ($excludeAdminId !== null) {
        $sql .= ' AND id != :id';
        $params[':id'] = $excludeAdminId;
    }
    $sql .= ' LIMIT 1';
    $adminCheck = $pdo->prepare($sql);
    $adminCheck->execute($params);

    return (bool) $adminCheck->fetch();
}

function generate_admin_staff_invite_code(PDO $pdo, ?int $excludeAdminId = null): string
{
    while (true) {
        $code = (string) random_int(100000, 999999);
        if (!admin_staff_invite_code_taken($pdo, $code, $excludeAdminId)) {
            return $code;
        }
    }
}

function ensure_admin_staff_invite_code(PDO $pdo, array &$admin): string
{
    $adminId = (int) ($admin['admin_user_id'] ?? $admin['id'] ?? 0);
    $code = trim((string) ($admin['staff_invite_code'] ?? ''));
    if ($adminId <= 0) {
        return preg_match('/^\d{6}$/', $code) ? $code : '';
    }
    if (preg_match('/^\d{6}$/', $code)) {
        return $code;
    }
    $code = generate_admin_staff_invite_code($pdo, $adminId);
    $pdo->prepare('UPDATE admin_users SET staff_invite_code = :code, updated_at = :updated_at WHERE id = :id')
        ->execute([
            ':code' => $code,
            ':updated_at' => now_iso(),
            ':id' => $adminId,
        ]);
    $admin['staff_invite_code'] = $code;
    return $code;
}

function admin_visible_admin_ids(PDO $pdo, array $admin): array
{
    if (admin_is_root_admin($admin)) {
        $rows = $pdo->query('SELECT id FROM admin_users ORDER BY id ASC')->fetchAll();
        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    $rootId = (int) ($admin['admin_user_id'] ?? $admin['id'] ?? 0);
    if ($rootId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('WITH RECURSIVE admin_tree AS (
        SELECT id FROM admin_users WHERE id = :root
        UNION ALL
        SELECT a.id FROM admin_users a INNER JOIN admin_tree t ON a.parent_admin_id = t.id
    ) SELECT id FROM admin_tree ORDER BY id ASC');
    $stmt->execute([':root' => $rootId]);

    return array_map(static fn (array $row): int => (int) $row['id'], $stmt->fetchAll());
}

function admin_can_access_admin(PDO $pdo, array $admin, int $targetAdminId): bool
{
    if (admin_is_root_admin($admin)) {
        return true;
    }
    return in_array($targetAdminId, admin_visible_admin_ids($pdo, $admin), true);
}

function admin_user_scope_sql(PDO $pdo, array $admin, string $userAlias = 'u', string $paramPrefix = 'scope_admin'): array
{
    if (admin_is_root_admin($admin)) {
        return ['sql' => '', 'params' => []];
    }

    $groupCode = admin_group_code($admin);
    if (admin_can_view_group_global_data($admin) && $groupCode !== '') {
        return [
            'sql' => "COALESCE({$userAlias}.admin_group_code, '') = :{$paramPrefix}_group_code",
            'params' => [':' . $paramPrefix . '_group_code' => $groupCode],
        ];
    }

    $adminIds = admin_visible_admin_ids($pdo, $admin);
    if ($adminIds === []) {
        return ['sql' => '1 = 0', 'params' => []];
    }

    $seed = [];
    $params = [];
    foreach ($adminIds as $idx => $adminId) {
        $ph = ':' . $paramPrefix . '_' . $idx;
        $seed[] = $ph;
        $params[$ph] = $adminId;
    }
    $seedSql = implode(', ', $seed);

    return [
        'sql' => "{$userAlias}.id IN (
            WITH RECURSIVE visible_users AS (
                SELECT id FROM users WHERE invited_by_admin_id IN ({$seedSql})
                UNION ALL
                SELECT child.id FROM users child INNER JOIN visible_users vu ON child.invited_by_user_id = vu.id
            ) SELECT id FROM visible_users
        )",
        'params' => $params,
    ];
}

/** 儀表板區間：以台北日曆日換算為 UTC ISO，供與 VARCHAR created_at 比對 */
function admin_dashboard_day_range_utc(string $startYmd, string $endYmd): array
{
    $tz = new DateTimeZone('Asia/Taipei');
    $start = DateTime::createFromFormat('Y-m-d', $startYmd, $tz);
    if (!$start) {
        $start = new DateTime('today', $tz);
    }
    $start->setTime(0, 0, 0);
    $end = DateTime::createFromFormat('Y-m-d', $endYmd, $tz);
    if (!$end) {
        $end = clone $start;
    }
    $end->setTime(23, 59, 59);
    $utc = new DateTimeZone('UTC');

    return [
        'start_utc' => (clone $start)->setTimezone($utc)->format('c'),
        'end_utc' => (clone $end)->setTimezone($utc)->format('c'),
    ];
}

function admin_dashboard_bind_user_scope(string $scopeSql, string $userAlias = 'u'): string
{
    if ($scopeSql === '') {
        return '';
    }
    if ($scopeSql === '1 = 0') {
        return ' AND 1=0';
    }
    if ($userAlias !== 'u') {
        return ' AND (' . str_replace('u.', $userAlias . '.', $scopeSql) . ')';
    }

    return ' AND (' . $scopeSql . ')';
}

function admin_dashboard_count_with_user_scope(
    PDO $pdo,
    array $admin,
    string $sql,
    array $baseParams = []
): int {
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    $fullSql = $sql . admin_dashboard_bind_user_scope($scope['sql']);
    $stmt = $pdo->prepare($fullSql);
    foreach (array_merge($baseParams, $scope['params']) as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function admin_dashboard_sum_approved_usdt_equiv(
    PDO $pdo,
    array $admin,
    string $tableName,
    string $startUtc,
    string $endUtc,
    float $eurPerUsdt
): string {
    if (!in_array($tableName, ['deposit_requests', 'withdrawal_requests'], true)) {
        return '0';
    }
    $alias = 'x';
    $usdtPerEur = $eurPerUsdt > 0 ? (1.0 / $eurPerUsdt) : 0.0;
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    $scopeTail = admin_dashboard_bind_user_scope($scope['sql']);
    $sql = "SELECT COALESCE(SUM(
            CASE
                WHEN UPPER(TRIM({$alias}.asset_code)) = 'USDT' THEN CAST({$alias}.amount AS DECIMAL(36,18))
                WHEN UPPER(TRIM({$alias}.asset_code)) = 'EUR' THEN CAST({$alias}.amount AS DECIMAL(36,18)) * :eur_to_usdt
                ELSE CAST({$alias}.amount AS DECIMAL(36,18))
            END
        ), 0) AS total
        FROM {$tableName} {$alias}
        INNER JOIN users u ON u.id = {$alias}.user_id
        WHERE {$alias}.status = 'approved'
        AND {$alias}.created_at >= :range_start
        AND {$alias}.created_at <= :range_end
        {$scopeTail}";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':eur_to_usdt', $usdtPerEur);
    $stmt->bindValue(':range_start', $startUtc, PDO::PARAM_STR);
    $stmt->bindValue(':range_end', $endUtc, PDO::PARAM_STR);
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch();
    $total = $row ? (string) $row['total'] : '0';

    return number_format((float) $total, 0, '.', '');
}

function admin_dashboard_trading_orders_count(PDO $pdo, array $admin): int
{
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    $sql = 'SELECT COUNT(DISTINCT o.id) FROM c2c_orders o
        INNER JOIN users u ON (u.id = o.buyer_user_id OR u.id = o.seller_user_id)
        WHERE o.status IN ("pending_payment", "paid_pending_release", "disputed")';
    $sql .= admin_dashboard_bind_user_scope($scope['sql']);
    $stmt = $pdo->prepare($sql);
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function require_admin_can_access_user(PDO $pdo, array $admin, int $userId): void
{
    if (admin_can_access_user($pdo, $admin, $userId)) {
        return;
    }
    failure('ADMIN_FORBIDDEN', 'Permission denied');
}

function admin_can_access_user(PDO $pdo, array $admin, int $userId): bool
{
    if (admin_is_root_admin($admin)) {
        return true;
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    $stmt = $pdo->prepare('SELECT 1 FROM users u WHERE u.id = :user_id AND ' . $scope['sql'] . ' LIMIT 1');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (bool) $stmt->fetch();
}

function count_user_downline_members(PDO $pdo, int $rootUserId): int
{
    $stmt = $pdo->prepare('WITH RECURSIVE tree AS (
        SELECT id FROM users WHERE invited_by_user_id = :root
        UNION ALL
        SELECT u.id FROM users u INNER JOIN tree t ON u.invited_by_user_id = t.id
    ) SELECT COUNT(*) FROM tree');
    $stmt->execute([':root' => $rootUserId]);

    return (int) $stmt->fetchColumn();
}

/**
 * @return list<array{id:int, username:string, depth:int, invited_by_user_id:?int, invited_by_admin_id:?int}>
 */
function list_user_downline_members(PDO $pdo, int $rootUserId): array
{
    $stmt = $pdo->prepare('WITH RECURSIVE tree AS (
        SELECT id, username, invited_by_user_id, invited_by_admin_id, 1 AS depth FROM users WHERE invited_by_user_id = :root
        UNION ALL
        SELECT u.id, u.username, u.invited_by_user_id, u.invited_by_admin_id, t.depth + 1
        FROM users u INNER JOIN tree t ON u.invited_by_user_id = t.id
    ) SELECT id, username, invited_by_user_id, invited_by_admin_id, depth FROM tree ORDER BY depth ASC, id ASC');
    $stmt->execute([':root' => $rootUserId]);
    $rows = $stmt->fetchAll();
    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'depth' => (int) $row['depth'],
            'invited_by_user_id' => $row['invited_by_user_id'] !== null ? (int) $row['invited_by_user_id'] : null,
            'invited_by_admin_id' => $row['invited_by_admin_id'] !== null ? (int) $row['invited_by_admin_id'] : null,
        ];
    }

    return $items;
}

function format_user_upline_label(
    ?int $invitedByUserId,
    ?int $invitedByAdminId,
    ?string $inviterUsername,
    ?string $adminDisplayName,
    ?string $adminAccount
): string {
    if ($invitedByAdminId !== null && $invitedByAdminId > 0) {
        $label = trim((string) ($adminDisplayName !== '' && $adminDisplayName !== null ? $adminDisplayName : ($adminAccount ?? '')));

        return $label !== '' ? ('管理＊' . $label) : '管理＊—';
    }
    if ($invitedByUserId !== null && $invitedByUserId > 0 && $inviterUsername !== null && $inviterUsername !== '') {
        return '会员﹡' . $inviterUsername;
    }

    return '-';
}

function normalize_admin_module_access(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $normalized = [];
    foreach ($value as $moduleKey => $accessLevel) {
        $module = trim((string) $moduleKey);
        $level = trim((string) $accessLevel);
        if ($module === '' || $level === '') {
            continue;
        }
        $normalized[$module] = $level;
    }
    return $normalized;
}

function normalize_admin_role_template(mixed $value, ?PDO $pdo = null): string
{
    $template = trim((string) $value);
    if ($template === '' || $template === 'custom' || $template === 'super_admin') {
        return $template === 'super_admin' ? 'custom' : ($template ?: 'custom');
    }
    $catalog = $pdo ? admin_role_template_all($pdo) : admin_role_template_catalog();

    return array_key_exists($template, $catalog) ? $template : 'custom';
}

function admin_module_access_from_permissions(array $permissions, bool $isSuperAdmin = false): array
{
    $catalog = admin_module_catalog();
    if ($isSuperAdmin) {
        return array_fill_keys(array_keys($catalog), 'write');
    }
    $lookup = array_fill_keys($permissions, true);
    $access = [];
    foreach ($catalog as $moduleKey => $meta) {
        $hasRead = false;
        $hasWrite = false;
        foreach ($meta['permissions'] as $permission) {
            if (!isset($lookup[$permission])) {
                continue;
            }
            if (str_ends_with($permission, '.write')) {
                $hasWrite = true;
            } else {
                $hasRead = true;
            }
        }
        if ($hasWrite) {
            $access[$moduleKey] = 'write';
        } elseif ($hasRead) {
            $access[$moduleKey] = 'read';
        }
    }
    return $access;
}

function admin_module_access_within_grant(array $grantAccess, array $requestedAccess): bool
{
    foreach ($requestedAccess as $moduleKey => $accessLevel) {
        $requested = trim((string) $accessLevel);
        if (!in_array($requested, ['read', 'write'], true)) {
            return false;
        }
        $granted = $grantAccess[$moduleKey] ?? '';
        if ($granted === 'write') {
            continue;
        }
        if ($granted === 'read' && $requested === 'read') {
            continue;
        }
        return false;
    }

    return true;
}

function admin_module_access_within_admin_grant(array $admin, array $requestedAccess): bool
{
    $grantAccess = admin_module_access_from_permissions(
        admin_permissions($admin),
        in_array('super_admin', admin_role_codes($admin), true)
    );

    return admin_module_access_within_grant($grantAccess, $requestedAccess);
}

function require_admin_module_access_within_grant(array $admin, array $requestedAccess): void
{
    if (!admin_module_access_within_admin_grant($admin, $requestedAccess)) {
        failure('ADMIN_PERMISSION_EXCEEDS_GRANT', 'Cannot grant permissions beyond current admin permissions');
    }
}

function admin_role_template_owner_sql(array $admin): array
{
    $sql = 'created_by_admin_id = :owner_admin_id';
    if (admin_is_root_admin($admin)) {
        $sql = '(created_by_admin_id = :owner_admin_id OR created_by_admin_id IS NULL)';
    }

    return [
        'sql' => $sql,
        'params' => [':owner_admin_id' => (int) $admin['admin_user_id']],
    ];
}

function admin_permissions_from_access(array $moduleAccess, bool $isSuperAdmin = false): array
{
    $catalog = admin_module_catalog();
    $resolved = [];
    $accessMap = $isSuperAdmin ? array_fill_keys(array_keys($catalog), 'write') : $moduleAccess;
    foreach ($accessMap as $moduleKey => $accessLevel) {
        if (!isset($catalog[$moduleKey])) {
            continue;
        }
        $level = trim((string) $accessLevel);
        if (!in_array($level, ['read', 'write'], true)) {
            continue;
        }
        foreach ($catalog[$moduleKey]['permissions'] as $permission) {
            if ($level === 'read' && str_ends_with($permission, '.write')) {
                continue;
            }
            $resolved[$permission] = true;
        }
    }
    return array_keys($resolved);
}

function admin_required_permissions_for_request(string $path, string $method): array
{
    $readWriteMap = [
        '#^/api/admin/dashboard-summary$#' => ['read' => ['users.read', 'deposits.read', 'withdrawals.read', 'orders.read'], 'write' => []],
        '#^/api/admin/users(?:/\d+|/batch)?(?:/audit-logs)?$#' => ['read' => ['users.read'], 'write' => ['users.write']],
        '#^/api/admin/users/\d+/network-members$#' => ['read' => ['users.read'], 'write' => ['users.write']],
        '#^/api/admin/users/\d+/status$#' => ['write' => ['users.write']],
        '#^/api/admin/users/\d+/invitation-status$#' => ['write' => ['users.write']],
        '#^/api/admin/invitations(?:/\d+)?$#' => ['read' => ['invitations.read'], 'write' => ['invitations.write']],
        '#^/api/admin/users/\d+/invitations$#' => ['write' => ['invitations.write']],
        '#^/api/admin/invitations/\d+/disable$#' => ['write' => ['invitations.write']],
        '#^/api/admin/verifications(?:/\d+)?$#' => ['read' => ['auth.read']],
        '#^/api/admin/users/\d+/wallets(?:/[A-Za-z0-9_\-]+)?(?:/ledger)?$#' => ['read' => ['wallets.read'], 'write' => ['wallets.write']],
        '#^/api/admin/users/\d+/tier-profile$#' => ['read' => ['tiers.read'], 'write' => ['tiers.write']],
        '#^/api/admin/tier-templates(?:/\d+)?$#' => ['read' => ['tiers.read'], 'write' => ['tiers.write']],
        '#^/api/admin/deposit-requests(?:/\d+)?$#' => ['read' => ['deposits.read'], 'write' => ['deposits.write']],
        '#^/api/admin/deposit-addresses(?:/\d+)?$#' => ['read' => ['deposit_addresses.read'], 'write' => ['deposit_addresses.write']],
        '#^/api/admin/financial-products(?:/\d+)?$#' => ['read' => ['financial_products.read'], 'write' => ['financial_products.write']],
        '#^/api/admin/uploads$#' => ['write' => ['deposit_addresses.write', 'home_content.write']],
        '#^/api/admin/withdrawal-requests(?:/\d+)?$#' => ['read' => ['withdrawals.read'], 'write' => ['withdrawals.write']],
        '#^/api/admin/withdrawal-settings/eur-swap(?:/(?:copy|history))?$#' => ['read' => ['withdrawals.read'], 'write' => ['withdrawals.write']],
        '#^/api/admin/orders(?:/\d+)?$#' => ['read' => ['orders.read'], 'write' => ['orders.write']],
        '#^/api/admin/orders/\d+/(status|release|dispute|evidences)$#' => ['write' => ['orders.write']],
        '#^/api/admin/financial-subscriptions(?:/\d+)?$#' => ['read' => ['financial_orders.read'], 'write' => ['financial_orders.write']],
        '#^/api/admin/financial-subscriptions/\d+/(return-policy|return)$#' => ['write' => ['financial_orders.write']],
        '#^/api/admin/kyc-applications(?:/\d+)?$#' => ['read' => ['kyc.read'], 'write' => ['kyc.write']],
        '#^/api/admin/payout-methods(?:/\d+)?$#' => ['read' => ['payout_methods.read'], 'write' => ['payout_methods.write']],
        '#^/api/admin/listings(?:/\d+)?$#' => ['read' => ['listings.read'], 'write' => ['listings.write']],
        '#^/api/admin/trade-feed-events(?:/(?:random|\d+))?$#' => ['read' => ['trade_feed.read'], 'write' => ['trade_feed.write']],
        '#^/api/admin/home-content(?:/notice|/banners(?:/\d+)?|/tutorial-links(?:/\d+)?)?$#' => ['read' => ['home_content.read'], 'write' => ['home_content.write']],
        '#^/api/admin/auth/events(?:/\d+)?$#' => ['read' => ['auth.read']],
        '#^/api/admin/system/configs(?:/[A-Za-z0-9_\-]+/[A-Za-z0-9_\-]+)?$#' => ['read' => ['system.read'], 'write' => ['system.write']],
        '#^/api/admin/admin-users(?:/\d+)?(?:/password|/audit-logs|/unlock)?$#' => ['read' => ['admins.read'], 'write' => ['admins.write']],
        '#^/api/admin/admin-groups(?:/\d+)?$#' => ['read' => ['admins.read'], 'write' => ['admins.write']],
        '#^/api/admin/role-templates(?:/[a-z0-9_]+)?$#' => ['read' => ['admins.read'], 'write' => ['admins.write']],
        '#^/api/admin/support-tickets(?:/\d+)?$#' => ['read' => ['system.read'], 'write' => ['system.write']],
    ];

    $action = in_array($method, ['POST', 'PATCH', 'DELETE'], true) ? 'write' : 'read';
    foreach ($readWriteMap as $pattern => $permissionMap) {
        if (preg_match($pattern, $path)) {
            return $permissionMap[$action] ?? [];
        }
    }
    return [];
}

function require_admin_request_permission(array $admin, string $path, string $method): void
{
    if (admin_is_super_admin($admin)) {
        return;
    }
    $requiredPermissions = admin_required_permissions_for_request($path, $method);
    if ($requiredPermissions === []) {
        return;
    }
    $grantedPermissions = array_fill_keys(admin_permissions($admin), true);
    foreach ($requiredPermissions as $permission) {
        if (isset($grantedPermissions[$permission])) {
            return;
        }
    }
    failure('ADMIN_FORBIDDEN', 'Permission denied');
}

function require_admin(PDO $pdo): array
{
    enforce_admin_ip_allowlist();
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        failure('ADMIN_UNAUTHORIZED', 'Unauthorized');
    }

    $token = trim($matches[1]);
    $stmt = $pdo->prepare('SELECT t.*, a.name, a.email, a.display_name, a.staff_invite_code, a.status, a.role_codes, a.permissions, a.admin_group_code, a.admin_group_name, a.can_view_group_global_data, a.created_by_admin_id, a.parent_admin_id, a.password_must_change
        FROM admin_tokens t
        JOIN admin_users a ON a.id = t.admin_user_id
        WHERE t.token = :token AND t.revoked_at IS NULL');
    $stmt->execute([':token' => $token]);
    $admin = $stmt->fetch();

    if (!$admin) {
        failure('ADMIN_TOKEN_INVALID', 'Admin token is invalid');
    }

    if (strtotime((string) $admin['expires_at']) < time()) {
        failure('ADMIN_TOKEN_EXPIRED', 'Admin token is expired');
    }

    if (($admin['status'] ?? '') !== 'normal') {
        failure('ADMIN_ACCOUNT_LOCKED', 'Admin account is locked');
    }
    ensure_admin_staff_invite_code($pdo, $admin);

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $passwordAllowedPaths = ['/api/admin/auth/me', '/api/admin/auth/logout', '/api/admin/auth/password'];
    if (!empty($admin['password_must_change']) && !in_array($requestPath, $passwordAllowedPaths, true)) {
        failure('ADMIN_PASSWORD_CHANGE_REQUIRED', 'Password change required', null, 403);
    }
    require_admin_request_permission($admin, $requestPath, $requestMethod);

    return $admin;
}

function json_bool(mixed $value): bool
{
    return filter_var($value, FILTER_VALIDATE_BOOL);
}

function require_user(PDO $pdo): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        failure('AUTH_UNAUTHORIZED', 'Unauthorized');
    }

    $token = trim($matches[1]);
    $stmt = $pdo->prepare('SELECT t.*, u.username, u.email, u.mobile, u.country_code, u.mobile_e164, u.status, u.lang, u.avatar_id, u.invitation_code
        FROM user_tokens t
        JOIN users u ON u.id = t.user_id
        WHERE t.token = :token AND t.revoked_at IS NULL
        LIMIT 1');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        failure('AUTH_TOKEN_INVALID', 'Token is invalid');
    }
    if (strtotime((string) $user['expires_at']) < time()) {
        failure('AUTH_TOKEN_EXPIRED', 'Token is expired');
    }
    if (($user['status'] ?? '') !== 'normal') {
        failure('AUTH_ACCOUNT_LOCKED', 'Account is locked');
    }

    $user['user_id'] = (int) $user['user_id'];
    return $user;
}

function user_avatar_ids(): array
{
    return [
        'male-aurora',
        'male-slate',
        'male-mint',
        'female-rose',
        'female-sky',
        'female-violet',
    ];
}

function normalize_user_avatar_id(?string $avatarId): ?string
{
    $avatarId = trim((string) $avatarId);
    if ($avatarId === '') {
        return null;
    }
    return in_array($avatarId, user_avatar_ids(), true) ? $avatarId : null;
}

function random_user_avatar_id(): string
{
    $ids = user_avatar_ids();
    return $ids[random_int(0, count($ids) - 1)];
}

function ensure_user_avatar_id(PDO $pdo, array $user): string
{
    $avatarId = normalize_user_avatar_id($user['avatar_id'] ?? null);
    if ($avatarId !== null) {
        return $avatarId;
    }
    $avatarId = random_user_avatar_id();
    $pdo->prepare('UPDATE users SET avatar_id = :avatar_id, updated_at = :updated_at WHERE id = :user_id')
        ->execute([
            ':avatar_id' => $avatarId,
            ':updated_at' => now_iso(),
            ':user_id' => (int) $user['user_id'],
        ]);
    return $avatarId;
}

function user_me_payload(PDO $pdo, array $user): array
{
    $tierStmt = $pdo->prepare('SELECT * FROM user_tier_profiles WHERE user_id = :user_id LIMIT 1');
    $tierStmt->execute([':user_id' => $user['user_id']]);
    $tier = $tierStmt->fetch() ?: [];

    $kycStmt = $pdo->prepare('SELECT legal_name, status, reviewed_at, submitted_at
        FROM user_kyc_applications
        WHERE user_id = :user_id
        ORDER BY id DESC
        LIMIT 1');
    $kycStmt->execute([':user_id' => $user['user_id']]);
    $kyc = $kycStmt->fetch() ?: [];
    $legalName = trim((string) ($kyc['legal_name'] ?? ''));
    $username = trim((string) $user['username']);
    $displayCode = $username;
    if (preg_match('/^EN\d{6}$/i', $username)) {
        $displayCode = strtoupper($username);
    } elseif (preg_match('/^EU\d{6}$/i', $username)) {
        $displayCode = 'EN' . substr($username, 2);
    } else {
        $displayCode = 'EN' . str_pad((string) ((int) $user['user_id']), 6, '0', STR_PAD_LEFT);
    }

    return [
        'id' => (int) $user['user_id'],
        'username' => $username,
        'display_code' => $displayCode,
        'real_name' => $legalName !== '' ? $legalName : null,
        'legal_name' => $legalName !== '' ? $legalName : null,
        'email' => $user['email'] !== null ? (string) $user['email'] : null,
        'mobile' => $user['mobile'] !== null ? (string) $user['mobile'] : null,
        'country_code' => $user['country_code'] !== null ? (string) $user['country_code'] : null,
        'mobile_e164' => $user['mobile_e164'] !== null ? (string) $user['mobile_e164'] : null,
        'status' => (string) $user['status'],
        'lang' => (string) $user['lang'],
        'avatar_id' => ensure_user_avatar_id($pdo, $user),
        'invitation_code' => (string) $user['invitation_code'],
        'tier' => [
            'level' => (int) ($tier['level'] ?? 0),
            'group_code' => $tier['group_code'] ?? null,
            'score' => (int) ($tier['score'] ?? 0),
            'merchant_enabled' => !empty($tier['merchant_enabled']),
            'is_verified' => !empty($tier['is_verified']),
            'risk_status' => (string) ($tier['risk_status'] ?? 'normal'),
        ],
        'kyc' => [
            'legal_name' => $legalName !== '' ? $legalName : null,
            'status' => isset($kyc['status']) ? (string) $kyc['status'] : null,
            'submitted_at' => $kyc['submitted_at'] ?? null,
            'reviewed_at' => $kyc['reviewed_at'] ?? null,
        ],
    ];
}

function require_user_exists(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, username, email, mobile_e164, status FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }

    $user['id'] = (int) $user['id'];
    return $user;
}

function insert_wallet_ledger(PDO $pdo, array $entry): void
{
    $pdo->prepare('INSERT INTO wallet_ledger (
        user_id, wallet_id, wallet_code, currency_code, change_type, source_type, source_id,
        available_delta, reserved_delta, available_before, reserved_before, available_after, reserved_after,
        operator_type, operator_id, reason, metadata_json, created_at
    ) VALUES (
        :user_id, :wallet_id, :wallet_code, :currency_code, :change_type, :source_type, :source_id,
        :available_delta, :reserved_delta, :available_before, :reserved_before, :available_after, :reserved_after,
        :operator_type, :operator_id, :reason, :metadata_json, :created_at
    )')->execute([
        ':user_id' => (int) $entry['user_id'],
        ':wallet_id' => $entry['wallet_id'] !== null ? (int) $entry['wallet_id'] : null,
        ':wallet_code' => (string) $entry['wallet_code'],
        ':currency_code' => (string) $entry['currency_code'],
        ':change_type' => (string) $entry['change_type'],
        ':source_type' => (string) $entry['source_type'],
        ':source_id' => $entry['source_id'] !== null ? (int) $entry['source_id'] : null,
        ':available_delta' => (string) $entry['available_delta'],
        ':reserved_delta' => (string) $entry['reserved_delta'],
        ':available_before' => (string) $entry['available_before'],
        ':reserved_before' => (string) $entry['reserved_before'],
        ':available_after' => (string) $entry['available_after'],
        ':reserved_after' => (string) $entry['reserved_after'],
        ':operator_type' => $entry['operator_type'] ?? null,
        ':operator_id' => isset($entry['operator_id']) && $entry['operator_id'] !== null ? (int) $entry['operator_id'] : null,
        ':reason' => $entry['reason'] ?? null,
        ':metadata_json' => !empty($entry['metadata']) ? json_encode($entry['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ':created_at' => $entry['created_at'] ?? now_iso(),
    ]);
}

function apply_wallet_delta(PDO $pdo, int $userId, string $walletCode, float $availableDelta, float $reservedDelta = 0.0, array $ledgerMeta = []): array
{
    $stmt = $pdo->prepare('SELECT * FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
    $stmt->execute([
        ':user_id' => $userId,
        ':wallet_code' => $walletCode,
    ]);
    $wallet = $stmt->fetch();
    if (!$wallet) {
        failure('ADMIN_WALLET_NOT_FOUND', 'Wallet not found');
    }

    $available = (float) $wallet['available_balance'] + $availableDelta;
    $reserved = (float) $wallet['reserved_balance'] + $reservedDelta;
    if ($available < 0 || $reserved < 0) {
        failure('ADMIN_WALLET_BALANCE_INSUFFICIENT', 'Wallet balance is insufficient');
    }

    $next = [
        'available_balance' => number_format($available, 8, '.', ''),
        'reserved_balance' => number_format($reserved, 8, '.', ''),
        'updated_at' => now_iso(),
    ];

    $pdo->prepare('UPDATE user_wallet_balances
        SET available_balance = :available_balance, reserved_balance = :reserved_balance, updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':available_balance' => $next['available_balance'],
            ':reserved_balance' => $next['reserved_balance'],
            ':updated_at' => $next['updated_at'],
            ':id' => $wallet['id'],
        ]);

    if (abs($availableDelta) > 0.000000001 || abs($reservedDelta) > 0.000000001) {
        insert_wallet_ledger($pdo, [
            'user_id' => $userId,
            'wallet_id' => (int) $wallet['id'],
            'wallet_code' => $walletCode,
            'currency_code' => (string) $wallet['currency_code'],
            'change_type' => $ledgerMeta['change_type'] ?? 'wallet_delta',
            'source_type' => $ledgerMeta['source_type'] ?? 'system',
            'source_id' => $ledgerMeta['source_id'] ?? null,
            'available_delta' => number_format($availableDelta, 8, '.', ''),
            'reserved_delta' => number_format($reservedDelta, 8, '.', ''),
            'available_before' => (string) $wallet['available_balance'],
            'reserved_before' => (string) $wallet['reserved_balance'],
            'available_after' => $next['available_balance'],
            'reserved_after' => $next['reserved_balance'],
            'operator_type' => $ledgerMeta['operator_type'] ?? null,
            'operator_id' => $ledgerMeta['operator_id'] ?? null,
            'reason' => $ledgerMeta['reason'] ?? null,
            'metadata' => $ledgerMeta['metadata'] ?? null,
            'created_at' => $next['updated_at'],
        ]);
    }

    return [
        'wallet_id' => (int) $wallet['id'],
        'before' => [
            'available_balance' => $wallet['available_balance'],
            'reserved_balance' => $wallet['reserved_balance'],
        ],
        'after' => $next,
        'wallet_code' => $walletCode,
    ];
}

function ensure_wallet_balance(PDO $pdo, int $userId, string $walletCode, string $currencyCode): void
{
    $stmt = $pdo->prepare('SELECT id FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
    $stmt->execute([
        ':user_id' => $userId,
        ':wallet_code' => $walletCode,
    ]);
    if ($stmt->fetch()) {
        return;
    }

    $now = now_iso();
    $pdo->prepare('INSERT INTO user_wallet_balances (
        user_id, wallet_code, currency_code, available_balance, reserved_balance, created_at, updated_at
    ) VALUES (
        :user_id, :wallet_code, :currency_code, "0.00000000", "0.00000000", :created_at, :updated_at
    )')->execute([
        ':user_id' => $userId,
        ':wallet_code' => $walletCode,
        ':currency_code' => $currencyCode,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function c2c_fiat_wallet_meta(string $fiatCode): array
{
    $resolvedFiatCode = strtoupper(trim($fiatCode));

    return match ($resolvedFiatCode) {
        'EUR' => ['wallet_code' => 'eur', 'currency_code' => 'EUR'],
        'USD' => ['wallet_code' => 'usd', 'currency_code' => 'USD'],
        'CNY' => ['wallet_code' => 'cny', 'currency_code' => 'CNY'],
        default => [
            'wallet_code' => strtolower($resolvedFiatCode !== '' ? $resolvedFiatCode : 'eur'),
            'currency_code' => $resolvedFiatCode !== '' ? $resolvedFiatCode : 'EUR',
        ],
    };
}

function reserve_sell_order_assets(PDO $pdo, int $sellerUserId, float $amount): void
{
    ensure_wallet_balance($pdo, $sellerUserId, 'cash_usdt', 'USDT');

    $stmt = $pdo->prepare('SELECT available_balance FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = "cash_usdt" LIMIT 1');
    $stmt->execute([':user_id' => $sellerUserId]);
    $wallet = $stmt->fetch() ?: ['available_balance' => '0.00000000'];
    if ((float) $wallet['available_balance'] < $amount) {
        failure('AUTH_WALLET_BALANCE_INSUFFICIENT', 'Insufficient USDT balance for sell order');
    }

    apply_wallet_delta($pdo, $sellerUserId, 'cash_usdt', -$amount, $amount);
}

function reserve_buy_order_fiat(PDO $pdo, int $buyerUserId, string $fiatCode, float $totalAmount): void
{
    $fiatWallet = c2c_fiat_wallet_meta($fiatCode);
    $walletCode = (string) $fiatWallet['wallet_code'];
    ensure_wallet_balance($pdo, $buyerUserId, $walletCode, (string) $fiatWallet['currency_code']);

    $stmt = $pdo->prepare('SELECT available_balance FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
    $stmt->execute([
        ':user_id' => $buyerUserId,
        ':wallet_code' => $walletCode,
    ]);
    $wallet = $stmt->fetch() ?: ['available_balance' => '0.00000000'];
    if ((float) $wallet['available_balance'] < $totalAmount) {
        failure('AUTH_WALLET_BALANCE_INSUFFICIENT', 'Insufficient fiat balance for buy order');
    }

    apply_wallet_delta($pdo, $buyerUserId, $walletCode, -$totalAmount, $totalAmount, [
        'change_type' => 'c2c_buy_reserve',
        'source_type' => 'c2c_order',
        'reason' => 'Reserve fiat for C2C buy order',
    ]);
}

function refund_sell_order_assets(PDO $pdo, array $order): void
{
    if (($order['side'] ?? '') !== 'sell') {
        return;
    }

    $sellerUserId = (int) ($order['seller_user_id'] ?? 0);
    $amount = (float) ($order['amount'] ?? 0);
    if ($sellerUserId <= 0 || $amount <= 0) {
        return;
    }

    ensure_wallet_balance($pdo, $sellerUserId, 'cash_usdt', 'USDT');
    $stmt = $pdo->prepare('SELECT reserved_balance FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = "cash_usdt" LIMIT 1');
    $stmt->execute([':user_id' => $sellerUserId]);
    $wallet = $stmt->fetch() ?: ['reserved_balance' => '0.00000000'];
    $refundAmount = min((float) $wallet['reserved_balance'], $amount);
    if ($refundAmount <= 0) {
        return;
    }

    apply_wallet_delta($pdo, $sellerUserId, 'cash_usdt', $refundAmount, -$refundAmount);
}

function refund_buy_order_fiat(PDO $pdo, array $order): void
{
    if (($order['side'] ?? '') !== 'buy') {
        return;
    }

    $buyerUserId = (int) ($order['buyer_user_id'] ?? 0);
    $totalAmount = (float) ($order['total_amount'] ?? 0);
    if ($buyerUserId <= 0 || $totalAmount <= 0) {
        return;
    }

    $fiatWallet = c2c_fiat_wallet_meta((string) ($order['fiat_code'] ?? 'EUR'));
    $walletCode = (string) $fiatWallet['wallet_code'];
    ensure_wallet_balance($pdo, $buyerUserId, $walletCode, (string) $fiatWallet['currency_code']);
    $stmt = $pdo->prepare('SELECT reserved_balance FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
    $stmt->execute([
        ':user_id' => $buyerUserId,
        ':wallet_code' => $walletCode,
    ]);
    $wallet = $stmt->fetch() ?: ['reserved_balance' => '0.00000000'];
    $refundAmount = min((float) $wallet['reserved_balance'], $totalAmount);
    if ($refundAmount <= 0) {
        return;
    }

    apply_wallet_delta($pdo, $buyerUserId, $walletCode, $refundAmount, -$refundAmount, [
        'change_type' => 'c2c_buy_refund',
        'source_type' => 'c2c_order',
        'source_id' => (int) ($order['id'] ?? 0) ?: null,
        'reason' => 'Refund fiat for cancelled C2C buy order',
    ]);
}

function consume_sell_order_assets(PDO $pdo, int $sellerUserId, float $amount): void
{
    ensure_wallet_balance($pdo, $sellerUserId, 'cash_usdt', 'USDT');
    $stmt = $pdo->prepare('SELECT available_balance, reserved_balance FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = "cash_usdt" LIMIT 1');
    $stmt->execute([':user_id' => $sellerUserId]);
    $wallet = $stmt->fetch() ?: ['available_balance' => '0.00000000', 'reserved_balance' => '0.00000000'];

    $reservedBalance = (float) ($wallet['reserved_balance'] ?? 0);
    $availableBalance = (float) ($wallet['available_balance'] ?? 0);
    if (($reservedBalance + $availableBalance) < $amount) {
        failure('AUTH_WALLET_BALANCE_INSUFFICIENT', 'Insufficient USDT balance for sell settlement');
    }

    $reservedAmount = min($reservedBalance, $amount);
    if ($reservedAmount > 0) {
        apply_wallet_delta($pdo, $sellerUserId, 'cash_usdt', 0.0, -$reservedAmount);
    }

    $remainingAmount = $amount - $reservedAmount;
    if ($remainingAmount > 0) {
        apply_wallet_delta($pdo, $sellerUserId, 'cash_usdt', -$remainingAmount, 0.0);
    }
}

function consume_buy_order_fiat(PDO $pdo, int $buyerUserId, string $fiatCode, float $totalAmount): void
{
    $fiatWallet = c2c_fiat_wallet_meta($fiatCode);
    $walletCode = (string) $fiatWallet['wallet_code'];
    ensure_wallet_balance($pdo, $buyerUserId, $walletCode, (string) $fiatWallet['currency_code']);
    $stmt = $pdo->prepare('SELECT available_balance, reserved_balance FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
    $stmt->execute([
        ':user_id' => $buyerUserId,
        ':wallet_code' => $walletCode,
    ]);
    $wallet = $stmt->fetch() ?: ['available_balance' => '0.00000000', 'reserved_balance' => '0.00000000'];

    $reservedBalance = (float) ($wallet['reserved_balance'] ?? 0);
    $availableBalance = (float) ($wallet['available_balance'] ?? 0);
    if (($reservedBalance + $availableBalance) < $totalAmount) {
        failure('AUTH_WALLET_BALANCE_INSUFFICIENT', 'Insufficient fiat balance for buy settlement');
    }

    $reservedAmount = min($reservedBalance, $totalAmount);
    if ($reservedAmount > 0) {
        apply_wallet_delta($pdo, $buyerUserId, $walletCode, 0.0, -$reservedAmount, [
            'change_type' => 'c2c_buy_settle',
            'source_type' => 'c2c_order',
            'reason' => 'Consume reserved fiat for C2C buy order',
        ]);
    }

    $remainingAmount = $totalAmount - $reservedAmount;
    if ($remainingAmount > 0) {
        apply_wallet_delta($pdo, $buyerUserId, $walletCode, -$remainingAmount, 0.0, [
            'change_type' => 'c2c_buy_settle',
            'source_type' => 'c2c_order',
            'reason' => 'Consume fiat for C2C buy order',
        ]);
    }
}

function settle_completed_c2c_order(PDO $pdo, array $order): void
{
    $amount = (float) ($order['amount'] ?? 0);
    $totalAmount = (float) ($order['total_amount'] ?? 0);
    if ($amount <= 0) {
        return;
    }

    if (($order['side'] ?? '') === 'buy') {
        $buyerUserId = (int) ($order['buyer_user_id'] ?? 0);
        consume_buy_order_fiat($pdo, $buyerUserId, (string) ($order['fiat_code'] ?? 'EUR'), $totalAmount);
        ensure_wallet_balance($pdo, $buyerUserId, 'cash_usdt', 'USDT');
        apply_wallet_delta($pdo, $buyerUserId, 'cash_usdt', $amount, 0.0, [
            'change_type' => 'c2c_buy_receive',
            'source_type' => 'c2c_order',
            'source_id' => (int) ($order['id'] ?? 0) ?: null,
            'reason' => 'Receive USDT from completed C2C buy order',
        ]);
        return;
    }

    if (($order['side'] ?? '') === 'sell') {
        $sellerUserId = (int) ($order['seller_user_id'] ?? 0);
        consume_sell_order_assets($pdo, $sellerUserId, $amount);
        $fiatWallet = c2c_fiat_wallet_meta((string) ($order['fiat_code'] ?? 'USD'));
        ensure_wallet_balance($pdo, $sellerUserId, (string) $fiatWallet['wallet_code'], (string) $fiatWallet['currency_code']);
        apply_wallet_delta($pdo, $sellerUserId, (string) $fiatWallet['wallet_code'], $totalAmount, 0.0);
    }
}

function restore_c2c_listing_inventory(PDO $pdo, array $order): void
{
    $listingId = (int) ($order['listing_id'] ?? 0);
    $amount = (float) ($order['amount'] ?? 0);
    if ($listingId <= 0 || $amount <= 0) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE c2c_listings
        SET available_amount = CAST(available_amount AS DECIMAL(24,8)) + :amount,
            updated_at = :updated_at
        WHERE id = :id');
    $stmt->execute([
        ':amount' => number_format($amount, 8, '.', ''),
        ':updated_at' => now_iso(),
        ':id' => $listingId,
    ]);
}

function financial_product_catalog(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT *
        FROM financial_products
        WHERE status = "active"
        ORDER BY sort_order ASC, id ASC');
    return $stmt ? $stmt->fetchAll() : [];
}

function user_financial_subscribed_amount(PDO $pdo, int $userId, string $productCode): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(CAST(amount AS DECIMAL(24,8))), 0)
        FROM user_financial_subscriptions
        WHERE user_id = :user_id AND product_code = :product_code AND status IN ("active", "pending_settlement")');
    $stmt->execute([
        ':user_id' => $userId,
        ':product_code' => $productCode,
    ]);
    return (float) $stmt->fetchColumn();
}

function serialize_financial_product(PDO $pdo, int $userId, array $product): array
{
    $walletStmt = $pdo->prepare('SELECT available_balance
        FROM user_wallet_balances
        WHERE user_id = :user_id AND wallet_code = :wallet_code
        LIMIT 1');
    $walletStmt->execute([
        ':user_id' => $userId,
        ':wallet_code' => (string) $product['wallet_code'],
    ]);
    $wallet = $walletStmt->fetch() ?: ['available_balance' => '0.00000000'];

    $userSubscribed = user_financial_subscribed_amount($pdo, $userId, (string) $product['product_code']);
    $personalLimit = (float) $product['personal_limit_amount'];
    $totalQuota = (float) $product['total_quota_amount'];
    $soldQuota = (float) $product['sold_quota_amount'];
    $remainingQuota = max(0.0, $totalQuota - $soldQuota);
    $userRemainingQuota = max(0.0, $personalLimit - $userSubscribed);

    return [
        'id' => (int) $product['id'],
        'product_code' => (string) $product['product_code'],
        'asset_code' => (string) $product['asset_code'],
        'wallet_code' => (string) $product['wallet_code'],
        'display_name' => trim((string) ($product['display_name'] ?? '')) ?: null,
        'subtitle' => trim((string) ($product['subtitle'] ?? '')) ?: null,
        'detail_note' => trim((string) ($product['detail_note'] ?? '')) ?: null,
        'apr_rate' => number_format((float) $product['apr_rate'], 2, '.', ''),
        'term_days' => (int) $product['term_days'],
        'min_subscribe_amount' => number_format((float) $product['min_subscribe_amount'], 8, '.', ''),
        'personal_limit_amount' => number_format($personalLimit, 8, '.', ''),
        'total_quota_amount' => number_format($totalQuota, 8, '.', ''),
        'sold_quota_amount' => number_format($soldQuota, 8, '.', ''),
        'remaining_quota_amount' => number_format($remainingQuota, 8, '.', ''),
        'user_subscribed_amount' => number_format($userSubscribed, 8, '.', ''),
        'user_remaining_quota_amount' => number_format($userRemainingQuota, 8, '.', ''),
        'available_balance' => number_format((float) ($wallet['available_balance'] ?? 0), 8, '.', ''),
        'default_return_mode' => normalize_financial_return_mode($product['default_return_mode'] ?? 'auto'),
        'default_return_delay_days' => (int) ($product['default_return_delay_days'] ?? 0),
        'status' => (string) $product['status'],
        'sort_order' => (int) $product['sort_order'],
    ];
}

function normalize_financial_return_mode(mixed $value): string
{
    $mode = trim((string) $value);
    return $mode === 'auto' ? 'auto' : 'manual';
}

function normalize_financial_product_status(mixed $value): string
{
    $status = trim((string) $value);
    return $status === 'inactive' ? 'inactive' : 'active';
}

function sanitize_financial_product_payload(array $input, bool $isCreate = true): array
{
    $productCode = strtolower(trim((string) ($input['product_code'] ?? '')));
    $assetCode = strtoupper(trim((string) ($input['asset_code'] ?? 'USDT')));
    $walletCode = strtolower(trim((string) ($input['wallet_code'] ?? 'cash_usdt')));
    $displayName = trim((string) ($input['display_name'] ?? ''));
    $subtitle = trim((string) ($input['subtitle'] ?? ''));
    $detailNote = trim((string) ($input['detail_note'] ?? ''));
    $aprRate = (float) ($input['apr_rate'] ?? 0);
    $termDays = (int) ($input['term_days'] ?? 0);
    $minSubscribeAmount = (float) ($input['min_subscribe_amount'] ?? 0);
    $personalLimitAmount = (float) ($input['personal_limit_amount'] ?? 0);
    $totalQuotaAmount = (float) ($input['total_quota_amount'] ?? 0);
    $sortOrder = (int) ($input['sort_order'] ?? 0);
    $defaultReturnMode = normalize_financial_return_mode($input['default_return_mode'] ?? 'auto');
    $defaultReturnDelayDays = max(0, (int) ($input['default_return_delay_days'] ?? 0));
    $status = normalize_financial_product_status($input['status'] ?? 'active');

    if ($productCode === '' || !preg_match('/^[a-z0-9_-]{3,40}$/', $productCode)) {
        failure('AUTH_INVALID_PARAMS', 'Financial product code is invalid');
    }
    if ($assetCode === '') {
        failure('AUTH_INVALID_PARAMS', 'Financial product asset is invalid');
    }
    if ($walletCode === '') {
        failure('AUTH_INVALID_PARAMS', 'Financial product wallet is invalid');
    }
    if ($displayName === '') {
        $displayName = strtoupper($productCode);
    }
    if ($aprRate < 0 || $termDays <= 0 || $minSubscribeAmount <= 0 || $personalLimitAmount <= 0 || $totalQuotaAmount <= 0) {
        failure('AUTH_INVALID_PARAMS', 'Financial product params are invalid');
    }
    if ($personalLimitAmount > $totalQuotaAmount) {
        failure('AUTH_INVALID_PARAMS', 'Financial product limit is invalid');
    }
    if (mb_strlen($displayName) > 80 || mb_strlen($subtitle) > 160 || mb_strlen($detailNote) > 500) {
        failure('AUTH_INVALID_PARAMS', 'Financial product copy is too long');
    }

    return [
        'product_code' => $productCode,
        'asset_code' => $assetCode,
        'wallet_code' => $walletCode,
        'display_name' => $displayName,
        'subtitle' => $subtitle !== '' ? $subtitle : null,
        'detail_note' => $detailNote !== '' ? $detailNote : null,
        'apr_rate' => number_format($aprRate, 2, '.', ''),
        'term_days' => $termDays,
        'min_subscribe_amount' => number_format($minSubscribeAmount, 8, '.', ''),
        'personal_limit_amount' => number_format($personalLimitAmount, 8, '.', ''),
        'total_quota_amount' => number_format($totalQuotaAmount, 8, '.', ''),
        'sort_order' => $sortOrder,
        'default_return_mode' => $defaultReturnMode,
        'default_return_delay_days' => $defaultReturnDelayDays,
        'status' => $status,
    ];
}

function derive_withdrawal_remark(array $item): string
{
    $remark = trim((string) ($item['remark'] ?? ''));
    if ($remark !== '') {
        return $remark;
    }
    $assetCode = strtoupper(trim((string) ($item['asset_code'] ?? '')));
    $sourceWalletCode = strtolower(trim((string) ($item['source_wallet_code'] ?? '')));
    $channelType = strtolower(trim((string) ($item['channel_type'] ?? '')));
    if ($assetCode === 'EUR' && $sourceWalletCode === 'eur' && $channelType === 'usdt') {
        return 'EUR换USDT提领';
    }
    if ($assetCode === 'USDT') {
        return $sourceWalletCode === 'eur' ? 'EUR提现' : 'USDT提现';
    }
    if ($assetCode === 'EUR') {
        return 'EUR提现';
    }
    return $assetCode !== '' ? $assetCode . '提现' : '-';
}

function financial_return_schedule(string $maturityAt, int $delayDays): string
{
    $baseTimestamp = strtotime($maturityAt);
    if ($baseTimestamp === false) {
        $baseTimestamp = time();
    }
    $safeDelayDays = max(0, $delayDays);
    return gmdate('c', $baseTimestamp + ($safeDelayDays * 86400));
}

function settle_financial_subscription(PDO $pdo, array $subscription, ?int $adminUserId = null): array
{
    $subscriptionId = (int) $subscription['id'];
    $userId = (int) $subscription['user_id'];
    $amount = (float) $subscription['amount'];
    $interest = (float) $subscription['estimated_interest'];
    $walletCode = (string) $subscription['wallet_code'];
    $productId = (int) $subscription['product_id'];
    $totalCredit = $amount + $interest;
    $now = now_iso();

    apply_wallet_delta($pdo, $userId, $walletCode, $totalCredit, 0.0);

    $productStmt = $pdo->prepare('SELECT sold_quota_amount FROM financial_products WHERE id = :id LIMIT 1');
    $productStmt->execute([':id' => $productId]);
    $product = $productStmt->fetch();
    if ($product) {
        $nextSold = max(0.0, (float) $product['sold_quota_amount'] - $amount);
        $pdo->prepare('UPDATE financial_products SET sold_quota_amount = :sold_quota_amount, updated_at = :updated_at WHERE id = :id')
            ->execute([
                ':sold_quota_amount' => number_format($nextSold, 8, '.', ''),
                ':updated_at' => $now,
                ':id' => $productId,
            ]);
    }

    $pdo->prepare('UPDATE user_financial_subscriptions
        SET status = "settled", settled_at = :settled_at, returned_by_admin_id = :returned_by_admin_id, updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':settled_at' => $now,
            ':returned_by_admin_id' => $adminUserId,
            ':updated_at' => $now,
            ':id' => $subscriptionId,
        ]);

    return [
        'subscription_id' => $subscriptionId,
        'returned_total' => number_format($totalCredit, 8, '.', ''),
        'settled_at' => $now,
    ];
}

function process_due_financial_returns(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT *
        FROM user_financial_subscriptions
        WHERE status = "active"
          AND return_mode = "auto"
          AND return_scheduled_at IS NOT NULL
          AND ' . db_due_datetime_condition('return_scheduled_at') . '
        ORDER BY id ASC');
    $stmt->execute();
    foreach ($stmt->fetchAll() as $subscription) {
        try {
            $pdo->beginTransaction();
            settle_financial_subscription($pdo, $subscription, null);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

function serialize_financial_subscription(array $item, bool $localizeTimes = false): array
{
    $returnScheduled = !empty($item['return_scheduled_at']) ? (string) $item['return_scheduled_at'] : null;
    $settled = !empty($item['settled_at']) ? (string) $item['settled_at'] : null;

    $out = [
        'id' => (int) $item['id'],
        'user_id' => (int) $item['user_id'],
        'product_id' => (int) $item['product_id'],
        'product_code' => (string) $item['product_code'],
        'asset_code' => (string) $item['asset_code'],
        'wallet_code' => (string) $item['wallet_code'],
        'amount' => (string) $item['amount'],
        'apr_rate' => number_format((float) $item['apr_rate'], 2, '.', ''),
        'term_days' => (int) $item['term_days'],
        'estimated_interest' => (string) $item['estimated_interest'],
        'status' => (string) $item['status'],
        'return_mode' => normalize_financial_return_mode($item['return_mode'] ?? 'manual'),
        'return_delay_days' => (int) ($item['return_delay_days'] ?? 0),
        'subscribed_at' => (string) $item['subscribed_at'],
        'interest_start_at' => (string) $item['interest_start_at'],
        'maturity_at' => (string) $item['maturity_at'],
        'return_scheduled_at' => $returnScheduled,
        'settled_at' => $settled,
        'returned_by_admin_id' => $item['returned_by_admin_id'] !== null ? (int) $item['returned_by_admin_id'] : null,
    ];

    if ($localizeTimes) {
        $out = localize_timestamps_on_row($out, ['subscribed_at', 'interest_start_at', 'maturity_at', 'return_scheduled_at', 'settled_at']);
    }

    return $out;
}

const COINDESK_NEWS_CACHE_TTL_SEC = 21600; // 6 小時
const COINDESK_NEWS_LIMIT = 5;

function coindesk_news_language(string $lang): string
{
    $lang = strtolower(trim($lang));
    $map = [
        'zh-hant' => 'zh',
        'zh-hans' => 'zh',
        'zh' => 'zh',
        'eng' => 'en',
        'en' => 'en',
        'de' => 'de',
        'fr' => 'fr',
        'it' => 'it',
        'nl' => 'nl',
        'es' => 'es',
        'pt' => 'pt-br',
        'pt-br' => 'pt-br',
        'jp' => 'ja',
        'ja' => 'ja',
        'kr' => 'ko',
        'ko' => 'ko',
    ];

    return $map[$lang] ?? 'en';
}

function coindesk_news_url(string $lang): string
{
    $siteLang = coindesk_news_language($lang);
    if ($siteLang === 'en') {
        return 'https://www.coindesk.com/latest-crypto-news';
    }

    return 'https://www.coindesk.com/' . $siteLang . '/latest-crypto-news';
}

function coindesk_news_accept_language(string $siteLang): string
{
    $map = [
        'zh' => 'zh-Hant,zh-CN,en;q=0.8',
        'ja' => 'ja,en;q=0.8',
        'ko' => 'ko,en;q=0.8',
        'pt-br' => 'pt-BR,pt,en;q=0.8',
    ];

    return $map[$siteLang] ?? ($siteLang . ',en;q=0.8');
}

function coindesk_news_cache_file(string $lang): string
{
    return dirname(__DIR__) . '/storage/cache/coindesk-news-' . coindesk_news_language($lang) . '.json';
}

function coindesk_news_normalize_time_label(string $label, string $siteLang): string
{
    $s = trim($label);
    if ($s === '') {
        return '';
    }
    if ($siteLang === 'zh') {
        return str_replace(['分钟', '小时'], ['分鐘', '小時'], $s);
    }
    return $s;
}

function coindesk_decode_next_image_src(string $src): ?string
{
    $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($src === '') {
        return null;
    }
    if (str_starts_with($src, 'https://cdn.sanity.io/')) {
        return $src;
    }
    if (!str_contains($src, '/_next/image?') || !str_contains($src, 'url=')) {
        return null;
    }
    $prefix = str_starts_with($src, 'http') ? '' : 'https://www.coindesk.com';
    $query = parse_url($prefix . $src, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return null;
    }
    parse_str($query, $pairs);
    $rawUrl = $pairs['url'] ?? null;
    if (!is_string($rawUrl) || $rawUrl === '') {
        return null;
    }
    $decoded = urldecode($rawUrl);

    return $decoded !== '' ? $decoded : null;
}

function coindesk_extract_image_after_card(string $htmlFragment): ?string
{
    if (preg_match('#src="(https://cdn\\.sanity\\.io[^"]+)"#i', $htmlFragment, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('#src="(/_next/image\\?url=[^"]+)"#i', $htmlFragment, $m)) {
        return coindesk_decode_next_image_src($m[1]);
    }

    return null;
}

function coindesk_fetch_listing_html(string $lang): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $siteLang = coindesk_news_language($lang);
    $url = coindesk_news_url($siteLang);
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 22,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (compatible; EURNYSE-C2C/1.0; +' . $url . ')',
            'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ' . coindesk_news_accept_language($siteLang),
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $body === '' || $code < 200 || $code >= 400) {
        return null;
    }

    return $body;
}

/**
 * @return list<array{title: string, url: string, image_url: string, time_label: string}>
 */
function coindesk_parse_latest_news(string $html, int $limit, string $lang): array
{
    $siteLang = coindesk_news_language($lang);
    $pattern = '#content-card-title" href="((?:/[a-z]{2}(?:-[a-z]{2})?)?/[a-z-]+/\\d{4}/\\d{2}/\\d{2}/[^"]+)"[^>]*><h2[^>]*>([^<]+)</h2></a>(?:<p class="font-body[^"]*mb-4">[^<]*</p>)?<p class="flex gap-2 flex-col"><span class="font-metadata text-subtle">([^<]*)</span>#i';
    if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $out = [];
    foreach ($matches as $row) {
        if (count($out) >= $limit) {
            break;
        }
        $path = (string) ($row[1][0] ?? '');
        $titleRaw = (string) ($row[2][0] ?? '');
        $timeRaw = (string) ($row[3][0] ?? '');
        if ($path === '' || $titleRaw === '') {
            continue;
        }
        $endPos = (int) ($row[0][1] ?? 0) + strlen((string) ($row[0][0] ?? ''));
        $after = substr($html, $endPos, 2800);
        $imageUrl = coindesk_extract_image_after_card($after);
        if ($imageUrl === null || $imageUrl === '') {
            $startPos = (int) ($row[0][1] ?? 0);
            $before = substr($html, max(0, $startPos - 4000), min(4000, $startPos));
            if (preg_match_all('#src="([^"]+)"#i', $before, $imgMatches)) {
                $candidates = $imgMatches[1];
                for ($i = count($candidates) - 1; $i >= 0; $i--) {
                    $decoded = coindesk_decode_next_image_src($candidates[$i]);
                    if ($decoded !== null && $decoded !== '') {
                        $imageUrl = $decoded;
                        break;
                    }
                    if (str_starts_with($candidates[$i], 'https://cdn.sanity.io/')) {
                        $imageUrl = html_entity_decode($candidates[$i], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        break;
                    }
                }
            }
        }
        if ($imageUrl === null || $imageUrl === '') {
            continue;
        }
        $title = html_entity_decode(trim($titleRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $out[] = [
            'title' => $title,
            'url' => 'https://www.coindesk.com' . $path,
            'image_url' => $imageUrl,
            'time_label' => coindesk_news_normalize_time_label($timeRaw, $siteLang),
        ];
    }

    return $out;
}

/**
 * @return array{items: list<array{title: string, url: string, image_url: string, time_label: string}>, fetched_at: string, source: string, lang: string, cache_hit: bool}
 */
function coindesk_news_payload(string $lang, bool $forceRefresh = false): array
{
    $siteLang = coindesk_news_language($lang);
    $sourceUrl = coindesk_news_url($siteLang);
    $cacheFile = coindesk_news_cache_file($siteLang);
    $now = time();
    if (!$forceRefresh && is_readable($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['fetched_at'], $decoded['items']) && is_array($decoded['items'])) {
                $ts = strtotime((string) $decoded['fetched_at']) ?: 0;
                if ($ts > 0 && ($now - $ts) < COINDESK_NEWS_CACHE_TTL_SEC) {
                    $decoded['cache_hit'] = true;
                    $decoded['source'] = $sourceUrl;
                    $decoded['lang'] = $siteLang;

                    return $decoded;
                }
            }
        }
    }
    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $html = coindesk_fetch_listing_html($siteLang);
    $items = is_string($html) ? coindesk_parse_latest_news($html, COINDESK_NEWS_LIMIT, $siteLang) : [];
    $fetchedAt = now_iso();
    $payload = [
        'items' => $items,
        'fetched_at' => $fetchedAt,
        'source' => $sourceUrl,
        'lang' => $siteLang,
        'cache_hit' => false,
    ];
    if ($items !== []) {
        @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } elseif (is_readable($cacheFile)) {
        $stale = @file_get_contents($cacheFile);
        if (is_string($stale) && $stale !== '') {
            $old = json_decode($stale, true);
            if (is_array($old) && isset($old['items']) && is_array($old['items']) && $old['items'] !== []) {
                $old['fetched_at'] = $fetchedAt;
                $old['cache_hit'] = true;
                $old['source'] = $sourceUrl;
                $old['lang'] = $siteLang;

                return $old;
            }
        }
    }

    return $payload;
}

$pdo = pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$input = read_input();

if (($path === '/index.php/api/public/coindesk-zh-news' || $path === '/api/public/coindesk-zh-news' || $path === '/index.php/api/public/coindesk-news' || $path === '/api/public/coindesk-news') && $method === 'GET') {
    $refresh = isset($_GET['refresh']) && (string) $_GET['refresh'] === '1';
    $lang = trim((string) ($_GET['lang'] ?? 'zh-Hant'));
    success('PUBLIC_COINDESK_NEWS_OK', 'ok', coindesk_news_payload($lang, $refresh));
}

if (($path === '/index.php/api/public/market-snapshot' || $path === '/api/public/market-snapshot') && $method === 'GET') {
    success('PUBLIC_MARKET_SNAPSHOT_OK', 'ok', [
        'items' => fetch_market_snapshot(),
        'source' => 'binance',
    ]);
}

if ($path === '/api/health' || $path === '/health') {
    $dbOk = false;
    try {
        $dbOk = (string) $pdo->query('SELECT 1')->fetchColumn() === '1';
    } catch (Throwable $exception) {
        $dbOk = false;
    }
    success('HEALTH_OK', 'ok', [
        'service' => 'backend-api',
        'database' => $dbOk ? 'ok' : 'error',
        'time' => now_iso(),
    ]);
}

if ($path === '/index.php/api/user/login' || $path === '/api/user/login') {
    $account = trim((string) ($input['account'] ?? ''));
    $password = trim((string) ($input['password'] ?? ''));
    $lang = trim((string) ($input['lang'] ?? ''));

    if ($account === '' || $password === '') {
        failure('AUTH_INVALID_PARAMS', '請輸入帳號與密碼');
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :account OR email = :account OR mobile_e164 = :account LIMIT 1');
    $stmt->execute([':account' => $account]);
    $user = $stmt->fetch();

    if (!$user) {
        audit($pdo, 'auth', 'login_failed', 'system', null, 'user', null, null, null, null, 'AUTH_ACCOUNT_INCORRECT', [
            'account' => $account,
            'ip' => client_ip(),
            'msg' => '帳號不存在或輸入錯誤',
        ]);
        failure('AUTH_ACCOUNT_INCORRECT', '帳號不存在或輸入錯誤');
    }

    if (($user['status'] ?? '') !== 'normal') {
        audit($pdo, 'auth', 'login_failed', 'system', null, 'user', (int) $user['id'], null, null, null, 'AUTH_ACCOUNT_LOCKED', [
            'account' => $account,
            'ip' => client_ip(),
            'msg' => '帳號已被鎖定',
        ]);
        failure('AUTH_ACCOUNT_LOCKED', '帳號已被鎖定');
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        $pdo->prepare('UPDATE users SET login_failure_count = login_failure_count + 1, updated_at = :updated_at WHERE id = :id')
            ->execute([
                ':updated_at' => now_iso(),
                ':id' => $user['id'],
            ]);
        audit($pdo, 'auth', 'login_failed', 'system', null, 'user', (int) $user['id'], null, null, null, 'AUTH_PASSWORD_INCORRECT', [
            'account' => $account,
            'ip' => client_ip(),
            'msg' => '密碼錯誤',
        ]);
        failure('AUTH_PASSWORD_INCORRECT', '密碼錯誤');
    }

    $token = random_token(48);
    $now = now_iso();
    $expiresAt = gmdate('c', time() + 86400 * 7);
    $pdo->prepare('UPDATE user_tokens
        SET revoked_at = :revoked_at, updated_at = :updated_at
        WHERE user_id = :user_id AND revoked_at IS NULL')
        ->execute([
            ':revoked_at' => $now,
            ':updated_at' => $now,
            ':user_id' => $user['id'],
        ]);
    $pdo->prepare('INSERT INTO user_tokens (user_id, token, issued_at, expires_at, revoked_at, created_at, updated_at)
        VALUES (:user_id, :token, :issued_at, :expires_at, null, :created_at, :updated_at)')
        ->execute([
            ':user_id' => $user['id'],
            ':token' => $token,
            ':issued_at' => $now,
            ':expires_at' => $expiresAt,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    $resolvedLang = $lang !== '' ? lang_map($lang) : (string) ($user['lang'] ?? 'eng');
    $pdo->prepare('UPDATE users
        SET login_failure_count = 0,
            last_login_at = :last_login_at,
            last_login_ip = :last_login_ip,
            lang = :lang,
            updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':last_login_at' => $now,
            ':last_login_ip' => client_ip(),
            ':lang' => $resolvedLang,
            ':updated_at' => $now,
            ':id' => $user['id'],
        ]);
    audit($pdo, 'auth', 'login_success', 'system', null, 'user', (int) $user['id'], null, null, ['lang' => $resolvedLang], null, [
        'account' => $account,
        'ip' => client_ip(),
        'msg' => '登入成功',
    ]);

    success('AUTH_LOGIN_SUCCESS', '登入成功', [
        'userinfo' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'token' => $token,
            'status' => $user['status'],
        ],
    ]);
}

if ($path === '/index.php/api/user/register' || $path === '/api/user/register') {
    $username = trim((string) ($input['username'] ?? ''));
    $password = trim((string) ($input['password'] ?? ''));
    $invitationCode = strtoupper(trim((string) ($input['invitation_code'] ?? '')));
    $lang = trim((string) ($input['lang'] ?? ''));

    if ($username === '' || $password === '') {
        failure('AUTH_INVALID_PARAMS', '請填寫完整註冊資料');
    }
    if ($invitationCode === '') {
        failure('AUTH_INVITATION_REQUIRED', '請輸入邀請碼');
    }
    if (mb_strlen($password) < 6) {
        failure('AUTH_PASSWORD_TOO_SHORT', '密碼至少需要 6 位字元');
    }

    $inviteStmt = $pdo->prepare('SELECT * FROM invitation_codes WHERE code = :code AND status = "active" LIMIT 1');
    $inviteStmt->execute([':code' => $invitationCode]);
    $invite = $inviteStmt->fetch();
    $invitedByUserId = null;
    $invitedByAdminId = null;
    $adminGroupCodeForUser = null;
    if ($invite) {
        $invitedByUserId = (int) $invite['user_id'];
        $inviterUserStmt = $pdo->prepare('SELECT admin_group_code FROM users WHERE id = :id LIMIT 1');
        $inviterUserStmt->execute([':id' => $invitedByUserId]);
        $inviterUser = $inviterUserStmt->fetch();
        if ($inviterUser) {
            $adminGroupCodeForUser = normalize_admin_group_code($inviterUser['admin_group_code'] ?? '');
        }
    } else {
        $adminInviteStmt = $pdo->prepare('SELECT id, name, display_name, status, admin_group_code FROM admin_users WHERE staff_invite_code = :code AND status = "normal" LIMIT 1');
        $adminInviteStmt->execute([':code' => $invitationCode]);
        $adminInvite = $adminInviteStmt->fetch();
        if (!$adminInvite) {
            failure('AUTH_INVITATION_INVALID', '邀請碼錯誤或不存在');
        }
        $invitedByAdminId = (int) $adminInvite['id'];
        $adminGroupCodeForUser = normalize_admin_group_code($adminInvite['admin_group_code'] ?? '');
    }

    $accountType = 'email';
    $email = null;
    $mobile = null;
    $countryCode = null;
    $mobileE164 = null;

    if (is_email_account($username)) {
        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            failure('AUTH_EMAIL_INVALID', 'Email 格式不正確');
        }
        $email = mb_strtolower($username);
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute([':email' => $email]);
        if ($exists->fetch()) {
            failure('AUTH_EMAIL_EXISTS', '此 Email 已被註冊');
        }
    } else {
        $normalized = normalize_mobile($username);
        if (!$normalized) {
            failure('AUTH_MOBILE_INVALID', '手機號碼格式不正確');
        }
        $accountType = 'mobile';
        $mobile = $normalized['mobile'];
        $countryCode = $normalized['country_code'];
        $mobileE164 = $normalized['mobile_e164'];
        $exists = $pdo->prepare('SELECT id FROM users WHERE mobile_e164 = :mobile_e164 LIMIT 1');
        $exists->execute([':mobile_e164' => $mobileE164]);
        if ($exists->fetch()) {
            failure('AUTH_MOBILE_EXISTS', '此手機號碼已被註冊');
        }
    }

    $now = now_iso();
    $username = generate_user_display_code($pdo);
    $newInvitationCode = generate_invitation_numeric_code($pdo);
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (
        account_type, username, email, mobile, country_code, mobile_e164, password_hash, status, lang, avatar_id,
        invitation_code, invited_by_user_id, invited_by_admin_id, admin_group_code, login_failure_count, created_at, updated_at
    ) VALUES (
        :account_type, :username, :email, :mobile, :country_code, :mobile_e164, :password_hash, :status, :lang, :avatar_id,
        :invitation_code, :invited_by_user_id, :invited_by_admin_id, :admin_group_code, 0, :created_at, :updated_at
    )');
    $stmt->execute([
        ':account_type' => $accountType,
        ':username' => $username,
        ':email' => $email,
        ':mobile' => $mobile,
        ':country_code' => $countryCode,
        ':mobile_e164' => $mobileE164,
        ':password_hash' => $passwordHash,
        ':status' => 'normal',
        ':lang' => lang_map($lang),
        ':avatar_id' => random_user_avatar_id(),
        ':invitation_code' => $newInvitationCode,
        ':invited_by_user_id' => $invitedByUserId,
        ':invited_by_admin_id' => $invitedByAdminId,
        ':admin_group_code' => $adminGroupCodeForUser !== '' ? $adminGroupCodeForUser : null,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO invitation_codes (user_id, code, status, is_primary, issued_at, expires_at, created_at, updated_at)
        VALUES (:user_id, :code, "active", 1, :issued_at, null, :created_at, :updated_at)')
        ->execute([
            ':user_id' => $userId,
            ':code' => $newInvitationCode,
            ':issued_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    initialize_user_defaults($pdo, $userId);
    audit($pdo, 'auth', 'register_success', 'system', null, 'user', $userId, null, null, array_filter([
        'invited_by_user_id' => $invitedByUserId,
        'invited_by_admin_id' => $invitedByAdminId,
    ], static fn ($v) => $v !== null && $v !== 0), null, [
        'account' => $username,
        'ip' => client_ip(),
        'msg' => '註冊成功',
    ]);

    success('AUTH_REGISTER_SUCCESS', '註冊成功', []);
}

if ($path === '/index.php/api/user/send_email_mobile' || $path === '/api/user/send_email_mobile') {
    $username = trim((string) ($input['username'] ?? ''));
    $lang = trim((string) ($input['lang'] ?? ''));
    $event = trim((string) ($input['event'] ?? 'register'));

    if ($lang === '') {
        failure('AUTH_LANG_REQUIRED', 'language is not null');
    }

    if ($username === '') {
        failure('AUTH_INVALID_PARAMS', 'Invalid parameters');
    }

    $channel = 'email';
    $target = $username;
    $countryCode = null;

    if (is_email_account($username)) {
        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            failure('AUTH_EMAIL_INVALID', 'Email is incorrect');
        }
        $target = mb_strtolower($username);
    } else {
        $normalized = normalize_mobile($username);
        if (!$normalized) {
            failure('AUTH_MOBILE_INVALID', 'Mobile is incorrect');
        }
        $channel = 'sms';
        $target = $normalized['mobile_e164'];
        $countryCode = $normalized['country_code'];
    }

    $rateStmt = $pdo->prepare('SELECT sent_at FROM verification_codes WHERE target = :target AND event = :event ORDER BY id DESC LIMIT 1');
    $rateStmt->execute([
        ':target' => $target,
        ':event' => $event,
    ]);
    $latest = $rateStmt->fetchColumn();
    if ($latest && strtotime((string) $latest) > time() - 60) {
        failure('AUTH_SEND_TOO_FREQUENT', 'Send frequently');
    }

    $code = (string) random_int(100000, 999999);
    $now = now_iso();
    $expiresAt = gmdate('c', time() + 600);
    $pdo->prepare('INSERT INTO verification_codes (
        channel, target, event, code, status, attempt_count, sent_ip, sent_at, expires_at, consumed_at, created_at, updated_at
    ) VALUES (
        :channel, :target, :event, :code, "pending", 0, :sent_ip, :sent_at, :expires_at, null, :created_at, :updated_at
    )')->execute([
        ':channel' => $channel,
        ':target' => $target,
        ':event' => $event,
        ':code' => $code,
        ':sent_ip' => client_ip(),
        ':sent_at' => $now,
        ':expires_at' => $expiresAt,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    audit($pdo, 'auth', 'verification_sent', 'system', null, 'verification', null, null, null, ['target' => $target, 'event' => $event, 'channel' => $channel], null, [
        'account' => $target,
        'ip' => client_ip(),
        'msg' => $channel === 'email' ? 'The email was sent successfully' : 'SMS sent successfully',
    ]);

    $debugEnabled = env_bool('AUTH_DEBUG_VERIFICATION_CODE', false);
    $responseData = $debugEnabled ? ['debug_code' => $code] : [];

    if ($channel === 'email') {
        success('AUTH_EMAIL_CODE_SENT', 'The email was sent successfully', $responseData);
    }

    success('AUTH_SMS_CODE_SENT', 'SMS sent successfully', $responseData);
}

if ($path === '/index.php/api/user/me' || $path === '/api/user/me') {
    $user = require_user($pdo);
    success('AUTH_ME_SUCCESS', 'ok', ['user' => user_me_payload($pdo, $user)]);
}

if ($path === '/index.php/api/user/avatar' || $path === '/api/user/avatar') {
    if ($method !== 'PATCH' && $method !== 'POST') {
        failure('AUTH_METHOD_NOT_ALLOWED', 'Method not allowed', 405);
    }
    $user = require_user($pdo);
    $avatarId = normalize_user_avatar_id($input['avatar_id'] ?? null);
    if ($avatarId === null) {
        failure('AUTH_INVALID_PARAMS', 'Avatar is invalid');
    }
    $pdo->prepare('UPDATE users SET avatar_id = :avatar_id, updated_at = :updated_at WHERE id = :user_id')
        ->execute([
            ':avatar_id' => $avatarId,
            ':updated_at' => now_iso(),
            ':user_id' => (int) $user['user_id'],
        ]);
    $user['avatar_id'] = $avatarId;
    success('AUTH_AVATAR_UPDATED', 'ok', ['user' => user_me_payload($pdo, $user)]);
}

if (($path === '/index.php/api/user/logout' || $path === '/api/user/logout') && $method === 'POST') {
    $user = require_user($pdo);
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    preg_match('/Bearer\s+(.+)/i', $header, $matches);
    $token = trim((string) ($matches[1] ?? ''));
    $now = now_iso();
    $pdo->prepare('UPDATE user_tokens SET revoked_at = :revoked_at, updated_at = :updated_at WHERE token = :token AND user_id = :user_id')
        ->execute([
            ':revoked_at' => $now,
            ':updated_at' => $now,
            ':token' => $token,
            ':user_id' => $user['user_id'],
        ]);
    audit($pdo, 'auth', 'logout_success', 'user', (int) $user['user_id'], 'user', (int) $user['user_id'], null, null, null, null, [
        'account' => $user['username'],
        'ip' => client_ip(),
        'msg' => 'Logged out successful',
    ]);
    success('AUTH_LOGOUT_SUCCESS', 'Logged out successful', []);
}

if (($path === '/index.php/api/user/password' || $path === '/api/user/password') && $method === 'PATCH') {
    $user = require_user($pdo);
    $currentPassword = (string) ($input['current_password'] ?? '');
    $newPassword = (string) ($input['new_password'] ?? '');
    if ($currentPassword === '' || $newPassword === '') {
        failure('AUTH_INVALID_PARAMS', '請確認輸入資料是否完整');
    }
    if (mb_strlen($newPassword) < 6) {
        failure('AUTH_PASSWORD_TOO_SHORT', '密碼至少需要 6 位字元');
    }
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $user['user_id']]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '' || !password_verify($currentPassword, $hash)) {
        failure('AUTH_PASSWORD_INCORRECT', '密碼錯誤');
    }
    $now = now_iso();
    $pdo->prepare('UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id')
        ->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':updated_at' => $now,
            ':id' => $user['user_id'],
        ]);
    $pdo->prepare('UPDATE user_tokens SET revoked_at = :revoked_at, updated_at = :updated_at WHERE user_id = :user_id AND revoked_at IS NULL AND token <> :token')
        ->execute([
            ':revoked_at' => $now,
            ':updated_at' => $now,
            ':user_id' => $user['user_id'],
            ':token' => trim((string) preg_replace('/^Bearer\s+/i', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '')),
        ]);
    audit($pdo, 'auth', 'password_changed', 'user', (int) $user['user_id'], 'user', (int) $user['user_id'], null, null, null, null, [
        'account' => $user['username'],
        'ip' => client_ip(),
        'msg' => 'Password changed',
    ]);
    success('AUTH_PASSWORD_CHANGED', '密碼已更新', []);
}

if (($path === '/index.php/api/user/support-tickets' || $path === '/api/user/support-tickets') && $method === 'POST') {
    $user = require_user($pdo);
    $category = trim((string) ($input['category'] ?? 'general')) ?: 'general';
    $subject = trim((string) ($input['subject'] ?? ''));
    $content = trim((string) ($input['content'] ?? ''));
    if ($subject === '') {
        failure('SUPPORT_SUBJECT_REQUIRED', 'Subject is required');
    }
    $now = now_iso();
    $pdo->prepare('INSERT INTO support_tickets (
        user_id, category, subject, content, status, priority, assigned_admin_id, admin_note, created_at, updated_at
    ) VALUES (
        :user_id, :category, :subject, :content, "open", "normal", null, null, :created_at, :updated_at
    )')->execute([
        ':user_id' => (int) $user['user_id'],
        ':category' => $category,
        ':subject' => $subject,
        ':content' => $content !== '' ? $content : null,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    success('SUPPORT_TICKET_CREATED', 'Support ticket created', ['id' => (int) $pdo->lastInsertId()]);
}

if (($path === '/index.php/api/user/deposit-requests' || $path === '/api/user/deposit-requests') && $method === 'POST') {
    $user = require_user($pdo);
    $amount = trim((string) ($input['amount'] ?? ''));
    $reference = trim((string) ($input['reference'] ?? ''));
    $proofUrl = trim((string) ($input['proof_url'] ?? ''));
    $network = trim((string) ($input['network'] ?? 'TRC20'));
    $assetCode = trim((string) ($input['asset_code'] ?? 'USDT'));
    $targetWalletCode = strtoupper($assetCode) === 'EUR' ? 'eur' : 'cash_usdt';
    if (!is_numeric($amount) || (float) $amount <= 0) {
        failure('AUTH_INVALID_PARAMS', 'Invalid amount');
    }
    if ($proofUrl === '') {
        failure('AUTH_INVALID_PARAMS', 'Proof is required');
    }
    $now = now_iso();
    $pdo->prepare('INSERT INTO deposit_requests (
        user_id, amount, asset_code, network, target_wallet_code, proof_url, reference_text,
        status, admin_note, reviewed_by_admin_id, reviewed_at, created_at, updated_at
    ) VALUES (
        :user_id, :amount, :asset_code, :network, :target_wallet_code, :proof_url, :reference_text,
        "pending", null, null, null, :created_at, :updated_at
    )')->execute([
        ':user_id' => (int) $user['user_id'],
        ':amount' => number_format((float) $amount, 8, '.', ''),
        ':asset_code' => $assetCode,
        ':network' => $network,
        ':target_wallet_code' => $targetWalletCode,
        ':proof_url' => $proofUrl,
        ':reference_text' => $reference !== '' ? $reference : null,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $requestId = (int) $pdo->lastInsertId();
    success('AUTH_DEPOSIT_REQUEST_CREATED', 'Deposit request submitted', [
        'request_id' => $requestId,
        'deposit_request_id' => $requestId,
    ]);
}

if (($path === '/index.php/api/user/deposit-requests' || $path === '/api/user/deposit-requests') && $method === 'GET') {
    $user = require_user($pdo);
    $stmt = $pdo->prepare('SELECT id, amount, asset_code, network, status, reference_text, proof_url, created_at, reviewed_at
        FROM deposit_requests
        WHERE user_id = :user_id
        ORDER BY id DESC');
    $stmt->execute([':user_id' => (int) $user['user_id']]);
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];

        return localize_timestamps_on_row($item, ['created_at', 'reviewed_at']);
    }, $stmt->fetchAll());
    success('AUTH_DEPOSIT_REQUESTS_SUCCESS', 'ok', ['items' => $items, 'timezone' => request_timezone()]);
}

if (preg_match('#^/(?:index\.php/)?api/user/deposit-requests/(\d+)$#', $path, $matches) && $method === 'GET') {
    $user = require_user($pdo);
    $requestId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT id, user_id, amount, asset_code, network, target_wallet_code, proof_url, reference_text, status, admin_note, reviewed_at, created_at, updated_at
        FROM deposit_requests
        WHERE id = :id AND user_id = :user_id
        LIMIT 1');
    $stmt->execute([
        ':id' => $requestId,
        ':user_id' => (int) $user['user_id'],
    ]);
    $requestItem = $stmt->fetch();
    if (!$requestItem) {
        failure('AUTH_NOT_FOUND', 'Deposit request not found', 404);
    }
    $requestItem['id'] = (int) $requestItem['id'];
    $requestItem['user_id'] = (int) $requestItem['user_id'];
    $requestItem = localize_timestamps_on_row($requestItem, ['created_at', 'reviewed_at', 'updated_at']);
    success('AUTH_DEPOSIT_REQUEST_DETAIL_SUCCESS', 'ok', ['request' => $requestItem, 'timezone' => request_timezone()]);
}

if (($path === '/index.php/api/user/withdrawal-requests' || $path === '/api/user/withdrawal-requests') && $method === 'POST') {
    $user = require_user($pdo);
    $amount = trim((string) ($input['amount'] ?? ''));
    $channelType = trim((string) ($input['channel_type'] ?? 'usdt'));
    $payoutAddress = trim((string) ($input['payout_address'] ?? ''));
    $payoutMethodId = isset($input['payout_method_id']) && $input['payout_method_id'] !== '' ? (int) $input['payout_method_id'] : null;
    $assetCode = trim((string) ($input['asset_code'] ?? ($channelType === 'bank' ? 'EUR' : 'USDT')));
    $sourceWalletCode = trim((string) ($input['source_wallet_code'] ?? ($channelType === 'bank' ? 'eur' : 'cash_usdt')));
    $remark = trim((string) ($input['remark'] ?? ''));
    if (!in_array($channelType, ['bank', 'usdt', 'pix'], true)) {
        failure('AUTH_INVALID_PARAMS', 'Invalid channel type');
    }
    if (!is_numeric($amount) || (float) $amount <= 0) {
        failure('AUTH_INVALID_PARAMS', 'Invalid amount');
    }
    if ($channelType === 'usdt' && $payoutAddress === '') {
        failure('AUTH_INVALID_PARAMS', 'USDT address is required');
    }
    if ($channelType !== 'usdt' && !$payoutMethodId) {
        failure('AUTH_INVALID_PARAMS', 'Payout method is required');
    }

    $tierStmt = $pdo->prepare('SELECT is_verified FROM user_tier_profiles WHERE user_id = :user_id LIMIT 1');
    $tierStmt->execute([':user_id' => (int) $user['user_id']]);
    $tier = $tierStmt->fetch();
    if (!$tier || !(bool) $tier['is_verified']) {
        failure('AUTH_UNAUTHORIZED', 'KYC is required before withdrawal');
    }

    $walletStmt = $pdo->prepare('SELECT available_balance, reserved_balance FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
    $walletStmt->execute([
        ':user_id' => (int) $user['user_id'],
        ':wallet_code' => $sourceWalletCode,
    ]);
    $wallet = $walletStmt->fetch();
    $balanceToCheck = str_contains($sourceWalletCode, 'reserved')
        ? (float) ($wallet['reserved_balance'] ?? 0)
        : (float) ($wallet['available_balance'] ?? 0);
    if (!$wallet || $balanceToCheck < (float) $amount) {
        failure('AUTH_INVALID_PARAMS', 'Insufficient balance');
    }

    $now = now_iso();
    try {
        $pdo->beginTransaction();
        apply_wallet_delta($pdo, (int) $user['user_id'], $sourceWalletCode, -(float) $amount, (float) $amount);
        $pdo->prepare('INSERT INTO withdrawal_requests (
            user_id, amount, asset_code, channel_type, payout_method_id, payout_address, source_wallet_code, remark,
            status, admin_note, reviewed_by_admin_id, reviewed_at, created_at, updated_at
        ) VALUES (
            :user_id, :amount, :asset_code, :channel_type, :payout_method_id, :payout_address, :source_wallet_code, :remark,
            "pending", null, null, null, :created_at, :updated_at
        )')->execute([
            ':user_id' => (int) $user['user_id'],
            ':amount' => number_format((float) $amount, 8, '.', ''),
            ':asset_code' => $assetCode,
            ':channel_type' => $channelType,
            ':payout_method_id' => $payoutMethodId,
            ':payout_address' => $payoutAddress !== '' ? $payoutAddress : null,
            ':source_wallet_code' => $sourceWalletCode,
            ':remark' => $remark !== '' ? $remark : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $requestId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        failure('AUTH_WITHDRAWAL_REQUEST_CREATE_FAILED', 'Withdrawal request creation failed');
    }
    success('AUTH_WITHDRAWAL_REQUEST_CREATED', 'Withdrawal request submitted', [
        'request_id' => $requestId,
        'withdrawal_request_id' => $requestId,
    ]);
}

if (($path === '/index.php/api/user/withdrawal-requests' || $path === '/api/user/withdrawal-requests') && $method === 'GET') {
    $user = require_user($pdo);
    $stmt = $pdo->prepare('SELECT id, amount, asset_code, channel_type, payout_address, source_wallet_code, status, created_at, reviewed_at
        FROM withdrawal_requests
        WHERE user_id = :user_id
        ORDER BY id DESC');
    $stmt->execute([':user_id' => (int) $user['user_id']]);
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];

        return localize_timestamps_on_row($item, ['created_at', 'reviewed_at']);
    }, $stmt->fetchAll());
    success('AUTH_WITHDRAWAL_REQUESTS_SUCCESS', 'ok', ['items' => $items, 'timezone' => request_timezone()]);
}

if (preg_match('#^/(?:index\.php/)?api/user/withdrawal-requests/(\d+)$#', $path, $matches) && $method === 'GET') {
    $user = require_user($pdo);
    $requestId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT w.id, w.user_id, w.amount, w.asset_code, w.channel_type, w.payout_method_id, w.payout_address,
            w.source_wallet_code, w.remark, w.status, w.admin_note, w.reviewed_at, w.created_at, w.updated_at,
            p.bank_name, p.account_holder, p.account_no_masked, p.usdt_network, p.pix_key
        FROM withdrawal_requests w
        LEFT JOIN user_payout_methods p ON p.id = w.payout_method_id
        WHERE w.id = :id AND w.user_id = :user_id
        LIMIT 1');
    $stmt->execute([
        ':id' => $requestId,
        ':user_id' => (int) $user['user_id'],
    ]);
    $requestItem = $stmt->fetch();
    if (!$requestItem) {
        failure('AUTH_NOT_FOUND', 'Withdrawal request not found', 404);
    }
    $requestItem['id'] = (int) $requestItem['id'];
    $requestItem['user_id'] = (int) $requestItem['user_id'];
    $requestItem['payout_method_id'] = $requestItem['payout_method_id'] !== null ? (int) $requestItem['payout_method_id'] : null;
    $requestItem = localize_timestamps_on_row($requestItem, ['created_at', 'reviewed_at', 'updated_at']);
    success('AUTH_WITHDRAWAL_REQUEST_DETAIL_SUCCESS', 'ok', ['request' => $requestItem, 'timezone' => request_timezone()]);
}

if (($path === '/index.php/api/user/kyc-applications' || $path === '/api/user/kyc-applications') && $method === 'POST') {
    $user = require_user($pdo);
    $legalName = trim((string) ($input['legal_name'] ?? ''));
    $idNumber = trim((string) ($input['id_number'] ?? ''));
    $frontUrl = trim((string) ($input['id_doc_front_url'] ?? ''));
    $backUrl = trim((string) ($input['id_doc_back_url'] ?? ''));
    if ($legalName === '' || $idNumber === '' || $frontUrl === '' || $backUrl === '') {
        failure('AUTH_INVALID_PARAMS', 'KYC materials are incomplete');
    }

    $stmt = $pdo->prepare('SELECT * FROM user_kyc_applications WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
    $stmt->execute([':user_id' => (int) $user['user_id']]);
    $existing = $stmt->fetch();
    $now = now_iso();
    $masked = mb_strlen($idNumber) > 4 ? mb_substr($idNumber, 0, 2) . '****' . mb_substr($idNumber, -2) : $idNumber;

    if ($existing) {
        $pdo->prepare('UPDATE user_kyc_applications
            SET legal_name = :legal_name, id_number_masked = :id_number_masked, id_doc_front_url = :id_doc_front_url,
                id_doc_back_url = :id_doc_back_url, selfie_url = null, status = "pending",
                review_note = null, reviewed_by_admin_id = null, reviewed_at = null, submitted_at = :submitted_at, updated_at = :updated_at
            WHERE id = :id')
            ->execute([
                ':legal_name' => $legalName,
                ':id_number_masked' => $masked,
                ':id_doc_front_url' => $frontUrl,
                ':id_doc_back_url' => $backUrl,
                ':submitted_at' => $now,
                ':updated_at' => $now,
                ':id' => (int) $existing['id'],
            ]);
    } else {
        $pdo->prepare('INSERT INTO user_kyc_applications (
            user_id, legal_name, id_number_masked, id_doc_front_url, id_doc_back_url, selfie_url,
            status, review_note, reviewed_by_admin_id, reviewed_at, submitted_at, created_at, updated_at
        ) VALUES (
            :user_id, :legal_name, :id_number_masked, :id_doc_front_url, :id_doc_back_url, null,
            "pending", null, null, null, :submitted_at, :created_at, :updated_at
        )')->execute([
            ':user_id' => (int) $user['user_id'],
            ':legal_name' => $legalName,
            ':id_number_masked' => $masked,
            ':id_doc_front_url' => $frontUrl,
            ':id_doc_back_url' => $backUrl,
            ':submitted_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
    success('AUTH_KYC_APPLICATION_SUBMITTED', 'KYC submitted', []);
}

if (($path === '/index.php/api/user/kyc-applications/latest' || $path === '/api/user/kyc-applications/latest') && $method === 'GET') {
    $user = require_user($pdo);
    $stmt = $pdo->prepare('SELECT id, legal_name, id_number_masked, id_doc_front_url, id_doc_back_url, selfie_url, status, review_note, submitted_at, reviewed_at
        FROM user_kyc_applications
        WHERE user_id = :user_id
        ORDER BY id DESC
        LIMIT 1');
    $stmt->execute([':user_id' => (int) $user['user_id']]);
    $application = $stmt->fetch() ?: null;
    if ($application) {
        $application['id'] = (int) $application['id'];
    }
    success('AUTH_KYC_APPLICATION_SUCCESS', 'ok', ['application' => $application]);
}

if (($path === '/index.php/api/user/payout-methods' || $path === '/api/user/payout-methods') && $method === 'POST') {
    $user = require_user($pdo);
    $channelType = trim((string) ($input['channel_type'] ?? 'bank'));
    $bankName = trim((string) ($input['bank_name'] ?? ''));
    $accountHolder = trim((string) ($input['account_holder'] ?? ''));
    $accountNo = trim((string) ($input['account_no'] ?? ''));
    $usdtNetwork = trim((string) ($input['usdt_network'] ?? ''));
    $payoutAddress = trim((string) ($input['payout_address'] ?? ''));
    $pixKey = trim((string) ($input['pix_key'] ?? ''));
    $isDefault = array_key_exists('is_default', $input) ? (json_bool($input['is_default']) ? 1 : 0) : 0;
    if (!in_array($channelType, ['bank', 'usdt', 'pix'], true)) {
        failure('AUTH_INVALID_PARAMS', 'Invalid payout channel');
    }
    if ($channelType === 'bank' && ($bankName === '' || $accountHolder === '' || $accountNo === '')) {
        failure('AUTH_INVALID_PARAMS', 'Bank account fields are required');
    }
    if ($channelType === 'usdt' && ($usdtNetwork === '' || $payoutAddress === '')) {
        failure('AUTH_INVALID_PARAMS', 'USDT payout fields are required');
    }
    if ($channelType === 'pix' && $pixKey === '') {
        failure('AUTH_INVALID_PARAMS', 'PIX key is required');
    }

    $maskedAccount = $accountNo !== '' && mb_strlen($accountNo) > 4 ? str_repeat('*', max(0, mb_strlen($accountNo) - 4)) . mb_substr($accountNo, -4) : $accountNo;
    $now = now_iso();
    $pdo->prepare('INSERT INTO user_payout_methods (
        user_id, channel_type, status, bank_name, account_holder, account_no_masked, usdt_network,
        payout_address, pix_key, is_default, review_note, reviewed_by_admin_id, reviewed_at, created_at, updated_at
    ) VALUES (
        :user_id, :channel_type, "pending", :bank_name, :account_holder, :account_no_masked, :usdt_network,
        :payout_address, :pix_key, :is_default, null, null, null, :created_at, :updated_at
    )')->execute([
        ':user_id' => (int) $user['user_id'],
        ':channel_type' => $channelType,
        ':bank_name' => $bankName !== '' ? $bankName : null,
        ':account_holder' => $accountHolder !== '' ? $accountHolder : null,
        ':account_no_masked' => $maskedAccount !== '' ? $maskedAccount : null,
        ':usdt_network' => $usdtNetwork !== '' ? $usdtNetwork : null,
        ':payout_address' => $payoutAddress !== '' ? $payoutAddress : null,
        ':pix_key' => $pixKey !== '' ? $pixKey : null,
        ':is_default' => $isDefault,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    success('AUTH_PAYOUT_METHOD_SUBMITTED', 'Payout method submitted', []);
}

if (($path === '/index.php/api/user/payout-methods' || $path === '/api/user/payout-methods') && $method === 'GET') {
    $user = require_user($pdo);
    $stmt = $pdo->prepare('SELECT id, channel_type, status, bank_name, account_holder, account_no_masked, usdt_network, payout_address, pix_key, is_default, review_note, created_at, reviewed_at
        FROM user_payout_methods
        WHERE user_id = :user_id
        ORDER BY id DESC');
    $stmt->execute([':user_id' => (int) $user['user_id']]);
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['is_default'] = (bool) $item['is_default'];
        return $item;
    }, $stmt->fetchAll());
    success('AUTH_PAYOUT_METHODS_SUCCESS', 'ok', ['items' => $items]);
}

if (($path === '/index.php/api/user/overview' || $path === '/api/user/overview') && $method === 'GET') {
    $user = require_user($pdo);
    $userId = (int) $user['user_id'];

    $walletStmt = $pdo->prepare('SELECT wallet_code, currency_code, available_balance, reserved_balance
        FROM user_wallet_balances
        WHERE user_id = :user_id
        ORDER BY id ASC');
    $walletStmt->execute([':user_id' => $userId]);
    $wallets = $walletStmt->fetchAll();
    $walletMap = [];
    foreach ($wallets as $wallet) {
        $walletMap[(string) $wallet['wallet_code']] = [
            'wallet_code' => $wallet['wallet_code'],
            'currency_code' => $wallet['currency_code'],
            'available_balance' => $wallet['available_balance'],
            'reserved_balance' => $wallet['reserved_balance'],
        ];
    }

    $tierStmt = $pdo->prepare('SELECT level, group_code, score, merchant_enabled, is_verified, daily_trade_limit, min_sell_amount, margin_amount, margin_ratio, risk_status, violation_message
        FROM user_tier_profiles
        WHERE user_id = :user_id
        LIMIT 1');
    $tierStmt->execute([':user_id' => $userId]);
    $tier = $tierStmt->fetch() ?: null;
    if ($tier) {
        $tier['level'] = normalize_tier_level($tier['level'] ?? 1);
        $tier['score'] = (int) $tier['score'];
        $tier['merchant_enabled'] = json_bool($tier['merchant_enabled']);
        $tier['is_verified'] = json_bool($tier['is_verified']);
        $tier['daily_trade_limit'] = tier_daily_sell_limit_for_level($tier['level']);
    }

    $kycStmt = $pdo->prepare('SELECT id, status, submitted_at, reviewed_at
        FROM user_kyc_applications
        WHERE user_id = :user_id
        ORDER BY id DESC
        LIMIT 1');
    $kycStmt->execute([':user_id' => $userId]);
    $latestKyc = $kycStmt->fetch() ?: null;
    if ($latestKyc) {
        $latestKyc['id'] = (int) $latestKyc['id'];
    }

    $payoutCountStmt = $pdo->prepare('SELECT COUNT(*) FROM user_payout_methods WHERE user_id = :user_id AND status = "approved"');
    $payoutCountStmt->execute([':user_id' => $userId]);
    $approvedPayoutCount = (int) $payoutCountStmt->fetchColumn();

    $defaultPayoutStmt = $pdo->prepare('SELECT id, channel_type, status, bank_name, account_holder, account_no_masked, usdt_network, payout_address, pix_key
        FROM user_payout_methods
        WHERE user_id = :user_id
        ORDER BY is_default DESC, id DESC
        LIMIT 1');
    $defaultPayoutStmt->execute([':user_id' => $userId]);
    $defaultPayout = $defaultPayoutStmt->fetch() ?: null;
    if ($defaultPayout) {
        $defaultPayout['id'] = (int) $defaultPayout['id'];
    }

    $orderCountStmt = $pdo->prepare('SELECT COUNT(*) FROM c2c_orders WHERE (buyer_user_id = :user_id OR seller_user_id = :user_id) AND status IN ("pending_payment", "paid_pending_release", "disputed")');
    $orderCountStmt->execute([':user_id' => $userId]);
    $openOrderCount = (int) $orderCountStmt->fetchColumn();

    $tradeGate = ['action' => 'allow', 'reason' => null];
    if ($tier && !$tier['is_verified']) {
        $tradeGate = ['action' => 'busauth', 'reason' => 'identity_verification_required'];
    } elseif ($approvedPayoutCount === 0) {
        $tradeGate = ['action' => 'bindinfo', 'reason' => 'approved_payout_method_required'];
    }

    success('AUTH_USER_OVERVIEW_SUCCESS', 'ok', [
        'user' => [
            'id' => $userId,
            'username' => $user['username'],
            'email' => $user['email'],
            'mobile' => $user['mobile'] ?? '',
            'country_code' => $user['country_code'] ?? '',
            'status' => $user['status'],
            'lang' => $user['lang'],
            'invitation_code' => $user['invitation_code'] ?? '',
        ],
        'wallets' => $wallets,
        'wallet_map' => $walletMap,
        'tier_profile' => $tier,
        'latest_kyc' => $latestKyc,
        'default_payout_method' => $defaultPayout,
        'approved_payout_method_count' => $approvedPayoutCount,
        'open_order_count' => $openOrderCount,
        'trade_gate' => $tradeGate,
    ]);
}

if (($path === '/index.php/api/user/invite-team' || $path === '/api/user/invite-team') && $method === 'GET') {
    $user = require_user($pdo);
    $userId = (int) $user['user_id'];

    $directCountStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE invited_by_user_id = :user_id');
    $directCountStmt->execute([':user_id' => $userId]);
    $directCount = (int) $directCountStmt->fetchColumn();

    $levelTwoCountStmt = $pdo->prepare('SELECT COUNT(*)
        FROM users
        WHERE invited_by_user_id IN (
            SELECT id FROM users WHERE invited_by_user_id = :user_id
        )');
    $levelTwoCountStmt->execute([':user_id' => $userId]);
    $levelTwoCount = (int) $levelTwoCountStmt->fetchColumn();

    $directUsersStmt = $pdo->prepare('SELECT
            u.id,
            u.username,
            u.email,
            u.created_at,
            EXISTS(
                SELECT 1
                FROM deposit_requests d
                WHERE d.user_id = u.id AND d.status = "approved"
                LIMIT 1
            ) AS first_deposit_completed
        FROM users u
        WHERE u.invited_by_user_id = :user_id
        ORDER BY u.created_at DESC, u.id DESC
        LIMIT 50');
    $directUsersStmt->execute([':user_id' => $userId]);

    $members = array_map(static function (array $item): array {
        $username = trim((string) ($item['username'] ?? ''));
        $email = trim((string) ($item['email'] ?? ''));
        return [
            'id' => (int) $item['id'],
            'username' => $username !== '' ? $username : ($email !== '' ? $email : 'member-' . (int) $item['id']),
            'created_at' => (string) ($item['created_at'] ?? ''),
            'first_deposit_completed' => !empty($item['first_deposit_completed']),
        ];
    }, $directUsersStmt->fetchAll());

    success('AUTH_INVITE_TEAM_SUCCESS', 'ok', [
        'invitation_code' => trim((string) ($user['invitation_code'] ?? '')),
        'direct_count' => $directCount,
        'level_two_count' => $levelTwoCount,
        'total_invites' => $directCount + $levelTwoCount,
        'members' => $members,
    ]);
}

if (($path === '/index.php/api/user/home-snapshot' || $path === '/api/user/home-snapshot') && $method === 'GET') {
    $user = require_user($pdo);
    $activeBanners = array_values(array_filter(home_banner_list($pdo), static fn(array $item): bool => ($item['status'] ?? '') === 'active'));
    $activeTutorialLinks = array_values(array_filter(home_tutorial_link_list($pdo), static function (array $item): bool {
        return ($item['status'] ?? '') === 'active';
    }));
    $tutorialLinksForClient = array_map(static function (array $item): array {
        return [
            'id' => (int) $item['id'],
            'title' => (string) ($item['title'] ?? ''),
            'subtitle' => (string) ($item['subtitle'] ?? ''),
            'link_url' => (string) ($item['link_url'] ?? ''),
            'sort_order' => (int) ($item['sort_order'] ?? 0),
        ];
    }, $activeTutorialLinks);

    $feedStmt = $pdo->prepare('SELECT id, action_type, title, actor_name, asset_code, amount, occurred_at
        FROM trade_feed_events
        WHERE status = "active"
        ORDER BY sort_order ASC, id DESC
        LIMIT 20');
    $feedStmt->execute();
    $tradeFeed = array_map(function (array $item) use ($user): array {
        $item['id'] = (int) $item['id'];
        $item['action_type'] = normalize_trade_feed_action_type($item['action_type'] ?? '');
        $item['display_title'] = trade_feed_display_title($item, (string) ($user['lang'] ?? 'eng'));
        return $item;
    }, $feedStmt->fetchAll());

    $listingStmt = $pdo->prepare('SELECT id, owner_user_id, nickname, side, asset_code, fiat_code, price, min_amount, max_amount,
            available_amount, payment_method_summary, completion_rate,
            badge_vip, badge_pro, badge_stars
        FROM c2c_listings
        WHERE status = "active"
        ORDER BY id DESC
        LIMIT 20');
    $listingStmt->execute();
    $listings = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['owner_user_id'] = (int) $item['owner_user_id'];
        $item['completion_rate'] = normalize_listing_completion_rate($item['completion_rate'] ?? null);
        return attach_listing_badge_fields($item);
    }, $listingStmt->fetchAll());

    success('AUTH_HOME_SNAPSHOT_SUCCESS', 'ok', [
        'noticeText' => get_system_config($pdo, 'trade', 'hall_notice', ''),
        'banners' => $activeBanners,
        'tutorialLinks' => $tutorialLinksForClient,
        'tradeFeed' => $tradeFeed,
        'listings' => $listings,
        'fallbackHints' => [
            'notice' => '',
            'feed' => '',
            'listings' => '',
        ],
    ]);
}

if (($path === '/index.php/api/user/ops-snapshot' || $path === '/api/user/ops-snapshot') && $method === 'GET') {
    $user = require_user($pdo);
    $userId = (int) $user['user_id'];

    $orderPendingStmt = $pdo->prepare('SELECT COUNT(*) FROM c2c_orders
        WHERE (buyer_user_id = :user_id OR seller_user_id = :user_id) AND status = "pending_payment"');
    $orderPendingStmt->execute([':user_id' => $userId]);
    $orderPendingCount = (int) $orderPendingStmt->fetchColumn();

    $orderDisputedStmt = $pdo->prepare('SELECT COUNT(*) FROM c2c_orders
        WHERE (buyer_user_id = :user_id OR seller_user_id = :user_id) AND status = "disputed"');
    $orderDisputedStmt->execute([':user_id' => $userId]);
    $orderDisputedCount = (int) $orderDisputedStmt->fetchColumn();

    $withdrawPendingStmt = $pdo->prepare('SELECT COUNT(*) FROM withdrawal_requests
        WHERE user_id = :user_id AND status = "pending"');
    $withdrawPendingStmt->execute([':user_id' => $userId]);
    $withdrawPendingCount = (int) $withdrawPendingStmt->fetchColumn();

    $withdrawProcessingStmt = $pdo->prepare('SELECT COUNT(*) FROM withdrawal_requests
        WHERE user_id = :user_id AND status = "processing"');
    $withdrawProcessingStmt->execute([':user_id' => $userId]);
    $withdrawProcessingCount = (int) $withdrawProcessingStmt->fetchColumn();

    success('AUTH_OPS_SNAPSHOT_SUCCESS', 'ok', [
        'orderPendingCount' => $orderPendingCount,
        'orderDisputedCount' => $orderDisputedCount,
        'withdrawPendingCount' => $withdrawPendingCount,
        'withdrawProcessingCount' => $withdrawProcessingCount,
        'fallbackHints' => [
            'orders' => '',
            'withdrawals' => '',
        ],
    ]);
}

if (($path === '/index.php/api/user/compliance-snapshot' || $path === '/api/user/compliance-snapshot') && $method === 'GET') {
    $user = require_user($pdo);
    $userId = (int) $user['user_id'];

    $directCountStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE invited_by_user_id = :user_id');
    $directCountStmt->execute([':user_id' => $userId]);
    $directCount = (int) $directCountStmt->fetchColumn();

    $levelTwoCountStmt = $pdo->prepare('SELECT COUNT(*)
        FROM users
        WHERE invited_by_user_id IN (
            SELECT id FROM users WHERE invited_by_user_id = :user_id
        )');
    $levelTwoCountStmt->execute([':user_id' => $userId]);
    $levelTwoCount = (int) $levelTwoCountStmt->fetchColumn();

    $inviteDepositDoneStmt = $pdo->prepare('SELECT COUNT(*)
        FROM users u
        WHERE u.invited_by_user_id = :user_id
          AND EXISTS (
            SELECT 1 FROM deposit_requests d
            WHERE d.user_id = u.id AND d.status = "approved"
            LIMIT 1
          )');
    $inviteDepositDoneStmt->execute([':user_id' => $userId]);
    $inviteDepositDone = (int) $inviteDepositDoneStmt->fetchColumn();

    $payoutPendingStmt = $pdo->prepare('SELECT COUNT(*) FROM user_payout_methods
        WHERE user_id = :user_id AND status = "pending"');
    $payoutPendingStmt->execute([':user_id' => $userId]);
    $payoutPendingCount = (int) $payoutPendingStmt->fetchColumn();

    $kycPendingStmt = $pdo->prepare('SELECT COUNT(*) FROM user_kyc_applications
        WHERE user_id = :user_id AND status = "pending"');
    $kycPendingStmt->execute([':user_id' => $userId]);
    $kycPendingCount = (int) $kycPendingStmt->fetchColumn();

    success('AUTH_COMPLIANCE_SNAPSHOT_SUCCESS', 'ok', [
        'inviteTotal' => $directCount + $levelTwoCount,
        'inviteDepositDone' => $inviteDepositDone,
        'payoutPendingCount' => $payoutPendingCount,
        'kycPendingCount' => $kycPendingCount,
        'fallbackHints' => [
            'invite' => '',
            'reviewQueue' => '',
        ],
    ]);
}

if (($path === '/index.php/api/user/financial-products' || $path === '/api/user/financial-products') && $method === 'GET') {
    process_due_financial_returns($pdo);
    $user = require_user($pdo);
    $userId = (int) $user['user_id'];
    $items = array_map(static fn(array $product): array => serialize_financial_product($pdo, $userId, $product), financial_product_catalog($pdo));
    success('AUTH_FINANCIAL_PRODUCTS_SUCCESS', 'ok', ['items' => $items]);
}

if (($path === '/index.php/api/user/financial-subscriptions' || $path === '/api/user/financial-subscriptions') && $method === 'GET') {
    process_due_financial_returns($pdo);
    $user = require_user($pdo);
    $stmt = $pdo->prepare('SELECT id, product_id, product_code, asset_code, wallet_code, amount, apr_rate, term_days,
            estimated_interest, status, return_mode, return_delay_days, subscribed_at, interest_start_at, maturity_at, return_scheduled_at, settled_at
        FROM user_financial_subscriptions
        WHERE user_id = :user_id
        ORDER BY id DESC');
    $stmt->execute([':user_id' => (int) $user['user_id']]);
    $items = array_map(static function (array $item): array {
        $item['user_id'] = 0;
        $item['returned_by_admin_id'] = null;

        return serialize_financial_subscription($item, true);
    }, $stmt->fetchAll());
    success('AUTH_FINANCIAL_SUBSCRIPTIONS_SUCCESS', 'ok', ['items' => $items, 'timezone' => request_timezone()]);
}

if (($path === '/index.php/api/user/financial-subscriptions' || $path === '/api/user/financial-subscriptions') && $method === 'POST') {
    process_due_financial_returns($pdo);
    $user = require_user($pdo);
    $userId = (int) $user['user_id'];
    $productCode = trim((string) ($input['product_code'] ?? ''));
    $amount = trim((string) ($input['amount'] ?? ''));

    if ($productCode === '' || !is_numeric($amount) || (float) $amount <= 0) {
        failure('AUTH_INVALID_PARAMS', 'Invalid financial subscription params');
    }

    $productStmt = $pdo->prepare('SELECT * FROM financial_products WHERE product_code = :product_code AND status = "active" LIMIT 1');
    $productStmt->execute([':product_code' => $productCode]);
    $product = $productStmt->fetch();
    if (!$product) {
        failure('AUTH_INVALID_PARAMS', 'Financial product not found');
    }

    $amountFloat = (float) $amount;
    $minSubscribe = (float) $product['min_subscribe_amount'];
    if ($amountFloat < $minSubscribe) {
        failure('AUTH_INVALID_PARAMS', 'Amount is below the minimum subscribe threshold');
    }

    $userSubscribed = user_financial_subscribed_amount($pdo, $userId, $productCode);
    $personalLimit = (float) $product['personal_limit_amount'];
    $remainingPersonalLimit = max(0.0, $personalLimit - $userSubscribed);
    if ($amountFloat > $remainingPersonalLimit) {
        failure('AUTH_INVALID_PARAMS', 'Amount exceeds personal remaining quota');
    }

    $totalQuota = (float) $product['total_quota_amount'];
    $soldQuota = (float) $product['sold_quota_amount'];
    $remainingQuota = max(0.0, $totalQuota - $soldQuota);
    if ($amountFloat > $remainingQuota) {
        failure('AUTH_INVALID_PARAMS', 'Amount exceeds product remaining quota');
    }

    $walletStmt = $pdo->prepare('SELECT available_balance FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
    $walletStmt->execute([
        ':user_id' => $userId,
        ':wallet_code' => (string) $product['wallet_code'],
    ]);
    $wallet = $walletStmt->fetch();
    $availableBalance = (float) ($wallet['available_balance'] ?? 0);
    if (!$wallet || $availableBalance < $amountFloat) {
        failure('AUTH_INVALID_PARAMS', 'Insufficient wallet balance');
    }

    $aprRate = (float) $product['apr_rate'];
    $termDays = (int) $product['term_days'];
    $estimatedInterest = $amountFloat * ($aprRate / 100) * ($termDays / 365);
    $subscribedAt = now_iso();
    $interestStartAt = gmdate('c', time() + 86400);
    $maturityAt = gmdate('c', time() + ($termDays * 86400));
    $defaultReturnMode = normalize_financial_return_mode($product['default_return_mode'] ?? 'auto');
    $defaultReturnDelayDays = max(0, (int) ($product['default_return_delay_days'] ?? 0));
    $returnScheduledAt = $defaultReturnMode === 'auto'
        ? financial_return_schedule($maturityAt, $defaultReturnDelayDays)
        : null;

    try {
        $pdo->beginTransaction();

        apply_wallet_delta($pdo, $userId, (string) $product['wallet_code'], -$amountFloat, 0.0);
        $pdo->prepare('UPDATE financial_products
            SET sold_quota_amount = :sold_quota_amount, updated_at = :updated_at
            WHERE id = :id')
            ->execute([
                ':sold_quota_amount' => number_format($soldQuota + $amountFloat, 8, '.', ''),
                ':updated_at' => $subscribedAt,
                ':id' => (int) $product['id'],
            ]);

        $pdo->prepare('INSERT INTO user_financial_subscriptions (
            user_id, product_id, product_code, asset_code, wallet_code, amount, apr_rate, term_days,
            estimated_interest, status, return_mode, return_delay_days, subscribed_at, interest_start_at, maturity_at,
            return_scheduled_at, settled_at, returned_by_admin_id, created_at, updated_at
        ) VALUES (
            :user_id, :product_id, :product_code, :asset_code, :wallet_code, :amount, :apr_rate, :term_days,
            :estimated_interest, "active", :return_mode, :return_delay_days, :subscribed_at, :interest_start_at, :maturity_at,
            :return_scheduled_at, null, null, :created_at, :updated_at
        )')->execute([
            ':user_id' => $userId,
            ':product_id' => (int) $product['id'],
            ':product_code' => (string) $product['product_code'],
            ':asset_code' => (string) $product['asset_code'],
            ':wallet_code' => (string) $product['wallet_code'],
            ':amount' => number_format($amountFloat, 8, '.', ''),
            ':apr_rate' => number_format($aprRate, 2, '.', ''),
            ':term_days' => $termDays,
            ':estimated_interest' => number_format($estimatedInterest, 8, '.', ''),
            ':return_mode' => $defaultReturnMode,
            ':return_delay_days' => $defaultReturnDelayDays,
            ':subscribed_at' => $subscribedAt,
            ':interest_start_at' => $interestStartAt,
            ':maturity_at' => $maturityAt,
            ':return_scheduled_at' => $returnScheduledAt,
            ':created_at' => $subscribedAt,
            ':updated_at' => $subscribedAt,
        ]);

        $subscriptionId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        failure('AUTH_FINANCIAL_SUBSCRIBE_FAILED', 'Financial subscription failed');
    }

    success('AUTH_FINANCIAL_SUBSCRIBED', 'Financial subscription submitted', [
        'subscription_id' => $subscriptionId,
        'product_code' => (string) $product['product_code'],
        'amount' => number_format($amountFloat, 8, '.', ''),
        'asset_code' => (string) $product['asset_code'],
        'estimated_interest' => number_format($estimatedInterest, 8, '.', ''),
        'maturity_at' => $maturityAt,
        'return_mode' => 'manual',
    ]);
}

if (($path === '/index.php/api/user/app-config' || $path === '/api/user/app-config') && $method === 'GET') {
    $user = require_user($pdo);
    $requestedNetworkCode = strtoupper(trim((string) ($_GET['deposit_network'] ?? '')));
    if ($requestedNetworkCode !== '' && !preg_match('/^[A-Z0-9_-]{2,24}$/', $requestedNetworkCode)) {
        $requestedNetworkCode = '';
    }
    $userDepositAddresses = user_enabled_deposit_addresses($pdo, (int) $user['user_id'], 'USDT');
    if ($userDepositAddresses) {
        $enabledUsdtNetworks = array_map(static function (array $item): array {
            return [
                'network_code' => (string) $item['network_code'],
                'network_label' => (string) $item['network_label'],
                'enabled_count' => 1,
            ];
        }, $userDepositAddresses);
        $selectedUsdtTrc20Address = null;
        foreach ($userDepositAddresses as $item) {
            if ((string) $item['network_code'] === $requestedNetworkCode) {
                $selectedUsdtTrc20Address = serialize_deposit_address($item);
                break;
            }
        }
        if (!$selectedUsdtTrc20Address && $requestedNetworkCode === '') {
            $selectedUsdtTrc20Address = serialize_deposit_address($userDepositAddresses[0]);
        }
        if ($selectedUsdtTrc20Address) {
            $resolvedNetworkCode = (string) ($selectedUsdtTrc20Address['network_code'] ?? 'TRC20');
        } else {
            $resolvedNetworkCode = $requestedNetworkCode !== '' ? $requestedNetworkCode : 'TRC20';
        }
    } else {
        $enabledUsdtNetworks = [];
        $resolvedNetworkCode = $requestedNetworkCode !== '' ? $requestedNetworkCode : 'TRC20';
        $selectedUsdtTrc20Address = null;
    }

    // 優先使用該用戶在 deposit_addresses 的啟用地址；僅當「當前鏈無有效配置」時才回退 system_configs 全站預設（預設不寫入、不覆蓋 per-user 列）。
    $selectedAddressEmpty = !$selectedUsdtTrc20Address || trim((string) ($selectedUsdtTrc20Address['address'] ?? '')) === '';
    if ($selectedAddressEmpty) {
        $netForFallback = $requestedNetworkCode !== '' ? $requestedNetworkCode : $resolvedNetworkCode;
        if (!in_array($netForFallback, ['TRC20', 'ERC20', 'BEP20'], true)) {
            $netForFallback = 'TRC20';
        }
        $fallbackAddr = platform_default_usdt_deposit_address($pdo, $netForFallback);
        if ($fallbackAddr !== '') {
            $selectedUsdtTrc20Address = serialize_deposit_address(synthetic_platform_deposit_row($netForFallback, $fallbackAddr));
            $resolvedNetworkCode = $netForFallback;
        }
        if (!$userDepositAddresses) {
            $enabledUsdtNetworks = [];
            foreach (['TRC20', 'ERC20', 'BEP20'] as $nc) {
                if (platform_default_usdt_deposit_address($pdo, $nc) !== '') {
                    $enabledUsdtNetworks[] = [
                        'network_code' => $nc,
                        'network_label' => 'USDT(' . $nc . ')',
                        'enabled_count' => 1,
                    ];
                }
            }
        }
    }

    success('AUTH_APP_CONFIG_SUCCESS', 'ok', [
        'finance' => [
            'usdt_to_eur_rate' => get_system_config($pdo, 'finance', 'usdt_to_eur_rate', '0.96000000'),
            'eur_to_usdt_withdraw_rate' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_rate', get_system_config($pdo, 'finance', 'usdt_to_eur_rate', '0.96000000')),
            'eur_to_usdt_withdraw_fee_mode' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_mode', 'percent'),
            'eur_to_usdt_withdraw_fee_rate' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_rate', '0.00'),
            'eur_to_usdt_withdraw_fee_fixed_usdt' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_fixed_usdt', '0.00'),
            'selected_deposit_network' => $selectedUsdtTrc20Address['network_code'] ?? $resolvedNetworkCode,
            'usdt_deposit_networks' => $enabledUsdtNetworks,
            'usdt_deposit_address' => $selectedUsdtTrc20Address ?? [
                'id' => null,
                'asset_code' => 'USDT',
                'network_code' => $resolvedNetworkCode,
                'network_label' => 'USDT(' . $resolvedNetworkCode . ')',
                'address' => '',
                'qr_code_url' => null,
                'remark' => null,
                'status' => 'disabled',
                'created_at' => null,
                'updated_at' => null,
            ],
        ],
        'trade' => [
            'hall_notice' => get_system_config($pdo, 'trade', 'hall_notice', ''),
        ],
        'home' => [
            'banners' => array_values(array_filter(home_banner_list($pdo), static fn (array $item): bool => $item['status'] === 'active')),
        ],
        'site' => site_config_payload($pdo),
        'market' => [
            'items' => fetch_market_snapshot(),
            'source' => 'binance',
        ],
    ]);
}

if (($path === '/index.php/api/user/trade-feed-events' || $path === '/api/user/trade-feed-events') && $method === 'GET') {
    $user = require_user($pdo);
    $pageSize = max(1, min(20, (int) ($_GET['page_size'] ?? ($_GET['limit'] ?? 7))));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare('SELECT id, action_type, title, actor_name, asset_code, amount, occurred_at
        FROM trade_feed_events
        WHERE status = "active"
        ORDER BY sort_order ASC, id DESC
        LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $pageSize + 1, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $hasMore = count($rows) > $pageSize;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $pageSize);
    }
    $items = array_map(function (array $item) use ($user): array {
        $item['id'] = (int) $item['id'];
        $item['action_type'] = normalize_trade_feed_action_type($item['action_type'] ?? '');
        $item['display_title'] = trade_feed_display_title($item, (string) ($user['lang'] ?? 'eng'));
        return $item;
    }, $rows);
    success('AUTH_TRADE_FEED_SUCCESS', 'ok', [
        'items' => $items,
        'page' => $page,
        'page_size' => $pageSize,
        'has_more' => $hasMore,
    ]);
}

if (($path === '/index.php/api/user/uploads' || $path === '/api/user/uploads') && $method === 'POST') {
    require_user($pdo);
    $category = trim((string) ($_POST['category'] ?? 'general'));
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        failure('AUTH_INVALID_PARAMS', 'Upload file is required');
    }
    success('AUTH_UPLOAD_SUCCESS', 'Upload successful', store_uploaded_file($_FILES['file'], $category));
}

if (($path === '/index.php/api/admin/uploads' || $path === '/api/admin/uploads') && $method === 'POST') {
    enforce_admin_ip_allowlist();
    require_admin($pdo);
    $category = trim((string) ($_POST['category'] ?? 'general'));
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        failure('ADMIN_INVALID_PARAMS', 'Upload file is required');
    }
    success('ADMIN_UPLOAD_SUCCESS', 'Upload successful', store_uploaded_file($_FILES['file'], $category));
}

function normalize_listing_completion_rate(mixed $value): ?string
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    $text = str_replace(['％', ' '], ['%', ''], $text);
    $numericText = str_ends_with($text, '%') ? substr($text, 0, -1) : $text;
    if (!is_numeric($numericText)) {
        return $text;
    }

    $rate = max(0.0, min(100.0, (float) $numericText));
    if (abs($rate - round($rate)) < 0.000001) {
        return sprintf('%d%%', (int) round($rate));
    }

    return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
}

function normalize_listing_badge_int(mixed $value): int
{
    if ($value === true || $value === 1 || $value === '1') {
        return 1;
    }
    if (is_string($value)) {
        $t = strtolower(trim($value));
        if ($t === 'true' || $t === 'on' || $t === 'yes') {
            return 1;
        }
    }

    return 0;
}

function attach_listing_badge_fields(array $row): array
{
    foreach (['badge_vip', 'badge_pro', 'badge_stars'] as $key) {
        $row[$key] = normalize_listing_badge_int($row[$key] ?? 0);
    }

    return $row;
}

if (($path === '/index.php/api/user/listings' || $path === '/api/user/listings') && $method === 'GET') {
    require_user($pdo);
    $side = trim((string) ($_GET['side'] ?? ''));
    $where = 'WHERE l.status = "active"';
    $params = [];
    if ($side !== '') {
        $where .= ' AND l.side = :side';
        $params[':side'] = $side;
    }
    $limit = (int) ($_GET['page_size'] ?? ($_GET['limit'] ?? 0));
    $limit = max(0, min(100, $limit));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = $limit > 0 ? (($page - 1) * $limit) : 0;
    $orderBy = $limit > 0 ? 'ORDER BY l.id DESC' : 'ORDER BY l.id ASC';
    $limitSql = $limit > 0 ? ' LIMIT ' . ($limit + 1) . ' OFFSET ' . $offset : '';
    $stmt = $pdo->prepare("SELECT l.id, l.owner_user_id, l.nickname, l.side, l.asset_code, l.fiat_code, l.price, l.min_amount, l.max_amount,
            l.available_amount, l.payment_method_summary, l.completion_rate,
            l.badge_vip, l.badge_pro, l.badge_stars
        FROM c2c_listings l
        {$where}
        {$orderBy}{$limitSql}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $hasMore = $limit > 0 && count($rows) > $limit;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $limit);
    }
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['owner_user_id'] = (int) $item['owner_user_id'];
        $item['completion_rate'] = normalize_listing_completion_rate($item['completion_rate'] ?? null);
        return attach_listing_badge_fields($item);
    }, $rows);
    success('AUTH_LISTINGS_SUCCESS', 'ok', [
        'items' => $items,
        'page' => $limit > 0 ? $page : 1,
        'page_size' => $limit,
        'has_more' => $hasMore,
    ]);
}

if ((preg_match('#^/index.php/api/user/listings/(\d+)$#', $path, $matches) || preg_match('#^/api/user/listings/(\d+)$#', $path, $matches)) && $method === 'GET') {
    require_user($pdo);
    $listingId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT id, owner_user_id, nickname, side, asset_code, fiat_code, price, min_amount, max_amount, available_amount, payment_method_summary, completion_rate,
            badge_vip, badge_pro, badge_stars
        FROM c2c_listings
        WHERE id = :id AND status = "active"
        LIMIT 1');
    $stmt->execute([':id' => $listingId]);
    $listing = $stmt->fetch();
    if (!$listing) {
        failure('AUTH_INVALID_PARAMS', 'Listing not found');
    }
    $listing['id'] = (int) $listing['id'];
    $listing['owner_user_id'] = (int) $listing['owner_user_id'];
    $listing['completion_rate'] = normalize_listing_completion_rate($listing['completion_rate'] ?? null);
    $listing = attach_listing_badge_fields($listing);
    success('AUTH_LISTING_DETAIL_SUCCESS', 'ok', ['listing' => $listing]);
}

if (($path === '/index.php/api/user/orders' || $path === '/api/user/orders') && $method === 'POST') {
    $user = require_user($pdo);
    $listingId = (int) ($input['listing_id'] ?? 0);
    $amount = trim((string) ($input['amount'] ?? ''));
    if ($listingId <= 0 || !is_numeric($amount) || (float) $amount <= 0) {
        failure('AUTH_INVALID_PARAMS', 'Invalid order params');
    }
    $stmt = $pdo->prepare('SELECT * FROM c2c_listings WHERE id = :id AND status = "active" LIMIT 1');
    $stmt->execute([':id' => $listingId]);
    $listing = $stmt->fetch();
    if (!$listing) {
        failure('AUTH_INVALID_PARAMS', 'Listing not found');
    }
    $amountFloat = (float) $amount;
    if ($amountFloat < (float) $listing['min_amount'] || $amountFloat > (float) $listing['max_amount']) {
        failure('AUTH_INVALID_PARAMS', 'Amount is outside listing range');
    }
    if ($amountFloat > (float) $listing['available_amount']) {
        failure('AUTH_INVALID_PARAMS', 'Amount exceeds listing availability');
    }
    if ((int) $listing['owner_user_id'] === (int) $user['user_id']) {
        failure('AUTH_INVALID_PARAMS', 'Cannot trade with your own listing');
    }

    $price = (float) $listing['price'];
    $totalAmount = $amountFloat * $price;
    $listingSide = (string) $listing['side'];
    $orderSide = $listingSide;
    $buyerUserId = $orderSide === 'buy' ? (int) $user['user_id'] : (int) $listing['owner_user_id'];
    $sellerUserId = $orderSide === 'sell' ? (int) $user['user_id'] : (int) $listing['owner_user_id'];
    $now = now_iso();

    if ($orderSide === 'sell') {
        $sellerTierStmt = $pdo->prepare('SELECT level FROM user_tier_profiles WHERE user_id = :user_id LIMIT 1');
        $sellerTierStmt->execute([':user_id' => $sellerUserId]);
        $sellerTier = $sellerTierStmt->fetch();
        $sellerLevel = normalize_tier_level($sellerTier['level'] ?? 1);
        $dailySellLimit = tier_daily_sell_limit_for_level($sellerLevel);
        if ($dailySellLimit !== null) {
            $dayStart = gmdate('Y-m-d\T00:00:00\Z');
            $dayEnd = gmdate('Y-m-d\T23:59:59\Z');
            $dailySellCountStmt = $pdo->prepare('SELECT COUNT(*)
                FROM c2c_orders
                WHERE seller_user_id = :seller_user_id
                  AND side = "sell"
                  AND created_at >= :day_start
                  AND created_at <= :day_end');
            $dailySellCountStmt->execute([
                ':seller_user_id' => $sellerUserId,
                ':day_start' => $dayStart,
                ':day_end' => $dayEnd,
            ]);
            $dailySellCount = (int) $dailySellCountStmt->fetchColumn();
            if ($dailySellCount >= $dailySellLimit) {
                failure('AUTH_TIER_DAILY_SELL_LIMIT_REACHED', 'Daily sell order limit reached');
            }
        }
    }

    try {
        $pdo->beginTransaction();

        if ($orderSide === 'sell') {
            reserve_sell_order_assets($pdo, $sellerUserId, $amountFloat);
        } elseif ($orderSide === 'buy') {
            reserve_buy_order_fiat($pdo, $buyerUserId, (string) $listing['fiat_code'], $totalAmount);
        }

        $inventoryStmt = $pdo->prepare('UPDATE c2c_listings
            SET available_amount = CAST(available_amount AS DECIMAL(24,8)) - :amount,
                updated_at = :updated_at
            WHERE id = :id
              AND status = "active"
              AND CAST(available_amount AS DECIMAL(24,8)) >= :amount_check');
        $inventoryStmt->execute([
            ':amount' => number_format($amountFloat, 8, '.', ''),
            ':amount_check' => number_format($amountFloat, 8, '.', ''),
            ':updated_at' => $now,
            ':id' => $listingId,
        ]);
        if ($inventoryStmt->rowCount() !== 1) {
            throw new RuntimeException('listing_availability');
        }

        $pdo->prepare('INSERT INTO c2c_orders (
            order_no, listing_id, side, buyer_user_id, seller_user_id, amount, price, total_amount, asset_code, fiat_code,
            payment_method_summary, status, completed_at, cancel_reason, dispute_reason, created_at, updated_at
        ) VALUES (
            :order_no, :listing_id, :side, :buyer_user_id, :seller_user_id, :amount, :price, :total_amount, :asset_code, :fiat_code,
            :payment_method_summary, :status, null, null, null, :created_at, :updated_at
        )')->execute([
            ':order_no' => 'OD' . strtoupper(substr(random_token(14), 0, 10)),
            ':listing_id' => $listingId,
            ':side' => $orderSide,
            ':buyer_user_id' => $buyerUserId,
            ':seller_user_id' => $sellerUserId,
            ':amount' => number_format($amountFloat, 8, '.', ''),
            ':price' => number_format($price, 8, '.', ''),
            ':total_amount' => number_format($totalAmount, 8, '.', ''),
            ':asset_code' => $listing['asset_code'],
            ':fiat_code' => $listing['fiat_code'],
            ':payment_method_summary' => $listing['payment_method_summary'],
            ':status' => $orderSide === 'sell' ? 'paid_pending_release' : 'pending_payment',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO order_evidences (order_id, actor_type, actor_id, evidence_type, content, attachment_url, created_at)
            VALUES (:order_id, "system", null, "timeline", :content, null, :created_at)')
            ->execute([
                ':order_id' => $orderId,
                ':content' => $orderSide === 'sell'
                    ? 'Order created from listing #' . $listingId . ', seller USDT deducted, awaiting fiat release'
                    : 'Order created from listing #' . $listingId . ', buyer fiat reserved, awaiting payment confirmation',
                ':created_at' => $now,
            ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception->getMessage() === 'listing_availability') {
            failure('AUTH_INVALID_PARAMS', 'Amount exceeds listing availability');
        }
        failure('AUTH_ORDER_CREATE_FAILED', 'Order creation failed');
    }
    success('AUTH_ORDER_CREATED', 'Order created', ['order_id' => $orderId]);
}

if (($path === '/index.php/api/user/fund-records' || $path === '/api/user/fund-records') && $method === 'GET') {
    process_due_financial_returns($pdo);
    $user = require_user($pdo);
    $records = [];

    $depositStmt = $pdo->prepare('SELECT id, amount, asset_code, status, created_at, reviewed_at FROM deposit_requests WHERE user_id = :user_id');
    $depositStmt->execute([':user_id' => (int) $user['user_id']]);
    foreach ($depositStmt->fetchAll() as $item) {
        $records[] = [
            'id' => 'deposit-' . $item['id'],
            'record_id' => (int) $item['id'],
            'type' => 'recharge',
            'title' => 'Deposit request',
            'amount' => $item['amount'],
            'asset_code' => $item['asset_code'],
            'meta' => sprintf('%s · %s', $item['asset_code'], $item['status']),
            'value' => '+' . $item['amount'],
            'status' => $item['status'],
            'created_at' => $item['created_at'],
            'reviewed_at' => $item['reviewed_at'],
            'route' => '/pages/setting/rechargeDetail?request_id=' . (int) $item['id'],
        ];
    }

    $withdrawStmt = $pdo->prepare('SELECT id, amount, asset_code, channel_type, status, created_at, reviewed_at FROM withdrawal_requests WHERE user_id = :user_id');
    $withdrawStmt->execute([':user_id' => (int) $user['user_id']]);
    foreach ($withdrawStmt->fetchAll() as $item) {
        $records[] = [
            'id' => 'withdraw-' . $item['id'],
            'record_id' => (int) $item['id'],
            'type' => 'withdraw',
            'title' => 'Withdrawal request',
            'amount' => $item['amount'],
            'asset_code' => $item['asset_code'],
            'channel_type' => $item['channel_type'],
            'meta' => sprintf('%s · %s · %s', $item['asset_code'], $item['channel_type'], $item['status']),
            'value' => '-' . $item['amount'],
            'status' => $item['status'],
            'created_at' => $item['created_at'],
            'reviewed_at' => $item['reviewed_at'],
            'route' => '/pages/setting/withdrawDetail?request_id=' . (int) $item['id'],
        ];
    }

    $financialStmt = $pdo->prepare('SELECT id, product_code, asset_code, amount, estimated_interest, status, return_mode, return_delay_days, subscribed_at, maturity_at, return_scheduled_at, settled_at
        FROM user_financial_subscriptions
        WHERE user_id = :user_id');
    $financialStmt->execute([':user_id' => (int) $user['user_id']]);
    foreach ($financialStmt->fetchAll() as $item) {
        $records[] = [
            'id' => 'financial-' . $item['id'],
            'record_id' => (int) $item['id'],
            'type' => 'financial',
            'title' => 'Financial subscription',
            'product_code' => $item['product_code'],
            'amount' => $item['amount'],
            'asset_code' => $item['asset_code'],
            'estimated_interest' => $item['estimated_interest'],
            'meta' => sprintf(
                '%s · est. %s %s · %s · %s',
                $item['product_code'],
                $item['estimated_interest'],
                $item['asset_code'],
                $item['status'],
                normalize_financial_return_mode($item['return_mode'] ?? 'manual') === 'auto'
                    ? 'auto+' . (int) ($item['return_delay_days'] ?? 0) . 'd'
                    : 'manual'
            ),
            'value' => '-' . $item['amount'],
            'status' => $item['status'],
            'created_at' => $item['subscribed_at'],
            'reviewed_at' => $item['settled_at'] ?: ($item['return_scheduled_at'] ?: $item['maturity_at']),
            'route' => '/pages/index/financial',
        ];
    }

    usort($records, static fn(array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

    foreach ($records as &$record) {
        $record = localize_timestamps_on_row($record, ['created_at', 'reviewed_at']);
    }
    unset($record);

    success('AUTH_FUND_RECORDS_SUCCESS', 'ok', ['items' => $records, 'timezone' => request_timezone()]);
}

if (($path === '/index.php/api/user/orders/summary' || $path === '/api/user/orders/summary') && $method === 'GET') {
    $user = require_user($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM c2c_orders
        WHERE (buyer_user_id = :user_id OR seller_user_id = :user_id)
          AND status IN ("pending_payment", "paid_pending_release", "disputed")');
    $stmt->execute([':user_id' => (int) $user['user_id']]);
    success('AUTH_ORDERS_SUMMARY_SUCCESS', 'ok', [
        'active_count' => (int) $stmt->fetchColumn(),
    ]);
}

if (($path === '/index.php/api/user/orders' || $path === '/api/user/orders') && $method === 'GET') {
    $user = require_user($pdo);
    $stmt = $pdo->prepare('SELECT o.id, o.order_no, o.side, o.buyer_user_id, o.seller_user_id, o.amount, o.price, o.total_amount,
            o.asset_code, o.fiat_code, o.payment_method_summary, o.status, o.created_at, o.updated_at,
            buyer.username AS buyer_username, buyer.email AS buyer_email, seller.username AS seller_username, seller.email AS seller_email
        FROM c2c_orders o
        LEFT JOIN users buyer ON buyer.id = o.buyer_user_id
        LEFT JOIN users seller ON seller.id = o.seller_user_id
        WHERE o.buyer_user_id = :user_id OR o.seller_user_id = :user_id
        ORDER BY o.id DESC');
    $stmt->execute([':user_id' => (int) $user['user_id']]);
    $items = array_map(static function (array $item) use ($user): array {
        $item['id'] = (int) $item['id'];
        $item['buyer_user_id'] = (int) $item['buyer_user_id'];
        $item['seller_user_id'] = (int) $item['seller_user_id'];
        $item['group'] = in_array($item['status'], ['completed', 'cancelled'], true) ? 'history' : 'open';
        $item['role'] = $item['buyer_user_id'] === (int) $user['user_id'] ? 'buyer' : 'seller';
        $item['counterparty_display_name'] = $item['role'] === 'buyer'
            ? (($item['seller_email'] ?? '') !== '' ? (string) $item['seller_email'] : ((($item['seller_username'] ?? '') !== '') ? (string) $item['seller_username'] : 'Merchant'))
            : (($item['buyer_email'] ?? '') !== '' ? (string) $item['buyer_email'] : ((($item['buyer_username'] ?? '') !== '') ? (string) $item['buyer_username'] : 'Merchant'));
        $item['counterparty_username'] = $item['counterparty_display_name'];
        $item['created_at_utc'] = (string) $item['created_at'];
        $item['updated_at_utc'] = (string) $item['updated_at'];
        $item['created_at'] = format_time_for_request_timezone($item['created_at']) ?? (string) $item['created_at'];
        $item['updated_at'] = format_time_for_request_timezone($item['updated_at']) ?? (string) $item['updated_at'];
        return $item;
    }, $stmt->fetchAll());
    success('AUTH_ORDERS_SUCCESS', 'ok', ['items' => $items, 'timezone' => request_timezone()]);
}

if ((preg_match('#^/index.php/api/user/orders/(\d+)$#', $path, $matches) || preg_match('#^/api/user/orders/(\d+)$#', $path, $matches)) && $method === 'GET') {
    $user = require_user($pdo);
    $orderId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT o.*, buyer.username AS buyer_username, buyer.email AS buyer_email,
            seller.username AS seller_username, seller.email AS seller_email
        FROM c2c_orders o
        LEFT JOIN users buyer ON buyer.id = o.buyer_user_id
        LEFT JOIN users seller ON seller.id = o.seller_user_id
        WHERE o.id = :id AND (o.buyer_user_id = :user_id OR o.seller_user_id = :user_id)
        LIMIT 1');
    $stmt->execute([
        ':id' => $orderId,
        ':user_id' => (int) $user['user_id'],
    ]);
    $order = $stmt->fetch();
    if (!$order) {
        failure('AUTH_INVALID_PARAMS', 'Order not found');
    }
    $evidenceStmt = $pdo->prepare('SELECT id, actor_type, actor_id, evidence_type, content, attachment_url, created_at
        FROM order_evidences
        WHERE order_id = :order_id
        ORDER BY id DESC');
    $evidenceStmt->execute([':order_id' => $orderId]);
    $order['id'] = (int) $order['id'];
    $order['buyer_user_id'] = (int) $order['buyer_user_id'];
    $order['seller_user_id'] = (int) $order['seller_user_id'];
    $order['role'] = $order['buyer_user_id'] === (int) $user['user_id'] ? 'buyer' : 'seller';
    $order['counterparty_role'] = $order['role'] === 'buyer' ? 'seller' : 'buyer';
    $order['counterparty_display_name'] = $order['role'] === 'buyer'
        ? (($order['seller_email'] ?? '') !== '' ? (string) $order['seller_email'] : ((($order['seller_username'] ?? '') !== '') ? (string) $order['seller_username'] : 'Merchant'))
        : (($order['buyer_email'] ?? '') !== '' ? (string) $order['buyer_email'] : ((($order['buyer_username'] ?? '') !== '') ? (string) $order['buyer_username'] : 'Merchant'));
    $order['counterparty_username'] = $order['counterparty_display_name'];
    $order['created_at_utc'] = (string) $order['created_at'];
    $order['updated_at_utc'] = (string) $order['updated_at'];
    $order['completed_at_utc'] = !empty($order['completed_at']) ? (string) $order['completed_at'] : null;
    $order['created_at'] = format_time_for_request_timezone($order['created_at']) ?? (string) $order['created_at'];
    $order['updated_at'] = format_time_for_request_timezone($order['updated_at']) ?? (string) $order['updated_at'];
    $order['completed_at'] = format_time_for_request_timezone($order['completed_at']);
    success('AUTH_ORDER_DETAIL_SUCCESS', 'ok', [
        'order' => $order,
        'evidences' => array_map(static function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['actor_id'] = $item['actor_id'] !== null ? (int) $item['actor_id'] : null;
            $item['created_at_utc'] = (string) $item['created_at'];
            $item['created_at'] = format_time_for_request_timezone($item['created_at']) ?? (string) $item['created_at'];
            return $item;
        }, $evidenceStmt->fetchAll()),
        'timezone' => request_timezone(),
    ]);
}

if ((preg_match('#^/index.php/api/user/orders/(\d+)/cancel$#', $path, $matches) || preg_match('#^/api/user/orders/(\d+)/cancel$#', $path, $matches)) && $method === 'POST') {
    $user = require_user($pdo);
    $orderId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? 'user_cancelled'));
    if ($reason === '') {
        $reason = 'user_cancelled';
    }
    $stmt = $pdo->prepare('SELECT * FROM c2c_orders WHERE id = :id AND (buyer_user_id = :user_id OR seller_user_id = :user_id) LIMIT 1');
    $stmt->execute([
        ':id' => $orderId,
        ':user_id' => (int) $user['user_id'],
    ]);
    $order = $stmt->fetch();
    if (!$order) {
        failure('AUTH_INVALID_PARAMS', 'Order not found');
    }
    if (in_array((string) $order['status'], ['completed', 'cancelled'], true)) {
        failure('AUTH_INVALID_PARAMS', 'Order status is invalid');
    }

    try {
        $pdo->beginTransaction();
        refund_sell_order_assets($pdo, $order);
        refund_buy_order_fiat($pdo, $order);
        restore_c2c_listing_inventory($pdo, $order);
        $pdo->prepare('UPDATE c2c_orders
            SET status = "cancelled", cancel_reason = :cancel_reason, updated_at = :updated_at
            WHERE id = :id')
            ->execute([
                ':cancel_reason' => $reason,
                ':updated_at' => now_iso(),
                ':id' => $orderId,
            ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        failure('AUTH_ORDER_CANCEL_FAILED', 'Order cancel failed');
    }
    audit($pdo, 'user', 'order_cancelled', 'user', (int) $user['user_id'], 'order', $orderId, $reason, ['status' => $order['status']], ['status' => 'cancelled']);
    success('AUTH_ORDER_CANCELLED', 'Order cancelled', []);
}

if ((preg_match('#^/index.php/api/user/orders/(\d+)/dispute$#', $path, $matches) || preg_match('#^/api/user/orders/(\d+)/dispute$#', $path, $matches)) && $method === 'POST') {
    $user = require_user($pdo);
    $orderId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '') {
        failure('AUTH_INVALID_PARAMS', 'Dispute reason is required');
    }
    $stmt = $pdo->prepare('SELECT * FROM c2c_orders WHERE id = :id AND (buyer_user_id = :user_id OR seller_user_id = :user_id) LIMIT 1');
    $stmt->execute([
        ':id' => $orderId,
        ':user_id' => (int) $user['user_id'],
    ]);
    $order = $stmt->fetch();
    if (!$order) {
        failure('AUTH_INVALID_PARAMS', 'Order not found');
    }
    $now = now_iso();
    $pdo->prepare('UPDATE c2c_orders SET status = "disputed", dispute_reason = :dispute_reason, updated_at = :updated_at WHERE id = :id')
        ->execute([
            ':dispute_reason' => $reason,
            ':updated_at' => $now,
            ':id' => $orderId,
        ]);
    $pdo->prepare('INSERT INTO order_evidences (order_id, actor_type, actor_id, evidence_type, content, attachment_url, created_at)
        VALUES (:order_id, "user", :actor_id, "dispute", :content, null, :created_at)')
        ->execute([
            ':order_id' => $orderId,
            ':actor_id' => (int) $user['user_id'],
            ':content' => $reason,
            ':created_at' => $now,
        ]);
    success('AUTH_ORDER_DISPUTE_SUBMITTED', 'Order dispute submitted', []);
}

if ((preg_match('#^/index.php/api/user/orders/(\d+)/evidences$#', $path, $matches) || preg_match('#^/api/user/orders/(\d+)/evidences$#', $path, $matches)) && $method === 'POST') {
    $user = require_user($pdo);
    $orderId = (int) $matches[1];
    $content = trim((string) ($input['content'] ?? ''));
    $attachmentUrl = trim((string) ($input['attachment_url'] ?? ''));
    $evidenceType = trim((string) ($input['evidence_type'] ?? 'note'));
    if ($content === '' && $attachmentUrl === '') {
        failure('AUTH_INVALID_PARAMS', 'Evidence content is required');
    }
    $stmt = $pdo->prepare('SELECT id FROM c2c_orders WHERE id = :id AND (buyer_user_id = :user_id OR seller_user_id = :user_id) LIMIT 1');
    $stmt->execute([
        ':id' => $orderId,
        ':user_id' => (int) $user['user_id'],
    ]);
    if (!$stmt->fetch()) {
        failure('AUTH_INVALID_PARAMS', 'Order not found');
    }
    $pdo->prepare('INSERT INTO order_evidences (order_id, actor_type, actor_id, evidence_type, content, attachment_url, created_at)
        VALUES (:order_id, "user", :actor_id, :evidence_type, :content, :attachment_url, :created_at)')
        ->execute([
            ':order_id' => $orderId,
            ':actor_id' => (int) $user['user_id'],
            ':evidence_type' => $evidenceType,
            ':content' => $content !== '' ? $content : null,
            ':attachment_url' => $attachmentUrl !== '' ? $attachmentUrl : null,
            ':created_at' => now_iso(),
        ]);
    success('AUTH_ORDER_EVIDENCE_SUBMITTED', 'Order evidence submitted', []);
}

if ($path === '/api/admin/auth/login' && $method === 'POST') {
    enforce_admin_ip_allowlist();
    $account = trim((string) ($input['account'] ?? ''));
    $password = trim((string) ($input['password'] ?? ''));
    $ip = request_ip();

    if ($account === '' || $password === '') {
        failure('ADMIN_INVALID_PARAMS', 'Invalid parameters');
    }
    admin_login_assert_not_limited($pdo, $account, $ip);

    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = :account OR name = :account LIMIT 1');
    $stmt->execute([':account' => $account]);
    $admin = $stmt->fetch();

    if (!$admin) {
        admin_login_record_failure($pdo, $account, $ip);
        failure('ADMIN_ACCOUNT_INCORRECT', 'Admin account is incorrect');
    }
    if (admin_temp_locked_until($admin)) {
        failure('ADMIN_ACCOUNT_TEMP_LOCKED', 'Admin account is temporarily locked', [
            'locked_until' => admin_temp_locked_until($admin),
            'lock_state' => 'temporary',
        ], 423);
    }
    if (($admin['status'] ?? '') !== 'normal') {
        failure('ADMIN_ACCOUNT_LOCKED', 'Admin account is locked');
    }
    ensure_admin_staff_invite_code($pdo, $admin);
    if (!password_verify($password, (string) $admin['password_hash'])) {
        admin_login_record_failure($pdo, $account, $ip);
        $lockResult = admin_register_failed_login($pdo, $admin);
        if ($lockResult['locked']) {
            if ($lockResult['permanent']) {
                audit(
                    $pdo,
                    'admin',
                    'admin_login_permanent_locked',
                    'system',
                    null,
                    'admin_user',
                    (int) $admin['id'],
                    null,
                    null,
                    [
                        'account' => $admin['name'],
                        'ip' => $ip,
                        'lock_level' => $lockResult['lock_level'],
                    ]
                );
                failure('ADMIN_ACCOUNT_LOCKED', 'Admin account is permanently locked');
            }
            audit(
                $pdo,
                'admin',
                'admin_login_temporary_locked',
                'system',
                null,
                'admin_user',
                (int) $admin['id'],
                null,
                null,
                [
                    'account' => $admin['name'],
                    'ip' => $ip,
                    'locked_until' => $lockResult['locked_until'],
                    'lock_level' => $lockResult['lock_level'],
                ]
            );
            failure('ADMIN_ACCOUNT_TEMP_LOCKED', 'Admin account is temporarily locked', [
                'locked_until' => $lockResult['locked_until'],
                'lock_state' => 'temporary',
            ], 423);
        }
        failure('ADMIN_PASSWORD_INCORRECT', 'Admin password is incorrect');
    }
    admin_login_clear_failures($pdo, $account, $ip);
    admin_clear_account_lock_metadata($pdo, (int) $admin['id'], false);

    $token = random_token(48);
    $now = now_iso();
    $expiresAt = gmdate('c', time() + 86400);
    $pdo->prepare('INSERT INTO admin_tokens (admin_user_id, token, issued_at, expires_at, revoked_at, created_at, updated_at)
        VALUES (:admin_user_id, :token, :issued_at, :expires_at, null, :created_at, :updated_at)')
        ->execute([
            ':admin_user_id' => $admin['id'],
            ':token' => $token,
            ':issued_at' => $now,
            ':expires_at' => $expiresAt,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    audit($pdo, 'admin', 'admin_login_success', 'admin', (int) $admin['id'], 'admin_user', (int) $admin['id']);

    success('ADMIN_LOGIN_SUCCESS', 'ok', [
        'token' => $token,
        'admin' => [
            'id' => (int) $admin['id'],
            'account' => $admin['name'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'display_name' => $admin['display_name'] ?? null,
            'staff_invite_code' => ensure_admin_staff_invite_code($pdo, $admin),
            'role_codes' => admin_role_codes($admin),
            'permissions' => admin_permissions($admin),
            'is_root_admin' => admin_is_root_admin($admin),
            'role_template' => admin_role_template($admin, $pdo),
            'admin_group_code' => admin_group_code($admin) !== '' ? admin_group_code($admin) : null,
            'admin_group_name' => admin_group_name($admin) !== '' ? admin_group_name($admin) : null,
            'can_view_group_global_data' => admin_can_view_group_global_data($admin),
            'password_must_change' => !empty($admin['password_must_change']),
        ],
    ]);
}

if ($path === '/api/admin/auth/logout' && $method === 'POST') {
    $admin = require_admin($pdo);
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    preg_match('/Bearer\s+(.+)/i', $header, $matches);
    $token = trim($matches[1] ?? '');
    $pdo->prepare('UPDATE admin_tokens SET revoked_at = :revoked_at, updated_at = :updated_at WHERE token = :token')
        ->execute([
            ':revoked_at' => now_iso(),
            ':updated_at' => now_iso(),
            ':token' => $token,
        ]);
    audit($pdo, 'admin', 'admin_logout_success', 'admin', (int) $admin['admin_user_id'], 'admin_user', (int) $admin['admin_user_id']);
    success('ADMIN_LOGOUT_SUCCESS', 'ok', []);
}

if ($path === '/api/admin/auth/me' && $method === 'GET') {
    $admin = require_admin($pdo);
    success('ADMIN_ME_SUCCESS', 'ok', [
        'admin' => [
            'id' => (int) $admin['admin_user_id'],
            'account' => $admin['name'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'display_name' => $admin['display_name'] ?? null,
            'staff_invite_code' => ensure_admin_staff_invite_code($pdo, $admin),
            'role_codes' => admin_role_codes($admin),
            'permissions' => admin_permissions($admin),
            'is_root_admin' => admin_is_root_admin($admin),
            'role_template' => admin_role_template($admin, $pdo),
            'admin_group_code' => admin_group_code($admin) !== '' ? admin_group_code($admin) : null,
            'admin_group_name' => admin_group_name($admin) !== '' ? admin_group_name($admin) : null,
            'can_view_group_global_data' => admin_can_view_group_global_data($admin),
            'password_must_change' => !empty($admin['password_must_change']),
        ],
    ]);
}

if ($path === '/api/admin/auth/password' && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $currentPassword = trim((string) ($input['current_password'] ?? ''));
    $newPassword = trim((string) ($input['new_password'] ?? ''));

    if ($currentPassword === '' || $newPassword === '') {
        failure('ADMIN_INVALID_PARAMS', 'Invalid parameters');
    }
    if (mb_strlen($newPassword) < 6) {
        failure('ADMIN_PASSWORD_TOO_SHORT', 'Password must be at least 6 characters');
    }

    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $admin['admin_user_id']]);
    $adminUser = $stmt->fetch();
    if (!$adminUser) {
        failure('ADMIN_ACCOUNT_INCORRECT', 'Admin account is incorrect');
    }
    if (!password_verify($currentPassword, (string) $adminUser['password_hash'])) {
        failure('ADMIN_PASSWORD_INCORRECT', 'Current password is incorrect');
    }
    if (password_verify($newPassword, (string) $adminUser['password_hash'])) {
        failure('ADMIN_PASSWORD_UNCHANGED', 'New password must be different');
    }

    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE admin_users SET password_hash = :password_hash, password_must_change = 0, updated_at = :updated_at WHERE id = :id')
        ->execute([
            ':password_hash' => $passwordHash,
            ':updated_at' => now_iso(),
            ':id' => (int) $admin['admin_user_id'],
        ]);

    audit(
        $pdo,
        'admin',
        'admin_password_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'admin_user',
        (int) $admin['admin_user_id'],
        null,
        null,
        ['password_changed' => true]
    );
    success('ADMIN_PASSWORD_UPDATED', 'ok', []);
}

if ($path === '/api/admin/admin-users' && $method === 'GET') {
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $where = [];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(a.name LIKE :keyword OR IFNULL(a.display_name, "") LIKE :keyword OR IFNULL(a.staff_invite_code, "") LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    if ($status !== '') {
        $where[] = 'a.status = :status';
        $params[':status'] = $status;
    }
    if (!admin_is_root_admin($admin)) {
        $visibleAdminIds = admin_visible_admin_ids($pdo, $admin);
        if ($visibleAdminIds === []) {
            $where[] = '1 = 0';
        } else {
            $scopeParts = [];
            foreach ($visibleAdminIds as $idx => $adminId) {
                $ph = ':visible_admin_' . $idx;
                $scopeParts[] = $ph;
                $params[$ph] = $adminId;
            }
            $where[] = 'a.id IN (' . implode(', ', $scopeParts) . ')';
        }
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users a {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $pageSize)) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $pageSize;
    $items = [];
    $stmt = $pdo->prepare("SELECT " . admin_select_columns('a') . ",
            cb.name AS created_by_admin_account,
            cb.display_name AS created_by_admin_display_name,
            pa.name AS parent_admin_account,
            pa.display_name AS parent_admin_display_name
        FROM admin_users a
        LEFT JOIN admin_users cb ON cb.id = a.created_by_admin_id
        LEFT JOIN admin_users pa ON pa.id = a.parent_admin_id
        {$whereSql}
        ORDER BY a.id ASC LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    while ($item = $stmt->fetch()) {
        $items[] = serialize_admin_user($item, $pdo);
    }
    success('ADMIN_USERS_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
        'catalog' => admin_module_catalog(),
        'role_templates' => admin_role_template_all_for_admin($pdo, $admin),
        'custom_role_template_keys' => array_keys(admin_custom_role_templates_rows($pdo, $admin)),
        'admin_groups' => array_map('serialize_admin_group', admin_group_rows_for_admin($pdo, $admin)),
    ]);
}

if ($path === '/api/admin/admin-groups' && $method === 'GET') {
    $admin = require_admin($pdo);
    success('ADMIN_GROUPS_SUCCESS', 'ok', [
        'items' => array_map('serialize_admin_group', admin_group_rows_for_admin($pdo, $admin)),
    ]);
}

if ($path === '/api/admin/admin-groups' && $method === 'POST') {
    $admin = require_admin($pdo);
    $groupName = normalize_admin_group_name($input['group_name'] ?? '');
    $groupCode = normalize_admin_group_code($input['group_code'] ?? $groupName);
    if ($groupName === '' || $groupCode === '') {
        failure('ADMIN_GROUP_REQUIRED', 'Group name and code are required');
    }

    $existsStmt = $pdo->prepare('SELECT id FROM admin_groups WHERE group_code = :group_code LIMIT 1');
    $existsStmt->execute([':group_code' => $groupCode]);
    if ($existsStmt->fetch()) {
        failure('ADMIN_GROUP_EXISTS', 'Group code already exists');
    }

    $now = now_iso();
    $pdo->prepare('INSERT INTO admin_groups (group_name, group_code, created_by_admin_id, created_at, updated_at)
        VALUES (:group_name, :group_code, :created_by_admin_id, :created_at, :updated_at)')
        ->execute([
            ':group_name' => $groupName,
            ':group_code' => $groupCode,
            ':created_by_admin_id' => (int) ($admin['admin_user_id'] ?? $admin['id'] ?? 0),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

    success('ADMIN_GROUP_CREATED', 'ok', [
        'id' => (int) $pdo->lastInsertId(),
        'group_name' => $groupName,
        'group_code' => $groupCode,
    ]);
}

if ($path === '/api/admin/admin-users' && $method === 'POST') {
    $admin = require_admin($pdo);

    $account = normalize_admin_account((string) ($input['account'] ?? ''));
    $password = trim((string) ($input['password'] ?? ''));
    $status = trim((string) ($input['status'] ?? 'normal'));
    $isSuperAdmin = json_bool($input['is_super_admin'] ?? false);
    if ($isSuperAdmin && !admin_is_root_admin($admin)) {
        failure('ADMIN_ROOT_REQUIRED', 'Only root admin can create super admins');
    }
    $roleTemplate = trim((string) ($input['role_template'] ?? 'custom'));
    if ($roleTemplate === '' || $roleTemplate === 'super_admin') {
        $roleTemplate = 'custom';
    }
    $moduleAccess = normalize_admin_module_access($input['module_access'] ?? []);
    if ($moduleAccess === [] && array_key_exists('module_keys', $input)) {
        $moduleAccess = array_fill_keys(admin_json_array($input['module_keys']), 'write');
    }
    if (!$isSuperAdmin && $roleTemplate !== 'custom') {
        $allTemplates = admin_role_template_all_for_admin($pdo, $admin);
        if (!isset($allTemplates[$roleTemplate]['module_access'])) {
            failure('ADMIN_INVALID_PARAMS', 'Invalid role template');
        }
        $moduleAccess = $allTemplates[$roleTemplate]['module_access'];
    }

    validate_admin_account($account);
    if ($password === '' || mb_strlen($password) < 6) {
        failure('ADMIN_PASSWORD_TOO_SHORT', 'Password must be at least 6 characters');
    }
    if (!in_array($status, ['normal', 'disabled'], true)) {
        failure('ADMIN_INVALID_STATUS', 'Invalid admin status');
    }
    if (!$isSuperAdmin && $moduleAccess === []) {
        failure('ADMIN_PERMISSIONS_REQUIRED', 'Please select at least one module permission');
    }

    $catalog = admin_module_catalog();
    foreach ($moduleAccess as $moduleKey => $accessLevel) {
        if (!isset($catalog[$moduleKey])) {
            failure('ADMIN_PERMISSION_INVALID', 'Invalid module permission');
        }
        if (!in_array($accessLevel, ['read', 'write'], true)) {
            failure('ADMIN_PERMISSION_INVALID', 'Invalid module permission');
        }
    }
    if (!$isSuperAdmin) {
        require_admin_module_access_within_grant($admin, $moduleAccess);
    }

    $existsStmt = $pdo->prepare('SELECT id FROM admin_users WHERE name = :name LIMIT 1');
    $existsStmt->execute([':name' => $account]);
    if ($existsStmt->fetch()) {
        failure('ADMIN_ACCOUNT_EXISTS', 'Admin account already exists');
    }

    $displayName = normalize_admin_display_name($input['display_name'] ?? '');
    $staffInviteCode = normalize_admin_staff_invite_code($input['staff_invite_code'] ?? null);
    if ($staffInviteCode === null) {
        $staffInviteCode = generate_admin_staff_invite_code($pdo);
    }
    if (admin_staff_invite_code_taken($pdo, $staffInviteCode, null)) {
        failure('ADMIN_STAFF_INVITE_CODE_EXISTS', 'This invite code is already in use');
    }

    $adminGroupCode = admin_group_code($admin);
    $adminGroupName = admin_group_name($admin);
    $adminGroupNameInput = normalize_admin_group_name($input['admin_group_name'] ?? '');
    $adminGroupCodeRaw = trim((string) ($input['admin_group_code'] ?? ''));
    $adminGroupCodeInput = normalize_admin_group_code($adminGroupCodeRaw !== '' ? $adminGroupCodeRaw : $adminGroupNameInput);
    if ($adminGroupCodeInput !== '') {
        $matchedGroup = null;
        foreach (admin_group_rows_for_admin($pdo, $admin) as $row) {
            if ((string) $row['group_code'] === $adminGroupCodeInput) {
                $matchedGroup = $row;
                break;
            }
        }
        if (!$matchedGroup && !admin_is_root_admin($admin)) {
            failure('ADMIN_GROUP_FORBIDDEN', 'Group is not available');
        }
        $adminGroupCode = $adminGroupCodeInput;
        $adminGroupName = $adminGroupNameInput !== '' ? $adminGroupNameInput : (string) ($matchedGroup['group_name'] ?? $adminGroupCodeInput);
    }
    if ($adminGroupCode === '') {
        failure('ADMIN_GROUP_REQUIRED', 'Admin group is required');
    }
    $canViewGroupGlobalData = array_key_exists('can_view_group_global_data', $input)
        ? json_bool($input['can_view_group_global_data'])
        : $isSuperAdmin;
    if ($canViewGroupGlobalData && !admin_is_root_admin($admin) && !admin_can_view_group_global_data($admin)) {
        failure('ADMIN_FORBIDDEN', 'Cannot grant group global data permission');
    }

    $now = now_iso();
    $roleCodes = $isSuperAdmin ? ['super_admin'] : ($roleTemplate !== 'custom' ? [$roleTemplate] : []);
    $permissions = admin_permissions_from_access($moduleAccess, $isSuperAdmin);
    $stmt = $pdo->prepare('INSERT INTO admin_users (
        name, email, password_hash, status, role_codes, permissions, display_name, staff_invite_code, admin_group_code, admin_group_name, can_view_group_global_data, created_by_admin_id, parent_admin_id, password_must_change, created_at, updated_at
    ) VALUES (
        :name, :email, :password_hash, :status, :role_codes, :permissions, :display_name, :staff_invite_code, :admin_group_code, :admin_group_name, :can_view_group_global_data, :created_by_admin_id, :parent_admin_id, 1, :created_at, :updated_at
    )');
    $stmt->execute([
        ':name' => $account,
        ':email' => admin_account_email($account),
        ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ':status' => $status,
        ':role_codes' => json_encode($roleCodes, JSON_UNESCAPED_UNICODE),
        ':permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
        ':display_name' => $displayName !== '' ? $displayName : null,
        ':staff_invite_code' => $staffInviteCode,
        ':admin_group_code' => $adminGroupCode,
        ':admin_group_name' => $adminGroupName,
        ':can_view_group_global_data' => $canViewGroupGlobalData ? 1 : 0,
        ':created_by_admin_id' => (int) $admin['admin_user_id'],
        ':parent_admin_id' => (int) $admin['admin_user_id'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $adminUserId = (int) $pdo->lastInsertId();

    audit(
        $pdo,
        'admin',
        'admin_user_created',
        'admin',
        (int) $admin['admin_user_id'],
        'admin_user',
        $adminUserId,
        null,
        null,
        [
            'account' => $account,
            'display_name' => $displayName,
            'staff_invite_code' => $staffInviteCode,
            'status' => $status,
            'is_super_admin' => $isSuperAdmin,
            'role_template' => $isSuperAdmin ? 'super_admin' : $roleTemplate,
            'module_access' => admin_module_access_from_permissions($permissions, $isSuperAdmin),
            'admin_group_code' => $adminGroupCode,
            'admin_group_name' => $adminGroupName,
            'can_view_group_global_data' => $canViewGroupGlobalData,
        ]
    );
    success('ADMIN_USER_CREATED', 'ok', ['id' => $adminUserId]);
}

if (preg_match('#^/api/admin/admin-users/(\d+)$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $adminUserId = (int) $matches[1];
    if (!admin_can_access_admin($pdo, $admin, $adminUserId)) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    $stmt = $pdo->prepare('SELECT ' . admin_select_columns('a') . ',
            cb.name AS created_by_admin_account,
            cb.display_name AS created_by_admin_display_name,
            pa.name AS parent_admin_account,
            pa.display_name AS parent_admin_display_name
        FROM admin_users a
        LEFT JOIN admin_users cb ON cb.id = a.created_by_admin_id
        LEFT JOIN admin_users pa ON pa.id = a.parent_admin_id
        WHERE a.id = :id LIMIT 1');
    $stmt->execute([':id' => $adminUserId]);
    $item = $stmt->fetch();
    if (!$item) {
        failure('ADMIN_USER_NOT_FOUND', 'Admin user not found');
    }
    success('ADMIN_USER_DETAIL_SUCCESS', 'ok', [
        'admin_user' => serialize_admin_user($item, $pdo),
    ]);
}

if (preg_match('#^/api/admin/admin-users/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);

    $adminUserId = (int) $matches[1];
    if (!admin_can_access_admin($pdo, $admin, $adminUserId)) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    $stmt = $pdo->prepare('SELECT ' . admin_select_columns() . ' FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $adminUserId]);
    $target = $stmt->fetch();
    if (!$target) {
        failure('ADMIN_USER_NOT_FOUND', 'Admin user not found');
    }

    $account = normalize_admin_account((string) ($input['account'] ?? $target['name']));
    $status = trim((string) ($input['status'] ?? $target['status']));
    $isSuperAdmin = json_bool($input['is_super_admin'] ?? in_array('super_admin', admin_role_codes($target), true));
    if ($isSuperAdmin && !admin_is_root_admin($admin)) {
        failure('ADMIN_ROOT_REQUIRED', 'Only root admin can grant super admin');
    }
    if (in_array('super_admin', admin_role_codes($target), true) && !$isSuperAdmin && !admin_is_root_admin($admin)) {
        failure('ADMIN_ROOT_REQUIRED', 'Only root admin can revoke super admin');
    }
    $roleTemplate = trim((string) ($input['role_template'] ?? admin_role_template($target, $pdo)));
    if ($roleTemplate === '' || $roleTemplate === 'super_admin') {
        $roleTemplate = 'custom';
    }
    $existingModuleAccess = admin_module_access_from_permissions(admin_permissions($target), in_array('super_admin', admin_role_codes($target), true));
    $moduleAccess = normalize_admin_module_access($input['module_access'] ?? $existingModuleAccess);
    if ($moduleAccess === [] && array_key_exists('module_keys', $input)) {
        $moduleAccess = array_fill_keys(admin_json_array($input['module_keys']), 'write');
    }
    if (!$isSuperAdmin && $roleTemplate !== 'custom') {
        $allTemplates = admin_role_template_all_for_admin($pdo, $admin);
        if (!isset($allTemplates[$roleTemplate]['module_access'])) {
            failure('ADMIN_INVALID_PARAMS', 'Invalid role template');
        }
        $moduleAccess = $allTemplates[$roleTemplate]['module_access'];
    }

    validate_admin_account($account);
    if (!in_array($status, ['normal', 'disabled'], true)) {
        failure('ADMIN_INVALID_STATUS', 'Invalid admin status');
    }
    if (!$isSuperAdmin && $moduleAccess === []) {
        failure('ADMIN_PERMISSIONS_REQUIRED', 'Please select at least one module permission');
    }

    $displayName = array_key_exists('display_name', $input)
        ? normalize_admin_display_name($input['display_name'])
        : normalize_admin_display_name($target['display_name'] ?? '');
    $targetStaffRaw = trim((string) ($target['staff_invite_code'] ?? ''));
    if (array_key_exists('staff_invite_code', $input)) {
        $staffInviteCode = normalize_admin_staff_invite_code($input['staff_invite_code']);
    } else {
        $staffInviteCode = preg_match('/^\d{6}$/', $targetStaffRaw) ? $targetStaffRaw : null;
    }
    if ($staffInviteCode === null) {
        $staffInviteCode = generate_admin_staff_invite_code($pdo, $adminUserId);
    }
    if (admin_staff_invite_code_taken($pdo, $staffInviteCode, $adminUserId)) {
        failure('ADMIN_STAFF_INVITE_CODE_EXISTS', 'This invite code is already in use');
    }

    $catalog = admin_module_catalog();
    foreach ($moduleAccess as $moduleKey => $accessLevel) {
        if (!isset($catalog[$moduleKey])) {
            failure('ADMIN_PERMISSION_INVALID', 'Invalid module permission');
        }
        if (!in_array($accessLevel, ['read', 'write'], true)) {
            failure('ADMIN_PERMISSION_INVALID', 'Invalid module permission');
        }
    }
    if (!$isSuperAdmin) {
        require_admin_module_access_within_grant($admin, $moduleAccess);
    }

    if ((int) $admin['admin_user_id'] === $adminUserId) {
        if ($status !== 'normal') {
            failure('ADMIN_SELF_LOCK_FORBIDDEN', 'Cannot disable current admin');
        }
        if (admin_is_super_admin($admin) && !$isSuperAdmin) {
            failure('ADMIN_SELF_ROLE_FORBIDDEN', 'Current admin must keep super admin permission');
        }
    }

    $existsStmt = $pdo->prepare('SELECT id FROM admin_users WHERE name = :name AND id != :id LIMIT 1');
    $existsStmt->execute([
        ':name' => $account,
        ':id' => $adminUserId,
    ]);
    if ($existsStmt->fetch()) {
        failure('ADMIN_ACCOUNT_EXISTS', 'Admin account already exists');
    }

    $targetIsRootAdmin = admin_is_root_admin($target);
    $adminGroupCode = admin_group_code($target);
    $adminGroupName = admin_group_name($target);
    if ($targetIsRootAdmin) {
        $displayName = '大老板';
        $adminGroupCode = 'boss';
        $adminGroupName = '大老板';
        $isSuperAdmin = true;
    } elseif (admin_is_root_admin($admin)) {
        $groupNameInput = normalize_admin_group_name($input['admin_group_name'] ?? '');
        $groupCodeInput = normalize_admin_group_code($input['admin_group_code'] ?? $groupNameInput);
        if ($groupCodeInput !== '') {
            $adminGroupCode = $groupCodeInput;
            $adminGroupName = $groupNameInput !== '' ? $groupNameInput : $groupCodeInput;
        }
    }
    if ($isSuperAdmin && ($adminGroupCode === '' || $adminGroupName === '')) {
        failure('ADMIN_GROUP_REQUIRED', 'Group is required for group super admin');
    }
    $canViewGroupGlobalData = array_key_exists('can_view_group_global_data', $input)
        ? json_bool($input['can_view_group_global_data'])
        : admin_can_view_group_global_data($target);
    if ($targetIsRootAdmin) {
        $canViewGroupGlobalData = true;
    }
    if ($canViewGroupGlobalData && !admin_is_root_admin($admin) && !admin_can_view_group_global_data($admin)) {
        failure('ADMIN_FORBIDDEN', 'Cannot grant group global data permission');
    }

    $roleCodes = $isSuperAdmin ? ['super_admin'] : ($roleTemplate !== 'custom' ? [$roleTemplate] : []);
    $permissions = admin_permissions_from_access($moduleAccess, $isSuperAdmin);
    $before = [
        'account' => $target['name'],
        'display_name' => trim((string) ($target['display_name'] ?? '')),
        'staff_invite_code' => $targetStaffRaw !== '' ? strtoupper($targetStaffRaw) : null,
        'status' => $target['status'],
        'display_status' => admin_effective_status($target),
        'lock_state' => admin_lock_state($target),
        'temporary_locked_until' => admin_temp_locked_until($target),
        'permanent_locked_at' => $target['login_permanent_locked_at'] ?? null,
        'role_codes' => admin_role_codes($target),
        'role_template' => admin_role_template($target, $pdo),
        'module_access' => admin_module_access_from_permissions(admin_permissions($target), in_array('super_admin', admin_role_codes($target), true)),
        'admin_group_code' => admin_group_code($target),
        'admin_group_name' => admin_group_name($target),
        'can_view_group_global_data' => admin_can_view_group_global_data($target),
    ];
    $after = [
        'account' => $account,
        'display_name' => $displayName,
        'staff_invite_code' => $staffInviteCode,
        'status' => $status,
        'display_status' => $status,
        'role_codes' => $roleCodes,
        'role_template' => $isSuperAdmin ? 'super_admin' : $roleTemplate,
        'module_access' => admin_module_access_from_permissions($permissions, $isSuperAdmin),
        'admin_group_code' => $adminGroupCode,
        'admin_group_name' => $adminGroupName,
        'can_view_group_global_data' => $canViewGroupGlobalData,
    ];

    $resetLockLevel = false;
    if ($status === 'normal' && admin_is_permanently_locked($target)) {
        $resetLockLevel = true;
        $after['lock_state'] = 'none';
        $after['temporary_locked_until'] = null;
        $after['permanent_locked_at'] = null;
    }

    $pdo->prepare('UPDATE admin_users SET
        name = :name,
        email = :email,
        display_name = :display_name,
        staff_invite_code = :staff_invite_code,
        status = :status,
        role_codes = :role_codes,
        permissions = :permissions,
        admin_group_code = :admin_group_code,
        admin_group_name = :admin_group_name,
        can_view_group_global_data = :can_view_group_global_data,
        login_failure_count = CASE WHEN :reset_lock_level = 1 THEN 0 ELSE login_failure_count END,
        login_first_failure_at = CASE WHEN :reset_lock_level = 1 THEN null ELSE login_first_failure_at END,
        login_last_failure_at = CASE WHEN :reset_lock_level = 1 THEN null ELSE login_last_failure_at END,
        login_locked_until = CASE WHEN :reset_lock_level = 1 THEN null ELSE login_locked_until END,
        login_lock_level = CASE WHEN :reset_lock_level = 1 THEN 0 ELSE login_lock_level END,
        login_permanent_locked_at = CASE WHEN :reset_lock_level = 1 THEN null ELSE login_permanent_locked_at END,
        updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':name' => $account,
            ':email' => admin_account_email($account),
            ':display_name' => $displayName !== '' ? $displayName : null,
            ':staff_invite_code' => $staffInviteCode,
            ':status' => $status,
            ':role_codes' => json_encode($roleCodes, JSON_UNESCAPED_UNICODE),
            ':permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
            ':admin_group_code' => $adminGroupCode,
            ':admin_group_name' => $adminGroupName,
            ':can_view_group_global_data' => $canViewGroupGlobalData ? 1 : 0,
            ':reset_lock_level' => $resetLockLevel ? 1 : 0,
            ':updated_at' => now_iso(),
            ':id' => $adminUserId,
        ]);

    audit(
        $pdo,
        'admin',
        'admin_user_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'admin_user',
        $adminUserId,
        null,
        $before,
        $after
    );
    success('ADMIN_USER_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/admin-users/(\d+)/password$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);

    $adminUserId = (int) $matches[1];
    if (!admin_can_access_admin($pdo, $admin, $adminUserId)) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    $newPassword = trim((string) ($input['password'] ?? ''));
    if ($newPassword === '' || mb_strlen($newPassword) < 6) {
        failure('ADMIN_PASSWORD_TOO_SHORT', 'Password must be at least 6 characters');
    }

    $stmt = $pdo->prepare('SELECT id, name FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $adminUserId]);
    $target = $stmt->fetch();
    if (!$target) {
        failure('ADMIN_USER_NOT_FOUND', 'Admin user not found');
    }

    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE admin_users SET password_hash = :password_hash, password_must_change = 1, updated_at = :updated_at WHERE id = :id')
        ->execute([
            ':password_hash' => $passwordHash,
            ':updated_at' => now_iso(),
            ':id' => $adminUserId,
        ]);

    audit(
        $pdo,
        'admin',
        'admin_user_password_reset',
        'admin',
        (int) $admin['admin_user_id'],
        'admin_user',
        $adminUserId,
        null,
        null,
        ['account' => $target['name'], 'password_changed' => true]
    );
    success('ADMIN_USER_PASSWORD_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/admin-users/(\d+)/unlock$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);

    $adminUserId = (int) $matches[1];
    if (!admin_can_access_admin($pdo, $admin, $adminUserId)) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    if ((int) $admin['admin_user_id'] === $adminUserId) {
        failure('ADMIN_SELF_UNLOCK_FORBIDDEN', 'Current admin does not need manual unlock');
    }

    $stmt = $pdo->prepare('SELECT id, name, status, login_failure_count, login_first_failure_at, login_last_failure_at, login_locked_until, login_lock_level, login_permanent_locked_at, role_codes, permissions FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $adminUserId]);
    $target = $stmt->fetch();
    if (!$target) {
        failure('ADMIN_USER_NOT_FOUND', 'Admin user not found');
    }

    $lockState = admin_lock_state($target);
    if ($lockState === 'none') {
        failure('ADMIN_USER_NOT_LOCKED', 'Admin user is not in a locked state');
    }

    $before = [
        'account' => $target['name'],
        'status' => $target['status'],
        'display_status' => admin_effective_status($target),
        'lock_state' => $lockState,
        'lock_level' => (int) ($target['login_lock_level'] ?? 0),
        'temporary_locked_until' => admin_temp_locked_until($target),
        'permanent_locked_at' => $target['login_permanent_locked_at'] ?? null,
    ];

    $pdo->prepare('UPDATE admin_users SET
        status = "normal",
        login_failure_count = 0,
        login_first_failure_at = null,
        login_last_failure_at = null,
        login_locked_until = null,
        login_lock_level = 0,
        login_permanent_locked_at = null,
        updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':updated_at' => now_iso(),
            ':id' => $adminUserId,
        ]);

    $after = [
        'account' => $target['name'],
        'status' => 'normal',
        'display_status' => 'normal',
        'lock_state' => 'none',
        'lock_level' => 0,
        'temporary_locked_until' => null,
        'permanent_locked_at' => null,
    ];

    audit(
        $pdo,
        'admin',
        'admin_user_unlocked',
        'admin',
        (int) $admin['admin_user_id'],
        'admin_user',
        $adminUserId,
        null,
        $before,
        $after,
        null,
        [
            'account' => $target['name'],
            'previous_lock_state' => $lockState,
        ]
    );
    success('ADMIN_USER_UNLOCKED', 'ok', []);
}

if (preg_match('#^/api/admin/admin-users/(\d+)/audit-logs$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $adminUserId = (int) $matches[1];
    if (!admin_can_access_admin($pdo, $admin, $adminUserId)) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    $stmt = $pdo->prepare('SELECT id, category, action, operator_type, operator_id, target_type, target_id, reason, before_json, after_json, payload_json, error_code, msg, created_at
        FROM audit_logs
        WHERE target_type = "admin_user" AND target_id = :target_id
        ORDER BY id DESC LIMIT 50');
    $stmt->execute([':target_id' => $adminUserId]);
    $items = $stmt->fetchAll();
    success('ADMIN_ADMIN_USER_AUDIT_LIST_SUCCESS', 'ok', [
        'items' => array_map(static function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['operator_id'] = $item['operator_id'] !== null ? (int) $item['operator_id'] : null;
            $item['target_id'] = $item['target_id'] !== null ? (int) $item['target_id'] : null;
            $item['before'] = $item['before_json'] ? json_decode((string) $item['before_json'], true) : null;
            $item['after'] = $item['after_json'] ? json_decode((string) $item['after_json'], true) : null;
            $item['payload'] = $item['payload_json'] ? json_decode((string) $item['payload_json'], true) : null;
            unset($item['before_json'], $item['after_json'], $item['payload_json']);
            return $item;
        }, $items),
    ]);
}

if (preg_match('#^/api/admin/admin-users/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $admin = require_admin($pdo);
    $adminUserId = (int) $matches[1];
    if (!admin_can_access_admin($pdo, $admin, $adminUserId)) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    if ((int) $admin['admin_user_id'] === $adminUserId) {
        failure('ADMIN_SELF_DELETE_FORBIDDEN', 'Cannot delete current admin');
    }

    $stmt = $pdo->prepare('SELECT id, name, status, role_codes, permissions FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $adminUserId]);
    $target = $stmt->fetch();
    if (!$target) {
        failure('ADMIN_USER_NOT_FOUND', 'Admin user not found');
    }

    if (in_array('super_admin', admin_role_codes($target), true)) {
        $count = 0;
        $countStmt = $pdo->query('SELECT role_codes FROM admin_users');
        while ($item = $countStmt->fetch()) {
            if (in_array('super_admin', admin_role_codes($item), true)) {
                $count++;
            }
        }
        if ($count <= 1) {
            failure('ADMIN_LAST_SUPER_ADMIN_FORBIDDEN', 'Cannot delete the last super admin');
        }
    }

    $before = [
        'account' => $target['name'],
        'status' => $target['status'],
        'role_codes' => admin_role_codes($target),
        'module_access' => admin_module_access_from_permissions(admin_permissions($target), in_array('super_admin', admin_role_codes($target), true)),
    ];

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE admin_tokens SET revoked_at = :revoked_at, updated_at = :updated_at WHERE admin_user_id = :admin_user_id AND revoked_at IS NULL')
            ->execute([
                ':revoked_at' => now_iso(),
                ':updated_at' => now_iso(),
                ':admin_user_id' => $adminUserId,
            ]);
        $pdo->prepare('DELETE FROM admin_users WHERE id = :id')->execute([':id' => $adminUserId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    audit(
        $pdo,
        'admin',
        'admin_user_deleted',
        'admin',
        (int) $admin['admin_user_id'],
        'admin_user',
        $adminUserId,
        null,
        $before,
        ['deleted' => true]
    );
    success('ADMIN_USER_DELETED', 'ok', []);
}

if (preg_match('#^/api/admin/role-templates/([a-z0-9_]+)$#', $path, $rtMatches)) {
    $admin = require_admin($pdo);
    $rtKey = $rtMatches[1];
    if ($method === 'GET') {
        $all = admin_role_template_all_for_admin($pdo, $admin);
        if (!isset($all[$rtKey])) {
            failure('ADMIN_ROLE_TEMPLATE_NOT_FOUND', 'Template not found');
        }
        success('ADMIN_ROLE_TEMPLATE_DETAIL_SUCCESS', 'ok', [
            'template_key' => $rtKey,
            'template' => $all[$rtKey],
            'builtin' => array_key_exists($rtKey, admin_role_template_catalog()),
        ]);
    }
    if ($method === 'PATCH') {
        if (array_key_exists($rtKey, admin_role_template_catalog())) {
            failure('ADMIN_ROLE_TEMPLATE_BUILTIN', 'Cannot modify built-in template');
        }
        $owner = admin_role_template_owner_sql($admin);
        $existsStmt = $pdo->prepare('SELECT template_key FROM admin_role_templates WHERE template_key = :k AND ' . $owner['sql'] . ' LIMIT 1');
        $existsStmt->execute(array_merge([':k' => $rtKey], $owner['params']));
        if (!$existsStmt->fetch()) {
            failure('ADMIN_ROLE_TEMPLATE_NOT_FOUND', 'Template not found');
        }
        $label = array_key_exists('label', $input) ? trim((string) $input['label']) : null;
        if ($label !== null && ($label === '' || mb_strlen($label) > 191)) {
            failure('ADMIN_INVALID_PARAMS', 'Invalid label');
        }
        $moduleAccess = null;
        if (array_key_exists('module_access', $input)) {
            $moduleAccess = normalize_admin_module_access($input['module_access']);
            if ($moduleAccess === []) {
                failure('ADMIN_PERMISSIONS_REQUIRED', 'Please select at least one module permission');
            }
            $catalog = admin_module_catalog();
            foreach ($moduleAccess as $moduleKey => $accessLevel) {
                if (!isset($catalog[$moduleKey])) {
                    failure('ADMIN_PERMISSION_INVALID', 'Invalid module permission');
                }
                if (!in_array($accessLevel, ['read', 'write'], true)) {
                    failure('ADMIN_PERMISSION_INVALID', 'Invalid module permission');
                }
            }
            require_admin_module_access_within_grant($admin, $moduleAccess);
        }
        $now = now_iso();
        if ($label !== null && $moduleAccess !== null) {
            $pdo->prepare('UPDATE admin_role_templates SET label = :label, module_access_json = :mod, updated_at = :u WHERE template_key = :k')
                ->execute([
                    ':label' => $label,
                    ':mod' => json_encode($moduleAccess, JSON_UNESCAPED_UNICODE),
                    ':u' => $now,
                    ':k' => $rtKey,
                ]);
        } elseif ($label !== null) {
            $pdo->prepare('UPDATE admin_role_templates SET label = :label, updated_at = :u WHERE template_key = :k')
                ->execute([':label' => $label, ':u' => $now, ':k' => $rtKey]);
        } elseif ($moduleAccess !== null) {
            $pdo->prepare('UPDATE admin_role_templates SET module_access_json = :mod, updated_at = :u WHERE template_key = :k')
                ->execute([
                    ':mod' => json_encode($moduleAccess, JSON_UNESCAPED_UNICODE),
                    ':u' => $now,
                    ':k' => $rtKey,
                ]);
        } else {
            failure('ADMIN_INVALID_PARAMS', 'No changes');
        }
        audit($pdo, 'admin', 'admin_role_template_updated', 'admin', (int) $admin['admin_user_id'], 'admin_role_template', null, null, ['key' => $rtKey], ['key' => $rtKey, 'label' => $label, 'module_access' => $moduleAccess], null, null);
        success('ADMIN_ROLE_TEMPLATE_UPDATED', 'ok', []);
    }
    if ($method === 'DELETE') {
        if (array_key_exists($rtKey, admin_role_template_catalog())) {
            failure('ADMIN_ROLE_TEMPLATE_BUILTIN', 'Cannot delete built-in template');
        }
        $owner = admin_role_template_owner_sql($admin);
        $existsStmt = $pdo->prepare('SELECT template_key FROM admin_role_templates WHERE template_key = :k AND ' . $owner['sql'] . ' LIMIT 1');
        $existsStmt->execute(array_merge([':k' => $rtKey], $owner['params']));
        if (!$existsStmt->fetch()) {
            failure('ADMIN_ROLE_TEMPLATE_NOT_FOUND', 'Template not found');
        }
        if (admin_role_template_key_in_use($pdo, $rtKey)) {
            failure('ADMIN_ROLE_TEMPLATE_IN_USE', 'Template is assigned to one or more admin accounts');
        }
        $pdo->prepare('DELETE FROM admin_role_templates WHERE template_key = :k')->execute([':k' => $rtKey]);
        audit($pdo, 'admin', 'admin_role_template_deleted', 'admin', (int) $admin['admin_user_id'], 'admin_role_template', null, null, ['key' => $rtKey], ['deleted' => true], null, null);
        success('ADMIN_ROLE_TEMPLATE_DELETED', 'ok', []);
    }
    failure('ADMIN_INVALID_PARAMS', 'Method not allowed');
}

if ($path === '/api/admin/role-templates' && $method === 'POST') {
    $admin = require_admin($pdo);
    $key = normalize_admin_role_template_key(trim((string) ($input['template_key'] ?? '')));
    $label = trim((string) ($input['label'] ?? ''));
    if ($label === '' || mb_strlen($label) > 191) {
        failure('ADMIN_INVALID_PARAMS', 'Invalid label');
    }
    $moduleAccess = normalize_admin_module_access($input['module_access'] ?? []);
    if ($moduleAccess === []) {
        failure('ADMIN_PERMISSIONS_REQUIRED', 'Please select at least one module permission');
    }
    $catalog = admin_module_catalog();
    foreach ($moduleAccess as $moduleKey => $accessLevel) {
        if (!isset($catalog[$moduleKey])) {
            failure('ADMIN_PERMISSION_INVALID', 'Invalid module permission');
        }
        if (!in_array($accessLevel, ['read', 'write'], true)) {
            failure('ADMIN_PERMISSION_INVALID', 'Invalid module permission');
        }
    }
    require_admin_module_access_within_grant($admin, $moduleAccess);
    $dup = $pdo->prepare('SELECT template_key FROM admin_role_templates WHERE template_key = :k LIMIT 1');
    $dup->execute([':k' => $key]);
    if ($dup->fetch()) {
        failure('ADMIN_ROLE_TEMPLATE_EXISTS', 'Template key already exists');
    }
    $now = now_iso();
    $pdo->prepare('INSERT INTO admin_role_templates (template_key, label, module_access_json, created_by_admin_id, created_at, updated_at) VALUES (:k, :l, :m, :admin_id, :c, :u)')
        ->execute([
            ':k' => $key,
            ':l' => $label,
            ':m' => json_encode($moduleAccess, JSON_UNESCAPED_UNICODE),
            ':admin_id' => (int) $admin['admin_user_id'],
            ':c' => $now,
            ':u' => $now,
        ]);
    audit($pdo, 'admin', 'admin_role_template_created', 'admin', (int) $admin['admin_user_id'], 'admin_role_template', null, null, null, ['key' => $key, 'label' => $label, 'module_access' => $moduleAccess], null, null);
    success('ADMIN_ROLE_TEMPLATE_CREATED', 'ok', ['template_key' => $key]);
}

if ($path === '/api/admin/users' && $method === 'GET') {
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $filterUserId = (int) ($_GET['user_id'] ?? 0);
    $registeredFrom = trim((string) ($_GET['registered_from'] ?? ''));

    $where = [];
    $params = [];
    if ($filterUserId > 0) {
        $where[] = 'u.id = :filter_user_id';
        $params[':filter_user_id'] = $filterUserId;
    }
    if ($keyword !== '') {
        if (preg_match('/^\d+$/', $keyword)) {
            $where[] = 'u.id = :keyword_user_id';
            $params[':keyword_user_id'] = (string) ((int) $keyword);
        } else {
            $where[] = '(u.username LIKE :keyword OR u.email LIKE :keyword OR u.mobile_e164 LIKE :keyword)';
            $params[':keyword'] = '%' . $keyword . '%';
        }
    }
    if ($status !== '') {
        $where[] = 'u.status = :status';
        $params[':status'] = $status;
    }
    if ($registeredFrom !== '') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $registeredFrom, new DateTimeZone('UTC'));
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            failure('ADMIN_INVALID_PARAMS', 'Registered from date is invalid');
        }
        $where[] = 'u.created_at >= :registered_from';
        $params[':registered_from'] = $date->format('Y-m-d\T00:00:00\Z');
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    if ($scope['sql'] !== '') {
        $where[] = $scope['sql'];
        $params = array_merge($params, $scope['params']);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT
            u.id,
            u.username,
            u.account_type,
            u.email,
            u.mobile,
            u.country_code,
            u.status,
            u.lang,
            u.invitation_code,
            COALESCE((
                SELECT i.status
                FROM invitation_codes i
                WHERE i.user_id = u.id AND i.is_primary = 1
                ORDER BY i.id DESC
                LIMIT 1
            ), 'active') AS invitation_status,
            u.invited_by_user_id,
            u.invited_by_admin_id,
            u.created_at,
            u.last_login_at,
            iu.username AS inviter_username,
            adm.display_name AS inviter_admin_display_name,
            adm.name AS inviter_admin_account,
            COALESCE((
                SELECT " . db_balance_total_expr() . "
                FROM user_wallet_balances w
                WHERE w.user_id = u.id AND w.currency_code = 'USDT'
            ), '0.00000000') AS usdt_balance,
            COALESCE((
                SELECT " . db_balance_total_expr() . "
                FROM user_wallet_balances w
                WHERE w.user_id = u.id AND w.currency_code = 'EUR'
            ), '0.00000000') AS eur_balance,
            COALESCE((
                SELECT " . db_balance_available_sum_expr() . "
                FROM user_wallet_balances w
                WHERE w.user_id = u.id AND w.currency_code = 'USDT'
            ), '0.00000000') AS usdt_available_balance,
            COALESCE((
                SELECT " . db_balance_reserved_sum_expr() . "
                FROM user_wallet_balances w
                WHERE w.user_id = u.id AND w.currency_code = 'USDT'
            ), '0.00000000') AS usdt_reserved_balance,
            COALESCE((
                SELECT " . db_balance_available_sum_expr() . "
                FROM user_wallet_balances w
                WHERE w.user_id = u.id AND w.currency_code = 'EUR'
            ), '0.00000000') AS eur_available_balance,
            COALESCE((
                SELECT " . db_balance_reserved_sum_expr() . "
                FROM user_wallet_balances w
                WHERE w.user_id = u.id AND w.currency_code = 'EUR'
            ), '0.00000000') AS eur_reserved_balance,
            (SELECT MAX(w.updated_at) FROM user_wallet_balances w WHERE w.user_id = u.id) AS wallets_max_updated_at,
            (
                SELECT COUNT(*)
                FROM users invited_users
                WHERE invited_users.invited_by_user_id = u.id
            ) AS invite_count
        FROM users u
        LEFT JOIN users iu ON u.invited_by_user_id = iu.id
        LEFT JOIN admin_users adm ON u.invited_by_admin_id = adm.id
        {$whereSql}
        ORDER BY u.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    success('ADMIN_USERS_LIST_SUCCESS', 'ok', [
        'items' => array_map(static function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['invited_by_user_id'] = $item['invited_by_user_id'] !== null ? (int) $item['invited_by_user_id'] : null;
            $item['invited_by_admin_id'] = isset($item['invited_by_admin_id']) && $item['invited_by_admin_id'] !== null && $item['invited_by_admin_id'] !== ''
                ? (int) $item['invited_by_admin_id']
                : null;
            $item['invite_count'] = (int) $item['invite_count'];
            $item['upline_label'] = format_user_upline_label(
                $item['invited_by_user_id'],
                $item['invited_by_admin_id'],
                isset($item['inviter_username']) ? (string) $item['inviter_username'] : null,
                isset($item['inviter_admin_display_name']) ? trim((string) $item['inviter_admin_display_name']) : '',
                isset($item['inviter_admin_account']) ? (string) $item['inviter_admin_account'] : null
            );
            unset($item['inviter_username'], $item['inviter_admin_display_name'], $item['inviter_admin_account']);

            return $item;
        }, $items),
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if ($path === '/api/admin/users' && $method === 'POST') {
    $admin = require_admin($pdo);
    $accountType = trim((string) ($input['account_type'] ?? 'email'));
    $password = trim((string) ($input['password'] ?? ''));
    $lang = lang_map(trim((string) ($input['lang'] ?? 'eng')));
    $status = trim((string) ($input['status'] ?? 'normal'));
    $reason = trim((string) ($input['reason'] ?? ''));

    if (!in_array($accountType, ['email', 'mobile'], true)) {
        failure('ADMIN_INVALID_ACCOUNT_TYPE', 'Invalid account type');
    }
    if ($password === '' || mb_strlen($password) < 6) {
        failure('ADMIN_PASSWORD_TOO_SHORT', 'Password must be at least 6 characters');
    }
    if (!in_array($status, ['normal', 'locked', 'disabled'], true)) {
        failure('ADMIN_INVALID_STATUS', 'Invalid user status');
    }

    $email = null;
    $mobile = null;
    $countryCode = null;
    $mobileE164 = null;

    if ($accountType === 'email') {
        $emailInput = trim((string) ($input['email'] ?? ''));
        if ($emailInput === '' || !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            failure('ADMIN_EMAIL_INVALID', 'Email is invalid');
        }
        $email = mb_strtolower($emailInput);
        $existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existsStmt->execute([':email' => $email]);
        if ($existsStmt->fetch()) {
            failure('ADMIN_EMAIL_EXISTS', 'Email already exists');
        }
    } else {
        $countryCodeInput = trim((string) ($input['country_code'] ?? ''));
        $mobileInput = trim((string) ($input['mobile'] ?? ''));
        $normalizedMobile = normalize_mobile($countryCodeInput . $mobileInput);
        if (!$normalizedMobile) {
            failure('ADMIN_MOBILE_INVALID', 'Mobile is invalid');
        }
        $countryCode = $normalizedMobile['country_code'];
        $mobile = $normalizedMobile['mobile'];
        $mobileE164 = $normalizedMobile['mobile_e164'];
        $existsStmt = $pdo->prepare('SELECT id FROM users WHERE mobile_e164 = :mobile_e164 LIMIT 1');
        $existsStmt->execute([':mobile_e164' => $mobileE164]);
        if ($existsStmt->fetch()) {
            failure('ADMIN_MOBILE_EXISTS', 'Mobile already exists');
        }
    }

    $invitedByRaw = $input['invited_by_user_id'] ?? null;
    $invitedByUserId = null;
    if ($invitedByRaw !== null && $invitedByRaw !== '') {
        $invitedByUserId = (int) $invitedByRaw;
        if ($invitedByUserId <= 0) {
            failure('ADMIN_INVITED_BY_INVALID', 'Invited by user id is invalid');
        }
        $inviterStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $inviterStmt->execute([':id' => $invitedByUserId]);
        if (!$inviterStmt->fetch()) {
            failure('ADMIN_INVITED_BY_NOT_FOUND', 'Inviter user not found');
        }
        require_admin_can_access_user($pdo, $admin, $invitedByUserId);
    }

    $now = now_iso();
    $username = generate_user_display_code($pdo);
    $invitationCode = generate_invitation_numeric_code($pdo);
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $adminGroupCodeForUser = admin_group_code($admin);

    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO users (
            account_type, username, email, mobile, country_code, mobile_e164, password_hash, status, lang, avatar_id,
            invitation_code, invited_by_user_id, invited_by_admin_id, admin_group_code, login_failure_count, created_at, updated_at
        ) VALUES (
            :account_type, :username, :email, :mobile, :country_code, :mobile_e164, :password_hash, :status, :lang, :avatar_id,
            :invitation_code, :invited_by_user_id, :invited_by_admin_id, :admin_group_code, 0, :created_at, :updated_at
        )')->execute([
            ':account_type' => $accountType,
            ':username' => $username,
            ':email' => $email,
            ':mobile' => $mobile,
            ':country_code' => $countryCode,
            ':mobile_e164' => $mobileE164,
            ':password_hash' => $passwordHash,
            ':status' => $status,
            ':lang' => $lang,
            ':avatar_id' => random_user_avatar_id(),
            ':invitation_code' => $invitationCode,
            ':invited_by_user_id' => $invitedByUserId,
            ':invited_by_admin_id' => (int) $admin['admin_user_id'],
            ':admin_group_code' => $adminGroupCodeForUser !== '' ? $adminGroupCodeForUser : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO invitation_codes (user_id, code, status, is_primary, issued_at, expires_at, created_at, updated_at)
            VALUES (:user_id, :code, "active", 1, :issued_at, NULL, :created_at, :updated_at)')
            ->execute([
                ':user_id' => $userId,
                ':code' => $invitationCode,
                ':issued_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

        initialize_user_defaults($pdo, $userId);
        $pdo->commit();

        audit(
            $pdo,
            'admin',
            'user_created',
            'admin',
            (int) $admin['admin_user_id'],
            'user',
            $userId,
            $reason,
            null,
            [
                'account_type' => $accountType,
                'username' => $username,
                'email' => $email,
                'mobile' => $mobile,
                'country_code' => $countryCode,
                'status' => $status,
                'lang' => $lang,
                'invitation_code' => $invitationCode,
                'invited_by_user_id' => $invitedByUserId,
                'invited_by_admin_id' => (int) $admin['admin_user_id'],
            ]
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    success('ADMIN_USER_CREATED', 'ok', [
        'user' => [
            'id' => $userId,
            'username' => $username,
            'invitation_code' => $invitationCode,
        ],
    ]);
}

if ($path === '/api/admin/users/batch' && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $action = trim((string) ($input['action'] ?? ''));
    $rawIds = $input['user_ids'] ?? [];
    if (!is_array($rawIds)) {
        failure('ADMIN_INVALID_PARAMS', 'User ids are required');
    }
    $userIds = [];
    foreach ($rawIds as $rawId) {
        $id = (int) $rawId;
        if ($id > 0) {
            $userIds[$id] = $id;
        }
    }
    $userIds = array_values($userIds);
    if (!$userIds) {
        failure('ADMIN_INVALID_PARAMS', 'User ids are required');
    }
    if (count($userIds) > 100) {
        failure('ADMIN_INVALID_PARAMS', 'Too many users selected');
    }
    $reason = trim((string) ($input['reason'] ?? ''));
    $updated = 0;
    $now = now_iso();

    try {
        $pdo->beginTransaction();
        foreach ($userIds as $userId) {
            require_admin_can_access_user($pdo, $admin, $userId);
            if ($action === 'status') {
                $status = trim((string) ($input['status'] ?? ''));
                if (!in_array($status, ['normal', 'locked', 'disabled'], true)) {
                    failure('ADMIN_INVALID_STATUS', 'Invalid user status');
                }
                $stmt = $pdo->prepare('SELECT id, status FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch();
                if (!$user) {
                    failure('ADMIN_USER_NOT_FOUND', 'User not found');
                }
                if ((string) $user['status'] !== $status) {
                    $pdo->prepare('UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id')
                        ->execute([
                            ':status' => $status,
                            ':updated_at' => $now,
                            ':id' => $userId,
                        ]);
                    audit($pdo, 'admin', 'user_status_batch_updated', 'admin', (int) $admin['admin_user_id'], 'user', $userId, $reason, ['status' => $user['status']], ['status' => $status]);
                    $updated++;
                }
                continue;
            }

            if ($action === 'risk') {
                $riskStatus = trim((string) ($input['risk_status'] ?? ''));
                if (!in_array($riskStatus, ['normal', 'warning', 'blocked'], true)) {
                    failure('ADMIN_RISK_STATUS_INVALID', 'Risk status is invalid');
                }
                $violationMessage = trim((string) ($input['violation_message'] ?? ''));
                $stmt = $pdo->prepare('SELECT * FROM user_tier_profiles WHERE user_id = :user_id LIMIT 1');
                $stmt->execute([':user_id' => $userId]);
                $before = $stmt->fetch();
                if (!$before) {
                    $pdo->prepare('INSERT INTO user_tier_profiles (
                        user_id, level, group_code, score, merchant_enabled, is_verified, daily_trade_limit,
                        min_sell_amount, margin_amount, margin_ratio, risk_status, violation_message, created_at, updated_at
                    ) VALUES (
                        :user_id, 1, :group_code, 30, 0, 0, 1,
                        "10.00000000", "0.00000000", "0.0000", :risk_status, :violation_message, :created_at, :updated_at
                    )')->execute([
                        ':user_id' => $userId,
                        ':group_code' => tier_group_code_for_level(1),
                        ':risk_status' => $riskStatus,
                        ':violation_message' => $violationMessage !== '' ? $violationMessage : null,
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]);
                    audit($pdo, 'admin', 'tier_profile_batch_risk_updated', 'admin', (int) $admin['admin_user_id'], 'tier_profile', $userId, $reason, null, ['risk_status' => $riskStatus, 'violation_message' => $violationMessage]);
                    $updated++;
                    continue;
                }
                if ((string) $before['risk_status'] !== $riskStatus || (string) ($before['violation_message'] ?? '') !== $violationMessage) {
                    $pdo->prepare('UPDATE user_tier_profiles
                        SET risk_status = :risk_status, violation_message = :violation_message, updated_at = :updated_at
                        WHERE user_id = :user_id')
                        ->execute([
                            ':risk_status' => $riskStatus,
                            ':violation_message' => $violationMessage !== '' ? $violationMessage : null,
                            ':updated_at' => $now,
                            ':user_id' => $userId,
                        ]);
                    audit($pdo, 'admin', 'tier_profile_batch_risk_updated', 'admin', (int) $admin['admin_user_id'], 'tier_profile', $userId, $reason, $before, ['risk_status' => $riskStatus, 'violation_message' => $violationMessage]);
                    $updated++;
                }
                continue;
            }

            failure('ADMIN_INVALID_PARAMS', 'Batch action is invalid');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    success('ADMIN_USERS_BATCH_UPDATED', 'ok', ['updated_count' => $updated]);
}

if (preg_match('#^/api/admin/users/(\d+)/network-members$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $existsStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $existsStmt->execute([':id' => $userId]);
    if (!$existsStmt->fetch()) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }
    $items = list_user_downline_members($pdo, $userId);
    success('ADMIN_USER_NETWORK_MEMBERS_SUCCESS', 'ok', [
        'items' => $items,
        'total' => count($items),
    ]);
}

if (preg_match('#^/api/admin/users/(\d+)$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $stmt = $pdo->prepare('SELECT
            u.id,
            u.username,
            u.account_type,
            u.email,
            u.mobile,
            u.country_code,
            u.status,
            u.lang,
            u.invitation_code,
            COALESCE((
                SELECT i.status
                FROM invitation_codes i
                WHERE i.user_id = u.id AND i.is_primary = 1
                ORDER BY i.id DESC
                LIMIT 1
            ), "active") AS invitation_status,
            u.invited_by_user_id,
            u.invited_by_admin_id,
            u.login_failure_count,
            u.last_login_at,
            u.last_login_ip,
            u.created_at,
            iu.username AS inviter_username,
            adm.display_name AS inviter_admin_display_name,
            adm.name AS inviter_admin_account
        FROM users u
        LEFT JOIN users iu ON u.invited_by_user_id = iu.id
        LEFT JOIN admin_users adm ON u.invited_by_admin_id = adm.id
        WHERE u.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }
    $user['id'] = (int) $user['id'];
    $usernameForDisplay = trim((string) ($user['username'] ?? ''));
    $inviteForDisplay = trim((string) ($user['invitation_code'] ?? ''));
    if (preg_match('/^EN\d{6}$/i', $usernameForDisplay)) {
        $user['display_code'] = strtoupper($usernameForDisplay);
    } elseif (preg_match('/^EU\d{6}$/i', $usernameForDisplay)) {
        $user['display_code'] = 'EN' . substr($usernameForDisplay, 2);
    } elseif (preg_match('/^EN\d{6}$/i', $inviteForDisplay)) {
        $user['display_code'] = strtoupper($inviteForDisplay);
    } elseif (preg_match('/^EU\d{6}$/i', $inviteForDisplay)) {
        $user['display_code'] = 'EN' . substr($inviteForDisplay, 2);
    } elseif (preg_match('/^\d{6}$/', $inviteForDisplay)) {
        $user['display_code'] = 'EN' . $inviteForDisplay;
    } else {
        $user['display_code'] = 'EN' . str_pad((string) $user['id'], 6, '0', STR_PAD_LEFT);
    }
    $user['invited_by_user_id'] = $user['invited_by_user_id'] !== null ? (int) $user['invited_by_user_id'] : null;
    $user['invited_by_admin_id'] = isset($user['invited_by_admin_id']) && $user['invited_by_admin_id'] !== null && $user['invited_by_admin_id'] !== ''
        ? (int) $user['invited_by_admin_id']
        : null;
    $user['login_failure_count'] = (int) $user['login_failure_count'];
    $user['upline_label'] = format_user_upline_label(
        $user['invited_by_user_id'],
        $user['invited_by_admin_id'],
        isset($user['inviter_username']) ? (string) $user['inviter_username'] : null,
        isset($user['inviter_admin_display_name']) ? trim((string) $user['inviter_admin_display_name']) : '',
        isset($user['inviter_admin_account']) ? (string) $user['inviter_admin_account'] : null
    );
    unset($user['inviter_username'], $user['inviter_admin_display_name'], $user['inviter_admin_account']);
    $user['network_member_count'] = count_user_downline_members($pdo, $userId);
    $tierScoreStmt = $pdo->prepare('SELECT score FROM user_tier_profiles WHERE user_id = :user_id LIMIT 1');
    $tierScoreStmt->execute([':user_id' => $userId]);
    $tierScoreRow = $tierScoreStmt->fetch();
    $user['credit_score'] = $tierScoreRow ? (int) $tierScoreRow['score'] : 30;
    success('ADMIN_USER_DETAIL_SUCCESS', 'ok', ['user' => $user]);
}

if (preg_match('#^/api/admin/users/(\d+)/invitation-status$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $status = trim((string) ($input['status'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));

    if (!in_array($status, ['active', 'disabled'], true)) {
        failure('ADMIN_INVITATION_STATUS_INVALID', 'Invitation status is invalid');
    }

    $userStmt = $pdo->prepare('SELECT id, invitation_code FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();
    if (!$user) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }

    $primaryStmt = $pdo->prepare('SELECT id, code, status FROM invitation_codes WHERE user_id = :user_id AND is_primary = 1 ORDER BY id DESC LIMIT 1');
    $primaryStmt->execute([':user_id' => $userId]);
    $invitation = $primaryStmt->fetch();
    if (!$invitation) {
        $now = now_iso();
        $pdo->prepare('INSERT INTO invitation_codes (user_id, code, status, is_primary, issued_at, expires_at, created_at, updated_at)
            VALUES (:user_id, :code, :status, 1, :issued_at, NULL, :created_at, :updated_at)')
            ->execute([
                ':user_id' => $userId,
                ':code' => (string) $user['invitation_code'],
                ':status' => $status,
                ':issued_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        $before = null;
        $targetInvitationId = (int) $pdo->lastInsertId();
    } else {
        $before = ['status' => $invitation['status']];
        $pdo->prepare('UPDATE invitation_codes SET status = :status, updated_at = :updated_at WHERE id = :id')
            ->execute([
                ':status' => $status,
                ':updated_at' => now_iso(),
                ':id' => (int) $invitation['id'],
            ]);
        $targetInvitationId = (int) $invitation['id'];
    }

    audit(
        $pdo,
        'admin',
        $status === 'active' ? 'invitation_enabled' : 'invitation_disabled',
        'admin',
        (int) $admin['admin_user_id'],
        'invitation',
        $targetInvitationId,
        $reason,
        $before,
        ['status' => $status, 'user_id' => $userId]
    );
    success('ADMIN_USER_INVITATION_STATUS_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/users/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $reason = trim((string) ($input['reason'] ?? ''));

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }

    $accountType = trim((string) ($input['account_type'] ?? $user['account_type']));
    if (!in_array($accountType, ['email', 'mobile'], true)) {
        failure('ADMIN_INVALID_ACCOUNT_TYPE', 'Invalid account type');
    }

    $status = trim((string) ($input['status'] ?? $user['status']));
    if (!in_array($status, ['normal', 'locked', 'disabled'], true)) {
        failure('ADMIN_INVALID_STATUS', 'Invalid user status');
    }

    $password = trim((string) ($input['password'] ?? ''));
    if ($password !== '' && mb_strlen($password) < 6) {
        failure('ADMIN_PASSWORD_TOO_SHORT', 'Password must be at least 6 characters');
    }

    $username = trim((string) ($input['username'] ?? $user['username']));
    if ($username === '') {
        failure('ADMIN_USERNAME_REQUIRED', 'Username is required');
    }

    $emailInput = trim((string) ($input['email'] ?? ($user['email'] ?? '')));
    $email = $emailInput !== '' ? mb_strtolower($emailInput) : null;
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        failure('ADMIN_EMAIL_INVALID', 'Email is invalid');
    }
    if ($accountType === 'email' && $email === null) {
        failure('ADMIN_EMAIL_REQUIRED', 'Email is required');
    }

    $countryCodeInput = trim((string) ($input['country_code'] ?? ($user['country_code'] ?? '')));
    $mobileInput = trim((string) ($input['mobile'] ?? ($user['mobile'] ?? '')));
    $countryCode = $countryCodeInput !== '' ? $countryCodeInput : null;
    $mobile = $mobileInput !== '' ? $mobileInput : null;
    $mobileE164 = null;
    if ($countryCode !== null || $mobile !== null) {
        $normalizedMobile = normalize_mobile(($countryCode ?? '') . ($mobile ?? ''));
        if (!$normalizedMobile) {
            failure('ADMIN_MOBILE_INVALID', 'Mobile is invalid');
        }
        $countryCode = $normalizedMobile['country_code'];
        $mobile = $normalizedMobile['mobile'];
        $mobileE164 = $normalizedMobile['mobile_e164'];
    }
    if ($accountType === 'mobile' && $mobileE164 === null) {
        failure('ADMIN_MOBILE_REQUIRED', 'Mobile is required');
    }

    $lang = lang_map(trim((string) ($input['lang'] ?? $user['lang'])));
    $invitationCode = strtoupper(trim((string) ($input['invitation_code'] ?? $user['invitation_code'])));
    if ($invitationCode === '') {
        failure('ADMIN_INVITATION_CODE_REQUIRED', 'Invitation code is required');
    }

    $prevInvitedByUserId = $user['invited_by_user_id'] !== null ? (int) $user['invited_by_user_id'] : null;
    $prevInvitedByAdminId = isset($user['invited_by_admin_id']) && $user['invited_by_admin_id'] !== null && $user['invited_by_admin_id'] !== ''
        ? (int) $user['invited_by_admin_id']
        : null;

    if (array_key_exists('invited_by_user_id', $input)) {
        $invitedByRaw = $input['invited_by_user_id'];
        $invitedByUserId = null;
        if ($invitedByRaw !== null && $invitedByRaw !== '') {
            $invitedByUserId = (int) $invitedByRaw;
            if ($invitedByUserId <= 0) {
                failure('ADMIN_INVITED_BY_INVALID', 'Invited by user id is invalid');
            }
            if ($invitedByUserId === $userId) {
                failure('ADMIN_INVITED_BY_INVALID', 'Invited by user id is invalid');
            }
            $inviterStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
            $inviterStmt->execute([':id' => $invitedByUserId]);
            if (!$inviterStmt->fetch()) {
                failure('ADMIN_INVITED_BY_NOT_FOUND', 'Inviter user not found');
            }
        }
        $invitedByAdminId = $invitedByUserId !== null ? null : $prevInvitedByAdminId;
    } else {
        $invitedByUserId = $prevInvitedByUserId;
        $invitedByAdminId = $prevInvitedByAdminId;
    }

    if ($username !== $user['username']) {
        $existsStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1');
        $existsStmt->execute([
            ':username' => $username,
            ':id' => $userId,
        ]);
        if ($existsStmt->fetch()) {
            failure('ADMIN_USERNAME_EXISTS', 'Username already exists');
        }
    }

    if ($email !== ($user['email'] ?? null) && $email !== null) {
        $existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
        $existsStmt->execute([
            ':email' => $email,
            ':id' => $userId,
        ]);
        if ($existsStmt->fetch()) {
            failure('ADMIN_EMAIL_EXISTS', 'Email already exists');
        }
    }

    if ($mobileE164 !== ($user['mobile_e164'] ?? null) && $mobileE164 !== null) {
        $existsStmt = $pdo->prepare('SELECT id FROM users WHERE mobile_e164 = :mobile_e164 AND id != :id LIMIT 1');
        $existsStmt->execute([
            ':mobile_e164' => $mobileE164,
            ':id' => $userId,
        ]);
        if ($existsStmt->fetch()) {
            failure('ADMIN_MOBILE_EXISTS', 'Mobile already exists');
        }
    }

    if ($invitationCode !== $user['invitation_code']) {
        $existsStmt = $pdo->prepare('SELECT id FROM invitation_codes WHERE code = :code AND user_id != :user_id LIMIT 1');
        $existsStmt->execute([
            ':code' => $invitationCode,
            ':user_id' => $userId,
        ]);
        if ($existsStmt->fetch()) {
            failure('ADMIN_INVITATION_CODE_EXISTS', 'Invitation code already exists');
        }
    }

    $patchCreditScore = array_key_exists('credit_score', $input);
    $creditScoreValue = $patchCreditScore ? (int) $input['credit_score'] : null;
    if ($patchCreditScore && ($creditScoreValue < 0 || $creditScoreValue > 9999999)) {
        failure('ADMIN_CREDIT_SCORE_INVALID', 'Credit score is invalid');
    }

    $before = [
        'account_type' => $user['account_type'],
        'username' => $user['username'],
        'email' => $user['email'],
        'mobile' => $user['mobile'],
        'country_code' => $user['country_code'],
        'status' => $user['status'],
        'lang' => $user['lang'],
        'invitation_code' => $user['invitation_code'],
        'invited_by_user_id' => $prevInvitedByUserId,
        'invited_by_admin_id' => $prevInvitedByAdminId,
    ];

    $after = [
        'account_type' => $accountType,
        'username' => $username,
        'email' => $email,
        'mobile' => $mobile,
        'country_code' => $countryCode,
        'status' => $status,
        'lang' => $lang,
        'invitation_code' => $invitationCode,
        'invited_by_user_id' => $invitedByUserId,
        'invited_by_admin_id' => $invitedByAdminId,
    ];
    if ($password !== '') {
        $after['password_changed'] = true;
    }

    $now = now_iso();
    $passwordHash = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : $user['password_hash'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET
            account_type = :account_type,
            username = :username,
            email = :email,
            mobile = :mobile,
            country_code = :country_code,
            mobile_e164 = :mobile_e164,
            password_hash = :password_hash,
            status = :status,
            lang = :lang,
            invitation_code = :invitation_code,
            invited_by_user_id = :invited_by_user_id,
            invited_by_admin_id = :invited_by_admin_id,
            updated_at = :updated_at
            WHERE id = :id')
            ->execute([
                ':account_type' => $accountType,
                ':username' => $username,
                ':email' => $email,
                ':mobile' => $mobile,
                ':country_code' => $countryCode,
                ':mobile_e164' => $mobileE164,
                ':password_hash' => $passwordHash,
                ':status' => $status,
                ':lang' => $lang,
                ':invitation_code' => $invitationCode,
                ':invited_by_user_id' => $invitedByUserId,
                ':invited_by_admin_id' => $invitedByAdminId,
                ':updated_at' => $now,
                ':id' => $userId,
            ]);

        if ($invitationCode !== $user['invitation_code']) {
            $pdo->prepare('DELETE FROM invitation_codes WHERE user_id = :user_id AND code = :code AND is_primary = 0')
                ->execute([
                    ':user_id' => $userId,
                    ':code' => $invitationCode,
                ]);
            $primaryStmt = $pdo->prepare('SELECT id FROM invitation_codes WHERE user_id = :user_id AND is_primary = 1 LIMIT 1');
            $primaryStmt->execute([':user_id' => $userId]);
            $primaryInvitation = $primaryStmt->fetch();
            if ($primaryInvitation) {
                $pdo->prepare('UPDATE invitation_codes SET code = :code, updated_at = :updated_at WHERE id = :id')
                    ->execute([
                        ':code' => $invitationCode,
                        ':updated_at' => $now,
                        ':id' => (int) $primaryInvitation['id'],
                    ]);
            } else {
                $pdo->prepare('INSERT INTO invitation_codes (user_id, code, status, is_primary, issued_at, expires_at, created_at, updated_at)
                    VALUES (:user_id, :code, "active", 1, :issued_at, NULL, :created_at, :updated_at)')
                    ->execute([
                        ':user_id' => $userId,
                        ':code' => $invitationCode,
                        ':issued_at' => $now,
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]);
            }
        }

        if ($patchCreditScore) {
            $tierStmt = $pdo->prepare('SELECT * FROM user_tier_profiles WHERE user_id = :user_id LIMIT 1');
            $tierStmt->execute([':user_id' => $userId]);
            $tierProfile = $tierStmt->fetch();
            if ($tierProfile) {
                if ((int) $tierProfile['score'] !== $creditScoreValue) {
                    $pdo->prepare('UPDATE user_tier_profiles SET score = :score, updated_at = :updated_at WHERE user_id = :user_id')
                        ->execute([
                            ':score' => $creditScoreValue,
                            ':updated_at' => $now,
                            ':user_id' => $userId,
                        ]);
                    $nextTier = [
                        'level' => normalize_tier_level($tierProfile['level'] ?? 1),
                        'group_code' => trim((string) ($tierProfile['group_code'] ?? '')) !== ''
                            ? trim((string) $tierProfile['group_code'])
                            : tier_group_code_for_level(normalize_tier_level($tierProfile['level'] ?? 1)),
                        'score' => $creditScoreValue,
                        'merchant_enabled' => (int) $tierProfile['merchant_enabled'],
                        'is_verified' => (int) $tierProfile['is_verified'],
                        'daily_trade_limit' => tier_daily_sell_limit_for_level(normalize_tier_level($tierProfile['level'] ?? 1)),
                        'min_sell_amount' => $tierProfile['min_sell_amount'],
                        'margin_amount' => $tierProfile['margin_amount'],
                        'margin_ratio' => $tierProfile['margin_ratio'],
                        'risk_status' => $tierProfile['risk_status'],
                        'violation_message' => $tierProfile['violation_message'],
                    ];
                    audit($pdo, 'admin', 'tier_profile_updated', 'admin', (int) $admin['admin_user_id'], 'tier_profile', $userId, $reason, $tierProfile, $nextTier);
                }
            } else {
                $pdo->prepare('INSERT INTO user_tier_profiles (
                    user_id, level, group_code, score, merchant_enabled, is_verified, daily_trade_limit,
                    min_sell_amount, margin_amount, margin_ratio, risk_status, violation_message, created_at, updated_at
                ) VALUES (
                    :user_id, :level, :group_code, :score, :merchant_enabled, :is_verified, :daily_trade_limit,
                    :min_sell_amount, :margin_amount, :margin_ratio, :risk_status, :violation_message, :created_at, :updated_at
                )')->execute([
                    ':user_id' => $userId,
                    ':level' => 1,
                    ':group_code' => tier_group_code_for_level(1),
                    ':score' => $creditScoreValue,
                    ':merchant_enabled' => 0,
                    ':is_verified' => 0,
                    ':daily_trade_limit' => 1,
                    ':min_sell_amount' => '10.00000000',
                    ':margin_amount' => '0.00000000',
                    ':margin_ratio' => '0.0000',
                    ':risk_status' => 'normal',
                    ':violation_message' => null,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $insertedTier = [
                    'user_id' => $userId,
                    'level' => 1,
                    'group_code' => tier_group_code_for_level(1),
                    'score' => $creditScoreValue,
                    'merchant_enabled' => 0,
                    'is_verified' => 0,
                    'daily_trade_limit' => 1,
                    'min_sell_amount' => '10.00000000',
                    'margin_amount' => '0.00000000',
                    'margin_ratio' => '0.0000',
                    'risk_status' => 'normal',
                    'violation_message' => null,
                ];
                audit($pdo, 'admin', 'tier_profile_updated', 'admin', (int) $admin['admin_user_id'], 'tier_profile', $userId, $reason, null, $insertedTier);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    audit(
        $pdo,
        'admin',
        'user_profile_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'user',
        $userId,
        $reason,
        $before,
        $after
    );
    success('ADMIN_USER_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/users/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $reason = trim((string) ($input['reason'] ?? ''));

    $stmt = $pdo->prepare('SELECT id, username, email, mobile_e164, status, invitation_code, invited_by_user_id FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }

    $localFileUrls = [];

    $depositProofStmt = $pdo->prepare('SELECT proof_url FROM deposit_requests WHERE user_id = :user_id');
    $depositProofStmt->execute([':user_id' => $userId]);
    $localFileUrls = array_merge($localFileUrls, array_column($depositProofStmt->fetchAll(), 'proof_url'));

    $kycFileStmt = $pdo->prepare('SELECT id_doc_front_url, id_doc_back_url, selfie_url FROM user_kyc_applications WHERE user_id = :user_id');
    $kycFileStmt->execute([':user_id' => $userId]);
    foreach ($kycFileStmt->fetchAll() as $item) {
        $localFileUrls[] = (string) ($item['id_doc_front_url'] ?? '');
        $localFileUrls[] = (string) ($item['id_doc_back_url'] ?? '');
        $localFileUrls[] = (string) ($item['selfie_url'] ?? '');
    }

    $depositAddressFileStmt = $pdo->prepare('SELECT qr_code_url FROM deposit_addresses WHERE user_id = :user_id');
    $depositAddressFileStmt->execute([':user_id' => $userId]);
    $localFileUrls = array_merge($localFileUrls, array_column($depositAddressFileStmt->fetchAll(), 'qr_code_url'));

    $orderEvidenceFileStmt = $pdo->prepare('SELECT e.attachment_url
        FROM order_evidences e
        WHERE e.order_id IN (
            SELECT id FROM c2c_orders WHERE buyer_user_id = :user_id OR seller_user_id = :user_id
        )');
    $orderEvidenceFileStmt->execute([':user_id' => $userId]);
    $localFileUrls = array_merge($localFileUrls, array_column($orderEvidenceFileStmt->fetchAll(), 'attachment_url'));

    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET invited_by_user_id = NULL, updated_at = :updated_at WHERE invited_by_user_id = :user_id')
            ->execute([
                ':updated_at' => now_iso(),
                ':user_id' => $userId,
            ]);
        $pdo->prepare('DELETE FROM order_evidences
            WHERE order_id IN (
                SELECT id FROM c2c_orders WHERE buyer_user_id = :user_id OR seller_user_id = :user_id
            )')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM c2c_orders WHERE buyer_user_id = :user_id OR seller_user_id = :user_id')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM c2c_listings WHERE owner_user_id = :user_id')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM deposit_requests WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM withdrawal_requests WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM user_financial_subscriptions WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM user_payout_methods WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM user_kyc_applications WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM deposit_addresses WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM user_tokens WHERE user_id = :user_id')->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM invitation_codes WHERE user_id = :user_id')->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM user_wallet_balances WHERE user_id = :user_id')->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM user_tier_profiles WHERE user_id = :user_id')->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM audit_logs
            WHERE (target_type = "user" AND target_id = :user_id)
               OR (operator_type = "user" AND operator_id = :user_id)')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    delete_local_storage_files($localFileUrls);

    audit(
        $pdo,
        'admin',
        'user_deleted',
        'admin',
        (int) $admin['admin_user_id'],
        null,
        null,
        $reason,
        [
            'username' => $user['username'],
            'email' => $user['email'],
            'mobile_e164' => $user['mobile_e164'],
            'status' => $user['status'],
            'invitation_code' => $user['invitation_code'],
            'invited_by_user_id' => $user['invited_by_user_id'] !== null ? (int) $user['invited_by_user_id'] : null,
        ],
        ['deleted' => true],
        null,
        ['deleted_user_id' => $userId]
    );
    success('ADMIN_USER_DELETED', 'ok', []);
}

if (preg_match('#^/api/admin/users/(\d+)/status$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $status = trim((string) ($input['status'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));

    if (!in_array($status, ['normal', 'locked', 'disabled'], true)) {
        failure('ADMIN_INVALID_STATUS', 'Invalid user status');
    }
    if ($reason === '' && $action !== 'approve') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }

    $before = [
        'status' => $user['status'],
    ];
    $pdo->prepare('UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id')
        ->execute([
            ':status' => $status,
            ':updated_at' => now_iso(),
            ':id' => $userId,
        ]);
    audit(
        $pdo,
        'admin',
        'user_status_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'user',
        $userId,
        $reason,
        $before,
        ['status' => $status]
    );
    success('ADMIN_USER_STATUS_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/users/(\d+)/audit-logs$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $stmt = $pdo->prepare('SELECT id, category, action, operator_type, operator_id, target_type, target_id, error_code, created_at
        FROM audit_logs
        WHERE target_type = "user" AND target_id = :target_id
        ORDER BY id DESC LIMIT 50');
    $stmt->execute([':target_id' => $userId]);
    $items = $stmt->fetchAll();
    success('ADMIN_USER_AUDIT_LIST_SUCCESS', 'ok', [
        'items' => array_map(static function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['operator_id'] = $item['operator_id'] !== null ? (int) $item['operator_id'] : null;
            $item['target_id'] = $item['target_id'] !== null ? (int) $item['target_id'] : null;
            return $item;
        }, $items),
    ]);
}

if ($path === '/api/admin/invitations' && $method === 'GET') {
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $userLookup = trim((string) ($_GET['user_id'] ?? ''));
    $isPrimary = trim((string) ($_GET['is_primary'] ?? ''));

    $where = [];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(i.code LIKE :keyword OR u.username LIKE :keyword OR u.email LIKE :keyword OR u.mobile_e164 LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    if ($status !== '') {
        $where[] = 'i.status = :status';
        $params[':status'] = $status;
    }
    if ($userLookup !== '') {
        $where[] = 'i.user_id = :user_id';
        $params[':user_id'] = (int) $userLookup;
    }
    if ($isPrimary !== '') {
        $where[] = 'i.is_primary = :is_primary';
        $params[':is_primary'] = json_bool($isPrimary) ? 1 : 0;
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    if ($scope['sql'] !== '') {
        $where[] = $scope['sql'];
        $params = array_merge($params, $scope['params']);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM invitation_codes i LEFT JOIN users u ON u.id = i.user_id {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT
            i.id,
            i.user_id,
            i.code,
            i.status,
            i.is_primary,
            i.issued_at,
            i.expires_at,
            u.username,
            u.email,
            u.mobile_e164,
            (
                SELECT COUNT(*)
                FROM users invited_users
                WHERE invited_users.invited_by_user_id = i.user_id
            ) AS register_count
        FROM invitation_codes i
        LEFT JOIN users u ON u.id = i.user_id
        {$whereSql}
        ORDER BY i.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    success('ADMIN_INVITATIONS_LIST_SUCCESS', 'ok', [
        'items' => array_map(static function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['user_id'] = (int) $item['user_id'];
            $item['is_primary'] = (bool) $item['is_primary'];
            $item['register_count'] = (int) $item['register_count'];
            return $item;
        }, $items),
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/invitations/(\d+)$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $invitationId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT
            i.id,
            i.user_id,
            i.code,
            i.status,
            i.is_primary,
            i.issued_at,
            i.expires_at,
            i.created_at,
            i.updated_at,
            u.username,
            u.email,
            u.mobile_e164,
            (
                SELECT COUNT(*)
                FROM users invited_users
                WHERE invited_users.invited_by_user_id = i.user_id
            ) AS register_count
        FROM invitation_codes i
        LEFT JOIN users u ON u.id = i.user_id
        WHERE i.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $invitationId]);
    $invitation = $stmt->fetch();
    if (!$invitation) {
        failure('ADMIN_INVITATION_NOT_FOUND', 'Invitation not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $invitation['user_id']);
    $invitation['id'] = (int) $invitation['id'];
    $invitation['user_id'] = (int) $invitation['user_id'];
    $invitation['is_primary'] = (bool) $invitation['is_primary'];
    $invitation['register_count'] = (int) $invitation['register_count'];
    success('ADMIN_INVITATION_DETAIL_SUCCESS', 'ok', ['invitation' => $invitation]);
}

if (preg_match('#^/api/admin/invitations/(\d+)/disable$#', $path, $matches) && $method === 'POST') {
    $admin = require_admin($pdo);
    $invitationId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }

    $stmt = $pdo->prepare('SELECT * FROM invitation_codes WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $invitationId]);
    $invitation = $stmt->fetch();
    if (!$invitation) {
        failure('ADMIN_INVITATION_NOT_FOUND', 'Invitation not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $invitation['user_id']);
    if (($invitation['status'] ?? '') === 'disabled') {
        failure('ADMIN_INVITATION_ALREADY_DISABLED', 'Invitation is already disabled');
    }

    $pdo->prepare('UPDATE invitation_codes SET status = "disabled", updated_at = :updated_at WHERE id = :id')
        ->execute([
            ':updated_at' => now_iso(),
            ':id' => $invitationId,
        ]);
    audit(
        $pdo,
        'admin',
        'invitation_disabled',
        'admin',
        (int) $admin['admin_user_id'],
        'invitation',
        $invitationId,
        $reason,
        ['status' => $invitation['status']],
        ['status' => 'disabled']
    );
    success('ADMIN_INVITATION_DISABLED', 'ok', []);
}

if (preg_match('#^/api/admin/users/(\d+)/invitations$#', $path, $matches) && $method === 'POST') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $expiresAt = trim((string) ($input['expires_at'] ?? ''));
    $isPrimary = array_key_exists('is_primary', $input) ? json_bool($input['is_primary']) : false;

    $userStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();
    if (!$user) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }
    if ($expiresAt !== '' && strtotime($expiresAt) === false) {
        failure('ADMIN_INVITATION_EXPIRES_AT_INVALID', 'Invitation expires_at is invalid');
    }

    if ($isPrimary) {
        $pdo->prepare('UPDATE invitation_codes SET is_primary = 0, updated_at = :updated_at WHERE user_id = :user_id')
            ->execute([
                ':updated_at' => now_iso(),
                ':user_id' => $userId,
            ]);
    }

    do {
        $code = 'EU' . strtoupper(substr(random_token(10), 0, 6));
        $existsStmt = $pdo->prepare('SELECT id FROM invitation_codes WHERE code = :code LIMIT 1');
        $existsStmt->execute([':code' => $code]);
        $exists = $existsStmt->fetch();
    } while ($exists);

    $now = now_iso();
    $pdo->prepare('INSERT INTO invitation_codes (user_id, code, status, is_primary, issued_at, expires_at, created_at, updated_at)
        VALUES (:user_id, :code, "active", :is_primary, :issued_at, :expires_at, :created_at, :updated_at)')
        ->execute([
            ':user_id' => $userId,
            ':code' => $code,
            ':is_primary' => $isPrimary ? 1 : 0,
            ':issued_at' => $now,
            ':expires_at' => $expiresAt !== '' ? gmdate('c', strtotime($expiresAt)) : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    $invitationId = (int) $pdo->lastInsertId();

    audit(
        $pdo,
        'admin',
        'invitation_created',
        'admin',
        (int) $admin['admin_user_id'],
        'invitation',
        $invitationId,
        'manual invitation creation',
        null,
        [
            'user_id' => $userId,
            'code' => $code,
            'is_primary' => $isPrimary,
        ]
    );

    success('ADMIN_INVITATION_CREATED', 'ok', [
        'invitation' => [
            'id' => $invitationId,
            'user_id' => $userId,
            'code' => $code,
            'status' => 'active',
            'is_primary' => $isPrimary,
            'expires_at' => $expiresAt !== '' ? gmdate('c', strtotime($expiresAt)) : null,
        ],
    ]);
}

if ($path === '/api/admin/verifications' && $method === 'GET') {
    require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $target = trim((string) ($_GET['target'] ?? ''));
    $channel = trim((string) ($_GET['channel'] ?? ''));
    $event = trim((string) ($_GET['event'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $sentIp = trim((string) ($_GET['sent_ip'] ?? ''));

    $where = [];
    $params = [];
    if ($target !== '') {
        $where[] = 'target LIKE :target';
        $params[':target'] = '%' . $target . '%';
    }
    if ($channel !== '') {
        $where[] = 'channel = :channel';
        $params[':channel'] = $channel;
    }
    if ($event !== '') {
        $where[] = 'event = :event';
        $params[':event'] = $event;
    }
    if ($status !== '') {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }
    if ($sentIp !== '') {
        $where[] = 'sent_ip LIKE :sent_ip';
        $params[':sent_ip'] = '%' . $sentIp . '%';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM verification_codes {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT id, channel, target, event, status, attempt_count, sent_ip, sent_at, expires_at, consumed_at
        FROM verification_codes
        {$whereSql}
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    success('ADMIN_VERIFICATIONS_LIST_SUCCESS', 'ok', [
        'items' => array_map(static function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['attempt_count'] = (int) $item['attempt_count'];
            return $item;
        }, $items),
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/verifications/(\d+)$#', $path, $matches) && $method === 'GET') {
    require_admin($pdo);
    $verificationId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT id, channel, target, event, code, status, attempt_count, sent_ip, sent_at, expires_at, consumed_at, created_at, updated_at
        FROM verification_codes
        WHERE id = :id
        LIMIT 1');
    $stmt->execute([':id' => $verificationId]);
    $verification = $stmt->fetch();
    if (!$verification) {
        failure('ADMIN_VERIFICATION_NOT_FOUND', 'Verification not found');
    }
    $verification['id'] = (int) $verification['id'];
    $verification['attempt_count'] = (int) $verification['attempt_count'];
    success('ADMIN_VERIFICATION_DETAIL_SUCCESS', 'ok', ['verification' => $verification]);
}

if (preg_match('#^/api/admin/users/(\d+)/wallets$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $userStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute([':id' => $userId]);
    if (!$userStmt->fetch()) {
        failure('ADMIN_USER_NOT_FOUND', 'User not found');
    }
    $stmt = $pdo->prepare('SELECT wallet_code, currency_code, available_balance, reserved_balance, updated_at
        FROM user_wallet_balances
        WHERE user_id = :user_id
        ORDER BY wallet_code ASC');
    $stmt->execute([':user_id' => $userId]);
    success('ADMIN_USER_WALLETS_SUCCESS', 'ok', [
        'items' => $stmt->fetchAll(),
    ]);
}

if (preg_match('#^/api/admin/users/(\d+)/wallets/ledger$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    require_user_exists($pdo, $userId);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $walletCode = trim((string) ($_GET['wallet_code'] ?? ''));
    $where = ['user_id = :user_id'];
    $params = [':user_id' => $userId];
    if ($walletCode !== '') {
        $where[] = 'wallet_code = :wallet_code';
        $params[':wallet_code'] = $walletCode;
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_ledger {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT id, user_id, wallet_code, currency_code, change_type, source_type, source_id,
            available_delta, reserved_delta, available_before, reserved_before, available_after, reserved_after,
            operator_type, operator_id, reason, metadata_json, created_at
        FROM wallet_ledger
        {$whereSql}
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['user_id'] = (int) $item['user_id'];
        $item['source_id'] = $item['source_id'] !== null ? (int) $item['source_id'] : null;
        $item['operator_id'] = $item['operator_id'] !== null ? (int) $item['operator_id'] : null;
        $decoded = json_decode((string) ($item['metadata_json'] ?? ''), true);
        $item['metadata'] = is_array($decoded) ? $decoded : null;
        unset($item['metadata_json']);
        return $item;
    }, $stmt->fetchAll());
    success('ADMIN_WALLET_LEDGER_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => ['page' => $page, 'page_size' => $pageSize, 'total' => $total],
    ]);
}

if (preg_match('#^/api/admin/users/(\d+)/wallets/([A-Za-z0-9_\-]+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $walletCode = $matches[2];
    $operation = trim((string) ($input['operation'] ?? ''));
    $amount = trim((string) ($input['amount'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    $allowedOperations = ['increase_available', 'decrease_available', 'increase_reserved', 'decrease_reserved'];

    if (!in_array($operation, $allowedOperations, true)) {
        failure('ADMIN_WALLET_OPERATION_INVALID', 'Wallet operation is invalid');
    }
    if (!is_numeric($amount) || (float) $amount <= 0) {
        failure('ADMIN_WALLET_AMOUNT_INVALID', 'Wallet amount is invalid');
    }

    $stmt = $pdo->prepare('SELECT * FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
    $stmt->execute([
        ':user_id' => $userId,
        ':wallet_code' => $walletCode,
    ]);
    $wallet = $stmt->fetch();
    if (!$wallet) {
        failure('ADMIN_WALLET_NOT_FOUND', 'Wallet not found');
    }

    $delta = (float) $amount;
    $availableDelta = 0.0;
    $reservedDelta = 0.0;
    if ($operation === 'increase_available') {
        $availableDelta = $delta;
    } elseif ($operation === 'decrease_available') {
        $availableDelta = -$delta;
    } elseif ($operation === 'increase_reserved') {
        $reservedDelta = $delta;
    } else {
        $reservedDelta = -$delta;
    }

    $result = apply_wallet_delta($pdo, $userId, $walletCode, $availableDelta, $reservedDelta, [
        'change_type' => 'admin_adjustment',
        'source_type' => 'admin_wallet_adjustment',
        'operator_type' => 'admin',
        'operator_id' => (int) $admin['admin_user_id'],
        'reason' => $reason !== '' ? $reason : null,
        'metadata' => ['operation' => $operation],
    ]);
    audit(
        $pdo,
        'admin',
        'wallet_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'wallet',
        (int) $wallet['id'],
        $reason !== '' ? $reason : null,
        $result['before'],
        array_merge($result['after'], [
            'operation' => $operation,
            'amount' => number_format($delta, 8, '.', ''),
        ])
    );
    success('ADMIN_USER_WALLET_UPDATED', 'ok', []);
}

if ($path === '/api/admin/tier-templates' && $method === 'GET') {
    require_admin($pdo);
    $status = trim((string) ($_GET['status'] ?? ''));
    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare("SELECT * FROM tier_templates {$whereSql} ORDER BY sort_order ASC, level ASC, id ASC");
    $stmt->execute($params);
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['level'] = (int) $item['level'];
        $item['score'] = (int) $item['score'];
        $item['merchant_enabled'] = (bool) $item['merchant_enabled'];
        $item['is_verified'] = (bool) $item['is_verified'];
        $item['daily_trade_limit'] = $item['daily_trade_limit'] !== null ? (int) $item['daily_trade_limit'] : null;
        $item['sort_order'] = (int) $item['sort_order'];
        return $item;
    }, $stmt->fetchAll());
    success('ADMIN_TIER_TEMPLATES_SUCCESS', 'ok', ['items' => $items]);
}

if ($path === '/api/admin/tier-templates' && $method === 'POST') {
    $admin = require_admin($pdo);
    $code = strtolower(trim((string) ($input['template_code'] ?? '')));
    $name = trim((string) ($input['display_name'] ?? ''));
    $level = normalize_tier_level($input['level'] ?? 1);
    if ($code === '' || !preg_match('/^[a-z0-9_\-]+$/', $code)) {
        failure('ADMIN_TIER_TEMPLATE_CODE_INVALID', 'Template code is invalid');
    }
    if ($name === '') {
        failure('ADMIN_TIER_TEMPLATE_NAME_REQUIRED', 'Display name is required');
    }
    $now = now_iso();
    $item = [
        'template_code' => $code,
        'display_name' => $name,
        'level' => $level,
        'group_code' => trim((string) ($input['group_code'] ?? '')) ?: tier_group_code_for_level($level),
        'score' => isset($input['score']) ? (int) $input['score'] : 0,
        'merchant_enabled' => json_bool($input['merchant_enabled'] ?? false) ? 1 : 0,
        'is_verified' => json_bool($input['is_verified'] ?? false) ? 1 : 0,
        'daily_trade_limit' => array_key_exists('daily_trade_limit', $input) && $input['daily_trade_limit'] !== '' ? (int) $input['daily_trade_limit'] : tier_daily_sell_limit_for_level($level),
        'min_sell_amount' => number_format((float) ($input['min_sell_amount'] ?? 0), 8, '.', ''),
        'margin_amount' => number_format((float) ($input['margin_amount'] ?? 0), 8, '.', ''),
        'margin_ratio' => number_format((float) ($input['margin_ratio'] ?? 0), 4, '.', ''),
        'risk_status' => in_array(($input['risk_status'] ?? 'normal'), ['normal', 'warning', 'blocked'], true) ? (string) ($input['risk_status'] ?? 'normal') : 'normal',
        'description' => trim((string) ($input['description'] ?? '')),
        'status' => in_array(($input['status'] ?? 'active'), ['active', 'disabled'], true) ? (string) ($input['status'] ?? 'active') : 'active',
        'sort_order' => isset($input['sort_order']) ? (int) $input['sort_order'] : 0,
    ];
    $pdo->prepare('INSERT INTO tier_templates (
        template_code, display_name, level, group_code, score, merchant_enabled, is_verified, daily_trade_limit,
        min_sell_amount, margin_amount, margin_ratio, risk_status, description, status, sort_order, created_at, updated_at
    ) VALUES (
        :template_code, :display_name, :level, :group_code, :score, :merchant_enabled, :is_verified, :daily_trade_limit,
        :min_sell_amount, :margin_amount, :margin_ratio, :risk_status, :description, :status, :sort_order, :created_at, :updated_at
    )')->execute(array_merge($item, [':created_at' => $now, ':updated_at' => $now]));
    $id = (int) $pdo->lastInsertId();
    audit($pdo, 'admin', 'tier_template_created', 'admin', (int) $admin['admin_user_id'], 'tier_template', $id, trim((string) ($input['reason'] ?? '')) ?: null, null, $item);
    success('ADMIN_TIER_TEMPLATE_CREATED', 'ok', ['id' => $id]);
}

if (preg_match('#^/api/admin/tier-templates/(\d+)$#', $path, $matches) && $method === 'GET') {
    require_admin($pdo);
    $stmt = $pdo->prepare('SELECT * FROM tier_templates WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $matches[1]]);
    $item = $stmt->fetch();
    if (!$item) {
        failure('ADMIN_TIER_TEMPLATE_NOT_FOUND', 'Tier template not found');
    }
    $item['id'] = (int) $item['id'];
    success('ADMIN_TIER_TEMPLATE_SUCCESS', 'ok', ['item' => $item]);
}

if (preg_match('#^/api/admin/tier-templates/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $id = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT * FROM tier_templates WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $before = $stmt->fetch();
    if (!$before) {
        failure('ADMIN_TIER_TEMPLATE_NOT_FOUND', 'Tier template not found');
    }
    $level = array_key_exists('level', $input) ? normalize_tier_level($input['level']) : (int) $before['level'];
    $next = [
        'template_code' => array_key_exists('template_code', $input) ? strtolower(trim((string) $input['template_code'])) : $before['template_code'],
        'display_name' => array_key_exists('display_name', $input) ? trim((string) $input['display_name']) : $before['display_name'],
        'level' => $level,
        'group_code' => array_key_exists('group_code', $input) && trim((string) $input['group_code']) !== '' ? trim((string) $input['group_code']) : ($before['group_code'] ?: tier_group_code_for_level($level)),
        'score' => array_key_exists('score', $input) ? (int) $input['score'] : (int) $before['score'],
        'merchant_enabled' => array_key_exists('merchant_enabled', $input) ? (json_bool($input['merchant_enabled']) ? 1 : 0) : (int) $before['merchant_enabled'],
        'is_verified' => array_key_exists('is_verified', $input) ? (json_bool($input['is_verified']) ? 1 : 0) : (int) $before['is_verified'],
        'daily_trade_limit' => array_key_exists('daily_trade_limit', $input) && $input['daily_trade_limit'] !== '' ? (int) $input['daily_trade_limit'] : ($before['daily_trade_limit'] !== null ? (int) $before['daily_trade_limit'] : null),
        'min_sell_amount' => array_key_exists('min_sell_amount', $input) ? number_format((float) $input['min_sell_amount'], 8, '.', '') : $before['min_sell_amount'],
        'margin_amount' => array_key_exists('margin_amount', $input) ? number_format((float) $input['margin_amount'], 8, '.', '') : $before['margin_amount'],
        'margin_ratio' => array_key_exists('margin_ratio', $input) ? number_format((float) $input['margin_ratio'], 4, '.', '') : $before['margin_ratio'],
        'risk_status' => array_key_exists('risk_status', $input) && in_array($input['risk_status'], ['normal', 'warning', 'blocked'], true) ? (string) $input['risk_status'] : $before['risk_status'],
        'description' => array_key_exists('description', $input) ? trim((string) $input['description']) : $before['description'],
        'status' => array_key_exists('status', $input) && in_array($input['status'], ['active', 'disabled'], true) ? (string) $input['status'] : $before['status'],
        'sort_order' => array_key_exists('sort_order', $input) ? (int) $input['sort_order'] : (int) $before['sort_order'],
        'updated_at' => now_iso(),
    ];
    if ($next['template_code'] === '' || !preg_match('/^[a-z0-9_\-]+$/', (string) $next['template_code'])) {
        failure('ADMIN_TIER_TEMPLATE_CODE_INVALID', 'Template code is invalid');
    }
    if ($next['display_name'] === '') {
        failure('ADMIN_TIER_TEMPLATE_NAME_REQUIRED', 'Display name is required');
    }
    $pdo->prepare('UPDATE tier_templates SET
        template_code = :template_code, display_name = :display_name, level = :level, group_code = :group_code, score = :score,
        merchant_enabled = :merchant_enabled, is_verified = :is_verified, daily_trade_limit = :daily_trade_limit,
        min_sell_amount = :min_sell_amount, margin_amount = :margin_amount, margin_ratio = :margin_ratio,
        risk_status = :risk_status, description = :description, status = :status, sort_order = :sort_order, updated_at = :updated_at
        WHERE id = :id')->execute(array_merge($next, [':id' => $id]));
    audit($pdo, 'admin', 'tier_template_updated', 'admin', (int) $admin['admin_user_id'], 'tier_template', $id, trim((string) ($input['reason'] ?? '')) ?: null, $before, $next);
    success('ADMIN_TIER_TEMPLATE_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/tier-templates/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $admin = require_admin($pdo);
    $id = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT * FROM tier_templates WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $before = $stmt->fetch();
    if (!$before) {
        failure('ADMIN_TIER_TEMPLATE_NOT_FOUND', 'Tier template not found');
    }
    $pdo->prepare('DELETE FROM tier_templates WHERE id = :id')->execute([':id' => $id]);
    audit($pdo, 'admin', 'tier_template_deleted', 'admin', (int) $admin['admin_user_id'], 'tier_template', $id, trim((string) ($input['reason'] ?? '')) ?: null, $before, null);
    success('ADMIN_TIER_TEMPLATE_DELETED', 'ok', []);
}

if (preg_match('#^/api/admin/users/(\d+)/tier-profile$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $stmt = $pdo->prepare('SELECT user_id, level, group_code, score, merchant_enabled, is_verified, daily_trade_limit, min_sell_amount, margin_amount, margin_ratio, risk_status, violation_message, updated_at
        FROM user_tier_profiles
        WHERE user_id = :user_id
        LIMIT 1');
    $stmt->execute([':user_id' => $userId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        failure('ADMIN_TIER_PROFILE_NOT_FOUND', 'Tier profile not found');
    }
    $profile['user_id'] = (int) $profile['user_id'];
    $profile['level'] = normalize_tier_level($profile['level'] ?? 1);
    $profile['group_code'] = trim((string) ($profile['group_code'] ?? '')) !== ''
        ? trim((string) $profile['group_code'])
        : tier_group_code_for_level($profile['level']);
    $profile['score'] = (int) $profile['score'];
    $profile['merchant_enabled'] = (bool) $profile['merchant_enabled'];
    $profile['is_verified'] = (bool) $profile['is_verified'];
    $profile['daily_trade_limit'] = tier_daily_sell_limit_for_level($profile['level']);
    success('ADMIN_USER_TIER_PROFILE_SUCCESS', 'ok', ['tier_profile' => $profile]);
}

if (preg_match('#^/api/admin/users/(\d+)/tier-profile$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    require_admin_can_access_user($pdo, $admin, $userId);
    $reason = trim((string) ($input['reason'] ?? ''));
    if (isset($input['level']) && (!is_numeric($input['level']) || (int) $input['level'] < 1 || (int) $input['level'] > 3)) {
        failure('ADMIN_LEVEL_INVALID', 'Level is invalid');
    }
    foreach ([
        'min_sell_amount' => 'ADMIN_MIN_SELL_AMOUNT_INVALID',
        'margin_amount' => 'ADMIN_MARGIN_AMOUNT_INVALID',
        'margin_ratio' => 'ADMIN_MARGIN_RATIO_INVALID',
    ] as $field => $errorCode) {
        if (isset($input[$field]) && $input[$field] !== '' && !is_numeric($input[$field])) {
            failure($errorCode, "{$field} is invalid");
        }
    }
    if (isset($input['risk_status']) && !in_array($input['risk_status'], ['normal', 'warning', 'blocked'], true)) {
        failure('ADMIN_RISK_STATUS_INVALID', 'Risk status is invalid');
    }

    $stmt = $pdo->prepare('SELECT * FROM user_tier_profiles WHERE user_id = :user_id LIMIT 1');
    $stmt->execute([':user_id' => $userId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        failure('ADMIN_TIER_PROFILE_NOT_FOUND', 'Tier profile not found');
    }

    $next = [
        'level' => isset($input['level']) ? normalize_tier_level($input['level']) : normalize_tier_level($profile['level'] ?? 1),
        'group_code' => array_key_exists('group_code', $input)
            ? (trim((string) $input['group_code']) !== ''
                ? trim((string) $input['group_code'])
                : tier_group_code_for_level(isset($input['level']) ? normalize_tier_level($input['level']) : normalize_tier_level($profile['level'] ?? 1)))
            : (trim((string) ($profile['group_code'] ?? '')) !== ''
                ? trim((string) $profile['group_code'])
                : tier_group_code_for_level(normalize_tier_level($profile['level'] ?? 1))),
        'score' => isset($input['score']) && $input['score'] !== '' ? (int) $input['score'] : (int) $profile['score'],
        'merchant_enabled' => array_key_exists('merchant_enabled', $input) ? (json_bool($input['merchant_enabled']) ? 1 : 0) : (int) $profile['merchant_enabled'],
        'is_verified' => array_key_exists('is_verified', $input) ? (json_bool($input['is_verified']) ? 1 : 0) : (int) $profile['is_verified'],
        'daily_trade_limit' => tier_daily_sell_limit_for_level(isset($input['level']) ? normalize_tier_level($input['level']) : normalize_tier_level($profile['level'] ?? 1)),
        'min_sell_amount' => array_key_exists('min_sell_amount', $input) && $input['min_sell_amount'] !== '' ? number_format((float) $input['min_sell_amount'], 8, '.', '') : $profile['min_sell_amount'],
        'margin_amount' => array_key_exists('margin_amount', $input) && $input['margin_amount'] !== '' ? number_format((float) $input['margin_amount'], 8, '.', '') : $profile['margin_amount'],
        'margin_ratio' => array_key_exists('margin_ratio', $input) && $input['margin_ratio'] !== '' ? number_format((float) $input['margin_ratio'], 4, '.', '') : $profile['margin_ratio'],
        'risk_status' => array_key_exists('risk_status', $input) ? trim((string) $input['risk_status']) : $profile['risk_status'],
        'violation_message' => array_key_exists('violation_message', $input) ? trim((string) $input['violation_message']) : $profile['violation_message'],
    ];

    $pdo->prepare('UPDATE user_tier_profiles SET
        level = :level,
        group_code = :group_code,
        score = :score,
        merchant_enabled = :merchant_enabled,
        is_verified = :is_verified,
        daily_trade_limit = :daily_trade_limit,
        min_sell_amount = :min_sell_amount,
        margin_amount = :margin_amount,
        margin_ratio = :margin_ratio,
        risk_status = :risk_status,
        violation_message = :violation_message,
        updated_at = :updated_at
        WHERE user_id = :user_id')
        ->execute([
            ':level' => $next['level'],
            ':group_code' => $next['group_code'],
            ':score' => $next['score'],
            ':merchant_enabled' => $next['merchant_enabled'],
            ':is_verified' => $next['is_verified'],
            ':daily_trade_limit' => $next['daily_trade_limit'],
            ':min_sell_amount' => $next['min_sell_amount'],
            ':margin_amount' => $next['margin_amount'],
            ':margin_ratio' => $next['margin_ratio'],
            ':risk_status' => $next['risk_status'],
            ':violation_message' => $next['violation_message'],
            ':updated_at' => now_iso(),
            ':user_id' => $userId,
        ]);
    audit($pdo, 'admin', 'tier_profile_updated', 'admin', (int) $admin['admin_user_id'], 'tier_profile', $userId, $reason, $profile, $next);
    success('ADMIN_USER_TIER_PROFILE_UPDATED', 'ok', []);
}

if ($path === '/api/admin/deposit-requests' && $method === 'GET') {
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $status = trim((string) ($_GET['status'] ?? ''));
    $userLookup = trim((string) ($_GET['user_id'] ?? ''));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));
    $assetCodeFilter = strtoupper(trim((string) ($_GET['asset_code'] ?? '')));

    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'd.status = :status';
        $params[':status'] = $status;
    }
    if ($assetCodeFilter !== '' && in_array($assetCodeFilter, ['USDT', 'EUR'], true)) {
        $where[] = 'd.asset_code = :asset_code_filter';
        $params[':asset_code_filter'] = $assetCodeFilter;
    }
    apply_admin_lookup_filter($where, $params, $userLookup, ['d.user_id'], ['u.email', 'u.mobile_e164'], 'deposit_user');
    if ($keyword !== '') {
        $where[] = '(u.username LIKE :keyword OR COALESCE(d.reference_text, "") LIKE :keyword OR d.asset_code LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    if ($scope['sql'] !== '') {
        $where[] = $scope['sql'];
        $params = array_merge($params, $scope['params']);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM deposit_requests d JOIN users u ON u.id = d.user_id {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT d.id, d.user_id, u.username, u.email, u.mobile_e164, u.invitation_code, d.amount, d.asset_code, d.network, d.target_wallet_code, d.status, d.created_at, d.reviewed_at
        FROM deposit_requests d
        JOIN users u ON u.id = d.user_id
        {$whereSql}
        ORDER BY d.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['user_id'] = (int) $item['user_id'];
        return $item;
    }, $stmt->fetchAll());

    success('ADMIN_DEPOSIT_REQUESTS_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/deposit-requests/(\d+)$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $requestId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT d.*, u.username, u.email, u.mobile_e164
        FROM deposit_requests d
        JOIN users u ON u.id = d.user_id
        WHERE d.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $requestId]);
    $requestItem = $stmt->fetch();
    if (!$requestItem) {
        failure('ADMIN_DEPOSIT_REQUEST_NOT_FOUND', 'Deposit request not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $requestItem['user_id']);
    $requestItem['id'] = (int) $requestItem['id'];
    $requestItem['user_id'] = (int) $requestItem['user_id'];
    $requestItem['reviewed_by_admin_id'] = $requestItem['reviewed_by_admin_id'] !== null ? (int) $requestItem['reviewed_by_admin_id'] : null;
    success('ADMIN_DEPOSIT_REQUEST_DETAIL_SUCCESS', 'ok', ['request' => $requestItem]);
}

if (preg_match('#^/api/admin/deposit-requests/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $requestId = (int) $matches[1];
    $action = trim((string) ($input['action'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    $allowedActions = ['approve', 'reject', 'cancel'];
    if (!in_array($action, $allowedActions, true)) {
        failure('ADMIN_DEPOSIT_ACTION_INVALID', 'Deposit action is invalid');
    }
    if ($reason === '' && $action !== 'approve') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }

    $stmt = $pdo->prepare('SELECT * FROM deposit_requests WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $requestId]);
    $requestItem = $stmt->fetch();
    if (!$requestItem) {
        failure('ADMIN_DEPOSIT_REQUEST_NOT_FOUND', 'Deposit request not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $requestItem['user_id']);
    if (($requestItem['status'] ?? '') !== 'pending') {
        failure('ADMIN_DEPOSIT_STATUS_INVALID', 'Deposit request status is invalid');
    }

    $nextStatus = match ($action) {
        'approve' => 'approved',
        'reject' => 'rejected',
        default => 'cancelled',
    };
    $walletDelta = null;
    if ($action === 'approve') {
        $walletDelta = apply_wallet_delta($pdo, (int) $requestItem['user_id'], (string) $requestItem['target_wallet_code'], (float) $requestItem['amount'], 0.0);
    }

    $reviewedAt = now_iso();
    $pdo->prepare('UPDATE deposit_requests
        SET status = :status, admin_note = :admin_note, reviewed_by_admin_id = :reviewed_by_admin_id, reviewed_at = :reviewed_at, updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':status' => $nextStatus,
            ':admin_note' => $reason,
            ':reviewed_by_admin_id' => (int) $admin['admin_user_id'],
            ':reviewed_at' => $reviewedAt,
            ':updated_at' => $reviewedAt,
            ':id' => $requestId,
        ]);
    audit(
        $pdo,
        'admin',
        'deposit_request_reviewed',
        'admin',
        (int) $admin['admin_user_id'],
        'deposit_request',
        $requestId,
        $reason,
        ['status' => $requestItem['status']],
        [
            'status' => $nextStatus,
            'action' => $action,
            'wallet_code' => $requestItem['target_wallet_code'],
            'amount' => $requestItem['amount'],
            'wallet_delta' => $walletDelta,
        ]
    );
    success('ADMIN_DEPOSIT_REQUEST_REVIEWED', 'ok', []);
}

if ($path === '/api/admin/withdrawal-requests' && $method === 'GET') {
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $status = trim((string) ($_GET['status'] ?? ''));
    $userLookup = trim((string) ($_GET['user_id'] ?? ''));
    $channelType = trim((string) ($_GET['channel_type'] ?? ''));
    $assetCode = strtoupper(trim((string) ($_GET['asset_code'] ?? '')));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));

    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'w.status = :status';
        $params[':status'] = $status;
    }
    apply_admin_lookup_filter($where, $params, $userLookup, ['w.user_id'], ['u.email', 'u.mobile_e164'], 'withdrawal_user');
    if ($channelType !== '') {
        $where[] = 'w.channel_type = :channel_type';
        $params[':channel_type'] = $channelType;
    }
    if ($assetCode !== '') {
        $where[] = 'w.asset_code = :asset_code';
        $params[':asset_code'] = $assetCode;
    }
    if ($keyword !== '') {
        $where[] = '(u.username LIKE :keyword OR COALESCE(w.payout_address, "") LIKE :keyword OR w.asset_code LIKE :keyword OR COALESCE(w.remark, "") LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    if ($scope['sql'] !== '') {
        $where[] = $scope['sql'];
        $params = array_merge($params, $scope['params']);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_requests w JOIN users u ON u.id = w.user_id {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT w.id, w.user_id, u.username, u.email, u.mobile_e164, u.invitation_code, w.amount, w.asset_code, w.channel_type, w.source_wallet_code, w.remark, w.status, w.created_at, w.reviewed_at
        FROM withdrawal_requests w
        JOIN users u ON u.id = w.user_id
        {$whereSql}
        ORDER BY w.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['user_id'] = (int) $item['user_id'];
        $item['remark'] = derive_withdrawal_remark($item);
        return $item;
    }, $stmt->fetchAll());

    success('ADMIN_WITHDRAWAL_REQUESTS_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/withdrawal-requests/(\d+)$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $requestId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT w.*, u.username, u.email, u.mobile_e164, p.bank_name, p.account_holder, p.account_no_masked, p.usdt_network, p.pix_key
        FROM withdrawal_requests w
        JOIN users u ON u.id = w.user_id
        LEFT JOIN user_payout_methods p ON p.id = w.payout_method_id
        WHERE w.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $requestId]);
    $requestItem = $stmt->fetch();
    if (!$requestItem) {
        failure('ADMIN_WITHDRAWAL_REQUEST_NOT_FOUND', 'Withdrawal request not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $requestItem['user_id']);
    $requestItem['id'] = (int) $requestItem['id'];
    $requestItem['user_id'] = (int) $requestItem['user_id'];
    $requestItem['payout_method_id'] = $requestItem['payout_method_id'] !== null ? (int) $requestItem['payout_method_id'] : null;
    $requestItem['reviewed_by_admin_id'] = $requestItem['reviewed_by_admin_id'] !== null ? (int) $requestItem['reviewed_by_admin_id'] : null;
    $requestItem['remark'] = derive_withdrawal_remark($requestItem);
    success('ADMIN_WITHDRAWAL_REQUEST_DETAIL_SUCCESS', 'ok', ['request' => $requestItem]);
}

if (preg_match('#^/api/admin/withdrawal-requests/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $requestId = (int) $matches[1];
    $action = trim((string) ($input['action'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    $allowedActions = ['approve', 'reject', 'cancel'];
    if (!in_array($action, $allowedActions, true)) {
        failure('ADMIN_WITHDRAWAL_ACTION_INVALID', 'Withdrawal action is invalid');
    }
    if ($reason === '' && $action !== 'approve') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }

    $stmt = $pdo->prepare('SELECT * FROM withdrawal_requests WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $requestId]);
    $requestItem = $stmt->fetch();
    if (!$requestItem) {
        failure('ADMIN_WITHDRAWAL_REQUEST_NOT_FOUND', 'Withdrawal request not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $requestItem['user_id']);
    if (($requestItem['status'] ?? '') !== 'pending') {
        failure('ADMIN_WITHDRAWAL_STATUS_INVALID', 'Withdrawal request status is invalid');
    }

    $nextStatus = match ($action) {
        'approve' => 'approved',
        'reject' => 'rejected',
        default => 'cancelled',
    };
    $walletDelta = null;
    $reviewedAt = now_iso();
    try {
        $pdo->beginTransaction();

        $walletStmt = $pdo->prepare('SELECT available_balance, reserved_balance FROM user_wallet_balances WHERE user_id = :user_id AND wallet_code = :wallet_code LIMIT 1');
        $walletStmt->execute([
            ':user_id' => (int) $requestItem['user_id'],
            ':wallet_code' => (string) $requestItem['source_wallet_code'],
        ]);
        $wallet = $walletStmt->fetch() ?: ['available_balance' => '0.00000000', 'reserved_balance' => '0.00000000'];
        $amount = (float) $requestItem['amount'];
        $reservedAmount = min((float) ($wallet['reserved_balance'] ?? 0), $amount);

        if ($action === 'approve') {
            if ($reservedAmount > 0) {
                $walletDelta = apply_wallet_delta(
                    $pdo,
                    (int) $requestItem['user_id'],
                    (string) $requestItem['source_wallet_code'],
                    0.0,
                    -$reservedAmount
                );
            }
            $remainingAmount = $amount - $reservedAmount;
            if ($remainingAmount > 0) {
                $walletDelta = apply_wallet_delta(
                    $pdo,
                    (int) $requestItem['user_id'],
                    (string) $requestItem['source_wallet_code'],
                    -$remainingAmount,
                    0.0
                );
            }
        } elseif (in_array($action, ['reject', 'cancel'], true) && $reservedAmount > 0) {
            $walletDelta = apply_wallet_delta(
                $pdo,
                (int) $requestItem['user_id'],
                (string) $requestItem['source_wallet_code'],
                $reservedAmount,
                -$reservedAmount
            );
        }

        $pdo->prepare('UPDATE withdrawal_requests
            SET status = :status, admin_note = :admin_note, reviewed_by_admin_id = :reviewed_by_admin_id, reviewed_at = :reviewed_at, updated_at = :updated_at
            WHERE id = :id')
            ->execute([
                ':status' => $nextStatus,
                ':admin_note' => $reason,
                ':reviewed_by_admin_id' => (int) $admin['admin_user_id'],
                ':reviewed_at' => $reviewedAt,
                ':updated_at' => $reviewedAt,
                ':id' => $requestId,
            ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        failure('ADMIN_WITHDRAWAL_REVIEW_FAILED', 'Withdrawal review failed');
    }
    audit(
        $pdo,
        'admin',
        'withdrawal_request_reviewed',
        'admin',
        (int) $admin['admin_user_id'],
        'withdrawal_request',
        $requestId,
        $reason,
        ['status' => $requestItem['status']],
        [
            'status' => $nextStatus,
            'action' => $action,
            'wallet_code' => $requestItem['source_wallet_code'],
            'amount' => $requestItem['amount'],
            'wallet_delta' => $walletDelta,
        ]
    );
    success('ADMIN_WITHDRAWAL_REQUEST_REVIEWED', 'ok', []);
}

if ($path === '/api/admin/orders' && $method === 'GET') {
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $status = trim((string) ($_GET['status'] ?? ''));
    $side = trim((string) ($_GET['side'] ?? ''));
    $userLookup = trim((string) ($_GET['user_id'] ?? ''));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));

    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'o.status = :status';
        $params[':status'] = $status;
    }
    if ($side !== '') {
        $where[] = 'o.side = :side';
        $params[':side'] = $side;
    }
    apply_admin_lookup_filter(
        $where,
        $params,
        $userLookup,
        ['o.buyer_user_id', 'o.seller_user_id'],
        ['buyer.email', 'buyer.mobile_e164', 'seller.email', 'seller.mobile_e164'],
        'order_user'
    );
    if ($keyword !== '') {
        $where[] = '(o.order_no LIKE :keyword OR buyer.username LIKE :keyword OR seller.username LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $buyerScope = admin_user_scope_sql($pdo, $admin, 'buyer', 'scope_buyer');
    $sellerScope = admin_user_scope_sql($pdo, $admin, 'seller', 'scope_seller');
    if ($buyerScope['sql'] !== '' && $sellerScope['sql'] !== '') {
        $where[] = '((' . $buyerScope['sql'] . ') OR (' . $sellerScope['sql'] . '))';
        $params = array_merge($params, $buyerScope['params'], $sellerScope['params']);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*)
        FROM c2c_orders o
        LEFT JOIN users buyer ON buyer.id = o.buyer_user_id
        LEFT JOIN users seller ON seller.id = o.seller_user_id
        {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT o.id, o.order_no, o.side, o.amount, o.price, o.total_amount, o.asset_code, o.fiat_code, o.status, o.created_at,
            o.buyer_user_id, buyer.username AS buyer_username, buyer.email AS buyer_email, buyer.mobile_e164 AS buyer_mobile_e164, buyer.invitation_code AS buyer_invitation_code, buyer.invited_by_admin_id AS buyer_invited_by_admin_id,
            o.seller_user_id, seller.username AS seller_username, seller.email AS seller_email, seller.mobile_e164 AS seller_mobile_e164, seller.invitation_code AS seller_invitation_code, seller.invited_by_admin_id AS seller_invited_by_admin_id
        FROM c2c_orders o
        LEFT JOIN users buyer ON buyer.id = o.buyer_user_id
        LEFT JOIN users seller ON seller.id = o.seller_user_id
        {$whereSql}
        ORDER BY o.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['buyer_user_id'] = (int) $item['buyer_user_id'];
        $item['seller_user_id'] = (int) $item['seller_user_id'];
        $item['buyer_source'] = admin_user_source_label($item['buyer_invited_by_admin_id'] ?? null, (int) $item['buyer_user_id']);
        $item['seller_source'] = admin_user_source_label($item['seller_invited_by_admin_id'] ?? null, (int) $item['seller_user_id']);
        $merchantUserId = ((string) ($item['side'] ?? '') === 'buy') ? (int) $item['seller_user_id'] : (int) $item['buyer_user_id'];
        $item['merchant_user_id'] = $merchantUserId;
        $item['merchant_source'] = ((string) ($item['side'] ?? '') === 'buy') ? $item['seller_source'] : $item['buyer_source'];
        unset($item['buyer_invited_by_admin_id'], $item['seller_invited_by_admin_id']);
        return $item;
    }, $stmt->fetchAll());

    success('ADMIN_ORDERS_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/orders/(\d+)$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $orderId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT o.*, buyer.username AS buyer_username, buyer.email AS buyer_email, buyer.mobile_e164 AS buyer_mobile_e164, buyer.invited_by_admin_id AS buyer_invited_by_admin_id,
            seller.username AS seller_username, seller.email AS seller_email, seller.mobile_e164 AS seller_mobile_e164, seller.invited_by_admin_id AS seller_invited_by_admin_id
        FROM c2c_orders o
        LEFT JOIN users buyer ON buyer.id = o.buyer_user_id
        LEFT JOIN users seller ON seller.id = o.seller_user_id
        WHERE o.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        failure('ADMIN_ORDER_NOT_FOUND', 'Order not found');
    }
    if (
        !admin_can_access_user($pdo, $admin, (int) $order['buyer_user_id']) &&
        !admin_can_access_user($pdo, $admin, (int) $order['seller_user_id'])
    ) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    $order['id'] = (int) $order['id'];
    $order['buyer_user_id'] = (int) $order['buyer_user_id'];
    $order['seller_user_id'] = (int) $order['seller_user_id'];
    $order['buyer_source'] = admin_user_source_label($order['buyer_invited_by_admin_id'] ?? null, (int) $order['buyer_user_id']);
    $order['seller_source'] = admin_user_source_label($order['seller_invited_by_admin_id'] ?? null, (int) $order['seller_user_id']);
    $order['merchant_user_id'] = ((string) ($order['side'] ?? '') === 'buy') ? (int) $order['seller_user_id'] : (int) $order['buyer_user_id'];
    $order['merchant_source'] = ((string) ($order['side'] ?? '') === 'buy') ? $order['seller_source'] : $order['buyer_source'];
    unset($order['buyer_invited_by_admin_id'], $order['seller_invited_by_admin_id']);
    $evidenceStmt = $pdo->prepare('SELECT id, actor_type, actor_id, evidence_type, content, attachment_url, created_at
        FROM order_evidences
        WHERE order_id = :order_id
        ORDER BY id DESC');
    $evidenceStmt->execute([':order_id' => $orderId]);
    success('ADMIN_ORDER_DETAIL_SUCCESS', 'ok', [
        'order' => $order,
        'evidences' => array_map(static function (array $item): array {
            $item['id'] = (int) $item['id'];
            $item['actor_id'] = $item['actor_id'] !== null ? (int) $item['actor_id'] : null;
            return $item;
        }, $evidenceStmt->fetchAll()),
    ]);
}

if (preg_match('#^/api/admin/orders/(\d+)/status$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $orderId = (int) $matches[1];
    $status = trim((string) ($input['status'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    $allowedStatuses = ['pending_payment', 'paid_pending_release', 'completed', 'cancelled', 'disputed'];
    if (!in_array($status, $allowedStatuses, true)) {
        failure('ADMIN_ORDER_STATUS_INVALID', 'Order status is invalid');
    }
    if ($reason === '' && $status !== 'completed') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }

    $stmt = $pdo->prepare('SELECT * FROM c2c_orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        failure('ADMIN_ORDER_NOT_FOUND', 'Order not found');
    }
    if (
        !admin_can_access_user($pdo, $admin, (int) $order['buyer_user_id']) &&
        !admin_can_access_user($pdo, $admin, (int) $order['seller_user_id'])
    ) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    if ((string) $order['side'] === 'sell' && $status === 'pending_payment') {
        failure('ADMIN_ORDER_STATUS_INVALID', 'Sell orders do not use pending payment status');
    }

    $completedAt = $status === 'completed' ? now_iso() : null;
    $cancelReason = $status === 'cancelled' ? $reason : null;
    $disputeReason = $status === 'disputed' ? $reason : null;
    try {
        $pdo->beginTransaction();

        if ($status === 'completed' && (string) $order['status'] !== 'completed') {
            settle_completed_c2c_order($pdo, $order);
        }

        if ($status === 'cancelled' && !in_array((string) $order['status'], ['completed', 'cancelled'], true)) {
            refund_sell_order_assets($pdo, $order);
            refund_buy_order_fiat($pdo, $order);
            restore_c2c_listing_inventory($pdo, $order);
        }

        $pdo->prepare('UPDATE c2c_orders
            SET status = :status, completed_at = :completed_at, cancel_reason = :cancel_reason, dispute_reason = :dispute_reason, updated_at = :updated_at
            WHERE id = :id')
            ->execute([
                ':status' => $status,
                ':completed_at' => $completedAt,
                ':cancel_reason' => $cancelReason,
                ':dispute_reason' => $disputeReason,
                ':updated_at' => now_iso(),
                ':id' => $orderId,
            ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        failure('ADMIN_ORDER_UPDATE_FAILED', 'Order update failed');
    }
    audit($pdo, 'admin', 'order_status_updated', 'admin', (int) $admin['admin_user_id'], 'order', $orderId, $reason !== '' ? $reason : null, ['status' => $order['status']], ['status' => $status]);
    success('ADMIN_ORDER_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/orders/(\d+)/release$#', $path, $matches) && $method === 'POST') {
    $admin = require_admin($pdo);
    $orderId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));
    $stmt = $pdo->prepare('SELECT * FROM c2c_orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        failure('ADMIN_ORDER_NOT_FOUND', 'Order not found');
    }
    if (
        !admin_can_access_user($pdo, $admin, (int) $order['buyer_user_id']) &&
        !admin_can_access_user($pdo, $admin, (int) $order['seller_user_id'])
    ) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    $now = now_iso();
    if ((string) $order['status'] === 'completed') {
        failure('ADMIN_ORDER_ALREADY_COMPLETED', 'Order is already completed');
    }
    try {
        $pdo->beginTransaction();
        settle_completed_c2c_order($pdo, $order);
        $pdo->prepare('UPDATE c2c_orders
            SET status = "completed", completed_at = :completed_at, updated_at = :updated_at
            WHERE id = :id')
            ->execute([
                ':completed_at' => $now,
                ':updated_at' => $now,
                ':id' => $orderId,
            ]);
        $pdo->prepare('INSERT INTO order_evidences (order_id, actor_type, actor_id, evidence_type, content, attachment_url, created_at)
            VALUES (:order_id, "admin", :actor_id, "release", :content, null, :created_at)')
            ->execute([
                ':order_id' => $orderId,
                ':actor_id' => (int) $admin['admin_user_id'],
                ':content' => $reason !== '' ? $reason : 'Admin confirmed fiat release',
                ':created_at' => $now,
            ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        failure('ADMIN_ORDER_RELEASE_FAILED', 'Order release failed');
    }
    audit($pdo, 'admin', 'order_released', 'admin', (int) $admin['admin_user_id'], 'order', $orderId, $reason !== '' ? $reason : null, ['status' => $order['status']], ['status' => 'completed']);
    success('ADMIN_ORDER_RELEASED', 'ok', []);
}

if (preg_match('#^/api/admin/orders/(\d+)/dispute$#', $path, $matches) && $method === 'POST') {
    $admin = require_admin($pdo);
    $orderId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '' && $action !== 'approve') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }
    $stmt = $pdo->prepare('SELECT * FROM c2c_orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        failure('ADMIN_ORDER_NOT_FOUND', 'Order not found');
    }
    if (
        !admin_can_access_user($pdo, $admin, (int) $order['buyer_user_id']) &&
        !admin_can_access_user($pdo, $admin, (int) $order['seller_user_id'])
    ) {
        failure('ADMIN_FORBIDDEN', 'Permission denied');
    }
    $pdo->prepare('UPDATE c2c_orders SET status = "disputed", dispute_reason = :dispute_reason, updated_at = :updated_at WHERE id = :id')
        ->execute([
            ':dispute_reason' => $reason,
            ':updated_at' => now_iso(),
            ':id' => $orderId,
        ]);
    $pdo->prepare('INSERT INTO order_evidences (order_id, actor_type, actor_id, evidence_type, content, attachment_url, created_at)
        VALUES (:order_id, "admin", :actor_id, "dispute", :content, null, :created_at)')
        ->execute([
            ':order_id' => $orderId,
            ':actor_id' => (int) $admin['admin_user_id'],
            ':content' => $reason,
            ':created_at' => now_iso(),
        ]);
    audit($pdo, 'admin', 'order_disputed', 'admin', (int) $admin['admin_user_id'], 'order', $orderId, $reason, ['status' => $order['status']], ['status' => 'disputed']);
    success('ADMIN_ORDER_DISPUTED', 'ok', []);
}

if (preg_match('#^/api/admin/orders/(\d+)/evidences$#', $path, $matches) && $method === 'POST') {
    $admin = require_admin($pdo);
    $orderId = (int) $matches[1];
    $content = trim((string) ($input['content'] ?? ''));
    $attachmentUrl = trim((string) ($input['attachment_url'] ?? ''));
    $evidenceType = trim((string) ($input['evidence_type'] ?? 'note'));
    if ($content === '' && $attachmentUrl === '') {
        failure('ADMIN_REASON_REQUIRED', 'Evidence content is required');
    }
    $stmt = $pdo->prepare('SELECT id FROM c2c_orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    if (!$stmt->fetch()) {
        failure('ADMIN_ORDER_NOT_FOUND', 'Order not found');
    }
    $pdo->prepare('INSERT INTO order_evidences (order_id, actor_type, actor_id, evidence_type, content, attachment_url, created_at)
        VALUES (:order_id, "admin", :actor_id, :evidence_type, :content, :attachment_url, :created_at)')
        ->execute([
            ':order_id' => $orderId,
            ':actor_id' => (int) $admin['admin_user_id'],
            ':evidence_type' => $evidenceType,
            ':content' => $content !== '' ? $content : null,
            ':attachment_url' => $attachmentUrl !== '' ? $attachmentUrl : null,
            ':created_at' => now_iso(),
        ]);
    success('ADMIN_ORDER_EVIDENCE_ADDED', 'ok', []);
}

if ($path === '/api/admin/financial-products' && $method === 'GET') {
    require_admin($pdo);
    $keyword = trim((string) ($_GET['keyword'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $where = [];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(product_code LIKE :keyword OR IFNULL(display_name, "") LIKE :keyword OR IFNULL(subtitle, "") LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    if ($status !== '') {
        $where[] = 'status = :status';
        $params[':status'] = normalize_financial_product_status($status);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare("SELECT * FROM financial_products {$whereSql} ORDER BY sort_order ASC, id ASC");
    $stmt->execute($params);
    $items = array_map(static function (array $item) use ($pdo): array {
        return serialize_financial_product($pdo, 0, $item);
    }, $stmt->fetchAll());
    success('ADMIN_FINANCIAL_PRODUCTS_SUCCESS', 'ok', ['items' => $items]);
}

if (preg_match('#^/api/admin/financial-products/(\d+)$#', $path, $matches) && $method === 'GET') {
    require_admin($pdo);
    $stmt = $pdo->prepare('SELECT * FROM financial_products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $matches[1]]);
    $product = $stmt->fetch();
    if (!$product) {
        failure('AUTH_INVALID_PARAMS', 'Financial product not found');
    }
    success('ADMIN_FINANCIAL_PRODUCT_SUCCESS', 'ok', ['item' => serialize_financial_product($pdo, 0, $product)]);
}

if ($path === '/api/admin/financial-products' && $method === 'POST') {
    $admin = require_admin($pdo);
    $payload = sanitize_financial_product_payload($input);
    $exists = $pdo->prepare('SELECT id FROM financial_products WHERE product_code = :product_code LIMIT 1');
    $exists->execute([':product_code' => $payload['product_code']]);
    if ($exists->fetch()) {
        failure('AUTH_INVALID_PARAMS', 'Financial product code already exists');
    }
    $now = now_iso();
    $pdo->prepare('INSERT INTO financial_products (
        product_code, asset_code, wallet_code, display_name, subtitle, detail_note, apr_rate, term_days,
        min_subscribe_amount, personal_limit_amount, total_quota_amount, sold_quota_amount, auto_renew_default,
        default_return_mode, default_return_delay_days, status, sort_order, created_at, updated_at
    ) VALUES (
        :product_code, :asset_code, :wallet_code, :display_name, :subtitle, :detail_note, :apr_rate, :term_days,
        :min_subscribe_amount, :personal_limit_amount, :total_quota_amount, "0.00000000", 0,
        :default_return_mode, :default_return_delay_days, :status, :sort_order, :created_at, :updated_at
    )')->execute([
        ':product_code' => $payload['product_code'],
        ':asset_code' => $payload['asset_code'],
        ':wallet_code' => $payload['wallet_code'],
        ':display_name' => $payload['display_name'],
        ':subtitle' => $payload['subtitle'],
        ':detail_note' => $payload['detail_note'],
        ':apr_rate' => $payload['apr_rate'],
        ':term_days' => $payload['term_days'],
        ':min_subscribe_amount' => $payload['min_subscribe_amount'],
        ':personal_limit_amount' => $payload['personal_limit_amount'],
        ':total_quota_amount' => $payload['total_quota_amount'],
        ':default_return_mode' => $payload['default_return_mode'],
        ':default_return_delay_days' => $payload['default_return_delay_days'],
        ':status' => $payload['status'],
        ':sort_order' => $payload['sort_order'],
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $productId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM financial_products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    $serialized = serialize_financial_product($pdo, 0, $product);
    audit($pdo, 'admin', 'financial_product_created', 'admin', (int) $admin['admin_user_id'], 'financial_product', $productId, null, null, $serialized);
    success('ADMIN_FINANCIAL_PRODUCT_CREATED', 'ok', ['item' => $serialized]);
}

if (preg_match('#^/api/admin/financial-products/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $productId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT * FROM financial_products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        failure('AUTH_INVALID_PARAMS', 'Financial product not found');
    }
    $payload = sanitize_financial_product_payload([
        'product_code' => $input['product_code'] ?? $existing['product_code'],
        'asset_code' => $input['asset_code'] ?? $existing['asset_code'],
        'wallet_code' => $input['wallet_code'] ?? $existing['wallet_code'],
        'display_name' => array_key_exists('display_name', $input) ? $input['display_name'] : $existing['display_name'],
        'subtitle' => array_key_exists('subtitle', $input) ? $input['subtitle'] : $existing['subtitle'],
        'detail_note' => array_key_exists('detail_note', $input) ? $input['detail_note'] : $existing['detail_note'],
        'apr_rate' => $input['apr_rate'] ?? $existing['apr_rate'],
        'term_days' => $input['term_days'] ?? $existing['term_days'],
        'min_subscribe_amount' => $input['min_subscribe_amount'] ?? $existing['min_subscribe_amount'],
        'personal_limit_amount' => $input['personal_limit_amount'] ?? $existing['personal_limit_amount'],
        'total_quota_amount' => $input['total_quota_amount'] ?? $existing['total_quota_amount'],
        'sort_order' => $input['sort_order'] ?? $existing['sort_order'],
        'default_return_mode' => $input['default_return_mode'] ?? $existing['default_return_mode'],
        'default_return_delay_days' => $input['default_return_delay_days'] ?? $existing['default_return_delay_days'],
        'status' => $input['status'] ?? $existing['status'],
    ], false);
    $exists = $pdo->prepare('SELECT id FROM financial_products WHERE product_code = :product_code AND id != :id LIMIT 1');
    $exists->execute([
        ':product_code' => $payload['product_code'],
        ':id' => $productId,
    ]);
    if ($exists->fetch()) {
        failure('AUTH_INVALID_PARAMS', 'Financial product code already exists');
    }
    $before = serialize_financial_product($pdo, 0, $existing);
    $updateStmt = $pdo->prepare('UPDATE financial_products SET
        product_code = :product_code,
        asset_code = :asset_code,
        wallet_code = :wallet_code,
        display_name = :display_name,
        subtitle = :subtitle,
        detail_note = :detail_note,
        apr_rate = :apr_rate,
        term_days = :term_days,
        min_subscribe_amount = :min_subscribe_amount,
        personal_limit_amount = :personal_limit_amount,
        total_quota_amount = :total_quota_amount,
        default_return_mode = :default_return_mode,
        default_return_delay_days = :default_return_delay_days,
        status = :status,
        sort_order = :sort_order,
        updated_at = :updated_at
        WHERE id = :id');
    $updateStmt->execute([
        ':product_code' => $payload['product_code'],
        ':asset_code' => $payload['asset_code'],
        ':wallet_code' => $payload['wallet_code'],
        ':display_name' => $payload['display_name'],
        ':subtitle' => $payload['subtitle'],
        ':detail_note' => $payload['detail_note'],
        ':apr_rate' => $payload['apr_rate'],
        ':term_days' => $payload['term_days'],
        ':min_subscribe_amount' => $payload['min_subscribe_amount'],
        ':personal_limit_amount' => $payload['personal_limit_amount'],
        ':total_quota_amount' => $payload['total_quota_amount'],
        ':default_return_mode' => $payload['default_return_mode'],
        ':default_return_delay_days' => $payload['default_return_delay_days'],
        ':status' => $payload['status'],
        ':sort_order' => $payload['sort_order'],
        ':updated_at' => now_iso(),
        ':id' => $productId,
    ]);
    $stmt = $pdo->prepare('SELECT * FROM financial_products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    $serialized = serialize_financial_product($pdo, 0, $product);
    audit($pdo, 'admin', 'financial_product_updated', 'admin', (int) $admin['admin_user_id'], 'financial_product', $productId, null, $before, $serialized);
    success('ADMIN_FINANCIAL_PRODUCT_UPDATED', 'ok', ['item' => $serialized]);
}

if ($path === '/api/admin/financial-subscriptions' && $method === 'GET') {
    process_due_financial_returns($pdo);
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $status = trim((string) ($_GET['status'] ?? ''));
    $returnModeRaw = trim((string) ($_GET['return_mode'] ?? ''));
    $userLookup = trim((string) ($_GET['user_id'] ?? ''));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));

    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 's.status = :status';
        $params[':status'] = $status;
    }
    if ($returnModeRaw !== '') {
        $where[] = 's.return_mode = :return_mode';
        $params[':return_mode'] = normalize_financial_return_mode($returnModeRaw);
    }
    apply_admin_lookup_filter($where, $params, $userLookup, ['s.user_id'], ['u.email', 'u.mobile_e164'], 'financial_user');
    if ($keyword !== '') {
        $where[] = '(u.username LIKE :keyword OR u.email LIKE :keyword OR s.product_code LIKE :keyword OR ' . db_identifier_cast_expr('s.id') . ' LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    if ($scope['sql'] !== '') {
        $where[] = $scope['sql'];
        $params = array_merge($params, $scope['params']);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*)
        FROM user_financial_subscriptions s
        JOIN users u ON u.id = s.user_id
        {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT s.*, u.username, u.email, u.mobile_e164
        FROM user_financial_subscriptions s
        JOIN users u ON u.id = s.user_id
        {$whereSql}
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $serialized = serialize_financial_subscription($item);
        $serialized['username'] = (string) ($item['username'] ?? '');
        $serialized['email'] = (string) ($item['email'] ?? '');
        $serialized['mobile_e164'] = (string) ($item['mobile_e164'] ?? '');
        return $serialized;
    }, $stmt->fetchAll());

    success('ADMIN_FINANCIAL_SUBSCRIPTIONS_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/financial-subscriptions/(\d+)$#', $path, $matches) && $method === 'GET') {
    process_due_financial_returns($pdo);
    $admin = require_admin($pdo);
    $subscriptionId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT s.*, u.username, u.email, u.mobile_e164, a.name AS returned_admin_name
        FROM user_financial_subscriptions s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN admin_users a ON a.id = s.returned_by_admin_id
        WHERE s.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $subscriptionId]);
    $subscription = $stmt->fetch();
    if (!$subscription) {
        failure('ADMIN_FINANCIAL_SUBSCRIPTION_NOT_FOUND', 'Financial subscription not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $subscription['user_id']);
    $payload = serialize_financial_subscription($subscription);
    $payload['username'] = (string) ($subscription['username'] ?? '');
    $payload['email'] = (string) ($subscription['email'] ?? '');
    $payload['mobile_e164'] = (string) ($subscription['mobile_e164'] ?? '');
    $payload['returned_admin_name'] = (string) ($subscription['returned_admin_name'] ?? '');
    success('ADMIN_FINANCIAL_SUBSCRIPTION_DETAIL_SUCCESS', 'ok', ['subscription' => $payload]);
}

if (preg_match('#^/api/admin/financial-subscriptions/(\d+)/return-policy$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $subscriptionId = (int) $matches[1];
    $returnMode = normalize_financial_return_mode($input['return_mode'] ?? 'manual');
    $returnDelayDays = max(0, (int) ($input['return_delay_days'] ?? 0));
    $reason = trim((string) ($input['reason'] ?? ''));

    $stmt = $pdo->prepare('SELECT * FROM user_financial_subscriptions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $subscriptionId]);
    $subscription = $stmt->fetch();
    if (!$subscription) {
        failure('ADMIN_FINANCIAL_SUBSCRIPTION_NOT_FOUND', 'Financial subscription not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $subscription['user_id']);
    if (($subscription['status'] ?? '') !== 'active') {
        failure('ADMIN_FINANCIAL_SUBSCRIPTION_STATUS_INVALID', 'Only active subscriptions can change return policy');
    }

    $returnScheduledAt = $returnMode === 'auto'
        ? financial_return_schedule((string) $subscription['maturity_at'], $returnDelayDays)
        : null;
    $now = now_iso();
    $pdo->prepare('UPDATE user_financial_subscriptions
        SET return_mode = :return_mode, return_delay_days = :return_delay_days, return_scheduled_at = :return_scheduled_at, updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':return_mode' => $returnMode,
            ':return_delay_days' => $returnDelayDays,
            ':return_scheduled_at' => $returnScheduledAt,
            ':updated_at' => $now,
            ':id' => $subscriptionId,
        ]);
    audit(
        $pdo,
        'admin',
        'financial_subscription_return_policy_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'financial_subscription',
        $subscriptionId,
        $reason !== '' ? $reason : null,
        [
            'return_mode' => normalize_financial_return_mode($subscription['return_mode'] ?? 'manual'),
            'return_delay_days' => (int) ($subscription['return_delay_days'] ?? 0),
            'return_scheduled_at' => $subscription['return_scheduled_at'] ?? null,
        ],
        [
            'return_mode' => $returnMode,
            'return_delay_days' => $returnDelayDays,
            'return_scheduled_at' => $returnScheduledAt,
        ]
    );
    success('ADMIN_FINANCIAL_RETURN_POLICY_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/financial-subscriptions/(\d+)/return$#', $path, $matches) && $method === 'POST') {
    $admin = require_admin($pdo);
    $subscriptionId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));

    $stmt = $pdo->prepare('SELECT * FROM user_financial_subscriptions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $subscriptionId]);
    $subscription = $stmt->fetch();
    if (!$subscription) {
        failure('ADMIN_FINANCIAL_SUBSCRIPTION_NOT_FOUND', 'Financial subscription not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $subscription['user_id']);
    if (($subscription['status'] ?? '') !== 'active') {
        failure('ADMIN_FINANCIAL_SUBSCRIPTION_STATUS_INVALID', 'Financial subscription already returned');
    }

    try {
        $pdo->beginTransaction();
        $result = settle_financial_subscription($pdo, $subscription, (int) $admin['admin_user_id']);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        failure('ADMIN_FINANCIAL_RETURN_FAILED', 'Financial return failed');
    }

    audit(
        $pdo,
        'admin',
        'financial_subscription_returned',
        'admin',
        (int) $admin['admin_user_id'],
        'financial_subscription',
        $subscriptionId,
        $reason !== '' ? $reason : null,
        ['status' => $subscription['status']],
        ['status' => 'settled', 'returned_total' => $result['returned_total']]
    );
    success('ADMIN_FINANCIAL_RETURNED', 'ok', $result);
}

if ($path === '/api/admin/kyc-applications' && $method === 'GET') {
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $status = trim((string) ($_GET['status'] ?? ''));
    $userLookup = trim((string) ($_GET['user_id'] ?? ''));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));

    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'k.status = :status';
        $params[':status'] = $status;
    }
    apply_admin_lookup_filter($where, $params, $userLookup, ['k.user_id'], ['u.email', 'u.mobile_e164'], 'kyc_user');
    if ($keyword !== '') {
        $where[] = '(u.username LIKE :keyword OR COALESCE(k.legal_name, "") LIKE :keyword OR COALESCE(k.id_number_masked, "") LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    if ($scope['sql'] !== '') {
        $where[] = $scope['sql'];
        $params = array_merge($params, $scope['params']);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_kyc_applications k JOIN users u ON u.id = k.user_id {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT k.id, k.user_id, u.username, u.email, u.mobile_e164, u.invitation_code, k.legal_name, k.id_number_masked, k.status, k.submitted_at, k.reviewed_at
        FROM user_kyc_applications k
        JOIN users u ON u.id = k.user_id
        {$whereSql}
        ORDER BY k.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['user_id'] = (int) $item['user_id'];
        return $item;
    }, $stmt->fetchAll());

    success('ADMIN_KYC_APPLICATIONS_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/kyc-applications/(\d+)$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $applicationId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT k.*, u.username, u.email, u.mobile_e164
        FROM user_kyc_applications k
        JOIN users u ON u.id = k.user_id
        WHERE k.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $applicationId]);
    $application = $stmt->fetch();
    if (!$application) {
        failure('ADMIN_KYC_APPLICATION_NOT_FOUND', 'KYC application not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $application['user_id']);
    $application['id'] = (int) $application['id'];
    $application['user_id'] = (int) $application['user_id'];
    $application['reviewed_by_admin_id'] = $application['reviewed_by_admin_id'] !== null ? (int) $application['reviewed_by_admin_id'] : null;
    success('ADMIN_KYC_APPLICATION_DETAIL_SUCCESS', 'ok', ['application' => $application]);
}

if (preg_match('#^/api/admin/kyc-applications/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $applicationId = (int) $matches[1];
    $action = trim((string) ($input['action'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    if (!in_array($action, ['approve', 'reject'], true)) {
        failure('ADMIN_KYC_ACTION_INVALID', 'KYC action is invalid');
    }
    if ($reason === '' && $action !== 'approve') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }

    $stmt = $pdo->prepare('SELECT * FROM user_kyc_applications WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $applicationId]);
    $application = $stmt->fetch();
    if (!$application) {
        failure('ADMIN_KYC_APPLICATION_NOT_FOUND', 'KYC application not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $application['user_id']);
    if (($application['status'] ?? '') !== 'pending') {
        failure('ADMIN_KYC_STATUS_INVALID', 'KYC application status is invalid');
    }

    $nextStatus = $action === 'approve' ? 'approved' : 'rejected';
    $reviewedAt = now_iso();
    $pdo->prepare('UPDATE user_kyc_applications
        SET status = :status, review_note = :review_note, reviewed_by_admin_id = :reviewed_by_admin_id, reviewed_at = :reviewed_at, updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':status' => $nextStatus,
            ':review_note' => $reason,
            ':reviewed_by_admin_id' => (int) $admin['admin_user_id'],
            ':reviewed_at' => $reviewedAt,
            ':updated_at' => $reviewedAt,
            ':id' => $applicationId,
        ]);
    $pdo->prepare('UPDATE user_tier_profiles SET is_verified = :is_verified, updated_at = :updated_at WHERE user_id = :user_id')
        ->execute([
            ':is_verified' => $action === 'approve' ? 1 : 0,
            ':updated_at' => $reviewedAt,
            ':user_id' => (int) $application['user_id'],
        ]);
    audit($pdo, 'admin', 'kyc_application_reviewed', 'admin', (int) $admin['admin_user_id'], 'kyc_application', $applicationId, $reason, ['status' => $application['status']], ['status' => $nextStatus, 'action' => $action]);
    success('ADMIN_KYC_APPLICATION_REVIEWED', 'ok', []);
}

if ($path === '/api/admin/payout-methods' && $method === 'GET') {
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $status = trim((string) ($_GET['status'] ?? ''));
    $userLookup = trim((string) ($_GET['user_id'] ?? ''));
    $channelType = trim((string) ($_GET['channel_type'] ?? ''));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));

    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'p.status = :status';
        $params[':status'] = $status;
    }
    apply_admin_lookup_filter($where, $params, $userLookup, ['p.user_id'], ['u.email', 'u.mobile_e164'], 'payout_user');
    if ($channelType !== '') {
        $where[] = 'p.channel_type = :channel_type';
        $params[':channel_type'] = $channelType;
    }
    if ($keyword !== '') {
        $where[] = '(u.username LIKE :keyword OR COALESCE(p.account_no_masked, "") LIKE :keyword OR COALESCE(p.payout_address, "") LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    if ($scope['sql'] !== '') {
        $where[] = $scope['sql'];
        $params = array_merge($params, $scope['params']);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_payout_methods p JOIN users u ON u.id = p.user_id {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT p.id, p.user_id, u.username, u.email, u.mobile_e164, u.invitation_code, p.channel_type, p.status, p.bank_name, p.account_no_masked, p.usdt_network, p.payout_address, p.is_default, p.created_at, p.reviewed_at
        FROM user_payout_methods p
        JOIN users u ON u.id = p.user_id
        {$whereSql}
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['user_id'] = (int) $item['user_id'];
        $item['is_default'] = (bool) $item['is_default'];
        return $item;
    }, $stmt->fetchAll());

    success('ADMIN_PAYOUT_METHODS_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/payout-methods/(\d+)$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $methodId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT p.*, u.username, u.email, u.mobile_e164
        FROM user_payout_methods p
        JOIN users u ON u.id = p.user_id
        WHERE p.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $methodId]);
    $methodItem = $stmt->fetch();
    if (!$methodItem) {
        failure('ADMIN_PAYOUT_METHOD_NOT_FOUND', 'Payout method not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $methodItem['user_id']);
    $methodItem['id'] = (int) $methodItem['id'];
    $methodItem['user_id'] = (int) $methodItem['user_id'];
    $methodItem['is_default'] = (bool) $methodItem['is_default'];
    $methodItem['reviewed_by_admin_id'] = $methodItem['reviewed_by_admin_id'] !== null ? (int) $methodItem['reviewed_by_admin_id'] : null;
    success('ADMIN_PAYOUT_METHOD_DETAIL_SUCCESS', 'ok', ['method' => $methodItem]);
}

if (preg_match('#^/api/admin/payout-methods/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $methodId = (int) $matches[1];
    $action = trim((string) ($input['action'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    if (!in_array($action, ['approve', 'reject', 'disable'], true)) {
        failure('ADMIN_PAYOUT_METHOD_ACTION_INVALID', 'Payout method action is invalid');
    }
    if ($reason === '') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }

    $stmt = $pdo->prepare('SELECT * FROM user_payout_methods WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $methodId]);
    $methodItem = $stmt->fetch();
    if (!$methodItem) {
        failure('ADMIN_PAYOUT_METHOD_NOT_FOUND', 'Payout method not found');
    }
    require_admin_can_access_user($pdo, $admin, (int) $methodItem['user_id']);

    $nextStatus = match ($action) {
        'approve' => 'approved',
        'reject' => 'rejected',
        default => 'disabled',
    };
    $reviewedAt = now_iso();
    $pdo->prepare('UPDATE user_payout_methods
        SET status = :status, review_note = :review_note, reviewed_by_admin_id = :reviewed_by_admin_id, reviewed_at = :reviewed_at, updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            ':status' => $nextStatus,
            ':review_note' => $reason,
            ':reviewed_by_admin_id' => (int) $admin['admin_user_id'],
            ':reviewed_at' => $reviewedAt,
            ':updated_at' => $reviewedAt,
            ':id' => $methodId,
        ]);
    audit($pdo, 'admin', 'payout_method_updated', 'admin', (int) $admin['admin_user_id'], 'payout_method', $methodId, $reason, ['status' => $methodItem['status']], ['status' => $nextStatus, 'action' => $action]);
    success('ADMIN_PAYOUT_METHOD_UPDATED', 'ok', []);
}

if ($path === '/api/admin/listings' && $method === 'GET') {
    $admin = require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $status = trim((string) ($_GET['status'] ?? ''));
    $side = trim((string) ($_GET['side'] ?? ''));
    $ownerLookup = trim((string) ($_GET['owner_user_id'] ?? ''));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));

    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'l.status = :status';
        $params[':status'] = $status;
    }
    if ($side !== '') {
        $where[] = 'l.side = :side';
        $params[':side'] = $side;
    }
    apply_admin_lookup_filter($where, $params, $ownerLookup, ['l.owner_user_id'], ['u.email', 'u.mobile_e164'], 'listing_owner');
    if ($keyword !== '') {
        $where[] = '(l.nickname LIKE :keyword OR COALESCE(u.username, "") LIKE :keyword OR l.asset_code LIKE :keyword OR l.fiat_code LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    if ($scope['sql'] !== '') {
        $where[] = $scope['sql'];
        $params = array_merge($params, $scope['params']);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM c2c_listings l LEFT JOIN users u ON u.id = l.owner_user_id {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT l.id, l.owner_user_id, u.username AS owner_username, u.email AS owner_email, u.mobile_e164 AS owner_mobile_e164, u.invited_by_admin_id AS owner_invited_by_admin_id, l.nickname, l.side, l.asset_code, l.fiat_code, l.price,
            l.min_amount, l.max_amount, l.available_amount, l.payment_method_summary, l.completion_rate,
            l.badge_vip, l.badge_pro, l.badge_stars, l.status, l.updated_at
        FROM c2c_listings l
        LEFT JOIN users u ON u.id = l.owner_user_id
        {$whereSql}
        ORDER BY l.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['owner_user_id'] = (int) $item['owner_user_id'];
        $item['owner_source'] = admin_user_source_label($item['owner_invited_by_admin_id'] ?? null, (int) $item['owner_user_id']);
        $item['completion_rate'] = normalize_listing_completion_rate($item['completion_rate'] ?? null);
        unset($item['owner_invited_by_admin_id']);
        return attach_listing_badge_fields($item);
    }, $stmt->fetchAll());

    success('ADMIN_LISTINGS_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if ($path === '/api/admin/listings' && $method === 'POST') {
    $admin = require_admin($pdo);
    $ownerUserId = (int) ($input['owner_user_id'] ?? 0);
    $nickname = trim((string) ($input['nickname'] ?? ''));
    $side = trim((string) ($input['side'] ?? ''));
    $assetCode = 'USDT';
    $fiatCode = 'EUR';
    $price = trim((string) ($input['price'] ?? ''));
    $minAmount = trim((string) ($input['min_amount'] ?? ''));
    $maxAmount = trim((string) ($input['max_amount'] ?? ''));
    $availableAmount = trim((string) ($input['available_amount'] ?? ''));
    $paymentMethodSummary = trim((string) ($input['payment_method_summary'] ?? ''));
    $completionRate = normalize_listing_completion_rate($input['completion_rate'] ?? '');
    $badgeVip = normalize_listing_badge_int($input['badge_vip'] ?? 0);
    $badgePro = normalize_listing_badge_int($input['badge_pro'] ?? 0);
    $badgeStars = normalize_listing_badge_int($input['badge_stars'] ?? 0);
    $status = trim((string) ($input['status'] ?? 'active'));
    if ($nickname === '' || !in_array($side, ['buy', 'sell'], true) || !is_numeric($price) || !is_numeric($minAmount) || !is_numeric($maxAmount) || !is_numeric($availableAmount)) {
        failure('ADMIN_INVALID_PARAMS', 'Invalid listing params');
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        failure('ADMIN_INVALID_PARAMS', 'Invalid listing status');
    }
    if ($ownerUserId > 0) {
        require_user_exists($pdo, $ownerUserId);
        require_admin_can_access_user($pdo, $admin, $ownerUserId);
    }
    $now = now_iso();
    $pdo->prepare('INSERT INTO c2c_listings (
        owner_user_id, nickname, side, asset_code, fiat_code, price, min_amount, max_amount, available_amount,
        payment_method_summary, completion_rate, badge_vip, badge_pro, badge_stars, status, created_at, updated_at
    ) VALUES (
        :owner_user_id, :nickname, :side, :asset_code, :fiat_code, :price, :min_amount, :max_amount, :available_amount,
        :payment_method_summary, :completion_rate, :badge_vip, :badge_pro, :badge_stars, :status, :created_at, :updated_at
    )')->execute([
        ':owner_user_id' => $ownerUserId > 0 ? $ownerUserId : 0,
        ':nickname' => $nickname,
        ':side' => $side,
        ':asset_code' => $assetCode,
        ':fiat_code' => $fiatCode,
        ':price' => number_format((float) $price, 8, '.', ''),
        ':min_amount' => number_format((float) $minAmount, 8, '.', ''),
        ':max_amount' => number_format((float) $maxAmount, 8, '.', ''),
        ':available_amount' => number_format((float) $availableAmount, 8, '.', ''),
        ':payment_method_summary' => $side === 'buy' && $paymentMethodSummary !== '' ? $paymentMethodSummary : null,
        ':completion_rate' => $completionRate,
        ':badge_vip' => $badgeVip,
        ':badge_pro' => $badgePro,
        ':badge_stars' => $badgeStars,
        ':status' => $status,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $listingId = (int) $pdo->lastInsertId();
    audit($pdo, 'admin', 'listing_created', 'admin', (int) $admin['admin_user_id'], 'listing', $listingId, null, null, ['owner_user_id' => $ownerUserId, 'side' => $side, 'status' => $status]);
    success('ADMIN_LISTING_CREATED', 'ok', ['listing_id' => $listingId]);
}

if (preg_match('#^/api/admin/listings/(\d+)$#', $path, $matches) && $method === 'GET') {
    $admin = require_admin($pdo);
    $listingId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT l.*, u.username AS owner_username, u.email AS owner_email, u.mobile_e164 AS owner_mobile_e164, u.invited_by_admin_id AS owner_invited_by_admin_id
        FROM c2c_listings l
        LEFT JOIN users u ON u.id = l.owner_user_id
        WHERE l.id = :id
        LIMIT 1');
    $stmt->execute([':id' => $listingId]);
    $listing = $stmt->fetch();
    if (!$listing) {
        failure('ADMIN_LISTING_NOT_FOUND', 'Listing not found');
    }
    if ((int) $listing['owner_user_id'] > 0) {
        require_admin_can_access_user($pdo, $admin, (int) $listing['owner_user_id']);
    }
    $listing['id'] = (int) $listing['id'];
    $listing['owner_user_id'] = (int) $listing['owner_user_id'];
    $listing['owner_source'] = admin_user_source_label($listing['owner_invited_by_admin_id'] ?? null, (int) $listing['owner_user_id']);
    $listing['completion_rate'] = normalize_listing_completion_rate($listing['completion_rate'] ?? null);
    unset($listing['owner_invited_by_admin_id']);
    $listing = attach_listing_badge_fields($listing);
    success('ADMIN_LISTING_DETAIL_SUCCESS', 'ok', ['listing' => $listing]);
}

if (preg_match('#^/api/admin/listings/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $listingId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT * FROM c2c_listings WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $listingId]);
    $listing = $stmt->fetch();
    if (!$listing) {
        failure('ADMIN_LISTING_NOT_FOUND', 'Listing not found');
    }
    if ((int) $listing['owner_user_id'] > 0) {
        require_admin_can_access_user($pdo, $admin, (int) $listing['owner_user_id']);
    }
    $next = [
        'owner_user_id' => array_key_exists('owner_user_id', $input) ? (int) $input['owner_user_id'] : (int) $listing['owner_user_id'],
        'nickname' => array_key_exists('nickname', $input) ? trim((string) $input['nickname']) : (string) $listing['nickname'],
        'side' => array_key_exists('side', $input) ? trim((string) $input['side']) : (string) $listing['side'],
        'asset_code' => 'USDT',
        'fiat_code' => 'EUR',
        'price' => array_key_exists('price', $input) ? number_format((float) $input['price'], 8, '.', '') : (string) $listing['price'],
        'min_amount' => array_key_exists('min_amount', $input) ? number_format((float) $input['min_amount'], 8, '.', '') : (string) $listing['min_amount'],
        'max_amount' => array_key_exists('max_amount', $input) ? number_format((float) $input['max_amount'], 8, '.', '') : (string) $listing['max_amount'],
        'available_amount' => array_key_exists('available_amount', $input) ? number_format((float) $input['available_amount'], 8, '.', '') : (string) $listing['available_amount'],
        'payment_method_summary' => array_key_exists('payment_method_summary', $input) ? trim((string) $input['payment_method_summary']) : (string) ($listing['payment_method_summary'] ?? ''),
        'completion_rate' => array_key_exists('completion_rate', $input)
            ? normalize_listing_completion_rate($input['completion_rate'] ?? '')
            : normalize_listing_completion_rate($listing['completion_rate'] ?? ''),
        'badge_vip' => array_key_exists('badge_vip', $input) ? normalize_listing_badge_int($input['badge_vip']) : normalize_listing_badge_int($listing['badge_vip'] ?? 0),
        'badge_pro' => array_key_exists('badge_pro', $input) ? normalize_listing_badge_int($input['badge_pro']) : normalize_listing_badge_int($listing['badge_pro'] ?? 0),
        'badge_stars' => array_key_exists('badge_stars', $input) ? normalize_listing_badge_int($input['badge_stars']) : normalize_listing_badge_int($listing['badge_stars'] ?? 0),
        'status' => array_key_exists('status', $input) ? trim((string) $input['status']) : (string) $listing['status'],
    ];
    if ($next['nickname'] === '' || !in_array($next['side'], ['buy', 'sell'], true) || !in_array($next['status'], ['active', 'inactive'], true)) {
        failure('ADMIN_INVALID_PARAMS', 'Invalid listing params');
    }
    if ($next['owner_user_id'] > 0) {
        require_user_exists($pdo, $next['owner_user_id']);
        require_admin_can_access_user($pdo, $admin, $next['owner_user_id']);
    }
    $pdo->prepare('UPDATE c2c_listings
        SET owner_user_id = :owner_user_id, nickname = :nickname, side = :side, asset_code = :asset_code, fiat_code = :fiat_code,
            price = :price, min_amount = :min_amount, max_amount = :max_amount, available_amount = :available_amount,
            payment_method_summary = :payment_method_summary, completion_rate = :completion_rate,
            badge_vip = :badge_vip, badge_pro = :badge_pro, badge_stars = :badge_stars,
            status = :status, updated_at = :updated_at
        WHERE id = :id')->execute([
        ':owner_user_id' => $next['owner_user_id'] > 0 ? $next['owner_user_id'] : 0,
        ':nickname' => $next['nickname'],
        ':side' => $next['side'],
        ':asset_code' => $next['asset_code'],
        ':fiat_code' => $next['fiat_code'],
        ':price' => $next['price'],
        ':min_amount' => $next['min_amount'],
        ':max_amount' => $next['max_amount'],
        ':available_amount' => $next['available_amount'],
        ':payment_method_summary' => $next['side'] === 'buy' && $next['payment_method_summary'] !== '' ? $next['payment_method_summary'] : null,
        ':completion_rate' => $next['completion_rate'],
        ':badge_vip' => $next['badge_vip'],
        ':badge_pro' => $next['badge_pro'],
        ':badge_stars' => $next['badge_stars'],
        ':status' => $next['status'],
        ':updated_at' => now_iso(),
        ':id' => $listingId,
    ]);
    audit($pdo, 'admin', 'listing_updated', 'admin', (int) $admin['admin_user_id'], 'listing', $listingId, null, ['status' => $listing['status']], ['status' => $next['status'], 'side' => $next['side']]);
    success('ADMIN_LISTING_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/listings/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $admin = require_admin($pdo);
    $listingId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));
    $stmt = $pdo->prepare('SELECT id, owner_user_id, status FROM c2c_listings WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $listingId]);
    $listing = $stmt->fetch();
    if (!$listing) {
        failure('ADMIN_LISTING_NOT_FOUND', 'Listing not found');
    }
    if ((int) $listing['owner_user_id'] > 0) {
        require_admin_can_access_user($pdo, $admin, (int) $listing['owner_user_id']);
    }
    $pdo->prepare('DELETE FROM c2c_listings WHERE id = :id')->execute([':id' => $listingId]);
    audit($pdo, 'admin', 'listing_deleted', 'admin', (int) $admin['admin_user_id'], 'listing', $listingId, $reason !== '' ? $reason : null, ['status' => $listing['status']], ['deleted' => true]);
    success('ADMIN_LISTING_DELETED', 'ok', []);
}

if ($path === '/api/admin/home-content' && $method === 'GET') {
    require_admin($pdo);
    success('ADMIN_HOME_CONTENT_SUCCESS', 'ok', [
        'notice' => get_system_config($pdo, 'trade', 'hall_notice', ''),
        'banners' => home_banner_list($pdo),
        'tutorial_links' => home_tutorial_link_list($pdo),
    ]);
}

if ($path === '/api/admin/home-content/notice' && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }
    $notice = trim((string) ($input['notice'] ?? ''));
    $beforeValue = get_system_config($pdo, 'trade', 'hall_notice', '');
    set_system_config($pdo, 'trade', 'hall_notice', $notice);
    audit(
        $pdo,
        'admin',
        'home_notice_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'system_config',
        null,
        $reason,
        ['notice' => $beforeValue],
        ['notice' => $notice]
    );
    success('ADMIN_HOME_NOTICE_UPDATED', 'Home notice updated', ['notice' => $notice]);
}

if ($path === '/api/admin/home-content/banners' && $method === 'GET') {
    require_admin($pdo);
    success('ADMIN_HOME_BANNERS_SUCCESS', 'ok', ['items' => home_banner_list($pdo)]);
}

if ($path === '/api/admin/home-content/banners' && $method === 'POST') {
    $admin = require_admin($pdo);
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }
    $title = trim((string) ($input['title'] ?? ''));
    $imageUrl = trim((string) ($input['image_url'] ?? ''));
    if ($title === '' || $imageUrl === '') {
        failure('ADMIN_INVALID_PARAMS', 'Title and image are required');
    }
    $items = home_banner_list($pdo);
    $nextId = 1;
    foreach ($items as $item) {
        $nextId = max($nextId, (int) $item['id'] + 1);
    }
    $banner = [
        'id' => $nextId,
        'title' => $title,
        'subtitle' => trim((string) ($input['subtitle'] ?? '')),
        'badge_text' => trim((string) ($input['badge_text'] ?? '')),
        'image_url' => $imageUrl,
        'link_url' => trim((string) ($input['link_url'] ?? '')),
        'sort_order' => (int) ($input['sort_order'] ?? 0),
        'status' => normalize_home_banner_status($input['status'] ?? 'active'),
        'created_at' => now_iso(),
        'updated_at' => now_iso(),
    ];
    $items[] = $banner;
    save_home_banner_list($pdo, $items);
    audit(
        $pdo,
        'admin',
        'home_banner_created',
        'admin',
        (int) $admin['admin_user_id'],
        'home_banner',
        $nextId,
        $reason,
        null,
        $banner
    );
    success('ADMIN_HOME_BANNER_CREATED', 'Home banner created', ['banner_id' => $nextId]);
}

if (preg_match('#^/api/admin/home-content/banners/(\d+)$#', $path, $matches) && $method === 'GET') {
    require_admin($pdo);
    $bannerId = (int) $matches[1];
    $banner = null;
    foreach (home_banner_list($pdo) as $item) {
        if ((int) $item['id'] === $bannerId) {
            $banner = $item;
            break;
        }
    }
    if (!$banner) {
        failure('ADMIN_HOME_BANNER_NOT_FOUND', 'Home banner not found');
    }
    success('ADMIN_HOME_BANNER_DETAIL_SUCCESS', 'ok', ['banner' => $banner]);
}

if (preg_match('#^/api/admin/home-content/banners/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $bannerId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }
    $items = home_banner_list($pdo);
    $index = null;
    foreach ($items as $key => $item) {
        if ((int) $item['id'] === $bannerId) {
            $index = $key;
            break;
        }
    }
    if ($index === null) {
        failure('ADMIN_HOME_BANNER_NOT_FOUND', 'Home banner not found');
    }
    $before = $items[$index];
    $next = [
        'id' => $bannerId,
        'title' => trim((string) ($input['title'] ?? $before['title'])),
        'subtitle' => trim((string) ($input['subtitle'] ?? $before['subtitle'])),
        'badge_text' => trim((string) ($input['badge_text'] ?? $before['badge_text'])),
        'image_url' => trim((string) ($input['image_url'] ?? $before['image_url'])),
        'link_url' => trim((string) ($input['link_url'] ?? $before['link_url'])),
        'sort_order' => array_key_exists('sort_order', $input) ? (int) $input['sort_order'] : (int) $before['sort_order'],
        'status' => normalize_home_banner_status($input['status'] ?? $before['status']),
        'created_at' => $before['created_at'],
        'updated_at' => now_iso(),
    ];
    if ($next['title'] === '' || $next['image_url'] === '') {
        failure('ADMIN_INVALID_PARAMS', 'Title and image are required');
    }
    $items[$index] = $next;
    save_home_banner_list($pdo, $items);
    audit(
        $pdo,
        'admin',
        'home_banner_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'home_banner',
        $bannerId,
        $reason,
        $before,
        $next
    );
    success('ADMIN_HOME_BANNER_UPDATED', 'Home banner updated', ['banner_id' => $bannerId]);
}

if (preg_match('#^/api/admin/home-content/banners/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $admin = require_admin($pdo);
    $bannerId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));
    $items = home_banner_list($pdo);
    $deleted = null;
    $nextItems = [];
    foreach ($items as $item) {
        if ((int) $item['id'] === $bannerId) {
            $deleted = $item;
            continue;
        }
        $nextItems[] = $item;
    }
    if (!$deleted) {
        failure('ADMIN_HOME_BANNER_NOT_FOUND', 'Home banner not found');
    }
    save_home_banner_list($pdo, $nextItems);
    audit(
        $pdo,
        'admin',
        'home_banner_deleted',
        'admin',
        (int) $admin['admin_user_id'],
        'home_banner',
        $bannerId,
        $reason !== '' ? $reason : null,
        $deleted,
        null
    );
    success('ADMIN_HOME_BANNER_DELETED', 'Home banner deleted', ['banner_id' => $bannerId]);
}

if ($path === '/api/admin/home-content/tutorial-links' && $method === 'POST') {
    $admin = require_admin($pdo);
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }
    $title = trim((string) ($input['title'] ?? ''));
    $linkUrl = trim((string) ($input['link_url'] ?? ''));
    if ($title === '' || $linkUrl === '') {
        failure('ADMIN_INVALID_PARAMS', 'Title and link are required');
    }
    $items = home_tutorial_link_list($pdo);
    $nextId = 1;
    foreach ($items as $item) {
        $nextId = max($nextId, (int) $item['id'] + 1);
    }
    $now = now_iso();
    $row = [
        'id' => $nextId,
        'title' => $title,
        'subtitle' => trim((string) ($input['subtitle'] ?? '')),
        'link_url' => $linkUrl,
        'sort_order' => (int) ($input['sort_order'] ?? 0),
        'status' => normalize_home_tutorial_link_status($input['status'] ?? 'active'),
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $items[] = $row;
    save_home_tutorial_link_list($pdo, $items);
    audit(
        $pdo,
        'admin',
        'home_tutorial_link_created',
        'admin',
        (int) $admin['admin_user_id'],
        'tutorial_link',
        $nextId,
        $reason,
        null,
        $row
    );
    success('ADMIN_HOME_TUTORIAL_LINK_CREATED', 'Tutorial link created', ['id' => $nextId]);
}

if (preg_match('#^/api/admin/home-content/tutorial-links/(\d+)$#', $path, $matches) && $method === 'GET') {
    require_admin($pdo);
    $linkId = (int) $matches[1];
    $found = null;
    foreach (home_tutorial_link_list($pdo) as $item) {
        if ((int) $item['id'] === $linkId) {
            $found = $item;
            break;
        }
    }
    if (!$found) {
        failure('ADMIN_HOME_TUTORIAL_LINK_NOT_FOUND', 'Tutorial link not found');
    }
    success('ADMIN_HOME_TUTORIAL_LINK_DETAIL_SUCCESS', 'ok', ['item' => $found]);
}

if (preg_match('#^/api/admin/home-content/tutorial-links/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $linkId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }
    $items = home_tutorial_link_list($pdo);
    $index = null;
    foreach ($items as $key => $item) {
        if ((int) $item['id'] === $linkId) {
            $index = $key;
            break;
        }
    }
    if ($index === null) {
        failure('ADMIN_HOME_TUTORIAL_LINK_NOT_FOUND', 'Tutorial link not found');
    }
    $before = $items[$index];
    $next = [
        'id' => $linkId,
        'title' => trim((string) ($input['title'] ?? $before['title'])),
        'subtitle' => trim((string) ($input['subtitle'] ?? $before['subtitle'])),
        'link_url' => trim((string) ($input['link_url'] ?? $before['link_url'])),
        'sort_order' => array_key_exists('sort_order', $input) ? (int) $input['sort_order'] : (int) $before['sort_order'],
        'status' => normalize_home_tutorial_link_status($input['status'] ?? $before['status']),
        'created_at' => $before['created_at'],
        'updated_at' => now_iso(),
    ];
    if ($next['title'] === '' || $next['link_url'] === '') {
        failure('ADMIN_INVALID_PARAMS', 'Title and link are required');
    }
    $items[$index] = $next;
    save_home_tutorial_link_list($pdo, $items);
    audit(
        $pdo,
        'admin',
        'home_tutorial_link_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'tutorial_link',
        $linkId,
        $reason,
        $before,
        $next
    );
    success('ADMIN_HOME_TUTORIAL_LINK_UPDATED', 'Tutorial link updated', ['id' => $linkId]);
}

if (preg_match('#^/api/admin/home-content/tutorial-links/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $admin = require_admin($pdo);
    $linkId = (int) $matches[1];
    $reason = trim((string) ($input['reason'] ?? ''));
    $items = home_tutorial_link_list($pdo);
    $deleted = null;
    $nextItems = [];
    foreach ($items as $item) {
        if ((int) $item['id'] === $linkId) {
            $deleted = $item;
            continue;
        }
        $nextItems[] = $item;
    }
    if (!$deleted) {
        failure('ADMIN_HOME_TUTORIAL_LINK_NOT_FOUND', 'Tutorial link not found');
    }
    save_home_tutorial_link_list($pdo, $nextItems);
    audit(
        $pdo,
        'admin',
        'home_tutorial_link_deleted',
        'admin',
        (int) $admin['admin_user_id'],
        'tutorial_link',
        $linkId,
        $reason !== '' ? $reason : null,
        $deleted,
        null
    );
    success('ADMIN_HOME_TUTORIAL_LINK_DELETED', 'Tutorial link deleted', ['id' => $linkId]);
}

if ($path === '/api/admin/trade-feed-events' && $method === 'GET') {
    require_admin($pdo);
    $status = trim((string) ($_GET['status'] ?? ''));
    $keyword = trim((string) ($_GET['keyword'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 25)));
    $offset = ($page - 1) * $pageSize;
    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }
    if ($keyword !== '') {
        $where[] = '(title LIKE :keyword OR actor_name LIKE :keyword OR asset_code LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM trade_feed_events {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT id, action_type, title, actor_name, asset_code, amount, occurred_at, sort_order, status, updated_at
        FROM trade_feed_events
        {$whereSql}
        ORDER BY sort_order ASC, id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['sort_order'] = (int) $item['sort_order'];
        $item['action_type'] = normalize_trade_feed_action_type($item['action_type'] ?? '');
        $item['title_display'] = trade_feed_display_title($item, 'hkg');
        return $item;
    }, $stmt->fetchAll());
    success('ADMIN_TRADE_FEED_SUCCESS', 'ok', ['items' => $items, 'pagination' => ['page' => $page, 'page_size' => $pageSize, 'total' => $total]]);
}

if ($path === '/api/admin/trade-feed-events' && $method === 'POST') {
    $admin = require_admin($pdo);
    $actionType = normalize_trade_feed_action_type($input['action_type'] ?? 'custom');
    $title = trim((string) ($input['title'] ?? ''));
    $actorName = trim((string) ($input['actor_name'] ?? ''));
    $assetCode = strtoupper(trim((string) ($input['asset_code'] ?? 'USDT')));
    $amount = trim((string) ($input['amount'] ?? ''));
    $occurredAt = trim((string) ($input['occurred_at'] ?? ''));
    if ($occurredAt === '') {
        $occurredAt = now_iso();
    }
    $sortOrder = (int) ($input['sort_order'] ?? 0);
    $status = trim((string) ($input['status'] ?? 'active'));
    if ($actionType !== 'custom') {
        $title = trade_feed_action_default_title($actionType);
    }
    if ($title === '' || $actorName === '' || !is_numeric($amount) || !in_array($status, ['active', 'inactive'], true)) {
        failure('ADMIN_INVALID_PARAMS', 'Invalid trade feed params');
    }
    $now = now_iso();
    $pdo->prepare('INSERT INTO trade_feed_events (
        action_type, title, actor_name, asset_code, amount, occurred_at, sort_order, status, created_at, updated_at
    ) VALUES (
        :action_type, :title, :actor_name, :asset_code, :amount, :occurred_at, :sort_order, :status, :created_at, :updated_at
    )')->execute([
        ':action_type' => $actionType,
        ':title' => $title,
        ':actor_name' => $actorName,
        ':asset_code' => $assetCode,
        ':amount' => number_format((float) $amount, 8, '.', ''),
        ':occurred_at' => $occurredAt,
        ':sort_order' => $sortOrder,
        ':status' => $status,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $eventId = (int) $pdo->lastInsertId();
    audit($pdo, 'admin', 'trade_feed_created', 'admin', (int) $admin['admin_user_id'], 'trade_feed_event', $eventId);
    success('ADMIN_TRADE_FEED_CREATED', 'ok', ['event_id' => $eventId]);
}

if ($path === '/api/admin/trade-feed-events/random' && $method === 'POST') {
    $admin = require_admin($pdo);
    $transfer = fetch_random_recent_usdt_trc20_transfer();
    if (!$transfer) {
        failure('ADMIN_TRADE_FEED_RANDOM_SOURCE_EMPTY', 'No USDT TRC20 transfer with amount >= 100 in the recent time window');
    }

    $requestedActionType = normalize_trade_feed_action_type($input['action_type'] ?? '');
    $actionType = in_array($requestedActionType, ['completed_buy', 'completed_sell'], true)
        ? $requestedActionType
        : (random_int(0, 1) === 1 ? 'completed_buy' : 'completed_sell');
    $title = trade_feed_action_default_title($actionType);
    $actorName = random_trade_feed_email();
    $now = now_iso();
    $pdo->prepare('INSERT INTO trade_feed_events (
        action_type, title, actor_name, asset_code, amount, occurred_at, sort_order, status, created_at, updated_at
    ) VALUES (
        :action_type, :title, :actor_name, :asset_code, :amount, :occurred_at, :sort_order, :status, :created_at, :updated_at
    )')->execute([
        ':action_type' => $actionType,
        ':title' => $title,
        ':actor_name' => $actorName,
        ':asset_code' => 'USDT',
        ':amount' => number_format((float) $transfer['amount'], 8, '.', ''),
        ':occurred_at' => (string) $transfer['occurred_at'],
        ':sort_order' => 0,
        ':status' => 'active',
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $eventId = (int) $pdo->lastInsertId();
    audit(
        $pdo,
        'admin',
        'trade_feed_random_created',
        'admin',
        (int) $admin['admin_user_id'],
        'trade_feed_event',
        $eventId,
        (string) ($transfer['txid'] ?? '')
    );
    success('ADMIN_TRADE_FEED_RANDOM_CREATED', 'ok', [
        'event_id' => $eventId,
        'actor_name' => $actorName,
        'amount' => number_format((float) $transfer['amount'], 2, '.', ''),
        'occurred_at' => (string) $transfer['occurred_at'],
    ]);
}

if (preg_match('#^/api/admin/trade-feed-events/(\d+)$#', $path, $matches) && $method === 'GET') {
    require_admin($pdo);
    $eventId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT * FROM trade_feed_events WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $eventId]);
    $eventItem = $stmt->fetch();
    if (!$eventItem) {
        failure('ADMIN_TRADE_FEED_NOT_FOUND', 'Trade feed event not found');
    }
    $eventItem['id'] = (int) $eventItem['id'];
    $eventItem['sort_order'] = (int) $eventItem['sort_order'];
    $eventItem['action_type'] = normalize_trade_feed_action_type($eventItem['action_type'] ?? '');
    $eventItem['title_display'] = trade_feed_display_title($eventItem, 'hkg');
    success('ADMIN_TRADE_FEED_DETAIL_SUCCESS', 'ok', ['event' => $eventItem]);
}

if (preg_match('#^/api/admin/trade-feed-events/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $eventId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT * FROM trade_feed_events WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $eventId]);
    $eventItem = $stmt->fetch();
    if (!$eventItem) {
        failure('ADMIN_TRADE_FEED_NOT_FOUND', 'Trade feed event not found');
    }
    $next = [
        'action_type' => array_key_exists('action_type', $input) ? normalize_trade_feed_action_type($input['action_type']) : normalize_trade_feed_action_type($eventItem['action_type'] ?? ''),
        'title' => array_key_exists('title', $input) ? trim((string) $input['title']) : (string) $eventItem['title'],
        'actor_name' => array_key_exists('actor_name', $input) ? trim((string) $input['actor_name']) : (string) $eventItem['actor_name'],
        'asset_code' => array_key_exists('asset_code', $input) ? strtoupper(trim((string) $input['asset_code'])) : (string) $eventItem['asset_code'],
        'amount' => array_key_exists('amount', $input) ? number_format((float) $input['amount'], 8, '.', '') : (string) $eventItem['amount'],
        'occurred_at' => array_key_exists('occurred_at', $input) ? trim((string) $input['occurred_at']) : (string) $eventItem['occurred_at'],
        'sort_order' => array_key_exists('sort_order', $input) ? (int) $input['sort_order'] : (int) $eventItem['sort_order'],
        'status' => array_key_exists('status', $input) ? trim((string) $input['status']) : (string) $eventItem['status'],
    ];
    if ($next['action_type'] !== 'custom') {
        $next['title'] = trade_feed_action_default_title($next['action_type']);
    }
    if ($next['title'] === '' || $next['actor_name'] === '' || !in_array($next['status'], ['active', 'inactive'], true)) {
        failure('ADMIN_INVALID_PARAMS', 'Invalid trade feed params');
    }
    $pdo->prepare('UPDATE trade_feed_events
        SET action_type = :action_type, title = :title, actor_name = :actor_name, asset_code = :asset_code, amount = :amount, occurred_at = :occurred_at,
            sort_order = :sort_order, status = :status, updated_at = :updated_at
        WHERE id = :id')->execute([
        ':action_type' => $next['action_type'],
        ':title' => $next['title'],
        ':actor_name' => $next['actor_name'],
        ':asset_code' => $next['asset_code'],
        ':amount' => $next['amount'],
        ':occurred_at' => $next['occurred_at'],
        ':sort_order' => $next['sort_order'],
        ':status' => $next['status'],
        ':updated_at' => now_iso(),
        ':id' => $eventId,
    ]);
    audit($pdo, 'admin', 'trade_feed_updated', 'admin', (int) $admin['admin_user_id'], 'trade_feed_event', $eventId);
    success('ADMIN_TRADE_FEED_UPDATED', 'ok', []);
}

if (preg_match('#^/api/admin/trade-feed-events/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $admin = require_admin($pdo);
    $eventId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT id FROM trade_feed_events WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $eventId]);
    if (!$stmt->fetch()) {
        failure('ADMIN_TRADE_FEED_NOT_FOUND', 'Trade feed event not found');
    }
    $pdo->prepare('DELETE FROM trade_feed_events WHERE id = :id')->execute([':id' => $eventId]);
    audit($pdo, 'admin', 'trade_feed_deleted', 'admin', (int) $admin['admin_user_id'], 'trade_feed_event', $eventId);
    success('ADMIN_TRADE_FEED_DELETED', 'ok', []);
}

if ($path === '/api/admin/dashboard-summary' && $method === 'GET') {
    $admin = require_admin($pdo);
    $tz = new DateTimeZone('Asia/Taipei');
    $today = (new DateTime('today', $tz))->format('Y-m-d');
    $startYmd = trim((string) ($_GET['start'] ?? ''));
    $endYmd = trim((string) ($_GET['end'] ?? ''));
    if ($startYmd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startYmd)) {
        $startYmd = $today;
    }
    if ($endYmd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endYmd)) {
        $endYmd = $today;
    }
    if ($startYmd > $endYmd) {
        $tmp = $startYmd;
        $startYmd = $endYmd;
        $endYmd = $tmp;
    }
    $range = admin_dashboard_day_range_utc($startYmd, $endYmd);
    $dtTodayAnchor = DateTime::createFromFormat('Y-m-d', $today, $tz) ?: new DateTime('today', $tz);
    $yesterday = (clone $dtTodayAnchor)->modify('-1 day')->format('Y-m-d');
    $todayRange = admin_dashboard_day_range_utc($today, $today);
    $yesterdayRange = admin_dashboard_day_range_utc($yesterday, $yesterday);
    $eurPerUsdt = (float) get_system_config($pdo, 'finance', 'usdt_to_eur_rate', '0.96');

    $pendingDeposits = admin_dashboard_count_with_user_scope(
        $pdo,
        $admin,
        'SELECT COUNT(*) FROM deposit_requests d INNER JOIN users u ON u.id = d.user_id WHERE d.status = "pending"',
        []
    );
    $pendingWithdrawals = admin_dashboard_count_with_user_scope(
        $pdo,
        $admin,
        'SELECT COUNT(*) FROM withdrawal_requests w INNER JOIN users u ON u.id = w.user_id WHERE w.status = "pending"',
        []
    );
    $pendingKyc = admin_dashboard_count_with_user_scope(
        $pdo,
        $admin,
        'SELECT COUNT(*) FROM user_kyc_applications k INNER JOIN users u ON u.id = k.user_id WHERE k.status = "pending"',
        []
    );
    $tradingOrders = admin_dashboard_trading_orders_count($pdo, $admin);

    $depNewToday = admin_dashboard_count_with_user_scope(
        $pdo,
        $admin,
        'SELECT COUNT(*) FROM deposit_requests d INNER JOIN users u ON u.id = d.user_id WHERE d.status = "pending" AND d.created_at >= :rs AND d.created_at <= :re',
        [':rs' => $todayRange['start_utc'], ':re' => $todayRange['end_utc']]
    );
    $depNewYesterday = admin_dashboard_count_with_user_scope(
        $pdo,
        $admin,
        'SELECT COUNT(*) FROM deposit_requests d INNER JOIN users u ON u.id = d.user_id WHERE d.status = "pending" AND d.created_at >= :rs AND d.created_at <= :re',
        [':rs' => $yesterdayRange['start_utc'], ':re' => $yesterdayRange['end_utc']]
    );
    $wdNewToday = admin_dashboard_count_with_user_scope(
        $pdo,
        $admin,
        'SELECT COUNT(*) FROM withdrawal_requests w INNER JOIN users u ON u.id = w.user_id WHERE w.status = "pending" AND w.created_at >= :rs AND w.created_at <= :re',
        [':rs' => $todayRange['start_utc'], ':re' => $todayRange['end_utc']]
    );
    $wdNewYesterday = admin_dashboard_count_with_user_scope(
        $pdo,
        $admin,
        'SELECT COUNT(*) FROM withdrawal_requests w INNER JOIN users u ON u.id = w.user_id WHERE w.status = "pending" AND w.created_at >= :rs AND w.created_at <= :re',
        [':rs' => $yesterdayRange['start_utc'], ':re' => $yesterdayRange['end_utc']]
    );

    $totalDepositUsdt = admin_dashboard_sum_approved_usdt_equiv(
        $pdo,
        $admin,
        'deposit_requests',
        $range['start_utc'],
        $range['end_utc'],
        $eurPerUsdt
    );
    $totalWithdrawUsdt = admin_dashboard_sum_approved_usdt_equiv(
        $pdo,
        $admin,
        'withdrawal_requests',
        $range['start_utc'],
        $range['end_utc'],
        $eurPerUsdt
    );

    success('ADMIN_DASHBOARD_SUMMARY_SUCCESS', 'ok', [
        'range' => [
            'start' => $startYmd,
            'end' => $endYmd,
            'start_utc' => $range['start_utc'],
            'end_utc' => $range['end_utc'],
            'timezone' => 'Asia/Taipei',
        ],
        'kpi' => [
            'pending_deposits' => $pendingDeposits,
            'pending_withdrawals' => $pendingWithdrawals,
            'trading_orders' => $tradingOrders,
            'pending_kyc' => $pendingKyc,
            'deposit_pending_new_today' => $depNewToday,
            'deposit_pending_new_yesterday' => $depNewYesterday,
            'withdrawal_pending_new_today' => $wdNewToday,
            'withdrawal_pending_new_yesterday' => $wdNewYesterday,
        ],
        'fund_totals' => [
            'deposit_usdt_equiv' => $totalDepositUsdt,
            'withdraw_usdt_equiv' => $totalWithdrawUsdt,
        ],
    ]);
}

if ($path === '/api/admin/auth/events' && $method === 'GET') {
    require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $account = trim((string) ($_GET['account'] ?? ''));
    $userLookup = trim((string) ($_GET['user_id'] ?? ''));
    $action = trim((string) ($_GET['action'] ?? ''));
    $errorCode = trim((string) ($_GET['error_code'] ?? ''));
    $ip = trim((string) ($_GET['ip'] ?? ''));

    $where = ['category = "auth"'];
    $params = [];
    if ($account !== '') {
        $where[] = 'account LIKE :account';
        $params[':account'] = '%' . $account . '%';
    }
    if ($userLookup !== '') {
        $resolvedUserId = find_user_id_by_admin_lookup($pdo, $userLookup);
        if ($resolvedUserId === null) {
            success('ADMIN_AUTH_EVENTS_SUCCESS', 'ok', [
                'items' => [],
                'pagination' => [
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total' => 0,
                ],
            ]);
        }
        $where[] = 'target_id = :target_id';
        $params[':target_id'] = $resolvedUserId;
    }
    if ($action !== '') {
        $where[] = 'action = :action';
        $params[':action'] = $action;
    }
    if ($errorCode !== '') {
        $where[] = 'error_code = :error_code';
        $params[':error_code'] = $errorCode;
    }
    if ($ip !== '') {
        $where[] = 'ip LIKE :ip';
        $params[':ip'] = '%' . $ip . '%';
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT id, action, account, target_id AS user_id, ip, error_code, msg, payload_json, created_at
        FROM audit_logs
        {$whereSql}
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();
    success('ADMIN_AUTH_EVENTS_LIST_SUCCESS', 'ok', [
        'items' => array_map(static function (array $item): array {
            $payload = $item['payload_json'] ? json_decode((string) $item['payload_json'], true) : [];
            $item['id'] = (int) $item['id'];
            $item['user_id'] = $item['user_id'] !== null ? (int) $item['user_id'] : null;
            $item['account'] = $item['account'] ?: ($payload['account'] ?? $payload['target'] ?? null);
            $item['ip'] = $item['ip'] ?: ($payload['ip'] ?? null);
            $item['msg'] = $item['msg'] ?: ($payload['msg'] ?? null);
            unset($item['payload_json']);
            return $item;
        }, $items),
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/auth/events/(\d+)$#', $path, $matches) && $method === 'GET') {
    require_admin($pdo);
    $eventId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT id, action, account, target_id AS user_id, ip, error_code, msg, payload_json, created_at
        FROM audit_logs
        WHERE id = :id AND category = "auth"
        LIMIT 1');
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        failure('ADMIN_AUTH_EVENT_NOT_FOUND', 'Auth event not found');
    }
    $payload = $event['payload_json'] ? json_decode((string) $event['payload_json'], true) : null;
    $event['id'] = (int) $event['id'];
    $event['user_id'] = $event['user_id'] !== null ? (int) $event['user_id'] : null;
    $event['account'] = $event['account'] ?: ($payload['account'] ?? $payload['target'] ?? null);
    $event['ip'] = $event['ip'] ?: ($payload['ip'] ?? null);
    $event['msg'] = $event['msg'] ?: ($payload['msg'] ?? null);
    $event['payload'] = $payload;
    unset($event['payload_json']);
    success('ADMIN_AUTH_EVENT_DETAIL_SUCCESS', 'ok', ['event' => $event]);
}

if ($path === '/api/admin/deposit-addresses' && $method === 'GET') {
    $admin = require_admin($pdo);
    $keyword = trim((string) ($_GET['keyword'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(200, max(1, (int) ($_GET['page_size'] ?? 50)));
    $offset = ($page - 1) * $pageSize;

    $where = ['u.id > 0'];
    $params = [];
    if ($keyword !== '') {
        $kwLower = '%' . mb_strtolower($keyword) . '%';
        $kwAddr = '%' . $keyword . '%';
        $kwParts = [
            'LOWER(COALESCE(u.username, "")) LIKE :da_kw_lower',
            'LOWER(COALESCE(u.email, "")) LIKE :da_kw_lower',
            'LOWER(COALESCE(u.mobile_e164, "")) LIKE :da_kw_lower',
        ];
        $params[':da_kw_lower'] = $kwLower;
        if (preg_match('/^\d+$/', $keyword)) {
            $kwParts[] = 'u.id = :da_kw_id';
            $params[':da_kw_id'] = (int) $keyword;
        }
        $kwParts[] = 'EXISTS (SELECT 1 FROM deposit_addresses dak WHERE dak.user_id = u.id AND dak.status = "enabled" AND dak.address LIKE :da_kw_addr)';
        $params[':da_kw_addr'] = $kwAddr;
        $where[] = '(' . implode(' OR ', $kwParts) . ')';
    }
    $scope = admin_user_scope_sql($pdo, $admin, 'u');
    if ($scope['sql'] !== '') {
        $where[] = $scope['sql'];
        $params = array_merge($params, $scope['params']);
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $fromSql = 'users u
        LEFT JOIN deposit_addresses da ON da.user_id = u.id AND da.asset_code = "USDT" AND da.status = "enabled"';

    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT u.id) FROM {$fromSql} {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT
            u.id AS user_id,
            u.username,
            u.email,
            u.mobile_e164,
            MAX(CASE WHEN da.network_code = 'TRC20' THEN da.address END) AS trc20_address,
            MAX(CASE WHEN da.network_code = 'ERC20' THEN da.address END) AS erc20_address,
            MAX(CASE WHEN da.network_code = 'BEP20' THEN da.address END) AS bep20_address,
            MIN(da.created_at) AS created_at,
            MAX(da.updated_at) AS updated_at
        FROM {$fromSql}
        {$whereSql}
        GROUP BY u.id, u.username, u.email, u.mobile_e164
        ORDER BY u.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item) use ($pdo): array {
        $trc = trim((string) ($item['trc20_address'] ?? ''));
        $erc = trim((string) ($item['erc20_address'] ?? ''));
        $bep = trim((string) ($item['bep20_address'] ?? ''));
        $createdAt = $item['created_at'] ?? null;
        $updatedAt = $item['updated_at'] ?? null;

        return build_user_deposit_address_item($pdo, (int) $item['user_id'], [
            ['network_code' => 'TRC20', 'address' => $trc, 'created_at' => $createdAt, 'updated_at' => $updatedAt],
            ['network_code' => 'ERC20', 'address' => $erc, 'created_at' => $createdAt, 'updated_at' => $updatedAt],
            ['network_code' => 'BEP20', 'address' => $bep, 'created_at' => $createdAt, 'updated_at' => $updatedAt],
        ]);
    }, $stmt->fetchAll());
    $items = array_values(array_filter($items));
    success('ADMIN_DEPOSIT_ADDRESSES_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ],
    ]);
}

if (preg_match('#^/api/admin/deposit-addresses/(\d+)$#', $path, $matches) && $method === 'GET') {
    require_admin($pdo);
    $userId = (int) $matches[1];
    $item = build_user_deposit_address_item($pdo, $userId);
    if (!$item) {
        failure('ADMIN_DEPOSIT_ADDRESS_NOT_FOUND', 'Deposit address not found');
    }
    success('ADMIN_DEPOSIT_ADDRESS_SUCCESS', 'ok', ['item' => $item]);
}

if ($path === '/api/admin/deposit-addresses' && $method === 'POST') {
    $admin = require_admin($pdo);
    $payload = sanitize_user_deposit_address_payload($input);
    $item = save_user_deposit_addresses($pdo, $payload['user_id'], $payload['addresses'], (int) $admin['admin_user_id']);
    success('ADMIN_DEPOSIT_ADDRESS_CREATED', 'Deposit address created', ['item' => $item]);
}

if (preg_match('#^/api/admin/deposit-addresses/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    if (!build_user_deposit_address_item($pdo, $userId)) {
        failure('ADMIN_DEPOSIT_ADDRESS_NOT_FOUND', 'Deposit address not found');
    }
    $payload = sanitize_user_deposit_address_payload(array_merge($input, ['user_id' => $userId]));
    $item = save_user_deposit_addresses($pdo, $payload['user_id'], $payload['addresses'], (int) $admin['admin_user_id']);
    success('ADMIN_DEPOSIT_ADDRESS_UPDATED', 'Deposit address updated', ['item' => $item]);
}

if (preg_match('#^/api/admin/deposit-addresses/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $admin = require_admin($pdo);
    $userId = (int) $matches[1];
    $before = build_user_deposit_address_item($pdo, $userId);
    if (!$before) {
        failure('ADMIN_DEPOSIT_ADDRESS_NOT_FOUND', 'Deposit address not found');
    }
    $pdo->prepare('DELETE FROM deposit_addresses WHERE user_id = :user_id')->execute([':user_id' => $userId]);
    audit(
        $pdo,
        'admin',
        'deposit_address_deleted',
        'admin',
        (int) $admin['admin_user_id'],
        'deposit_address_user',
        $userId,
        null,
        $before,
        null
    );

    success('ADMIN_DEPOSIT_ADDRESS_DELETED', 'Deposit address deleted', []);
}

if ($path === '/api/admin/withdrawal-settings/eur-swap' && $method === 'GET') {
    require_admin($pdo);
    success('ADMIN_EUR_SWAP_WITHDRAW_SETTINGS_SUCCESS', 'ok', [
        'item' => [
            'rate' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_rate', get_system_config($pdo, 'finance', 'usdt_to_eur_rate', '0.96000000')),
            'fee_mode' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_mode', 'percent'),
            'fee_rate' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_rate', '0.00'),
            'fee_fixed_usdt' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_fixed_usdt', '0.00'),
        ],
    ]);
}

if ($path === '/api/admin/withdrawal-settings/eur-swap/history' && $method === 'GET') {
    require_admin($pdo);
    $stmt = $pdo->prepare('SELECT id, operator_type, operator_id, reason, before_json, after_json, created_at
        FROM audit_logs
        WHERE target_type = "withdrawal_settings"
          AND action IN ("eur_swap_withdraw_settings_updated", "eur_swap_withdraw_settings_copied")
        ORDER BY id DESC
        LIMIT 30');
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['operator_id'] = $item['operator_id'] !== null ? (int) $item['operator_id'] : null;
        $item['before'] = $item['before_json'] ? json_decode((string) $item['before_json'], true) : null;
        $item['after'] = $item['after_json'] ? json_decode((string) $item['after_json'], true) : null;
        unset($item['before_json'], $item['after_json']);
        return $item;
    }, $stmt->fetchAll());
    success('ADMIN_EUR_SWAP_WITHDRAW_SETTINGS_HISTORY_SUCCESS', 'ok', ['items' => $items]);
}

if ($path === '/api/admin/withdrawal-settings/eur-swap/copy' && $method === 'POST') {
    $admin = require_admin($pdo);
    $reason = trim((string) ($input['reason'] ?? ''));
    $item = [
        'rate' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_rate', get_system_config($pdo, 'finance', 'usdt_to_eur_rate', '0.96000000')),
        'fee_mode' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_mode', 'percent'),
        'fee_rate' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_rate', '0.00'),
        'fee_fixed_usdt' => get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_fixed_usdt', '0.00'),
    ];
    audit(
        $pdo,
        'admin',
        'eur_swap_withdraw_settings_copied',
        'admin',
        (int) $admin['admin_user_id'],
        'withdrawal_settings',
        0,
        $reason !== '' ? $reason : 'copy current EUR swap withdrawal rule',
        null,
        $item
    );
    success('ADMIN_EUR_SWAP_WITHDRAW_SETTINGS_COPIED', 'ok', ['item' => $item]);
}

if ($path === '/api/admin/withdrawal-settings/eur-swap' && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $reason = trim((string) ($input['reason'] ?? ''));
    $rate = array_key_exists('rate', $input) ? trim((string) $input['rate']) : '';
    $feeMode = array_key_exists('fee_mode', $input) ? trim((string) $input['fee_mode']) : 'percent';
    $feeRate = array_key_exists('fee_rate', $input) ? trim((string) $input['fee_rate']) : '';
    $feeFixedUsdt = array_key_exists('fee_fixed_usdt', $input) ? trim((string) $input['fee_fixed_usdt']) : '';
    if ($rate === '' || !is_numeric($rate) || (float) $rate <= 0) {
        failure('ADMIN_SYSTEM_CONFIG_VALUE_INVALID', 'Rate is invalid');
    }
    if (!in_array($feeMode, ['percent', 'fixed_usdt'], true)) {
        failure('ADMIN_SYSTEM_CONFIG_VALUE_INVALID', 'Fee mode is invalid');
    }
    if ($feeRate === '' || !is_numeric($feeRate) || (float) $feeRate < 0) {
        failure('ADMIN_SYSTEM_CONFIG_VALUE_INVALID', 'Fee rate is invalid');
    }
    if ($feeFixedUsdt === '' || !is_numeric($feeFixedUsdt) || (float) $feeFixedUsdt < 0) {
        failure('ADMIN_SYSTEM_CONFIG_VALUE_INVALID', 'Fixed fee is invalid');
    }

    $beforeRate = get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_rate', get_system_config($pdo, 'finance', 'usdt_to_eur_rate', '0.96000000'));
    $beforeFeeMode = get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_mode', 'percent');
    $beforeFeeRate = get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_rate', '0.00');
    $beforeFeeFixedUsdt = get_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_fixed_usdt', '0.00');
    set_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_rate', number_format((float) $rate, 8, '.', ''));
    set_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_mode', $feeMode);
    set_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_rate', number_format((float) $feeRate, 4, '.', ''));
    set_system_config($pdo, 'finance', 'eur_to_usdt_withdraw_fee_fixed_usdt', number_format((float) $feeFixedUsdt, 8, '.', ''));
    audit(
        $pdo,
        'admin',
        'eur_swap_withdraw_settings_updated',
        'admin',
        (int) $admin['admin_user_id'],
        'withdrawal_settings',
        0,
        $reason,
        ['rate' => $beforeRate, 'fee_mode' => $beforeFeeMode, 'fee_rate' => $beforeFeeRate, 'fee_fixed_usdt' => $beforeFeeFixedUsdt],
        ['rate' => number_format((float) $rate, 8, '.', ''), 'fee_mode' => $feeMode, 'fee_rate' => number_format((float) $feeRate, 4, '.', ''), 'fee_fixed_usdt' => number_format((float) $feeFixedUsdt, 8, '.', '')]
    );
    success('ADMIN_EUR_SWAP_WITHDRAW_SETTINGS_UPDATED', 'ok', []);
}

if ($path === '/api/admin/support-tickets' && $method === 'GET') {
    require_admin($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int) ($_GET['page_size'] ?? 20)));
    $status = trim((string) ($_GET['status'] ?? ''));
    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 's.status = :status';
        $params[':status'] = $status;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets s {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT s.*, u.username, u.email, u.mobile_e164
        FROM support_tickets s
        LEFT JOIN users u ON u.id = s.user_id
        {$whereSql}
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        $item['user_id'] = $item['user_id'] !== null ? (int) $item['user_id'] : null;
        $item['assigned_admin_id'] = $item['assigned_admin_id'] !== null ? (int) $item['assigned_admin_id'] : null;
        return $item;
    }, $stmt->fetchAll());
    success('ADMIN_SUPPORT_TICKETS_SUCCESS', 'ok', [
        'items' => $items,
        'pagination' => ['page' => $page, 'page_size' => $pageSize, 'total' => $total],
    ]);
}

if (preg_match('#^/api/admin/support-tickets/(\d+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $id = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $before = $stmt->fetch();
    if (!$before) {
        failure('ADMIN_SUPPORT_TICKET_NOT_FOUND', 'Support ticket not found');
    }
    $status = array_key_exists('status', $input) ? trim((string) $input['status']) : (string) $before['status'];
    if (!in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
        failure('ADMIN_SUPPORT_TICKET_STATUS_INVALID', 'Support ticket status is invalid');
    }
    $priority = array_key_exists('priority', $input) ? trim((string) $input['priority']) : (string) $before['priority'];
    if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
        failure('ADMIN_SUPPORT_TICKET_PRIORITY_INVALID', 'Support ticket priority is invalid');
    }
    $adminNote = array_key_exists('admin_note', $input) ? trim((string) $input['admin_note']) : $before['admin_note'];
    $pdo->prepare('UPDATE support_tickets
        SET status = :status, priority = :priority, assigned_admin_id = :assigned_admin_id, admin_note = :admin_note, updated_at = :updated_at
        WHERE id = :id')->execute([
            ':status' => $status,
            ':priority' => $priority,
            ':assigned_admin_id' => (int) $admin['admin_user_id'],
            ':admin_note' => $adminNote !== '' ? $adminNote : null,
            ':updated_at' => now_iso(),
            ':id' => $id,
        ]);
    audit($pdo, 'admin', 'support_ticket_updated', 'admin', (int) $admin['admin_user_id'], 'support_ticket', $id, trim((string) ($input['reason'] ?? '')) ?: null, $before, [
        'status' => $status,
        'priority' => $priority,
        'admin_note' => $adminNote,
    ]);
    success('ADMIN_SUPPORT_TICKET_UPDATED', 'ok', []);
}

if ($path === '/api/admin/system/configs' && $method === 'GET') {
    require_admin($pdo);
    $group = trim((string) ($_GET['group'] ?? ''));
    $whereParts = ['NOT (config_group = "finance" AND config_key IN ("usdt_trc20_deposit_address", "usdt_trc20_network_label", "eur_to_usdt_withdraw_rate", "eur_to_usdt_withdraw_fee_mode", "eur_to_usdt_withdraw_fee_rate", "eur_to_usdt_withdraw_fee_fixed_usdt"))'];
    $params = [];
    if ($group !== '') {
        $whereParts[] = 'config_group = :config_group';
        $params[':config_group'] = $group;
    }
    $where = 'WHERE ' . implode(' AND ', $whereParts);
    $stmt = $pdo->prepare("SELECT config_group, config_key, config_value FROM system_configs {$where} ORDER BY config_group ASC, config_key ASC");
    $stmt->execute($params);
    $items = array_map(static function (array $item): array {
        $decoded = json_decode((string) $item['config_value'], true);
        return [
            'group' => $item['config_group'],
            'key' => $item['config_key'],
            'value' => $decoded !== null || $item['config_value'] === 'null'
                ? $decoded
                : match ($item['config_value']) {
                    'true' => true,
                    'false' => false,
                    default => is_numeric($item['config_value']) ? (str_contains((string) $item['config_value'], '.') ? (float) $item['config_value'] : (int) $item['config_value']) : $item['config_value'],
                },
        ];
    }, $stmt->fetchAll());
    success('ADMIN_SYSTEM_CONFIGS_SUCCESS', 'ok', ['items' => $items]);
}

if (preg_match('#^/api/admin/system/configs/([A-Za-z0-9_\-]+)/([A-Za-z0-9_\-]+)$#', $path, $matches) && $method === 'PATCH') {
    $admin = require_admin($pdo);
    $group = $matches[1];
    $key = $matches[2];
    if (is_deprecated_system_config_key($group, $key)) {
        failure('ADMIN_SYSTEM_CONFIG_NOT_FOUND', 'System config not found');
    }
    if (is_usdt_platform_deposit_system_config($group, $key)) {
        require_root_admin($admin);
    }
    $reason = trim((string) ($input['reason'] ?? ''));
    if ($reason === '') {
        failure('ADMIN_REASON_REQUIRED', 'Reason is required');
    }
    if (!array_key_exists('value', $input)) {
        failure('ADMIN_SYSTEM_CONFIG_VALUE_INVALID', 'Config value is invalid');
    }

    $stmt = $pdo->prepare('SELECT * FROM system_configs WHERE config_group = :config_group AND config_key = :config_key LIMIT 1');
    $stmt->execute([
        ':config_group' => $group,
        ':config_key' => $key,
    ]);
    $config = $stmt->fetch();
    if (!$config) {
        failure('ADMIN_SYSTEM_CONFIG_NOT_FOUND', 'System config not found');
    }

    $value = normalize_admin_system_config_value($group, $key, $input['value']);
    $encodedValue = is_bool($value) || is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value;
    $syncedFiles = [];
    $fileBackups = [];
    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE system_configs SET config_value = :config_value, updated_at = :updated_at
            WHERE config_group = :config_group AND config_key = :config_key')
            ->execute([
                ':config_value' => $encodedValue,
                ':updated_at' => now_iso(),
                ':config_group' => $group,
                ':config_key' => $key,
            ]);
        $syncedFiles = sync_system_config_to_files($group, $key, $config['config_value'], $value, $fileBackups);
        audit(
            $pdo,
            'admin',
            'system_config_updated',
            'admin',
            (int) $admin['admin_user_id'],
            'system_config',
            (int) $config['id'],
            $reason,
            ['value' => $config['config_value']],
            ['value' => $encodedValue, 'group' => $group, 'key' => $key, 'synced_files' => $syncedFiles]
        );
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($fileBackups) {
            try {
                restore_config_file_backups($fileBackups);
            } catch (Throwable $restoreException) {
                failure('ADMIN_SYSTEM_CONFIG_SYNC_FAILED', 'System config sync failed: ' . $exception->getMessage() . '; rollback failed: ' . $restoreException->getMessage());
            }
        }
        failure('ADMIN_SYSTEM_CONFIG_SYNC_FAILED', 'System config sync failed: ' . $exception->getMessage());
    }
    success('ADMIN_SYSTEM_CONFIG_UPDATED', 'ok', ['synced_files' => $syncedFiles]);
}

failure('ADMIN_RESOURCE_NOT_FOUND', 'Route not found', null, 404);
