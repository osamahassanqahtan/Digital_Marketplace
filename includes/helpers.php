<?php
/**
 * includes/helpers.php
 * دوال مساعدة عامة تستخدم في جميع أنحاء التطبيق
 */

/**
 * تنظيف المدخلات من HTML والعلامات الضارة
 * @param string $data
 * @return string
 */
function sanitizeInput(string $data): string {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * التحقق من صحة البريد الإلكتروني
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * إنشاء slug من النص (للروابط)
 * @param string $text
 * @return string
 */
function createSlug(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    // استبدال الأحرف العربية المشبعة
    $text = str_replace(
        ['أ','إ','آ','ة',' ','_'],
        ['ا','ا','ا','ه','-','-'],
        $text
    );
    // إزالة كل ما ليس حرفاً أو رقماً أو شرطة
    $text = preg_replace('/[^a-z0-9\-]/u', '', $text);
    // إزالة الشرطات المتكررة
    $text = preg_replace('/\-+/', '-', $text);
    return trim($text, '-');
}

/**
 * عرض رسالة نجاح أو خطأ (للمستخدم)
 * @param string $message
 * @param string $type success أو error أو warning أو info
 * @return string
 */
function showMessage(string $message, string $type = 'success'): string {
    $class = 'alert-' . $type;
    return "<div class='alert $class alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
}

/**
 * التحقق من أن السعر رقم موجب
 * @param mixed $price
 * @return bool
 */
function isValidPrice($price): bool {
    return is_numeric($price) && $price >= 0;
}

/**
 * اختصار النص لعدد محدد من الكلمات
 * @param string $text
 * @param int $limit
 * @return string
 */
function truncateText(string $text, int $limit = 20): string {
    $words = explode(' ', $text);
    if (count($words) <= $limit) return $text;
    return implode(' ', array_slice($words, 0, $limit)) . '...';
}

/**
 * تنسيق السعر مع العملة
 * @param float $price
 * @return string
 */
function formatPrice(float $price): string {
    return number_format($price, 2) . ' ريال';
}

/**
 * التحقق من أن رقم الهاتف صالح (مثال مبسط)
 * @param string $phone
 * @return bool
 */
function isValidPhone(string $phone): bool {
    return preg_match('/^[0-9+\-\(\)\s]{8,20}$/', $phone) === 1;
}