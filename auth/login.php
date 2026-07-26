<?php
/**
 * auth/login.php
 * صفحة تسجيل الدخول
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// إذا كان المستخدم مسجلاً دخوله، انتقل إلى الصفحة الرئيسية
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email)) {
        $errors[] = 'البريد الإلكتروني مطلوب.';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'البريد الإلكتروني غير صحيح.';
    }

    if (empty($password)) {
        $errors[] = 'كلمة المرور مطلوبة.';
    }

    if (empty($errors)) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? AND status = "active"');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // تسجيل الدخول - حفظ البيانات في الجلسة
                loginUser([
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $user['role']
                ]);

                // توجيه المستخدم حسب دوره
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/dashboard.php');
                } elseif ($user['role'] === 'seller') {
                    header('Location: ../seller/dashboard.php');
                } else {
                    header('Location: ../index.php');
                }
                exit;
            } else {
                $errors[] = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
            }
        } catch (PDOException $e) {
            $errors[] = 'حدث خطأ في قاعدة البيانات، حاول مرة أخرى.';
        }
    }
}

// عرض رسالة نجاح التسجيل إن وجدت
$successMessage = '';
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $successMessage = 'تم التسجيل بنجاح! يمكنك تسجيل الدخول الآن.';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4>تسجيل الدخول</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($successMessage): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">دخول</button>
                        </form>
                        <div class="mt-3 text-center">
                            ليس لديك حساب؟ <a href="register.php">إنشاء حساب جديد</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>