<?php
/**
 * includes/auth.php
 * ملف المصادقة والتحقق من المستخدمين
 * يحتوي على دوال لتسجيل الدخول، التحقق من الصلاحيات، وإدارة الجلسات
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

/**
 * محاولة تسجيل دخول المستخدم
 * @param string $email البريد الإلكتروني
 * @param string $password كلمة المرور
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function authenticateUser(string $email, string $password): array {
    // تنظيف البريد الإلكتروني
    $email = sanitizeInput($email);
    
    if (empty($email) || !isValidEmail($email)) {
        return ['success' => false, 'message' => 'البريد الإلكتروني غير صحيح.', 'user' => null];
    }
    
    if (empty($password)) {
        return ['success' => false, 'message' => 'كلمة المرور مطلوبة.', 'user' => null];
    }
    
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, name, email, password, role, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // التحقق من وجود المستخدم
        if (!$user) {
            return ['success' => false, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.', 'user' => null];
        }
        
        // التحقق من حالة المستخدم
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'الحساب غير نشط. يرجى التواصل مع الدعم.', 'user' => null];
        }
        
        // التحقق من كلمة المرور
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.', 'user' => null];
        }
        
        // إزالة كلمة المرور من بيانات المستخدم قبل الإرجاع
        unset($user['password']);
        
        return [
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'user' => $user
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.', 'user' => null];
    }
}

/**
 * تسجيل مستخدم جديد
 * @param array $data بيانات المستخدم (name, email, phone, password, role)
 * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
 */
function registerUser(array $data): array {
    $name = sanitizeInput($data['name'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $phone = sanitizeInput($data['phone'] ?? '');
    $password = $data['password'] ?? '';
    $role = in_array($data['role'] ?? '', ['admin', 'seller', 'buyer']) ? $data['role'] : 'buyer';
    
    // التحقق من صحة البيانات
    if (empty($name) || strlen($name) < 3) {
        return ['success' => false, 'message' => 'الاسم مطلوب (3 أحرف على الأقل).', 'user_id' => null];
    }
    
    if (empty($email) || !isValidEmail($email)) {
        return ['success' => false, 'message' => 'البريد الإلكتروني غير صحيح.', 'user_id' => null];
    }
    
    if (!empty($phone) && !isValidPhone($phone)) {
        return ['success' => false, 'message' => 'رقم الهاتف غير صحيح.', 'user_id' => null];
    }
    
    if (empty($password) || strlen($password) < 6) {
        return ['success' => false, 'message' => 'كلمة المرور يجب أن لا تقل عن 6 أحرف.', 'user_id' => null];
    }
    
    try {
        $db = Database::getConnection();
        
        // التحقق من عدم وجود البريد مسبقاً
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'البريد الإلكتروني مسجل مسبقاً.', 'user_id' => null];
        }
        
        // تشفير كلمة المرور وإدراج المستخدم
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email, $phone, $hashedPassword, $role]);
        
        return [
            'success' => true,
            'message' => 'تم تسجيل الحساب بنجاح.',
            'user_id' => (int)$db->lastInsertId()
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.', 'user_id' => null];
    }
}

/**
 * التحقق من صلاحية المستخدم للوصول إلى صفحة معينة
 * @param string|array $roles الأدوار المسموحة
 * @param string $redirectUrl رابط إعادة التوجيه في حال الفشل
 * @return bool
 */
function checkAccess($roles, string $redirectUrl = '../index.php'): bool {
    if (!isLoggedIn()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    if (!hasRole($roles)) {
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    return true;
}

/**
 * الحصول على بيانات المستخدم الحالي من قاعدة البيانات
 * @return array|null
 */
function getCurrentUserData(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    
    $userId = getCurrentUserId();
    
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, name, email, phone, role, location, status, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * تحديث بيانات المستخدم
 * @param int $userId معرف المستخدم
 * @param array $data البيانات المراد تحديثها
 * @return array ['success' => bool, 'message' => string]
 */
function updateUser(int $userId, array $data): array {
    $allowedFields = ['name', 'phone', 'location'];
    $updates = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = sanitizeInput($data[$field]);
        }
    }
    
    if (empty($updates)) {
        return ['success' => false, 'message' => 'لا توجد بيانات للتحديث.'];
    }
    
    // إضافة معرف المستخدم للمعاملات
    $params[] = $userId;
    
    try {
        $db = Database::getConnection();
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return ['success' => true, 'message' => 'تم تحديث البيانات بنجاح.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'حدث خطأ في تحديث البيانات.'];
    }
}

/**
 * تغيير كلمة المرور
 * @param int $userId معرف المستخدم
 * @param string $currentPassword كلمة المرور الحالية
 * @param string $newPassword كلمة المرور الجديدة
 * @return array ['success' => bool, 'message' => string]
 */
function changeUserPassword(int $userId, string $currentPassword, string $newPassword): array {
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'كلمة المرور الجديدة يجب أن لا تقل عن 6 أحرف.'];
    }
    
    try {
        $db = Database::getConnection();
        
        // التحقق من كلمة المرور الحالية
        $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'المستخدم غير موجود.'];
        }
        
        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة.'];
        }
        
        // تحديث كلمة المرور
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hashedPassword, $userId]);
        
        return ['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'حدث خطأ في تغيير كلمة المرور.'];
    }
}

/**
 * الحصول على عدد المستخدمين الكلي (للوحة المدير)
 * @return int
 */
function getTotalUsersCount(): int {
    try {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT COUNT(*) as total FROM users');
        return (int)$stmt->fetch()['total'];
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * التحقق من أن البريد الإلكتروني غير مستخدم من قبل مستخدم آخر
 * @param string $email
 * @param int|null $excludeUserId استثناء مستخدم معين (للتحديث)
 * @return bool
 */
function isEmailAvailable(string $email, ?int $excludeUserId = null): bool {
    try {
        $db = Database::getConnection();
        $sql = 'SELECT id FROM users WHERE email = ?';
        $params = [$email];
        
        if ($excludeUserId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeUserId;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return !$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}