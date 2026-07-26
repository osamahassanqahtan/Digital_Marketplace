<?php
/**
 * auth/profile.php
 * صفحة عرض وتعديل بيانات المستخدم الشخصية
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// التحقق من تسجيل الدخول
requireLogin('login.php');

$userId = getCurrentUserId();
$errors = [];
$success = '';

// جلب بيانات المستخدم الحالي
try {
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT id, name, email, phone, role, location, status, created_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        logoutUser();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    $errors[] = 'حدث خطأ في تحميل البيانات.';
}

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    
    if ($action === 'update_profile') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        
        // التحقق من صحة البيانات
        if (empty($name) || strlen($name) < 3) {
            $errors[] = 'الاسم مطلوب (3 أحرف على الأقل).';
        }
        if (!empty($phone) && !isValidPhone($phone)) {
            $errors[] = 'رقم الهاتف غير صحيح.';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare('UPDATE users SET name = ?, phone = ?, location = ? WHERE id = ?');
                $stmt->execute([$name, $phone, $location, $userId]);
                
                // تحديث اسم المستخدم في الجلسة
                $_SESSION['user_name'] = $name;
                
                $success = 'تم تحديث البيانات الشخصية بنجاح.';
                
                // تحديث بيانات المستخدم المعروضة
                $user['name'] = $name;
                $user['phone'] = $phone;
                $user['location'] = $location;
            } catch (PDOException $e) {
                $errors[] = 'حدث خطأ في تحديث البيانات.';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword)) {
            $errors[] = 'كلمة المرور الحالية مطلوبة.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'كلمة المرور الجديدة يجب أن لا تقل عن 6 أحرف.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'كلمتا المرور غير متطابقتين.';
        }
        
        if (empty($errors)) {
            try {
                // التحقق من كلمة المرور الحالية
                $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $userData = $stmt->fetch();
                
                if (!password_verify($currentPassword, $userData['password'])) {
                    $errors[] = 'كلمة المرور الحالية غير صحيحة.';
                } else {
                    // تحديث كلمة المرور
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $stmt->execute([$hashedPassword, $userId]);
                    $success = 'تم تغيير كلمة المرور بنجاح.';
                }
            } catch (PDOException $e) {
                $errors[] = 'حدث خطأ في تغيير كلمة المرور.';
            }
        }
    }
}

// تحديد دور المستخدم بالعربية
$roleLabels = [
    'admin' => 'مدير',
    'seller' => 'بائع',
    'buyer' => 'مشتري'
];
$roleLabel = $roleLabels[$user['role']] ?? $user['role'];

$statusLabels = [
    'active' => 'نشط',
    'inactive' => 'غير نشط'
];
$statusLabel = $statusLabels[$user['status']] ?? $user['status'];
require_once '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- شريط التنقل -->
    
    <div class="container py-4">
        <div class="row">
            <!-- بطاقة المعلومات الشخصية -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-person-circle" style="font-size: 5rem; color: #4F46E5;"></i>
                        </div>
                        <h4 class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></h4>
                        <p class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></p>
                        <div class="d-flex justify-content-center gap-2 mb-2">
                            <span class="badge bg-primary"><?php echo $roleLabel; ?></span>
                            <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo $statusLabel; ?>
                            </span>
                        </div>
                        <p class="text-muted small">
                            <i class="bi bi-calendar3"></i> عضو منذ <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                        </p>
                        <hr>
                        <div class="d-grid gap-2">
                            <a href="../chats/index.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-chat"></i> محادثاتي
                            </a>
                            <a href="../favorites/index.php" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-heart"></i> المفضلة
                            </a>
                            <?php if (hasRole('seller')): ?>
                                <a href="../seller/dashboard.php" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-speedometer2"></i> لوحة البائع
                                </a>
                            <?php endif; ?>
                            <?php if (hasRole('admin')): ?>
                                <a href="../admin/dashboard.php" class="btn btn-outline-dark btn-sm">
                                    <i class="bi bi-speedometer2"></i> لوحة المدير
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قسم التعديل -->
            <div class="col-md-8">
                <!-- عرض الرسائل -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-3">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- تحديث البيانات الشخصية -->
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white border-0 pt-4">
                        <h5 class="fw-bold"><i class="bi bi-pencil-square text-primary"></i> تعديل البيانات الشخصية</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">الاسم الكامل</label>
                                <input type="text" class="form-control form-control-lg" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">البريد الإلكتروني (غير قابل للتغيير)</label>
                                <input type="email" class="form-control form-control-lg" value="<?php echo htmlspecialchars($user['email']); ?>" disabled readonly>
                                <small class="text-muted">للتغيير، يرجى التواصل مع الدعم.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">رقم الهاتف</label>
                                <input type="tel" class="form-control form-control-lg" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">الموقع</label>
                                <input type="text" class="form-control form-control-lg" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="المدينة / المنطقة">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill">
                                <i class="bi bi-save"></i> حفظ التغييرات
                            </button>
                        </form>
                    </div>
                </div>

                <!-- تغيير كلمة المرور -->
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white border-0 pt-4">
                        <h5 class="fw-bold"><i class="bi bi-lock text-warning"></i> تغيير كلمة المرور</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">كلمة المرور الحالية</label>
                                <input type="password" class="form-control form-control-lg" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">كلمة المرور الجديدة</label>
                                <input type="password" class="form-control form-control-lg" name="new_password" required>
                                <small class="text-muted">يجب أن لا تقل عن 6 أحرف.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">تأكيد كلمة المرور الجديدة</label>
                                <input type="password" class="form-control form-control-lg" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-warning btn-lg w-100 rounded-pill">
                                <i class="bi bi-arrow-repeat"></i> تغيير كلمة المرور
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>