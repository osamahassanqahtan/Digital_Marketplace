<?php
/**
 * favorites/index.php
 * عرض قائمة المنتجات المفضلة للمستخدم - تصميم عصري وجذاب
 */

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
requireLogin('../auth/login.php');

$userId = getCurrentUserId();
$message = '';
$error = '';

try {
    $db = Database::getConnection();
    
    // جلب المنتجات المفضلة مع صورها وبيانات البائع
    $sql = "SELECT p.*, u.name as seller_name,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
            f.created_at as favorited_at
            FROM favorites f
            JOIN products p ON f.product_id = p.id
            JOIN users u ON p.seller_id = u.id
            WHERE f.user_id = ?
            ORDER BY f.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    $favorites = $stmt->fetchAll();

    // إحصائيات المفضلة
    $totalFavorites = count($favorites);

} catch (PDOException $e) {
    $error = 'حدث خطأ في تحميل المفضلات.';
    $favorites = [];
    $totalFavorites = 0;
}

// تضمين الهيدر الجديد
require_once '../includes/header.php';
?>

<style>
/* ==========================================
   تصميم صفحة المفضلة - عصري وجذاب
   ========================================== */

/* رأس الصفحة */
.favorites-header {
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 50%, #6D28D9 100%);
    padding: 40px 0 50px;
    border-radius: 0 0 40px 40px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}
.favorites-header::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.favorites-header::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
}
.favorites-header .page-title {
    color: #fff;
    font-weight: 900;
    font-size: 2.2rem;
    margin-bottom: 6px;
}
.favorites-header .page-title i {
    color: #FCD34D;
}
.favorites-header .page-subtitle {
    color: rgba(255,255,255,0.8);
    font-size: 1.05rem;
}
.favorites-header .favorites-stats {
    display: flex;
    gap: 30px;
    margin-top: 15px;
}
.favorites-header .favorites-stats .stat-item {
    color: rgba(255,255,255,0.9);
}
.favorites-header .favorites-stats .stat-item .stat-number {
    font-size: 1.6rem;
    font-weight: 800;
    display: block;
}
.favorites-header .favorites-stats .stat-item .stat-label {
    font-size: 0.85rem;
    opacity: 0.8;
}

/* بطاقات المنتجات */
.favorites-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    gap: 24px;
}
.favorite-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid #F1F5F9;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}
.favorite-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(79,70,229,0.10);
    border-color: #E0E7FF;
}
.favorite-card .card-image {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: #F8FAFC;
}
.favorite-card .card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.favorite-card:hover .card-image img {
    transform: scale(1.05);
}
.favorite-card .card-image .badge-condition {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 4px 16px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 700;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.favorite-card .card-image .badge-favorite {
    position: absolute;
    top: 12px;
    left: 12px;
    background: rgba(239, 68, 68, 0.9);
    color: #fff;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 700;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.favorite-card .card-image .badge-favorite i {
    margin-left: 4px;
}
.favorite-card .card-body {
    padding: 18px 20px 14px;
}
.favorite-card .card-body .product-name {
    font-weight: 700;
    font-size: 1.05rem;
    color: #1E293B;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.favorite-card .card-body .product-name a {
    color: inherit;
    text-decoration: none;
    transition: 0.3s;
}
.favorite-card .card-body .product-name a:hover {
    color: #4F46E5;
}
.favorite-card .card-body .product-desc {
    font-size: 0.85rem;
    color: #94A3B8;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 10px;
    min-height: 40px;
}
.favorite-card .card-body .product-price {
    font-size: 1.4rem;
    font-weight: 900;
    color: #4F46E5;
}
.favorite-card .card-body .product-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #F1F5F9;
}
.favorite-card .card-body .product-meta .seller-name {
    font-size: 0.8rem;
    color: #64748B;
}
.favorite-card .card-body .product-meta .seller-name i {
    margin-left: 4px;
}
.favorite-card .card-body .product-meta .favorite-date {
    font-size: 0.7rem;
    color: #94A3B8;
}
.favorite-card .card-footer {
    padding: 0 20px 18px;
    background: transparent;
    border: none;
    display: flex;
    gap: 8px;
}
.favorite-card .card-footer .btn-details {
    flex: 1;
    border-radius: 30px;
    padding: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border: none;
    color: #fff;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(79,70,229,0.2);
}
.favorite-card .card-footer .btn-details:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(79,70,229,0.35);
}
.favorite-card .card-footer .btn-remove {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 2px solid #FEE2E2;
    background: #FFF;
    color: #EF4444;
    transition: 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.favorite-card .card-footer .btn-remove:hover {
    background: #EF4444;
    color: #fff;
    border-color: #EF4444;
    transform: rotate(90deg);
}

/* حالة عدم وجود منتجات */
.empty-state {
    text-align: center;
    padding: 80px 20px;
}
.empty-state i {
    font-size: 5rem;
    color: #CBD5E1;
    display: block;
    margin-bottom: 16px;
}
.empty-state h4 {
    color: #1E293B;
    font-weight: 700;
    font-size: 1.5rem;
}
.empty-state p {
    color: #94A3B8;
    font-size: 1rem;
    max-width: 400px;
    margin: 0 auto 20px;
}
.empty-state .btn-explore {
    border-radius: 30px;
    padding: 12px 40px;
    font-weight: 700;
    background: linear-gradient(135deg, #4F46E5, #7C3AED);
    border: none;
    color: #fff;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(79,70,229,0.2);
}
.empty-state .btn-explore:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(79,70,229,0.35);
}

/* رسالة نجاح عند الإزالة */
.alert-remove-success {
    background: #D1FAE5;
    color: #065F46;
    border: none;
    border-radius: 16px;
    padding: 16px 24px;
}

/* التوافق مع الشاشات الصغيرة */
@media (max-width: 768px) {
    .favorites-header {
        padding: 25px 0 30px;
        border-radius: 0 0 25px 25px;
    }
    .favorites-header .page-title {
        font-size: 1.6rem;
    }
    .favorites-header .favorites-stats {
        gap: 15px;
        flex-wrap: wrap;
    }
    .favorites-header .favorites-stats .stat-item .stat-number {
        font-size: 1.2rem;
    }
    .favorites-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 16px;
    }
    .favorite-card .card-image {
        height: 160px;
    }
    .favorite-card .card-body .product-price {
        font-size: 1.1rem;
    }
    .favorite-card .card-body .product-name {
        font-size: 0.95rem;
    }
}
@media (max-width: 576px) {
    .favorites-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    .favorite-card .card-image {
        height: 140px;
    }
    .favorite-card .card-body {
        padding: 12px 14px 10px;
    }
    .favorite-card .card-body .product-desc {
        display: none;
    }
    .favorite-card .card-footer {
        padding: 0 14px 14px;
    }
    .favorite-card .card-footer .btn-details {
        font-size: 0.8rem;
        padding: 8px;
    }
    .favorite-card .card-footer .btn-remove {
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }
}
</style>

<!-- ==========================================
     رأس الصفحة
     ========================================== -->
<section class="favorites-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="page-title">
                    <i class="bi bi-heart-fill"></i> منتجاتي المفضلة
                </h1>
                <p class="page-subtitle">قائمة المنتجات التي أضفتها إلى المفضلة</p>
                <div class="favorites-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $totalFavorites; ?></span>
                        <span class="stat-label">منتج مفضل</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <i class="bi bi-heart" style="font-size: 6rem; opacity: 0.2; color: #fff;"></i>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     عرض المنتجات
     ========================================== -->
<div class="container pb-4">
    
    <!-- عرض رسائل النجاح أو الخطأ -->
    <?php if (isset($_GET['removed']) && $_GET['removed'] == 1): ?>
        <div class="alert alert-remove-success alert-dismissible fade show rounded-4 mb-4">
            <i class="bi bi-check-circle"></i> تم إزالة المنتج من المفضلة بنجاح.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger rounded-4"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (empty($favorites)): ?>
        <!-- حالة عدم وجود منتجات -->
        <div class="empty-state">
            <i class="bi bi-heart"></i>
            <h4>قائمة المفضلة فارغة</h4>
            <p>لم تقم بإضافة أي منتج إلى المفضلة بعد. استكشف المنتجات وأضف ما يعجبك.</p>
            <a href="../products/index.php" class="btn btn-explore">
                <i class="bi bi-grid"></i> استكشف المنتجات
            </a>
        </div>
    <?php else: ?>
        <!-- عرض المنتجات -->
        <div class="favorites-grid">
            <?php foreach ($favorites as $product): ?>
                <div class="favorite-card" data-product-id="<?php echo $product['id']; ?>">
                    <div class="card-image">
                        <?php if ($product['primary_image']): ?>
                            <img src="../uploads/products/<?php echo htmlspecialchars($product['primary_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                <i class="bi bi-image" style="font-size: 3rem; color: #CBD5E1;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <span class="badge-condition bg-<?php echo $product['condition'] == 'new' ? 'success' : 'warning'; ?> text-white">
                            <?php echo $product['condition'] == 'new' ? 'جديد' : 'مستعمل'; ?>
                        </span>
                        
                        <span class="badge-favorite">
                            <i class="bi bi-heart-fill"></i> مفضلة
                        </span>
                    </div>
                    
                    <div class="card-body">
                        <h5 class="product-name">
                            <a href="../products/details.php?id=<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h5>
                        <div class="product-desc"><?php echo htmlspecialchars($product['description']); ?></div>
                        
                        <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                        
                        <div class="product-meta">
                            <span class="seller-name">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($product['seller_name']); ?>
                            </span>
                            <span class="favorite-date">
                                <i class="bi bi-clock"></i> <?php echo date('d/m/Y', strtotime($product['favorited_at'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <a href="../products/details.php?id=<?php echo $product['id']; ?>" class="btn-details">
                            <i class="bi bi-eye"></i> تفاصيل
                        </a>
                        <button class="btn-remove remove-favorite" data-product-id="<?php echo $product['id']; ?>" title="إزالة من المفضلة">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// تضمين الفوتر الجديد
require_once '../includes/footer.php';
?>

<!-- ==========================================
     كود JavaScript لإزالة المفضلة
     ========================================== -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // حذف من المفضلة عبر AJAX مع تأثير سلس
        document.querySelectorAll('.remove-favorite').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('هل أنت متأكد من إزالة هذا المنتج من المفضلة؟')) return;
                
                const productId = this.dataset.productId;
                const card = this.closest('.favorite-card');
                const btnRemove = this;
                
                // تعطيل الزر مؤقتاً
                btnRemove.disabled = true;
                btnRemove.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                
                fetch('toggle.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `product_id=${productId}&action=remove`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // تأثير إزالة سلس
                        card.style.transition = 'all 0.4s ease';
                        card.style.transform = 'scale(0.9)';
                        card.style.opacity = '0';
                        
                        setTimeout(() => {
                            card.remove();
                            
                            // التحقق من عدد المنتجات المتبقية
                            const remaining = document.querySelectorAll('.favorite-card').length;
                            if (remaining === 0) {
                                // إعادة تحميل الصفحة لعرض رسالة "قائمة فارغة"
                                window.location.href = 'index.php?removed=1';
                            } else {
                                // عرض رسالة نجاح صغيرة
                                const stats = document.querySelector('.stat-number');
                                if (stats) {
                                    stats.textContent = remaining;
                                }
                            }
                        }, 400);
                    } else {
                        alert(data.message || 'حدث خطأ');
                        btnRemove.disabled = false;
                        btnRemove.innerHTML = '<i class="bi bi-x-lg"></i>';
                    }
                })
                .catch(err => {
                    alert('حدث خطأ في الاتصال');
                    btnRemove.disabled = false;
                    btnRemove.innerHTML = '<i class="bi bi-x-lg"></i>';
                });
            });
        });
    });
</script>
</body>
</html>