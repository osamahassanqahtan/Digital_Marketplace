<?php
/**
 * reviews/add.php
 * إضافة تقييم على منتج (للمستخدمين المسجلين فقط)
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

// التحقق من وجود المنتج
try {
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT id, seller_id FROM products WHERE id = ? AND status != "deleted"');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: ../products/index.php');
        exit;
    }
    
    // منع المستخدم من تقييم منتجه الخاص
    if ($product['seller_id'] == $userId) {
        $errors[] = 'لا يمكنك تقييم منتجك الخاص.';
    }
    
    // التحقق مما إذا كان المستخدم قد قيم هذا المنتج مسبقاً
    $stmt = $db->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ?');
    $stmt->execute([$productId, $userId]);
    if ($stmt->fetch()) {
        $errors[] = 'لقد قمت بتقييم هذا المنتج مسبقاً.';
    }
    
} catch (PDOException $e) {
    $errors[] = 'حدث خطأ في تحميل البيانات.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $rating = filter_var($_POST['rating'] ?? 0, FILTER_VALIDATE_INT);
    $comment = sanitizeInput($_POST['comment'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'يرجى اختيار تقييم من 1 إلى 5 نجوم.';
    }
    if (empty($comment)) {
        $errors[] = 'يرجى كتابة تعليقك.';
    } elseif (strlen($comment) < 3) {
        $errors[] = 'التعليق يجب أن لا يقل عن 3 أحرف.';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)');
            $stmt->execute([$productId, $userId, $rating, $comment]);
            $success = true;
            
            // إعادة التوجيه إلى صفحة المنتج مع رسالة نجاح
            header('Location: ../products/view.php?id=' . $productId . '&review_added=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'حدث خطأ في حفظ التقييم، حاول مرة أخرى.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة تقييم - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .star-rating {
            direction: ltr;
            display: inline-flex;
            gap: 5px;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-star"></i> إضافة تقييم</h4>
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
                        
                        <?php if (empty($errors) || !empty($_POST)): ?>
                        <form method="POST" action="">
                            <div class="mb-3 text-center">
                                <label class="form-label d-block">تقييمك</label>
                                <div class="star-rating">
                                    <input type="radio" name="rating" id="star5" value="5">
                                    <label for="star5" class="bi bi-star"></label>
                                    <input type="radio" name="rating" id="star4" value="4">
                                    <label for="star4" class="bi bi-star"></label>
                                    <input type="radio" name="rating" id="star3" value="3">
                                    <label for="star3" class="bi bi-star"></label>
                                    <input type="radio" name="rating" id="star2" value="2">
                                    <label for="star2" class="bi bi-star"></label>
                                    <input type="radio" name="rating" id="star1" value="1">
                                    <label for="star1" class="bi bi-star"></label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="comment" class="form-label">تعليقك</label>
                                <textarea class="form-control" id="comment" name="comment" rows="4" required><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">إرسال التقييم</button>
                        </form>
                        <?php endif; ?>
                        
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