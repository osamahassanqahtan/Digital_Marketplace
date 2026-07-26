<?php
/**
 * auth/register.php - نسخة محدثة مع اختيار الدور وتصميم احترافي
 */
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

if (isLoggedIn()) { header('Location: ../index.php'); exit; }

$errors = []; $success = false;
$formData = ['name'=>'', 'email'=>'', 'phone'=>'', 'role'=>'buyer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name'] = sanitizeInput($_POST['name'] ?? '');
    $formData['email'] = sanitizeInput($_POST['email'] ?? '');
    $formData['phone'] = sanitizeInput($_POST['phone'] ?? '');
    $formData['role'] = sanitizeInput($_POST['role'] ?? 'buyer');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // التحقق من صحة الأدوار (منع التسجيل كـ Admin)
    if (!in_array($formData['role'], ['buyer', 'seller'])) {
        $errors[] = 'دور غير صالح.';
    }
    if (empty($formData['name']) || strlen($formData['name']) < 3) $errors[] = 'الاسم مطلوب (3 أحرف على الأقل).';
    if (empty($formData['email']) || !isValidEmail($formData['email'])) $errors[] = 'البريد الإلكتروني غير صحيح.';
    if (strlen($password) < 6) $errors[] = 'كلمة المرور يجب أن لا تقل عن 6 أحرف.';
    if ($password !== $confirm) $errors[] = 'كلمتا المرور غير متطابقتين.';

    if (empty($errors)) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$formData['email']]);
            if ($stmt->fetch()) { $errors[] = 'البريد مسجل مسبقاً.'; }
            else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$formData['name'], $formData['email'], $formData['phone'], $hashed, $formData['role']]);
                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $e) { $errors[] = 'خطأ في قاعدة البيانات.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل جديد | منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- التصميم الاحترافي -->
</head>
<body class="auth-page">
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="card auth-card shadow-lg border-0">
                    <div class="card-header bg-gradient-primary text-white text-center py-4">
                        <h4 class="mb-0 fw-bold"><i class="bi bi-person-plus"></i> إنشاء حساب جديد</h4>
                        <p class="mb-0 small opacity-75">انضم إلى مجتمع السوق الذكي</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger rounded-3"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="bi bi-person"></i> الاسم الكامل</label>
                                <input type="text" class="form-control form-control-lg" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="bi bi-envelope"></i> البريد الإلكتروني</label>
                                <input type="email" class="form-control form-control-lg" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="bi bi-phone"></i> رقم الهاتف</label>
                                <input type="tel" class="form-control form-control-lg" name="phone" value="<?php echo htmlspecialchars($formData['phone']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="bi bi-person-badge"></i> نوع الحساب</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check flex-fill p-3 border rounded-3 <?php echo ($formData['role'] == 'buyer') ? 'border-primary bg-primary-soft' : 'border-secondary'; ?>">
                                        <input class="form-check-input" type="radio" name="role" id="roleBuyer" value="buyer" <?php echo ($formData['role'] == 'buyer') ? 'checked' : ''; ?>>
                                        <label class="form-check-label w-100" for="roleBuyer">
                                            <i class="bi bi-cart text-primary"></i> مشتري
                                            <small class="d-block text-muted">لشراء المنتجات</small>
                                        </label>
                                    </div>
                                    <div class="form-check flex-fill p-3 border rounded-3 <?php echo ($formData['role'] == 'seller') ? 'border-success bg-success-soft' : 'border-secondary'; ?>">
                                        <input class="form-check-input" type="radio" name="role" id="roleSeller" value="seller" <?php echo ($formData['role'] == 'seller') ? 'checked' : ''; ?>>
                                        <label class="form-check-label w-100" for="roleSeller">
                                            <i class="bi bi-shop text-success"></i> بائع
                                            <small class="d-block text-muted">لإضافة وبيع المنتجات</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="bi bi-lock"></i> كلمة المرور</label>
                                <input type="password" class="form-control form-control-lg" name="password" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold"><i class="bi bi-lock"></i> تأكيد كلمة المرور</label>
                                <input type="password" class="form-control form-control-lg" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold">إنشاء الحساب <i class="bi bi-arrow-left"></i></button>
                        </form>
                        <div class="mt-3 text-center">
                            لديك حساب؟ <a href="login.php" class="text-primary fw-bold">تسجيل الدخول</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>