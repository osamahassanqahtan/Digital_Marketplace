<?php
/**
 * reports/add.php
 * إضافة تقرير عن منتج مخالف (للمستخدمين المسجلين)
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// التحقق من تسجيل الدخول
requireLogin('../auth/login.php');

$productId = filter_var($_GET['product_id'] ?? 0, FILTER_VALIDATE_INT);
if ($productId < 1) {
    header('Location: ../products/index.php');
    exit;
}

$userId = getCurrentUserId();
$errors = [];
$success = false;

try {
    $db = Database::getConnection();
    
    // التحقق من وجود المنتج
    $stmt = $db->prepare('SELECT id, seller_id FROM products WHERE id = ? AND status != "deleted"');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: ../products/index.php');
        exit;
    }
    
    // منع الإبلاغ عن منتج البائع نفسه
    if ($product['seller_id'] == $userId) {
        $errors[] = 'لا يمكنك الإبلاغ عن منتجك الخاص.';
    }
    
    // التحقق من وجود تقرير سابق بنفس المستخدم لهذا المنتج (حالة pending)
    $stmt = $db->prepare('SELECT id FROM reports WHERE product_id = ? AND user_id = ? AND status = "pending"');
    $stmt->execute([$productId, $userId]);
    if ($stmt->fetch()) {
        $errors[] = 'لقد أبلغت عن هذا المنتج مسبقاً وهو قيد المراجعة.';
    }
    
} catch (PDOException $e) {
    $errors[] = 'حدث خطأ في تحميل البيانات.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $reason = sanitizeInput($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        $errors[] = 'يرجى كتابة سبب الإبلاغ.';
    } elseif (strlen($reason) < 5) {
        $errors[] = 'سبب الإبلاغ يجب أن لا يقل عن 5 أحرف.';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('INSERT INTO reports (product_id, user_id, reason, status) VALUES (?, ?, ?, "pending")');
            $stmt->execute([$productId, $userId, $reason]);
            $success = true;
            
            // إعادة التوجيه مع رسالة نجاح
            header('Location: ../products/view.php?id=' . $productId . '&reported=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'حدث خطأ في حفظ التقرير، حاول مرة أخرى.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإبلاغ عن منتج - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h4><i class="bi bi-flag"></i> الإبلاغ عن منتج مخالف</h4>
                    </div>
                    <div class="card-body">
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
                                <label for="reason" class="form-label">سبب الإبلاغ</label>
                                <textarea class="form-control" id="reason" name="reason" rows="4" required placeholder="اذكر سبب الإبلاغ عن هذا المنتج (مثلاً: منتج مقلد، مخالف للقوانين، إلخ)"><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-danger w-100"><i class="bi bi-send"></i> إرسال التقرير</button>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <a href="../products/view.php?id=<?php echo $productId; ?>" class="btn btn-secondary">العودة للتفاصيل</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>