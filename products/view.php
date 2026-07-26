<?php
/**
 * products/view.php
 * عرض تفاصيل المنتج مع الصور وبيانات البائع والتقييمات
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// التحقق من وجود معرف المنتج
$productId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($productId < 1) {
    header('Location: index.php');
    exit;
}

try {
    $db = Database::getConnection();
    
    // جلب بيانات المنتج مع البائع والقسم
    $sql = "SELECT p.*, u.id as seller_id, u.name as seller_name, u.email as seller_email, u.phone as seller_phone, u.location as seller_location,
            c.name as category_name
            FROM products p
            JOIN users u ON p.seller_id = u.id
            JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.status != 'deleted'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        header('Location: index.php');
        exit;
    }

    // زيادة عدد المشاهدات
    $updateView = $db->prepare('UPDATE products SET views = views + 1 WHERE id = ?');
    $updateView->execute([$productId]);

    // جلب صور المنتج
    $stmtImg = $db->prepare('SELECT image_path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC');
    $stmtImg->execute([$productId]);
    $images = $stmtImg->fetchAll();

    // التحقق من أن المنتج في المفضلة للمستخدم الحالي (إذا كان مسجلاً)
    $isFavorite = false;
    if (isLoggedIn()) {
        $userId = getCurrentUserId();
        $stmtFav = $db->prepare('SELECT id FROM favorites WHERE user_id = ? AND product_id = ?');
        $stmtFav->execute([$userId, $productId]);
        $isFavorite = $stmtFav->fetch() ? true : false;
    }

    // جلب التقييمات
    $avgRating = 0;
    $totalReviews = 0;
    $reviews = [];
    
    $stmtRev = $db->prepare('SELECT r.*, u.name as user_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC');
    $stmtRev->execute([$productId]);
    $reviews = $stmtRev->fetchAll();
    $totalReviews = count($reviews);
    
    if ($totalReviews > 0) {
        $stmtAvg = $db->prepare('SELECT AVG(rating) as avg FROM reviews WHERE product_id = ?');
        $stmtAvg->execute([$productId]);
        $avgRating = round($stmtAvg->fetch()['avg'], 1);
    }

    // التحقق مما إذا كان المستخدم الحالي قد قيم هذا المنتج
    $userReviewed = false;
    if (isLoggedIn()) {
        $stmtCheck = $db->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ?');
        $stmtCheck->execute([$productId, getCurrentUserId()]);
        $userReviewed = $stmtCheck->fetch() ? true : false;
    }

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل تفاصيل المنتج.';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - منصة السوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">🏪 منصة السوق</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php">المنتجات</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="../favorites/index.php">المفضلة</a></li>
                        <?php if (hasRole('seller') || hasRole('admin')): ?>
                            <li class="nav-item"><a class="nav-link" href="add.php">إضافة منتج</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="../auth/logout.php">تسجيل خروج</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="../auth/login.php">تسجيل دخول</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php else: ?>
            
            <!-- رسالة نجاح إضافة تقييم -->
            <?php if (isset($_GET['review_added'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    تم إضافة تقييمك بنجاح! شكراً لك.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- تفاصيل المنتج -->
            <div class="row">
                <div class="col-md-6">
                    <!-- عرض الصور -->
                    <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php if (empty($images)): ?>
                                <div class="carousel-item active">
                                    <div class="bg-secondary d-flex align-items-center justify-content-center" style="height: 400px;">
                                        <i class="bi bi-image text-light" style="font-size: 5rem;"></i>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($images as $index => $img): ?>
                                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                        <img src="../uploads/products/<?php echo htmlspecialchars($img['image_path']); ?>" 
                                             class="d-block w-100" style="height: 400px; object-fit: contain; background: #f8f9fa;" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (count($images) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">السابق</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">التالي</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-success fs-6"><?php echo formatPrice($product['price']); ?></span>
                        <span class="badge bg-secondary"><?php echo $product['condition'] == 'new' ? 'جديد' : 'مستعمل'; ?></span>
                        <span class="badge bg-info"><?php echo htmlspecialchars($product['category_name']); ?></span>
                        <span class="badge bg-warning text-dark"><i class="bi bi-eye"></i> <?php echo $product['views']; ?> مشاهدة</span>
                    </div>

                    <div class="mb-3">
                        <h5>الوصف</h5>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>

                    <div class="mb-3">
                        <h5>معلومات البائع</h5>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-person"></i> <?php echo htmlspecialchars($product['seller_name']); ?></li>
                            <li><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($product['location']); ?></li>
                            <li><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($product['contact_phone']); ?></li>
                            <?php if (isLoggedIn() && getCurrentUserId() != $product['seller_id']): ?>
                                <li><i class="bi bi-envelope"></i> <a href="mailto:<?php echo htmlspecialchars($product['seller_email']); ?>"><?php echo htmlspecialchars($product['seller_email']); ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- أزرار الإجراءات -->
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (isLoggedIn()): ?>
                            <?php if (getCurrentUserId() == $product['seller_id'] || getCurrentUserRole() == 'admin'): ?>
                                <a href="edit.php?id=<?php echo $product['id']; ?>" class="btn btn-warning"><i class="bi bi-pencil"></i> تعديل</a>
                                <a href="delete.php?id=<?php echo $product['id']; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المنتج؟')"><i class="bi bi-trash"></i> حذف</a>
                            <?php endif; ?>
                            <?php if (getCurrentUserId() != $product['seller_id']): ?>
                                <!-- ✅ تم تصحيح الرابط -->
                                <a href="../chat/conversation.php?user_id=<?php echo $product['seller_id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-chat"></i> مراسلة البائع
                                </a>
                                <button class="btn btn-outline-danger favorite-btn" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="bi <?php echo $isFavorite ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                                    <span><?php echo $isFavorite ? 'إزالة من المفضلة' : 'إضافة للمفضلة'; ?></span>
                                </button>
                                <?php if (!$userReviewed): ?>
                                    <a href="../reviews/add.php?product_id=<?php echo $product['id']; ?>" class="btn btn-outline-primary"><i class="bi bi-star"></i> تقييم</a>
                                <?php else: ?>
                                    <span class="btn btn-outline-secondary disabled"><i class="bi bi-check-circle"></i> قيمت هذا المنتج</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="../auth/login.php" class="btn btn-outline-primary">سجل دخول للتواصل مع البائع</a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
                    </div>

                    <!-- حالة المنتج -->
                    <div class="mt-3">
                        <strong>الحالة: </strong>
                        <?php if ($product['status'] == 'available'): ?>
                            <span class="badge bg-success">متاح</span>
                        <?php elseif ($product['status'] == 'sold'): ?>
                            <span class="badge bg-danger">تم البيع</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">قيد الانتظار</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- قسم التقييمات -->
            <div class="mt-5">
                <h4><i class="bi bi-star"></i> التقييمات</h4>
                <?php if ($totalReviews > 0): ?>
                    <div class="mb-3">
                        <span class="badge bg-warning text-dark fs-6">
                            <i class="bi bi-star-fill"></i> <?php echo $avgRating; ?> / 5
                        </span>
                        <span class="text-muted">(<?php echo $totalReviews; ?> تقييم)</span>
                    </div>
                    <?php foreach ($reviews as $review): ?>
                        <div class="card mb-2">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <h6 class="card-subtitle mb-2">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($review['user_name']); ?>
                                    </h6>
                                    <span>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>" style="color: <?php echo $i <= $review['rating'] ? '#ffc107' : '#ddd'; ?>;"></i>
                                        <?php endfor; ?>
                                    </span>
                                </div>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">لا توجد تقييمات حتى الآن. كن أول من يقيم هذا المنتج.</p>
                    <?php if (isLoggedIn() && getCurrentUserId() != $product['seller_id']): ?>
                        <a href="../reviews/add.php?product_id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">أضف تقييمك</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // إضافة/إزالة المفضلة باستخدام AJAX
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const icon = this.querySelector('i');
                const text = this.querySelector('span');
                const isCurrentlyFavorite = icon.classList.contains('bi-heart-fill');
                const action = isCurrentlyFavorite ? 'remove' : 'add';
                
                fetch('../favorites/toggle.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `product_id=${productId}&action=${action}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.is_favorite) {
                            icon.classList.replace('bi-heart', 'bi-heart-fill');
                            text.textContent = 'إزالة من المفضلة';
                        } else {
                            icon.classList.replace('bi-heart-fill', 'bi-heart');
                            text.textContent = 'إضافة للمفضلة';
                        }
                    } else {
                        alert(data.message || 'حدث خطأ');
                    }
                })
                .catch(err => {
                    alert('حدث خطأ في الاتصال');
                });
            });
        });
    </script>
</body>
</html>