<?php
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function asset(string $path): string
{
    $config = get_config();
    $base = rtrim($config['app']['base_url'] ?? '/', '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash(): array
{
    if (!isset($_SESSION['flash'])) {
        return [];
    }
    $messages = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $messages;
}

function ensure_csrf_token(): void
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function check_csrf_token(?string $token): bool
{
    ensure_csrf_token();
    return hash_equals($_SESSION['csrf_token'], (string) $token);
}

function get_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    if (!file_exists(__DIR__ . '/../config.php')) {
        return [];
    }

    $config = require __DIR__ . '/../config.php';
    return $config;
}

// Date helpers based on algorithms published by Kazimierz M. Borkowski and the GNU libc implementation.
function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = $gm > 2 ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * (int)($days / 12053));
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (int)($jy / 33) * 8 + (int)(((($jy % 33) + 3) / 4)) + $jd;
    if ($jm < 7) {
        $days += ($jm - 1) * 31;
    } else {
        $days += (($jm - 7) * 30) + 186;
    }
    $gy = 400 * (int)($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * (int)(--$days / 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0, 31, ((($gy % 4) === 0 && ($gy % 100) !== 0) || ($gy % 400) === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30];
    $gm = 0;
    for ($i = 0; $i < 13 && $gd > $sal_a[$i]; $i++) {
        $gd -= $sal_a[$i];
        $gm = $i + 1;
    }
    return [$gy, $gm, $gd];
}

function jalali_to_gregorian_date(?string $jalaliDate): ?string
{
    if (!$jalaliDate) {
        return null;
    }
    $parts = preg_split('/[\\s\-\/\\.]+/', trim($jalaliDate));
    if (count($parts) !== 3) {
        return null;
    }
    [$jy, $jm, $jd] = array_map('intval', $parts);
    if ($jy < 1000) {
        return null;
    }
    [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

function gregorian_to_jalali_date(?string $gregorianDate): ?string
{
    if (!$gregorianDate) {
        return null;
    }
    [$gy, $gm, $gd] = array_map('intval', explode('-', $gregorianDate));
    [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
    return sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
}

function format_number(?float $value): string
{
    if ($value === null) {
        return '';
    }
    return number_format($value, 2, '.', ',');
}

function current_url(array $params = []): string
{
    $query = array_merge($_GET, $params);
    return '?' . http_build_query($query);
}
