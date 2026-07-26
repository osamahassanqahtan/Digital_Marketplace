<?php
/**
 * products/details.php
 * عرض تفاصيل المنتج - تصميم عصري مستوحى من أكاديمية الحلول
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';

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
            c.id as category_id, c.name as category_name
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

    // التحقق من أن المنتج في المفضلة للمستخدم الحالي
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

    // جلب المنتجات ذات الصلة (نفس القسم)
    $relatedProducts = [];
    $stmtRelated = $db->prepare("SELECT p.id, p.name, p.price, 
                                 (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                                 FROM products p 
                                 WHERE p.category_id = ? AND p.id != ? AND p.status = 'available' 
                                 ORDER BY p.created_at DESC LIMIT 4");
    $stmtRelated->execute([$product['category_id'], $productId]);
    $relatedProducts = $stmtRelated->fetchAll();

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل تفاصيل المنتج.';
    $product = null;
    $images = [];
    $reviews = [];
    $relatedProducts = [];
}

// تضمين الهيدر الجديد
require_once '../includes/header.php';
?>
<style>
/* ==========================================
   تنسيقات صفحة تفاصيل المنتج - أكاديمية الحلول
   ========================================== */
.product-detail-wrapper {
    padding: 30px 0 50px;
}

/* معرض الصور */
.product-gallery .carousel-item img {
    height: 480px;
    object-fit: contain;
    background: #F8FAFC;
    border-radius: 20px;
}
.product-gallery .carousel-control-prev,
.product-gallery .carousel-control-next {
    width: 5%;
    opacity: 0.7;
}
.product-gallery .carousel-control-prev-icon,
.product-gallery .carousel-control-next-icon {
    background-color: rgba(79, 70, 229, 0.4);
    border-radius: 50%;
    padding: 20px;
    backdrop-filter: blur(4px);
}
.product-gallery .carousel-indicators {
    bottom: -30px;
}
.product-gallery .carousel-indicators button {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #CBD5E1;
    border: 2px solid transparent;
    margin: 0 4px;
}
.product-gallery .carousel-indicators button.active {
    background: #4F46E5;
    border-color: #4F46E5;
    width: 14px;
    height: 14px;
}

/* بطاقة المنتج */
.product-info-card {
    background: #fff;
    border-radius: 24px;
    padding: 30px;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.04);
    border: 1px solid #F1F5F9;
}
.product-info-card .product-title {
    font-size: 2rem;
    font-weight: 900;
    color: #1E293B;
    margin-bottom: 8px;
}
.product-info-card .product-price {
    font-size: 2.2rem;
    font-weight: 900;
    color: #4F46E5;
    background: #EEF2FF;
    padding: 4px 18px;
    border-radius: 50px;
    display: inline-block;
}
.product-info-card .product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 15px 0;
}
.product-info-card .product-meta .badge-custom {
    padding: 6px 16px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.85rem;
}
.product-info-card .product-description {
    color: #475569;
    line-height: 1.8;
    font-size: 1rem;
    border-top: 1px solid #F1F5F9;
    padding-top: 20px;
    margin-top: 10px;
}

/* بطاقة البائع */
.seller-card {
    background: #F8FAFC;
    border-radius: 20px;
    padding: 20px 24px;
    border: 1px solid #E2E8F0;
    transition: 0.3s;
}
.seller-card:hover {
    border-color: #4F46E5;
    box-shadow: 0 4px 20px rgba(79, 70, 229, 0.06);
}
.seller-card .seller-name {
    font-weight: 700;
    color: #1E293B;
}
.seller-card .seller-info-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #475569;
    margin-bottom: 6px;
}
.seller-card .seller-info-item i {
    color: #4F46E5;
    width: 20px;
    text-align: center;
}

/* أزرار الإجراءات */
.action-buttons .btn {
    border-radius: 50px;
    padding: 10px 24px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.action-buttons .btn-primary {
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border: none;
    box-shadow: 0 4px 15px rgba(79, 70, 229, 0.25);
}
.action-buttons .btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(79, 70, 229, 0.35);
}
.action-buttons .btn-outline-primary {
    border: 2px solid #4F46E5;
    color: #4F46E5;
}
.action-buttons .btn-outline-primary:hover {
    background: #4F46E5;
    color: #fff;
}
.action-buttons .btn-outline-danger {
    border: 2px solid #EF4444;
    color: #EF4444;
}
.action-buttons .btn-outline-danger:hover {
    background: #EF4444;
    color: #fff;
}
.action-buttons .btn-outline-warning {
    border: 2px solid #F59E0B;
    color: #D97706;
}
.action-buttons .btn-outline-warning:hover {
    background: #F59E0B;
    color: #fff;
}
.action-buttons .btn-secondary {
    background: #F1F5F9;
    color: #475569;
    border: none;
}
.action-buttons .btn-secondary:hover {
    background: #E2E8F0;
    transform: translateY(-2px);
}

/* التقييمات */
.review-card {
    background: #fff;
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 12px;
    border: 1px solid #F1F5F9;
    transition: 0.3s;
}
.review-card:hover {
    border-color: #E2E8F0;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}
.review-card .review-user {
    font-weight: 700;
    color: #1E293B;
}
.review-card .review-rating {
    color: #F59E0B;
}
.review-card .review-comment {
    color: #475569;
    margin-top: 6px;
}
.review-card .review-date {
    font-size: 0.8rem;
    color: #94A3B8;
}

/* منتجات ذات صلة */
.related-product-card {
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid #F1F5F9;
    background: #fff;
}
.related-product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
}
.related-product-card img {
    height: 160px;
    object-fit: cover;
}
.related-product-card .card-body {
    padding: 14px;
}
.related-product-card .card-title {
    font-size: 0.95rem;
    font-weight: 600;
}
.related-product-card .card-title a {
    color: #1E293B;
    text-decoration: none;
}
.related-product-card .card-title a:hover {
    color: #4F46E5;
}
.related-product-card .related-price {
    font-weight: 700;
    color: #4F46E5;
    font-size: 1rem;
}

/* حالة المنتج */
.product-status {
    display: inline-block;
    padding: 4px 16px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 0.85rem;
}
.product-status.available {
    background: #D1FAE5;
    color: #065F46;
}
.product-status.sold {
    background: #FEE2E2;
    color: #991B1B;
}
.product-status.pending {
    background: #FEF3C7;
    color: #92400E;
}

/* تحسينات للشاشات الصغيرة */
@media (max-width: 768px) {
    .product-info-card .product-title {
        font-size: 1.5rem;
    }
    .product-info-card .product-price {
        font-size: 1.6rem;
    }
    .product-gallery .carousel-item img {
        height: 300px;
    }
    .action-buttons .btn {
        padding: 8px 16px;
        font-size: 0.85rem;
    }
    .product-info-card {
        padding: 20px;
    }
}
</style>

<div class="container product-detail-wrapper">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger rounded-4"><?php echo $error; ?></div>
    <?php elseif ($product): ?>
        
        <!-- مسار التنقل (Breadcrumb) -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none text-primary">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-primary">المنتجات</a></li>
                <li class="breadcrumb-item"><a href="category.php?id=<?php echo $product['category_id']; ?>" class="text-decoration-none text-primary"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                <li class="breadcrumb-item active text-muted" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <!-- رسائل النجاح -->
        <?php if (isset($_GET['review_added'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4">
                <i class="bi bi-check-circle"></i> تم إضافة تقييمك بنجاح! شكراً لك.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['reported'])): ?>
            <div class="alert alert-info alert-dismissible fade show rounded-4">
                <i class="bi bi-info-circle"></i> تم إرسال البلاغ بنجاح، سيتم مراجعته من قبل الإدارة.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ==========================================
             تفاصيل المنتج – صف مزدوج
             ========================================== -->
        <div class="row g-4">
            
            <!-- العمود الأيسر: معرض الصور -->
            <div class="col-lg-6">
                <div id="productCarousel" class="carousel slide product-gallery" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php if (empty($images)): ?>
                            <div class="carousel-item active">
                                <div class="d-flex align-items-center justify-content-center" style="height: 480px; background: #F1F5F9; border-radius: 20px;">
                                    <i class="bi bi-image" style="font-size: 5rem; color: #CBD5E1;"></i>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($images as $index => $img): ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <img src="../uploads/products/<?php echo htmlspecialchars($img['image_path']); ?>" 
                                         class="d-block w-100" 
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
                        <div class="carousel-indicators">
                            <?php for ($i = 0; $i < count($images); $i++): ?>
                                <button type="button" data-bs-target="#productCarousel" data-bs-slide-to="<?php echo $i; ?>" 
                                        class="<?php echo $i === 0 ? 'active' : ''; ?>" aria-label="Slide <?php echo $i+1; ?>"></button>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- العمود الأيمن: معلومات المنتج -->
            <div class="col-lg-6">
                <div class="product-info-card">
                    
                    <!-- العنوان والسعر -->
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <span class="product-price"><?php echo formatPrice($product['price']); ?></span>
                    </div>

                    <!-- الشارات -->
                    <div class="product-meta">
                        <span class="badge-custom bg-<?php echo $product['condition'] == 'new' ? 'success' : 'warning'; ?> text-white">
                            <?php echo $product['condition'] == 'new' ? 'جديد' : 'مستعمل'; ?>
                        </span>
                        <span class="badge-custom bg-info text-white"><?php echo htmlspecialchars($product['category_name']); ?></span>
                        <span class="badge-custom bg-secondary text-white"><i class="bi bi-eye"></i> <?php echo $product['views']; ?></span>
                        <span class="product-status <?php echo $product['status']; ?>">
                            <?php echo $product['status'] == 'available' ? 'متاح' : ($product['status'] == 'sold' ? 'تم البيع' : 'قيد الانتظار'); ?>
                        </span>
                    </div>

                    <!-- التقييمات -->
                    <div class="mb-3">
                        <?php if ($totalReviews > 0): ?>
                            <span class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= floor($avgRating) ? '-fill' : ($i <= ceil($avgRating) && $avgRating - floor($avgRating) > 0 ? '-half' : ''); ?>" style="color: #F59E0B;"></i>
                                <?php endfor; ?>
                            </span>
                            <span class="fw-bold ms-1"><?php echo $avgRating; ?></span>
                            <span class="text-muted">(<?php echo $totalReviews; ?> تقييم)</span>
                        <?php else: ?>
                            <span class="text-muted">لا توجد تقييمات حتى الآن</span>
                        <?php endif; ?>
                    </div>

                    <!-- الوصف -->
                    <div class="product-description">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>

                    <!-- معلومات البائع -->
                    <div class="seller-card mt-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-person-circle text-primary"></i> معلومات البائع</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="seller-info-item">
                                    <i class="bi bi-person"></i>
                                    <span class="seller-name"><?php echo htmlspecialchars($product['seller_name']); ?></span>
                                </div>
                                <div class="seller-info-item">
                                    <i class="bi bi-geo-alt"></i>
                                    <span><?php echo htmlspecialchars($product['location']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="seller-info-item">
                                    <i class="bi bi-telephone"></i>
                                    <a href="tel:<?php echo htmlspecialchars($product['contact_phone']); ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($product['contact_phone']); ?></a>
                                </div>
                                <?php if (isLoggedIn() && getCurrentUserId() != $product['seller_id']): ?>
                                    <div class="seller-info-item">
                                        <i class="bi bi-envelope"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($product['seller_email']); ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($product['seller_email']); ?></a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- أزرار الإجراءات -->
                    <div class="action-buttons d-flex flex-wrap gap-2 mt-4">
                        <?php if (isLoggedIn()): ?>
                            <?php if (getCurrentUserId() == $product['seller_id'] || getCurrentUserRole() == 'admin'): ?>
                                <a href="edit.php?id=<?php echo $product['id']; ?>" class="btn btn-warning rounded-pill px-4">
                                    <i class="bi bi-pencil"></i> تعديل
                                </a>
                                <a href="delete.php?id=<?php echo $product['id']; ?>" class="btn btn-danger rounded-pill px-4" onclick="return confirm('هل أنت متأكد من حذف هذا المنتج؟')">
                                    <i class="bi bi-trash"></i> حذف
                                </a>
                            <?php endif; ?>
                            <?php if (getCurrentUserId() != $product['seller_id']): ?>
                                <a href="../chat/conversation.php?user_id=<?php echo $product['seller_id']; ?>" class="btn btn-primary rounded-pill px-4">
                                    <i class="bi bi-chat"></i> مراسلة البائع
                                </a>
                                <button class="btn btn-outline-danger rounded-pill px-4 favorite-btn" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="bi <?php echo $isFavorite ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                                    <span><?php echo $isFavorite ? 'إزالة من المفضلة' : 'إضافة للمفضلة'; ?></span>
                                </button>
                                <?php if (!$userReviewed): ?>
                                    <a href="../reviews/add.php?product_id=<?php echo $product['id']; ?>" class="btn btn-outline-primary rounded-pill px-4">
                                        <i class="bi bi-star"></i> تقييم
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-secondary rounded-pill px-4 disabled">
                                        <i class="bi bi-check-circle"></i> قيمت هذا المنتج
                                    </span>
                                <?php endif; ?>
                                <a href="../reports/add.php?product_id=<?php echo $product['id']; ?>" class="btn btn-outline-warning rounded-pill px-4">
                                    <i class="bi bi-flag"></i> إبلاغ
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="../auth/login.php" class="btn btn-primary rounded-pill px-4">سجل دخول للتواصل</a>
                            <a href="../auth/register.php" class="btn btn-outline-primary rounded-pill px-4">إنشاء حساب</a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-secondary rounded-pill px-4"><i class="bi bi-arrow-right"></i> العودة</a>
                    </div>

                </div><!-- /.product-info-card -->
            </div><!-- /.col-lg-6 -->
        </div><!-- /.row -->

        <!-- ==========================================
             التقييمات
             ========================================== -->
        <div class="mt-5">
            <h4 class="fw-bold mb-4"><i class="bi bi-star text-warning"></i> التقييمات</h4>
            <?php if ($totalReviews > 0): ?>
                <div class="mb-3">
                    <span class="badge bg-warning text-dark fs-6 p-2">
                        <i class="bi bi-star-fill"></i> <?php echo $avgRating; ?> / 5
                    </span>
                    <span class="text-muted">(<?php echo $totalReviews; ?> تقييم)</span>
                </div>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="review-user"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($review['user_name']); ?></span>
                                <span class="review-rating ms-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <span class="review-date"><?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?></span>
                        </div>
                        <div class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-light text-center py-4 rounded-4">
                    <i class="bi bi-chat-square-text" style="font-size: 2.5rem; color: #CBD5E1;"></i>
                    <p class="mt-2">لا توجد تقييمات حتى الآن.</p>
                    <?php if (isLoggedIn() && getCurrentUserId() != $product['seller_id'] && !$userReviewed): ?>
                        <a href="../reviews/add.php?product_id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm rounded-pill">أضف تقييمك</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==========================================
             منتجات ذات صلة
             ========================================== -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="mt-5">
                <h4 class="fw-bold mb-4"><i class="bi bi-tags text-primary"></i> منتجات ذات صلة</h4>
                <div class="row row-cols-2 row-cols-md-4 g-3">
                    <?php foreach ($relatedProducts as $related): ?>
                        <div class="col">
                            <div class="card related-product-card h-100">
                                <?php if ($related['primary_image']): ?>
                                    <img src="../uploads/products/<?php echo htmlspecialchars($related['primary_image']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($related['name']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top d-flex align-items-center justify-content-center bg-secondary" style="height: 160px;">
                                        <i class="bi bi-image text-light" style="font-size: 2.5rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <a href="details.php?id=<?php echo $related['id']; ?>"><?php echo htmlspecialchars($related['name']); ?></a>
                                    </h6>
                                    <span class="related-price"><?php echo formatPrice($related['price']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php
// تضمين الفوتر الجديد
require_once '../includes/footer.php';
?>

<!-- ==========================================
     كود JavaScript إضافي (للمفضلة)
     ========================================== -->

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