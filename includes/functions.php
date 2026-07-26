<?php
/**
 * includes/functions.php
 * دوال عامة إضافية للمشروع - مكملة لـ helpers.php
 * تم إزالة دوال الرفع (uploadFile, deleteFile, etc.) لأنها موجودة في upload.php
 */

/**
 * توليد سلسلة عشوائية
 * @param int $length الطول المطلوب
 * @return string
 */
function generateRandomString(int $length = 10): string {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * تنسيق التاريخ والوقت
 * @param string $date التاريخ (Y-m-d H:i:s)
 * @param string $format صيغة العرض (افتراضي: d/m/Y H:i)
 * @return string
 */
function formatDate(string $date, string $format = 'd/m/Y H:i'): string {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * الحصول على عنوان IP الخاص بالمستخدم
 * @return string
 */
function getClientIP(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * التحقق مما إذا كان الطلب عبر AJAX
 * @return bool
 */
function isAjaxRequest(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * إعادة توجيه آمنة
 * @param string $url
 * @param int $statusCode
 */
function redirect(string $url, int $statusCode = 302): void {
    if (!headers_sent()) {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
    echo "<script>window.location.href='$url';</script>";
    exit;
}

/**
 * تعيين رسالة فلاش (تظهر مرة واحدة)
 * @param string $key
 * @param string $message
 * @param string $type (success, danger, warning, info)
 */
function setFlashMessage(string $key, string $message, string $type = 'success'): void {
    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * الحصول على رسالة فلاش وحذفها
 * @param string $key
 * @return array|null ['message' => string, 'type' => string]
 */
function getFlashMessage(string $key): ?array {
    if (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }
    return null;
}

/**
 * عرض رسالة فلاش بشكل HTML
 * @param string $key
 * @return string
 */
function displayFlashMessage(string $key): string {
    $flash = getFlashMessage($key);
    if (!$flash) return '';
    return "<div class='alert alert-{$flash['type']} alert-dismissible fade show' role='alert'>
                {$flash['message']}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

/**
 * التحقق من أن القيمة فارغة (أكثر أماناً)
 * @param mixed $value
 * @return bool
 */
function isNullOrEmpty($value): bool {
    if (is_null($value)) return true;
    if (is_string($value)) return trim($value) === '';
    if (is_array($value)) return empty($value);
    return false;
}

/**
 * تنظيف النص من HTML و JavaScript
 * @param string $text
 * @return string
 */
function cleanText(string $text): string {
    return strip_tags(htmlspecialchars_decode($text));
}

/**
 * إنشاء نسخة مختصرة من النص مع الاحتفاظ بالكلمات كاملة
 * @param string $text
 * @param int $limit
 * @param string $end
 * @return string
 */
function truncateWords(string $text, int $limit = 20, string $end = '...'): string {
    $words = explode(' ', strip_tags($text));
    if (count($words) <= $limit) return $text;
    return implode(' ', array_slice($words, 0, $limit)) . $end;
}

/**
 * التحقق من أن التاريخ صحيح
 * @param string $date
 * @param string $format
 * @return bool
 */
function isValidDate(string $date, string $format = 'Y-m-d'): bool {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * الحصول على اسم المستخدم الحالي أو "زائر"
 * @return string
 */
function getCurrentUserNameOrGuest(): string {
    if (isLoggedIn()) {
        return getCurrentUserName();
    }
    return 'زائر';
}

/**
 * التحقق من أن المستخدم لديه صلاحية معينة وإلا إرجاع false
 * @param string|array $roles
 * @return bool
 */
function userHasAccess($roles): bool {
    return hasRole($roles);
}

/**
 * تسجيل خطأ في ملف السجلات (بدائي)
 * @param string $message
 * @param string $level
 */
function logError(string $message, string $level = 'ERROR'): void {
    $logDir = __DIR__ . '/../logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . 'error.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

/**
 * الحصول على معلمات GET بشكل آمن مع قيمة افتراضية
 * @param string $key
 * @param mixed $default
 * @param string $filter
 * @return mixed
 */
function getParam(string $key, $default = null, string $filter = 'string') {
    $value = $_GET[$key] ?? $default;
    switch ($filter) {
        case 'int':
            return filter_var($value, FILTER_VALIDATE_INT) ?: $default;
        case 'float':
            return filter_var($value, FILTER_VALIDATE_FLOAT) ?: $default;
        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) ?: $default;
        case 'string':
        default:
            return sanitizeInput($value);
    }
}