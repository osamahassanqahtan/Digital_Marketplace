<?php
/**
 * includes/session.php
 * إدارة الجلسات والتحقق من المستخدمين
 * يجب استدعاؤه في بداية كل صفحة تحتاج إلى جلسة
 */

// بدء الجلسة إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * التحقق مما إذا كان المستخدم مسجلاً دخوله
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * الحصول على معرف المستخدم الحالي
 * @return int|null
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * الحصول على دور المستخدم الحالي
 * @return string|null
 */
function getCurrentUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * الحصول على اسم المستخدم الحالي
 * @return string|null
 */
function getCurrentUserName(): ?string {
    return $_SESSION['user_name'] ?? null;
}

/**
 * التحقق من أن المستخدم مسجل وله دور معين
 * @param string|array $roles دور واحد أو قائمة أدوار مسموحة
 * @return bool
 */
function hasRole($roles): bool {
    if (!isLoggedIn()) return false;
    $userRole = getCurrentUserRole();
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    return $userRole === $roles;
}

/**
 * تسجيل دخول المستخدم - حفظ بيانات الجلسة
 * @param array $userData يجب أن يحتوي على id, name, role
 */
function loginUser(array $userData): void {
    $_SESSION['user_id']   = (int)$userData['id'];
    $_SESSION['user_name'] = $userData['name'];
    $_SESSION['user_role'] = $userData['role'];
    $_SESSION['user_email'] = $userData['email'] ?? null;
    // تجديد معرف الجلسة لمنع هجمات fixation
    session_regenerate_id(true);
}

/**
 * تسجيل الخروج - تدمير الجلسة
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * التحقق من صلاحية الدخول لصفحة معينة - إن لم يكن مصرحاً به، يعيد التوجيه
 * @param string|array $roles الأدوار المسموحة
 * @param string $redirectUrl رابط إعادة التوجيه في حال الفشل
 */
function requireRole($roles, string $redirectUrl = 'index.php'): void {
    if (!hasRole($roles)) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * التحقق من تسجيل الدخول - إن لم يكن مسجلاً، يعيد التوجيه
 * @param string $redirectUrl
 */
function requireLogin(string $redirectUrl = 'login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}